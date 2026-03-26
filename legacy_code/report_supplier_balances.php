<?php
// report_supplier_balances.php
require_once "includes/header.php";
require_once "config/database.php";

// --- Logic Switcher ---
// If an ID is present, show the DETAILED view. Otherwise, show the SUMMARY view.
$view_supplier_id = $_GET['supplier_id'] ?? null;
$view_biller_id = $_GET['biller_id'] ?? null;

// =================================================================================
// VIEW 1: SPECIFIC ENTITY DETAILS (Transactions for one Supplier/Biller)
// =================================================================================
if ($view_supplier_id || $view_biller_id) {
    $is_supplier = !empty($view_supplier_id);
    $id = $is_supplier ? $view_supplier_id : $view_biller_id;
    $type_label = $is_supplier ? "Supplier" : "Biller";
    
    // 1. Get Entity Name
    if ($is_supplier) {
        $stmt = $conn->prepare("SELECT supplier_name as name FROM suppliers WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT biller_name as name FROM billers WHERE id = ?");
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $entity = $stmt->get_result()->fetch_assoc();
    $entity_name = $entity['name'] ?? 'Unknown';

    // 2. Get Transactions (Purchases or Bills)
    if ($is_supplier) {
        $sql = "
            SELECT 
                p.id, 
                p.po_number as ref_number, 
                p.purchase_date as date, 
                p.due_date, 
                p.amount as total_amount, 
                p.status,
                (p.amount - (
                    SELECT COALESCE(SUM(pp.amount), 0) 
                    FROM purchase_payments pp 
                    LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
                    WHERE pp.purchase_id = p.id 
                    AND (pp.payment_method != 'Check' OR c.status = 'Cleared')
                )) as balance_due
            FROM purchases p 
            WHERE p.supplier_id = ? AND p.status != 'Canceled'
            ORDER BY p.purchase_date DESC
        ";
    } else {
        $sql = "
            SELECT 
                b.id, 
                b.bill_number as ref_number, 
                b.bill_date as date, 
                b.due_date, 
                b.amount as total_amount, 
                b.status,
                (b.amount - (
                    SELECT COALESCE(SUM(bp.amount), 0) 
                    FROM bill_payments bp 
                    LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
                    WHERE bp.bill_id = b.id 
                    AND (bp.payment_method != 'Check' OR c.status = 'Cleared')
                )) as balance_due
            FROM bills b 
            WHERE b.biller_id = ? AND b.status != 'Canceled'
            ORDER BY b.bill_date DESC
        ";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Calculate Summary for Header
    $total_balance = 0;
    foreach($transactions as $t) $total_balance += $t['balance_due'];
?>

    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($entity_name); ?></h2>
            <p class="text-gray-500 text-sm">Account Type: <?php echo $type_label; ?></p>
        </div>
        <a href="report_supplier_balances.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Back to Summary</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
            <p class="text-gray-500 text-sm font-bold uppercase">Total Transactions</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo count($transactions); ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
            <p class="text-gray-500 text-sm font-bold uppercase">Total Balance Due</p>
            <p class="text-3xl font-bold text-red-600">₱<?php echo number_format($total_balance, 2); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-bold mb-4 text-gray-700">Transaction History</h3>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 uppercase text-xs text-gray-600">
                <tr>
                    <th class="p-3">Date</th>
                    <th class="p-3">Ref #</th>
                    <th class="p-3">Due Date</th>
                    <th class="p-3 text-center">Status</th>
                    <th class="p-3 text-right">Total Amount</th>
                    <th class="p-3 text-right">Balance Due</th>
                    <th class="p-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach($transactions as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3"><?php echo $row['date']; ?></td>
                    <td class="p-3 font-bold text-blue-600"><?php echo htmlspecialchars($row['ref_number']); ?></td>
                    <td class="p-3 <?php echo ($row['balance_due'] > 0 && $row['due_date'] < date('Y-m-d')) ? 'text-red-600 font-bold' : 'text-gray-600'; ?>">
                        <?php echo $row['due_date']; ?>
                        <?php if($row['balance_due'] > 0 && $row['due_date'] < date('Y-m-d')) echo " (Overdue)"; ?>
                    </td>
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php 
                            if($row['status'] == 'Paid') echo 'bg-green-100 text-green-800';
                            elseif($row['status'] == 'Partially Paid') echo 'bg-yellow-100 text-yellow-800';
                            else echo 'bg-red-100 text-red-800';
                        ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td class="p-3 text-right">₱<?php echo number_format($row['total_amount'], 2); ?></td>
                    <td class="p-3 text-right font-bold <?php echo ($row['balance_due'] > 0) ? 'text-red-600' : 'text-green-600'; ?>">
                        ₱<?php echo number_format($row['balance_due'], 2); ?>
                    </td>
                    <td class="p-3 text-center">
                        <a href="<?php echo $is_supplier ? 'view_purchase.php' : 'view_bill.php'; ?>?id=<?php echo $row['id']; ?>" 
                           target="_blank" class="text-blue-500 hover:underline text-xs font-bold">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php
} 
// =================================================================================
// VIEW 2: SUMMARY DASHBOARD (List all Suppliers/Billers with Balances)
// =================================================================================
else {
    // 1. Suppliers Summary
    // Note: We use HAVING balance_due > 1 to hide fully paid suppliers (optional, remove HAVING to show all)
    $sql_suppliers = "
        SELECT 
            s.id, 
            s.supplier_name, 
            COUNT(p.id) as tx_count, 
            SUM(p.amount) as total_purchased,
            SUM(p.amount - (
                SELECT COALESCE(SUM(pp.amount), 0) 
                FROM purchase_payments pp 
                LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check'
                WHERE pp.purchase_id = p.id 
                AND (pp.payment_method != 'Check' OR c.status = 'Cleared')
            )) as balance_due
        FROM suppliers s
        JOIN purchases p ON s.id = p.supplier_id
        WHERE p.status != 'Canceled'
        GROUP BY s.id, s.supplier_name
        HAVING balance_due > 0.01
        ORDER BY balance_due DESC
    ";
    $suppliers = $conn->query($sql_suppliers)->fetch_all(MYSQLI_ASSOC);

    // 2. Billers Summary
    $sql_billers = "
        SELECT 
            bl.id, 
            bl.biller_name, 
            COUNT(b.id) as tx_count, 
            SUM(b.amount) as total_billed,
            SUM(b.amount - (
                SELECT COALESCE(SUM(bp.amount), 0) 
                FROM bill_payments bp 
                LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
                WHERE bp.bill_id = b.id 
                AND (bp.payment_method != 'Check' OR c.status = 'Cleared')
            )) as balance_due
        FROM billers bl
        JOIN bills b ON bl.id = b.biller_id
        WHERE b.status != 'Canceled'
        GROUP BY bl.id, bl.biller_name
        HAVING balance_due > 0.01
        ORDER BY balance_due DESC
    ";
    $billers = $conn->query($sql_billers)->fetch_all(MYSQLI_ASSOC);
    
    // Calculate Grand Total
    $grand_total = 0;
    foreach($suppliers as $s) $grand_total += $s['balance_due'];
    foreach($billers as $b) $grand_total += $b['balance_due'];
?>

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-3xl font-bold text-gray-800">Supplier & Biller Balances</h2>
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded-lg font-bold border border-red-200">
            Total Payables: ₱<?php echo number_format($grand_total, 2); ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-blue-800 border-b pb-2">Payable to Suppliers</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-blue-50 text-blue-700">
                        <tr>
                            <th class="p-2">Supplier Name</th>
                            <th class="p-2 text-center">Unpaid POs</th>
                            <th class="p-2 text-right">Balance Due</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if(empty($suppliers)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">No pending payables.</td></tr>
                        <?php else: foreach($suppliers as $s): ?>
                        <tr class="hover:bg-blue-50 transition cursor-pointer" onclick="window.location='?supplier_id=<?php echo $s['id']; ?>'">
                            <td class="p-3 font-semibold"><?php echo htmlspecialchars($s['supplier_name']); ?></td>
                            <td class="p-3 text-center"><?php echo $s['tx_count']; ?></td>
                            <td class="p-3 text-right font-bold text-red-600">₱<?php echo number_format($s['balance_due'], 2); ?></td>
                            <td class="p-3 text-right">
                                <span class="text-blue-500 hover:text-blue-700 text-xs font-bold">Details &rarr;</span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-bold mb-4 text-purple-800 border-b pb-2">Payable to Billers</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-purple-50 text-purple-700">
                        <tr>
                            <th class="p-2">Biller Name</th>
                            <th class="p-2 text-center">Unpaid Bills</th>
                            <th class="p-2 text-right">Balance Due</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if(empty($billers)): ?>
                            <tr><td colspan="4" class="p-4 text-center text-gray-500">No pending payables.</td></tr>
                        <?php else: foreach($billers as $b): ?>
                        <tr class="hover:bg-purple-50 transition cursor-pointer" onclick="window.location='?biller_id=<?php echo $b['id']; ?>'">
                            <td class="p-3 font-semibold"><?php echo htmlspecialchars($b['biller_name']); ?></td>
                            <td class="p-3 text-center"><?php echo $b['tx_count']; ?></td>
                            <td class="p-3 text-right font-bold text-red-600">₱<?php echo number_format($b['balance_due'], 2); ?></td>
                            <td class="p-3 text-right">
                                <span class="text-purple-500 hover:text-purple-700 text-xs font-bold">Details &rarr;</span>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

<?php } require_once "includes/footer.php"; ?>