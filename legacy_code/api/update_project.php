<?php
// api/update_project.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = $_POST['id'];
$name = $_POST['project_name'];
$desc = $_POST['description'];
$start = $_POST['start_date'];
$end = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
$budget = (float)$_POST['budget'];
$status = $_POST['status'];

$stmt = $conn->prepare("UPDATE projects SET project_name=?, description=?, start_date=?, end_date=?, budget=?, status=? WHERE id=?");
$stmt->bind_param("ssssdsi", $name, $desc, $start, $end, $budget, $status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Project updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
?>