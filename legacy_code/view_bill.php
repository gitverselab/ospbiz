<?php
// view_bill.php
require_once "includes/header.php";
require_once "config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: bills.php");
    exit;
}
$bill_id = (int)$_GET['id'];

// 1. Fetch Bill Details
$bill_sql = "SELECT b.*, bl.biller_name, coa.account_name 
             FROM bills b 
             JOIN billers bl ON b.biller_id = bl.id 
             LEFT JOIN chart_of_accounts coa ON b.chart_of_account_id = coa.id 
             WHERE b.id = ?";
$stmt_b = $conn->prepare($bill_sql);
$stmt_b->bind_param("i", $bill_id);
$stmt_b->execute();
$bill = $stmt_b->get_result()->fetch_assoc();
$stmt_b->close();

if (!$bill) {
    echo "<div class='p-6 text-red-600'>Error: Bill not found.</div>";
    require_once "includes/footer.php";
    exit;
}

// 2. Fetch Payments
$payments_sql = "
    SELECT bp.*, c.check_number, c.check_date, c.payee, c.status as check_status
    FROM bill_payments bp
    LEFT JOIN checks c ON bp.reference_id = c.id AND bp.payment_method = 'Check'
    WHERE bp.bill_id = ? 
    ORDER BY bp.payment_date DESC";
$stmt_pay = $conn->prepare($payments_sql);
$stmt_pay->bind_param("i", $bill_id);
$stmt_pay->execute();
$bill_payments = $stmt_pay->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pay->close();

// 3. Dropdowns
$cash_accounts = [];
if($res = $conn->query("SELECT id, account_name FROM cash_accounts ORDER BY account_name")) { while($r=$res->fetch_assoc()) $cash_accounts[]=$r; }
$passbooks = [];
if($res = $conn->query("SELECT id, bank_name, account_number FROM passbooks ORDER BY bank_name")) { while($r=$res->fetch_assoc()) $passbooks[]=$r; }
$billers = $conn->query("SELECT id, biller_name FROM billers ORDER BY biller_name")->fetch_all(MYSQLI_ASSOC);
$expense_accounts = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();

