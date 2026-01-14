<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Recurring Bill Profiles</h2>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">
        <i class="fa-solid fa-plus mr-2"></i> Create Profile
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Profile Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Next Due</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($recurrings as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-gray-800"><?php echo htmlspecialchars($r['profile_name']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($r['supplier_name']); ?></td>
                <td class="px-6 py-4 font-mono text-sm">₱<?php echo number_format($r['amount'], 2); ?></td>
                <td class="px-6 py-4 text-sm font-bold <?php echo (strtotime($r['next_due_date']) <= time()) ? 'text-red-600' : 'text-green-600'; ?>">
                    <?php echo $r['next_due_date']; ?>
                </td>
                <td class="px-6 py-4 text-center">
                    <form action="/expenses/recurring/generate" method="POST" onsubmit="return confirm('Generate bill for <?php echo $r['next_due_date']; ?>?');">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="text-blue-600 hover:text-blue-900 text-sm font-bold border border-blue-200 px-3 py-1 rounded bg-blue-50">
                            Generate Bill
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-full max-w-lg shadow-xl">
        <h3 class="text-lg font-bold mb-4">New Recurring Profile</h3>
        <form action="/expenses/recurring/create" method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <input type="text" name="profile_name" placeholder="Profile Name (e.g. Rent)" class="border p-2 rounded w-full" required>
                <select name="supplier_id" class="border p-2 rounded w-full" required>
                    <?php foreach($suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo $s['name']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <input type="number" name="amount" placeholder="Fixed Amount" step="0.01" class="border p-2 rounded w-full" required>
                <select name="frequency" class="border p-2 rounded w-full"><option value="monthly">Monthly</option></select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <input type="date" name="next_due_date" class="border p-2 rounded w-full" required>
                <select name="expense_account_id" class="border p-2 rounded w-full" required>
                    <?php foreach($accounts as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo $a['name']; ?></option><?php endforeach; ?>
                </select>
            </div>
            <input type="text" name="description" placeholder="Description for the generated bill" class="border p-2 rounded w-full">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save Profile</button>
            </div>
        </form>
    </div>
</div>