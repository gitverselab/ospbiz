<div class="flex justify-between items-center mb-6">
    <div class="text-sm text-gray-500">
        Directory of all active <?php echo ucfirst($type); ?>s.
    </div>
    <button onclick="document.getElementById('addContactModal').classList.remove('hidden')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add <?php echo ucfirst($type); ?>
    </button>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Contact Info</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tax ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Address</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($contacts)): ?>
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 text-sm">No records found.</td></tr>
            <?php else: ?>
                <?php foreach ($contacts as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($c['name']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <div><i class="fa-regular fa-envelope w-4"></i> <?php echo htmlspecialchars($c['email'] ?? '-'); ?></div>
                        <div class="mt-1"><i class="fa-solid fa-phone w-4"></i> <?php echo htmlspecialchars($c['phone'] ?? '-'); ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 font-mono"><?php echo htmlspecialchars($c['tax_id'] ?? '-'); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600 truncate max-w-xs"><?php echo htmlspecialchars($c['address'] ?? '-'); ?></td>
                    <td class="px-6 py-4 text-right text-sm">
                        <a href="#" class="text-blue-600 hover:text-blue-900">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="addContactModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold text-gray-800">Add New <?php echo ucfirst($type); ?></h3>
            <button onclick="document.getElementById('addContactModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        
        <form action="/settings/<?php echo $type; ?>s/create" method="POST" class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Company/Name *</label>
                <input type="text" name="name" class="w-full border-gray-300 rounded-md shadow-sm border p-2" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" class="w-full border-gray-300 rounded-md shadow-sm border p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full border-gray-300 rounded-md shadow-sm border p-2">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tax ID (TIN)</label>
                <input type="text" name="tax_id" class="w-full border-gray-300 rounded-md shadow-sm border p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2" class="w-full border-gray-300 rounded-md shadow-sm border p-2"></textarea>
            </div>
            
            <div class="pt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('addContactModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">Save <?php echo ucfirst($type); ?></button>
            </div>
        </form>
    </div>
</div>