<?php
// api/update_expense.php
require_once "../config/access_control.php";
check_permission('expenses.update');

require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? null;
    $expense_date = $_POST['expense_date'] ?? null;
    $chart_of_account_id = (int)$_POST['chart_of_account_id'];
    $description = trim($_POST['description'] ?? '');
    $payment_method = $_POST['payment_method'] ?? null;
    $account_id = (int)$_POST['account_id'];
    
    $is_change_pending = isset($_POST['is_change_pending']) ? 1 : 0;
    $amount_tendered = (float)($_POST['amount_tendered'] ?? 0);
    $actual_amount = (float)($_POST['amount'] ?? 0);

    // Determine what amount hits the bank/cash NOW
    $final_deduction_amount = ($is_change_pending && $amount_tendered > 0) ? $amount_tendered : $actual_amount;

    if (empty($id) || empty($expense_date) || empty($chart_of_account_id) || empty($description) || $final_deduction_amount <= 0 || empty($payment_method) || empty($account_id)) {
        $response['message'] = 'Please fill all required fields correctly.'; echo json_encode($response); exit;
    }

    $conn->begin_transaction();
    try {
        // 1. Fetch OLD expense data to reverse it
        $stmt_old = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $old_exp = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        if (!$old_exp) throw new Exception("Original expense not found.");

        $old_deduction = ($old_exp['is_change_pending'] && $old_exp['amount_tendered'] > 0) ? $old_exp['amount_tendered'] : $old_exp['amount'];

        // 2. REVERSE the old transaction
        if ($old_exp['payment_method'] === 'Cash on Hand') {
            if (!empty($old_exp['transaction_id'])) $conn->query("DELETE FROM cash_transactions WHERE id = {$old_exp['transaction_id']}");
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $old_deduction WHERE id = {$old_exp['account_id']}");
        } elseif ($old_exp['payment_method'] === 'Bank Transfer') {
            if (!empty($old_exp['transaction_id'])) $conn->query("DELETE FROM passbook_transactions WHERE id = {$old_exp['transaction_id']}");
            $conn->query("UPDATE passbooks SET current_balance = current_balance + $old_deduction WHERE id = {$old_exp['account_id']}");
        } elseif ($old_exp['payment_method'] === 'Check') {
            if (!empty($old_exp['transaction_id'])) $conn->query("DELETE FROM checks WHERE id = {$old_exp['transaction_id']}");
        }

        // 3. APPLY the new transaction
        $new_transaction_id = null;
        if ($payment_method === 'Cash on Hand') {
            $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, ?, 0.00, ?)");
            $stmt_trans->bind_param("issdi", $account_id, $expense_date, $description, $final_deduction_amount, $chart_of_account_id);
            $stmt_trans->execute();
            $new_transaction_id = $conn->insert_id;
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $final_deduction_amount WHERE id = $account_id");

        } elseif ($payment_method === 'Bank Transfer') {
            $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, ?, 0.00, ?)");
            $stmt_trans->bind_param("issdi", $account_id, $expense_date, $description, $final_deduction_amount, $chart_of_account_id);
            $stmt_trans->execute();
            $new_transaction_id = $conn->insert_id;
            $conn->query("UPDATE passbooks SET current_balance = current_balance - $final_deduction_amount WHERE id = $account_id");

        } elseif ($payment_method === 'Check') {
            $check_number = $_POST['check_number'] ?? '';
            $check_date = $_POST['check_date'] ?? $expense_date;
            $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, status) VALUES (?, ?, ?, ?, ?, ?, 'Issued')");
            $stmt_check->bind_param("issssd", $account_id, $check_date, $expense_date, $check_number, $description, $final_deduction_amount);
            $stmt_check->execute();
            $new_transaction_id = $conn->insert_id;
        }

        // 4. UPDATE the expense record
        $stmt_update_exp = $conn->prepare("UPDATE expenses SET expense_date = ?, chart_of_account_id = ?, description = ?, amount = ?, amount_tendered = ?, is_change_pending = ?, payment_method = ?, account_id = ?, transaction_id = ? WHERE id = ?");
        $stmt_update_exp->bind_param("sisddisiii", $expense_date, $chart_of_account_id, $description, $actual_amount, $amount_tendered, $is_change_pending, $payment_method, $account_id, $new_transaction_id, $id);
        $stmt_update_exp->execute();
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Expense and ledger updated successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    $conn->close();
}
echo json_encode($response);
?>