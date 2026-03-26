<?php
// api/delete_passbook.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}
$id = (int)$_POST['id'];

// Check for associated transactions before deleting
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM passbook_transactions WHERE passbook_id = ?");
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($result['count'] > 0) {
    $response['message'] = 'Cannot delete passbook. It has associated transactions.';
    echo json_encode($response);
    exit;
}

// Proceed with deletion if no transactions exist
$stmt = $conn->prepare("DELETE FROM passbooks WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Passbook deleted successfully.';
} else {
    $response['message'] = 'Failed to delete passbook.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>