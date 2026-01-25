<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Purchase Payment</h2>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Select Supplier</label>
        <select onchange="window.location.href='/expenses/payments/create?supplier_id='+this.value" class="w-full md:w-1/3 border p-2 rounded bg-white">
            <option value="">-- Choose Supplier --</option>
            <?php foreach($suppliers as $s): ?>
                <option value="<?= $s['id'] ?>" <?= (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="text-xs text-gray-400 mt-1">Loads unpaid POs automatically.</div>
    </div>

    <?php if(isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])): ?>
    <form action="/expenses/payments/store" method="POST">
        <input type="hidden" name="supplier_id" value="<?= $_GET['supplier_id'] ?>">
        
        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pay From Account</label>
                <select name="financial_account_id" class="w-full border p-2 rounded text-sm bg-white" required>
                    <?php foreach($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>">
                            <?= htmlspecialchars($acc['name']) ?> (Bal: ₱<?= number_format($acc['current_balance'], 2) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Method</label>
                <select name="payment_method" id="paymentMethod" class="w-full border p-2 rounded text-sm bg-white" onchange="updateRefPlaceholder()">
                    <option value="check">Check</option>
                    <option value="transfer">Bank Transfer</option>
                    <option value="cash">Cash</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1" id="refLabel">Check Number</label>
                <input type="text" name="reference_no" id="refInput" class="w-full border p-2 rounded text-sm" placeholder="Check Number" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded text-sm">
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
            <h3 class="font-bold text-gray-800 mb-4">Select Invoices to Pay</h3>
            
            <?php if(empty($openPOs)): ?>
                <div class="text-center py-8 text-gray-400 italic">No unpaid POs found.</div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">PO #</th>
                            <th class="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Total</th>
                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Balance Due</th>
                            <th class="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase w-40">Amount to Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($openPOs as $po): 
                            $balance = $po['total_amount'] - $po['amount_paid'];
                        ?>
                        <tr>
                            <td class="px-4 py-3 text-sm font-mono text-blue-600"><?= htmlspecialchars($po['po_number']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= date('M j, Y', strtotime($po['date'])) ?></td>
                            <td class="px-4 py-3 text-right text-sm text-gray-500">₱<?= number_format($po['total_amount'], 2) ?></td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-red-600">₱<?= number_format($balance, 2) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <input type="checkbox" class="h-4 w-4 text-blue-600 cursor-pointer" onchange="toggleRow(this, <?= $balance ?>)">
                                    <input type="number" step="0.01" max="<?= $balance ?>" 
                                           class="pay-input w-28 border border-gray-300 rounded p-1 text-right text-sm disabled:bg-gray-100 disabled:text-gray-400" 
                                           data-id="<?= $po['id'] ?>" 
                                           placeholder="0.00" 
                                           disabled
                                           oninput="calcTotal()">
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="flex justify-end items-center bg-gray-50 p-4 rounded-lg border border-gray-200 gap-6 sticky bottom-0">
            <div class="text-right">
                <div class="text-xs font-bold text-gray-500 uppercase">Total Payment</div>
                <div class="text-2xl font-bold text-green-600" id="grandTotalDisplay">₱0.00</div>
                <input type="hidden" name="total_paid" id="grandTotalInput" value="0">
                <input type="hidden" name="allocations_json" id="allocationsInput">
            </div>
            <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-8 py-3 rounded shadow hover:bg-blue-700 font-bold">
                Confirm Payment
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
function updateRefPlaceholder() {
    const method = document.getElementById('paymentMethod').value;
    const label = document.getElementById('refLabel');
    const input = document.getElementById('refInput');

    if (method === 'check') {
        label.innerText = 'Check Number';
        input.placeholder = 'e.g. 100523';
    } else {
        label.innerText = 'Reference No.';
        input.placeholder = 'Ref / Transaction ID';
    }
}

// Initial Call
updateRefPlaceholder();

function toggleRow(checkbox, maxAmount) {
    const input = checkbox.nextElementSibling;
    if (checkbox.checked) {
        input.disabled = false;
        input.value = maxAmount.toFixed(2);
        input.classList.add('bg-yellow-50', 'font-bold');
    } else {
        input.disabled = true;
        input.value = '';
        input.classList.remove('bg-yellow-50', 'font-bold');
    }
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.pay-input:not([disabled])').forEach(el => {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('grandTotalDisplay').innerText = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('grandTotalInput').value = total.toFixed(2);
}

function prepareSubmit(e) {
    const allocs = [];
    let total = 0;
    
    document.querySelectorAll('.pay-input:not([disabled])').forEach(el => {
        const val = parseFloat(el.value) || 0;
        if(val > 0) {
            allocs.push({ po_id: el.dataset.id, amount: val });
            total += val;
        }
    });

    if (total <= 0) {
        alert("Please select at least one PO to pay.");
        e.preventDefault();
        return;
    }

    document.getElementById('allocationsInput').value = JSON.stringify(allocs);
}
</script>