<?php
require_once "../config/database.php";
header('Content-Type: application/json');

if (!isset($_FILES['csv_file']) || empty($_POST['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing file or customer.']); exit;
}

$customer_id = (int)$_POST['customer_id'];
$file = fopen($_FILES['csv_file']['tmp_name'], 'r');
fgetcsv($file); // Skip header

$count = 0;
$stmt = $conn->prepare("INSERT INTO return_receipts (customer_id, item_code, description, quantity, uom, price, total_amount, rts_date, po_number, rd_number, rts_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

while (($row = fgetcsv($file)) !== FALSE) {
    if (count($row) < 14) continue; // Skip incomplete rows

    $item_code = $row[0];
    $desc = $row[1];
    $qty = (float)$row[2];
    $uom = $row[3];
    $price = (float)$row[5];
    $gr_date_raw = $row[7]; // mm/dd/yyyy
    $total_val = (float)$row[10];
    $po = $row[11];
    $rd = $row[12];
    $gr = $row[13]; // This maps to rts_number

    $dateObj = DateTime::createFromFormat('m/d/Y', $gr_date_raw);
    $rts_date = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

    $stmt->bind_param("issdsddssss", $customer_id, $item_code, $desc, $qty, $uom, $price, $total_val, $rts_date, $po, $rd, $gr);
    if($stmt->execute()) $count++;
}

fclose($file);
echo json_encode(['success' => true, 'message' => "Successfully imported $count returns."]);
?>