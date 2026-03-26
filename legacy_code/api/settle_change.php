<?php
// api/settle_change.php
require_once "../config/access_control.php";
check_permission('expenses.update');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || empty($_POST['actual_amount'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$expense_id = (int)$_POST['id'];
$actual_amount = (float)$_POST['actual_amount'];

$conn->begin_transaction();
try {
    // 1. Get current expense details
    $stmt_get = $conn->prepare("SELECT * FROM expenses WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $expense_id);
    $stmt_get->execute();
    $expense = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$expense) { throw new Exception("Expense not found."); }
    if ($expense['is_change_pending'] == 0) { throw new Exception("This expense is already settled."); }

    $amount_given = (float)$expense['amount_tendered'];
    $previous_recorded_amount = (float)$expense['amount']; // This was the full amount given
    $account_id = $expense['account_id'];
    $trans_id = $expense['transaction_id'];
    
    // Validate amounts
    if ($actual_amount > $amount_given) {
        throw new Exception("Actual amount cannot be higher than amount given.");
    }

    $change_to_return = $amount_given - $actual_amount;

    // 2. Update Expense Record
    // Set pending to 0, update amount to actual cost
    $stmt_upd = $conn->prepare("UPDATE expenses SET amount = ?, is_change_pending = 0 WHERE id = ?");
    $stmt_upd->bind_param("di", $actual_amount, $expense_id);
    $stmt_upd->execute();
    $stmt_upd->close();

    // 3. Update Financial Records
    if ($expense['payment_method'] === 'Cash on Hand' && $trans_id) {
        // Option A: Update the original transaction (Cleaner for reports)
        $stmt_trans = $conn->prepare("UPDATE cash_transactions SET debit = ?, description = REPLACE(description, ' (Change Pending)', '') WHERE id = ?");
        $stmt_trans->bind_param("di", $actual_amount, $trans_id);
        $stmt_trans->execute();
        
        // Option B: Adjust the Cash Balance (Add the change back)
        $stmt_bal = $conn->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?");
        $stmt_bal->bind_param("di", $change_to_return, $account_id);
        $stmt_bal->execute();
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Change settled. Cash balance adjusted by +₱' . number_format($change_to_return, 2);

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>