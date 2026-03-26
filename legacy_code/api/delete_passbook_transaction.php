<?php
// api/delete_passbook_transaction.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id']; // Transaction ID

    $conn->begin_transaction();
    try {
        // 1. Get the transaction details
        $stmt_get = $conn->prepare("SELECT passbook_id, debit, credit FROM passbook_transactions WHERE id = ? FOR UPDATE");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $transaction = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        if (!$transaction) { throw new Exception("Transaction not found."); }
        
        $passbook_id = $transaction['passbook_id'];

        // 2. Calculate the net effect (Credit - Debit)
        $net_effect = $transaction['credit'] - $transaction['debit'];

        // 3. Delete the transaction
        $stmt_del = $conn->prepare("DELETE FROM passbook_transactions WHERE id = ?");
        $stmt_del->bind_param("i", $id);
        $stmt_del->execute();
        $stmt_del->close();

        // 4. Reverse the balance (Subtract the net effect)
        $stmt_update = $conn->prepare("UPDATE passbooks SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_update->bind_param("di", $net_effect, $passbook_id);
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