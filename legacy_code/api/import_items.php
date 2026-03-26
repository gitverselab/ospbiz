<?php
// api/import_items.php
require_once "../config/access_control.php";
check_permission('items.create'); // Ensure user has permission to add items

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'imported_count' => 0, 'error_count' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Please upload a valid CSV file.';
    echo json_encode($response);
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === FALSE) {
    $response['message'] = 'Could not open the file.';
    echo json_encode($response);
    exit;
}

// Skip the header row
fgetcsv($handle); 

$imported = 0;
$errors = 0;
$row_num = 1;

$stmt = $conn->prepare("INSERT INTO items (item_name, item_description, unit) VALUES (?, ?, ?)");

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $row_num++;
    
    // Basic validation: Item Name (column 0) is required
    $item_name = trim($data[0] ?? '');
    $description = trim($data[1] ?? '');
    $unit = trim($data[2] ?? '');

    if (empty($item_name)) {
        $errors++;
        continue; // Skip empty rows
    }

    // Check for duplicate name to prevent SQL errors
    $check = $conn->query("SELECT id FROM items WHERE item_name = '" . $conn->real_escape_string($item_name) . "'");
    if ($check->num_rows > 0) {
        $errors++; // Skip duplicates
        continue;
    }

    $stmt->bind_param("sss", $item_name, $description, $unit);
    
    if ($stmt->execute()) {
        $imported++;
    } else {
        $errors++;
    }
}

fclose($handle);
$stmt->close();
$conn->close();

$response['success'] = true;
$response['imported_count'] = $imported;
$response['error_count'] = $errors;
$response['message'] = "Import complete. Imported: $imported, Skipped/Errors: $errors";

echo json_encode($response);
?>