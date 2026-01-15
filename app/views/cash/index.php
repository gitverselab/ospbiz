<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Cash on Hand / Petty Cash</h2>
    <button onclick="document.getElementById('addCashModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add New Fund
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Custodian</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Linked GL Account</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Current Balance</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($accounts as $acc): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-gray-800"><?php echo htmlspecialchars($acc['name'] ?? ''); ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($acc['account_holder'] ?? '-'); ?></td>
                <td class="px-6 py-4 text-sm">
                    <?php if(!empty($acc['gl_code'])): ?>
                        <span class="text-blue-600 font-bold font-mono"><?= htmlspecialchars($acc['gl_code']) ?></span> 
                        <span class="text-gray-600">- <?= htmlspecialchars($acc['gl_name'] ?? '') ?></span>
                    <?php else: ?>
                        <span class="text-red-400 italic text-xs">Unlinked</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-right font-bold text-gray-900">₱<?php echo number_format($acc['current_balance'] ?? 0, 2); ?></td>
                <td class="px-6 py-4 text-center text-sm">
                    <a href="/bank/cash-on-hand/view?id=<?php echo $acc['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">View Ledger</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addCashModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="text-lg font-bold mb-4 text-gray-800">Add Cash Account</h3>
        
        <form action="/bank/cash-on-hand/create" method="POST" class="space-y-3">
            
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Fund Name</label>
                <input type="text" name="name" placeholder="e.g. Petty Cash Fund" class="w-full border p-2 rounded text-sm" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Custodian</label>
                <input type="text" name="account_holder" placeholder="Who holds this cash?" class="w-full border p-2 rounded text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Link to GL Account</label>
                <select name="account_id" class="w-full border p-2 rounded text-sm bg-white border-blue-300" required>
                    <option value="">-- Select Asset Account --</option>
                    <?php foreach($assetAccounts as $aa): ?>
                        <option value="<?= $aa['id'] ?>"><?= $aa['code'] ?> - <?= $aa['name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-500 mt-1">Maps this fund to your Chart of Accounts (e.g. 1000).</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Opening Balance</label>
                <input type="number" step="0.01" name="opening_balance" placeholder="0.00" class="w-full border p-2 rounded text-sm font-bold text-right" value="0.00">
            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t">
                <button type="button" onclick="document.getElementById('addCashModal').classList.add('hidden')" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded text-sm">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700">Save Fund</button>
            </div>
        </form>
    </div>
</div>