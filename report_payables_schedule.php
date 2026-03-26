<?php
// report_payables_schedule.php
require_once "includes/header.php";
require_once "config/database.php";

// --- Initialize variables ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$search_payee = $_GET['payee'] ?? '';

// Old Payables range
$old_start = $_GET['old_start'] ?? '';
$old_end = $_GET['old_end'] ?? '';

// Sanitize the old dates rigorously for direct SQL injection
if (!function_exists('sanitize_date')) {
    function sanitize_date($date) {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';
    }
}
$old_start_safe = sanitize_date($old_start);
$old_end_safe = sanitize_date($old_end);

$payables = [];
$search_param = "%" . $search_payee . "%";

// Conditionally build the past due SQL logic using COALESCE to ALWAYS respect check dates first
$old_condition_p = "";
$old_condition_b = "";
$old_condition_c = "";

if ($old_start_safe && $old_end_safe) {
    $old_condition_p = " OR (COALESCE(c.check_date, p.due_date) BETWEEN '$old_start_safe' AND '$old_end_safe') ";
    $old_condition_b = " OR (COALESCE(c.check_date, b.due_date) BETWEEN '$old_start_safe' AND '$old_end_safe') ";
    $old_condition_c = " OR (c.check_date BETWEEN '$old_start_safe' AND '$old_end_safe') ";
}

// --- SQL Query ---
$sql = "
    -- 1. PURCHASES
    SELECT 
        'Purchase' as type,
        p.id,
        COALESCE(MAX(c.check_date), p.due_date) as due_date,
        s.supplier_name COLLATE utf8mb4_unicode_ci as name,
        GROUP_CONCAT(DISTINCT CONCAT(i.item_name, ' (', pi.quantity, 'x)') SEPARATOR ', ') COLLATE utf8mb4_unicode_ci as details,
        
        p.amount - (
            SELECT COALESCE(SUM(pp.amount), 0) 
            FROM purchase_payments pp 
            LEFT JOIN checks c2 ON pp.reference_id = c2.id AND pp.payment_method = 'Check'
            WHERE pp.purchase_id = p.id 
            AND (pp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c2.status = 'Cleared')
        ) as balance_due,
        
        GROUP_CONCAT(DISTINCT CONCAT(c.check_date, '|', c.check_number, '|', c.amount) SEPARATOR ';') as issued_checks
    FROM purchases p
    JOIN suppliers s ON p.supplier_id = s.id
    LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
    LEFT JOIN items i ON pi.item_id = i.id
    LEFT JOIN purchase_payments pp ON p.id = pp.purchase_id AND pp.payment_method = 'Check'
    LEFT JOIN checks c ON pp.reference_id = c.id AND c.status = 'Issued'
    WHERE p.status != 'Canceled' 
      AND (
          (COALESCE(c.check_date, p.due_date) BETWEEN ? AND ?)
          $old_condition_p
      )
      AND s.supplier_name LIKE ?
    GROUP BY p.id, p.due_date, s.supplier_name, p.amount
    HAVING balance_due > 0.01

    UNION ALL

    -- 2. BILLS
    SELECT 
        'Bill' as type,
        b.id,
        COALESCE(MAX(c.check_date), b.due_date) as due_date,
        bl.biller_name COLLATE utf8mb4_unicode_ci as name,
        b.description COLLATE utf8mb4_unicode_ci as details,
        
        b.amount - (
            SELECT COALESCE(SUM(bp.amount), 0) 
            FROM bill_payments bp 
            LEFT JOIN checks c2 ON bp.reference_id = c2.id AND bp.payment_method = 'Check'
            WHERE bp.bill_id = b.id 
            AND (bp.payment_method IN ('Cash on Hand', 'Bank Transfer') OR c2.status = 'Cleared')
        ) as balance_due,
        
        GROUP_CONCAT(DISTINCT CONCAT(c.check_date, '|', c.check_number, '|', c.amount) SEPARATOR ';') as issued_checks
    FROM bills b
    JOIN billers bl ON b.biller_id = bl.id
    LEFT JOIN bill_payments bp ON b.id = bp.bill_id AND bp.payment_method = 'Check'
    LEFT JOIN checks c ON bp.reference_id = c.id AND c.status = 'Issued'
    WHERE b.status != 'Canceled' 
      AND (
          (COALESCE(c.check_date, b.due_date) BETWEEN ? AND ?)
          $old_condition_b
      )
      AND bl.biller_name LIKE ?
    GROUP BY b.id, b.due_date, bl.biller_name, b.amount, b.description
    HAVING balance_due > 0.01

    UNION ALL

    -- 3. ISSUED CHECKS (Manual/Direct & LOANS/CREDITS)
    SELECT 
        'Issued Check' as type,
        c.id,
        c.check_date as due_date,
        c.payee COLLATE utf8mb4_unicode_ci as name,
        CONCAT('Check #', c.check_number, ' (', 
            CASE 
                WHEN pp.purchase_id IS NOT NULL THEN CONCAT('Payment for PO #', p.po_number)
                WHEN bp.bill_id IS NOT NULL THEN CONCAT('Payment for Bill #', b.bill_number)
                WHEN cr.id IS NOT NULL THEN CONCAT('Payment for Credit/Loan #', IFNULL(cr.credit_ref_number, cr.id))
                ELSE 'Manual/Other Payment'
            END, 
        ')') COLLATE utf8mb4_unicode_ci as details,
        c.amount as balance_due,
        NULL as issued_checks
    FROM checks c
    LEFT JOIN purchase_payments pp ON c.id = pp.reference_id AND pp.payment_method = 'Check'
    LEFT JOIN purchases p ON pp.purchase_id = p.id
    LEFT JOIN bill_payments bp ON c.id = bp.reference_id AND bp.payment_method = 'Check'
    LEFT JOIN bills b ON bp.bill_id = b.id
    LEFT JOIN credit_payments cp ON c.id = cp.reference_id AND cp.payment_method = 'Check'
    LEFT JOIN credits cr ON cp.credit_id = cr.id
    WHERE c.status = 'Issued' 
      AND (
          (c.check_date BETWEEN ? AND ?)
          $old_condition_c
      )
      AND c.payee LIKE ?
      AND pp.id IS NULL 
      AND bp.id IS NULL 
      -- FIX: cp.id IS NULL has been removed so Credit Checks finally appear on the schedule!

    ORDER BY type ASC, due_date ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("<div style='padding:20px; background:#fee2e2; color:#b91c1c; font-weight:bold;'>Database Error: " . $conn->error . "</div>");
}

