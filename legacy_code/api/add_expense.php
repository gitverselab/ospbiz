<?php
// api/add_expense.php
require_once "../config/access_control.php";
check_permission('expenses.create');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$expense_date = $_POST['expense_date'] ?? date('Y-m-d');
$chart_of_account_id = $_POST['chart_of_account_id'] ?? null;
$description = trim($_POST['description'] ?? '');
$payment_method = $_POST['payment_method'] ?? null;
$account_id = $_POST['account_id'] ?? null; // Source ID (Cash or Bank)

// LINKING FIELDS
// Capture Project ID (Allow null)
$project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
// Capture Request ID (Allow null)
$request_id = !empty($_POST['request_id']) ? $_POST['request_id'] : null;

// New Fields for Cash Change logic
$is_change_pending = isset($_POST['is_change_pending']) ? 1 : 0;
$amount_tendered = (float)($_POST['amount_tendered'] ?? 0);
$actual_amount = (float)($_POST['amount'] ?? 0);

// Validation
if (empty($chart_of_account_id) || empty($description) || empty($payment_method) || empty($account_id)) {
    $response['message'] = 'Missing required fields.'; echo json_encode($response); exit;
}

// LOGIC: If Change is Pending, we deduct the FULL amount given (Tendered) now.
// If NOT pending, we deduct the ACTUAL expense amount.
$final_deduction_amount = ($is_change_pending && $amount_tendered > 0) ? $amount_tendered : $actual_amount;

if ($final_deduction_amount <= 0) {
    $response['message'] = 'Invalid amount.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    $transaction_id = null;
    $desc_suffix = $is_change_pending ? " (Change Pending)" : "";
    $desc_full = $description . $desc_suffix;

    // 1. Handle Financial Transaction (Deduct Money)
    if ($payment_method === 'Cash on Hand') {
        // Deduct from Cash Account
        $stmt_bal = $conn->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_bal->bind_param("di", $final_deduction_amount, $account_id);
        $stmt_bal->execute();
        $stmt_bal->close();

        // Record Cash Transaction
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $expense_date, $desc_full, $final_deduction_amount, $chart_of_account_id);
        $stmt_trans->execute();
        $transaction_id = $conn->insert_id;
        $stmt_trans->close();

    } elseif ($payment_method === 'Bank Transfer') {
        // Deduct from Bank
        $stmt_bal = $conn->prepare("UPDATE passbooks SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_bal->bind_param("di", $final_deduction_amount, $account_id);
        $stmt_bal->execute();
        $stmt_bal->close();

        // Record Bank Transaction
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $expense_date, $desc_full, $final_deduction_amount, $chart_of_account_id);
        $stmt_trans->execute();
        $transaction_id = $conn->insert_id;
        $stmt_trans->close();

    } elseif ($payment_method === 'Check') {
        // Issue Check (No immediate deduction from balance, but record the check)
        $check_number = $_POST['check_number'] ?? '';
        $check_date = $_POST['check_date'] ?? $expense_date;
        
        $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, status) VALUES (?, ?, ?, ?, ?, ?, 'Issued')");
        $stmt_check->bind_param("issssd", $account_id, $check_date, $expense_date, $check_number, $description, $final_deduction_amount);
        $stmt_check->execute();
        $transaction_id = $conn->insert_id; // The Check ID becomes the reference
        $stmt_check->close();
    }

    // 2. Insert Expense Record (Updated to include project_id AND request_id)
    $stmt = $conn->prepare("INSERT INTO expenses (expense_date, chart_of_account_id, description, amount, amount_tendered, is_change_pending, payment_method, account_id, transaction_id, project_id, request_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // Type string explanation: s=string, i=int, d=double. Added 'i' at the end for request_id.
    $stmt->bind_param("sisddisiiii", $expense_date, $chart_of_account_id, $description, $actual_amount, $amount_tendered, $is_change_pending, $payment_method, $account_id, $transaction_id, $project_id, $request_id);
    
    if ($stmt->execute()) {
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Expense added successfully!';
    } else {
        throw new Exception("Database Error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>