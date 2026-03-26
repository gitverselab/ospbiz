<?php
// api/add_chart_of_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$name = trim($_POST['account_name']);
$type = $_POST['account_type'];
$desc = trim($_POST['description']);

if (empty($name) || empty($type)) {
    $response['message'] = 'Account Name and Type are required.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chart_of_accounts (account_name, account_type, description) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $type, $desc);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Account added successfully.';
} else {
    $response['message'] = 'Failed to add account. It may already exist.';
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>