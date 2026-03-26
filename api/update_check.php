<?php
// api/update_check.php
require_once "../config/access_control.php";
check_permission('checks.update');

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.'; echo json_encode($response); exit;
}

$id = $_POST['id'] ?? null;
$check_date = $_POST['check_date'] ?? null;
$release_date = $_POST['release_date'] ?? date('Y-m-d'); // Default to today if missing
$check_number = trim($_POST['check_number'] ?? '');
$passbook_id = $_POST['passbook_id'] ?? null;
$payee = trim($_POST['payee'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);

if (empty($id) || empty($check_date) || empty($check_number) || empty($passbook_id) || empty($payee) || $amount <= 0) {
    $response['message'] = 'Please fill all required fields.'; echo json_encode($response); exit;
}

$conn->begin_transaction();
try {
    // 1. Verify Check Exists and is Issued
    $stmt_get = $conn->prepare("SELECT id FROM checks WHERE id = ? AND status = 'Issued' FOR UPDATE");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    if ($stmt_get->get_result()->num_rows === 0) {
        throw new Exception("Check cannot be edited. It may be Cleared/Canceled or does not exist.");
    }
    $stmt_get->close();
    
    // 2. Update Check
    $stmt_update = $conn->prepare("UPDATE checks SET check_date=?, release_date=?, check_number=?, payee=?, amount=?, passbook_id=? WHERE id=?");
    $stmt_update->bind_param("ssssdii", $check_date, $release_date, $check_number, $payee, $amount, $passbook_id, $id);
    $stmt_update->execute();
    $stmt_update->close();

    // 3. Update ALL Linked Payments (Bill / Purchase / Credit)
    // We update all tables just in case. If the check ID isn't found in a table, nothing happens (safe).
    
    // Purchase Payments
    $conn->query("UPDATE purchase_payments SET amount = $amount WHERE reference_id = $id AND payment_method = 'Check'");
    
    // Bill Payments
    $conn->query("UPDATE bill_payments SET amount = $amount WHERE reference_id = $id AND payment_method = 'Check'");
    
    // Credit Payments
    $conn->query("UPDATE credit_payments SET amount = $amount WHERE reference_id = $id AND payment_method = 'Check'");

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Check details updated successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Update failed: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>