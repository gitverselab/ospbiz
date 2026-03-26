<?php
// api/add_cash_transaction.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$cash_account_id = (int)$_POST['cash_account_id'];
$date = $_POST['transaction_date'];
$type = $_POST['type'];
$chart_of_account_id = (int)$_POST['chart_of_account_id'];
$amount = (float)$_POST['amount'];
$description = trim($_POST['description']);

if (empty($date) || empty($type) || empty($chart_of_account_id) || !isset($amount)) {
    $response['message'] = 'Please fill all required fields.';
    echo json_encode($response);
    exit;
}

$debit = ($type === 'Debit') ? $amount : 0.00;
$credit = ($type === 'Credit') ? $amount : 0.00;

$conn->begin_transaction();
try {
    // Get current balance before this transaction
    $bal_stmt = $conn->prepare("SELECT current_balance FROM cash_accounts WHERE id = ?");
    $bal_stmt->bind_param("i", $cash_account_id);
    $bal_stmt->execute();
    $current_balance = $bal_stmt->get_result()->fetch_assoc()['current_balance'];
    $bal_stmt->close();

    $new_balance = $current_balance + $credit - $debit;

    // Insert the transaction with the new balance
    $stmt = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, credit, balance, chart_of_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdddi", $cash_account_id, $date, $description, $debit, $credit, $new_balance, $chart_of_account_id);
    $stmt->execute();
    $stmt->close();

    // Update the cash_accounts table with the new balance
    $stmt_update = $conn->prepare("UPDATE cash_accounts SET current_balance = ? WHERE id = ?");
    $stmt_update->bind_param("di", $new_balance, $cash_account_id);
    $stmt_update->execute();
    $stmt_update->close();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Transaction added successfully!';
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Failed to add transaction: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
exit;
?>