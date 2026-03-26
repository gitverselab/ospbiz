<?php
// api/update_cash_transaction.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $cash_account_id = $_POST['cash_account_id'];
    $transaction_date = $_POST['transaction_date'];
    $description = trim($_POST['description']);
    $type = $_POST['type']; // 'Debit' or 'Credit'
    $amount = (float)$_POST['amount'];

    $conn->begin_transaction();
    try {
        // 1. Get the OLD transaction
        $stmt_get = $conn->prepare("SELECT debit, credit FROM cash_transactions WHERE id = ? FOR UPDATE");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $old_transaction = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        // 2. Calculate the NET effect of the old transaction (Credit adds, Debit subtracts)
        $old_net_effect = $old_transaction['credit'] - $old_transaction['debit'];

        // 3. Calculate the NET effect of the new values
        $new_debit = ($type === 'Debit') ? $amount : 0;
        $new_credit = ($type === 'Credit') ? $amount : 0;
        $new_net_effect = $new_credit - $new_debit;
        
        // 4. Calculate the difference to apply (New - Old)
        $balance_adjustment = $new_net_effect - $old_net_effect;

        // 5. Update the transaction itself
        $stmt_update = $conn->prepare("UPDATE cash_transactions SET transaction_date = ?, description = ?, debit = ?, credit = ? WHERE id = ?");
        $stmt_update->bind_param("ssddi", $transaction_date, $description, $new_debit, $new_credit, $id);
        $stmt_update->execute();
        $stmt_update->close();

        // 6. Apply the adjustment to the account's current balance
        // If adjustment is positive, balance increases. If negative, it decreases.
        $stmt_balance = $conn->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?");
        $stmt_balance->bind_param("di", $balance_adjustment, $cash_account_id);
        $stmt_balance->execute();
        $stmt_balance->close();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Transaction updated successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Update failed: ' . $e->getMessage();
    }
    $conn->close();
}
echo json_encode($response);
exit;
?>