<?php
// api/update_chart_of_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = $_POST['id'];
$name = trim($_POST['account_name']);
$type = $_POST['account_type'];
$desc = trim($_POST['description']);

if (empty($id) || empty($name) || empty($type)) {
    $response['message'] = 'Account Name and Type are required.';
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("UPDATE chart_of_accounts SET account_name = ?, account_type = ?, description = ? WHERE id = ?");
$stmt->bind_param("sssi", $name, $type, $desc, $id);

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