<?php
// api/update_check_status.php
session_start();
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || empty($_POST['status'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$check_id = (int)$_POST['id'];
$new_status = $_POST['status'];

// Only allow these specific status changes from this script
if (!in_array($new_status, ['Canceled', 'Bounced'])) {
    $response['message'] = 'Invalid or unauthorized status change.';
    echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Update the check's status first
    $stmt_update_check = $conn->prepare("UPDATE checks SET status = ? WHERE id = ? AND status = 'Issued'");
    $stmt_update_check->bind_param("si", $new_status, $check_id);
    $stmt_update_check->execute();
    
    if ($stmt_update_check->affected_rows === 0) {
        throw new Exception("Check could not be updated. It may have already been cleared or is not in an 'Issued' state.");
    }
    $stmt_update_check->close();

    // 2. Find and delete the corresponding payment record to reverse the payment
    $payment_found = false;
    $payment_tables = [
        'purchase_payments' => ['link_column' => 'purchase_id', 'parent_table' => 'purchases'],
        'bill_payments' => ['link_column' => 'bill_id', 'parent_table' => 'bills'],
        'credit_payments' => ['link_column' => 'credit_id', 'parent_table' => 'credits']
    ];

    foreach ($payment_tables as $ptable => $pdetails) {
        $stmt_find = $conn->prepare("SELECT id, {$pdetails['link_column']} FROM {$ptable} WHERE reference_id = ? AND payment_method = 'Check'");
        $stmt_find->bind_param("i", $check_id);
        $stmt_find->execute();
        $payment = $stmt_find->get_result()->fetch_assoc();
        $stmt_find->close();

        if ($payment) {
            $payment_found = true;
            $parent_id = $payment[$pdetails['link_column']];

            // Delete the payment record
            $stmt_delete = $conn->prepare("DELETE FROM {$ptable} WHERE id = ?");
            $stmt_delete->bind_param("i", $payment['id']);
            $stmt_delete->execute();
            $stmt_delete->close();

            // 3. Recalculate the status of the parent item
            $stmt_total = $conn->prepare("SELECT amount FROM {$pdetails['parent_table']} WHERE id = ?");
            $stmt_total->bind_param("i", $parent_id);
            $stmt_total->execute();
            $total_amount = $stmt_total->get_result()->fetch_assoc()['amount'];
            $stmt_total->close();

            $stmt_paid = $conn->prepare("SELECT SUM(amount) as total_paid FROM {$ptable} WHERE {$pdetails['link_column']} = ?");
            $stmt_paid->bind_param("i", $parent_id);
            $stmt_paid->execute();
            $total_paid = $stmt_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;
            $stmt_paid->close();

            // Determine new status based on parent type
            $default_status = 'Unpaid';
            if ($pdetails['parent_table'] === 'credits') $default_status = 'Received';

            $new_parent_status = $default_status;
            if (abs($total_paid - $total_amount) < 0.01) {
                $new_parent_status = 'Paid';
            } elseif ($total_paid > 0) {
                $new_parent_status = 'Partially Paid';
            }
            
            $stmt_update_parent = $conn->prepare("UPDATE {$pdetails['parent_table']} SET status = ? WHERE id = ?");
            $stmt_update_parent->bind_param("si", $new_parent_status, $parent_id);
            $stmt_update_parent->execute();
            $stmt_update_parent->close();
            
            break; // Stop searching once the payment is found and processed
        }
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Check status updated and payment reversed successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>