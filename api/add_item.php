<?php
// api/add_item.php
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$item_name = trim($_POST['item_name'] ?? '');
$item_description = trim($_POST['item_description'] ?? '');
$unit = trim($_POST['unit'] ?? '');

if (empty($item_name)) {
    echo json_encode(['success' => false, 'message' => 'Item name is required.']);
    exit;
}

$sql = "INSERT INTO items (item_name, item_description, unit) VALUES (?, ?, ?)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("sss", $item_name, $item_description, $unit);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add item.']);
    }
    $stmt->close();
}
$conn->close();
?>