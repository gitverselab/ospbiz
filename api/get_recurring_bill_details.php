<?php
// api/get_recurring_bill_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'ID is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM recurring_bills WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$bill = $result->fetch_assoc();

if ($bill) {
    echo json_encode(['success' => true, 'data' => $bill]);
} else {
    echo json_encode(['success' => false, 'message' => 'Schedule not found.']);
}
$stmt->close();
$conn->close();
?>