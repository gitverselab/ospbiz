<?php
// api/update_customer.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sql = "UPDATE customers SET customer_name = ?, contact_person = ?, contact_email = ? WHERE id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssi", $_POST['customer_name'], $_POST['contact_person'], $_POST['contact_email'], $_POST['id']);
        if($stmt->execute()) {
            $response['success'] = true;
        } else {
            $response['message'] = $conn->errno === 1062 ? 'A customer with this name already exists.' : 'Database error.';
        }
        $stmt->close();
    }
    $conn->close();
}
echo json_encode($response);
?>