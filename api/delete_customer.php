<?php
// api/delete_customer.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];

    // Safety Check: See if customer has any sales linked
    $check_sql = "SELECT COUNT(*) as count FROM sales WHERE customer_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        $response['message'] = 'Cannot delete this customer because they have sales records associated with them.';
    } else {
        $sql = "DELETE FROM customers WHERE id = ?";
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id);
            if($stmt->execute()) {
                $response['success'] = true;
            } else {
                $response['message'] = 'Database error during deletion.';
            }
            $stmt->close();
        }
    }
    $conn->close();
}
echo json_encode($response);
?>