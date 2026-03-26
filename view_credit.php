<?php
// view_credit.php
require_once "includes/header.php";
require_once "config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) { header("Location: credits.php"); exit; }
$credit_id = (int)$_GET['id'];

// 1. Fetch Credit Details
$stmt = $conn->prepare("SELECT id, credit_date, creditor_name, credit_ref_number, amount, status, due_date, description FROM credits WHERE id = ?");
$stmt->bind_param("i", $credit_id);
$stmt->execute();
$credit = $stmt->get_result()->fetch_assoc();
if (!$credit) { echo "Credit not found."; exit; }

// 2. Fetch Receipts (Tranches)
$receipts = [];
$sql_receipts = "SELECT cr.*, CASE WHEN cr.account_type = 'Passbook' THEN pb.bank_name ELSE ca.account_name END as dest_name FROM credit_receipts cr LEFT JOIN passbooks pb ON cr.account_id = pb.id AND cr.account_type = 'Passbook' LEFT JOIN cash_accounts ca ON cr.account_id = ca.id AND cr.account_type = 'Cash' WHERE cr.credit_id = ? ORDER BY cr.received_date DESC";
$stmt_r = $conn->prepare($sql_receipts);
$stmt_r->bind_param("i", $credit_id);
$stmt_r->execute();
$res_r = $stmt_r->get_result();
while($row = $res_r->fetch_assoc()) { $receipts[] = $row; }
$stmt_r->close();

// 3. Fetch Payments (WITH CHECK STATUS)
$payments = [];
$sql_payments = "SELECT cp.*, c.check_number, c.status as check_status FROM credit_payments cp LEFT JOIN checks c ON cp.reference_id = c.id AND cp.payment_method = 'Check' WHERE cp.credit_id = ? ORDER BY cp.payment_date DESC";
$stmt_p = $conn->prepare($sql_payments);
$stmt_p->bind_param("i", $credit_id);
$stmt_p->execute();
$res_p = $stmt_p->get_result();
while($row = $res_p->fetch_assoc()) { $payments[] = $row; }
$stmt_p->close();

// Financial Calcs
$total_approved = (float)$credit['amount'];
$total_received = array_sum(array_column($receipts, 'amount'));
$remaining_to_receive = max(0, $total_approved - $total_received);

$total_principal_paid = 0;
$total_interest_paid = 0;
foreach ($payments as $p) {
    if ($p['payment_method'] !== 'Check' || $p['check_status'] === 'Cleared') {
        $total_principal_paid += (float)$p['principal_amount'];
        $total_interest_paid += (float)$p['interest_amount'];
    }
}
$outstanding_principal = max(0, $total_received - $total_principal_paid);

// Dropdowns for Modals
$cash_accounts = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Credit / Loan Details</h2>
    <a href="credits.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Back to List</a>
</div>

