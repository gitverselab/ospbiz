<?php
// api/get_bill_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $response['message'] = 'Invalid Bill ID provided.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch the main bill details, joining to get names. Added b.bill_number
    $bill_sql = "SELECT b.*, bl.biller_name, coa.account_name 
                 FROM bills b 
                 LEFT JOIN billers bl ON b.biller_id = bl.id
                 LEFT JOIN chart_of_accounts coa ON b.chart_of_account_id = coa.id
                 WHERE b.id = ?";
    $stmt_bill = $conn->prepare($bill_sql);
    $stmt_bill->bind_param("i", $id);
    $stmt_bill->execute();
    $bill = $stmt_bill->get_result()->fetch_assoc();
    $stmt_bill->close();

    if ($bill) {
        // Fetch all associated payments for this bill
        $payments_sql = "SELECT * FROM bill_payments WHERE bill_id = ? ORDER BY payment_date DESC";
        $stmt_payments = $conn->prepare($payments_sql);
        $stmt_payments->bind_param("i", $id);
        $stmt_payments->execute();
        $payments = $stmt_payments->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_payments->close();
        
        // Add the payments array to the bill object
        $bill['payments'] = $payments;
        
        $response['success'] = true;
        $response['data'] = $bill;
    } else {
        $response['message'] = 'Bill not found.';
    }

} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>