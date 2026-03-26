<?php
// api/sync_balances.php
require_once "../config/database.php";

$conn->begin_transaction();
try {
    // 1. Sync Cash Accounts
    $accounts = $conn->query("SELECT id FROM cash_accounts");
    while ($acc = $accounts->fetch_assoc()) {
        $id = $acc['id'];
        $stmt = $conn->query("SELECT SUM(credit) - SUM(debit) as true_bal FROM cash_transactions WHERE cash_account_id = $id");
        $true_bal = $stmt->fetch_assoc()['true_bal'] ?? 0;
        
        $conn->query("UPDATE cash_accounts SET current_balance = $true_bal WHERE id = $id");
    }

    // 2. Sync Passbooks
    $passbooks = $conn->query("SELECT id FROM passbooks");
    while ($pb = $passbooks->fetch_assoc()) {
        $id = $pb['id'];
        $stmt = $conn->query("SELECT SUM(credit) - SUM(debit) as true_bal FROM passbook_transactions WHERE passbook_id = $id");
        $true_bal = $stmt->fetch_assoc()['true_bal'] ?? 0;
        
        $conn->query("UPDATE passbooks SET current_balance = $true_bal WHERE id = $id");
    }

    $conn->commit();
    echo "<div style='font-family: sans-serif; padding: 20px; background: #dcfce7; color: #166534; border-radius: 8px; max-width: 600px; margin: 50px auto; border: 1px solid #bbf7d0;'>";
    echo "<h2>✅ Synchronization Complete!</h2>";
    echo "<p>All Cash and Passbook database balances have been successfully overwritten to perfectly match their true mathematical transaction history.</p>";
    echo "<a href='../cash_accounts.php' style='display: inline-block; margin-top: 15px; padding: 10px 15px; background: #166534; color: white; text-decoration: none; border-radius: 5px;'>Back to Dashboard</a>";
    echo "</div>";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
$conn->close();
?>