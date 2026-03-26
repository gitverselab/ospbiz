<?php
// api/get_sale_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

$sale_id = $_GET['id'] ?? null;
$response = ['success' => false];

if (!$sale_id) {
    $response['message'] = 'Sale ID is required.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch main sale details
    $sale_sql = "SELECT s.*, c.customer_name FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.id = ?";
    $stmt_sale = $conn->prepare($sale_sql);
    $stmt_sale->bind_param("i", $sale_id);
    $stmt_sale->execute();
    $sale_result = $stmt_sale->get_result();
    
    if ($sale_result->num_rows === 0) { throw new Exception("Sale not found."); }
    $sale = $sale_result->fetch_assoc();

    // Fetch linked delivery receipts (Uses * to avoid unknown column errors)
    $dr_sql = "SELECT dr.* FROM delivery_receipts dr JOIN sales_items si ON dr.id = si.delivery_receipt_id WHERE si.sale_id = ?";
    $stmt_dr = $conn->prepare($dr_sql);
    $stmt_dr->bind_param("i", $sale_id);
    $stmt_dr->execute();
    $delivery_receipts = $stmt_dr->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch linked return receipts (RTS)
    $return_receipts = [];
    $check_rts_table = $conn->query("SHOW TABLES LIKE 'sales_rts'");
    if ($check_rts_table->num_rows > 0) {
        $rts_sql = "SELECT rr.* FROM return_receipts rr JOIN sales_rts sr ON rr.id = sr.return_receipt_id WHERE sr.sale_id = ?";
        $stmt_rts = $conn->prepare($rts_sql);
        $stmt_rts->bind_param("i", $sale_id);
        $stmt_rts->execute();
        $return_receipts = $stmt_rts->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch payments
    $payment_sql = "SELECT * FROM sales_payments WHERE sale_id = ? ORDER BY payment_date DESC";
    $stmt_payment = $conn->prepare($payment_sql);
    $stmt_payment->bind_param("i", $sale_id);
    $stmt_payment->execute();
    $payments = $stmt_payment->get_result()->fetch_all(MYSQLI_ASSOC);

    $sale['delivery_receipts'] = $delivery_receipts;
    $sale['return_receipts'] = $return_receipts;
    $sale['payments'] = $payments;

    $response['success'] = true;
    $response['sale'] = $sale;

} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>