<?php
// api/clear_delivery_receipts.php
session_start();
require_once "../config/database.php";

$status = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['customer_id'])) {
    $status['message'] = 'Invalid request.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}

$customer_id = (int)$_POST['customer_id'];

if (empty($customer_id)) {
    $status['message'] = 'No customer was selected.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}

// Check for related sales records before deleting.
$check_sales_sql = "SELECT COUNT(*) as count FROM sales_items si JOIN delivery_receipts dr ON si.delivery_receipt_id = dr.id WHERE dr.customer_id = ?";
$stmt_check = $conn->prepare($check_sales_sql);
$stmt_check->bind_param("i", $customer_id);
$stmt_check->execute();
$result = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($result['count'] > 0) {
    $status['message'] = 'Cannot delete: Some of these delivery receipts are already linked to existing invoices. Please delete the related invoices first.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}


// Proceed with deletion if no linked sales exist
$delete_sql = "DELETE FROM delivery_receipts WHERE customer_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->bind_param("i", $customer_id);

if ($stmt->execute()) {
    $status['success'] = true;
    $status['message'] = "Successfully deleted " . $stmt->affected_rows . " delivery receipts for the selected customer.";
} else {
    $status['message'] = "Error deleting records: " . $conn->error;
}

$stmt->close();
$conn->close();

$_SESSION['import_status'] = $status;
header("Location: ../import_delivery_receipts.php");
exit;
?>
