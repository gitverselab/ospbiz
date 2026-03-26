<?php
// api/delete_delivery_receipt.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];

    $check_sql = "SELECT COUNT(*) as count FROM sales_items WHERE delivery_receipt_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['count'] > 0) {
        $response['message'] = 'Cannot delete: This DR is linked to a sales invoice.';
    } else {
        $sql = "DELETE FROM delivery_receipts WHERE id = ?";
        if($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id);
            if($stmt->execute()) { $response['success'] = true; }
            else { $response['message'] = 'Database error.'; }
            $stmt->close();
        }
    }
    $conn->close();
}
echo json_encode($response);
?>