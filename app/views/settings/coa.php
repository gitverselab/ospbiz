<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-500">Manage your financial categories.</div>
    <button onclick="document.getElementById('addAccountModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add Account
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Sub-Type</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($accounts as $acc): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-mono text-sm text-blue-600 font-bold"><?php echo htmlspecialchars($acc['code']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($acc['name']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($acc['type']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($acc['subtype'] ?? '-'); ?></td>
                <td class="px-6 py-4 text-center">
                    <?php if($acc['is_active']): ?>
                        <span class="px-2 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded-full">Active</span>
                    <?php else: ?>
                        <span class="px-2 py-1 text-xs font-semibold text-red-800 bg-red-100 rounded-full">Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addAccountModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold text-gray-800">Add New Account</h3>
            <button onclick="document.getElementById('addAccountModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <form action="/settings/coa/create" method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Code</label>
                <input type="text" name="code" class="w-full border-gray-300 rounded-md shadow-sm border p-2" placeholder="e.g. 1010" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Account Name</label>
                <input type="text" name="name" class="w-full border-gray-300 rounded-md shadow-sm border p-2" placeholder="e.g. Petty Cash" required>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full border-gray-300 rounded-md shadow-sm border p-2">
                        <option value="asset">Asset</option>
                        <option value="liability">Liability</option>
                        <option value="equity">Equity</option>
                        <option value="revenue">Revenue</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sub-Type</label>
                    <input type="text" name="subtype" class="w-full border-gray-300 rounded-md shadow-sm border p-2" placeholder="Optional">
                </div>
            </div>
            
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('addAccountModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">Save Account</button>
            </div>
        </form>
    </div>
</div>