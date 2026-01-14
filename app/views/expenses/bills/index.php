<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Bills (Accounts Payable)</h2>
    <a href="/expenses/bills/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Create New Bill
    </a>
</div>

<form method="GET" action="/expenses/bills" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Bill # or Supplier..." class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white">
                <option value="10" <?php echo (($filters['limit'] ?? 10) == 10) ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo (($filters['limit'] ?? 10) == 25) ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo (($filters['limit'] ?? 10) == 50) ? 'selected' : ''; ?>>50</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/bills" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bill Number</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Due Date</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Balance Due</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($bills)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No bills found.</td></tr>
            <?php else: ?>
                <?php foreach ($bills as $b): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($b['date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-blue-600 font-mono"><?php echo htmlspecialchars($b['bill_number']); ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($b['supplier_name']); ?></td>
                    <td class="px-6 py-4 text-sm text-red-500"><?php echo date('M j, Y', strtotime($b['due_date'])); ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            <?php echo ($b['status'] == 'paid') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?php echo number_format($b['balance'], 2); ?></td>
                    <td class="px-6 py-4 text-center text-sm">
                        <a href="#" class="text-blue-600 hover:text-blue-900">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Page <?php echo $filters['page'] ?? 1; ?> of <?php echo $filters['total_pages'] ?? 1; ?> 
        (Total <?php echo $filters['total_records'] ?? 0; ?>)
    </div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
            $curr = $filters['page'] ?? 1;
            $total = $filters['total_pages'] ?? 1;
        ?>
        <?php if ($curr > 1): ?><a href="<?php echo $baseUrl . ($curr - 1); ?>" class="px-3 py-1 bg-white border rounded text-sm">Prev</a><?php endif; ?>
        <?php if ($curr < $total): ?><a href="<?php echo $baseUrl . ($curr + 1); ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a><?php endif; ?>
    </div>
</div>