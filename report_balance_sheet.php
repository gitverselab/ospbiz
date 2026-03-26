<?php
// report_balance_sheet.php
require_once "includes/header.php";
require_once "config/database.php";

$report_date = $_GET['report_date'] ?? date('Y-m-d');
$assets = ['cash_and_equivalents' => [], 'accounts_receivable' => []];
$liabilities = ['accounts_payable' => []];
$total_assets = 0;
$total_liabilities = 0;

// --- ASSETS ---
// 1. Cash
$cash_assets_sql = "
    SELECT ca.account_name, COALESCE(SUM(ct.credit) - SUM(ct.debit), 0) AS current_balance
    FROM cash_accounts ca
    LEFT JOIN cash_transactions ct ON ca.id = ct.cash_account_id AND ct.transaction_date <= ?
    GROUP BY ca.id, ca.account_name HAVING current_balance != 0";
$stmt_cash = $conn->prepare($cash_assets_sql);
$stmt_cash->bind_param("s", $report_date);
$stmt_cash->execute();
$cash_assets = $stmt_cash->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_cash->close();

$passbook_assets_sql = "
    SELECT CONCAT(p.bank_name, ' (', p.account_number, ')') as account_name,
    p.initial_balance + COALESCE(SUM(pt.credit), 0) - COALESCE(SUM(pt.debit), 0) AS current_balance
    FROM passbooks p
    LEFT JOIN passbook_transactions pt ON p.id = pt.passbook_id AND pt.transaction_date <= ?
    GROUP BY p.id, p.bank_name, p.account_number, p.initial_balance HAVING current_balance != 0";
$stmt_passbook = $conn->prepare($passbook_assets_sql);
$stmt_passbook->bind_param("s", $report_date);
$stmt_passbook->execute();
$passbook_assets = $stmt_passbook->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_passbook->close();

$assets['cash_and_equivalents'] = array_merge($cash_assets, $passbook_assets);
$total_cash = array_sum(array_column($assets['cash_and_equivalents'], 'current_balance'));
$total_assets += $total_cash;

// 2. Accounts Receivable
$ar_sql = "SELECT s.id, c.customer_name, (s.total_amount - s.withholding_tax) - (SELECT COALESCE(SUM(amount), 0) FROM sales_payments WHERE sale_id = s.id AND payment_date <= ?) as balance_due
           FROM sales s JOIN customers c ON s.customer_id = c.id
           WHERE s.status IN ('Issued', 'Partial') AND s.invoice_date <= ? HAVING balance_due > 0";
$stmt_ar = $conn->prepare($ar_sql);
$stmt_ar->bind_param("ss", $report_date, $report_date);
$stmt_ar->execute();
$ar_items = $stmt_ar->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ar->close();
$assets['accounts_receivable'] = $ar_items;
$total_ar = array_sum(array_column($assets['accounts_receivable'], 'balance_due'));
$total_assets += $total_ar;


