<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Fund Transfers</h2>
    
    <a href="/bank/transfers/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-money-bill-transfer mr-2"></i> New Transfer
    </a>
</div>

<form method="GET" action="/bank/transfers" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        
        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Ref # or Description..." class="w-full border p-2 rounded text-sm">
        </div>

        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">From (Source)</label>
            <select name="source_id" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Accounts</option>
                <?php foreach($accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= (($filters['source_id'] ?? '') == $acc['id']) ? 'selected' : '' ?>>
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
                    <option value="<?= $acc['id'] ?>" <?= (($filters['dest_id'] ?? '') == $acc['id']) ? 'selected' : '' ?>>
                        <?= $acc['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Date From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>
        
        <div class="col-span-1">
            <label class="text-xs font-bold text-gray-500 uppercase">Date To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>
    </div>
    
    <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
        <div class="w-32">
            <label class="text-xs font-bold text-gray-500 uppercase mr-2">Show</label>
            <select name="limit" class="border p-1 rounded text-sm bg-white" onchange="this.form.submit()">
                <option value="10" <?= (($filters['limit'] ?? 10) == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= (($filters['limit'] ?? 10) == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= (($filters['limit'] ?? 10) == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/bank/transfers" class="bg-gray-200 text-gray-700 px-6 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden min-h-[400px]">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Method</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Source (From)</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Destination (To)</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($transfers)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No transfers found.</td></tr>
            <?php else: ?>
                <?php foreach ($transfers as $t): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-600"><?= $t['date'] ?></td>
                    
                    <td class="px-6 py-4 text-sm font-bold text-gray-700">
                        <?php 
                            if($t['method'] == 'check') echo '<i class="fa-solid fa-money-check mr-1 text-blue-500"></i> Check #' . $t['check_number'];
                            elseif($t['method'] == 'bank_transfer') echo '<i class="fa-solid fa-building-columns mr-1 text-purple-500"></i> Bank Transfer';
                            else echo '<i class="fa-solid fa-hand-holding-dollar mr-1 text-green-500"></i> Cash Handover';
                        ?>
                         <div class="text-xs text-gray-400 font-normal"><?= htmlspecialchars($t['reference_no']) ?></div>
                    </td>

                    <td class="px-6 py-4 text-sm text-red-600">
                        <?= htmlspecialchars($t['from_acc']) ?>
                         <span class="text-xs text-gray-400 block"><?= ucfirst($t['from_type']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-green-600">
                        <?= htmlspecialchars($t['to_acc']) ?>
                         <span class="text-xs text-gray-400 block"><?= ucfirst($t['to_type']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-900">₱<?= number_format($t['amount'], 2) ?></td>
                    
                    <td class="px-6 py-4 text-center">
                        <?php if ($t['status'] === 'pending'): ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full animate-pulse">Pending Approval</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Approved</span>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4 text-center">
                        <?php if ($t['status'] === 'pending'): ?>
                            <div class="flex justify-center gap-2">
                                <form action="/fund_transfers/approve" method="POST" onsubmit="return confirm('Confirm this transfer? Balances will be updated.');">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700 shadow flex items-center">
                                        <i class="fa-solid fa-check mr-1"></i> Approve
                                    </button>
                                </form>
                                <form action="/fund_transfers/delete" method="POST" onsubmit="return confirm('Cancel this request?');">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold px-2 py-1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400 italic"><i class="fa-solid fa-lock"></i> Locked</span>
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
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
            $currPage = $filters['page'] ?? 1;
            $maxPage = $filters['total_pages'] ?? 1;
        ?>
        <?php if ($currPage > 1): ?>
            <a href="<?= $baseUrl . ($currPage - 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Previous</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Previous</span>
        <?php endif; ?>

        <?php if ($currPage < $maxPage): ?>
            <a href="<?= $baseUrl . ($currPage + 1) ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Next</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Next</span>
        <?php endif; ?>
    </div>
</div>