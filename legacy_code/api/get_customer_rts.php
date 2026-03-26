<?php
require_once "../config/database.php";
header('Content-Type: application/json');
$cid = (int)$_GET['customer_id'];
$data = $conn->query("SELECT * FROM return_receipts WHERE customer_id = $cid AND status = 'Pending' ORDER BY rts_date ASC")->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>