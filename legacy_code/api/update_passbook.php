<?php
// api/update_passbook.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$id = $_POST['id'];
$bank_name = trim($_POST['bank_name']);
$account_number = trim($_POST['account_number']);
$account_holder = trim($_POST['account_holder']);

if (empty($id) || empty($bank_name) || empty($account_number) || empty($account_holder)) {
    $response['message'] = 'Please fill all required fields.';
    echo json_encode($response);
    exit;
}

// Balance is not updated from this form.
$stmt = $conn->prepare("UPDATE passbooks SET bank_name = ?, account_number = ?, account_holder = ? WHERE id = ?");
$stmt->bind_param("sssi", $bank_name, $account_number, $account_holder, $id);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Passbook updated successfully.';
} else {
    $response['message'] = 'Failed to update passbook.';
}

$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>