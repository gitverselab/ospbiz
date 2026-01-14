<form action="/expenses/loans/store" method="POST" class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Loan Record</h2>
        <a href="/expenses/loans" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase mb-3">Lender Information</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Lender Name</label>
            <input type="text" name="lender_name" class="w-full border p-2 rounded" placeholder="Bank Name or Person" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference / Agreement #</label>
            <input type="text" name="reference_no" class="w-full border p-2 rounded" placeholder="e.g. LN-2023-001">
        </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase mb-3">Loan Terms</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Principal Amount</label>
            <input type="number" step="0.01" name="principal_amount" class="w-full border p-2 rounded text-lg font-bold" placeholder="0.00" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date Received</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Maturity Date</label>
            <input type="date" name="maturity_date" class="w-full border p-2 rounded">
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Interest Rate (%)</label>
            <input type="number" step="0.01" name="interest_rate" class="w-full border p-2 rounded" placeholder="e.g. 5.5">
        </div>
    </div>

    <h3 class="text-sm font-bold text-gray-500 uppercase mb-3">Accounting Entry</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-green-50 p-4 rounded border border-green-100">
            <label class="block text-xs font-bold text-green-700 uppercase mb-1">Deposit To (Debit Asset)</label>
            <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                <?php foreach($financialAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= $acc['name'] ?> (<?= ucfirst($acc['type']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="text-xs text-green-600 mt-1">Money will be added to this account.</div>
        </div>
        <div class="bg-red-50 p-4 rounded border border-red-100">
            <label class="block text-xs font-bold text-red-700 uppercase mb-1">Liability Account (Credit)</label>
            <select name="liability_account_id" class="w-full border p-2 rounded bg-white" required>
                <?php foreach($liabilityAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= $acc['code'] ?> - <?= $acc['name'] ?></option>
                <?php endforeach; ?>
            </select>
            <div class="text-xs text-red-600 mt-1">Tracks the obligation to pay.</div>
        </div>
    </div>

    <div class="mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description / Notes</label>
        <textarea name="description" class="w-full border p-2 rounded" rows="2" placeholder="Additional details..."></textarea>
    </div>

    <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow">Record Loan</button>
    </div>
</form>