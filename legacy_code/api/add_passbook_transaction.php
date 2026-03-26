<?php
// api/add_passbook_transaction.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$passbook_id = (int)$_POST['passbook_id'];
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
    // Insert the transaction using debit/credit columns
    $stmt = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddi", $passbook_id, $date, $description, $debit, $credit, $chart_of_account_id);
    $stmt->execute();
    $stmt->close();

    // Update the passbook balance
    $balance_change = $credit - $debit;
    $stmt_update = $conn->prepare("UPDATE passbooks SET current_balance = current_balance + ? WHERE id = ?");
    $stmt_update->bind_param("di", $balance_change, $passbook_id);
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