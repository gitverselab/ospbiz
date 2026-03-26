<?php
require_once "../config/database.php";
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$customer_id = $_POST['customer_id'];
$rts_number = $_POST['rts_number']; // GR
$rd_number = $_POST['rd_number'] ?? '';
$po_number = $_POST['po_number'] ?? '';
$rts_date = $_POST['rts_date'];
$item_code = $_POST['item_code'];
$description = $_POST['description'];
$qty = $_POST['quantity'];
$price = 0; // Manual entry assumes price embedded or ignored
$total = $_POST['total_amount'];
$uom = $_POST['uom'] ?? '';

$stmt = $conn->prepare("INSERT INTO return_receipts (customer_id, rts_number, rd_number, po_number, rts_date, item_code, description, quantity, uom, price, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssssdsdd", $customer_id, $rts_number, $rd_number, $po_number, $rts_date, $item_code, $description, $qty, $uom, $price, $total);

if ($stmt->execute()) echo json_encode(['success' => true]);
else echo json_encode(['success' => false, 'message' => $stmt->error]);
?>