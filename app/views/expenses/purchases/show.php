<div class="max-w-5xl mx-auto">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="/expenses/purchases" class="text-sm text-gray-500 hover:text-gray-700 mb-1 inline-block">&larr; Back to List</a>
            <h2 class="text-2xl font-bold text-gray-800">PO #<?= htmlspecialchars($po['po_number']) ?></h2>
        </div>
        <div class="flex gap-2">
            <?php if($po['status'] !== 'paid'): ?>
                <a href="/expenses/purchases/edit?id=<?= $po['id'] ?>" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded shadow-sm hover:bg-gray-50">
                    <i class="fa-solid fa-pencil mr-2"></i> Edit PO
                </a>
                <a href="/expenses/payments/create?supplier_id=<?= $po['supplier_id'] ?>" class="bg-green-600 text-white px-4 py-2 rounded shadow hover:bg-green-700">
                    <i class="fa-solid fa-money-bill mr-2"></i> Make Payment
                </a>
            <?php else: ?>
                <span class="bg-green-100 text-green-800 px-4 py-2 rounded font-bold border border-green-200">
                    <i class="fa-solid fa-check mr-2"></i> Fully Paid
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 md:col-span-2">
            <div class="flex justify-between mb-4 border-b pb-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Supplier</p>
                    <p class="text-lg font-bold text-gray-800"><?= htmlspecialchars($po['supplier_name']) ?></p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($po['address'] ?? '') ?></p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-gray-500 uppercase font-bold">Date</p>
                    <p class="text-sm text-gray-800 mb-2"><?= date('F j, Y', strtotime($po['date'])) ?></p>
                    
                    <p class="text-xs text-gray-500 uppercase font-bold">Status</p>
                    <span class="inline-block px-2 py-1 text-xs font-bold rounded bg-gray-100 text-gray-600 uppercase">
                        <?= $po['status'] ?>
                    </span>
                </div>
            </div>

            <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase">Order Items</h3>
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="text-left p-2 text-gray-600">Description</th>
                        <th class="text-right p-2 text-gray-600 w-24">Qty</th>
                        <th class="text-right p-2 text-gray-600 w-32">Unit Price</th>
                        <th class="text-right p-2 text-gray-600 w-32">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lines as $line): ?>
                    <tr class="border-b last:border-0">
                        <td class="p-2"><?= htmlspecialchars($line['description']) ?></td>
                        <td class="p-2 text-right"><?= number_format($line['quantity'], 2) ?></td>
                        <td class="p-2 text-right"><?= number_format($line['unit_price'], 2) ?></td>
                        <td class="p-2 text-right font-medium">₱<?= number_format($line['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="3" class="p-3 text-right font-bold text-gray-700">Total Amount:</td>
                        <td class="p-3 text-right font-bold text-xl text-blue-700">₱<?= number_format($po['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 h-fit">
            <h3 class="text-sm font-bold text-gray-700 mb-4 uppercase border-b pb-2">Payment History</h3>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Total Order:</span>
                    <span class="font-bold">₱<?= number_format($po['total_amount'], 2) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">Amount Paid:</span>
                    <span class="font-bold text-green-600">₱<?= number_format($po['amount_paid'], 2) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm border-t pt-2">
                    <span class="text-gray-700 font-bold">Balance Due:</span>
                    <?php $bal = $po['total_amount'] - $po['amount_paid']; ?>
                    <span class="font-bold text-lg <?= $bal > 0 ? 'text-red-600' : 'text-gray-400' ?>">
                        ₱<?= number_format($bal, 2) ?>
                    </span>
                </div>
            </div>

            <div class="mt-6">
                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Transactions</h4>
                <?php if(empty($payments)): ?>
                    <p class="text-sm text-gray-400 italic">No payments recorded yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach($payments as $pay): ?>
                        <div class="bg-gray-50 p-3 rounded border border-gray-100">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="font-bold text-gray-700"><?= $pay['reference_no'] ?></span>
                                <span class="text-gray-500"><?= $pay['date'] ?></span>
                            </div>
                            <div class="flex justify-between items-end">
                                <span class="text-xs text-gray-500 bg-white border px-1 rounded"><?= ucfirst($pay['payment_method']) ?></span>
                                <span class="font-bold text-green-700 text-sm">₱<?= number_format($pay['amount_applied'], 2) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>