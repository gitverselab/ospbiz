<?php
// api/get_item_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Item ID.']);
    exit;
}

$id = (int)$_GET['id'];
$sql = "SELECT id, item_name, item_description, unit FROM items WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($item = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
    }
    $stmt->close();
}
$conn->close();
?>