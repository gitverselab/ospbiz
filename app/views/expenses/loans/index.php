<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Credits / Loans</h2>
    <a href="/expenses/loans/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-hand-holding-dollar mr-2"></i> New Loan
    </a>
</div>

<form method="GET" action="/expenses/loans" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Lender Name or Ref #..." class="w-full border p-2 rounded text-sm">
        </div>
        
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
            <select name="status" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All</option>
                <option value="active" <?= ($filters['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                <option value="paid" <?= ($filters['status'] == 'paid') ? 'selected' : '' ?>>Fully Paid</option>
            </select>
        </div>

        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white">
                <option value="10" <?= ($filters['limit'] == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($filters['limit'] == 25) ? 'selected' : '' ?>>25</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/loans" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lender / Reference</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Principal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Paid</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Balance</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($loans)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No loans found.</td></tr>
            <?php else: ?>
                <?php foreach ($loans as $l): 
                    $percent = ($l['principal_amount'] > 0) ? ($l['amount_paid'] / $l['principal_amount']) * 100 : 0;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($l['date'])) ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($l['lender_name']) ?></div>
                        <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($l['reference_no']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">₱<?= number_format($l['principal_amount'], 2) ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-green-600 font-bold">₱<?= number_format($l['amount_paid'], 2) ?></div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                            <div class="bg-green-500 h-1.5 rounded-full" style="width: <?= $percent ?>%"></div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-bold text-red-600">₱<?= number_format($l['balance'], 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= ($l['status'] == 'paid') ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                            <?= ucfirst($l['status']) ?>
                        </span>
                    </td>
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
        Showing Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?> 
        (Total <?= $filters['total_records'] ?> records)
    </div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?><a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Prev</a><?php endif; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?><a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a><?php endif; ?>
    </div>
</div>