<?php
// api/receive_credit_funds.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$credit_id = (int)$_POST['credit_id'];
$received_date = $_POST['received_date'];
$account_type = $_POST['account_type'];
$account_id = (int)$_POST['account_id']; 
$amount = (float)$_POST['amount']; 

if (empty($credit_id) || empty($received_date) || empty($account_type) || empty($account_id) || $amount <= 0) {
    $response['message'] = 'Missing required fields or invalid amount.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Get credit details & validate amount
    $stmt = $conn->prepare("SELECT amount, creditor_name FROM credits WHERE id = ?");
    $stmt->bind_param("i", $credit_id);
    $stmt->execute();
    $credit = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$credit) throw new Exception("Credit not found.");
    
    // Check how much we've already received
    $rcv_stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total_rcv FROM credit_receipts WHERE credit_id = $credit_id");
    $total_received = $rcv_stmt->fetch_assoc()['total_rcv'];
    
    if (($total_received + $amount) > $credit['amount']) {
        throw new Exception("Cannot receive more than the approved loan amount. Remaining to receive: " . ($credit['amount'] - $total_received));
    }

    $description = "Loan funds received from " . $credit['creditor_name'] . " (Tranche)";
    $reference_id = null;
    $chart_of_account_id = null; // Can map to "Loan Receivable" later if needed

    // 2. Create transaction in the appropriate account
    if ($account_type === 'Passbook') {
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, 0.00, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $received_date, $description, $amount, $chart_of_account_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();
        
        $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = $account_id");
    } else {
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, credit, chart_of_account_id) VALUES (?, ?, ?, 0.00, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $received_date, $description, $amount, $chart_of_account_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();

        $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = $account_id");
    }

    // 3. Log the receipt tranche
    $stmt_receipt = $conn->prepare("INSERT INTO credit_receipts (credit_id, received_date, amount, account_type, account_id, reference_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_receipt->bind_param("isdsii", $credit_id, $received_date, $amount, $account_type, $account_id, $reference_id);
    $stmt_receipt->execute();
    $stmt_receipt->close();

    // 4. Calculate new overall status
    $new_total_received = $total_received + $amount;
    
    // Check if there are any principal payments already
    $paid_stmt = $conn->query("SELECT COALESCE(SUM(cp.principal_amount), 0) as total_paid FROM credit_payments cp LEFT JOIN checks c ON cp.reference_id = c.id AND cp.payment_method = 'Check' WHERE cp.credit_id = $credit_id AND (cp.payment_method != 'Check' OR c.status = 'Cleared')");
    $total_paid = $paid_stmt->fetch_assoc()['total_paid'];

    $new_status = 'Pending';
    if ($total_paid >= $credit['amount']) { $new_status = 'Paid'; }
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }
    elseif ($new_total_received >= $credit['amount']) { $new_status = 'Received'; }
    elseif ($new_total_received > 0) { $new_status = 'Partially Received'; }

    $conn->query("UPDATE credits SET status = '$new_status' WHERE id = $credit_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Funds tranche recorded successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>