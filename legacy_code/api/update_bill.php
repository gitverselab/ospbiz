<?php
// api/update_bill.php
require_once "../config/access_control.php";
check_permission('bills.update');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Retrieve POST data
$id = $_POST['id'] ?? null;
$bill_date = $_POST['bill_date'] ?? null;
$due_date = $_POST['due_date'] ?? null;
$biller_id = $_POST['biller_id'] ?? null;
$bill_number = trim($_POST['bill_number'] ?? '');
$chart_of_account_id = $_POST['chart_of_account_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$description = trim($_POST['description'] ?? '');

// Validate required fields
if (empty($id) || empty($bill_date) || empty($due_date) || empty($biller_id) || empty($bill_number) || empty($chart_of_account_id) || !is_numeric($amount)) {
    $response['message'] = 'Required fields are missing or invalid.';
    echo json_encode($response);
    exit;
}

// Check for duplicate Bill Number on OTHER records (excluding self)
$stmt_check = $conn->prepare("SELECT id FROM bills WHERE bill_number = ? AND id != ?");
$stmt_check->bind_param("si", $bill_number, $id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $response['message'] = 'This Bill Number is already in use by another bill.';
    echo json_encode($response);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

// Update query
$sql = "UPDATE bills SET bill_date = ?, due_date = ?, biller_id = ?, bill_number = ?, chart_of_account_id = ?, amount = ?, description = ? WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    // FIXED: "ssisidsi" (8 characters for 8 variables)
    // s=string, i=integer, d=double(decimal)
    $stmt->bind_param("ssisidsi", $bill_date, $due_date, $biller_id, $bill_number, $chart_of_account_id, $amount, $description, $id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Bill updated successfully.';
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'Database prepare error: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>