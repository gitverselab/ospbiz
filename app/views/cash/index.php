<div class="flex justify-end mb-6">
    <button onclick="document.getElementById('addCashModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        Add New Cash Account
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account Name</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Current Balance</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($accounts as $acc): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-gray-800"><?php echo htmlspecialchars($acc['name']); ?></td>
                <td class="px-6 py-4 text-right font-bold text-gray-900">₱<?php echo number_format($acc['current_balance'], 2); ?></td>
                <td class="px-6 py-4 text-center text-sm">
                    <a href="/bank/cash-on-hand/view?id=<?php echo $acc['id']; ?>" class="text-blue-600 hover:underline mr-2">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addCashModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="text-lg font-bold mb-4">Add Cash Account</h3>
        <form action="/bank/cash-on-hand/create" method="POST" class="space-y-3">
            <input type="text" name="name" placeholder="Account Name (e.g. Petty Cash)" class="w-full border p-2 rounded" required>
            <input type="number" step="0.01" name="opening_balance" placeholder="Opening Balance" class="w-full border p-2 rounded" value="0.00">
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="document.getElementById('addCashModal').classList.add('hidden')" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>