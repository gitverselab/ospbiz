<?php
// api/delete_expense.php
require_once "../config/access_control.php";
check_permission('expenses.delete');

require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? null;
    if (!$id) { 
        $response['message'] = 'ID is required.'; echo json_encode($response); exit; 
    }

    $conn->begin_transaction();
    try {
        // 1. Get expense details
        $stmt_get = $conn->prepare("SELECT * FROM expenses WHERE id = ? FOR UPDATE");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $expense = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();
        
        if (!$expense) { throw new Exception("Expense not found."); }

        // Determine the EXACT amount that was originally deducted from the bank/cash
        $original_deduction = ($expense['is_change_pending'] && $expense['amount_tendered'] > 0) ? $expense['amount_tendered'] : $expense['amount'];

        // 2. Reverse the transaction safely
        if ($expense['payment_method'] === 'Cash on Hand') {
            if (!empty($expense['transaction_id'])) {
                $conn->query("DELETE FROM cash_transactions WHERE id = {$expense['transaction_id']}");
            }
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $original_deduction WHERE id = {$expense['account_id']}");

        } elseif ($expense['payment_method'] === 'Bank Transfer') {
            if (!empty($expense['transaction_id'])) {
                $conn->query("DELETE FROM passbook_transactions WHERE id = {$expense['transaction_id']}");
            }
            $conn->query("UPDATE passbooks SET current_balance = current_balance + $original_deduction WHERE id = {$expense['account_id']}");

        } elseif ($expense['payment_method'] === 'Check') {
            if (!empty($expense['transaction_id'])) {
                $conn->query("DELETE FROM checks WHERE id = {$expense['transaction_id']}");
            }
        }

        // 3. Delete the expense
        $stmt_del_exp = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt_del_exp->bind_param("i", $id);
        $stmt_del_exp->execute();
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Expense deleted and balances restored successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    $conn->close();
}
echo json_encode($response);
?>