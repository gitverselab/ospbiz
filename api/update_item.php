<?php
// api/update_item.php
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id = trim($_POST['id'] ?? '');
$item_name = trim($_POST['item_name'] ?? '');
$item_description = trim($_POST['item_description'] ?? '');
$unit = trim($_POST['unit'] ?? '');

if (empty($id) || empty($item_name)) {
    echo json_encode(['success' => false, 'message' => 'Item ID and Name are required.']);
    exit;
}

$sql = "UPDATE items SET item_name = ?, item_description = ?, unit = ? WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sssi", $item_name, $item_description, $unit, $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update item.']);
    }
    $stmt->close();
}
$conn->close();
?>