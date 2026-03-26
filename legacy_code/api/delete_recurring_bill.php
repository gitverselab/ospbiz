<?php
// api/delete_recurring_bill.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response); exit;
}

$id = $_POST['id'];
$stmt = $conn->prepare("DELETE FROM recurring_bills WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $response['success'] = true;
} else {
    $response['message'] = 'Failed to delete schedule.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
?>