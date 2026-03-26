<?php
// view_sale.php
require_once "includes/header.php";
require_once "config/database.php";

$sale_id = $_GET['id'] ?? null;
if (!$sale_id) { header("Location: sales.php"); exit; }

// Fetch main sale details from API
$api_url = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/api/get_sale_details.php?id=" . $sale_id;
$data = json_decode(@file_get_contents($api_url), true);
if (!$data || !$data['success']) { die("Error: Could not fetch sale details. Details: " . ($data['message'] ?? 'Unknown Error')); }

$sale = $data['sale'];
$delivery_receipts = $sale['delivery_receipts'] ?? [];
$return_receipts = $sale['return_receipts'] ?? [];
$sale_payments = $sale['payments'] ?? [];

// ==========================================
// PRECISE FINANCIAL CALCULATIONS (Backwards from DB)
// ==========================================
$total_amount = (float)$sale['total_amount']; 

// Back-calculate to ensure visual perfection matching the database
$vatable_sales = $total_amount / 1.12;
$vat_amount = $total_amount - $vatable_sales;

$wht = (float)$sale['withholding_tax'];
$net_receivable = $total_amount - $wht;

$total_paid = array_sum(array_column($sale_payments, 'amount'));
$balance_due = $net_receivable - $total_paid;

// Determine Status Color
$status = $sale['status'];
$status_color = 'bg-gray-100 text-gray-800';
if ($status === 'Paid' || $balance_due <= 0.01) { $status = 'Paid'; $status_color = 'bg-green-100 text-green-800 border-green-200'; }
elseif ($status === 'Partial' || $total_paid > 0) { $status = 'Partially Paid'; $status_color = 'bg-yellow-100 text-yellow-800 border-yellow-200'; }
elseif ($status === 'Void') { $status_color = 'bg-red-100 text-red-800 border-red-200'; }
else { $status_color = 'bg-blue-100 text-blue-800 border-blue-200'; }

