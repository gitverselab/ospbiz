<?php
require_once "../config/database.php";
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$id = $_POST['id'];
$customer_id = $_POST['customer_id'];
$rts_number = $_POST['rts_number'];
$rd_number = $_POST['rd_number'];
$po_number = $_POST['po_number'];
$rts_date = $_POST['rts_date'];
$item_code = $_POST['item_code'];
$description = $_POST['description'];
$qty = $_POST['quantity'];
$total = $_POST['total_amount'];
$uom = $_POST['uom'];

$stmt = $conn->prepare("UPDATE return_receipts SET customer_id=?, rts_number=?, rd_number=?, po_number=?, rts_date=?, item_code=?, description=?, quantity=?, uom=?, total_amount=? WHERE id=?");
$stmt->bind_param("issssssdsdi", $customer_id, $rts_number, $rd_number, $po_number, $rts_date, $item_code, $description, $qty, $uom, $total, $id);

if ($stmt->execute()) echo json_encode(['success' => true]);
else echo json_encode(['success' => false, 'message' => $stmt->error]);
?>