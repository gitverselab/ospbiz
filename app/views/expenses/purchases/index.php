<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Purchase Orders</h2>
    <a href="/expenses/purchases/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Create PO
    </a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">PO Number</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($pos)): ?>
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No Purchase Orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($pos as $po): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-bold text-blue-600 font-mono"><?php echo htmlspecialchars($po['po_number']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($po['date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            <?php echo ($po['status'] == 'paid') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo ucfirst($po['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?php echo number_format($po['total_amount'], 2); ?></td>
                    <td class="px-6 py-4 text-center text-sm">
                        <a href="#" class="text-blue-600 hover:text-blue-900">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>