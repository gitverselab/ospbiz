<?php
// api/get_unpaid_items.php
require_once "../config/database.php";
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$items = [];

switch ($type) {
    case 'purchase':
        $sql = "SELECT p.id, CONCAT('Purchase #', p.id, ' - ', s.supplier_name) as name, 
                       (p.amount - (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id)) as balance_due
                FROM purchases p
                JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.status IN ('Unpaid', 'Partially Paid')
                HAVING balance_due > 0";
        if ($result = $conn->query($sql)) {
            $items = $result->fetch_all(MYSQLI_ASSOC);
        }
        break;
    case 'bill':
        $sql = "SELECT b.id, CONCAT('Bill #', b.id, ' - ', bl.biller_name) as name,
                       (b.amount - (SELECT COALESCE(SUM(amount), 0) FROM bill_payments WHERE bill_id = b.id)) as balance_due
                FROM bills b
                JOIN billers bl ON b.biller_id = bl.id
                WHERE b.status IN ('Unpaid', 'Partially Paid')
                HAVING balance_due > 0";
        if ($result = $conn->query($sql)) {
            $items = $result->fetch_all(MYSQLI_ASSOC);
        }
        break;
    case 'credit':
        $sql = "SELECT c.id, CONCAT('Credit #', c.id, ' - ', c.creditor_name) as name,
                       (c.amount - (SELECT COALESCE(SUM(amount), 0) FROM credit_payments WHERE credit_id = c.id)) as balance_due
                FROM credits c
                WHERE c.status IN ('Received', 'Partially Paid')
                HAVING balance_due > 0";
        if ($result = $conn->query($sql)) {
            $items = $result->fetch_all(MYSQLI_ASSOC);
        }
        break;
}

$conn->close();
echo json_encode($items);
?>