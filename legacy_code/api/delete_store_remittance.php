<?php
// api/delete_store_remittance.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

$id = $_POST['id'];
$conn->begin_transaction();

try {
    // Get Details
    $res = $conn->query("SELECT * FROM store_remittances WHERE id = $id");
    $row = $res->fetch_assoc();
    if (!$row) throw new Exception("Record not found");

    // 1. Reverse Balance (Deduct Money)
    $table = ($row['destination_type'] === 'Bank') ? 'passbooks' : 'cash_accounts';
    $conn->query("UPDATE $table SET current_balance = current_balance - {$row['amount']} WHERE id = {$row['destination_id']}");

    // 2. Delete Ledger Transaction
    $trans_table = ($row['destination_type'] === 'Bank') ? 'passbook_transactions' : 'cash_transactions';
    if($row['transaction_id']) {
        $conn->query("DELETE FROM $trans_table WHERE id = {$row['transaction_id']}");
    }

    // 3. Delete Record
    $conn->query("DELETE FROM store_remittances WHERE id = $id");

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>