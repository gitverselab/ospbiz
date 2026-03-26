<?php
// api/update_supplier.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request']); exit; }

$id = $_POST['id'] ?? '';
$name = trim($_POST['supplier_name'] ?? '');
$person = trim($_POST['contact_person'] ?? '');
$number = trim($_POST['contact_number'] ?? ''); // New Field
$email = trim($_POST['contact_email'] ?? '');

if (empty($id) || empty($name)) { echo json_encode(['success'=>false,'message'=>'ID and Name required']); exit; }

$stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, contact_person=?, contact_number=?, contact_email=? WHERE id=?");
$stmt->bind_param("ssssi", $name, $person, $number, $email, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Supplier updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>