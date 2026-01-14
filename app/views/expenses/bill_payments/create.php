<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Bill Payment</h2>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Select Supplier / Biller</label>
        <select onchange="window.location.href='/expenses/bill-payments/create?supplier_id='+this.value" class="w-full md:w-1/3 border p-2 rounded bg-white">
            <option value="">-- Choose Supplier --</option>
            <?php foreach($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if(isset($_GET['supplier_id'])): ?>
    <form action="/expenses/bill-payments/store" method="POST">
        <input type="hidden" name="supplier_id" value="<?= $_GET['supplier_id'] ?>">
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pay From</label>
                <select name="financial_account_id" class="w-full border p-2 rounded bg-white">
                    <?php foreach($accounts as $acc): ?><option value="<?= $acc['id'] ?>"><?= $acc['name'] ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Method</label>
                <select name="payment_method" class="w-full border p-2 rounded bg-white"><option value="check">Check</option><option value="cash">Cash</option></select>
            </div>
            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ref #</label><input type="text" name="reference_no" class="w-full border p-2 rounded"></div>
            <div><label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded"></div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
            <h3 class="font-bold text-gray-800 mb-4">Unpaid Bills</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr><th>Bill #</th><th>Due Date</th><th class="text-right">Balance</th><th class="text-right w-40">Payment</th></tr>
                </thead>
                <tbody>
                    <?php foreach($openBills as $b): $bal = $b['total_amount'] - $b['amount_paid']; ?>
                    <tr>
                        <td class="px-4 py-3 text-sm font-mono text-blue-600"><?= $b['bill_number'] ?></td>
                        <td class="px-4 py-3 text-sm text-red-500"><?= $b['due_date'] ?></td>
                        <td class="px-4 py-3 text-right font-bold">₱<?= number_format($bal, 2) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <input type="checkbox" class="h-4 w-4" onchange="toggleRow(this, <?= $bal ?>)">
                                <input type="number" step="0.01" max="<?= $bal ?>" class="pay-input w-28 border rounded p-1 text-right disabled:bg-gray-100" data-id="<?= $b['id'] ?>" disabled oninput="calcTotal()">
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end items-center bg-gray-50 p-4 rounded-lg border gap-6">
            <div class="text-right"><div class="text-xs font-bold text-gray-500">Total</div><div class="text-2xl font-bold text-green-600" id="dispTotal">₱0.00</div></div>
            <input type="hidden" name="total_paid" id="inpTotal" value="0"><input type="hidden" name="allocations_json" id="inpAlloc">
            <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-8 py-3 rounded font-bold">Pay Bills</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function toggleRow(cb, max) {
    const inp = cb.nextElementSibling;
    inp.disabled = !cb.checked;
    if(cb.checked) inp.value = max.toFixed(2); else inp.value = '';
    calcTotal();
}
function calcTotal() {
    let tot = 0;
    document.querySelectorAll('.pay-input:not([disabled])').forEach(el => tot += parseFloat(el.value)||0);
    document.getElementById('dispTotal').innerText = '₱'+tot.toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('inpTotal').value = tot.toFixed(2);
}
function prepareSubmit(e) {
    const allocs = [];
    document.querySelectorAll('.pay-input:not([disabled])').forEach(el => {
        if(el.value > 0) allocs.push({ bill_id: el.dataset.id, amount: el.value });
    });
    document.getElementById('inpAlloc').value = JSON.stringify(allocs);
}
</script>