<div class="bg-white p-6 rounded-lg shadow-md border-t-4 border-blue-600 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div><p class="text-sm text-gray-500">Creditor</p><p class="text-xl font-bold"><?php echo htmlspecialchars($credit['creditor_name']); ?></p></div>
        <div><p class="text-sm text-gray-500">Reference #</p><p class="text-lg font-semibold"><?php echo htmlspecialchars($credit['credit_ref_number'] ?? 'N/A'); ?></p></div>
        <div><p class="text-sm text-gray-500">Status</p>
             <span class="px-2 py-1 rounded text-sm font-bold <?php echo ($credit['status']=='Paid')?'bg-green-100 text-green-800':(($credit['status']=='Pending')?'bg-yellow-100 text-yellow-800':'bg-blue-100 text-blue-800'); ?>">
                 <?php echo htmlspecialchars($credit['status']); ?>
             </span>
        </div>
        <div><p class="text-sm text-gray-500">Approved Amount</p><p class="text-2xl font-bold text-blue-900">₱<?php echo number_format($total_approved, 2); ?></p></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500 flex justify-between items-center">
        <div><p class="text-sm text-gray-500">Total Funds Received</p><p class="text-xl font-bold text-green-700">₱<?php echo number_format($total_received, 2); ?></p></div>
        <?php if($remaining_to_receive > 0): ?>
            <button onclick="document.getElementById('receiveModal').classList.remove('hidden')" class="bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-3 rounded shadow">+ Receive Tranche</button>
        <?php endif; ?>
    </div>
    <div class="bg-white p-4 rounded-lg shadow border-l-4 border-orange-500">
        <p class="text-sm text-gray-500">Outstanding Principal</p>
        <p class="text-xl font-bold text-orange-700">₱<?php echo number_format($outstanding_principal, 2); ?></p>
    </div>
    <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500 flex justify-between items-center">
        <div>
            <p class="text-sm text-gray-500">Total Paid (Principal + Int)</p>
            <p class="text-xl font-bold text-gray-800">₱<?php echo number_format($total_principal_paid + $total_interest_paid, 2); ?></p>
            <p class="text-xs text-gray-400">Int: ₱<?php echo number_format($total_interest_paid, 2); ?></p>
        </div>
        <?php if($outstanding_principal > 0): ?>
            <button onclick="document.getElementById('paymentModal').classList.remove('hidden')" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-2 px-3 rounded shadow">Make Payment</button>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold text-green-800 mb-4">Funds Received (Tranches)</h3>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-2">Date</th>
                    <th class="p-2">Destination</th>
                    <th class="p-2 text-right">Amount</th>
                    <th class="p-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if(empty($receipts)): ?><tr><td colspan="4" class="p-4 text-center text-gray-500">No funds received yet.</td></tr><?php endif; ?>
                <?php foreach($receipts as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2"><?php echo htmlspecialchars($r['received_date']); ?></td>
                    <td class="p-2"><span class="bg-gray-200 px-2 py-0.5 rounded text-xs"><?php echo htmlspecialchars($r['account_type']); ?></span> <?php echo htmlspecialchars($r['dest_name']); ?></td>
                    <td class="p-2 text-right font-bold text-green-700">₱<?php echo number_format($r['amount'], 2); ?></td>
                    <td class="p-2 text-center">
                        <button onclick="deleteReceipt(<?php echo $r['id']; ?>)" class="text-red-500 hover:underline text-xs font-bold">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Payment History</h3>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-2">Date</th>
                    <th class="p-2">Details</th>
                    <th class="p-2 text-right">Total Paid</th>
                    <th class="p-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if(empty($payments)): ?><tr><td colspan="4" class="p-4 text-center text-gray-500">No payments made.</td></tr><?php endif; ?>
                <?php foreach($payments as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 whitespace-nowrap"><?php echo htmlspecialchars($p['payment_date']); ?></td>
                    <td class="p-2">
                        <span class="bg-blue-100 text-blue-800 px-1 rounded text-xs"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                        <?php if($p['payment_method']=='Check'): ?>
                            <span class="text-xs <?php echo $p['check_status']=='Cleared'?'text-green-600':'text-orange-500'; ?> font-bold">(<?php echo htmlspecialchars($p['check_status']); ?>)</span>
                        <?php endif; ?>
                        <div class="text-xs text-gray-500 mt-1">Pr: ₱<?php echo number_format($p['principal_amount'],2); ?> | Int: ₱<?php echo number_format($p['interest_amount'],2); ?></div>
                    </td>
                    <td class="p-2 text-right font-bold text-red-600 whitespace-nowrap">₱<?php echo number_format($p['amount'], 2); ?></td>
                    <td class="p-2 text-center">
                        <button onclick="deletePayment(<?php echo $p['id']; ?>)" class="text-red-500 hover:underline text-xs font-bold">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="receiveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="receiveForm" action="api/receive_credit_funds.php">
            <h3 class="text-xl font-bold mb-4 text-green-800">Receive Loan Funds</h3>
            <input type="hidden" name="credit_id" value="<?php echo $credit_id; ?>">
            <div class="space-y-4">
                <div><label class="block text-sm font-bold">Date Received</label><input type="date" name="received_date" class="w-full border rounded p-2" required></div>
                <div>
                    <label class="block text-sm font-bold">Amount Receiving</label>
                    <input type="number" step="0.01" name="amount" class="w-full border rounded p-2 font-bold text-green-700" value="<?php echo $remaining_to_receive; ?>" max="<?php echo $remaining_to_receive; ?>" required>
                    <p class="text-xs text-gray-500 mt-1">Max remaining: ₱<?php echo number_format($remaining_to_receive, 2); ?></p>
                </div>
                <div><label class="block text-sm font-bold">Deposit To</label>
                    <select name="account_type" id="rcv_type" class="w-full border rounded p-2" onchange="toggleReceiveType()">
                        <option value="Passbook">Bank Account (Passbook)</option>
                        <option value="Cash">Cash on Hand</option>
                    </select>
                </div>
                <div><label class="block text-sm font-bold">Select Account</label><select name="account_id" id="rcv_account_id" class="w-full border rounded p-2" required></select></div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('receiveModal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm font-bold">Save Funds</button>
            </div>
        </form>
    </div>
</div>

<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="paymentForm" action="api/record_credit_payment.php">
            <h3 class="text-xl font-bold mb-4 text-red-800">Record Repayment</h3>
            <input type="hidden" name="credit_id" value="<?php echo $credit_id; ?>">
            <div class="space-y-4">
                <div><label class="block text-sm font-bold">Payment Date</label><input type="date" name="payment_date" class="w-full border rounded p-2" required></div>
                
                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-sm font-bold">Total Cash Paid</label>
                    <input type="number" step="0.01" name="amount" id="total_amount" class="w-full border rounded p-2 mb-2 font-bold text-red-700" onkeyup="calcInterest()" required>
                    
                    <label class="block text-sm font-bold">Principal Deduction</label>
                    <input type="number" step="0.01" name="principal_amount" id="principal_amount" class="w-full border rounded p-2" max="<?php echo $outstanding_principal; ?>" onkeyup="calcInterest()" required>
                    <p class="text-xs text-gray-500 mt-1 mb-2">Max outstanding: ₱<?php echo number_format($outstanding_principal, 2); ?></p>
                    
                    <div class="flex justify-between text-sm font-bold text-gray-700 mt-2 pt-2 border-t border-gray-300">
                        <span>Calculated Interest:</span>
                        <span>₱<span id="interest_display">0.00</span></span>
                    </div>
                </div>

                <div><label class="block text-sm font-bold">Source of Funds</label>
                    <select name="payment_method" id="pay_method" class="w-full border rounded p-2" onchange="toggleSource()">
                        <option value="Cash on Hand">Cash on Hand</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Check">Check</option>
                    </select>
                </div>
                <div><label class="block text-sm font-bold">Select Account</label><select name="source_id" id="pay_source_id" class="w-full border rounded p-2" required></select></div>
                
                <div id="check_fields" class="hidden space-y-3 p-3 bg-blue-50 rounded border border-blue-100">
                    <div><label class="block text-xs font-bold">Check Number</label><input type="text" name="check_number" class="w-full border rounded p-1"></div>
                    <div><label class="block text-xs font-bold">Check Date</label><input type="date" name="check_date" class="w-full border rounded p-1"></div>
                    <div><label class="block text-xs font-bold">Payee</label><input type="text" name="payee" value="<?php echo htmlspecialchars($credit['creditor_name']); ?>" class="w-full border rounded p-1"></div>
                </div>

                <div><label class="block text-sm font-bold">Notes</label><input type="text" name="notes" class="w-full border rounded p-2"></div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('paymentModal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded text-sm font-bold">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
    const cashAccounts = <?php echo json_encode($cash_accounts); ?>;
    const passbooks = <?php echo json_encode($passbooks); ?>;

    function populateSelect(selectId, data, isPassbook) {
        const sel = document.getElementById(selectId);
        sel.innerHTML = '';
        data.forEach(item => {
            const text = isPassbook ? `${item.bank_name} (${item.account_number})` : item.account_name;
            sel.add(new Option(text, item.id));
        });
    }

    function toggleReceiveType() {
        const type = document.getElementById('rcv_type').value;
        if(type === 'Passbook') populateSelect('rcv_account_id', passbooks, true);
        else populateSelect('rcv_account_id', cashAccounts, false);
    }

    function toggleSource() {
        const method = document.getElementById('pay_method').value;
        const chk = document.getElementById('check_fields');
        if(method === 'Cash on Hand') { populateSelect('pay_source_id', cashAccounts, false); chk.classList.add('hidden'); }
        else { populateSelect('pay_source_id', passbooks, true); if(method==='Check') chk.classList.remove('hidden'); else chk.classList.add('hidden'); }
    }

    function calcInterest() {
        const principal = parseFloat(document.getElementById('principal_amount').value) || 0;
        const total = parseFloat(document.getElementById('total_amount').value) || 0;
        const interest = total - principal;
        document.getElementById('interest_display').innerText = interest.toLocaleString(undefined, {minimumFractionDigits: 2});
        if (interest < 0) {
            document.getElementById('interest_display').classList.add('text-red-600');
            document.getElementById('interest_display').innerText = "Invalid (Principal > Total)";
        } else {
            document.getElementById('interest_display').classList.remove('text-red-600');
        }
    }

    function attachSubmitHandler(formId) {
        document.getElementById(formId).addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerText;
            btn.disabled = true; btn.innerText = "Processing...";
            fetch(this.action, { method: 'POST', body: new FormData(this) })
            .then(res => res.json()).then(data => {
                if(data.success) { location.reload(); } 
                else { alert('Error: ' + data.message); btn.disabled = false; btn.innerText = originalText; }
            }).catch(err => { alert('Network error.'); btn.disabled = false; btn.innerText = originalText; });
        });
    }

    // --- DELETION FUNCTIONS ---
    function deleteReceipt(id) {
        if(confirm("Are you sure you want to delete this funds receipt? This will deduct the money back out of your account.")) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api/delete_credit_receipt.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert("Error: " + d.message); });
        }
    }

    function deletePayment(id) {
        if(confirm("Are you sure you want to delete this repayment? This will add the money back to your account.")) {
            const fd = new FormData();
            fd.append('id', id);
            fetch('api/delete_credit_payment.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert("Error: " + d.message); });
        }
    }

    attachSubmitHandler('receiveForm'); attachSubmitHandler('paymentForm');
    toggleReceiveType(); toggleSource();
</script>

<?php require_once "includes/footer.php"; ?>