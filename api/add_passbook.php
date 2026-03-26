<?php
// api/add_passbook.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$bank_name = trim($_POST['bank_name']);
$account_number = trim($_POST['account_number']);
$account_holder = trim($_POST['account_holder']);
$initial_balance = $_POST['initial_balance'];

if (empty($bank_name) || empty($account_number) || empty($account_holder) || !is_numeric($initial_balance)) {
    $response['message'] = 'Please fill all required fields correctly.';
    echo json_encode($response);
    exit;
}
$initial_balance = (float)$initial_balance;

$stmt = $conn->prepare("INSERT INTO passbooks (bank_name, account_number, account_holder, initial_balance, current_balance) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssdd", $bank_name, $account_number, $account_holder, $initial_balance, $initial_balance);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Passbook added successfully!';
} else {
    if ($conn->errno === 1062) {
        $response['message'] = 'An account with this number already exists.';
    } else {
        $response['message'] = 'Error: ' . $stmt->error;
    }
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>