// Fetch Dropdowns
$cash_accounts = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Invoice #<?php echo htmlspecialchars($sale['invoice_number']); ?></h2>
    <div class="flex gap-2">
        <span class="px-3 py-1 rounded-full border <?php echo $status_color; ?> font-bold text-sm"><?php echo $status; ?></span>
        <a href="sales.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Back</a>
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Print</button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
        <h3 class="text-sm font-bold text-gray-500 uppercase">Customer Details</h3>
        <p class="text-xl font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($sale['customer_name']); ?></p>
        <p class="text-sm text-gray-600 mt-2"><strong>Invoice Date:</strong> <?php echo date("M d, Y", strtotime($sale['invoice_date'])); ?></p>
        <p class="text-sm text-gray-600"><strong>Due Date:</strong> <?php echo date("M d, Y", strtotime($sale['payment_due_date'])); ?></p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
        <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Financial Summary</h3>
        <div class="flex justify-between text-sm text-gray-600"><span>Vatable Sales:</span> <span>₱<?php echo number_format($vatable_sales, 2); ?></span></div>
        <div class="flex justify-between text-sm text-gray-600"><span>VAT (12%):</span> <span>₱<?php echo number_format($vat_amount, 2); ?></span></div>
        <div class="flex justify-between text-sm font-bold text-gray-800 mt-1 border-t pt-1"><span>Total Invoice Amount:</span> <span>₱<?php echo number_format($total_amount, 2); ?></span></div>
        
        <?php if($wht > 0): ?>
        <div class="flex justify-between text-sm text-red-600 mt-1"><span>Less Withholding Tax:</span> <span>-₱<?php echo number_format($wht, 2); ?></span></div>
        <?php endif; ?>
        
        <div class="flex justify-between text-sm font-bold text-blue-800 border-t pt-1 mt-1"><span>Net Receivable:</span> <span>₱<?php echo number_format($net_receivable, 2); ?></span></div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow border-l-4 <?php echo ($balance_due > 0.01) ? 'border-red-500' : 'border-gray-500'; ?>">
        <h3 class="text-sm font-bold text-gray-500 uppercase mb-2">Payment Status</h3>
        <div class="flex justify-between text-sm text-green-600 mb-1"><span>Paid to Date:</span> <span>₱<?php echo number_format($total_paid, 2); ?></span></div>
        <div class="flex justify-between text-lg font-bold <?php echo ($balance_due > 0.01) ? 'text-red-600' : 'text-green-600'; ?>">
            <span>Balance Due:</span> <span>₱<?php echo number_format(max(0, $balance_due), 2); ?></span>
        </div>
        
        <?php if ($balance_due > 0.01 && $sale['status'] !== 'Void'): ?>
        <form id="paymentForm" class="mt-4 border-t pt-4">
            <input type="hidden" name="sale_id" value="<?php echo $sale_id; ?>">
            <div class="space-y-2">
                <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2 text-sm" required>
                <input type="number" step="0.01" name="amount" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" class="w-full border rounded p-2 text-sm font-bold" required>
                <select name="payment_method" id="payment_method" class="w-full border rounded p-2 text-sm" onchange="toggleAccountSelect()">
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Cash on Hand">Cash on Hand</option>
                </select>
                <select name="account_id" id="account_id" class="w-full border rounded p-2 text-sm" required></select>
                <input type="text" name="notes" placeholder="Notes / Ref #" class="w-full border rounded p-2 text-sm">
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-bold text-sm">Record Payment</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Delivery Receipts</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-2">DR Number</th>
                        <th class="p-2">Description</th>
                        <th class="p-2 text-right">Amount (Inc. VAT)</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if(empty($delivery_receipts)): ?><tr><td colspan="3" class="p-4 text-center text-gray-500">No DRs linked.</td></tr><?php endif; ?>
                    <?php foreach($delivery_receipts as $dr): 
                        $dr_num = $dr['dr_number'] ?? $dr['id'] ?? 'N/A';
                        $dr_desc = $dr['description'] ?? 'Delivery Receipt';
                        $dr_amt = $dr['vat_inclusive_amount'] ?? $dr['total_amount'] ?? $dr['total_value'] ?? $dr['amount'] ?? 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-2 font-mono"><?php echo htmlspecialchars($dr_num); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($dr_desc); ?></td>
                        <td class="p-2 text-right font-bold">₱<?php echo number_format($dr_amt, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold text-red-700 mb-4">Return Receipts (RTS)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-red-50 border-b border-red-100 text-red-900">
                    <tr>
                        <th class="p-2">RTS Number</th>
                        <th class="p-2">Description</th>
                        <th class="p-2 text-right">Deduction (Inc. VAT)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-red-50">
                    <?php if(empty($return_receipts)): ?><tr><td colspan="3" class="p-4 text-center text-gray-500">No RTS linked.</td></tr><?php endif; ?>
                    <?php foreach($return_receipts as $rt): 
                        $rts_num = $rt['rts_number'] ?? $rt['return_number'] ?? $rt['rr_number'] ?? $rt['dr_number'] ?? ('RTS-' . $rt['id']);
                        $rts_desc = $rt['description'] ?? 'Return Deduction';
                        $rts_amt = $rt['vat_inclusive_amount'] ?? $rt['total_amount'] ?? $rt['total_value'] ?? $rt['amount'] ?? 0;
                    ?>
                    <tr class="hover:bg-red-50/50">
                        <td class="p-2 font-mono text-red-700"><?php echo htmlspecialchars($rts_num); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($rts_desc); ?></td>
                        <td class="p-2 text-right font-bold text-red-600">- ₱<?php echo number_format($rts_amt, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="bg-white p-6 rounded-lg shadow-md mt-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Payment History</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-2">Date</th>
                    <th class="p-2">Method</th>
                    <th class="p-2">Notes</th>
                    <th class="p-2 text-right">Amount Paid</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if(empty($sale_payments)): ?><tr><td colspan="4" class="p-4 text-center text-gray-500">No payments recorded.</td></tr><?php endif; ?>
                <?php foreach($sale_payments as $payment): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                    <td class="p-2"><span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs font-bold"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                    <td class="p-2"><?php echo htmlspecialchars($payment['notes']); ?></td>
                    <td class="p-2 text-right font-bold text-green-700">₱<?php echo number_format($payment['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const cashAccounts = <?php echo json_encode($cash_accounts); ?>;
    const passbooks = <?php echo json_encode($passbooks); ?>;

    function toggleAccountSelect() {
        const method = document.getElementById('payment_method').value;
        const accountSelect = document.getElementById('account_id');
        accountSelect.innerHTML = ''; 

        if (method === 'Cash on Hand') {
            cashAccounts.forEach(acc => accountSelect.add(new Option(acc.account_name, acc.id)));
        } else if (method === 'Bank Transfer') {
            passbooks.forEach(pb => accountSelect.add(new Option(`${pb.bank_name} (${pb.account_number})`, pb.id)));
        }
    }
    toggleAccountSelect(); 

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        btn.disabled = true; btn.innerText = "Processing...";

        fetch('api/record_sale_payment.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if(data.success) { location.reload(); } 
            else { alert("Error: " + (data.message || "Could not record payment.")); btn.disabled = false; btn.innerText = originalText; }
        }).catch(err => { alert("Network Error"); btn.disabled = false; btn.innerText = originalText; });
    });
</script>

<?php require_once "includes/footer.php"; ?>