<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$id = $_POST['id'] ?? '';
$biller_name = trim($_POST['biller_name'] ?? '');
if (empty($id) || empty($biller_name)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
}
$stmt = $conn->prepare("UPDATE billers SET biller_name = ? WHERE id = ?");
$stmt->bind_param("si", $biller_name, $id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed.']);
}
?>