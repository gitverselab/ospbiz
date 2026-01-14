<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Loan Payments</h2>
    <a href="/expenses/loan-payments/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-money-bill-transfer mr-2"></i> Record Payment
    </a>
</div>

<form method="GET" action="/expenses/loan-payments" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Lender Name or Reference..." class="w-full border p-2 rounded text-sm">
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
            <a href="/expenses/loan-payments" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Lender</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Paid From</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Principal</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Interest</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Paid</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($payments)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No payments found.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($p['date'])) ?></td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-gray-800"><?= htmlspecialchars($p['lender_name']) ?></div>
                        <div class="text-xs text-gray-500">Loan Ref: <?= htmlspecialchars($p['loan_ref']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($p['account_name']) ?></td>
                    <td class="px-6 py-4 text-right text-sm text-gray-900">₱<?= number_format($p['principal_amount'], 2) ?></td>
                    <td class="px-6 py-4 text-right text-sm text-red-600">₱<?= number_format($p['interest_amount'], 2) ?></td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($p['total_paid'], 2) ?></td>
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