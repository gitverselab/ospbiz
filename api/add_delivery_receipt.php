<?php
// api/add_delivery_receipt.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $price = (float)($_POST['price'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $vat_inclusive_amount = ($price * $quantity) * 1.12;

    $sql = "INSERT INTO delivery_receipts (customer_id, item_code, description, uom, quantity, price, currency, dr_number, delivery_date, plant_name, plant_code, po_number, gr_number, vat_inclusive_amount, delivery_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("isssddsssssssds", 
            $_POST['customer_id'], $_POST['item_code'], $_POST['description'], $_POST['uom'],
            $quantity, $price, $_POST['currency'], $_POST['dr_number'], $_POST['delivery_date'],
            $_POST['plant_name'], $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'],
            $vat_inclusive_amount, $_POST['delivery_status']
        );
        if($stmt->execute()) { $response['success'] = true; } else { $response['message'] = 'DB Error: '.$stmt->error; }
    }
    $conn->close();
}
echo json_encode($response);
?>