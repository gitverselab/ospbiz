<div class="flex flex-col md:flex-row gap-6">
    
    <div class="w-full md:w-1/3">
        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-4 text-gray-800">1. Invoice Details</h2>
            
            <form id="invoiceForm" action="/revenue/sales/store" method="POST">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Select Customer</label>
                    <select name="customer_name" class="w-full border p-2 rounded bg-yellow-50" onchange="window.location.href='/revenue/sales/create?customer='+this.value">
                        <option value="">-- Choose Customer --</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_name']) ?>" <?= (isset($_GET['customer']) && $_GET['customer'] == $c['customer_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if(isset($_GET['customer'])): ?>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Invoice Number</label>
                    <input type="text" name="invoice_number" class="w-full border p-2 rounded" placeholder="e.g. SI-0001" required>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Customer TIN</label>
                    <input type="text" name="tin" class="w-full border p-2 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Business Style</label>
                    <input type="text" name="business_style" class="w-full border p-2 rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Address</label>
                    <textarea name="address" class="w-full border p-2 rounded" rows="2"></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Terms</label>
                    <input type="text" name="terms" class="w-full border p-2 rounded" placeholder="e.g. 30 Days">
                </div>

                <div class="bg-gray-100 p-4 rounded text-right">
                    <div class="text-xs text-gray-500">Total Amount Due</div>
                    <div class="text-2xl font-bold text-blue-600" id="grandTotal">₱0.00</div>
                    <button type="submit" class="mt-4 w-full bg-blue-600 text-white py-2 rounded font-bold hover:bg-blue-700">Generate Invoice</button>
                </div>

                <div id="selectedDrContainer"></div>

                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="w-full md:w-2/3">
        <div class="bg-white p-6 rounded shadow border h-full">
            <h2 class="text-lg font-bold mb-4 text-gray-800">2. Select Delivery Receipts (DR)</h2>

            <?php if(!isset($_GET['customer'])): ?>
                <div class="text-center text-gray-400 py-10">
                    <i class="fa-solid fa-arrow-left mb-2"></i><br>
                    Please select a customer first to see their uninvoiced DRs.
                </div>
            <?php elseif(empty($openDrs)): ?>
                <div class="text-center text-gray-500 py-10 bg-gray-50 rounded border border-dashed">
                    No uninvoiced DRs found for this customer.
                </div>
            <?php else: ?>
                
                <div class="mb-4">
                    <input type="text" id="drSearch" onkeyup="filterDrs()" placeholder="Search DR Number..." class="w-full border p-2 rounded text-sm">
                </div>

                <div class="overflow-y-auto max-h-[600px] border rounded">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 uppercase text-xs">
                            <tr>
                                <th class="p-3 w-10">Select</th>
                                <th class="p-3">DR Number</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">PO Number</th>
                                <th class="p-3 text-right">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody id="drTableBody">
                            <?php foreach($openDrs as $dr): ?>
                            <tr class="border-b hover:bg-blue-50 cursor-pointer" onclick="toggleCheckbox('check_<?= $dr['id'] ?>')">
                                <td class="p-3 text-center">
                                    <input type="checkbox" 
                                           class="dr-checkbox w-4 h-4 text-blue-600" 
                                           id="check_<?= $dr['id'] ?>" 
                                           value="<?= $dr['id'] ?>" 
                                           data-amount="<?= $dr['grand_total'] ?>"
                                           onclick="event.stopPropagation(); updateSelection();">
                                </td>
                                <td class="p-3 font-bold text-gray-700 dr-num"><?= $dr['dr_number'] ?></td>
                                <td class="p-3"><?= $dr['date'] ?></td>
                                <td class="p-3 text-gray-500"><?= $dr['po_number'] ?></td>
                                <td class="p-3 text-right font-bold">₱<?= number_format($dr['grand_total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-right text-sm text-gray-500">
                    Selected: <span id="countSelected" class="font-bold text-gray-800">0</span> DRs
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleCheckbox(id) {
    const cb = document.getElementById(id);
    cb.checked = !cb.checked;
    updateSelection();
}

function updateSelection() {
    let total = 0;
    let count = 0;
    const container = document.getElementById('selectedDrContainer');
    container.innerHTML = ''; // Clear previous

    document.querySelectorAll('.dr-checkbox:checked').forEach(cb => {
        total += parseFloat(cb.dataset.amount);
        count++;

        // Create hidden input for form submission
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'dr_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    document.getElementById('grandTotal').innerText = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('countSelected').innerText = count;
}

function filterDrs() {
    const term = document.getElementById('drSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#drTableBody tr');
    
    rows.forEach(row => {
        const drNum = row.querySelector('.dr-num').innerText.toLowerCase();
        if (drNum.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>