// 4. Calculate Cleared Balance
$total_paid_cleared = 0;
foreach ($bill_payments as $p) {
    if ($p['payment_method'] === 'Check') {
        if (isset($p['check_status']) && $p['check_status'] === 'Cleared') {
            $total_paid_cleared += $p['amount'];
        }
    } else {
        $total_paid_cleared += $p['amount'];
    }
}
$balance_due = $bill['amount'] - $total_paid_cleared;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Bill #<?php echo htmlspecialchars($bill['bill_number']); ?></h2>
                    <p class="text-sm text-gray-500 mb-2">Internal Ref: #<?php echo $bill['id']; ?></p>
                    <p class="text-gray-600">Biller: <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($bill['biller_name']); ?></span></p>
                    <p class="text-gray-600">Category: <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($bill['account_name'] ?? 'N/A'); ?></span></p>
                    <p class="text-gray-600">Date: <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($bill['bill_date']); ?></span></p>
                </div>
                <div class="text-right space-y-2">
                    <p class="font-semibold">Status: <span class="px-2 py-1 text-xs font-semibold rounded-full <?php 
                            if ($bill['status'] == 'Paid') echo 'bg-green-200 text-green-800';
                            elseif ($bill['status'] == 'Partially Paid') echo 'bg-blue-200 text-blue-800';
                            else echo 'bg-yellow-200 text-yellow-800';
                        ?>"><?php echo htmlspecialchars($bill['status']); ?></span></p>
                    <p>Due: <?php echo htmlspecialchars($bill['due_date']); ?></p>
                    <button onclick='openEditBillModal(<?php echo json_encode($bill); ?>)' class="text-sm bg-blue-100 text-blue-600 hover:bg-blue-200 px-3 py-1 rounded-md font-semibold">
                        Edit Bill Details
                    </button>
                </div>
            </div>
            
            <div class="flex justify-end mt-4 border-t pt-4">
                <div class="w-full max-w-xs text-right space-y-1">
                    <p><strong>Total Amount:</strong> ₱<?php echo number_format($bill['amount'], 2); ?></p>
                    <p class="text-green-600"><strong>Paid (Cleared):</strong> ₱<?php echo number_format($total_paid_cleared, 2); ?></p>
                    <p class="text-xl font-bold text-orange-600 border-t pt-1 mt-1"><strong>Balance Due:</strong> ₱<?php echo number_format($balance_due, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Payment History</h3>
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="p-2 text-left">Date</th><th class="p-2 text-left">Method</th><th class="p-2 text-right">Amount</th><th class="p-2 text-center">Status</th><th class="p-2 text-center">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($bill_payments)): ?>
                        <tr><td colspan="5" class="p-3 text-center text-gray-500">No payments recorded.</td></tr>
                    <?php else: foreach ($bill_payments as $payment): ?>
                    <tr class="border-b">
                        <td class="p-2"><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                        <td class="p-2">
                            <?php echo htmlspecialchars($payment['payment_method']); ?>
                            <?php if($payment['payment_method'] == 'Check') echo "<br><span class='text-xs text-gray-500'>#{$payment['check_number']}</span><br><span class='text-xs text-gray-500'>To: " . htmlspecialchars($payment['payee']) . "</span>"; ?>
                        </td>
                        <td class="p-2 text-right">₱<?php echo number_format($payment['amount'], 2); ?></td>
                        
                        <td class="p-2 text-center">
                            <?php if($payment['payment_method'] == 'Check'): ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo ($payment['check_status'] == 'Cleared') ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                                    <?php echo htmlspecialchars($payment['check_status'] ?? 'Unknown'); ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Completed</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="p-2 text-center space-x-1">
                            <?php if ($payment['payment_method'] === 'Check' && $payment['check_status'] === 'Cleared'): ?>
                                <span class="text-gray-400 text-xs italic">Locked</span>
                            <?php else: ?>
                                <button onclick='openEditPaymentModal(<?php echo json_encode($payment); ?>)' class="text-blue-500 hover:underline text-xs">Edit</button>
                                <button onclick="deletePayment(<?php echo $payment['id']; ?>)" class="text-red-500 hover:underline text-xs">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lg:col-span-1">
        <?php if ($balance_due > 0.009): ?>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Record a Payment</h3>
            <form id="paymentForm">
                <input type="hidden" name="bill_id" value="<?php echo $bill_id; ?>">
                <div class="space-y-4">
                    <div><label class="font-semibold">Payment Date</label><input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300" required></div>
                    <div><label class="font-semibold">Amount</label><input type="number" name="amount" step="0.01" max="<?php echo number_format($balance_due, 2, '.', ''); ?>" value="<?php echo number_format($balance_due, 2, '.', ''); ?>" class="mt-1 block w-full rounded-md border-gray-300" required></div>
                    <div><label class="font-semibold">Payment Method</label><select name="payment_method" id="payment_method" class="mt-1 block w-full rounded-md border-gray-300" onchange="toggleSourceSelect()">
                        <option>Cash on Hand</option><option>Bank Transfer</option><option>Check</option>
                    </select></div>
                    
                    <div id="source_cash_div"><label>From Cash Account</label><select name="source_id_cash" class="mt-1 block w-full rounded-md border-gray-300"><?php foreach($cash_accounts as $acc) { echo "<option value='{$acc['id']}'>{$acc['account_name']}</option>"; } ?></select></div>
                    <div id="source_bank_div" class="hidden"><label>From Bank Account</label><select name="source_id_bank" class="mt-1 block w-full rounded-md border-gray-300"><?php foreach($passbooks as $pb) { echo "<option value='{$pb['id']}'>{$pb['bank_name']} ({$pb['account_number']})</option>"; } ?></select></div>

                    <div id="check_details_div" class="hidden space-y-4 p-3 border rounded-md bg-gray-50">
                        <div><label>Check Number</label><input type="text" name="check_number" class="mt-1 block w-full rounded-md border-gray-300"></div>
                        <div><label>Check Date</label><input type="date" name="check_date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300"></div>
                        <div><label>Payee (Optional)</label><input type="text" name="payee" placeholder="Leave blank for Biller Name" class="mt-1 block w-full rounded-md border-gray-300"></div>
                    </div>

                    <div><label class="font-semibold">Notes</label><textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300"></textarea></div>
                </div>
                <div class="mt-6"><button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-bold">Record Payment</button></div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="editBillModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="editBillForm">
            <h3 class="text-xl font-bold mb-4">Edit Bill Details</h3>
            <input type="hidden" name="id" id="edit_bill_id">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label>Bill Date</label><input type="date" name="bill_date" id="edit_bill_date" class="mt-1 block w-full rounded-md" required></div>
                    <div><label>Due Date</label><input type="date" name="due_date" id="edit_due_date" class="mt-1 block w-full rounded-md" required></div>
                </div>
                 <div><label>Biller</label>
                    <select name="biller_id" id="edit_biller_id" class="mt-1 block w-full rounded-md" required>
                        <option value="">Select Biller...</option>
                        <?php foreach($billers as $biller): ?>
                            <option value="<?php echo $biller['id']; ?>"><?php echo htmlspecialchars($biller['biller_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Bill / Invoice #</label><input type="text" name="bill_number" id="edit_bill_number" class="mt-1 block w-full rounded-md" required></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label>Amount</label><input type="number" name="amount" id="edit_bill_amount" step="0.01" class="mt-1 block w-full rounded-md" required></div>
                    <div><label>Category</label><select name="chart_of_account_id" id="edit_chart_of_account_id" class="mt-1 block w-full rounded-md" required>
                         <option value="">Select Category</option>
                        <?php foreach($expense_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                </div>
                <div><label>Description</label><textarea name="description" id="edit_description" rows="2" class="mt-1 block w-full rounded-md"></textarea></div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('editBillModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Update Bill</button>
            </div>
        </form>
    </div>
</div>

<div id="editPaymentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="editPaymentForm">
            <h3 class="text-xl font-bold mb-4">Edit Payment</h3>
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="payment_method" id="edit_method_hidden">
            
            <div class="space-y-4">
                <div><label>Date</label><input type="date" name="payment_date" id="edit_date" class="mt-1 block w-full rounded-md border-gray-300" required></div>
                <div><label>Amount</label><input type="number" name="amount" id="edit_amount" step="0.01" class="mt-1 block w-full rounded-md border-gray-300" required></div>
                
                <div id="edit_check_fields" class="hidden space-y-4">
                    <div><label>Check Number</label><input type="text" name="check_number" id="edit_check_number" class="mt-1 block w-full rounded-md border-gray-300"></div>
                    <div><label>Check Date</label><input type="date" name="check_date" id="edit_check_date" class="mt-1 block w-full rounded-md border-gray-300"></div>
                    <div><label>Payee</label><input type="text" name="payee" id="edit_payee" class="mt-1 block w-full rounded-md border-gray-300"></div>
                </div>
                
                <div><label>Notes</label><textarea name="notes" id="edit_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300"></textarea></div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('editPaymentModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSourceSelect() {
    const method = document.getElementById('payment_method').value;
    const cashDiv = document.getElementById('source_cash_div');
    const bankDiv = document.getElementById('source_bank_div');
    const checkDiv = document.getElementById('check_details_div');
    cashDiv.style.display = 'none'; bankDiv.style.display = 'none'; checkDiv.style.display = 'none';
    if (method === 'Cash on Hand') cashDiv.style.display = 'block';
    else if (method === 'Bank Transfer') bankDiv.style.display = 'block';
    else if (method === 'Check') { bankDiv.style.display = 'block'; checkDiv.style.display = 'block'; }
}
toggleSourceSelect();

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }

// EDIT BILL LOGIC
function openEditBillModal(bill) {
    document.getElementById('edit_bill_id').value = bill.id;
    document.getElementById('edit_bill_date').value = bill.bill_date;
    document.getElementById('edit_due_date').value = bill.due_date;
    document.getElementById('edit_biller_id').value = bill.biller_id;
    document.getElementById('edit_bill_number').value = bill.bill_number;
    document.getElementById('edit_bill_amount').value = bill.amount;
    document.getElementById('edit_chart_of_account_id').value = bill.chart_of_account_id;
    document.getElementById('edit_description').value = bill.description;
    openModal('editBillModal');
}

document.getElementById('editBillForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/update_bill.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json()).then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});

// EDIT PAYMENT LOGIC
function openEditPaymentModal(payment) {
    document.getElementById('edit_id').value = payment.id;
    document.getElementById('edit_date').value = payment.payment_date;
    document.getElementById('edit_amount').value = payment.amount;
    document.getElementById('edit_notes').value = payment.notes;
    document.getElementById('edit_method_hidden').value = payment.payment_method;
    
    const checkFields = document.getElementById('edit_check_fields');
    if (payment.payment_method === 'Check') {
        checkFields.classList.remove('hidden');
        document.getElementById('edit_check_number').value = payment.check_number || '';
        document.getElementById('edit_check_date').value = payment.check_date || '';
        document.getElementById('edit_payee').value = payment.payee || '';
    } else {
        checkFields.classList.add('hidden');
    }
    openModal('editPaymentModal');
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const method = document.getElementById('payment_method').value;
    if (method === 'Cash on Hand') formData.append('source_id', formData.get('source_id_cash'));
    else formData.append('source_id', formData.get('source_id_bank'));
    formData.delete('source_id_cash'); formData.delete('source_id_bank');

    fetch('api/record_bill_payment.php', { method: 'POST', body: formData })
    .then(res => res.json()).then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});

document.getElementById('editPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/update_bill_payment.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json()).then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});

function deletePayment(id) {
    if (confirm('Are you sure you want to delete this payment?')) {
        const fd = new FormData(); fd.append('id', id);
        fetch('api/delete_bill_payment.php', { method: 'POST', body: fd })
        .then(res => res.json()).then(data => {
            if(data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    }
}
</script>

<?php require_once "includes/footer.php"; ?>