<?php
// api/delete_supplier.php
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$id = trim($_POST['id'] ?? '');

if (empty($id)) {
    echo json_encode(['success' => false, 'message' => 'Supplier ID is required.']);
    exit;
}

$sql = "DELETE FROM suppliers WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete supplier.']);
    }
    $stmt->close();
}
$conn->close();
?>