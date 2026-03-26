<?php
// index.php (Dashboard)

require_once "includes/header.php";
require_once "config/database.php";

// --- DATE FILTER INITIALIZATION ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- DATA FETCHING ---

// 1. Key Financial Summaries (SNAPSHOTS)
$total_cash_on_hand = $conn->query("SELECT SUM(current_balance) as total FROM cash_accounts")->fetch_assoc()['total'] ?? 0;
$total_bank_balance = $conn->query("SELECT SUM(current_balance) as total FROM passbooks")->fetch_assoc()['total'] ?? 0;

$ar_sql = "SELECT SUM(total_amount - withholding_tax) as total FROM sales WHERE status IN ('Issued', 'Partial')";
$accounts_receivable = $conn->query($ar_sql)->fetch_assoc()['total'] ?? 0;

$ap_sql = "SELECT SUM(balance_due) as total FROM (
    SELECT p.amount - (
        SELECT COALESCE(SUM(pp.amount), 0) 
        FROM purchase_payments pp 
        LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
        WHERE pp.purchase_id = p.id 
        AND (pp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
    ) as balance_due
    FROM purchases p 
    WHERE p.status != 'Canceled' 
    HAVING balance_due > 0.01

    UNION ALL

    SELECT b.amount - (
        SELECT COALESCE(SUM(bp.amount), 0) 
        FROM bill_payments bp 
        LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
        WHERE bp.bill_id = b.id 
        AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
    ) as balance_due
    FROM bills b 
    WHERE b.status != 'Canceled'
    HAVING balance_due > 0.01
) as outstanding_payables";

$accounts_payable = $conn->query($ap_sql)->fetch_assoc()['total'] ?? 0;


// 2. Cash Flow Chart Data
$monthly_data = [];
$month_labels = [];

$start    = new DateTime($start_date);
$start->modify('first day of this month');
$end      = new DateTime($end_date);
$end->modify('first day of next month');
$interval = DateInterval::createFromDateString('1 month');
$period   = new DatePeriod($start, $interval, $end);

foreach ($period as $dt) {
    $m_code = $dt->format("Y-m");
    $monthly_data[$m_code] = ['income' => 0, 'expenses' => 0];
    $month_labels[] = $dt->format("M Y");
}

// Income Flow
// FIX: Exclude Transfers AND Sales Payments (since they are counted in Sales Revenue if we were separating them, but for Cash Flow we use raw transactions)
// For Cash Flow Bar Chart, we actually WANT to count everything that hit the bank, 
// but we must exclude internal transfers.
$income_sql = "
    (SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(credit) as total 
     FROM cash_transactions 
     WHERE credit > 0 
       AND description NOT LIKE 'Transfer%' 
       AND description NOT LIKE '%Transfer In%'
       AND transaction_date BETWEEN ? AND ? 
     GROUP BY month)
    UNION ALL
    (SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(credit) as total 
     FROM passbook_transactions 
     WHERE credit > 0 
       AND description NOT LIKE 'Transfer%' 
       AND description NOT LIKE '%Transfer In%'
       AND transaction_date BETWEEN ? AND ? 
     GROUP BY month)";

$stmt_inc = $conn->prepare($income_sql);
$stmt_inc->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$stmt_inc->execute();
$result = $stmt_inc->get_result();
while ($row = $result->fetch_assoc()) {
    if (isset($monthly_data[$row['month']])) {
        $monthly_data[$row['month']]['income'] += (float)$row['total'];
    }
}
$stmt_inc->close();

// Expense Flow
$expenses_sql = "
    (SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(debit) as total 
     FROM cash_transactions 
     WHERE debit > 0 
       AND description NOT LIKE 'Transfer%' 
       AND description NOT LIKE '%(Transfer Out)'
       AND transaction_date BETWEEN ? AND ? 
     GROUP BY month)
    UNION ALL
    (SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(debit) as total 
     FROM passbook_transactions 
     WHERE debit > 0 
       AND description NOT LIKE 'Transfer%' 
       AND description NOT LIKE '%(Transfer Out)'
       AND transaction_date BETWEEN ? AND ? 
     GROUP BY month)";

$stmt_exp = $conn->prepare($expenses_sql);
$stmt_exp->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$stmt_exp->execute();
$result = $stmt_exp->get_result();
while ($row = $result->fetch_assoc()) {
    if (isset($monthly_data[$row['month']])) {
        $monthly_data[$row['month']]['expenses'] += (float)$row['total'];
    }
}
$stmt_exp->close();

$income_chart_data = array_column($monthly_data, 'income');
$expenses_chart_data = array_column($monthly_data, 'expenses');


