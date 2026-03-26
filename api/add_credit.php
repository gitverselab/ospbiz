<?php
// api/add_credit.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $credit_date = $_POST['credit_date'];
    $creditor_name = trim($_POST['creditor_name']);
    $credit_ref_number = !empty(trim($_POST['credit_ref_number'])) ? trim($_POST['credit_ref_number']) : null;
    $amount = $_POST['amount'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $description = trim($_POST['description']);
    
    if (empty($credit_date) || empty($creditor_name) || !is_numeric($amount) || $amount <= 0) {
        $response['message'] = 'Please fill all required fields.';
        echo json_encode($response); exit;
    }

    // Check for duplicate Reference Number
    if ($credit_ref_number !== null) {
        $stmt_check = $conn->prepare("SELECT id FROM credits WHERE credit_ref_number = ?");
        $stmt_check->bind_param("s", $credit_ref_number);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $response['message'] = 'This Reference Number already exists. Please enter a unique number or leave it blank.';
            echo json_encode($response);
            $stmt_check->close();
            exit;
        }
        $stmt_check->close();
    }

    $sql = "INSERT INTO credits (credit_date, creditor_name, credit_ref_number, amount, due_date, description, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    
    if ($stmt = $conn->prepare($sql)) {
        // FIXED LINE BELOW: Changed "sssds" to "sssdss" (Added 6th 's' for description)
        $stmt->bind_param("sssdss", $credit_date, $creditor_name, $credit_ref_number, $amount, $due_date, $description);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Credit added successfully.';
        } else {
            $response['message'] = 'Database error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Statement preparation failed: ' . $conn->error;
    }
    
    $conn->close();
}
echo json_encode($response);
?>