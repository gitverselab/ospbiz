<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Purchase Payments</h2>
    <a href="/expenses/payments/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-money-bill-transfer mr-2"></i> New Payment
    </a>
</div>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Paid From</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Reference</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Paid</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($payments as $p): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($p['date'])); ?></td>
                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($p['supplier_name']); ?></td>
                <td class="px-6 py-4 text-sm text-gray-500">
                    <?php echo htmlspecialchars($p['account_name']); ?>
                    <span class="text-xs text-gray-400 block uppercase"><?php echo $p['payment_method']; ?></span>
                </td>
                <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo htmlspecialchars($p['reference_no']); ?></td>
                <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?php echo number_format($p['total_paid'], 2); ?></td>
                <td class="px-6 py-4 text-center text-sm">
                    <a href="#" class="text-blue-600 hover:text-blue-900">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>