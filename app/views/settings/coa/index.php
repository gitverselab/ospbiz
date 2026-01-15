<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Chart of Accounts</h2>
    <button onclick="openModal('addModal')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add Account
    </button>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Code</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach($accounts as $acc): ?>
            <tr class="hover:bg-gray-50 group">
                <td class="px-6 py-3 font-mono text-blue-600 font-bold"><?= htmlspecialchars($acc['code']) ?></td>
                <td class="px-6 py-3 font-medium text-gray-800"><?= htmlspecialchars($acc['name']) ?></td>
                <td class="px-6 py-3 text-sm text-gray-600">
                    <span class="bg-gray-100 text-gray-600 py-1 px-2 rounded text-xs uppercase font-bold"><?= $acc['type'] ?></span>
                </td>
                <td class="px-6 py-3 text-center">
                    <?php 
                        // FIX: Use 'Active' as default if status is missing/null
                        $status = $acc['status'] ?? 'Active'; 
                    ?>
                    <?php if($status === 'Active'): ?>
                        <span class="text-green-600 text-xs font-bold"><i class="fa-solid fa-check-circle"></i> Active</span>
                    <?php else: ?>
                        <span class="text-red-400 text-xs font-bold"><i class="fa-solid fa-ban"></i> Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-3 text-center flex justify-center gap-3">
                    <button onclick='editAccount(<?= json_encode($acc) ?>)' class="text-blue-500 hover:text-blue-700">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    
                    <form action="/settings/coa/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this account?');">
                        <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="font-bold text-lg mb-4 text-gray-800">Add New Account</h3>
        <form action="/settings/coa/store" method="POST">
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Code</label>
                <input type="text" name="code" class="w-full border p-2 rounded" placeholder="e.g. 1010" required>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Name</label>
                <input type="text" name="name" class="w-full border p-2 rounded" placeholder="e.g. Cash on Hand" required>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                <select name="type" class="w-full border p-2 rounded bg-white">
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Revenue">Revenue</option>
                    <option value="Expense">Expense</option>
                    <option value="Cost of Goods Sold">Cost of Goods Sold</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded font-bold">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="font-bold text-lg mb-4 text-gray-800">Edit Account</h3>
        <form action="/settings/coa/update" method="POST">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Code</label>
                <input type="text" name="code" id="edit_code" class="w-full border p-2 rounded bg-gray-100" readonly>
                <p class="text-[10px] text-gray-400">Code cannot be changed.</p>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Account Name</label>
                <input type="text" name="name" id="edit_name" class="w-full border p-2 rounded" required>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                <select name="type" id="edit_type" class="w-full border p-2 rounded bg-white">
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Revenue">Revenue</option>
                    <option value="Expense">Expense</option>
                    <option value="Cost of Goods Sold">Cost of Goods Sold</option>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
                <select name="status" id="edit_status" class="w-full border p-2 rounded bg-white">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded font-bold">Update Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function editAccount(acc) {
    document.getElementById('edit_id').value = acc.id;
    document.getElementById('edit_code').value = acc.code;
    document.getElementById('edit_name').value = acc.name;
    document.getElementById('edit_type').value = acc.type;
    document.getElementById('edit_status').value = acc.status;
    
    openModal('editModal');
}
</script>