$stmt->bind_param("sssssssss", 
    $start_date, $end_date, $search_param,
    $start_date, $end_date, $search_param,
    $start_date, $end_date, $search_param
);
$stmt->execute();
$result = $stmt->get_result();
$payables = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// --- PRE-CALCULATE TOTALS ---
$total_payable = 0;
$total_issued_checks_amount = 0;

$bill_stats = [
    'total_balance' => 0,
    'check_covered' => 0,
    'unfunded' => 0
];

foreach ($payables as $item) {
    $bal = (float)$item['balance_due'];
    $total_payable += $bal;
    
    $item_check_amt = 0;

    if ($item['type'] === 'Issued Check') {
        $item_check_amt = $bal; 
    } elseif (!empty($item['issued_checks'])) {
        $checks = explode(';', $item['issued_checks']);
        foreach ($checks as $check_str) {
            $parts = explode('|', $check_str);
            if(isset($parts[2])) {
                $item_check_amt += (float)$parts[2];
            }
        }
    }
    
    $total_issued_checks_amount += $item_check_amt;

    if ($item['type'] === 'Bill') {
        $bill_stats['total_balance'] += $bal;
        $bill_stats['check_covered'] += $item_check_amt;
        $bill_stats['unfunded'] += max(0, $bal - $item_check_amt);
    }
}
?>

