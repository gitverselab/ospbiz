<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Sales Invoices</h2>
    <div class="flex gap-2">
        <button onclick="document.getElementById('spoilModal').classList.remove('hidden')" class="bg-red-100 text-red-700 px-4 py-2 rounded font-medium hover:bg-red-200 border border-red-200">
            <i class="fa-solid fa-ban mr-2"></i> Record Cancelled/Spoiled
        </button>
        
        <a href="/revenue/sales/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
            <i class="fa-solid fa-plus mr-2"></i> Record Invoice
        </a>
    </div>
</div>

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
            <?php foreach($invoices as $inv): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold font-mono <?= ($inv['status']=='cancelled')?'text-red-400 line-through':'text-blue-600' ?>">
                    <?= htmlspecialchars($inv['invoice_number']) ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $inv['date'] ?></td>
                <td class="px-6 py-4 text-sm"><?= htmlspecialchars($inv['customer_name']) ?></td>
                <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($inv['total_amount_due'], 2) ?></td>
                <td class="px-6 py-4 text-center">
                    <?php if($inv['status'] == 'cancelled'): ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-600 font-bold">CANCELLED</span>
                    <?php else: ?>
                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600 font-bold"><?= strtoupper($inv['status']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-center text-sm">
                    <?php if($inv['status'] != 'cancelled'): ?>
                    <form action="/revenue/sales/cancel" method="POST" onsubmit="return confirm('Are you sure? This will release the DRs attached.')">
                        <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Cancel Invoice</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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