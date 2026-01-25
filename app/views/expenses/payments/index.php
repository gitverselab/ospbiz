<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Purchase Payments</h2>
    <a href="/expenses/payments/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-money-bill-transfer mr-2"></i> New Payment
    </a>
</div>

<form method="GET" action="/expenses/payments" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Reference # or Supplier..." class="w-full border p-2 rounded text-sm">
        </div>

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">Method</label>
            <select name="method" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All</option>
                <option value="check" <?= (($filters['method'] ?? '') == 'check') ? 'selected' : '' ?>>Check</option>
                <option value="transfer" <?= (($filters['method'] ?? '') == 'transfer') ? 'selected' : '' ?>>Transfer</option>
                <option value="cash" <?= (($filters['method'] ?? '') == 'cash') ? 'selected' : '' ?>>Cash</option>
            </select>
        </div>

        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white" onchange="this.form.submit()">
                <?php $lim = $filters['limit'] ?? 10; ?>
                <option value="10" <?= ($lim == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($lim == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ($lim == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/payments" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Paid From</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Reference</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Paid</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($payments)): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500 italic">No payments found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($p['date'])) ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($p['supplier_name']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?= htmlspecialchars($p['account_name']) ?>
                        <span class="text-xs text-gray-400 block uppercase font-bold"><?= $p['payment_method'] ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm font-mono text-blue-600"><?= htmlspecialchars($p['reference_no']) ?></td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($p['total_paid'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Showing Page <?= $filters['page'] ?? 1 ?> of <?= $filters['total_pages'] ?? 1 ?> 
        (Total <?= $filters['total_records'] ?? 0 ?> records)
    </div>
    
    <div class="flex gap-2">
        <?php 
            $params = $_GET; 
            unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
            $currPage = $filters['page'] ?? 1;
            $maxPage = $filters['total_pages'] ?? 1;
        ?>

        <?php if ($currPage > 1): ?>
            <a href="<?= $baseUrl . ($currPage - 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Previous</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Previous</span>
        <?php endif; ?>

        <?php for($i = 1; $i <= $maxPage; $i++): ?>
            <a href="<?= $baseUrl . $i ?>" class="px-3 py-1 border rounded text-sm <?= ($i == $currPage) ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($currPage < $maxPage): ?>
            <a href="<?= $baseUrl . ($currPage + 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Next</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Next</span>
        <?php endif; ?>
    </div>
</div>