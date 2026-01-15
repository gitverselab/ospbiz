<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Fund Transfers</h2>
    <a href="/bank/transfers/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-arrow-right-arrow-left mr-2"></i> New Transfer
    </a>
</div>

<form method="GET" action="/bank/transfers" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        
        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Ref # or Description..." class="w-full border p-2 rounded text-sm">
        </div>

        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">From (Source)</label>
            <select name="source_id" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Accounts</option>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= ($filters['source_id'] == $acc['id']) ? 'selected' : '' ?>>
                        <?= $acc['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">To (Destination)</label>
            <select name="dest_id" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Accounts</option>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= ($filters['dest_id'] == $acc['id']) ? 'selected' : '' ?>>
                        <?= $acc['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Date From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="w-full border p-2 rounded text-sm">
        </div>
        
        <div class="col-span-1 flex gap-2">
            <div class="w-full">
                <label class="text-xs font-bold text-gray-500 uppercase">Date To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="w-full border p-2 rounded text-sm">
            </div>
        </div>
    </div>
    
    <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
        <div class="w-32">
            <label class="text-xs font-bold text-gray-500 uppercase mr-2">Show</label>
            <select name="limit" class="border p-1 rounded text-sm bg-white" onchange="this.form.submit()">
                <option value="10" <?= ($filters['limit'] == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= ($filters['limit'] == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= ($filters['limit'] == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/bank/transfers" class="bg-gray-200 text-gray-700 px-6 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">From (Source)</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase"><i class="fa-solid fa-arrow-right"></i></th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">To (Destination)</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ref #</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($transfers)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No transfers found.</td></tr>
            <?php else: ?>
                <?php foreach ($transfers as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900"><?= date('M j, Y', strtotime($t['date'])) ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-red-600">
                        <?= htmlspecialchars($t['from_acc']) ?>
                        <span class="text-xs text-gray-400 block"><?= ucfirst($t['from_type']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-center text-gray-300"><i class="fa-solid fa-arrow-right"></i></td>
                    <td class="px-6 py-4 text-sm font-medium text-green-600">
                        <?= htmlspecialchars($t['to_acc']) ?>
                        <span class="text-xs text-gray-400 block"><?= ucfirst($t['to_type']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-500">
                        <?= htmlspecialchars($t['reference_no']) ?>
                        <span class="text-xs block"><?= ucfirst($t['method']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($t['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?></div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?><a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Prev</a><?php endif; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?><a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a><?php endif; ?>
    </div>
</div>