<?php
// report_reconciliation.php
require_once "includes/header.php";
require_once "config/database.php";

// --- 1. FILTERS & PAGINATION ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// --- 2. BUILD BASE QUERY ---
// Logic: Sales that have a remaining "Cash Balance" > 0 (meaning either Unpaid OR WHT deducted)
// AND fall within the selected invoice date range.
$base_where = "
    WHERE s.invoice_date BETWEEN ? AND ? 
    AND (s.total_amount - COALESCE(p.total_cash_paid, 0)) > 0.01
";

// --- 3. GET TOTALS (For Summary Cards & Pagination) ---
// We sum everything for the selected date range to keep the cards accurate.
$totals_sql = "
    SELECT 
        COUNT(s.id) as total_records,
        SUM(s.total_amount) as grand_invoiced,
        SUM(s.withholding_tax) as grand_wht,
        SUM(COALESCE(p.total_cash_paid, 0)) as grand_cash
    FROM sales s
    LEFT JOIN (
        SELECT sale_id, SUM(amount) as total_cash_paid 
        FROM sales_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    $base_where
";

$stmt_total = $conn->prepare($totals_sql);
$stmt_total->bind_param("ss", $start_date, $end_date);
$stmt_total->execute();
$totals = $stmt_total->get_result()->fetch_assoc();
$stmt_total->close();

$total_records = $totals['total_records'];
$grand_invoiced = $totals['grand_invoiced'] ?? 0;
$grand_cash = $totals['grand_cash'] ?? 0;
$grand_wht = $totals['grand_wht'] ?? 0;

// Calculate the 'Unpaid' part of the discrepancy
// Formula: Invoiced - Cash - WHT = True Unpaid Balance
$grand_unpaid = $grand_invoiced - $grand_cash - $grand_wht;
$grand_variance = $grand_wht + $grand_unpaid; // Total missing from Bank

$total_pages = ceil($total_records / $limit);

// --- 4. FETCH DATA FOR CURRENT PAGE ---
$sql = "
    SELECT 
        s.invoice_number, 
        s.invoice_date, 
        c.customer_name, 
        s.total_amount as invoiced_amount,
        s.withholding_tax as wht_deducted,
        COALESCE(p.total_cash_paid, 0) as cash_collected,
        (s.total_amount - s.withholding_tax - COALESCE(p.total_cash_paid, 0)) as balance_due
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    LEFT JOIN (
        SELECT sale_id, SUM(amount) as total_cash_paid 
        FROM sales_payments 
        GROUP BY sale_id
    ) p ON s.id = p.sale_id
    $base_where
    ORDER BY s.invoice_date DESC, balance_due DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $start_date, $end_date, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<style>
    @media print {
        @page { size: landscape; margin: 10mm; }
        body * { visibility: hidden; }
        #report-content, #report-content * { visibility: visible; }
        #report-content { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">Sales vs. Cash Reconciliation</h2>
    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
        Print Report
    </button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase">From Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase">To Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase">Show Rows</label>
            <select name="limit" class="w-full border rounded p-2 text-sm" onchange="this.form.submit()">
                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">Apply Filter</button>
        </div>
        <div>
            <a href="report_reconciliation.php" class="block text-center w-full bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded text-sm">Reset</a>
        </div>
    </form>
</div>

<div id="report-content">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow border-l-4 border-blue-500">
            <div class="text-sm text-gray-500">Total Invoiced (P&L)</div>
            <div class="text-xl font-bold">₱<?php echo number_format($grand_invoiced, 2); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-green-500">
            <div class="text-sm text-gray-500">Cash Collected (Bank)</div>
            <div class="text-xl font-bold">₱<?php echo number_format($grand_cash, 2); ?></div>
        </div>
        <div class="bg-white p-4 rounded shadow border-l-4 border-orange-500">
            <div class="text-sm text-gray-500">Non-Cash (Variance)</div>
            <div class="text-xl font-bold text-orange-600">₱<?php echo number_format($grand_variance, 2); ?></div>
            <div class="text-xs text-gray-400">Total missing from Bank</div>
        </div>
        <div class="bg-gray-50 p-4 rounded shadow border border-gray-200 text-sm">
            <p class="font-bold text-gray-700 mb-1">Variance Breakdown:</p>
            <div class="flex justify-between">
                <span>Withholding Tax:</span>
                <span class="font-bold text-red-600">₱<?php echo number_format($grand_wht, 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span>Unpaid Balance:</span>
                <span class="font-bold text-red-600">₱<?php echo number_format($grand_unpaid, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded shadow">
        <h3 class="font-bold text-lg mb-4">Transactions Causing Discrepancy (<?php echo $total_records; ?> Found)</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="p-2">Invoice #</th>
                        <th class="p-2">Date</th>
                        <th class="p-2">Customer</th>
                        <th class="p-2 text-right">Invoiced Amt</th>
                        <th class="p-2 text-right text-green-700">Cash Paid</th>
                        <th class="p-2 text-right text-red-600">WHT (Tax)</th>
                        <th class="p-2 text-right text-red-600">Unpaid Bal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($items)): ?>
                        <tr><td colspan="7" class="p-4 text-center text-gray-500">No discrepancies found in this date range.</td></tr>
                    <?php else: foreach($items as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-2 font-mono font-bold text-blue-600"><?php echo htmlspecialchars($item['invoice_number']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($item['invoice_date']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($item['customer_name']); ?></td>
                        <td class="p-2 text-right font-bold">₱<?php echo number_format($item['invoiced_amount'], 2); ?></td>
                        <td class="p-2 text-right text-green-700">₱<?php echo number_format($item['cash_collected'], 2); ?></td>
                        <td class="p-2 text-right text-orange-600 font-bold"><?php echo ($item['wht_deducted'] > 0) ? '₱'.number_format($item['wht_deducted'], 2) : '-'; ?></td>
                        <td class="p-2 text-right text-red-600 font-bold"><?php echo ($item['balance_due'] > 0) ? '₱'.number_format($item['balance_due'], 2) : '-'; ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center no-print">
            <span class="text-sm text-gray-700 mb-2 sm:mb-0">
                Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Results
            </span>
            
            <div class="flex gap-1">
                <?php 
                    // Preserve filters for pagination links
                    $query_params = $_GET; 
                ?>

                <?php if ($page > 1): ?>
                    <?php $query_params['page'] = 1; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">« First</a>
                    
                    <?php $query_params['page'] = $page - 1; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">‹ Prev</a>
                <?php else: ?>
                    <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">« First</span>
                    <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">‹ Prev</span>
                <?php endif; ?>

                <?php 
                $range = 2; 
                $start_num = max(1, $page - $range);
                $end_num = min($total_pages, $page + $range);

                if ($start_num > 1) { echo '<span class="px-2 py-1">...</span>'; }

                for ($i = $start_num; $i <= $end_num; $i++): 
                    $query_params['page'] = $i;
                ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" 
                       class="px-3 py-1 border rounded <?php echo $i == $page ? 'bg-blue-600 text-white font-bold' : 'bg-white hover:bg-gray-100'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; 

                if ($end_num < $total_pages) { echo '<span class="px-2 py-1">...</span>'; }
                ?>

                <?php if ($page < $total_pages): ?>
                    <?php $query_params['page'] = $page + 1; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">Next ›</a>
                    
                    <?php $query_params['page'] = $total_pages; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">Last »</a>
                <?php else: ?>
                    <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">Next ›</span>
                    <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">Last »</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>