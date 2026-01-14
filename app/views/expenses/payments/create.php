<div class="bg-white p-6 rounded shadow">
    <div class="mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Select Supplier to Pay</label>
        <select onchange="window.location.href='/expenses/payments/create?supplier_id='+this.value" class="w-full md:w-1/3 border p-2 rounded">
            <option value="">-- Choose Supplier --</option>
            <?php foreach($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                    <?= $s['name'] ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if(!empty($openPOs)): ?>
    <form action="/expenses/payments/create" method="POST">
        <input type="hidden" name="supplier_id" value="<?= $_GET['supplier_id'] ?>">
        
        <div class="grid grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded border">
            <div>
                <label class="block text-xs font-bold text-gray-500">Pay From (Bank/Cash)</label>
                <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                    <?php foreach($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= $acc['name'] ?> (₱<?= number_format($acc['current_balance'],2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500">Payment Method</label>
                <select name="payment_method" class="w-full border p-2 rounded bg-white">
                    <option value="check">Check</option>
                    <option value="cash">Cash</option>
                    <option value="transfer">Bank Transfer</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500">Reference # (Check No.)</label>
                <input type="text" name="reference_no" class="w-full border p-2 rounded bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500">Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded bg-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500">Total Payment Amount</label>
                <input type="text" name="total_paid" id="totalPaidDisplay" readonly class="w-full border p-2 rounded bg-gray-200 font-bold text-blue-600">
            </div>
        </div>

        <h3 class="font-bold mb-2">Select POs to Pay</h3>
        <table class="w-full border mb-6">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2 text-left">PO #</th>
                    <th class="p-2 text-left">Date</th>
                    <th class="p-2 text-right">Total Amount</th>
                    <th class="p-2 text-right">Balance Due</th>
                    <th class="p-2 text-right w-40">Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($openPOs as $po): ?>
                <?php $balance = $po['total_amount'] - $po['amount_paid']; ?>
                <tr class="border-b">
                    <td class="p-2"><?= $po['po_number'] ?></td>
                    <td class="p-2"><?= $po['date'] ?></td>
                    <td class="p-2 text-right">₱<?= number_format($po['total_amount'], 2) ?></td>
                    <td class="p-2 text-right font-bold text-red-500">₱<?= number_format($balance, 2) ?></td>
                    <td class="p-2">
                        <input type="number" step="0.01" max="<?= $balance ?>" 
                               class="pay-input w-full border p-1 text-right bg-yellow-50" 
                               data-id="<?= $po['id'] ?>" 
                               placeholder="0.00" 
                               oninput="calcTotal()">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <input type="hidden" name="allocations_json" id="allocJson">
        <div class="text-right">
            <button type="submit" onclick="preparePayment()" class="bg-green-600 text-white px-6 py-3 rounded font-bold shadow">
                Process Payment
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.pay-input').forEach(el => {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('totalPaidDisplay').value = total.toFixed(2);
}

function preparePayment() {
    const allocs = [];
    document.querySelectorAll('.pay-input').forEach(el => {
        const val = parseFloat(el.value) || 0;
        if(val > 0) {
            allocs.push({ po_id: el.dataset.id, amount: val });
        }
    });
    document.getElementById('allocJson').value = JSON.stringify(allocs);
}
</script>