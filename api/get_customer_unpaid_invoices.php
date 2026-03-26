<?php
// api/get_customer_unpaid_invoices.php
require_once "../config/database.php";
header('Content-Type: application/json');

$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) { echo json_encode([]); exit; }

// Fetch invoices that are NOT fully paid
// Logic: (Total - Tax - Deductions) > Paid
// We select everything and let the JS filter/calc balance for precision
$sql = "
    SELECT s.id, s.invoice_number, s.invoice_date, s.total_amount, s.withholding_tax, s.total_deductions,
    (
        SELECT COALESCE(SUM(amount), 0) FROM sales_payments WHERE sale_id = s.id
    ) as total_paid
    FROM sales s
    WHERE s.customer_id = ? 
    AND s.status != 'Paid' 
    AND s.status != 'Canceled'
    ORDER BY s.invoice_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode($result);
$conn->close();
?>