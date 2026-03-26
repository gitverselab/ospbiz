<?php
// api/record_purchase_payment.php
require_once "../config/access_control.php";
check_permission('purchases.pay');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$purchase_id = $_POST['purchase_id'] ?? null;
$payment_date = $_POST['payment_date'] ?? null;
$amount = (float)($_POST['amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? null;
$notes = trim($_POST['notes'] ?? '');
$custom_payee = trim($_POST['payee'] ?? ''); // NEW: Custom Payee
$reference_id = null;

if (empty($purchase_id) || empty($payment_date) || $amount <= 0 || empty($payment_method)) {
    $response['message'] = 'Missing required payment data.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Get Purchase Info (Category & Supplier)
    $stmt_info = $conn->prepare("SELECT p.po_number, p.chart_of_account_id, s.supplier_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
    $stmt_info->bind_param("i", $purchase_id);
    $stmt_info->execute();
    $purchase_info = $stmt_info->get_result()->fetch_assoc();
    $stmt_info->close();

    if (!$purchase_info) throw new Exception("Purchase not found.");
    
    $description = "Payment for Purchase #" . $purchase_info['po_number'];
    $category_id = $purchase_info['chart_of_account_id'];
    
    // Determine Payee: Use custom if provided, otherwise Supplier Name
    $payee = !empty($custom_payee) ? $custom_payee : $purchase_info['supplier_name'];

    // 2. Handle Money Logic
    if ($payment_method === 'Cash on Hand') {
        $source_id = (int)$_POST['source_id']; 
        
        // Deduct from Cash
        $stmt_bal = $conn->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_bal->bind_param("di", $amount, $source_id);
        $stmt_bal->execute();
        $stmt_bal->close();

        // Record Transaction
        $stmt_trans = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();

    } elseif ($payment_method === 'Bank Transfer') {
        $source_id = (int)$_POST['source_id'];
        
        // Deduct from Bank
        $stmt_bal = $conn->prepare("UPDATE passbooks SET current_balance = current_balance - ? WHERE id = ?");
        $stmt_bal->bind_param("di", $amount, $source_id);
        $stmt_bal->execute();
        $stmt_bal->close();

        // Record Transaction
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, description, debit, chart_of_account_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("issdi", $source_id, $payment_date, $description, $amount, $category_id);
        $stmt_trans->execute();
        $reference_id = $conn->insert_id;
        $stmt_trans->close();

    } elseif ($payment_method === 'Check') {
        $source_id = (int)$_POST['source_id'];
        $check_number = $_POST['check_number'];
        $check_date = $_POST['check_date'];
        
        // Create Check (Status = Issued)
        $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, transaction_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Issued')");
        $stmt_check->bind_param("issssdi", $source_id, $check_date, $payment_date, $check_number, $payee, $amount, $purchase_id);
        $stmt_check->execute();
        $reference_id = $conn->insert_id;
        $stmt_check->close();
    }

    // 3. Record Payment
    $stmt_payment = $conn->prepare("INSERT INTO purchase_payments (purchase_id, payment_date, amount, payment_method, reference_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_payment->bind_param("isdsis", $purchase_id, $payment_date, $amount, $payment_method, $reference_id, $notes);
    $stmt_payment->execute();
    $stmt_payment->close();
    
    // 4. Update Status
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
    if (abs($total_paid - $total_purchase_amount) < 0.01) {
        $new_status = 'Paid';
    } elseif ($total_paid > 0) {
        $new_status = 'Partially Paid';
    }

    $conn->query("UPDATE purchases SET status = '$new_status' WHERE id = $purchase_id");

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