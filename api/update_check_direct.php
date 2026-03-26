<?php
// api/update_check_direct.php
header('Content-Type: application/json');
require_once "../config/database.php";

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'];
$status = $data['status'];
$response = ['success' => false];

// Only allow certain direct status changes
if (!in_array($status, ['Canceled'])) {
    $response['error'] = 'Invalid status change.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("UPDATE checks SET status = ? WHERE id = ? AND status = 'Issued'");
$stmt->bind_param("si", $status, $id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    $response['success'] = true;
} else {
    $response['error'] = 'Could not update check. It may have already been cleared or canceled.';
}

$stmt->close();
$conn->close();
echo json_encode($response);
?>
