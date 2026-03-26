<?php
// api/delete_bill.php
require_once "../config/access_control.php";
check_permission('bills.delete');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = $_POST['id'] ?? null;
if (empty($id)) {
    $response['message'] = 'Bill ID is required.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    // SAFETY CHECK: Prevent deletion if payments exist to protect ledger balances!
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM bill_payments WHERE bill_id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $payments_count = $stmt_check->get_result()->fetch_assoc()['count'];
    $stmt_check->close();

    if ($payments_count > 0) {
        throw new Exception("Cannot delete a bill that has payments recorded against it. Please delete the payments first to restore your balances.");
    }

    // Proceed with safe deletion
    $sql = "DELETE FROM bills WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Bill deleted successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>