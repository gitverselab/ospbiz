<?php
// api/add_purchase.php
session_start();
require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.'; echo json_encode($response); exit;
}

// Get POST data
$supplier_id = $_POST['supplier_id'];
$po_number = trim($_POST['po_number'] ?? ''); // Get PO number
$chart_of_account_id = !empty($_POST['chart_of_account_id']) ? $_POST['chart_of_account_id'] : null;
$purchase_date = $_POST['purchase_date'];
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$description = trim($_POST['description'] ?? '');

// LINKING FIELDS
// Capture Project ID
$project_id = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
// Capture Request ID
$request_id = !empty($_POST['request_id']) ? $_POST['request_id'] : null;

$items = $_POST['items'];

// Basic validation
if (empty($supplier_id) || empty($po_number) || empty($purchase_date) || empty($items) || !is_array($items)) {
    $response['message'] = 'Missing required purchase data (Supplier, PO #, Date, Items).'; echo json_encode($response); exit;
}

// Check for duplicate PO Number before proceeding
$stmt_check = $conn->prepare("SELECT id FROM purchases WHERE po_number = ?");
$stmt_check->bind_param("s", $po_number);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    $response['message'] = 'This PO Number already exists. Please enter a unique PO number.';
    echo json_encode($response);
    $stmt_check->close();
    exit;
}
$stmt_check->close();

// Calculate total amount from items
$total_amount = 0;
foreach ($items['quantity'] as $key => $quantity) {
    $unit_price = $items['unit_price'][$key];
    if (is_numeric($quantity) && is_numeric($unit_price)) {
        $total_amount += $quantity * $unit_price;
    }
}

$conn->begin_transaction();
try {
    // UPDATED: Added request_id to the INSERT query
    $sql_purchase = "INSERT INTO purchases (supplier_id, po_number, purchase_date, due_date, amount, status, chart_of_account_id, description, project_id, request_id) VALUES (?, ?, ?, ?, ?, 'Unpaid', ?, ?, ?, ?)";
    $stmt_purchase = $conn->prepare($sql_purchase);
    // Updated bind_param: added 'i' at the end for request_id
    $stmt_purchase->bind_param("isssdisii", $supplier_id, $po_number, $purchase_date, $due_date, $total_amount, $chart_of_account_id, $description, $project_id, $request_id);
    $stmt_purchase->execute();

    $purchase_id = $conn->insert_id;

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
    $_SESSION['message'] = ['type' => 'success', 'text' => 'Purchase added successfully!'];

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = 'Failed to add purchase: ' . $e->getMessage();
}

echo json_encode($response);
?>