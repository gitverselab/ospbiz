<?php
// api/delete_cash_transaction.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    // We fetch account_id from DB to be safe
    
    $conn->begin_transaction();
    try {
        // 1. Get the transaction details
        $stmt_get = $conn->prepare("SELECT cash_account_id, debit, credit FROM cash_transactions WHERE id = ? FOR UPDATE");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $transaction = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$transaction) {
            throw new Exception("Transaction not found.");
        }
        
        $cash_account_id = $transaction['cash_account_id'];

        // 2. Calculate the effect that needs to be REMOVED.
        // If it was Credit 100 (Add 100), we must Subtract 100.
        // If it was Debit 100 (Subtract 100), we must Add 100.
        $net_effect = $transaction['credit'] - $transaction['debit'];

        // 3. Delete the transaction
        $stmt_del = $conn->prepare("DELETE FROM cash_transactions WHERE id = ?");
        $stmt_del->bind_param("i", $id);
        $stmt_del->execute();
        $stmt_del->close();

        // 4. Reverse the balance (Subtract the net effect)
        $stmt_update = $conn->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_update->bind_param("di", $net_effect, $cash_account_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Transaction deleted successfully.';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Delete failed: ' . $e->getMessage();
    }
    $conn->close();
}
echo json_encode($response);
exit;
?>