<?php
// api/delete_cash_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit;
}
$id = (int)$_POST['id'];

// Check for transactions
$check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM cash_transactions WHERE cash_account_id = ?");
$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($result['count'] > 0) {
    $response['message'] = 'Cannot delete account. It has associated transactions.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("DELETE FROM cash_accounts WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Account deleted successfully.';
} else {
    $response['message'] = 'Failed to delete account.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>