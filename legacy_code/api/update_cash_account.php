<?php
// api/update_cash_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = $_POST['id'];
$account_name = trim($_POST['account_name']);

if (empty($id) || empty($account_name)) {
    $response['message'] = 'Account name cannot be empty.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("UPDATE cash_accounts SET account_name = ? WHERE id = ?");
$stmt->bind_param("si", $account_name, $id);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Account updated successfully.';
} else {
     $response['message'] = 'Failed to update account.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>