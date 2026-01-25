<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">RTS Management</h2>
    <div class="flex gap-2">
        <a href="/revenue/rts/create" class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 shadow font-bold">
            <i class="fa-solid fa-plus"></i> Manual Input
        </a>
        <div class="h-8 w-px bg-gray-300 mx-1"></div>
        <a href="/revenue/rts/template" class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700">
            <i class="fa-solid fa-download"></i> Template
        </a>
        <button onclick="document.getElementById('importRts').classList.remove('hidden')" class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600">
            <i class="fa-solid fa-file-import"></i> Import CSV
        </button>
        <a href="/revenue/rts/export" class="bg-gray-600 text-white px-3 py-2 rounded text-sm hover:bg-gray-700">
            <i class="fa-solid fa-file-export"></i> Export
        </a>
    </div>
</div>

<form method="GET" action="/revenue/rts" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="RD, GR, PO or Item..." class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">Plant / Customer</label>
            <select name="plant" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Plants</option>
                <?php foreach($customers as $c): ?>
                    <option value="<?= htmlspecialchars($c['name'] ?? '') ?>" <?= (($filters['plant'] ?? '') == ($c['name'] ?? '')) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white" onchange="this.form.submit()">
                <option value="10" <?= (($filters['limit'] ?? 10) == 10) ? 'selected' : '' ?>>10</option>
                <option value="25" <?= (($filters['limit'] ?? 10) == 25) ? 'selected' : '' ?>>25</option>
                <option value="50" <?= (($filters['limit'] ?? 10) == 50) ? 'selected' : '' ?>>50</option>
            </select>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">RD # / Orig GR</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Plant Name</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Item Description</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Qty</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($rts)): ?>
                <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500 italic">No return records found.</td></tr>
            <?php else: ?>
                <?php foreach($rts as $r): 
                    $incVat = $r['is_vat_inc'] ? $r['amount'] : ($r['amount'] * 1.12);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-red-600"><?= htmlspecialchars($r['rd_number']) ?></div>
                        <div class="text-xs text-gray-500">Orig GR: <?= htmlspecialchars($r['gr_number']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= date('m/d/Y', strtotime($r['date'])) ?>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800">
                        <?= htmlspecialchars($r['plant_name']) ?>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($r['plant_code']) ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm text-gray-800"><?= htmlspecialchars($r['description']) ?></div>
                        <div class="text-xs text-gray-500">Item: <?= htmlspecialchars($r['item_code']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="text-sm text-gray-800"><?= number_format($r['quantity'], 2) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($r['uom']) ?></div>
                    </td>
                    <td class="px-6 py-4 text-right text-sm font-bold text-gray-800">
                        <?= $r['currency'] ?> <?= number_format($incVat, 2) ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-3 py-1 text-xs font-bold text-white rounded-full 
                            <?= ($r['status'] == 'received') ? 'bg-green-500' : 'bg-orange-500' ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-sm">
                        <div class="flex flex-col gap-1 items-center">
                            <a href="/revenue/rts/edit?id=<?= $r['rts_id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                            <form action="/revenue/rts/delete" method="POST" onsubmit="return confirm('Delete this RTS record?');">
                                <input type="hidden" name="id" value="<?= $r['rts_id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 font-medium text-xs">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex flex-col md:flex-row justify-between items-center mt-4 border-t pt-4">
    <div class="text-sm text-gray-500 mb-2 md:mb-0">
        Page <?= $filters['page'] ?? 1 ?> of <?= $filters['total_pages'] ?? 1 ?> 
        (Total <?= $filters['total_records'] ?? 0 ?> records)
    </div>
    
    <div class="flex gap-1">
        <?php 
            $params = $_GET; 
            unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
            
            $currPage = (int)($filters['page'] ?? 1);
            $maxPage = (int)($filters['total_pages'] ?? 1);
            
            $start = max(1, $currPage - 2);
            $end = min($maxPage, $currPage + 2);
            
            if ($currPage <= 3) { $end = min(5, $maxPage); }
            if ($currPage > $maxPage - 2) { $start = max(1, $maxPage - 4); }
        ?>

        <?php if ($currPage > 1): ?>
            <a href="<?= $baseUrl . ($currPage - 1) ?>" class="px-3 py-1 bg-white border border-gray-300 text-gray-700 rounded text-sm hover:bg-gray-50">
                &laquo;
            </a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border border-gray-200 text-gray-400 rounded text-sm cursor-not-allowed">
                &laquo;
            </span>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?= $baseUrl . $i ?>" class="px-3 py-1 border rounded text-sm <?= ($i == $currPage) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($currPage < $maxPage): ?>
            <a href="<?= $baseUrl . ($currPage + 1) ?>" class="px-3 py-1 bg-white border border-gray-300 text-gray-700 rounded text-sm hover:bg-gray-50">
                &raquo;
            </a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border border-gray-200 text-gray-400 rounded text-sm cursor-not-allowed">
                &raquo;
            </span>
        <?php endif; ?>
    </div>
</div>

<div id="importRts" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl w-96">
        <h3 class="font-bold text-lg mb-4 text-gray-800">Import RTS CSV</h3>
        <form action="/revenue/rts/import" method="POST" enctype="multipart/form-data">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Customer (Optional)</label>
                <select name="import_customer_name" class="w-full border p-2 rounded text-sm bg-gray-50">
                    <option value="">-- Use Name from CSV --</option>
                    <?php foreach($customers as $c): ?>
                        <option value="<?= htmlspecialchars($c['name'] ?? '') ?>">
                            <?= htmlspecialchars($c['name'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-gray-400 mt-1">If selected, this customer is used for ALL imported rows.</p>
            </div>

            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">CSV File</label>
            <input type="file" name="csv_file" accept=".csv" required class="w-full border p-2 mb-4 rounded bg-gray-50 text-sm">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importRts').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Upload</button>
            </div>
        </form>
    </div>
</div>