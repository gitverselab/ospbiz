<?php
// api/delete_bill_payment.php
require_once "../config/access_control.php";
check_permission('bills.pay');

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
    $stmt_get = $conn->prepare("SELECT * FROM bill_payments WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $payment_id);
    $stmt_get->execute();
    $payment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$payment) { throw new Exception("Payment not found."); }
    
    $bill_id = $payment['bill_id'];
    $amount = $payment['amount'];
    $ref_id = $payment['reference_id'];

    // 2. Reverse transaction
    if ($payment['payment_method'] === 'Cash on Hand' && $ref_id) {
        $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = (SELECT cash_account_id FROM cash_transactions WHERE id=$ref_id)");
        $conn->query("DELETE FROM cash_transactions WHERE id = $ref_id");

    } elseif ($payment['payment_method'] === 'Bank Transfer' && $ref_id) {
        $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = (SELECT passbook_id FROM passbook_transactions WHERE id=$ref_id)");
        $conn->query("DELETE FROM passbook_transactions WHERE id = $ref_id");

    } elseif ($payment['payment_method'] === 'Check' && $ref_id) {
        // Check if the check was CLEARED (meaning money left the bank)
        $stmt_chk = $conn->prepare("SELECT id, status FROM checks WHERE id = ?");
        $stmt_chk->bind_param("i", $ref_id);
        $stmt_chk->execute();
        $check = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();
        
        if ($check) {
            if ($check['status'] === 'Cleared') {
                // Reverse the bank deduction
                $stmt_cl = $conn->prepare("SELECT id, passbook_id FROM passbook_transactions WHERE check_ref_id = ?");
                $stmt_cl->bind_param("i", $ref_id);
                $stmt_cl->execute();
                $pt = $stmt_cl->get_result()->fetch_assoc();
                if ($pt) {
                    $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = {$pt['passbook_id']}");
                    $conn->query("DELETE FROM passbook_transactions WHERE id = {$pt['id']}");
                }
            }
            // Delete check record
            $conn->query("DELETE FROM checks WHERE id = $ref_id");
        }
    }

    // 3. Delete payment
    $conn->query("DELETE FROM bill_payments WHERE id = $payment_id");

    // 4. Recalculate Status (FIXED: Resolved ambiguous 'amount' column)
    $res = $conn->query("SELECT amount FROM bills WHERE id = $bill_id");
    $total_bill_amount = $res->fetch_assoc()['amount'];

    $paid_sql = "SELECT SUM(bp.amount) as total FROM bill_payments bp LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check' WHERE bp.bill_id = $bill_id AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')";
    $paid_res = $conn->query($paid_sql);
    $total_paid = $paid_res->fetch_assoc()['total'] ?? 0;
    
    $new_status = (abs($total_paid - $total_bill_amount) < 0.01) ? 'Paid' : (($total_paid > 0) ? 'Partially Paid' : 'Unpaid');
    $conn->query("UPDATE bills SET status = '$new_status' WHERE id = $bill_id");

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