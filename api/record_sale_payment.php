<?php
// api/record_sale_payment.php
require_once "../config/access_control.php";
check_permission('sales.pay');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$sale_id = $_POST['sale_id'] ?? null;
$payment_date = $_POST['payment_date'] ?? null;
$amount = (float)($_POST['amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$account_id = $_POST['account_id'] ?? null; 
$reference_id = null;

if (empty($sale_id) || empty($payment_date) || $amount <= 0 || empty($payment_method) || empty($account_id)) {
    $response['message'] = 'Missing required payment data.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Fetch Sale Info (including Category)
    $stmt_inv = $conn->prepare("SELECT invoice_number, total_amount, withholding_tax, chart_of_account_id FROM sales WHERE id = ?");
    $stmt_inv->bind_param("i", $sale_id);
    $stmt_inv->execute();
    $sale_data = $stmt_inv->get_result()->fetch_assoc();
    $stmt_inv->close();

    if (!$sale_data) { throw new Exception("Sales invoice not found."); }
    
    $description = "Payment for Sales Invoice #" . $sale_data['invoice_number'];
    $category_id = $sale_data['chart_of_account_id']; // This is the INCOME Category ID

    // 2. Handle Money IN
    if ($payment_method === 'Cash on Hand') {
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, credit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();
        $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = $account_id");

    } elseif ($payment_method === 'Bank Transfer') {
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, credit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $account_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();
        $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = $account_id");
    }

    // 3. Record Payment
    $stmt_payment = $conn->prepare("INSERT INTO sales_payments (sale_id, payment_date, amount, payment_method, reference_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_payment->bind_param("isdsis", $sale_id, $payment_date, $amount, $payment_method, $reference_id, $notes);
    $stmt_payment->execute();
    $stmt_payment->close();
    
    // 4. Update Status
    $net_receivable = $sale_data['total_amount'] - $sale_data['withholding_tax'];
    $paid_stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM sales_payments WHERE sale_id = ?");
    $paid_stmt->bind_param("i", $sale_id);
    $paid_stmt->execute();
    $total_paid = $paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;
    $paid_stmt->close();
    
    $new_status = ($total_paid >= $net_receivable - 0.01) ? 'Paid' : (($total_paid > 0) ? 'Partial' : 'Issued');
    
    $update_stmt = $conn->prepare("UPDATE sales SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $sale_id);
    $update_stmt->execute();
    $update_stmt->close();

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Payment recorded successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>