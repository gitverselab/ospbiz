<?php
// api/get_purchase_details.php
require_once "../config/access_control.php";
check_permission('purchases.update'); // Or 'purchases.view' if you have separate permissions

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
$id = $_GET['id'] ?? null;
if (!$id) { 
    $response['message'] = 'Purchase ID not provided.';
    echo json_encode($response);
    exit;
}

try {
    $purchase_sql = "SELECT p.*, s.supplier_name, coa.account_name 
                     FROM purchases p 
                     JOIN suppliers s ON p.supplier_id = s.id
                     LEFT JOIN chart_of_accounts coa ON p.chart_of_account_id = coa.id
                     WHERE p.id = ?";
    $stmt_purchase = $conn->prepare($purchase_sql);
    $stmt_purchase->bind_param("i", $id);
    $stmt_purchase->execute();
    $purchase = $stmt_purchase->get_result()->fetch_assoc();

    if ($purchase) {
        // Fetch items
        $items_sql = "SELECT pi.item_id, pi.quantity, pi.unit_price, i.item_name 
                      FROM purchase_items pi
                      JOIN items i ON pi.item_id = i.id
                      WHERE pi.purchase_id = ?";
        $stmt_items = $conn->prepare($items_sql);
        $stmt_items->bind_param("i", $id);
        $stmt_items->execute();
        $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
        $purchase['items'] = $items;

        // Fetch payments WITH CHECK STATUS
        // We LEFT JOIN checks to get the status and check number
        $payments_sql = "
            SELECT pp.*, c.check_number, c.status as check_status
            FROM purchase_payments pp
            LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
            WHERE pp.purchase_id = ? 
            ORDER BY pp.payment_date DESC";
            
        $stmt_payments = $conn->prepare($payments_sql);
        $stmt_payments->bind_param("i", $id);
        $stmt_payments->execute();
        $payments = $stmt_payments->get_result()->fetch_all(MYSQLI_ASSOC);
        $purchase['payments'] = $payments;

        $response['success'] = true;
        $response['purchase'] = $purchase;
    } else {
        $response['message'] = 'Purchase not found.';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>