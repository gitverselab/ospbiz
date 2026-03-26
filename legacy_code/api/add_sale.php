<?php
// api/add_sale.php
require_once "../config/access_control.php";
check_permission('sales.create');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$customer_id = $_POST['customer_id'] ?? null;
$invoice_number = trim($_POST['invoice_number'] ?? '');
$invoice_date = $_POST['invoice_date'] ?? null;
$due_date = $_POST['due_date'] ?? null;
$chart_of_account_id = !empty($_POST['chart_of_account_id']) ? (int)$_POST['chart_of_account_id'] : null;

$dr_ids = $_POST['dr_ids'] ?? [];
$rts_ids = $_POST['rts_ids'] ?? []; 
$withholding_tax = isset($_POST['withholding_tax']) ? (float)$_POST['withholding_tax'] : 0.00;

if (empty($customer_id) || empty($invoice_number) || empty($invoice_date) || empty($due_date) || empty($dr_ids) || empty($chart_of_account_id)) {
    $response['message'] = 'Please fill all fields: customer, invoice number, dates, category, and select at least one delivery receipt.';
    echo json_encode($response); exit;
}

// Check for duplicate invoice number
$stmt_check = $conn->prepare("SELECT id FROM sales WHERE invoice_number = ?");
$stmt_check->bind_param("s", $invoice_number);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $response['message'] = 'Invoice number already exists.';
    echo json_encode($response); exit;
}
$stmt_check->close();

$conn->begin_transaction();
try {
    // 1. Get DR Base
    $dr_base = 0;
    if (!empty($dr_ids)) {
        $dr_placeholders = implode(',', array_fill(0, count($dr_ids), '?'));
        $stmt_dr = $conn->prepare("SELECT SUM(total_value) as total_base FROM delivery_receipts WHERE id IN ($dr_placeholders)");
        $stmt_dr->bind_param(str_repeat('i', count($dr_ids)), ...$dr_ids);
        $stmt_dr->execute();
        $dr_base = $stmt_dr->get_result()->fetch_assoc()['total_base'] ?? 0;
        $stmt_dr->close();
    }

    // 2. Get RTS Base
    $rts_base = 0;
    if (!empty($rts_ids)) {
        $rts_placeholders = implode(',', array_fill(0, count($rts_ids), '?'));
        $stmt_rts = $conn->prepare("SELECT SUM(total_value) as total_base FROM return_receipts WHERE id IN ($rts_placeholders)");
        $stmt_rts->bind_param(str_repeat('i', count($rts_ids)), ...$rts_ids);
        $stmt_rts->execute();
        $rts_base = $stmt_rts->get_result()->fetch_assoc()['total_base'] ?? 0;
        $stmt_rts->close();
    }

    // 3. Apply 12% VAT precisely to the Net Base
    $net_base = $dr_base - $rts_base;
    $calculated_vat = $net_base * 0.12;
    $grand_total = round($net_base + $calculated_vat, 2);

    // =========================================================================

    // Insert Sale (FIXED: Status is now 'Issued' instead of 'Unpaid')
    $insert_sql = "INSERT INTO sales (customer_id, invoice_number, invoice_date, payment_due_date, total_amount, withholding_tax, chart_of_account_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Issued')";
    $stmt_sale = $conn->prepare($insert_sql);
    $stmt_sale->bind_param("isssddi", $customer_id, $invoice_number, $invoice_date, $due_date, $grand_total, $withholding_tax, $chart_of_account_id);
    $stmt_sale->execute();
    $sale_id = $conn->insert_id;
    $stmt_sale->close();

    // Link Delivery Receipts
    $link_sql = "INSERT INTO sales_items (sale_id, delivery_receipt_id) VALUES (?, ?)";
    $stmt_link = $conn->prepare($link_sql);
    $update_dr_sql = "UPDATE delivery_receipts SET is_invoiced = 1 WHERE id = ?";
    $stmt_update = $conn->prepare($update_dr_sql);

    foreach ($dr_ids as $dr_id) {
        $stmt_link->bind_param("ii", $sale_id, $dr_id);
        $stmt_link->execute();
        $stmt_update->bind_param("i", $dr_id);
        $stmt_update->execute();
    }

    // Link Return Receipts (RTS)
    if (!empty($rts_ids)) {
        $link_rts_sql = "INSERT INTO sales_rts (sale_id, return_receipt_id) VALUES (?, ?)";
        $stmt_link_rts = $conn->prepare($link_rts_sql);
        $update_rts_sql = "UPDATE return_receipts SET status = 'Deducted' WHERE id = ?";
        $stmt_update_rts = $conn->prepare($update_rts_sql);

        foreach ($rts_ids as $rid) {
            $stmt_link_rts->bind_param("ii", $sale_id, $rid);
            $stmt_link_rts->execute();
            $stmt_update_rts->bind_param("i", $rid);
            $stmt_update_rts->execute();
        }
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Invoice created successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>