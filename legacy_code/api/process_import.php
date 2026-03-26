<?php
// api/process_import.php
session_start();
require_once "../config/database.php";

// Initialize status array
$status = [
    'success' => false, 'message' => 'An unknown error occurred.',
    'total_rows' => 0, 'imported_count' => 0, 'duplicate_count' => 0, 'error_count' => 0,
    'error_file' => null
];
$error_rows = []; // To store rows that failed

// Basic validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['customer_id']) || empty($_FILES['csv_file']['tmp_name'])) {
    $status['message'] = 'Invalid request. Please select a customer and a file.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}

$customer_id = (int)$_POST['customer_id'];
$file_path = $_FILES['csv_file']['tmp_name'];

if (($handle = fopen($file_path, "r")) === FALSE) {
    $status['message'] = 'Error opening the uploaded file.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}

// Get header row and clean it
$header_from_file = fgetcsv($handle, 2000, ",");
if (!$header_from_file) {
    $status['message'] = 'Could not read the header from the CSV file.';
    $_SESSION['import_status'] = $status;
    header("Location: ../import_delivery_receipts.php");
    exit;
}
$header_from_file[0] = preg_replace('/^\x{EF}\x{BB}\x{BF}/', '', $header_from_file[0]);
$header_from_file = array_map('trim', $header_from_file);

// Map header names to expected columns
$expected_headers = [
    'Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 
    'Reference Doc', 'GR Date', 'Plant Code', 'Plant Name', 'Vat Inc.', 
    'PO Number', 'GR Number'
];
foreach($expected_headers as $expected_header) {
    if (!in_array($expected_header, $header_from_file)) {
        $status['message'] = "CSV header mismatch. Missing column: '{$expected_header}'.";
        $_SESSION['import_status'] = $status;
        header("Location: ../import_delivery_receipts.php");
        exit;
    }
}


// Prepare statement for insertion
$stmt = $conn->prepare("INSERT INTO delivery_receipts (customer_id, item_code, description, uom, quantity, price, currency, dr_number, delivery_date, plant_name, plant_code, po_number, gr_number, vat_inclusive_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
    $status['total_rows']++;
    
    // --- Data Extraction and Validation ---
    $row_data = [];
    foreach ($expected_headers as $header) {
       $index = array_search($header, $header_from_file);
       // handle case where a column might be missing in a row
       $row_data[$header] = isset($data[$index]) ? trim($data[$index]) : '';
    }

    // Error check: Skip row if essential data is missing
    if (empty($row_data['GR Number']) || empty($row_data['Item Code'])) {
        $status['error_count']++;
        $error_rows[] = array_merge($data, ['ErrorReason' => 'Missing GR Number or Item Code']);
        continue;
    }

    // Data cleaning and type conversion
    $quantity = (float)str_replace(',', '', $row_data['Qty']);
    $total_price_from_csv = (float)str_replace(',', '', $row_data['Price']);
    $price = ($quantity != 0) ? ($total_price_from_csv / $quantity) : 0;
    $vat_inclusive_amount = (float)str_replace(',', '', $row_data['Vat Inc.']);

    // Date validation
    $date_obj = date_create_from_format('M j, Y', $row_data['GR Date']);
    if (!$date_obj) $date_obj = date_create($row_data['GR Date']);
    
    if (!$date_obj) {
        $status['error_count']++;
        $error_rows[] = array_merge($data, ['ErrorReason' => "Invalid Date Format: {$row_data['GR Date']}"]);
        continue;
    }
    $delivery_date = $date_obj->format('Y-m-d');
    
    // Execute insertion
    $stmt->bind_param("isssddsssssssd", 
        $customer_id, $row_data['Item Code'], $row_data['Description'], $row_data['UOM'], 
        $quantity, $price, $row_data['Currency'], $row_data['Reference Doc'], 
        $delivery_date, $row_data['Plant Name'], $row_data['Plant Code'], 
        $row_data['PO Number'], $row_data['GR Number'], $vat_inclusive_amount
    );

    if ($stmt->execute()) {
        $status['imported_count']++;
    } else {
        $status['error_count']++;
        $error_rows[] = array_merge($data, ['ErrorReason' => 'Database Insert Error']);
    }
}

fclose($handle);
$stmt->close();
$conn->close();

// If there were errors, create a report file
if (!empty($error_rows)) {
    $error_filename = 'error_report_' . time() . '.csv';
    $error_filepath = '../uploads/' . $error_filename;
    
    if (!is_dir('../uploads')) {
        mkdir('../uploads', 0755, true);
    }

    $error_handle = fopen($error_filepath, 'w');
    fputcsv($error_handle, array_merge($header_from_file, ['Reason for Failure']));
    foreach ($error_rows as $row) {
        fputcsv($error_handle, $row);
    }
    fclose($error_handle);
    $status['error_file'] = $error_filename;
}

// Set final success message
$status['success'] = true;
if ($status['imported_count'] > 0) {
    $status['message'] = 'Import process completed.';
} else {
    $status['message'] = 'Import process completed, but no new rows were imported.';
}

$_SESSION['import_status'] = $status;
header("Location: ../import_delivery_receipts.php");
exit;
?>

