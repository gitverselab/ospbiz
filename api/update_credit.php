<?php
// api/update_credit.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $credit_date = $_POST['credit_date'];
    $creditor_name = trim($_POST['creditor_name']);
    $credit_ref_number = !empty(trim($_POST['credit_ref_number'])) ? trim($_POST['credit_ref_number']) : null;
    $amount = (float)$_POST['amount'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $description = trim($_POST['description']);

    if (empty($id) || empty($credit_date) || empty($creditor_name) || $amount <= 0) {
        $response['message'] = 'Please fill all required fields.';
        echo json_encode($response); exit;
    }
    
    // Check received amount
    $rcv = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM credit_receipts WHERE credit_id = $id")->fetch_assoc()['total'];
    if ($amount < $rcv) {
        $response['message'] = 'Approved amount cannot be less than what has already been received (' . $rcv . ').';
        echo json_encode($response); exit;
    }

    if ($credit_ref_number !== null) {
        $stmt_check = $conn->prepare("SELECT id FROM credits WHERE credit_ref_number = ? AND id != ?");
        $stmt_check->bind_param("si", $credit_ref_number, $id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $response['message'] = 'This Reference Number is already in use.';
            echo json_encode($response); exit;
        }
        $stmt_check->close();
    }

    $sql = "UPDATE credits SET credit_date = ?, creditor_name = ?, credit_ref_number = ?, amount = ?, due_date = ?, description = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sssdsi", $credit_date, $creditor_name, $credit_ref_number, $amount, $due_date, $description, $id);
        $stmt->execute();
        $response['success'] = true;
        $stmt->close();
    }
    $conn->close();
}
echo json_encode($response);
?>