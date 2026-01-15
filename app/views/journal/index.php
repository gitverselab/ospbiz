<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Journal History</h2>
    <a href="/journal/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-pen-fancy mr-2"></i> Create Journal Entry
    </a>
</div>

<form method="GET" action="/journal/list" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="JV Number or Description..." class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
    </div>
</form>

<div class="space-y-6">
    <?php if(empty($journals)): ?>
        <div class="bg-white p-8 text-center text-gray-500 italic rounded shadow">No journal entries found.</div>
    <?php else: ?>
        <?php foreach($journals as $j): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-3 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <span class="font-bold text-blue-800 mr-2"><?= $j['reference_no'] ?></span>
                    <span class="text-sm text-gray-600"><?= date('M j, Y', strtotime($j['date'])) ?></span>
                    <span class="mx-2 text-gray-300">|</span>
                    <span class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($j['description']) ?></span>
                </div>
                <div class="text-xs uppercase font-bold text-gray-400"><?= $j['source_module'] ?></div>
            </div>
            
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-400 bg-white border-b">
                    <tr>
                        <th class="px-6 py-2 text-left font-normal">Account</th>
                        <th class="px-6 py-2 text-left font-normal">Description</th>
                        <th class="px-6 py-2 text-right font-normal">Debit</th>
                        <th class="px-6 py-2 text-right font-normal">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php 
                        $totalDr = 0; 
                        $totalCr = 0; 
                    ?>
                    <?php foreach($j['lines'] as $line): 
                        $totalDr += $line['debit'];
                        $totalCr += $line['credit'];
                    ?>
                    <tr>
                        <td class="px-6 py-2 font-mono text-gray-600">
                            <span class="font-bold text-gray-800"><?= $line['code'] ?></span> - <?= $line['account_name'] ?>
                        </td>
                        <td class="px-6 py-2 text-gray-500 italic"><?= htmlspecialchars($line['description']) ?></td>
                        <td class="px-6 py-2 text-right text-gray-800"><?= $line['debit'] > 0 ? number_format($line['debit'], 2) : '' ?></td>
                        <td class="px-6 py-2 text-right text-gray-800"><?= $line['credit'] > 0 ? number_format($line['credit'], 2) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="2"></td>
                        <td class="px-6 py-2 text-right font-bold text-gray-800 border-t border-gray-300">
                            <?= number_format($totalDr, 2) ?>
                        </td>
                        <td class="px-6 py-2 text-right font-bold text-gray-800 border-t border-gray-300">
                            <?= number_format($totalCr, 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="flex justify-between items-center mt-6">
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