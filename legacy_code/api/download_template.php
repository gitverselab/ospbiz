<?php
// api/download_template.php

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=delivery_receipt_template.csv');

$output = fopen('php://output', 'w');

// Define the header row to EXACTLY match the user's provided file header
$header = [
    'Item Code', 
    'Description', 
    'Qty', 
    'UOM', 
    'Currency', 
    'Price', 
    'Reference Doc', 
    'GR Date', 
    'Plant Code', 
    'Plant Name', 
    'Vat Inc.', 
    'PO Number', 
    'GR Number'
];

fputcsv($output, $header);

// Add a sample row to guide the user and show the correct format
$sample_row = [
    '4000005565',
    'Fruit,macapuno,sweetened,frozen,500g/pk',
    '4000',
    'PAC',
    'PHP',
    '299040.00',
    '3583',
    '2025-01-03',
    'ZL07',
    'ZF Logistics Bulacan Cold',
    '334924.80',
    '3082076919',
    '5011779797'
];
fputcsv($output, $sample_row);

fclose($output);
exit;
?>