// --- LIABILITIES (Accounts Payable) ---
$ap_sql = "
    SELECT * FROM (
        SELECT 'Purchase' as type, p.id, s.supplier_name COLLATE utf8mb4_unicode_ci as name, p.purchase_date as transaction_date,
        (p.amount - (SELECT COALESCE(SUM(pp.amount), 0) FROM purchase_payments pp LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check' WHERE pp.purchase_id = p.id AND (pp.payment_method != 'Check' OR c.status = 'Cleared') AND pp.payment_date <= ?)) as balance_due 
        FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.status IN ('Unpaid', 'Partially Paid')
        UNION ALL
        SELECT 'Bill' as type, b.id, bl.biller_name COLLATE utf8mb4_unicode_ci as name, b.bill_date as transaction_date,
        (b.amount - (SELECT COALESCE(SUM(bp.amount), 0) FROM bill_payments bp LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check' WHERE bp.bill_id = b.id AND (bp.payment_method != 'Check' OR c.status = 'Cleared') AND bp.payment_date <= ?)) as balance_due 
        FROM bills b JOIN billers bl ON b.biller_id = bl.id WHERE b.status IN ('Unpaid', 'Partially Paid')
    ) as all_payables WHERE all_payables.transaction_date <= ? AND all_payables.balance_due > 0.005
";
$stmt_ap = $conn->prepare($ap_sql);
$stmt_ap->bind_param("sss", $report_date, $report_date, $report_date);
$stmt_ap->execute();
$ap_items = $stmt_ap->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ap->close();
$liabilities['accounts_payable'] = $ap_items;
$total_ap = array_sum(array_column($liabilities['accounts_payable'], 'balance_due'));
$total_liabilities += $total_ap;


// --- EQUITY ---
// 1. Opening Balance
$opening_equity = 0;
$res_oe = $conn->query("SELECT SUM(initial_balance) as total FROM passbooks");
$opening_equity += $res_oe->fetch_assoc()['total'] ?? 0;

// 2. Retained Earnings (FIXED: Exclude Invoice Payments from manual totals)
$income_sql = "
    SELECT SUM(total) as grand_total FROM (
        SELECT SUM(amount) as total FROM sales_payments WHERE payment_date <= ?
        UNION ALL
        SELECT SUM(credit) as total FROM cash_transactions ct JOIN chart_of_accounts coa ON ct.chart_of_account_id = coa.id 
        WHERE coa.account_type = 'Income' AND ct.transaction_date <= ? AND ct.description NOT LIKE 'Payment for Sales Invoice%'
        UNION ALL
        SELECT SUM(credit) as total FROM passbook_transactions pt JOIN chart_of_accounts coa ON pt.chart_of_account_id = coa.id 
        WHERE coa.account_type = 'Income' AND pt.transaction_date <= ? AND pt.description NOT LIKE 'Payment for Sales Invoice%'
    ) as all_income";
$stmt_income = $conn->prepare($income_sql);
$stmt_income->bind_param("sss", $report_date, $report_date, $report_date);
$stmt_income->execute();
$total_income = $stmt_income->get_result()->fetch_assoc()['grand_total'] ?? 0;
$stmt_income->close();

$expense_sql = "
    SELECT SUM(total) as grand_total FROM (
        SELECT SUM(pp.amount) as total FROM purchase_payments pp LEFT JOIN checks c ON pp.reference_id=c.id AND pp.payment_method='Check' 
        WHERE pp.payment_date <= ? AND (pp.payment_method != 'Check' OR c.status='Cleared')
        UNION ALL
        SELECT SUM(bp.amount) as total FROM bill_payments bp LEFT JOIN checks c ON bp.reference_id=c.id AND bp.payment_method='Check' 
        WHERE bp.payment_date <= ? AND (bp.payment_method != 'Check' OR c.status='Cleared')
        UNION ALL
        SELECT SUM(e.amount) as total FROM expenses e LEFT JOIN checks c ON e.transaction_id=c.id AND e.payment_method='Check' 
        WHERE e.expense_date <= ? AND (e.payment_method != 'Check' OR c.status='Cleared')
    ) as all_expenses
";
$stmt_expenses = $conn->prepare($expense_sql);
$stmt_expenses->bind_param("sss", $report_date, $report_date, $report_date);
$stmt_expenses->execute();
$total_expenses = $stmt_expenses->get_result()->fetch_assoc()['grand_total'] ?? 0;
$stmt_expenses->close();

$retained_earnings = $total_income - $total_expenses;
$total_equity = $opening_equity + $retained_earnings;
$total_liabilities_and_equity = $total_liabilities + $total_equity;

$conn->close();
?>

<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">Balance Sheet</h2>
    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print Report</button>
</div>
<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="report_balance_sheet.php" class="flex items-center space-x-4">
        <div><label>As of Date:</label><input type="date" name="report_date" value="<?php echo htmlspecialchars($report_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md"></div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Generate Report</button>
    </form>
</div>
<div id="printable-area">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <div class="text-center mb-8">
            <h3 class="text-3xl font-bold">Onyang's Food Inc.</h3>
            <p class="text-xl mt-1">Balance Sheet</p>
            <p class="text-gray-600">As of <?php echo date("F j, Y", strtotime($report_date)); ?></p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <div>
                <h4 class="text-xl font-bold border-b-2 border-gray-800 pb-2 mb-4 text-blue-800">Assets</h4>
                <div class="mb-6"><h5 class="font-bold text-gray-700 mb-2">Cash and Cash Equivalents</h5><table class="w-full text-sm"><tbody><?php if(!empty($assets['cash_and_equivalents'])): foreach($assets['cash_and_equivalents'] as $item): ?><tr class="border-b border-gray-100"><td class="py-1 pr-2"><?php echo htmlspecialchars($item['account_name']); ?></td><td class="py-1 text-right">₱<?php echo number_format($item['current_balance'], 2); ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="py-1 text-gray-500 italic">No cash accounts with balance.</td></tr><?php endif; ?></tbody><tfoot class="font-semibold text-gray-900"><tr><td class="py-2">Total Cash</td><td class="py-2 text-right">₱<?php echo number_format($total_cash, 2); ?></td></tr></tfoot></table></div>
                <div class="mb-6"><h5 class="font-bold text-gray-700 mb-2">Accounts Receivable</h5><table class="w-full text-sm"><tbody><?php if(!empty($assets['accounts_receivable'])): foreach($assets['accounts_receivable'] as $item): ?><tr class="border-b border-gray-100"><td class="py-1 pr-2">INV-<?php echo $item['id'] . " (" . htmlspecialchars($item['customer_name']) . ")"; ?></td><td class="py-1 text-right">₱<?php echo number_format($item['balance_due'], 2); ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="py-1 text-gray-500 italic">No receivables.</td></tr><?php endif; ?></tbody><tfoot class="font-semibold text-gray-900"><tr><td class="py-2">Total Receivables</td><td class="py-2 text-right">₱<?php echo number_format($total_ar, 2); ?></td></tr></tfoot></table></div>
                <div class="mt-8 border-t-4 border-blue-800 pt-2 flex justify-between items-center"><span class="text-xl font-bold text-blue-900">Total Assets</span><span class="text-xl font-bold text-blue-900">₱<?php echo number_format($total_assets, 2); ?></span></div>
            </div>
            <div>
                <h4 class="text-xl font-bold border-b-2 border-gray-800 pb-2 mb-4 text-red-800">Liabilities & Equity</h4>
                <div class="mb-6"><h5 class="font-bold text-gray-700 mb-2">Liabilities (Accounts Payable)</h5><table class="w-full text-sm"><tbody><?php if(!empty($liabilities['accounts_payable'])): foreach($liabilities['accounts_payable'] as $item): ?><tr class="border-b border-gray-100"><td class="py-1 pr-2"><?php echo htmlspecialchars(ucfirst($item['type']) . ' #' . $item['id'] . " (" . $item['name'] . ")"); ?></td><td class="py-1 text-right">₱<?php echo number_format($item['balance_due'], 2); ?></td></tr><?php endforeach; else: ?><tr><td colspan="2" class="py-1 text-gray-500 italic">No payables.</td></tr><?php endif; ?></tbody></table><div class="flex justify-between font-bold border-t border-gray-300 pt-2 mt-1 text-red-700"><span>Total Liabilities</span><span>₱<?php echo number_format($total_liabilities, 2); ?></span></div></div>
                <div class="mb-6 mt-8"><h5 class="font-bold text-gray-700 mb-2">Equity</h5><table class="w-full text-sm"><tbody><tr class="border-b border-gray-100"><td class="py-1 pr-2">Opening Balance Equity</td><td class="py-1 text-right">₱<?php echo number_format($opening_equity, 2); ?></td></tr><tr class="border-b border-gray-100"><td class="py-1 pr-2">Retained Earnings</td><td class="py-1 text-right">₱<?php echo number_format($retained_earnings, 2); ?></td></tr></tbody></table><div class="flex justify-between font-bold border-t border-gray-300 pt-2 mt-1 text-green-700"><span>Total Equity</span><span>₱<?php echo number_format($total_equity, 2); ?></span></div></div>
                <div class="mt-8 border-t-4 border-red-800 pt-2 flex justify-between items-center"><span class="text-xl font-bold text-gray-900">Total Liabilities & Equity</span><span class="text-xl font-bold text-gray-900">₱<?php echo number_format($total_liabilities_and_equity, 2); ?></span></div>
            </div>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>