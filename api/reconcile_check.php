<?php
// api/reconcile_check.php
require_once "../config/access_control.php";
check_permission('checks.reconcile');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$check_id = (int)$_POST['check_id'];
$passbook_id = (int)$_POST['passbook_id'];
$cleared_date = $_POST['cleared_date'];

if (empty($check_id) || empty($passbook_id) || empty($cleared_date)) {
    $response['message'] = 'Missing required reconciliation data.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Get check details & ensure it's still 'Issued'
    $check_sql = "SELECT amount, check_number, payee FROM checks WHERE id = ? AND status = 'Issued' FOR UPDATE";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $check_id);
    $stmt_check->execute();
    $check_details = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$check_details) { throw new Exception("Check not found or has already been processed."); }
    
    $amount = $check_details['amount'];
    $check_num = $check_details['check_number'];

    // 2. Update the check status to 'Cleared'
    $conn->query("UPDATE checks SET status = 'Cleared' WHERE id = $check_id");
    
    // 3. IDENTIFY THE CHECK TYPE

    // --- A. CHECK: FUND TRANSFER (FIXED LOGIC) ---
    $ft_res = $conn->query("SELECT id, to_account_id, to_account_type FROM fund_transfers WHERE check_id = $check_id LIMIT 1");
    $fund_transfer = $ft_res->fetch_assoc();

    if ($fund_transfer) {
        $ft_id = $fund_transfer['id'];
        $dest_id = $fund_transfer['to_account_id'];
        $dest_type = $fund_transfer['to_account_type'];
        
        // 1. Debit Source (Money Out)
        $desc_source = "Cleared Check #$check_num (Transfer Out)";
        $stmt_src = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, check_ref_id, transaction_date, debit, description) VALUES (?, ?, ?, ?, ?)");
        $stmt_src->bind_param("iisds", $passbook_id, $check_id, $cleared_date, $amount, $desc_source);
        $stmt_src->execute();
        $src_tid = $conn->insert_id; // Capture ID
        
        $conn->query("UPDATE passbooks SET current_balance = current_balance - $amount WHERE id = $passbook_id");

        // 2. Credit Destination (Money In)
        $desc_dest = "Transfer In via Check #$check_num";
        $dest_tid = 0;

        if ($dest_type === 'Passbook') {
            $stmt_dest = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, transaction_date, credit, description) VALUES (?, ?, ?, ?)");
            $stmt_dest->bind_param("isds", $dest_id, $cleared_date, $amount, $desc_dest);
            $stmt_dest->execute();
            $dest_tid = $conn->insert_id; // Capture ID
            $conn->query("UPDATE passbooks SET current_balance = current_balance + $amount WHERE id = $dest_id");
        } else {
            $stmt_dest = $conn->prepare("INSERT INTO cash_transactions (cash_account_id, transaction_date, credit, description) VALUES (?, ?, ?, ?)");
            $stmt_dest->bind_param("isds", $dest_id, $cleared_date, $amount, $desc_dest);
            $stmt_dest->execute();
            $dest_tid = $conn->insert_id; // Capture ID
            $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $amount WHERE id = $dest_id");
        }

        // 3. IMPORTANT: Update Fund Transfer Record with the new Transaction IDs
        // This ensures if you delete the transfer later, the money reverses correctly.
        $conn->query("UPDATE fund_transfers SET from_transaction_id = $src_tid, to_transaction_id = $dest_tid WHERE id = $ft_id");

    } else {
        // --- B. ALL OTHER CHECKS (Expenses, Bills, etc.) ---
        
        $link_desc = " to " . $check_details['payee'];
        $linked_type = 'manual';
        $parent_id = null;

        if ($cr_res = $conn->query("SELECT credit_id FROM credit_payments WHERE reference_id = $check_id AND payment_method = 'Check' LIMIT 1")) {
            if ($cr_res->num_rows > 0) { $row = $cr_res->fetch_assoc(); $linked_type = 'credit'; $parent_id = $row['credit_id']; $link_desc = " for Credit #$parent_id"; }
        }
        if ($linked_type == 'manual') {
            if ($b_res = $conn->query("SELECT bill_id FROM bill_payments WHERE reference_id = $check_id AND payment_method = 'Check' LIMIT 1")) {
                if ($b_res->num_rows > 0) { $row = $b_res->fetch_assoc(); $linked_type = 'bill'; $parent_id = $row['bill_id']; $link_desc = " for Bill #$parent_id"; }
            }
        }
        if ($linked_type == 'manual') {
            if ($p_res = $conn->query("SELECT purchase_id FROM purchase_payments WHERE reference_id = $check_id AND payment_method = 'Check' LIMIT 1")) {
                if ($p_res->num_rows > 0) { 
                    $row = $p_res->fetch_assoc(); 
                    $linked_type = 'purchase'; 
                    $parent_id = $row['purchase_id']; 
                    $po_res = $conn->query("SELECT po_number FROM purchases WHERE id = $parent_id");
                    $po_num = ($po_res && $r=$po_res->fetch_assoc()) ? $r['po_number'] : $parent_id;
                    $link_desc = " for PO #$po_num"; 
                }
            }
        }
        if ($linked_type == 'manual') {
            if ($e_res = $conn->query("SELECT description FROM expenses WHERE transaction_id = $check_id AND payment_method = 'Check' LIMIT 1")) {
                if ($e_res->num_rows > 0) { $row = $e_res->fetch_assoc(); $linked_type = 'expense'; $link_desc = " (" . $row['description'] . ")"; }
            }
        }

        // Record Transaction
        $description = "Cleared Check #$check_num" . $link_desc;
        $stmt_trans = $conn->prepare("INSERT INTO passbook_transactions (passbook_id, check_ref_id, transaction_date, debit, description) VALUES (?, ?, ?, ?, ?)");
        $stmt_trans->bind_param("iisds", $passbook_id, $check_id, $cleared_date, $amount, $description);
        $stmt_trans->execute();
        $conn->query("UPDATE passbooks SET current_balance = current_balance - $amount WHERE id = $passbook_id");

        // Update Parent Status
        if ($linked_type !== 'manual' && $linked_type !== 'expense' && $parent_id) {
            $table_map = [
                'credit' => ['table' => 'credits', 'pay_table' => 'credit_payments', 'fk' => 'credit_id'],
                'bill' => ['table' => 'bills', 'pay_table' => 'bill_payments', 'fk' => 'bill_id'],
                'purchase' => ['table' => 'purchases', 'pay_table' => 'purchase_payments', 'fk' => 'purchase_id']
            ];
            
            $config = $table_map[$linked_type];
            $main_table = $config['table'];
            $pay_table = $config['pay_table'];
            $fk_col = $config['fk'];

            $total_res = $conn->query("SELECT amount FROM $main_table WHERE id = $parent_id");
            $total_amount = ($total_res && $r=$total_res->fetch_assoc()) ? $r['amount'] : 0;

            $paid_sql = "SELECT SUM(pp.amount) as total FROM $pay_table pp LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method='Check' WHERE pp.$fk_col = $parent_id AND (pp.payment_method IN ('Cash on Hand','Bank Transfer') OR c.status='Cleared')";
            $paid_res = $conn->query($paid_sql);
            $total_paid = $paid_res->fetch_assoc()['total'] ?? 0;

            $new_status = 'Unpaid';
            if ($linked_type === 'credit') $new_status = 'Received';

            if (abs($total_paid - $total_amount) < 0.01) { $new_status = 'Paid'; } 
            elseif ($total_paid > 0) { $new_status = 'Partially Paid'; }

            $conn->query("UPDATE $main_table SET status = '$new_status' WHERE id = $parent_id");
        }
    }
    
    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Check reconciled successfully!';
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Reconciliation failed: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>