<?php
// api/add_store_remittance.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false, 'message'=>'Invalid request']); exit; }

$date = $_POST['remittance_date'];
$amount = (float)$_POST['amount'];
$cat_id = $_POST['chart_of_account_id'];
$dest_raw = $_POST['destination_account']; // Format: "type-id" (e.g., "Cash-1")
$desc = trim($_POST['description']);

if ($amount <= 0 || empty($date) || empty($dest_raw) || empty($cat_id)) {
    echo json_encode(['success'=>false, 'message'=>'Missing required fields']); exit;
}

list($dest_type, $dest_id) = explode('-', $dest_raw);

$conn->begin_transaction();
try {
    // 1. Update Balance (Add Money)
    $table = ($dest_type === 'Bank') ? 'passbooks' : 'cash_accounts';
    $conn->query("UPDATE $table SET current_balance = current_balance + $amount WHERE id = $dest_id");

    // 2. Create Ledger Transaction (Credit/Income)
    $trans_table = ($dest_type === 'Bank') ? 'passbook_transactions' : 'cash_transactions';
    $col_id = ($dest_type === 'Bank') ? 'passbook_id' : 'cash_account_id';
    
    $stmt_t = $conn->prepare("INSERT INTO $trans_table ($col_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, 0, ?, ?)");
    $stmt_t->bind_param("issdi", $dest_id, $date, $desc, $amount, $cat_id);
    $stmt_t->execute();
    $trans_id = $conn->insert_id;

    // 3. Log Remittance
    $stmt = $conn->prepare("INSERT INTO store_remittances (remittance_date, amount, chart_of_account_id, destination_type, destination_id, description, transaction_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdisisi", $date, $amount, $cat_id, $dest_type, $dest_id, $desc, $trans_id);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Remittance recorded successfully']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>