<?php
// api/update_bill_payment.php
require_once "../config/access_control.php";
check_permission('bills.pay');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$payment_id = (int)$_POST['id'];
$new_date = $_POST['payment_date'];
$new_amount = (float)$_POST['amount'];
$new_notes = trim($_POST['notes']);
$new_check_number = $_POST['check_number'] ?? '';
$new_check_date = $_POST['check_date'] ?? '';
$new_payee = trim($_POST['payee'] ?? '');

if (empty($new_date) || $new_amount <= 0) {
    $response['message'] = 'Invalid date or amount.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    $stmt_get = $conn->prepare("SELECT * FROM bill_payments WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $payment_id);
    $stmt_get->execute();
    $payment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$payment) { throw new Exception("Payment not found."); }
    
    $bill_id = $payment['bill_id'];
    $method = $payment['payment_method'];
    $ref_id = $payment['reference_id'];
    $diff = $new_amount - $payment['amount'];

    // Fetch the original Bill Number to preserve the Ledger Description
    $stmt_b = $conn->query("SELECT bill_number FROM bills WHERE id = $bill_id");
    $bill_number = $stmt_b->fetch_assoc()['bill_number'] ?? 'Unknown Bill';
    $ledger_description = "Payment for Bill #" . $bill_number . " (Updated)";

    // Update underlying transactions securely
    if ($method === 'Check' && $ref_id) {
        $stmt_chk = $conn->prepare("UPDATE checks SET check_date=?, check_number=?, payee=?, amount=? WHERE id=?");
        $stmt_chk->bind_param("sssdi", $new_check_date, $new_check_number, $new_payee, $new_amount, $ref_id);
        $stmt_chk->execute();
        $stmt_chk->close();
        
        $chk_status_res = $conn->query("SELECT status FROM checks WHERE id = $ref_id");
        $status = $chk_status_res->fetch_assoc()['status'];
        if ($status === 'Cleared') {
             $pt_res = $conn->query("SELECT id, passbook_id FROM passbook_transactions WHERE check_ref_id = $ref_id");
             if ($pt = $pt_res->fetch_assoc()) {
                 $conn->query("UPDATE passbook_transactions SET debit = $new_amount, description = '$ledger_description' WHERE id = {$pt['id']}");
                 $conn->query("UPDATE passbooks SET current_balance = current_balance - $diff WHERE id = {$pt['passbook_id']}");
             }
        }

    } elseif ($method === 'Cash on Hand' && $ref_id) {
        // Securely update cash ledger
        $stmt_ct = $conn->prepare("UPDATE cash_transactions SET transaction_date=?, debit=?, description=? WHERE id=?");
        $stmt_ct->bind_param("sdsi", $new_date, $new_amount, $ledger_description, $ref_id);
        $stmt_ct->execute();
        $stmt_ct->close();

        $ct_res = $conn->query("SELECT cash_account_id FROM cash_transactions WHERE id=$ref_id");
        if($acc_id = $ct_res->fetch_assoc()['cash_account_id']) {
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $diff WHERE id = $acc_id");
        }

    } elseif ($method === 'Bank Transfer' && $ref_id) {
         // Securely update bank ledger
         $stmt_pt = $conn->prepare("UPDATE passbook_transactions SET transaction_date=?, debit=?, description=? WHERE id=?");
         $stmt_pt->bind_param("sdsi", $new_date, $new_amount, $ledger_description, $ref_id);
         $stmt_pt->execute();
         $stmt_pt->close();

         $pt_res = $conn->query("SELECT passbook_id FROM passbook_transactions WHERE id=$ref_id");
         if($acc_id = $pt_res->fetch_assoc()['passbook_id']) {
             $conn->query("UPDATE passbooks SET current_balance = current_balance - $diff WHERE id = $acc_id");
         }
    }

    $stmt_upd = $conn->prepare("UPDATE bill_payments SET payment_date=?, amount=?, notes=? WHERE id=?");
    $stmt_upd->bind_param("sdsi", $new_date, $new_amount, $new_notes, $payment_id);
    $stmt_upd->execute();
    
    // Recalculate Status
    $res = $conn->query("SELECT amount FROM bills WHERE id = $bill_id");
    $total_bill_amount = $res->fetch_assoc()['amount'];

    $paid_sql = "SELECT SUM(bp.amount) as total FROM bill_payments bp LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check' WHERE bp.bill_id = $bill_id AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')";
    $paid_res = $conn->query($paid_sql);
    $total_paid = $paid_res->fetch_assoc()['total'] ?? 0;
    
    $new_status = (abs($total_paid - $total_bill_amount) < 0.01) ? 'Paid' : (($total_paid > 0) ? 'Partially Paid' : 'Unpaid');
    $conn->query("UPDATE bills SET status = '$new_status' WHERE id = $bill_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment updated successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>