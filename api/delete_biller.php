<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$id = $_POST['id'] ?? '';
$stmt = $conn->prepare("DELETE FROM billers WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed. Biller might be in use.']);
}
?>