<?php
// api/add_cash_account.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$account_name = trim($_POST['account_name']);
$initial_balance = $_POST['initial_balance'];

if (empty($account_name) || !is_numeric($initial_balance)) {
    $response['message'] = 'Please fill all required fields correctly.';
    echo json_encode($response);
    exit;
}
$initial_balance = (float)$initial_balance;

$stmt = $conn->prepare("INSERT INTO cash_accounts (account_name, current_balance) VALUES (?, ?)");
$stmt->bind_param("sd", $account_name, $initial_balance);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Cash account added successfully!';
} else {
    if ($conn->errno === 1062) {
        $response['message'] = 'An account with this name already exists.';
    } else {
        $response['message'] = 'Error: ' . $stmt->error;
    }
}
$stmt->close();
$conn->close();
echo json_encode($response);
exit;
?>