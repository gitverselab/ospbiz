<?php
// api/get_credit_details.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $response['message'] = 'Invalid Credit ID provided.';
    echo json_encode($response);
    exit;
}

try {
    // Fetch the main credit details
    $stmt = $conn->prepare("SELECT id, credit_date, creditor_name, credit_ref_number, amount, status, due_date, description FROM credits WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $credit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($credit) {
        $response['success'] = true;
        $response['data'] = $credit;
    } else {
        $response['message'] = 'Credit record not found.';
    }

} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>