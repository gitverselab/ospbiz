<?php
// api/add_supplier.php
require_once "../config/access_control.php"; // Assuming you use this
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid request']); exit; }

$name = trim($_POST['supplier_name'] ?? '');
$person = trim($_POST['contact_person'] ?? '');
$number = trim($_POST['contact_number'] ?? ''); // New Field
$email = trim($_POST['contact_email'] ?? '');

if (empty($name)) { echo json_encode(['success'=>false,'message'=>'Supplier Name is required']); exit; }

$stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, contact_number, contact_email) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $name, $person, $number, $email);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Supplier added successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
$stmt->close();
$conn->close();
?>