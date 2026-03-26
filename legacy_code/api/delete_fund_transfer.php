<?php
// api/delete_fund_transfer.php
require_once "../config/access_control.php";
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

// ---------------------------------------------------------
// 1. ADMIN PERMISSION CHECK (Robust)
// ---------------------------------------------------------
$is_admin = false;

// Check 1: Direct Session Role
if ((isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') || 
    (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)) {
    $is_admin = true;
}

// Check 2: Database Confirmation (If session check fails)
if (!$is_admin && $user_id > 0) {
    $sql_check = "
        SELECT 1 
        FROM app_user_roles ur
        JOIN roles r ON ur.role_id = r.id
        WHERE ur.user_id = ? 
        AND (r.role_name = 'Admin' OR ur.role_id = 1)
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $is_admin = true;
    }
    $stmt->close();
}

if (!$is_admin) {
    $response['message'] = 'Access Denied. Admin privileges required.';
    echo json_encode($response); 
    exit;
}

// ---------------------------------------------------------
// 2. VOID TRANSACTION LOGIC
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$id = (int)$_POST['id'];
$conn->begin_transaction();

try {
    // A. Get Transfer Details
    $stmt = $conn->prepare("SELECT * FROM fund_transfers WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    
    if (!$t) throw new Exception("Transfer record not found.");

    $amt = (float)$t['amount'];

    // B. Reverse Balances
    // 1. Add back to Source
    $tbl_from = ($t['from_account_type'] == 'Passbook') ? 'passbooks' : 'cash_accounts';
    $conn->query("UPDATE $tbl_from SET current_balance = current_balance + $amt WHERE id = {$t['from_account_id']}");

    // 2. Deduct from Destination
    $tbl_to = ($t['to_account_type'] == 'Passbook') ? 'passbooks' : 'cash_accounts';
    $conn->query("UPDATE $tbl_to SET current_balance = current_balance - $amt WHERE id = {$t['to_account_id']}");

    // C. Void Check (if exists)
    if (!empty($t['check_id'])) {
        $conn->query("UPDATE checks SET status = 'Canceled' WHERE id = {$t['check_id']}");
    }
    
    // D. Delete Ledger Transactions
    if(!empty($t['from_transaction_id'])) {
        $tbl = ($t['from_account_type'] == 'Passbook') ? 'passbook_transactions' : 'cash_transactions';
        $conn->query("DELETE FROM $tbl WHERE id = {$t['from_transaction_id']}");
    }
    if(!empty($t['to_transaction_id'])) {
        $tbl = ($t['to_account_type'] == 'Passbook') ? 'passbook_transactions' : 'cash_transactions';
        $conn->query("DELETE FROM $tbl WHERE id = {$t['to_transaction_id']}");
    }

    // E. Delete Main Log
    $conn->query("DELETE FROM fund_transfers WHERE id = $id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = "Transfer voided successfully.";

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Error: " . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>