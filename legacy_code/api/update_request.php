<?php
// api/update_request.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$is_admin = false;
// Check if Admin (Session or DB check)
if ((isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') || (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)) {
    $is_admin = true;
}

$id = $_POST['id'];
$name = $_POST['request_name'];
$cat = $_POST['category'];
$desc = $_POST['description'];
$r_date = $_POST['request_date'];
$d_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
$cost = (float)$_POST['estimated_cost'];
$new_status = $_POST['status'];

// SECURITY CHECK: If not admin, force status to remain what it currently is in the DB
if (!$is_admin) {
    $stmt_check = $conn->prepare("SELECT status FROM requests WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $current = $stmt_check->get_result()->fetch_assoc();
    $new_status = $current['status']; // Ignore user input for status
}

$stmt = $conn->prepare("UPDATE requests SET request_name=?, category=?, description=?, request_date=?, due_date=?, estimated_cost=?, status=? WHERE id=?");
$stmt->bind_param("sssssdsi", $name, $cat, $desc, $r_date, $d_date, $cost, $new_status, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
}
?>