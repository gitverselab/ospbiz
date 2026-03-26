<?php
// api/update_delivery_receipt.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Note: Removed vat_inclusive_amount as it's a calculated field in the DB
    $sql = "UPDATE delivery_receipts SET customer_id=?, delivery_date=?, dr_number=?, po_number=?, item_code=?, description=?, quantity=?, uom=?, price=?, delivery_status=? WHERE id=?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isssssdsdsi", 
            $_POST['customer_id'], $_POST['delivery_date'], $_POST['dr_number'], $_POST['po_number'], 
            $_POST['item_code'], $_POST['description'], $_POST['quantity'], $_POST['uom'], 
            $_POST['price'], $_POST['delivery_status'], $_POST['id']
        );
        if($stmt->execute()) { $response['success'] = true; }
        else { $response['message'] = 'Database error: ' . $stmt->error; }
        $stmt->close();
    }
    $conn->close();
}
echo json_encode($response);
?>