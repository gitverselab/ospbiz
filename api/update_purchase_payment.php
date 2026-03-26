<?php
// api/update_purchase_payment.php
require_once "../config/access_control.php";
check_permission('purchases.pay'); 

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

// Fields for Check edits
$new_check_number = $_POST['check_number'] ?? '';
$new_check_date = $_POST['check_date'] ?? '';
$new_payee = trim($_POST['payee'] ?? '');

if (empty($new_date) || $new_amount <= 0) {
    $response['message'] = 'Invalid date or amount.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Get existing payment
    $stmt_get = $conn->prepare("SELECT * FROM purchase_payments WHERE id = ? FOR UPDATE");
    $stmt_get->bind_param("i", $payment_id);
    $stmt_get->execute();
    $payment = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$payment) { throw new Exception("Payment not found."); }
    
    $purchase_id = $payment['purchase_id'];
    $method = $payment['payment_method'];
    $ref_id = $payment['reference_id'];
    $diff = $new_amount - $payment['amount'];

    // Fetch the original PO Number to preserve the Ledger Description
    $stmt_po = $conn->query("SELECT po_number FROM purchases WHERE id = $purchase_id");
    $po_number = $stmt_po->fetch_assoc()['po_number'] ?? 'Unknown PO';
    $ledger_description = "Payment for Purchase #" . $po_number . " (Updated)";

    // 2. Update the underlying transaction/check securely
    if ($method === 'Check' && $ref_id) {
        // Update Check Details
        $stmt_chk = $conn->prepare("UPDATE checks SET check_date=?, check_number=?, payee=?, amount=? WHERE id=?");
        $stmt_chk->bind_param("sssdi", $new_check_date, $new_check_number, $new_payee, $new_amount, $ref_id);
        $stmt_chk->execute();
        $stmt_chk->close();
        
        // If check is Cleared, we MUST also update the passbook transaction amount
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
        // Securely Update Cash Transaction
        $stmt_ct = $conn->prepare("UPDATE cash_transactions SET transaction_date=?, debit=?, description=? WHERE id=?");
        $stmt_ct->bind_param("sdsi", $new_date, $new_amount, $ledger_description, $ref_id);
        $stmt_ct->execute();
        $stmt_ct->close();

        // Update Account Balance (Subtract difference)
        $ct_res = $conn->query("SELECT cash_account_id FROM cash_transactions WHERE id=$ref_id");
        if($acc_id = $ct_res->fetch_assoc()['cash_account_id']) {
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $diff WHERE id = $acc_id");
        }

    } elseif ($method === 'Bank Transfer' && $ref_id) {
         // Securely Update Bank Transaction
         $stmt_pt = $conn->prepare("UPDATE passbook_transactions SET transaction_date=?, debit=?, description=? WHERE id=?");
         $stmt_pt->bind_param("sdsi", $new_date, $new_amount, $ledger_description, $ref_id);
         $stmt_pt->execute();
         $stmt_pt->close();

         // Update Balance
         $pt_res = $conn->query("SELECT passbook_id FROM passbook_transactions WHERE id=$ref_id");
         if($acc_id = $pt_res->fetch_assoc()['passbook_id']) {
             $conn->query("UPDATE passbooks SET current_balance = current_balance - $diff WHERE id = $acc_id");
         }
    }

    // 3. Update Purchase Payment Record
    $stmt_upd = $conn->prepare("UPDATE purchase_payments SET payment_date=?, amount=?, notes=? WHERE id=?");
    $stmt_upd->bind_param("sdsi", $new_date, $new_amount, $new_notes, $payment_id);
    $stmt_upd->execute();
    
    // 4. Recalculate Status (Standard Logic)
    $res = $conn->query("SELECT amount FROM purchases WHERE id = $purchase_id");
    $total_purchase_amount = $res->fetch_assoc()['amount'];

    $paid_sql = "SELECT SUM(pp.amount) as total FROM purchase_payments pp LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check' WHERE pp.purchase_id = $purchase_id AND (pp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')";
    $paid_res = $conn->query($paid_sql);
    $total_paid = $paid_res->fetch_assoc()['total'] ?? 0;
    
    $new_status = 'Unpaid';
    if (abs($total_paid - $total_purchase_amount) < 0.01) { $new_status = 'Paid'; }
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }

    $conn->query("UPDATE purchases SET status = '$new_status' WHERE id = $purchase_id");

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