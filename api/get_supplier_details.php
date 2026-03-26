<?php
// api/get_supplier_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Supplier ID.']);
    exit;
}

$id = (int)$_GET['id'];
$sql = "SELECT id, supplier_name, contact_person, contact_email FROM suppliers WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($supplier = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $supplier]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Supplier not found.']);
    }
    $stmt->close();
}
$conn->close();
?>