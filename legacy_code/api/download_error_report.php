<?php
// api/download_error_report.php
session_start();

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$filename = basename($_GET['file']);
$filepath = '../uploads/' . $filename;

// Security check: ensure file is within the intended directory
if (!file_exists($filepath) || strpos(realpath($filepath), realpath('../uploads')) !== 0) {
    die("File not found or access denied.");
}

header('Content-Description: File Transfer');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
