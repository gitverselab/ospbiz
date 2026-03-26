<?php
// api/delete_transaction.php
session_start();
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$transaction_id = $_POST['transaction_id'] ?? null;
$account_type = $_POST['account_type'] ?? null; // 'passbook' or 'cash'
$account_id = $_POST['account_id'] ?? null;

if (empty($transaction_id) || empty($account_type) || empty($account_id)) {
    $response['message'] = 'Missing required data.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    $transaction_table = $account_type === 'passbook' ? 'passbook_transactions' : 'cash_transactions';
    
    // 1. Get transaction details before deleting
    $stmt_get = $conn->prepare("SELECT * FROM {$transaction_table} WHERE id = ?");
    $stmt_get->bind_param("i", $transaction_id);
    $stmt_get->execute();
    $transaction = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$transaction) { throw new Exception("Transaction not found."); }

    // 2. Delete the transaction itself
    $stmt_del = $conn->prepare("DELETE FROM {$transaction_table} WHERE id = ?");
    $stmt_del->bind_param("i", $transaction_id);
    $stmt_del->execute();
    $stmt_del->close();

    // 3. Correctly reverse the balance on the parent account
    $balance_change = $transaction['credit'] - $transaction['debit'];
    $account_table = $account_type === 'passbook' ? 'passbooks' : 'cash_accounts';
    $stmt_update_balance = $conn->prepare("UPDATE {$account_table} SET current_balance = current_balance - ? WHERE id = ?");
    $stmt_update_balance->bind_param("di", $balance_change, $account_id);
    $stmt_update_balance->execute();
    $stmt_update_balance->close();

    // 4. Check if this transaction was linked to a payment and reverse it
    $payment_tables = ['sales_payments', 'purchase_payments', 'bill_payments'];
    foreach ($payment_tables as $ptable) {
        $stmt_find_payment = $conn->prepare("SELECT * FROM {$ptable} WHERE reference_id = ? AND payment_method IN ('Bank Transfer', 'Cash on Hand')");
        $stmt_find_payment->bind_param("i", $transaction_id);
        $stmt_find_payment->execute();
        $payment = $stmt_find_payment->get_result()->fetch_assoc();
        $stmt_find_payment->close();
        
        if ($payment) {
            // Found a linked payment - delete it
            $stmt_del_payment = $conn->prepare("DELETE FROM {$ptable} WHERE id = ?");
            $stmt_del_payment->bind_param("i", $payment['id']);
            $stmt_del_payment->execute();
            $stmt_del_payment->close();
            
            // Recalculate the status of the parent sale/purchase/bill
            if ($ptable === 'sales_payments') {
                $sale_id = $payment['sale_id'];
                $sale_details_stmt = $conn->prepare("SELECT total_amount, withholding_tax FROM sales WHERE id = ?");
                $sale_details_stmt->bind_param("i", $sale_id);
                $sale_details_stmt->execute();
                $sale_details = $sale_details_stmt->get_result()->fetch_assoc();
                $net_receivable = $sale_details['total_amount'] - $sale_details['withholding_tax'];

                $paid_stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM sales_payments WHERE sale_id = ?");
                $paid_stmt->bind_param("i", $sale_id);
                $paid_stmt->execute();
                $total_paid = $paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;
                
                $new_status = 'Issued';
                if ($total_paid >= $net_receivable - 0.01) { $new_status = 'Paid'; } 
                elseif ($total_paid > 0) { $new_status = 'Partial'; }

                $update_stmt = $conn->prepare("UPDATE sales SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $sale_id);
                $update_stmt->execute();
                $update_stmt->close();
            } 
            elseif ($ptable === 'bill_payments') {
                $bill_id = $payment['bill_id'];
                $bill_total_stmt = $conn->prepare("SELECT amount FROM bills WHERE id = ?");
                $bill_total_stmt->bind_param("i", $bill_id);
                $bill_total_stmt->execute();
                $total_bill_amount = $bill_total_stmt->get_result()->fetch_assoc()['amount'];

                $paid_stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM bill_payments WHERE bill_id = ?");
                $paid_stmt->bind_param("i", $bill_id);
                $paid_stmt->execute();
                $total_paid = $paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;

                $new_status = 'Unpaid';
                if (abs($total_paid - $total_bill_amount) < 0.01) { $new_status = 'Paid'; }
                elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }
                
                $update_stmt = $conn->prepare("UPDATE bills SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $bill_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            elseif ($ptable === 'purchase_payments') {
                $purchase_id = $payment['purchase_id'];
                $purchase_total_stmt = $conn->prepare("SELECT amount FROM purchases WHERE id = ?");
                $purchase_total_stmt->bind_param("i", $purchase_id);
                $purchase_total_stmt->execute();
                $total_purchase_amount = $purchase_total_stmt->get_result()->fetch_assoc()['amount'];

                $paid_stmt = $conn->prepare("SELECT SUM(amount) as total_paid FROM purchase_payments WHERE purchase_id = ?");
                $paid_stmt->bind_param("i", $purchase_id);
                $paid_stmt->execute();
                $total_paid = $paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0;

                $new_status = 'Unpaid';
                if (abs($total_paid - $total_purchase_amount) < 0.01) { $new_status = 'Paid'; }
                elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }
                
                $update_stmt = $conn->prepare("UPDATE purchases SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $purchase_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            break; 
        }
    }

    $conn->commit();
    $response['success'] = true;
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Failed to delete transaction: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>