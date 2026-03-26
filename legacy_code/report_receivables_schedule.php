<?php
// report_receivables_schedule.php
require_once "includes/header.php";
require_once "config/database.php";

// --- Initialize variables ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$receivables = [];

// --- SQL Query to fetch all receivables with full DR details ---
$sql = "
    SELECT 
        s.id,
        s.invoice_number,
        s.payment_due_date,
        c.customer_name as name,
        (s.total_amount - s.withholding_tax) - (SELECT COALESCE(SUM(amount), 0) FROM sales_payments WHERE sale_id = s.id) as balance_due,
        -- Group all DR details into a single string for display
        GROUP_CONCAT(
            DISTINCT CONCAT(
                'DR#', dr.dr_number, ': ', 
                dr.description, 
                ' (', dr.quantity, ' ', dr.uom, 
                ' @ ₱', FORMAT(dr.price, 2), '/pc, ',
                'ex-VAT: ₱', FORMAT(dr.total_value, 2), ')'
            ) SEPARATOR '\n'
        ) COLLATE utf8mb4_unicode_ci as details
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    LEFT JOIN sales_items si ON s.id = si.sale_id
    LEFT JOIN delivery_receipts dr ON si.delivery_receipt_id = dr.id
    WHERE s.status IN ('Issued', 'Partial') AND s.payment_due_date BETWEEN ? AND ?
    GROUP BY s.id, s.invoice_number, s.payment_due_date, c.customer_name
    HAVING balance_due > 0.01
    ORDER BY s.payment_due_date ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$receivables = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$total_receivable = 0;
?>

<style>
    @media print {
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
    }
</style>
<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">Receivables Schedule Report</h2>
    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print Report</button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="report_receivables_schedule.php" class="flex items-center space-x-4">
        <div>
            <label for="start_date" class="text-sm font-medium">From Due Date:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <div>
            <label for="end_date" class="text-sm font-medium">To Due Date:</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Generate Report</button>
    </form>
</div>

<div id="printable-area">
    <div class="bg-white p-6 rounded-lg shadow-md print:shadow-none">
        <div class="text-center mb-6">
            <h3 class="text-2xl font-bold">Onyang's Food Inc.</h3>
            <p class="text-lg">Receivables Schedule</p>
            <p class="text-gray-600">For invoices due between <?php echo date("F j, Y", strtotime($start_date)); ?> and <?php echo date("F j, Y", strtotime($end_date)); ?></p>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 text-left">Due Date</th>
                    <th class="p-2 text-left">Customer / Invoice #</th>
                    <th class="p-2 text-left">Included DRs / Details</th>
                    <th class="p-2 text-right">Balance Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($receivables)): foreach ($receivables as $item): 
                    $total_receivable += $item['balance_due'];
                ?>
                    <tr class="border-b">
                        <td class="p-2 align-top"><?php echo htmlspecialchars($item['payment_due_date']); ?></td>
                        <td class="p-2 align-top">
                            <a href="view_sale.php?id=<?php echo $item['id']; ?>" class="font-semibold text-blue-600 hover:underline"><?php echo htmlspecialchars($item['name']); ?></a>
                            <p class="text-xs text-gray-500">#<?php echo htmlspecialchars($item['invoice_number']); ?></p>
                        </td>
                        <td class="p-2 align-top text-xs text-gray-700 whitespace-pre-line">
                            <?php echo htmlspecialchars($item['details']); ?>
                        </td>
                        <td class="p-2 text-right align-top font-bold">₱<?php echo number_format($item['balance_due'], 2); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4" class="p-4 text-center text-gray-500">No receivables found in the selected date range.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-100 font-bold border-t-2 border-gray-300">
                <tr>
                    <td colspan="3" class="p-3 text-right text-gray-700">Total Receivables:</td>
                    <td class="p-3 text-right text-blue-600">₱<?php echo number_format($total_receivable, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>