<?php
// api/record_remittance.php
require_once "../config/access_control.php";
check_permission('sales.create'); 

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    $chart_of_account_id = !empty($_POST['chart_of_account_id']) ? $_POST['chart_of_account_id'] : 'NULL';
    $invoices = json_decode($_POST['invoices'], true);
    
    if (empty($invoices)) {
        echo json_encode(['message' => 'No invoices selected']); exit;
    }

    $conn->begin_transaction();
    try {
        $total_payment = 0;
        $customer_names = []; 

        foreach ($invoices as $inv) {
            $sale_id = $inv['sale_id'];
            $amount = (float)$inv['amount'];

            if ($amount > 0) {
                // 1. Record Payment
                $check_no = null;
                if ($payment_method === 'Check' && !empty($_POST['check_number'])) {
                    $check_no = $_POST['check_number'];
                }

                $stmt = $conn->prepare("INSERT INTO sales_payments (sale_id, payment_date, amount, payment_method, check_number, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isdsss", $sale_id, $payment_date, $amount, $payment_method, $check_no, $notes);
                $stmt->execute();
                $total_payment += $amount;

                // 2. Get Customer Name (For Ledger)
                $c_res = $conn->query("SELECT c.customer_name FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.id = $sale_id");
                if($c_row = $c_res->fetch_assoc()) {
                    $customer_names[] = $c_row['customer_name'];
                }

                // 3. UPDATE INVOICE STATUS (CRITICAL FIX)
                // Logic: (Total Paid + WHT + Deductions) >= Total Amount ? 'Paid' : 'Partial'
                $update_sql = "
                    UPDATE sales s
                    SET s.status = CASE
                        WHEN (
                            IFNULL((SELECT SUM(amount) FROM sales_payments WHERE sale_id = s.id), 0) + 
                            IFNULL(s.withholding_tax, 0) + 
                            IFNULL(s.total_deductions, 0)
                        ) >= s.total_amount - 0.01 THEN 'Paid' -- 0.01 tolerance for rounding
                        ELSE 'Partial'
                    END
                    WHERE s.id = ?
                ";
                $stmt_upd = $conn->prepare($update_sql);
                $stmt_upd->bind_param("i", $sale_id);
                $stmt_upd->execute();
            }
        }
        
        $unique_customers = array_unique($customer_names);
        $desc_customers = implode(", ", array_slice($unique_customers, 0, 3));
        if(count($unique_customers) > 3) $desc_customers .= " and others";

        // 4. Insert into Ledger (Passbook / Cash)
        if ($total_payment > 0) {
            if ($payment_method == 'Cash on Hand') {
                $cash_id = $_POST['cash_account_id'];
                $conn->query("UPDATE cash_accounts SET current_balance = current_balance + $total_payment WHERE id = $cash_id");
                
                $desc = "Remittance: $desc_customers";
                $sql = "INSERT INTO cash_transactions (cash_account_id, chart_of_account_id, transaction_date, description, credit) VALUES ($cash_id, $chart_of_account_id, '$payment_date', '$desc', $total_payment)";
                $conn->query($sql);

            } else {
                $passbook_id = $_POST['passbook_id'];
                $conn->query("UPDATE passbooks SET current_balance = current_balance + $total_payment WHERE id = $passbook_id");

                $desc = "Deposit: $desc_customers"; 
                if($payment_method === 'Check' && !empty($_POST['check_number'])) {
                    $desc .= " (Check# " . $_POST['check_number'] . ")";
                } elseif ($payment_method === 'Bank Transfer') {
                    $desc .= " (Bank Transfer)";
                }
                
                $sql = "INSERT INTO passbook_transactions (passbook_id, chart_of_account_id, transaction_date, description, credit) VALUES ($passbook_id, $chart_of_account_id, '$payment_date', '$desc', $total_payment)";
                $conn->query($sql);
            }
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Payment recorded and statuses updated.";

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    $conn->close();
}
echo json_encode($response);
?>