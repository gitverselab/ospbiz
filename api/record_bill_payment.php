<?php
// api/record_bill_payment.php
require_once "../config/access_control.php";
check_permission('bills.pay');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$bill_id = $_POST['bill_id'] ?? null;
$payment_date = $_POST['payment_date'] ?? null;
$amount = (float)($_POST['amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$custom_payee = trim($_POST['payee'] ?? '');
$reference_id = null; 

if (empty($bill_id) || empty($payment_date) || $amount <= 0 || empty($payment_method)) {
    $response['message'] = 'Missing required payment data.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Get Bill Info (including Category)
    $stmt_info = $conn->prepare("SELECT b.bill_number, b.chart_of_account_id, bl.biller_name FROM bills b JOIN billers bl ON b.biller_id = bl.id WHERE b.id = ?");
    $stmt_info->bind_param("i", $bill_id);
    $stmt_info->execute();
    $bill_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    if (!$bill_info) throw new Exception("Bill not found.");

    $description = "Payment for Bill #" . $bill_info['bill_number'];
    $category_id = $bill_info['chart_of_account_id'];
    $payee = !empty($custom_payee) ? $custom_payee : $bill_info['biller_name'];

    // 2. Handle Money Logic
    if ($payment_method === 'Cash on Hand') {
        $source_id = (int)$_POST['source_id'];
        
        // Deduct from Cash
        $conn->query("UPDATE cash_accounts SET current_balance = current_balance - $amount WHERE id = $source_id");

        // Record Transaction (WITH CATEGORY)
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;

    } elseif ($payment_method === 'Bank Transfer') {
        $source_id = (int)$_POST['source_id'];
        
        // Deduct from Bank
        $conn->query("UPDATE passbooks SET current_balance = current_balance - $amount WHERE id = $source_id");

        // Record Transaction (WITH CATEGORY)
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        
    } elseif ($payment_method === 'Check') {
        $source_id = (int)$_POST['source_id'];
        $check_number = $_POST['check_number'];
        $check_date = $_POST['check_date'];
        
        // Create Check (Status = Issued)
        $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, transaction_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Issued')");
        $stmt_check->bind_param("issssdi", $source_id, $check_date, $payment_date, $check_number, $payee, $amount, $bill_id);
        $stmt_check->execute();
        $reference_id = $conn->insert_id;
    }

    // 3. Record Payment
    $stmt_payment = $conn->prepare("INSERT INTO bill_payments (bill_id, payment_date, amount, payment_method, reference_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_payment->bind_param("isdsis", $bill_id, $payment_date, $amount, $payment_method, $reference_id, $notes);
    $stmt_payment->execute();
    
    // 4. Update Status (CLEARED FUNDS ONLY)
    $res = $conn->query("SELECT amount FROM bills WHERE id = $bill_id");
    $total_bill_amount = $res->fetch_assoc()['amount'];

    $paid_sql = "
        SELECT SUM(bp.amount) as total_paid 
        FROM bill_payments bp 
        LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check' 
        WHERE bp.bill_id = $bill_id 
        AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
    ";
    $paid_res = $conn->query($paid_sql);
    $total_paid = $paid_res->fetch_assoc()['total_paid'] ?? 0;
    
    $new_status = 'Unpaid';
    if (abs($total_paid - $total_bill_amount) < 0.01) { $new_status = 'Paid'; } 
    elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }

    $conn->query("UPDATE bills SET status = '$new_status' WHERE id = $bill_id");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment recorded successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Failed to record payment: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>