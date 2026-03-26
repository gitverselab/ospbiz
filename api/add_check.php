<?php
// api/add_check.php
require_once "../config/access_control.php";
check_permission('checks.create');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $passbook_id = $_POST['passbook_id'];
    $check_date = $_POST['check_date'];
    $check_number = trim($_POST['check_number']);
    $payee = trim($_POST['payee']);
    $amount = (float)$_POST['amount'];
    $payment_for = $_POST['payment_for']; // 'manual', 'purchase', 'bill', or 'credit'
    $link_id = !empty($_POST['link_id']) ? (int)$_POST['link_id'] : null;

    // 1. Basic Validation
    if (empty($passbook_id) || empty($check_date) || empty($check_number) || empty($payee) || $amount <= 0 || empty($payment_for)) {
        $response['message'] = 'Please fill all required check details.';
        echo json_encode($response); exit;
    }

    // 2. Conditional Validation: link_id is required ONLY if NOT manual
    if ($payment_for !== 'manual' && empty($link_id)) {
        $response['message'] = 'You must select a specific Purchase, Bill, or Credit reference.';
        echo json_encode($response); exit;
    }

    $conn->begin_transaction();
    try {
        // 3. Insert the check
        // We store the link_id in transaction_id for reference (unless it's manual)
        $transaction_id_val = ($payment_for !== 'manual') ? $link_id : null;
        
        $stmt_check = $conn->prepare("INSERT INTO checks (passbook_id, check_date, release_date, check_number, payee, amount, transaction_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Issued')");
        $stmt_check->bind_param("issssdi", $passbook_id, $check_date, $check_date, $check_number, $payee, $amount, $transaction_id_val);
        $stmt_check->execute();
        $check_id = $conn->insert_id;
        $stmt_check->close();

        // 4. If this is NOT a manual check, link it to the specific module
        if ($payment_for !== 'manual') {
            $payment_table = '';
            $parent_table = '';
            $link_column = '';

            switch ($payment_for) {
                case 'purchase':
                    $payment_table = 'purchase_payments';
                    $parent_table = 'purchases';
                    $link_column = 'purchase_id';
                    break;
                case 'bill':
                    $payment_table = 'bill_payments';
                    $parent_table = 'bills';
                    $link_column = 'bill_id';
                    break;
                case 'credit':
                    $payment_table = 'credit_payments';
                    $parent_table = 'credits';
                    $link_column = 'credit_id';
                    break;
            }

            // Insert into the correct payments table
            $notes = "Paid by Check #" . $check_number;
            $stmt_payment = $conn->prepare("INSERT INTO {$payment_table} ({$link_column}, payment_date, amount, payment_method, reference_id, notes) VALUES (?, ?, ?, 'Check', ?, ?)");
            $stmt_payment->bind_param("isdis", $link_id, $check_date, $amount, $check_id, $notes);
            $stmt_payment->execute();
            $stmt_payment->close();

            // Update the status of the parent item
            $stmt_total = $conn->prepare("SELECT amount FROM {$parent_table} WHERE id = ?");
            $stmt_total->bind_param("i", $link_id);
            $stmt_total->execute();
            $total_amount = $stmt_total->get_result()->fetch_assoc()['amount'];
            $stmt_total->close();

            $stmt_paid = $conn->prepare("SELECT SUM(amount) as total_paid FROM {$payment_table} WHERE {$link_column} = ?");
            $stmt_paid->bind_param("i", $link_id);
            $stmt_paid->execute();
            $total_paid = $stmt_paid->get_result()->fetch_assoc()['total_paid'] ?? 0;
            $stmt_paid->close();

            $new_status = ($parent_table === 'credits') ? 'Received' : 'Unpaid';
            if (abs($total_paid - $total_amount) < 0.01) {
                $new_status = 'Paid';
            } elseif ($total_paid > 0) {
                $new_status = 'Partially Paid';
            }

            $stmt_update = $conn->prepare("UPDATE {$parent_table} SET status = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_status, $link_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Check issued successfully!';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }
    
    $conn->close();
}
echo json_encode($response);
?>