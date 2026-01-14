<div class="flex justify-end mb-6">
    <button onclick="document.getElementById('addBankModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        Add New Passbook
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bank Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account Holder</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Current Balance</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($passbooks as $pb): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-gray-800"><?php echo htmlspecialchars($pb['name']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($pb['account_number']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($pb['account_holder']); ?></td>
                <td class="px-6 py-4 text-right font-bold text-gray-900">₱<?php echo number_format($pb['current_balance'], 2); ?></td>
                <td class="px-6 py-4 text-center text-sm">
                    <a href="/bank/passbooks/view?id=<?php echo $pb['id']; ?>" class="text-blue-600 hover:underline mr-2">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="addBankModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
        <h3 class="text-lg font-bold mb-4">Add Passbook</h3>
        <form action="/bank/passbooks/create" method="POST" class="space-y-3">
            <input type="text" name="name" placeholder="Bank Name (e.g. MetroBank Main)" class="w-full border p-2 rounded" required>
            <input type="text" name="account_number" placeholder="Account Number" class="w-full border p-2 rounded">
            <input type="text" name="account_holder" placeholder="Account Holder Name" class="w-full border p-2 rounded">
            <input type="number" step="0.01" name="opening_balance" placeholder="Opening Balance" class="w-full border p-2 rounded" value="0.00">
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="document.getElementById('addBankModal').classList.add('hidden')" class="px-4 py-2 text-gray-600">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>