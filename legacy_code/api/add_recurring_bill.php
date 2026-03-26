<?php
// api/add_recurring_bill.php
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response); exit;
}

$biller_id = $_POST['biller_id'] ?? null;
$chart_of_account_id = $_POST['chart_of_account_id'] ?? null;
$amount = $_POST['amount'] ?? null;
$frequency = $_POST['frequency'] ?? null;
$recur_day = $_POST['recur_day'] ?? null;
$start_date = $_POST['start_date'] ?? null;
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
$description = trim($_POST['description'] ?? '');

if (empty($biller_id) || empty($chart_of_account_id) || empty($amount) || empty($frequency) || empty($recur_day) || empty($start_date)) {
    $response['message'] = 'Please fill all required fields.';
    echo json_encode($response); exit;
}

$sql = "INSERT INTO recurring_bills (biller_id, chart_of_account_id, amount, frequency, recur_day, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iidsisss", $biller_id, $chart_of_account_id, $amount, $frequency, $recur_day, $start_date, $end_date, $description);
    if ($stmt->execute()) {
        $response['success'] = true;
    } else {
        $response['message'] = 'Database error: ' . $stmt->error;
    }
    $stmt->close();
}
$conn->close();
echo json_encode($response);
?>