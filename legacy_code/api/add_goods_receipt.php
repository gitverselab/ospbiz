<?php
require_once "../config/database.php";
session_start();
// Auth check
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic validation
    $required_fields = ['gr_date', 'dr_number', 'item_code', 'description', 'quantity', 'price'];
    foreach($required_fields as $field) {
        if(empty($_POST[$field])) {
            header("location: ../goods_receipt.php?error=emptyfields");
            exit;
        }
    }

    $sql = "INSERT INTO goods_receipts (gr_date, dr_number, item_code, description, uom, quantity, price, plant_name, po_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssssddss", 
            $_POST['gr_date'], 
            $_POST['dr_number'], 
            $_POST['item_code'], 
            $_POST['description'], 
            $_POST['uom'], 
            $_POST['quantity'], 
            $_POST['price'], 
            $_POST['plant_name'], 
            $_POST['po_number']
        );

        if($stmt->execute()) {
            header("location: ../goods_receipt.php?success=created");
        } else {
            header("location: ../goods_receipt.php?error=sqlerror");
        }
        $stmt->close();
    }
    $conn->close();
}
?>
