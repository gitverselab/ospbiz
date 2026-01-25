<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Purchase Orders</h2>
    <a href="/expenses/purchases/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Create PO
    </a>
</div>

<form method="GET" action="/expenses/purchases" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="PO Number or Supplier..." class="w-full border p-2 rounded text-sm">
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
            <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
            <select name="status" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Status</option>
                <option value="open" <?= ($filters['status'] == 'open') ? 'selected' : '' ?>>Open</option>
                <option value="partial" <?= ($filters['status'] == 'partial') ? 'selected' : '' ?>>Partial</option>
                <option value="paid" <?= ($filters['status'] == 'paid') ? 'selected' : '' ?>>Paid</option>
            </select>
        </div>
        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white" onchange="this.form.submit()">
                <option value="10" <?= ($filters['limit'] == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($filters['limit'] == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ($filters['limit'] == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/purchases" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">PO #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-600 uppercase tracking-wider">Total</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($pos)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No Purchase Orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($pos as $po): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-bold text-blue-600 font-mono"><?= htmlspecialchars($po['po_number']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($po['date'])) ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($po['supplier_name']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                            $statusClass = match($po['status']) {
                                'paid' => 'bg-green-100 text-green-800',
                                'partial' => 'bg-orange-100 text-orange-800',
                                default => 'bg-yellow-100 text-yellow-800'
                            };
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                            <?= ucfirst($po['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($po['total_amount'], 2) ?></td>
                    <td class="px-6 py-4 text-center text-sm">
                        <a href="/expenses/purchases/view?id=<?= $po['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3 font-medium">View</a>
                        <?php if($po['status'] !== 'paid'): ?>
                            <a href="/expenses/purchases/edit?id=<?= $po['id'] ?>" class="text-gray-600 hover:text-gray-900">Edit</a>
                        <?php endif; ?>
                    </td>
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