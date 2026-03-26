<?php
// api/add_fund_transfer.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] !== "POST") { echo json_encode(['message'=>'Invalid']); exit; }

$from_raw = $_POST['from_account'];
$to_raw = $_POST['to_account'];
$amt = (float)$_POST['amount'];
$date = $_POST['transfer_date'];
$notes = trim($_POST['notes'] ?? ''); 
$method = $_POST['transfer_method'] ?? 'Direct';

if (empty($from_raw) || empty($to_raw) || $amt <= 0 || $from_raw === $to_raw) {
    echo json_encode(['message' => 'Invalid input']); exit;
}

list($from_type, $from_id) = explode('-', $from_raw); 
list($to_type, $to_id) = explode('-', $to_raw);       

$conn->begin_transaction();
try {
    $tid1 = null;
    $tid2 = null;
    $check_id = null;
    
    $from_type_label = ($from_type == 'passbook') ? 'Passbook' : 'Cash Account';
    $to_type_label   = ($to_type == 'passbook') ? 'Passbook' : 'Cash Account';

    // ---------------------------------------------------------
    // A. IF CHECK: Create Check Record ONLY (No Money Movement)
    // ---------------------------------------------------------
    if ($method === 'Check') {
        if ($from_type !== 'passbook') throw new Exception("Checks require bank source.");
        
        $stmt_c = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, status) VALUES (?, ?, ?, ?, 'Transfer', ?, 'Issued')");
        $stmt_c->bind_param("isssd", $from_id, $_POST['check_date'], $date, $_POST['check_number'], $amt);
        $stmt_c->execute();
        $check_id = $conn->insert_id;
        
        // Notice: We do NOT update balances or create ledger entries here.
    }

    // ---------------------------------------------------------
    // B. IF DIRECT: Move Money Immediately
    // ---------------------------------------------------------
    if ($method !== 'Check') {
        // 1. Update Balances
        $tbl_from = ($from_type == 'passbook') ? 'passbooks' : 'cash_accounts';
        $conn->query("UPDATE $tbl_from SET current_balance = current_balance - $amt WHERE id = $from_id");

        $tbl_to = ($to_type == 'passbook') ? 'passbooks' : 'cash_accounts';
        $conn->query("UPDATE $tbl_to SET current_balance = current_balance + $amt WHERE id = $to_id");

        // 2. Ledger Transactions
        // Source (Debit/Out)
        $desc1 = "Transfer to " . $to_type_label . " #" . $to_id;
        $tbl1 = ($from_type == 'passbook') ? 'passbook_transactions' : 'cash_transactions';
        $col1 = ($from_type == 'passbook') ? 'passbook_id' : 'cash_account_id';
        $conn->query("INSERT INTO $tbl1 ($col1, transaction_date, description, debit, credit) VALUES ($from_id, '$date', '$desc1', $amt, 0.00)");
        $tid1 = $conn->insert_id;

        // Destination (Credit/In)
        $desc2 = "Transfer from " . $from_type_label . " #" . $from_id;
        $tbl2 = ($to_type == 'passbook') ? 'passbook_transactions' : 'cash_transactions';
        $col2 = ($to_type == 'passbook') ? 'passbook_id' : 'cash_account_id';
        $conn->query("INSERT INTO $tbl2 ($col2, transaction_date, description, debit, credit) VALUES ($to_id, '$date', '$desc2', 0.00, $amt)");
        $tid2 = $conn->insert_id;
    }

    // ---------------------------------------------------------
    // C. Log Main Record
    // ---------------------------------------------------------
    // If Check, $tid1 and $tid2 are NULL (Pending). They will be filled when you Reconcile.
    $stmt = $conn->prepare("INSERT INTO fund_transfers (transfer_date, from_account_type, from_account_id, to_account_type, to_account_id, amount, notes, from_transaction_id, to_transaction_id, check_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssisidsiii", $date, $from_type_label, $from_id, $to_type_label, $to_id, $amt, $notes, $tid1, $tid2, $check_id);
    $stmt->execute();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = ($method === 'Check') ? "Transfer queued. Funds will move when Check is Cleared." : "Transfer successful.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error: " . $e->getMessage();
}
echo json_encode($response);
?>