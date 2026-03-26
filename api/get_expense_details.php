<?php
// api/get_expense_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $response['message'] = 'Invalid Expense ID provided.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $expense = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($expense) {
        $response['success'] = true;
        $response['data'] = $expense;
    } else {
        $response['message'] = 'Expense record not found.';
    }
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>