<?php
// api/delete_check.php
require_once "../config/access_control.php";
check_permission('checks.delete'); // Ensure Admin permission

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$check_id = (int)$_POST['id'];

$conn->begin_transaction();
try {
    // 1. Verify Check Status
    $stmt_c = $conn->prepare("SELECT status FROM checks WHERE id = ?");
    $stmt_c->bind_param("i", $check_id);
    $stmt_c->execute();
    $check_data = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if (!$check_data) { throw new Exception("Check not found."); }
    if ($check_data['status'] !== 'Issued') { throw new Exception("Only 'Issued' checks can be deleted. Please use Reconcile or Cancel for others."); }

    // 2. Remove Linked Payments (Purchase/Bill/Credit)
    // This "Unpays" the bill/purchase/credit
    $conn->query("DELETE FROM purchase_payments WHERE reference_id = $check_id AND payment_method = 'Check'");
    $conn->query("DELETE FROM bill_payments WHERE reference_id = $check_id AND payment_method = 'Check'");
    $conn->query("DELETE FROM credit_payments WHERE reference_id = $check_id AND payment_method = 'Check'");
    
    // Also remove from Expenses if it was a direct expense check
    $conn->query("DELETE FROM expenses WHERE transaction_id = $check_id AND payment_method = 'Check'");

    // 3. Delete the Check
    $stmt_del = $conn->prepare("DELETE FROM checks WHERE id = ?");
    $stmt_del->bind_param("i", $check_id);
    if ($stmt_del->execute()) {
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Check deleted successfully.';
    } else {
        throw new Exception("Failed to delete check.");
    }

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>