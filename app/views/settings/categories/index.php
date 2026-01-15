<div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    <div class="md:col-span-1">
        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-4 text-gray-800">Add New Category</h2>
            <form action="/settings/categories/store" method="POST">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category Name</label>
                    <input type="text" name="name" class="w-full border p-2 rounded" placeholder="e.g. Internet & Comm." required>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description (Optional)</label>
                    <textarea name="description" class="w-full border p-2 rounded" rows="3"></textarea>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded font-bold hover:bg-blue-700">
                    <i class="fa-solid fa-plus mr-2"></i> Add Category
                </button>
            </form>
        </div>
    </div>

    <div class="md:col-span-2">
        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-2 text-gray-800">Category Mapping</h2>
            <p class="text-xs text-gray-500 mb-6">Link each category to a Chart of Account code so Journal Entries are created automatically.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left border-collapse">
                    <thead class="bg-gray-100 uppercase text-xs">
                        <tr>
                            <th class="p-3 border-b">Category Name</th>
                            <th class="p-3 border-b">Linked GL Account</th>
                            <th class="p-3 border-b text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($categories)): ?>
                            <tr><td colspan="3" class="p-4 text-center text-gray-400 italic">No categories found.</td></tr>
                        <?php else: ?>
                            <?php foreach($categories as $cat): ?>
                            <tr class="hover:bg-gray-50 border-b">
                                <td class="p-3 font-medium text-gray-800">
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <div class="text-xs text-gray-400"><?= htmlspecialchars($cat['description']) ?></div>
                                </td>
                                <td class="p-3">
                                    <form action="/settings/categories/update" method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                        
                                        <select name="account_id" class="border rounded px-2 py-1 text-xs w-64 bg-white <?= $cat['account_id'] ? 'border-green-300' : 'border-red-300' ?>">
                                            <option value="">-- Unmapped --</option>
                                            <?php foreach($accounts as $acc): ?>
                                                <option value="<?= $acc['id'] ?>" <?= ($cat['account_id'] == $acc['id']) ? 'selected' : '' ?>>
                                                    <?= $acc['code'] ?> - <?= $acc['name'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <button type="submit" class="text-blue-600 hover:text-blue-800 text-xs font-bold underline" title="Save Mapping">
                                            Save
                                        </button>
                                    </form>
                                </td>
                                <td class="p-3 text-center">
                                    <form action="/settings/categories/delete" method="POST" onsubmit="return confirm('Delete this category?');">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="text-red-400 hover:text-red-600">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>