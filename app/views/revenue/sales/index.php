<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Sales Invoices</h2>
    <a href="/revenue/sales/create" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Create Invoice
    </a>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Invoice #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Vatable</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">VAT</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Due</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach($invoices as $inv): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-bold text-blue-600"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $inv['date'] ?></td>
                <td class="px-6 py-4 text-sm"><?= htmlspecialchars($inv['customer_name']) ?></td>
                <td class="px-6 py-4 text-right text-sm">₱<?= number_format($inv['vatable_sales'], 2) ?></td>
                <td class="px-6 py-4 text-right text-sm">₱<?= number_format($inv['vat_amount'], 2) ?></td>
                <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($inv['total_amount_due'], 2) ?></td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600 uppercase font-bold"><?= $inv['status'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>