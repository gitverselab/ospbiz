<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$id = $_POST['id'] ?? 0;
$conn->query("DELETE FROM return_receipts WHERE id=$id AND status='Pending'");
echo json_encode(['success' => true]);
?>