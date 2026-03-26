<?php
// view_purchase.php
require_once "includes/header.php";
require_once "config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: purchases.php");
    exit;
}
$purchase_id = (int)$_GET['id'];

// 1. Fetch Details Direct
$purchase_sql = "SELECT p.*, s.supplier_name, coa.account_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id LEFT JOIN chart_of_accounts coa ON p.chart_of_account_id = coa.id WHERE p.id = ?";
$stmt_p = $conn->prepare($purchase_sql);
$stmt_p->bind_param("i", $purchase_id);
$stmt_p->execute();
$purchase = $stmt_p->get_result()->fetch_assoc();
$stmt_p->close();

if (!$purchase) { echo "<div class='p-6 text-red-600'>Error: Purchase not found.</div>"; require_once "includes/footer.php"; exit; }

// 2. Fetch Items
$items_sql = "SELECT pi.item_id, pi.quantity, pi.unit_price, i.item_name FROM purchase_items pi JOIN items i ON pi.item_id = i.id WHERE pi.purchase_id = ?";
$stmt_i = $conn->prepare($items_sql);
$stmt_i->bind_param("i", $purchase_id);
$stmt_i->execute();
$purchase_items = $stmt_i->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_i->close();

// 3. Fetch Payments (Include Payee and Check Details for Editing)
$payments_sql = "SELECT pp.*, c.check_number, c.check_date, c.payee, c.status as check_status FROM purchase_payments pp LEFT JOIN checks c ON pp.reference_id = c.id AND pp.payment_method = 'Check' WHERE pp.purchase_id = ? ORDER BY pp.payment_date DESC";
$stmt_pay = $conn->prepare($payments_sql);
$stmt_pay->bind_param("i", $purchase_id);
$stmt_pay->execute();
$purchase_payments = $stmt_pay->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_pay->close();

// 4. Dropdowns
$cash_accounts = []; if($res = $conn->query("SELECT id, account_name FROM cash_accounts ORDER BY account_name")) { while($r=$res->fetch_assoc()) $cash_accounts[]=$r; }
$passbooks = []; if($res = $conn->query("SELECT id, bank_name, account_number FROM passbooks ORDER BY bank_name")) { while($r=$res->fetch_assoc()) $passbooks[]=$r; }
$conn->close();

// 5. Calculate Cleared Balance
$total_paid_cleared = 0;
foreach ($purchase_payments as $p) {
    if ($p['payment_method'] === 'Check') {
        if (isset($p['check_status']) && $p['check_status'] === 'Cleared') { $total_paid_cleared += $p['amount']; }
    } else { $total_paid_cleared += $p['amount']; }
}
$balance_due = $purchase['amount'] - $total_paid_cleared;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">PO Number: <?php echo htmlspecialchars($purchase['po_number']); ?></h2>
                    <p class="text-gray-600">Supplier: <span class="font-semibold"><?php echo htmlspecialchars($purchase['supplier_name']); ?></span></p>
                </div>
                <div class="text-right">
                     <p class="font-semibold">Status: <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo ($purchase['status'] == 'Paid') ? 'bg-green-200 text-green-800' : 'bg-yellow-200 text-yellow-800'; ?>"><?php echo htmlspecialchars($purchase['status']); ?></span></p>
                </div>
            </div>
            
            <table class="w-full mb-6">
                <thead class="bg-gray-50"><tr><th class="p-2 text-left">Item</th><th class="p-2 text-right">Qty</th><th class="p-2 text-right">Unit Price</th><th class="p-2 text-right">Total</th></tr></thead>
                <tbody>
                    <?php if (!empty($purchase_items)): foreach($purchase_items as $item): ?>
                    <tr class="border-b">
                        <td class="p-2"><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td class="p-2 text-right"><?php echo htmlspecialchars(number_format($item['quantity'], 2)); ?></td>
                        <td class="p-2 text-right">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="p-2 text-right font-semibold">₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="p-3 text-center text-gray-500">No items listed for this purchase.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="flex justify-end mt-4">
                <div class="text-right">
                    <p><strong>Total Amount:</strong> ₱<?php echo number_format($purchase['amount'], 2); ?></p>
                    <p class="text-green-600"><strong>Paid (Cleared):</strong> ₱<?php echo number_format($total_paid_cleared, 2); ?></p>
                    <p class="text-xl font-bold text-orange-600"><strong>Balance Due:</strong> ₱<?php echo number_format($balance_due, 2); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Payment History</h3>
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="p-2">Date</th><th class="p-2">Method</th><th class="p-2 text-right">Amount</th><th class="p-2 text-center">Status</th><th class="p-2 text-center">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($purchase_payments)): ?><tr><td colspan="5" class="p-3 text-center text-gray-500">No payments recorded.</td></tr><?php else: foreach ($purchase_payments as $payment): ?>
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
                                <span class="text-gray-400 text-xs italic" title="Reconciled payments cannot be edited">Locked</span>
                            <?php else: ?>
                                <button onclick='openEditModal(<?php echo json_encode($payment); ?>)' class="text-blue-500 hover:underline text-xs">Edit</button>
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
                <input type="hidden" name="purchase_id" value="<?php echo $purchase_id; ?>">
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
                        <div><label>Payee (Optional)</label><input type="text" name="payee" placeholder="Leave blank for Supplier Name" class="mt-1 block w-full rounded-md border-gray-300"></div>
                    </div>
                    <div><label class="font-semibold">Notes</label><textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300"></textarea></div>
                </div>
                <div class="mt-6"><button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg font-bold">Record Payment</button></div>
            </form>
        </div>
        <?php endif; ?>
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

function openEditModal(payment) {
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

    fetch('api/record_purchase_payment.php', { method: 'POST', body: formData })
    .then(res => res.json()).then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});

document.getElementById('editPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    fetch('api/update_purchase_payment.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json()).then(data => {
        if(data.success) location.reload();
        else alert('Error: ' + data.message);
    });
});

function deletePayment(id) {
    if (confirm('Are you sure you want to delete this payment?')) {
        const fd = new FormData(); fd.append('id', id);
        fetch('api/delete_purchase_payment.php', { method: 'POST', body: fd })
        .then(res => res.json()).then(data => {
            if(data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    }
}
</script>

<?php require_once "includes/footer.php"; ?>