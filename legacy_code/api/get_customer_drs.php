<?php
// api/get_customer_drs.php
require_once "../config/database.php";
header('Content-Type: application/json');

$customer_id = $_GET['customer_id'] ?? null;
if (empty($customer_id)) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required.']);
    exit;
}

$sql = "SELECT id, delivery_date, dr_number, vat_inclusive_amount 
        FROM delivery_receipts 
        WHERE customer_id = ? AND is_invoiced = 0 
        ORDER BY delivery_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$drs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'drs' => $drs]);
?>