// 3. Income Sources Breakdown (The Pie Chart)
// FIX: We prevent double counting here.
// If it's in 'Sales Revenue', we don't want to count the corresponding Bank Transaction in 'Client Sales'.
$income_breakdown_sql = "
    SELECT label, SUM(total) as total_amount FROM (
        -- 1. Sales Revenue (From Invoices)
        SELECT 'Sales Revenue' as label, SUM(amount) as total FROM sales_payments WHERE payment_date BETWEEN ? AND ?
        
        UNION ALL
        
        -- 2. Manual Cash Income (Exclude Invoice Payments)
        SELECT coa.account_name as label, SUM(ct.credit) as total
        FROM cash_transactions ct
        JOIN chart_of_accounts coa ON ct.chart_of_account_id = coa.id
        WHERE coa.account_type = 'Income' 
          AND ct.credit > 0 
          AND ct.description NOT LIKE 'Payment for Sales Invoice%'
          AND ct.transaction_date BETWEEN ? AND ?
        GROUP BY coa.account_name
        
        UNION ALL
        
        -- 3. Manual Bank Income (Exclude Invoice Payments)
        SELECT coa.account_name as label, SUM(pt.credit) as total
        FROM passbook_transactions pt
        JOIN chart_of_accounts coa ON pt.chart_of_account_id = coa.id
        WHERE coa.account_type = 'Income' 
          AND pt.credit > 0 
          AND pt.description NOT LIKE 'Payment for Sales Invoice%'
          AND pt.transaction_date BETWEEN ? AND ?
        GROUP BY coa.account_name
    ) AS income_sources
    GROUP BY label
    ORDER BY total_amount DESC LIMIT 10";

$stmt_ib = $conn->prepare($income_breakdown_sql);
$stmt_ib->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt_ib->execute();
$result = $stmt_ib->get_result();
$income_labels = [];
$income_values = [];
while ($row = $result->fetch_assoc()) {
    $income_labels[] = $row['label'];
    $income_values[] = (float)$row['total_amount'];
}
$stmt_ib->close();


// 4. Expense Breakdown
$category_sql = "
    SELECT coa.account_name, SUM(total) as total_amount FROM (
        SELECT p.chart_of_account_id, pp.amount as total
        FROM purchase_payments pp
        JOIN purchases p ON pp.purchase_id = p.id
        LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
        WHERE pp.payment_date BETWEEN ? AND ?
          AND (pp.payment_method != 'Check' OR c.status = 'Cleared')

        UNION ALL

        SELECT b.chart_of_account_id, bp.amount as total
        FROM bill_payments bp
        JOIN bills b ON bp.bill_id = b.id
        LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
        WHERE bp.payment_date BETWEEN ? AND ?
          AND (bp.payment_method != 'Check' OR c.status = 'Cleared')

        UNION ALL

        SELECT e.chart_of_account_id, e.amount as total 
        FROM expenses e
        LEFT JOIN checks c ON e.transaction_id = c.id AND e.payment_method = 'Check'
        WHERE e.expense_date BETWEEN ? AND ?
          AND (e.payment_method != 'Check' OR c.status = 'Cleared')
    ) AS expenses
    JOIN chart_of_accounts coa ON expenses.chart_of_account_id = coa.id
    WHERE coa.account_type = 'Expense' AND expenses.chart_of_account_id IS NOT NULL
    GROUP BY coa.account_name
    ORDER BY total_amount DESC LIMIT 10";

$stmt_cat = $conn->prepare($category_sql);
$stmt_cat->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
$stmt_cat->execute();
$result = $stmt_cat->get_result();
$category_labels = [];
$category_values = [];
while ($row = $result->fetch_assoc()) {
    $category_labels[] = $row['account_name'];
    $category_values[] = (float)$row['total_amount'];
}
$stmt_cat->close();


