<?php
// api/record_credit_payment.php
require_once "../config/access_control.php";
check_permission('credits.pay');
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false]); exit; }

$credit_id = (int)$_POST['credit_id'];
$payment_date = $_POST['payment_date'];
$total_payment_amount = (float)$_POST['amount']; 
$principal_deduction = (float)$_POST['principal_amount'];
$payment_method = $_POST['payment_method']; 
$notes = trim($_POST['notes']);

if (empty($credit_id) || empty($payment_date) || $total_payment_amount <= 0 || $principal_deduction < 0 || empty($payment_method)) {
     echo json_encode(['success'=>false, 'message'=>'Invalid fields.']); exit;
}
if ($principal_deduction > $total_payment_amount) {
    echo json_encode(['success'=>false, 'message'=>'Principal deduction exceeds total payment.']); exit;
}

$conn->begin_transaction();
try {
    // Fetch Total Received & Total Principal Paid
    $rcv_stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM credit_receipts WHERE credit_id = $credit_id");
    $total_received = $rcv_stmt->fetch_assoc()['total'];

    $paid_stmt = $conn->query("SELECT COALESCE(SUM(cp.principal_amount), 0) as total_paid FROM credit_payments cp LEFT JOIN checks c ON cp.reference_id = c.id AND cp.payment_method = 'Check' WHERE cp.credit_id = $credit_id AND (cp.payment_method != 'Check' OR c.status = 'Cleared')");
    $total_paid = $paid_stmt->fetch_assoc()['total_paid'];
    
    $outstanding_balance = $total_received - $total_paid;
    if ($principal_deduction > $outstanding_balance) {
        throw new Exception("Principal payment cannot exceed your current outstanding balance of " . number_format($outstanding_balance, 2));
    }

    $interest_amount = $total_payment_amount - $principal_deduction;
    
    // FIXED: Added ", 2" to number_format so it doesn't round off your cents in the ledger!
    $description = "Payment for Credit #$credit_id (Principal: " . number_format($principal_deduction, 2) . ", Int: " . number_format($interest_amount, 2) . ")";
    $reference_id = null;
    $chart_of_account_id = null; // Can map to a "Loan Payable" account id if you pass it from the frontend later

    // 1. Handle Money Out
    if ($payment_method === 'Cash on Hand') {
        $source_id = (int)$_POST['source_id'];
        $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $total_payment_amount WHERE id = $source_id");
        
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, ?, 0.00, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $total_payment_amount, $chart_of_account_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        
    } elseif ($payment_method === 'Bank Transfer') {
        $source_id = (int)$_POST['source_id'];
        $conn->query("UPDATE passbooks SET current_balance = current_balance - $total_payment_amount WHERE id = $source_id");
        
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, ?, 0.00, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $total_payment_amount, $chart_of_account_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        
    } elseif ($payment_method === 'Check') {
        $source_id = (int)$_POST['source_id'];
        $check_number = $_POST['check_number'];
        $check_date = $_POST['check_date'];
        $payee = $_POST['payee'];
        
        $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, status) VALUES (?, ?, ?, ?, ?, ?, 'Issued')");
        $stmt_check->bind_param("issssd", $source_id, $check_date, $payment_date, $check_number, $payee, $total_payment_amount);
        $stmt_check->execute();
        $reference_id = $conn->insert_id;
    }

    // 2. Record Payment
    $stmt_payment = $conn->prepare("INSERT INTO credit_payments (credit_id, payment_date, amount, principal_amount, interest_amount, payment_method, reference_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_payment->bind_param("isdddsis", $credit_id, $payment_date, $total_payment_amount, $principal_deduction, $interest_amount, $payment_method, $reference_id, $notes);
    $stmt_payment->execute();
    
    // 3. Update Status
    $credit_total_stmt = $conn->query("SELECT amount FROM credits WHERE id = $credit_id");
    $total_loan_approved = $credit_total_stmt->fetch_assoc()['amount'];
    
    $new_total_paid = $total_paid + ($payment_method === 'Check' ? 0 : $principal_deduction); 
    
    $new_status = 'Pending';
    if ($new_total_paid >= $total_loan_approved) { $new_status = 'Paid'; } 
    elseif ($new_total_paid > 0) { $new_status = 'Partially Paid'; }
    elseif ($total_received >= $total_loan_approved) { $new_status = 'Received'; }
    elseif ($total_received > 0) { $new_status = 'Partially Received'; }

    $conn->query("UPDATE credits SET status = '$new_status' WHERE id = $credit_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment recorded successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}
echo json_encode($response);
?>