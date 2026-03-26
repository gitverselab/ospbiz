<?php
// api/delete_purchase_payment.php
require_once "../config/access_control.php";
check_permission('purchases.pay'); // Re-using pay permission for deleting payments

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
    $stmt_get = $conn->prepare("SELECT * FROM purchase_payments WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $payment_id);
    $stmt_get->execute();
    $payment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$payment) { throw new Exception("Payment record not found."); }
    
    $purchase_id = $payment['purchase_id'];
    $amount = $payment['amount'];
    $ref_id = $payment['reference_id'];

    // 2. Reverse the financial transaction
    if ($payment['payment_method'] === 'Cash on Hand' && $ref_id) {
        // Find which account it came from
        $stmt_trans = $conn->prepare("SELECT cash_account_id FROM cash_transactions WHERE id = ?");
        $stmt_trans->bind_param("i", $ref_id);
        $stmt_trans->execute();
        $trans = $stmt_trans->get_result()->fetch_assoc();
        $stmt_trans->close();
        
        if ($trans) {
            // Return money to cash account
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = {$trans['cash_account_id']}");
            // Delete transaction
            $conn->query("DELETE FROM cash_transactions WHERE id = $ref_id");
        }

    } elseif ($payment['payment_method'] === 'Bank Transfer' && $ref_id) {
        // Find which account
        $stmt_trans = $conn->prepare("SELECT passbook_id FROM passbook_transactions WHERE id = ?");
        $stmt_trans->bind_param("i", $ref_id);
        $stmt_trans->execute();
        $trans = $stmt_trans->get_result()->fetch_assoc();
        $stmt_trans->close();
        
        if ($trans) {
            // Return money to bank
            $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = {$trans['passbook_id']}");
            // Delete transaction
            $conn->query("DELETE FROM passbook_transactions WHERE id = $ref_id");
        }

    } elseif ($payment['payment_method'] === 'Check' && $ref_id) {
        // Check if the check was CLEARED (meaning money left the bank)
        $stmt_chk = $conn->prepare("SELECT id, status FROM checks WHERE id = ?");
        $stmt_chk->bind_param("i", $ref_id);
        $stmt_chk->execute();
        $check = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();
        
        if ($check) {
            if ($check['status'] === 'Cleared') {
                // If cleared, we need to reverse the passbook deduction too!
                // Find the clearing transaction
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
            // Delete the check record
            $conn->query("DELETE FROM checks WHERE id = $ref_id");
        }
    }

    // 3. Delete the payment record
    $conn->query("DELETE FROM purchase_payments WHERE id = $payment_id");

    // 4. Update Purchase Status
    $res = $conn->query("SELECT amount FROM purchases WHERE id = $purchase_id");
    $total_purchase_amount = $res->fetch_assoc()['amount'];

    $paid_sql = "
        SELECT SUM(pp.amount) as total 
        FROM purchase_payments pp 
        LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check' 
        WHERE pp.purchase_id = $purchase_id 
        AND (pp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
    ";
    $paid_res = $conn->query($paid_sql);
    $total_paid = $paid_res->fetch_assoc()['total'] ?? 0;
    
    $new_status = 'Unpaid';
    if (abs($total_paid - $total_purchase_amount) < 0.01) { $new_status = 'Paid'; }
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }

    $conn->query("UPDATE purchases SET status = '$new_status' WHERE id = $purchase_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment deleted successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>