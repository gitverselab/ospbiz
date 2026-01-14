<form action="/expenses/loan-payments/store" method="POST" class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">Record Loan Payment</h2>
        <a href="/expenses/loan-payments" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 mb-6">
        <label class="block text-xs font-bold text-blue-700 uppercase mb-2">Select Loan to Pay</label>
        <select name="loan_id" class="w-full border p-2 rounded bg-white font-bold text-gray-700" required>
            <option value="">-- Select Active Loan --</option>
            <?php foreach($loans as $l): ?>
                <option value="<?= $l['id'] ?>">
                    <?= $l['lender_name'] ?> (Ref: <?= $l['reference_no'] ?>) - Bal: ₱<?= number_format($l['balance'], 2) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pay From (Bank/Cash)</label>
            <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= $acc['name'] ?> (Bal: ₱<?= number_format($acc['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
        </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase mb-3 border-b pb-1">Payment Breakdown</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Principal Amount</label>
            <input type="number" step="0.01" name="principal_amount" id="prin" oninput="calcTotal()" class="w-full border p-2 rounded text-lg font-bold" placeholder="0.00">
            <div class="text-xs text-green-600 mt-1">Reduces Loan Balance</div>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Interest Amount</label>
            <input type="number" step="0.01" name="interest_amount" id="int" oninput="calcTotal()" class="w-full border p-2 rounded text-lg font-bold text-red-600" placeholder="0.00">
            <div class="text-xs text-red-500 mt-1">Recorded as Expense</div>
        </div>
    </div>

    <div class="mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference No. / Check #</label>
        <input type="text" name="reference_no" class="w-full border p-2 rounded">
    </div>

    <div class="flex justify-between items-center bg-gray-50 p-4 rounded-lg border">
        <div class="text-sm font-bold text-gray-500 uppercase">Total Payment Amount</div>
        <div class="text-3xl font-bold text-gray-800" id="totalDisplay">₱0.00</div>
    </div>

    <div class="text-right mt-6">
        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow">Record Payment</button>
    </div>
</form>

<script>
function calcTotal() {
    const p = parseFloat(document.getElementById('prin').value) || 0;
    const i = parseFloat(document.getElementById('int').value) || 0;
    const total = p + i;
    document.getElementById('totalDisplay').innerText = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
}
</script>