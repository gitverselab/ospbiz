<?php
require_once "../config/database.php";
$name = $_POST['request_name'];
$cat = $_POST['category'];
$desc = $_POST['description'];
$r_date = $_POST['request_date'];
$d_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
$cost = (float)$_POST['estimated_cost'];
$status = $_POST['status'];

$stmt = $conn->prepare("INSERT INTO requests (request_name, category, description, request_date, due_date, estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssds", $name, $cat, $desc, $r_date, $d_date, $cost, $status);

echo json_encode(['success' => $stmt->execute()]);
?>