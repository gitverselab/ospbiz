<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Check Registry</h2>
    <a href="/bank/checks/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-pen-nib mr-2"></i> Encode Check
    </a>
</div>

<form method="GET" action="/bank/checks" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        
        <div class="flex-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Check # or Payee..." class="w-full border p-2 rounded text-sm">
        </div>
        
        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">Bank Account</label>
            <select name="bank_id" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Banks</option>
                <?php foreach($banks as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($filters['bank_id'] == $b['id']) ? 'selected' : '' ?>><?= $b['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
            <select name="status" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All</option>
                <option value="issued" <?= ($filters['status'] == 'issued') ? 'selected' : '' ?>>Issued</option>
                <option value="cleared" <?= ($filters['status'] == 'cleared') ? 'selected' : '' ?>>Cleared</option>
                <option value="void" <?= ($filters['status'] == 'void') ? 'selected' : '' ?>>Void</option>
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
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white">
                <option value="10" <?= ($filters['limit'] == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($filters['limit'] == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ($filters['limit'] == 50) ? 'selected' : '' ?>>50</option>
                <option value="100" <?= ($filters['limit'] == 100) ? 'selected' : '' ?>>100</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">Filter</button>
            <a href="/bank/checks" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Check #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Payee</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bank</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($checks)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No checks found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($checks as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($c['date'])) ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-blue-600 font-mono"><?= htmlspecialchars($c['check_number']) ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($c['payee_name']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($c['bank_name']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                            $badge = match($c['status']) {
                                'cleared' => 'bg-green-100 text-green-800',
                                'void' => 'bg-red-100 text-red-800',
                                default => 'bg-blue-50 text-blue-800'
                            };
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($c['amount'], 2) ?></td>
                    <td class="px-6 py-4 text-center text-xs">
                        <?php if($c['status'] == 'issued'): ?>
                            <form action="/bank/checks/status" method="POST" class="inline" onsubmit="return confirm('Mark as Cleared?');">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="status" value="cleared">
                                <button class="text-green-600 hover:underline mr-2">Clear</button>
                            </form>
                            <form action="/bank/checks/status" method="POST" class="inline" onsubmit="return confirm('VOID this check? Money will be returned to bank balance.');">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="status" value="void">
                                <button class="text-red-600 hover:underline">Void</button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
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
        Showing Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?> 
        (Total <?= $filters['total_records'] ?> records)
    </div>
    
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?>
            <a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Previous</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Previous</span>
        <?php endif; ?>

        <?php if ($filters['page'] < $filters['total_pages']): ?>
            <a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Next</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Next</span>
        <?php endif; ?>
    </div>
</div>