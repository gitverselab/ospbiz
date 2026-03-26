<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$id = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT id, biller_name FROM billers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$biller = $result->fetch_assoc();
echo json_encode(['success' => !!$biller, 'data' => $biller]);
?>