<style>
    @media print {
        @page { size: landscape; margin: 10mm; }
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
        .overflow-x-auto { overflow: visible !important; }
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Payables Schedule Report</h2>
    <button onclick="window.print()" class="w-full md:w-auto bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex items-center justify-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
        Print Report
    </button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="report_payables_schedule.php" class="space-y-4">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                <h4 class="text-xs font-bold text-gray-800 uppercase mb-2">Target Schedule Period</h4>
                <div class="flex gap-2">
                    <div class="w-1/2">
                        <label class="block text-[10px] font-bold text-gray-600 uppercase">From Date</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm" required>
                    </div>
                    <div class="w-1/2">
                        <label class="block text-[10px] font-bold text-gray-600 uppercase">To Date</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm" required>
                    </div>
                </div>
            </div>

            <div class="bg-red-50 p-3 rounded-lg border border-red-200">
                <h4 class="text-xs font-bold text-red-800 uppercase mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Include Old/Skipped Payables
                </h4>
                <div class="flex gap-2">
                    <div class="w-1/2">
                        <label class="block text-[10px] font-bold text-red-600 uppercase">From Date (Optional)</label>
                        <input type="date" name="old_start" value="<?php echo htmlspecialchars($old_start); ?>" class="w-full border border-red-200 rounded p-2 text-sm">
                    </div>
                    <div class="w-1/2">
                        <label class="block text-[10px] font-bold text-red-600 uppercase">To Date (Optional)</label>
                        <input type="date" name="old_end" value="<?php echo htmlspecialchars($old_end); ?>" class="w-full border border-red-200 rounded p-2 text-sm">
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div class="md:col-span-2">
                <label for="payee" class="block text-xs font-bold text-gray-700 uppercase">Payable To</label>
                <input type="text" name="payee" id="payee" value="<?php echo htmlspecialchars($search_payee); ?>" class="w-full border rounded p-2 text-sm" placeholder="Search Supplier/Biller">
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm h-[38px]">Generate Report</button>
            </div>
            <div>
                <a href="report_payables_schedule.php" class="block w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg text-sm text-center leading-[22px] h-[38px]">Reset Filters</a>
            </div>
        </div>
        
    </form>
    <p class="text-[10px] text-gray-500 mt-2">
        * "Target Schedule" filters by <strong>Check Date</strong> (if issued) or <strong>Due Date</strong> (if pending). "Old/Skipped" finds <strong>unpaid</strong> items in the past.
    </p>
</div>

<div id="printable-area">
    <div class="bg-white p-4 md:p-6 rounded-lg shadow-md print:shadow-none">
        <div class="text-center mb-6">
            <h3 class="text-xl md:text-2xl font-bold">Onyang's Food Inc.</h3>
            <p class="text-base md:text-lg">Payables Schedule</p>
            <p class="text-sm text-gray-600">
                Primary Period: <?php echo date("M d, Y", strtotime($start_date)); ?> — <?php echo date("M d, Y", strtotime($end_date)); ?>
                <?php if($old_start && $old_end): ?>
                    <br><span class="text-red-600 font-semibold">Includes Past Due: <?php echo date("M d, Y", strtotime($old_start)); ?> — <?php echo date("M d, Y", strtotime($old_end)); ?></span>
                <?php endif; ?>
                <?php if($search_payee) echo "<br>Filtered by: " . htmlspecialchars($search_payee); ?>
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-sm">
            <div class="border rounded p-3 bg-gray-50">
                <h4 class="font-bold text-gray-700 border-b pb-1 mb-2">Overall Summary</h4>
                <div class="flex justify-between mb-1">
                    <span>Total Payables (Cash Basis):</span>
                    <span class="font-bold">₱<?php echo number_format($total_payable, 2); ?></span>
                </div>
                <div class="flex justify-between mb-1 text-blue-600">
                    <span>Less: Issued Checks (Floating):</span>
                    <span>(₱<?php echo number_format($total_issued_checks_amount, 2); ?>)</span>
                </div>
                <div class="flex justify-between border-t pt-1 font-bold text-green-700">
                    <span>Net Unfunded Payable:</span>
                    <span>₱<?php echo number_format($total_payable - $total_issued_checks_amount, 2); ?></span>
                </div>
            </div>

            <div class="border rounded p-3 bg-purple-50 border-purple-100">
                <h4 class="font-bold text-purple-800 border-b border-purple-200 pb-1 mb-2">Bills Breakdown</h4>
                <div class="flex justify-between mb-1">
                    <span>Total Bills Balance:</span>
                    <span class="font-bold">₱<?php echo number_format($bill_stats['total_balance'], 2); ?></span>
                </div>
                <div class="flex justify-between mb-1 text-blue-600">
                    <span>Bills with Issued Checks:</span>
                    <span>(₱<?php echo number_format($bill_stats['check_covered'], 2); ?>)</span>
                </div>
                <div class="flex justify-between border-t border-purple-200 pt-1 font-bold text-purple-900">
                    <span>Bills without Issued Checks (Unfunded):</span>
                    <span>₱<?php echo number_format($bill_stats['unfunded'], 2); ?></span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b-2 border-gray-200">
                    <tr>
                        <th class="p-2 md:p-3 text-left whitespace-nowrap">Date</th>
                        <th class="p-2 md:p-3 text-left whitespace-nowrap">Payable To</th>
                        <th class="p-2 md:p-3 text-left min-w-[200px]">Type / Details</th>
                        <th class="p-2 md:p-3 text-right whitespace-nowrap">Balance Due</th>
                        <th class="p-2 md:p-3 text-left min-w-[200px]">Notes / Related Checks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $current_type = ''; 
                    if (!empty($payables)): foreach ($payables as $item): 
                        if ($item['type'] !== $current_type) {
                            $current_type = $item['type'];
                            $title = ($current_type == 'Issued Check') ? 'ISSUED CHECKS (MANUAL & LOAN PAYMENTS)' : strtoupper($current_type) . 'S';
                            echo "<tr><td colspan='5' class='bg-gray-200 font-bold p-2 text-gray-800 border-t-2 border-b-2 mt-4 uppercase'>" . $title . "</td></tr>";
                        }
                        
                        // Because $item['due_date'] is now safely the Check Date (if a check exists)
                        $is_past_due = ($item['due_date'] < $start_date);
                    ?>
                        <tr class="border-b hover:bg-gray-50 <?php echo $is_past_due ? 'bg-red-50/30' : ''; ?>">
                            <td class="p-2 md:p-3 align-top whitespace-nowrap">
                                <span class="font-bold <?php echo $is_past_due ? 'text-red-600' : 'text-gray-700'; ?>">
                                    <?php echo date("M d, Y", strtotime($item['due_date'])); ?>
                                </span>
                                <div class="flex items-center mt-1">
                                    <span class="text-[10px] md:text-xs text-gray-400 uppercase">
                                        <?php 
                                        if ($item['type'] == 'Issued Check') {
                                            echo 'Check Date';
                                        } elseif (!empty($item['issued_checks'])) {
                                            echo 'Eff. Check Date';
                                        } else {
                                            echo 'Due Date';
                                        }
                                        ?>
                                    </span>
                                    <?php if ($is_past_due): ?>
                                        <span class="ml-1 px-1 bg-red-100 text-red-600 text-[10px] rounded font-bold uppercase">Past Due</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="p-2 md:p-3 align-top font-semibold text-gray-800">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </td>
                            <td class="p-2 md:p-3 align-top">
                                <?php if (!empty($item['details'])): ?>
                                    <p class="text-xs text-gray-600 leading-snug"><?php echo nl2br(htmlspecialchars($item['details'])); ?></p>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-2 md:p-3 text-right align-top font-bold text-red-600 whitespace-nowrap">
                                ₱<?php echo number_format($item['balance_due'], 2); ?>
                            </td>
                            <td class="p-2 md:p-3 align-top">
                                <?php if (!empty($item['issued_checks'])): ?>
                                    <ul class="text-xs space-y-1">
                                        <?php 
                                        $checks = explode(';', $item['issued_checks']);
                                        foreach ($checks as $check_str): 
                                            list($c_date, $c_num, $c_amt) = explode('|', $check_str);
                                        ?>
                                            <li class="flex flex-wrap items-center text-blue-700 bg-blue-50 px-2 py-1 rounded">
                                                <span class="font-mono font-bold mr-2">#<?php echo htmlspecialchars($c_num); ?></span> 
                                                <span class="text-gray-500 mr-2 text-[10px]">[<?php echo date("M d", strtotime($c_date)); ?>]</span>
                                                <span class="font-semibold">₱<?php echo number_format($c_amt, 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ($item['type'] === 'Issued Check'): ?>
                                    <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2 py-1 rounded">Manual Check (Issued)</span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 italic">No issued checks</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="p-8 text-center text-gray-500">No payables found matching the criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
                
                <tfoot class="bg-gray-100 border-t-2 border-gray-300 font-bold text-gray-800">
                    <tr>
                        <td colspan="3" class="p-2 text-right text-xs md:text-sm">Total Payable Balance (Cash Basis):</td>
                        <td class="p-2 text-right text-red-700 text-sm md:text-lg whitespace-nowrap">₱<?php echo number_format($total_payable, 2); ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="p-2 text-right text-blue-700 text-xs md:text-sm">Less: Issued Checks (Uncleared):</td>
                        <td class="p-2 text-right text-blue-700 text-sm md:text-lg whitespace-nowrap">(₱<?php echo number_format($total_issued_checks_amount, 2); ?>)</td>
                        <td></td>
                    </tr>
                    <tr class="bg-yellow-50 border-t-2 border-yellow-200">
                        <td colspan="3" class="p-3 text-right uppercase text-xs md:text-sm">Net Payable (Truly Unfunded):</td>
                        <td class="p-3 text-right text-green-700 text-base md:text-xl font-extrabold whitespace-nowrap">₱<?php echo number_format($total_payable - $total_issued_checks_amount, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>