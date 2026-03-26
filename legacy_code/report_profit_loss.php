<?php
// report_profit_loss.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/header.php";
require_once "config/database.php";

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// ==========================================
// 1. RECONCILIATION LOGIC
// ==========================================
$cash_sql = "SELECT SUM(amount) as total FROM sales_payments WHERE payment_date BETWEEN ? AND ?";
$stmt = $conn->prepare($cash_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_cash_collected = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$wht_sql = "SELECT SUM(CASE WHEN (s.total_amount - s.withholding_tax) > 0 THEN sp.amount * (s.withholding_tax / (s.total_amount - s.withholding_tax)) ELSE 0 END) as realized_wht FROM sales_payments sp JOIN sales s ON sp.sale_id = s.id WHERE sp.payment_date BETWEEN ? AND ? AND s.status != 'Void'";
$stmt = $conn->prepare($wht_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_wht = $stmt->get_result()->fetch_assoc()['realized_wht'] ?? 0;
$stmt->close();

$gross_sales_cash_basis = $total_cash_collected + $total_wht;

// ==========================================
// 2. INCOME
// ==========================================
$income_details = [];
$income_sql = "SELECT sp.payment_date, s.id as sale_id, s.invoice_number, c.customer_name, COALESCE(coa.account_name, 'Uncategorized Income') as account_name, sp.amount FROM sales_payments sp JOIN sales s ON sp.sale_id = s.id JOIN customers c ON s.customer_id = c.id LEFT JOIN chart_of_accounts coa ON s.chart_of_account_id = coa.id WHERE sp.payment_date BETWEEN ? AND ? AND coa.account_type = 'Income' ORDER BY sp.payment_date DESC";
$stmt = $conn->prepare($income_sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { 
    $row['url'] = "view_sale.php?id=" . $row['sale_id'];
    $income_details[] = $row; 
}
$stmt->close();

$income_categories = [];
foreach ($income_details as $row) {
    if (!isset($income_categories[$row['account_name']])) $income_categories[$row['account_name']] = 0;
    $income_categories[$row['account_name']] += $row['amount'];
}

// ==========================================
// 3. EXPENSES (ROBUST DOUBLE-CHECK LOGIC)
// ==========================================
$expense_details = [];

$expense_sql = "
    -- 1. Purchase Payments
    SELECT 
        pp.payment_date as date, 
        'Purchase Payment' as source, 
        p.id as ref_id, 
        CONCAT('PO#', p.po_number) COLLATE utf8mb4_unicode_ci as reference, 
        COALESCE(coa.account_name, 'Uncategorized Expense') as account_name, 
        pp.amount, 
        pp.payment_method,
        COALESCE(c_direct.status, c_ledger.status, 'Issued') as check_status, 
        'view_purchase.php?id=' as link_base
    FROM purchase_payments pp 
    JOIN purchases p ON pp.purchase_id = p.id 
    LEFT JOIN chart_of_accounts coa ON p.chart_of_account_id = coa.id
    LEFT JOIN checks c_direct ON pp.reference_id = c_direct.id
    LEFT JOIN passbook_transactions pt ON pp.reference_id = pt.id
    LEFT JOIN checks c_ledger ON pt.check_ref_id = c_ledger.id
    WHERE pp.payment_date BETWEEN ? AND ?
    AND (
        (UPPER(pp.payment_method) LIKE '%CHECK%' AND COALESCE(c_direct.status, c_ledger.status) = 'Cleared') 
        OR 
        (UPPER(pp.payment_method) NOT LIKE '%CHECK%')
    )

    UNION ALL
    
    -- 2. Bill Payments
    SELECT 
        bp.payment_date as date, 
        'Bill Payment' as source, 
        b.id as ref_id, 
        CONCAT('Bill#', b.bill_number) COLLATE utf8mb4_unicode_ci as reference, 
        COALESCE(coa.account_name, 'Uncategorized Expense') as account_name, 
        bp.amount, 
        bp.payment_method,
        COALESCE(c_direct.status, c_ledger.status, 'Issued') as check_status,
        'view_bill.php?id=' as link_base
    FROM bill_payments bp 
    JOIN bills b ON bp.bill_id = b.id 
    LEFT JOIN chart_of_accounts coa ON b.chart_of_account_id = coa.id
    LEFT JOIN checks c_direct ON bp.reference_id = c_direct.id
    LEFT JOIN passbook_transactions pt ON bp.reference_id = pt.id
    LEFT JOIN checks c_ledger ON pt.check_ref_id = c_ledger.id
    WHERE bp.payment_date BETWEEN ? AND ?
    AND (
        (UPPER(bp.payment_method) LIKE '%CHECK%' AND COALESCE(c_direct.status, c_ledger.status) = 'Cleared') 
        OR 
        (UPPER(bp.payment_method) NOT LIKE '%CHECK%')
    )

    UNION ALL
    
    -- 3. Direct Expenses
    SELECT 
        expense_date as date, 
        'Direct Expense Record' as source, 
        e.id as ref_id, 
        CONCAT('Exp#', e.id) COLLATE utf8mb4_unicode_ci as reference, 
        COALESCE(coa.account_name, 'Uncategorized Expense') as account_name, 
        e.amount, 
        e.payment_method,
        COALESCE(c_direct.status, c_ledger.status, 'Issued') as check_status,
        'expenses.php?highlight=' as link_base
    FROM expenses e
    LEFT JOIN chart_of_accounts coa ON e.chart_of_account_id = coa.id
    LEFT JOIN checks c_direct ON e.transaction_id = c_direct.id
    LEFT JOIN passbook_transactions pt ON e.transaction_id = pt.id
    LEFT JOIN checks c_ledger ON pt.check_ref_id = c_ledger.id
    WHERE expense_date BETWEEN ? AND ?
    AND (
        (UPPER(e.payment_method) LIKE '%CHECK%' AND COALESCE(c_direct.status, c_ledger.status) = 'Cleared') 
        OR 
        (UPPER(e.payment_method) NOT LIKE '%CHECK%')
    )

    UNION ALL

    -- 4. Credit Interest
    SELECT 
        payment_date as date, 
        'Loan Interest' as source, 
        credit_id as ref_id, 
        CONCAT('Credit Pay#', id) COLLATE utf8mb4_unicode_ci as reference, 
        'Interest Expense' as account_name, 
        interest_amount as amount, 
        payment_method,
        'Cleared' as check_status,
        'credits.php?highlight=' as link_base
    FROM credit_payments WHERE payment_date BETWEEN ? AND ? AND interest_amount > 0

    ORDER BY date DESC
";

$stmt = $conn->prepare($expense_sql);
$stmt->bind_param("ssssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if($row['source'] == 'Loan Interest') $row['url'] = "credits.php";
    elseif ($row['source'] == 'Direct Expense Record') $row['url'] = "expenses.php";
    else $row['url'] = $row['link_base'] . $row['ref_id'];
    $expense_details[] = $row;
}
$stmt->close();

$expense_categories = [];
$total_expenses = 0;
foreach ($expense_details as $row) {
    if (!isset($expense_categories[$row['account_name']])) $expense_categories[$row['account_name']] = 0;
    $expense_categories[$row['account_name']] += $row['amount'];
    $total_expenses += $row['amount'];
}

$net_profit = $total_cash_collected - $total_expenses;

// ==========================================
// 4. EQUITY & FINANCING
// ==========================================
$equity_items = [];
$equity_sql = "
    SELECT date, account_name, description, amount, type FROM (
        SELECT sp.payment_date as date, COALESCE(coa.account_name, 'Equity Investment') as account_name, CONVERT(CONCAT('Inv: ', c.customer_name) USING utf8mb4) as description, sp.amount, 'In' as type FROM sales_payments sp JOIN sales s ON sp.sale_id = s.id JOIN customers c ON s.customer_id = c.id LEFT JOIN chart_of_accounts coa ON s.chart_of_account_id = coa.id WHERE sp.payment_date BETWEEN ? AND ? AND coa.account_type = 'Equity'
        UNION ALL
        SELECT expense_date as date, COALESCE(coa.account_name, 'Drawings') as account_name, CONVERT(e.description USING utf8mb4) as description, amount, 'Out' as type FROM expenses e LEFT JOIN chart_of_accounts coa ON e.chart_of_account_id = coa.id WHERE expense_date BETWEEN ? AND ? AND coa.account_type = 'Equity'
        UNION ALL
        SELECT bp.payment_date as date, COALESCE(coa.account_name, 'Drawings') as account_name, CONVERT(b.description USING utf8mb4) as description, bp.amount, 'Out' as type FROM bill_payments bp JOIN bills b ON bp.bill_id = b.id LEFT JOIN chart_of_accounts coa ON b.chart_of_account_id = coa.id WHERE bp.payment_date BETWEEN ? AND ? AND coa.account_type = 'Equity'
        UNION ALL
        SELECT credit_date as date, 'Loan Proceeds' as account_name, CONVERT(CONCAT('Loan from ', creditor_name) USING utf8mb4) as description, amount, 'In' as type FROM credits WHERE status IN ('Received', 'Paid', 'Partially Paid') AND credit_date BETWEEN ? AND ?
        UNION ALL
        SELECT payment_date as date, 'Loan Principal Payment' as account_name, CONVERT(CONCAT('Payment - Credit #', credit_id) USING utf8mb4) as description, principal_amount as amount, 'Out' as type FROM credit_payments WHERE payment_date BETWEEN ? AND ? AND principal_amount > 0
    ) AS combined_equity ORDER BY date DESC
";
$stmt = $conn->prepare($equity_sql);
$stmt->bind_param("ssssssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $equity_items[] = $row; }
$stmt->close();
?>

<style>
    @media print { 
        @page { size: portrait; margin: 10mm; } 
        body * { visibility: hidden; } 
        #report-container, #report-container * { visibility: visible; } 
        #report-container { position: absolute; left: 0; top: 0; width: 100%; } 
        .no-print { display: none !important; } 
        .bg-gray-100 { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; } 
    }
    .details-row { font-size: 0.85em; color: #555; background-color: #fefefe; }
    .details-row td { padding-top: 4px; padding-bottom: 4px; border-bottom: 1px dashed #eee; vertical-align: top; }
    .report-link { color: #2563eb; text-decoration: none; border-bottom: 1px dotted #2563eb; }
    .report-link:hover { color: #1e40af; border-bottom: 1px solid #1e40af; background-color: #eff6ff; }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Profit & Loss (Cash Basis)</h2>
    <button onclick="window.print()" class="w-full md:w-auto bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
        Print Report
    </button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">From</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">To</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">Generate</button>
    </form>
</div>

<div id="report-container" class="max-w-4xl mx-auto bg-white p-4 md:p-8 rounded-lg shadow-lg border border-gray-200">
    <div class="text-center mb-8 border-b pb-4">
        <h1 class="text-xl md:text-2xl font-bold uppercase tracking-wide">Statement of Profit & Loss</h1>
        <p class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">(Cash Basis / Actual Cleared Payments)</p>
        <p class="text-gray-600 mt-1 text-sm">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> — <?php echo date('M d, Y', strtotime($end_date)); ?></p>
    </div>

    <div class="mb-8">
        <div class="flex justify-between items-center border-b-2 border-gray-800 pb-1 mb-3">
            <h3 class="text-base md:text-lg font-bold text-gray-700 uppercase">Income (Collected)</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm mb-4">
                <?php foreach ($income_categories as $cat_name => $cat_total): ?>
                <tr class="font-bold bg-gray-50">
                    <td class="py-2 pl-2 text-gray-800"><?php echo htmlspecialchars($cat_name); ?></td>
                    <td class="py-2 text-right whitespace-nowrap">₱<?php echo number_format($cat_total, 2); ?></td>
                </tr>
                <?php foreach ($income_details as $detail): if ($detail['account_name'] === $cat_name): ?>
                <tr class="details-row">
                    <td class="pl-4 md:pl-6 italic text-xs md:text-sm break-words pr-2">
                        <?php echo $detail['payment_date']; ?> - <?php echo $detail['customer_name']; ?> 
                        <span class="text-gray-400 nowrap">(Inv# <a href="<?php echo $detail['url']; ?>" target="_blank" class="report-link"><?php echo $detail['invoice_number']; ?></a>)</span>
                    </td>
                    <td class="text-right text-gray-500 whitespace-nowrap align-top">₱<?php echo number_format($detail['amount'], 2); ?></td>
                </tr>
                <?php endif; endforeach; endforeach; ?>
                
                <tr class="border-t-2 border-gray-300 font-bold text-base bg-green-50">
                    <td class="py-3 pl-2">Total Cash Income</td>
                    <td class="py-3 text-right text-green-700 whitespace-nowrap">₱<?php echo number_format($total_cash_collected, 2); ?></td>
                </tr>
            </table>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 p-4 rounded text-xs text-gray-700 space-y-2">
            <div class="flex justify-between">
                <span>Total Gross Sales (Cash Basis):</span>
                <span class="font-semibold">₱<?php echo number_format($gross_sales_cash_basis, 2); ?></span>
            </div>
            <div class="flex justify-between text-red-600">
                <span>Less: Withholding Tax (Deducted):</span>
                <span>(₱<?php echo number_format($total_wht, 2); ?>)</span>
            </div>
            <div class="flex justify-between font-bold border-t border-yellow-300 pt-2 mt-1 text-green-800 text-sm">
                <span>Equals: Actual Cash Collected:</span>
                <span>₱<?php echo number_format($total_cash_collected, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <div class="flex justify-between items-center border-b-2 border-gray-800 pb-1 mb-3">
            <h3 class="text-base md:text-lg font-bold text-gray-700 uppercase">Expenses (Paid & Cleared)</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <?php foreach ($expense_categories as $cat_name => $cat_total): ?>
                <tr class="font-bold bg-gray-50">
                    <td class="py-2 pl-2 text-gray-800"><?php echo htmlspecialchars($cat_name); ?></td>
                    <td class="py-2 text-right whitespace-nowrap">₱<?php echo number_format($cat_total, 2); ?></td>
                </tr>
                <?php foreach ($expense_details as $detail): if ($detail['account_name'] === $cat_name): ?>
                <tr class="details-row">
                    <td class="pl-4 md:pl-6 italic text-xs md:text-sm break-words pr-2">
                        <span class="font-semibold text-gray-600 whitespace-nowrap"><?php echo $detail['date']; ?></span> - 
                        <?php echo $detail['source']; ?> 
                        <span class="text-xs text-blue-600 whitespace-nowrap">(<a href="<?php echo $detail['url']; ?>" target="_blank" class="report-link"><?php echo $detail['reference']; ?></a>)</span>
                        
                        <?php if(strpos(strtoupper($detail['payment_method']), 'CHECK') !== false): ?>
                            <span class="ml-1 text-[10px] px-1 rounded whitespace-nowrap <?php echo ($detail['check_status'] == 'Cleared') ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'; ?>">
                                Check: <?php echo $detail['check_status']; ?>
                            </span>
                        <?php else: ?>
                            <span class="ml-1 text-[10px] text-gray-400 whitespace-nowrap">[<?php echo $detail['payment_method']; ?>]</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right text-gray-500 whitespace-nowrap align-top">₱<?php echo number_format($detail['amount'], 2); ?></td>
                </tr>
                <?php endif; endforeach; endforeach; ?>
                
                <?php if (empty($expense_categories)): ?><tr><td colspan="2" class="py-2 text-gray-400 italic">No cleared/paid expenses in this period.</td></tr><?php endif; ?>
                <tr class="border-t font-bold text-base bg-red-50">
                    <td class="py-2 pt-3 pl-2">Total Expenses Paid</td>
                    <td class="py-2 pt-3 text-right text-red-700 whitespace-nowrap">₱<?php echo number_format($total_expenses, 2); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="border-t-4 border-gray-800 pt-4 flex flex-col md:flex-row justify-between items-center mb-10 gap-2">
        <h3 class="text-lg md:text-xl font-bold uppercase">Net Profit / (Loss)</h3>
        <span class="text-2xl md:text-3xl font-bold <?php echo $net_profit >= 0 ? 'text-green-800' : 'text-red-600'; ?>">₱<?php echo number_format($net_profit, 2); ?></span>
    </div>

    <?php if (!empty($equity_items)): ?>
    <div class="mb-8 p-4 bg-blue-50 border border-blue-200 rounded">
        <div class="flex justify-between items-center border-b border-blue-300 pb-2 mb-3">
            <h3 class="text-base md:text-lg font-bold text-blue-800 uppercase">Financing & Equity Activities</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-blue-200">
                    <?php foreach ($equity_items as $eq): ?>
                    <tr>
                        <td class="py-2 text-gray-600 whitespace-nowrap pr-2"><?php echo $eq['date']; ?></td>
                        <td class="py-2 font-bold text-gray-700 whitespace-nowrap pr-2"><?php echo htmlspecialchars($eq['account_name']); ?></td>
                        <td class="py-2 text-gray-600 italic break-words min-w-[150px]"><?php echo htmlspecialchars($eq['description']); ?></td>
                        <td class="py-2 text-right font-mono font-bold whitespace-nowrap <?php echo $eq['type']=='In' ? 'text-green-700' : 'text-red-700'; ?>">
                            <?php echo $eq['type']=='In' ? '+' : '-'; ?> ₱<?php echo number_format($eq['amount'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require_once "includes/footer.php"; ?>