<?php
// api/delete_chart_of_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}
$id = (int)$_POST['id'];

$stmt = $conn->prepare("DELETE FROM chart_of_accounts WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Account deleted successfully.';
} else {
    $response['message'] = 'Failed to delete account. It might be in use.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>