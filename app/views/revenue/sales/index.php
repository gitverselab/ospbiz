<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Sales Invoices</h2>
    <div class="flex gap-2">
        <button onclick="document.getElementById('spoilModal').classList.remove('hidden')" class="bg-red-100 text-red-700 px-4 py-2 rounded font-medium hover:bg-red-200 border border-red-200">
            <i class="fa-solid fa-ban mr-2"></i> Record Cancelled
        </button>
        <a href="/revenue/sales/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
            <i class="fa-solid fa-plus mr-2"></i> Create Invoice
        </a>
    </div>
</div>

<form method="GET" action="/revenue/sales" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Invoice Number..." class="w-full border p-2 rounded text-sm">
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

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">Status</label>
            <select name="status" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All</option>
                <option value="unpaid" <?= ($filters['status'] == 'unpaid') ? 'selected' : '' ?>>Unpaid</option>
                <option value="paid" <?= ($filters['status'] == 'paid') ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= ($filters['status'] == 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
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

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
        <a href="/revenue/sales" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
    </div>
</form>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Invoice #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Due</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($invoices)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No invoices found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach($invoices as $inv): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-bold font-mono <?= ($inv['status']=='cancelled')?'text-red-400 line-through':'text-blue-600' ?>">
                        <?= htmlspecialchars($inv['invoice_number']) ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= $inv['date'] ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($inv['customer_name']) ?></td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($inv['total_amount_due'], 2) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                            $badge = match($inv['status']) {
                                'paid' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default => 'bg-yellow-100 text-yellow-800'
                            };
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full <?= $badge ?> uppercase font-bold"><?= $inv['status'] ?></span>
                    </td>
                    
                    <td class="px-6 py-4 text-center text-sm">
                        <?php if($inv['status'] == 'unpaid'): ?>
                            <form action="/revenue/sales/cancel" method="POST" onsubmit="return confirm('Are you sure? This will release the DRs attached.')">
                                <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-bold">Cancel</button>
                            </form>
                        <?php elseif($inv['status'] == 'paid'): ?>
                            <span class="text-xs text-gray-400 italic flex justify-center items-center gap-1">
                                <i class="fa-solid fa-lock"></i> Locked
                            </span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Cancelled</span>
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

<div id="spoilModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg w-96 shadow-xl border-t-4 border-red-500">
        <h3 class="font-bold text-lg mb-2 text-gray-800">Record Spoiled Invoice</h3>
        <p class="text-sm text-gray-500 mb-4">Use this when you made a typo on the physical receipt and need to skip a number.</p>
        
        <form action="/revenue/sales/storeSpoiled" method="POST">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Invoice Number</label>
            <input type="text" name="invoice_number" class="w-full border p-2 rounded mb-4 font-mono" placeholder="e.g. 1024" required>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('spoilModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 rounded">Close</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded font-bold">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>