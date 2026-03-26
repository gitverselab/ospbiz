<?php
// api/delete_credit_payment.php
require_once "../config/access_control.php";
check_permission('credits.pay'); 

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$payment_id = (int)$_POST['id'];

$conn->begin_transaction();
try {
    // 1. Get payment details
    $stmt_get = $conn->prepare("SELECT * FROM credit_payments WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $payment_id);
    $stmt_get->execute();
    $payment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$payment) { throw new Exception("Payment record not found."); }
    
    $credit_id = $payment['credit_id'];
    $amount = $payment['amount']; // Total cash that left the account
    $ref_id = $payment['reference_id'];

    // 2. Reverse the financial transaction (Add money back)
    if ($payment['payment_method'] === 'Cash on Hand' && $ref_id) {
        $stmt_trans = $conn->prepare("SELECT cash_account_id FROM cash_transactions WHERE id = ?");
        $stmt_trans->bind_param("i", $ref_id);
        $stmt_trans->execute();
        $trans = $stmt_trans->get_result()->fetch_assoc();
        $stmt_trans->close();
        
        if ($trans) {
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = {$trans['cash_account_id']}");
            $conn->query("DELETE FROM cash_transactions WHERE id = $ref_id");
        }

    } elseif ($payment['payment_method'] === 'Bank Transfer' && $ref_id) {
        $stmt_trans = $conn->prepare("SELECT passbook_id FROM passbook_transactions WHERE id = ?");
        $stmt_trans->bind_param("i", $ref_id);
        $stmt_trans->execute();
        $trans = $stmt_trans->get_result()->fetch_assoc();
        $stmt_trans->close();
        
        if ($trans) {
            $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = {$trans['passbook_id']}");
            $conn->query("DELETE FROM passbook_transactions WHERE id = $ref_id");
        }

    } elseif ($payment['payment_method'] === 'Check' && $ref_id) {
        $stmt_chk = $conn->prepare("SELECT id, status FROM checks WHERE id = ?");
        $stmt_chk->bind_param("i", $ref_id);
        $stmt_chk->execute();
        $check = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();
        
        if ($check) {
            if ($check['status'] === 'Cleared') {
                $stmt_cl = $conn->prepare("SELECT id, passbook_id FROM passbook_transactions WHERE check_ref_id = ?");
                $stmt_cl->bind_param("i", $ref_id);
                $stmt_cl->execute();
                $clear_trans = $stmt_cl->get_result()->fetch_assoc();
                $stmt_cl->close();
                
                if ($clear_trans) {
                    $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = {$clear_trans['passbook_id']}");
                    $conn->query("DELETE FROM passbook_transactions WHERE id = {$clear_trans['id']}");
                }
            }
            $conn->query("DELETE FROM checks WHERE id = $ref_id");
        }
    }

    // 3. Delete the payment record
    $conn->query("DELETE FROM credit_payments WHERE id = $payment_id");

    // 4. Recalculate Credit Status
    $credit_stmt = $conn->query("SELECT amount FROM credits WHERE id = $credit_id");
    $credit_amount = $credit_stmt->fetch_assoc()['amount'];

    $rcv_stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM credit_receipts WHERE credit_id = $credit_id");
    $total_received = $rcv_stmt->fetch_assoc()['total'];

    $paid_sql = "SELECT COALESCE(SUM(cp.principal_amount), 0) as total_paid FROM credit_payments cp LEFT JOIN checks c ON cp.reference_id = c.id AND cp.payment_method = 'Check' WHERE cp.credit_id = $credit_id AND (cp.payment_method != 'Check' OR c.status = 'Cleared')";
    $total_paid = $conn->query($paid_sql)->fetch_assoc()['total_paid'];
    
    $new_status = 'Pending';
    if ($total_paid >= $credit_amount) { $new_status = 'Paid'; }
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }
    elseif ($total_received >= $credit_amount) { $new_status = 'Received'; }
    elseif ($total_received > 0) { $new_status = 'Partially Received'; }

    $conn->query("UPDATE credits SET status = '$new_status' WHERE id = $credit_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment deleted successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>