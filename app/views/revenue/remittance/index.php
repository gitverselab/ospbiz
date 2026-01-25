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
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Ref No..." class="w-full border p-2 rounded text-sm">
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
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ref No.</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bank</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($remittances)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No records found.</td></tr>
            <?php else: ?>
                <?php foreach($remittances as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-600"><?= $r['date'] ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td class="px-6 py-4 text-sm font-mono text-blue-600"><?= htmlspecialchars($r['reference_no']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($r['bank_name']) ?></td>
                    <td class="px-6 py-4 text-right font-bold text-green-700">₱<?= number_format($r['net_amount_received'], 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <form action="/revenue/remittance/void" method="POST" onsubmit="return confirm('WARNING: Voiding this remittance will:\n1. Deduct funds from the bank.\n2. Revert Invoices to Unpaid.\n3. Revert Returns to Received.\n\nContinue?');">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold border border-red-200 px-2 py-1 rounded hover:bg-red-50">
                                <i class="fa-solid fa-ban"></i> Void
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?>
    </div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?>
            <a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Prev</a>
        <?php endif; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?>
            <a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a>
        <?php endif; ?>
    </div>
</div>