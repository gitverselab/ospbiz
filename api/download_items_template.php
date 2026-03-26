<?php
// api/download_items_template.php
require_once "../config/access_control.php";
// Only allow logged-in users to download
require_role(['Admin', 'Accountant', 'Viewer']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=items_import_template.csv');

$output = fopen('php://output', 'w');

// Define the header row
$header = ['Item Name', 'Description', 'Unit'];
fputcsv($output, $header);

// Add a sample row to guide the user
$sample_row = ['Whole Chicken', 'Fresh whole chicken approx 1.2kg', 'pcs'];
fputcsv($output, $sample_row);

fclose($output);
exit;
?>