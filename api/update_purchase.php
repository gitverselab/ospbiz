<?php
// api/update_purchase.php
session_start();
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

$purchase_id = $_POST['id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;
$po_number = trim($_POST['po_number'] ?? ''); // Get PO number
$chart_of_account_id = !empty($_POST['chart_of_account_id']) ? $_POST['chart_of_account_id'] : null;
$purchase_date = $_POST['purchase_date'] ?? null;
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$description = trim($_POST['description'] ?? '');
$items = $_POST['items'] ?? [];

if (empty($purchase_id) || empty($supplier_id) || empty($po_number) || empty($purchase_date) || empty($items)) {
    $response['message'] = 'Missing required fields (Supplier, PO #, Date, Items).'; echo json_encode($response); exit;
}

// Check for duplicate PO Number on OTHER records
$stmt_check = $conn->prepare("SELECT id FROM purchases WHERE po_number = ? AND id != ?");
$stmt_check->bind_param("si", $po_number, $purchase_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $response['message'] = 'This PO Number is already in use by another purchase. Please enter a unique PO number.';
    echo json_encode($response);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

$conn->begin_transaction();
try {
    $total_amount = 0;
    foreach ($items['quantity'] as $key => $quantity) {
        $unit_price = $items['unit_price'][$key];
        if (is_numeric($quantity) && is_numeric($unit_price)) {
            $total_amount += $quantity * $unit_price;
        }
    }

    // UPDATED: Added po_number to the UPDATE query
    $sql_purchase = "UPDATE purchases SET supplier_id=?, po_number=?, purchase_date=?, due_date=?, amount=?, chart_of_account_id=?, description=? WHERE id=?";
    $stmt_purchase = $conn->prepare($sql_purchase);
    // Note the updated bind_param types and variables
    $stmt_purchase->bind_param("isssdisi", $supplier_id, $po_number, $purchase_date, $due_date, $total_amount, $chart_of_account_id, $description, $purchase_id);
    $stmt_purchase->execute();

    $stmt_del_items = $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?");
    $stmt_del_items->bind_param("i", $purchase_id);
    $stmt_del_items->execute();

    $sql_items = "INSERT INTO purchase_items (purchase_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
    $stmt_items = $conn->prepare($sql_items);
    foreach ($items['item_id'] as $key => $item_id) {
        if (empty($item_id)) continue;
        $quantity = $items['quantity'][$key];
        $unit_price = $items['unit_price'][$key];
        $stmt_items->bind_param("iidd", $purchase_id, $item_id, $quantity, $unit_price);
        $stmt_items->execute();
    }
    
    $conn->commit();
    $response['success'] = true;
    $_SESSION['message'] = ['type' => 'success', 'text' => 'Purchase updated successfully!'];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Failed to update purchase: ' . $e->getMessage();
}

echo json_encode($response);
?>