<?php
// api/add_bill.php
require_once "../config/access_control.php";
check_permission('bills.create');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response); exit;
}

$bill_date = $_POST['bill_date'];
$due_date = $_POST['due_date'];
$biller_id = $_POST['biller_id'];
$bill_number = trim($_POST['bill_number'] ?? ''); 
$amount = $_POST['amount'];
$chart_of_account_id = $_POST['chart_of_account_id'];
$description = trim($_POST['description'] ?? '');

if (empty($bill_date) || empty($due_date) || empty($biller_id) || empty($bill_number) || empty($amount) || empty($chart_of_account_id)) {
    $response['message'] = 'Please fill all required fields, including the Bill/Invoice #.';
    echo json_encode($response); exit;
}

// Check for duplicate Bill Number before proceeding
$stmt_check = $conn->prepare("SELECT id FROM bills WHERE bill_number = ?");
$stmt_check->bind_param("s", $bill_number);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $response['message'] = 'This Bill Number already exists. Please enter a unique number.';
    echo json_encode($response);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

$sql = "INSERT INTO bills (bill_date, due_date, biller_id, bill_number, amount, chart_of_account_id, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
if ($stmt = $conn->prepare($sql)) {
    // FIXED: Changed "ssisids" to "ssisdis"
    // s=string, i=integer, d=double (for amount)
    // Order: date(s), date(s), id(i), number(s), amount(d), cat_id(i), desc(s)
    $stmt->bind_param("ssisdis", $bill_date, $due_date, $biller_id, $bill_number, $amount, $chart_of_account_id, $description);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Bill added successfully!';
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
echo json_encode($response);
?>