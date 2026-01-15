<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Payment Remittances</h2>
    <a href="/revenue/remittance/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Record Remittance
    </a>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Ref No.</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Deposited To</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Gross Amount</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">WHT (1%)</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Net Received</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach($remittances as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-600"><?= $r['date'] ?></td>
                <td class="px-6 py-4 text-sm font-bold text-gray-800"><?= htmlspecialchars($r['customer_name']) ?></td>
                <td class="px-6 py-4 text-sm font-mono text-blue-600"><?= htmlspecialchars($r['reference_no']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($r['bank_name']) ?></td>
                <td class="px-6 py-4 text-right text-sm text-gray-600">₱<?= number_format($r['total_gross_amount'], 2) ?></td>
                <td class="px-6 py-4 text-right text-sm text-red-500">(₱<?= number_format($r['total_wht_amount'], 2) ?>)</td>
                <td class="px-6 py-4 text-right font-bold text-green-700">₱<?= number_format($r['net_amount_received'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>