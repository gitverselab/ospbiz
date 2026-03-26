<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$biller_name = trim($_POST['biller_name'] ?? '');
if (empty($biller_name)) {
    echo json_encode(['success' => false, 'message' => 'Biller name is required.']); exit;
}
$stmt = $conn->prepare("INSERT INTO billers (biller_name) VALUES (?)");
$stmt->bind_param("s", $biller_name);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'A biller with this name already exists.']);
}
?>