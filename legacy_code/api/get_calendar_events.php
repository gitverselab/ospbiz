<?php
// api/get_calendar_events.php
require_once "../config/database.php";
header('Content-Type: application/json');

$events = [];

try {
    // 1. Fetch Unpaid/Partially Paid Purchases with details
    $purchases_sql = "
        SELECT 
            p.id, p.due_date, p.amount, s.supplier_name, p.po_number, p.description,
            (p.amount - (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id)) as balance_due,
            GROUP_CONCAT(i.item_name SEPARATOR ', ') as items
        FROM purchases p
        JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
        LEFT JOIN items i ON pi.item_id = i.id
        WHERE p.status IN ('Unpaid', 'Partially Paid') AND p.due_date IS NOT NULL
        GROUP BY p.id
        HAVING balance_due > 0
    ";
    if ($result = $conn->query($purchases_sql)) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'title' => 'Purchase Due: ' . $row['supplier_name'] . ' (₱' . number_format($row['balance_due'], 2) . ')',
                'start' => $row['due_date'],
                'url'   => "view_purchase.php?id=" . $row['id'],
                'backgroundColor' => '#F97316', // Orange
                'borderColor' => '#F97316',
                'extendedProps' => [
                    'type' => 'Purchase',
                    'po_number' => $row['po_number'],
                    'description' => $row['description'],
                    'items' => $row['items']
                ]
            ];
        }
    }

    // 2. Fetch Unpaid/Partially Paid Bills with details
    $bills_sql = "
        SELECT 
            b.id, b.due_date, b.amount, bl.biller_name, b.bill_number, b.description,
            (b.amount - (SELECT COALESCE(SUM(amount), 0) FROM bill_payments WHERE bill_id = b.id)) as balance_due
        FROM bills b
        JOIN billers bl ON b.biller_id = bl.id
        WHERE b.status IN ('Unpaid', 'Partially Paid') AND b.due_date IS NOT NULL
        HAVING balance_due > 0
    ";
    if ($result = $conn->query($bills_sql)) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'title' => 'Bill Due: ' . $row['biller_name'] . ' (₱' . number_format($row['balance_due'], 2) . ')',
                'start' => $row['due_date'],
                'url'   => "view_bill.php?id=" . $row['id'],
                'backgroundColor' => '#EF4444', // Red
                'borderColor' => '#EF4444',
                'extendedProps' => [
                    'type' => 'Bill',
                    'bill_number' => $row['bill_number'],
                    'description' => $row['description']
                ]
            ];
        }
    }

    // 3. Fetch all 'Issued' (uncleared) checks with details
    $checks_sql = "
        SELECT 
            c.id, c.check_date, c.amount, c.payee, c.check_number,
            CASE
                WHEN pp.purchase_id IS NOT NULL THEN CONCAT('Purchase: ', s.supplier_name)
                WHEN bp.bill_id IS NOT NULL THEN CONCAT('Bill: ', bl.biller_name)
                WHEN crp.credit_id IS NOT NULL THEN CONCAT('Credit Pmt: ', cr.creditor_name)
                ELSE 'Manual/Other'
            END as linked_to
        FROM checks c
        LEFT JOIN purchase_payments pp ON c.id = pp.reference_id
        LEFT JOIN purchases p ON pp.purchase_id = p.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN bill_payments bp ON c.id = bp.reference_id
        LEFT JOIN bills b ON bp.bill_id = b.id
        LEFT JOIN billers bl ON b.biller_id = bl.id
        LEFT JOIN credit_payments crp ON c.id = crp.reference_id
        LEFT JOIN credits cr ON crp.credit_id = cr.id
        WHERE c.status = 'Issued'
        GROUP BY c.id
    ";
    if ($result = $conn->query($checks_sql)) {
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'title' => 'Check Funding: ' . $row['payee'] . ' (₱' . number_format($row['amount'], 2) . ')',
                'start' => $row['check_date'],
                'url'   => 'checks.php',
                'backgroundColor' => '#8B5CF6', // Purple
                'borderColor' => '#8B5CF6',
                'extendedProps' => [
                    'type' => 'Check',
                    'check_number' => $row['check_number'],
                    'linked_to' => $row['linked_to']
                ]
            ];
        }
    }

} catch (Exception $e) {
    // Return error as a JSON object
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$conn->close();
echo json_encode($events);
?>