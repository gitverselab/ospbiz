<?php
// report_ap_aging.php
require_once "includes/header.php";
require_once "config/database.php";

// --- Initialize variables ---
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// --- Initialize Aging Buckets ---
$aging_data = [
    'current' => ['total' => 0, 'items' => []],
    '1-30'    => ['total' => 0, 'items' => []],
    '31-60'   => ['total' => 0, 'items' => []],
    '61-90'   => ['total' => 0, 'items' => []],
    '91+'     => ['total' => 0, 'items' => []]
];
$grand_total = 0;

// --- Helper function to process and categorize a row into an aging bucket ---
function process_aging_row(&$row, &$aging_data, &$grand_total) {
    $days_overdue = (int)$row['days_overdue'];
    $balance_due = $row['balance_due'];
    $bucket = '';

    if ($days_overdue <= 0) {
        $bucket = 'current';
    } elseif ($days_overdue <= 30) {
        $bucket = '1-30';
    } elseif ($days_overdue <= 60) {
        $bucket = '31-60';
    } elseif ($days_overdue <= 90) {
        $bucket = '61-90';
    } else {
        $bucket = '91+';
    }

    $aging_data[$bucket]['items'][] = $row;
    $aging_data[$bucket]['total'] += $balance_due;
    $grand_total += $balance_due;
}

// --- Fetch and Process Unpaid Purchases using Prepared Statements ---
$purchases_sql = "
    SELECT 
        p.id, p.due_date, s.supplier_name as name,
        p.amount - (SELECT COALESCE(SUM(amount), 0) FROM purchase_payments WHERE purchase_id = p.id) as balance_due,
        DATEDIFF(?, p.due_date) as days_overdue
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.status IN ('Unpaid', 'Partially Paid') AND p.purchase_date <= ?
    HAVING balance_due > 0
";

$stmt_purchases = $conn->prepare($purchases_sql);
if ($stmt_purchases) {
    $stmt_purchases->bind_param("ss", $as_of_date, $as_of_date);
    $stmt_purchases->execute();
    $result_purchases = $stmt_purchases->get_result();
    while ($row = $result_purchases->fetch_assoc()) {
        $row['type'] = 'Purchase';
        process_aging_row($row, $aging_data, $grand_total);
    }
    $stmt_purchases->close();
}

// --- Fetch and Process Unpaid Bills using Prepared Statements ---
$bills_sql = "
    SELECT 
        b.id, b.due_date, bl.biller_name as name,
        b.amount - (SELECT COALESCE(SUM(amount), 0) FROM bill_payments WHERE bill_id = b.id) as balance_due,
        DATEDIFF(?, b.due_date) as days_overdue
    FROM bills b
    JOIN billers bl ON b.biller_id = bl.id
    WHERE b.status IN ('Unpaid', 'Partially Paid') AND b.bill_date <= ?
    HAVING balance_due > 0
";

$stmt_bills = $conn->prepare($bills_sql);
if ($stmt_bills) {
    $stmt_bills->bind_param("ss", $as_of_date, $as_of_date);
    $stmt_bills->execute();
    $result_bills = $stmt_bills->get_result();
    while ($row = $result_bills->fetch_assoc()) {
        $row['type'] = 'Bill';
        process_aging_row($row, $aging_data, $grand_total);
    }
    $stmt_bills->close();
}

$conn->close();
?>

<!-- NOTE: Make sure your header.php links to 'print.css' like this: -->
<!-- <link rel="stylesheet" href="public/css/print.css" media="print"> -->

<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">A/P Aging Report</h2>
    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print Report</button>
</div>

<!-- Date filter form -->
<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="report_ap_aging.php" class="flex items-center space-x-4">
        <div>
            <label for="as_of_date" class="text-sm font-medium">As of Date:</label>
            <input type="date" name="as_of_date" id="as_of_date" value="<?php echo htmlspecialchars($as_of_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Generate Report</button>
    </form>
</div>


<div id="printable-area">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="text-center mb-6">
            <h3 class="text-2xl font-bold">Onyang's Food Inc.</h3>
            <p class="text-lg">Accounts Payable Aging Summary</p>
            <p class="text-gray-600">As of <?php echo date("F j, Y", strtotime($as_of_date)); ?></p>
        </div>

        <table class="w-full mb-6">
            <thead class="bg-gray-100">
                <tr class="border-b-2">
                    <th class="p-2 text-right">Current</th>
                    <th class="p-2 text-right">1-30 Days</th>
                    <th class="p-2 text-right">31-60 Days</th>
                    <th class="p-2 text-right">61-90 Days</th>
                    <th class="p-2 text-right">91+ Days</th>
                    <th class="p-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="font-semibold">
                <tr>
                    <td class="p-2 text-right">₱<?php echo number_format($aging_data['current']['total'], 2); ?></td>
                    <td class="p-2 text-right">₱<?php echo number_format($aging_data['1-30']['total'], 2); ?></td>
                    <td class="p-2 text-right">₱<?php echo number_format($aging_data['31-60']['total'], 2); ?></td>
                    <td class="p-2 text-right">₱<?php echo number_format($aging_data['61-90']['total'], 2); ?></td>
                    <td class="p-2 text-right">₱<?php echo number_format($aging_data['91+']['total'], 2); ?></td>
                    <td class="p-2 text-right text-blue-600">₱<?php echo number_format($grand_total, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <h3 class="text-xl font-semibold mt-8 mb-4 border-t pt-4">Details</h3>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 text-left">Payable To</th>
                    <th class="p-2 text-left">Due Date</th>
                    <th class="p-2 text-right">Days Overdue</th>
                    <th class="p-2 text-right">Balance Due</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Function to display items in each bucket
                function render_items($items) {
                    foreach($items as $item) {
                        $link = ($item['type'] == 'Purchase') ? "view_purchase.php?id={$item['id']}" : "view_bill.php?id={$item['id']}";
                        echo "<tr class='border-b'><td class='p-2'><a href='{$link}' class='text-blue-500 hover:underline'>".htmlspecialchars($item['name'])."</a></td><td class='p-2'>{$item['due_date']}</td><td class='p-2 text-right'>".($item['days_overdue'] > 0 ? $item['days_overdue'] : 0)."</td><td class='p-2 text-right font-semibold'>₱".number_format($item['balance_due'], 2)."</td></tr>";
                    }
                }

                if($grand_total > 0):
                    if(!empty($aging_data['91+']['items'])) { echo "<tr><td colspan='4' class='p-2 font-bold bg-red-100'>Over 90 Days</td></tr>"; render_items($aging_data['91+']['items']); }
                    if(!empty($aging_data['61-90']['items'])) { echo "<tr><td colspan='4' class='p-2 font-bold bg-orange-100'>61-90 Days</td></tr>"; render_items($aging_data['61-90']['items']); }
                    if(!empty($aging_data['31-60']['items'])) { echo "<tr><td colspan='4' class='p-2 font-bold bg-yellow-100'>31-60 Days</td></tr>"; render_items($aging_data['31-60']['items']); }
                    if(!empty($aging_data['1-30']['items'])) { echo "<tr><td colspan='4' class='p-2 font-bold bg-gray-100'>1-30 Days</td></tr>"; render_items($aging_data['1-30']['items']); }
                    if(!empty($aging_data['current']['items'])) { echo "<tr><td colspan='4' class='p-2 font-bold bg-green-100'>Current</td></tr>"; render_items($aging_data['current']['items']); }
                else
                    echo "<tr><td colspan='4' class='text-center p-4 text-gray-500'>No outstanding payables.</td></tr>";
                endif;
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

