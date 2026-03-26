<?php
// api/update_sale.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? null;
    $invoice_date = $_POST['invoice_date'] ?? null;
    $payment_due_date = $_POST['payment_due_date'] ?? null;

    if (empty($id) || empty($invoice_date) || empty($payment_due_date)) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        exit;
    }

    $sql = "UPDATE sales SET invoice_date = ?, payment_due_date = ? WHERE id = ?";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $invoice_date, $payment_due_date, $id);
        if($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}
echo json_encode($response);
?>