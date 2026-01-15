<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Passbook Management</h2>
    <button onclick="document.getElementById('addBankModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add New Passbook
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bank Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Linked GL Account</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Current Balance</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($passbooks as $pb): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-gray-800">
                    <?php echo htmlspecialchars($pb['name']); ?>
                    <div class="text-xs text-gray-400 font-normal"><?php echo htmlspecialchars($pb['bank_name']); ?></div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($pb['account_number']); ?></td>
                <td class="px-6 py-4 text-sm">
                    <?php if($pb['gl_code']): ?>
                        <span class="text-blue-600 font-bold font-mono"><?= $pb['gl_code'] ?></span> 
                        <span class="text-gray-600">- <?= $pb['gl_name'] ?></span>
                    <?php else: ?>
                        <span class="text-red-400 italic text-xs">Unlinked</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right font-bold text-gray-900">₱<?php echo number_format($pb['current_balance'], 2); ?></td>
                <td class="px-6 py-4 text-center text-sm">
                    <a href="/bank/passbooks/view?id=<?php echo $pb['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">View Ledger</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addBankModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="text-lg font-bold mb-4 text-gray-800">Add Passbook</h3>
        
        <form action="/bank/passbooks/store" method="POST" class="space-y-3">
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Passbook Label</label>
                <input type="text" name="name" placeholder="e.g. MetroBank Main" class="w-full border p-2 rounded text-sm" required>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g. Metrobank" class="w-full border p-2 rounded text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Number</label>
                <input type="text" name="account_number" placeholder="123-456-7890" class="w-full border p-2 rounded text-sm font-mono">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Holder</label>
                <input type="text" name="account_holder" placeholder="Company Name / Person" class="w-full border p-2 rounded text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link to GL Account</label>
                <select name="account_id" class="w-full border p-2 rounded text-sm bg-white border-blue-300" required>
                    <option value="">-- Select Asset Account --</option>
                    <?php foreach($assetAccounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"><?= $acc['code'] ?> - <?= $acc['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-500 mt-1">This maps the bank to your Chart of Accounts (e.g. 1010).</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Opening Balance</label>
                <input type="number" step="0.01" name="opening_balance" placeholder="0.00" class="w-full border p-2 rounded text-sm font-bold text-right" value="0.00">
            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t">
                <button type="button" onclick="document.getElementById('addBankModal').classList.add('hidden')" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded text-sm">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700">Save Passbook</button>
            </div>
        </form>
    </div>
</div>