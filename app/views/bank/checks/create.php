<form action="/bank/checks/store" method="POST" class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">Encode Check</h2>
        <a href="/bank/checks" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-4">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bank Account</label>
            <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                <?php foreach($banks as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= $b['name'] ?> (₱<?= number_format($b['current_balance'], 2) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Check Number</label>
            <input type="text" name="check_number" class="w-full border p-2 rounded" placeholder="e.g. 0012345" required>
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payee Name</label>
        <input type="text" name="payee_name" class="w-full border p-2 rounded" placeholder="Name on check..." required>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-4">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
            <input type="number" step="0.01" name="amount" class="w-full border p-2 rounded font-bold text-lg" placeholder="0.00" required>
        </div>
    </div>

    <div class="mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Memo / Description</label>
        <input type="text" name="memo" class="w-full border p-2 rounded" placeholder="What is this check for?">
    </div>

    <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded font-bold hover:bg-blue-700 shadow">Record Check</button>
    </div>
</form>