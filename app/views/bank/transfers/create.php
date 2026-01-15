<form action="/bank/transfers/store" method="POST" class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Fund Transfer</h2>
        <a href="/bank/transfers" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-red-50 p-4 rounded border border-red-100">
            <label class="block text-xs font-bold text-red-700 uppercase mb-2">From (Source)</label>
            <select name="from_account_id" class="w-full border p-2 rounded bg-white" required>
                <option value="">Select Account...</option>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= $acc['name'] ?> (<?= ucfirst($acc['type']) ?> - ₱<?= number_format($acc['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bg-green-50 p-4 rounded border border-green-100">
            <label class="block text-xs font-bold text-green-700 uppercase mb-2">To (Destination)</label>
            <select name="to_account_id" class="w-full border p-2 rounded bg-white" required>
                <option value="">Select Account...</option>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"><?= $acc['name'] ?> (<?= ucfirst($acc['type']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
            <input type="number" step="0.01" name="amount" class="w-full border p-2 rounded font-bold text-lg" placeholder="0.00" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Method</label>
            <select name="method" class="w-full border p-2 rounded bg-white">
                <option value="online">Online Transfer</option>
                <option value="check">Check (Withdrawal)</option>
                <option value="cash">Cash (Handover)</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference No. / Check #</label>
            <input type="text" name="reference_no" class="w-full border p-2 rounded" placeholder="e.g. Check 12345 or Ref 001">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description / Memo</label>
            <input type="text" name="description" class="w-full border p-2 rounded" placeholder="Reason for transfer...">
        </div>
    </div>

    <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow">Process Transfer</button>
    </div>
</form>