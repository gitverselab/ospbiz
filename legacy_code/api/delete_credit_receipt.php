<?php
// api/delete_credit_receipt.php
require_once "../config/access_control.php";
check_permission('credits.update'); // Or 'credits.delete' depending on your roles

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$receipt_id = (int)$_POST['id'];

$conn->begin_transaction();
try {
    // 1. Get receipt details
    $stmt_get = $conn->prepare("SELECT * FROM credit_receipts WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $receipt_id);
    $stmt_get->execute();
    $receipt = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$receipt) { throw new Exception("Receipt not found."); }
    
    $credit_id = $receipt['credit_id'];
    $amount = $receipt['amount'];
    $ref_id = $receipt['reference_id'];
    $account_type = $receipt['account_type'];
    $account_id = $receipt['account_id'];

    // 2. Reverse the financial transaction (Subtract the money back out)
    if ($account_type === 'Passbook' && $ref_id) {
        $conn->query("UPDATE passbooks SET current_balance = current_balance - $amount WHERE id = $account_id");
        $conn->query("DELETE FROM passbook_transactions WHERE id = $ref_id");
    } elseif ($account_type === 'Cash' && $ref_id) {
        $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $amount WHERE id = $account_id");
        $conn->query("DELETE FROM cash_transactions WHERE id = $ref_id");
    }

    // 3. Delete the receipt record
    $conn->query("DELETE FROM credit_receipts WHERE id = $receipt_id");

    // 4. Recalculate Credit Status
    $credit_stmt = $conn->query("SELECT amount FROM credits WHERE id = $credit_id");
    $credit_amount = $credit_stmt->fetch_assoc()['amount'];

    $rcv_stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM credit_receipts WHERE credit_id = $credit_id");
    $total_received = $rcv_stmt->fetch_assoc()['total'];

    $paid_stmt = $conn->query("SELECT COALESCE(SUM(cp.principal_amount), 0) as total_paid FROM credit_payments cp LEFT JOIN checks c ON cp.reference_id = c.id AND cp.payment_method = 'Check' WHERE cp.credit_id = $credit_id AND (cp.payment_method != 'Check' OR c.status = 'Cleared')");
    $total_paid = $paid_stmt->fetch_assoc()['total_paid'];

    $new_status = 'Pending';
    if ($total_paid >= $credit_amount) { $new_status = 'Paid'; }
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }
    elseif ($total_received >= $credit_amount) { $new_status = 'Received'; }
    elseif ($total_received > 0) { $new_status = 'Partially Received'; }

    $conn->query("UPDATE credits SET status = '$new_status' WHERE id = $credit_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Receipt deleted and funds reversed successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>