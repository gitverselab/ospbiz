<?php
// api/add_project.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$name = $_POST['project_name'];
$desc = $_POST['description'];
$start = $_POST['start_date'];
$end = $_POST['end_date'];
$budget = (float)$_POST['budget'];
$status = $_POST['status'];

$stmt = $conn->prepare("INSERT INTO projects (project_name, description, start_date, end_date, budget, status) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssds", $name, $desc, $start, $end, $budget, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Project created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
?>