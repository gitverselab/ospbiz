<?php
// api/delete_purchase.php
session_start();
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$purchase_id = (int)$_POST['id'];

$conn->begin_transaction();
try {
    // Check for existing payments
    $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM purchase_payments WHERE purchase_id = ?");
    $stmt_check->bind_param("i", $purchase_id);
    $stmt_check->execute();
    $payments_count = $stmt_check->get_result()->fetch_assoc()['count'];

    if ($payments_count > 0) {
        throw new Exception("Cannot delete a purchase that has payments recorded against it. Please cancel it instead.");
    }

    // Since purchase_items has ON DELETE CASCADE, they will be deleted automatically.
    $stmt_delete = $conn->prepare("DELETE FROM purchases WHERE id = ?");
    $stmt_delete->bind_param("i", $purchase_id);
    $stmt_delete->execute();

    $conn->commit();
    $response['success'] = true;

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>