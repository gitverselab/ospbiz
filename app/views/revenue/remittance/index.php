<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Payment Remittances</h2>
    <a href="/revenue/remittance/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Record Remittance
    </a>
</div>

<form method="GET" action="/revenue/remittance" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Ref No. / Check No..." class="w-full border p-2 rounded text-sm">
        </div>

        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">Customer</label>
            <select name="customer" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Customers</option>
                <?php foreach($customers as $c): ?>
                    <option value="<?= htmlspecialchars($c['customer_name']) ?>" <?= ($filters['customer'] == $c['customer_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="w-full border p-2 rounded text-sm">
        </div>

        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white" onchange="this.form.submit()">
                <option value="10" <?= ($filters['limit'] == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($filters['limit'] == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ($filters['limit'] == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
        <a href="/revenue/remittance" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ref No.</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Deposited To</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Gross Amount</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">WHT (1%)</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Net Received</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($remittances)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No remittances found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach($remittances as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-600"><?= $r['date'] ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td class="px-6 py-4 text-sm font-mono text-blue-600"><?= htmlspecialchars($r['reference_no']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($r['bank_name']) ?></td>
                    <td class="px-6 py-4 text-right text-sm text-gray-600">₱<?= number_format($r['total_gross_amount'], 2) ?></td>
                    <td class="px-6 py-4 text-right text-sm text-red-500">(₱<?= number_format($r['total_wht_amount'], 2) ?>)</td>
                    <td class="px-6 py-4 text-right font-bold text-green-700">₱<?= number_format($r['net_amount_received'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Showing Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?> (Total <?= $filters['total_records'] ?>)
    </div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?>
            <a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Previous</a>
        <?php endif; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?>
            <a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a>
        <?php endif; ?>
    </div>
</div>