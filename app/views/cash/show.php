<div class="flex justify-between items-end mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($account['name']); ?></h2>
        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($account['account_number'] ?? ''); ?></div>
    </div>
    <div class="text-right">
        <div class="text-sm text-gray-500">Current Balance</div>
        <div class="text-3xl font-bold text-gray-900">₱<?php echo number_format($account['current_balance'], 2); ?></div>
    </div>
</div>

<div class="flex gap-2 mb-6">
    <button class="bg-gray-600 text-white px-4 py-2 rounded shadow text-sm">Print Statement</button>
    <button onclick="document.getElementById('addTxnModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 text-sm font-bold">
        Add Transaction
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Debit (In)</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Credit (Out)</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Ref</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($transactions as $t): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-900"><?php echo $t['date']; ?></td>
                <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($t['description']); ?></td>
                <td class="px-6 py-4 text-right text-sm font-bold text-green-600">
                    <?php echo ($t['type'] == 'debit') ? '₱'.number_format($t['amount'], 2) : '-'; ?>
                </td>
                <td class="px-6 py-4 text-right text-sm font-bold text-red-600">
                    <?php echo ($t['type'] == 'credit') ? '₱'.number_format($t['amount'], 2) : '-'; ?>
                </td>
                <td class="px-6 py-4 text-center text-xs text-gray-500 font-mono">
                    <?php echo htmlspecialchars($t['reference_no']); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addTxnModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold mb-4">Add Transaction</h3>
        <form action="/bank/cash-on-hand/transaction" method="POST" class="space-y-4">
            <input type="hidden" name="financial_account_id" value="<?php echo $account['id']; ?>">
            
            <div class="grid grid-cols-2 gap-4">
                <select name="type" class="border p-2 rounded w-full bg-gray-50">
                    <option value="credit">Credit (Money Out / Payment)</option>
                    <option value="debit">Debit (Money In / Deposit)</option>
                </select>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" class="border p-2 rounded w-full">
            </div>

            <input type="number" step="0.01" name="amount" placeholder="Amount" class="border p-2 rounded w-full text-lg font-bold" required>
            <input type="text" name="description" placeholder="Description / Payee" class="border p-2 rounded w-full" required>
            <input type="text" name="reference_no" placeholder="Check # or Ref #" class="border p-2 rounded w-full">
            
            <div>
                <label class="text-xs text-gray-500">Category / Contra Account</label>
                <select name="contra_account_id" class="border p-2 rounded w-full text-sm">
                    <?php foreach ($coa as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo $c['code'] . ' - ' . $c['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="document.getElementById('addTxnModal').classList.add('hidden')" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save Transaction</button>
            </div>
        </form>
    </div>
</div>