// 5. Upcoming Due Payables
$unpaid_purchases = $conn->query("
    SELECT p.id, s.supplier_name, p.due_date,
           p.amount - (
                SELECT COALESCE(SUM(pp.amount), 0) 
                FROM purchase_payments pp 
                LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
                WHERE pp.purchase_id = p.id
                AND (pp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
           ) as balance_due
    FROM purchases p JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status != 'Canceled' AND p.due_date IS NOT NULL
    HAVING balance_due > 0.01 
    ORDER BY p.due_date ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$unpaid_bills = $conn->query("
    SELECT b.id, bl.biller_name, b.due_date, 
           b.amount - (
                SELECT COALESCE(SUM(bp.amount), 0) 
                FROM bill_payments bp 
                LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
                WHERE bp.bill_id = b.id
                AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c.status = 'Cleared')
           ) as balance_due
    FROM bills b JOIN billers bl ON b.biller_id = bl.id
    WHERE b.status != 'Canceled' 
    HAVING balance_due > 0.01 
    ORDER BY b.due_date ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Financial Dashboard</h2>
    
    <form method="GET" action="index.php" class="flex items-center space-x-2 bg-white p-2 rounded-lg shadow-sm">
        <div class="flex items-center">
            <label for="start_date" class="text-xs font-semibold text-gray-500 mr-2">FROM</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="flex items-center">
            <label for="end_date" class="text-xs font-semibold text-gray-500 mr-2">TO</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-1.5 px-4 rounded-md">Filter</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-sm font-semibold text-gray-500">Total Cash on Hand</h3>
        <p class="text-3xl font-bold text-green-600 mt-2">₱<?php echo number_format($total_cash_on_hand, 2); ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-sm font-semibold text-gray-500">Total Bank Balance</h3>
        <p class="text-3xl font-bold text-blue-600 mt-2">₱<?php echo number_format($total_bank_balance, 2); ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-sm font-semibold text-gray-500">Accounts Payable</h3>
        <p class="text-3xl font-bold text-red-500 mt-2">₱<?php echo number_format($accounts_payable, 2); ?></p>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-sm font-semibold text-gray-500">Accounts Receivable</h3>
        <p class="text-3xl font-bold text-orange-500 mt-2">₱<?php echo number_format($accounts_receivable, 2); ?></p>
    </div>
</div>

<div class="bg-white p-6 rounded-lg shadow-md mb-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-4">Cash Flow (<?php echo date("M j", strtotime($start_date)) . " - " . date("M j, Y", strtotime($end_date)); ?>)</h3>
    <div class="relative h-80">
        <canvas id="incomeExpenseChart"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Income Sources</h3>
        <div class="relative h-80 flex-grow">
            <canvas id="incomeCategoryChart"></canvas>
        </div>
        <?php if(empty($income_values)): ?>
            <p class="text-center text-gray-400 mt-4">No income data for this period.</p>
        <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md flex flex-col">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Expense Categories</h3>
        <div class="relative h-80 flex-grow">
            <canvas id="expenseCategoryChart"></canvas>
        </div>
        <?php if(empty($category_values)): ?>
            <p class="text-center text-gray-400 mt-4">No expense data for this period.</p>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Upcoming Purchases to Pay</h3>
        <div class="overflow-x-auto">
             <table class="w-full">
                <tbody>
                    <?php if(!empty($unpaid_purchases)): foreach($unpaid_purchases as $purchase): ?>
                    <tr class="border-b">
                        <td class="py-2 text-sm">
                            <a href="view_purchase.php?id=<?php echo $purchase['id']; ?>" class="text-blue-600 hover:underline font-semibold"><?php echo htmlspecialchars($purchase['supplier_name']); ?></a>
                        </td>
                        <td class="py-2 text-sm text-right text-gray-600"><?php echo htmlspecialchars($purchase['due_date']); ?></td>
                        <td class="py-2 text-sm text-right font-bold">₱<?php echo number_format($purchase['balance_due'], 2); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td class="py-2 text-sm text-gray-500 text-center">No unpaid purchases due soon.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold text-gray-700 mb-4">Upcoming Bills to Pay</h3>
        <div class="overflow-x-auto">
             <table class="w-full">
                <tbody>
                    <?php if(!empty($unpaid_bills)): foreach($unpaid_bills as $bill): ?>
                    <tr class="border-b">
                        <td class="py-2 text-sm">
                            <a href="view_bill.php?id=<?php echo $bill['id']; ?>" class="text-blue-600 hover:underline font-semibold"><?php echo htmlspecialchars($bill['biller_name']); ?></a>
                        </td>
                        <td class="py-2 text-sm text-right text-gray-600"><?php echo htmlspecialchars($bill['due_date']); ?></td>
                        <td class="py-2 text-sm text-right font-bold">₱<?php echo number_format($bill['balance_due'], 2); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td class="py-2 text-sm text-gray-500 text-center">No unpaid bills due soon.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
    new Chart(incomeExpenseCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($month_labels); ?>,
            datasets: [
                { label: 'Income', data: <?php echo json_encode($income_chart_data); ?>, backgroundColor: 'rgba(34, 197, 94, 0.6)', borderColor: 'rgba(34, 197, 94, 1)', borderWidth: 1 },
                { label: 'Expense', data: <?php echo json_encode($expenses_chart_data); ?>, backgroundColor: 'rgba(239, 68, 68, 0.6)', borderColor: 'rgba(239, 68, 68, 1)', borderWidth: 1 }
            ]
        },
        options: { scales: { y: { beginAtZero: true } }, responsive: true, maintainAspectRatio: false }
    });

    const incomeCategoryCtx = document.getElementById('incomeCategoryChart').getContext('2d');
    new Chart(incomeCategoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($income_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($income_values); ?>,
                backgroundColor: ['#3B82F6', '#10B981', '#0EA5E9', '#6366F1', '#8B5CF6'],
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    const expenseCategoryCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    new Chart(expenseCategoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($category_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($category_values); ?>,
                backgroundColor: ['#EF4444', '#F97316', '#EAB308', '#F43F5E', '#A855F7', '#6B7280'],
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });
});
</script>

<?php require_once "includes/footer.php"; ?>