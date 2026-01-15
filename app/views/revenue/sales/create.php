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
                    <input type="text" name="invoice_number" value="<?= $suggestedInv ?>" class="w-full border p-2 rounded font-mono font-bold text-blue-600" placeholder="e.g. 1024" required>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded">
                </div>
                
                <input type="hidden" name="tin" value="<?= $settings['company_tin'] ?? '' ?>">
                <input type="hidden" name="address" value="<?= $settings['company_address'] ?? '' ?>">
                <input type="hidden" name="business_style" value="<?= $settings['business_style'] ?? '' ?>">
                <input type="text" name="terms" class="hidden" value="30 Days">

                <div class="bg-gray-50 p-4 rounded border-t-4 border-blue-500">
                    <h3 class="font-bold text-gray-700 mb-3 text-sm uppercase">Payment Computation</h3>
                    
                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-gray-500">Total Invoice (VAT Inc):</span>
                        <span class="font-bold text-gray-800" id="grossDisplay">0.00</span>
                    </div>

                    <div class="flex justify-between items-center mb-2 text-sm bg-yellow-50 p-2 rounded">
                        <div class="flex flex-col">
                            <span class="text-gray-600 font-bold">Less: WHT (1%)</span>
                            <span class="text-[10px] text-gray-400 italic">(Calculated on Net of VAT)</span>
                        </div>
                        <input type="number" step="0.01" name="wht_amount" id="whtInput" 
                               class="w-24 text-right border p-1 rounded text-red-600 font-bold bg-white focus:ring-2 focus:ring-red-300 outline-none" 
                               value="0.00" oninput="manualWht()">
                    </div>

                    <div class="border-t border-gray-300 my-2"></div>

                    <div class="flex justify-between text-lg">
                        <span class="font-bold text-gray-700">Net Receivable:</span>
                        <span class="font-bold text-blue-600" id="netDisplay">₱0.00</span>
                    </div>
                </div>

                <button type="submit" class="mt-4 w-full bg-blue-600 text-white py-3 rounded font-bold hover:bg-blue-700 shadow">
                    Generate Invoice
                </button>

                <div id="selectedDrContainer"></div>

                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="w-full md:w-2/3">
        <div class="bg-white p-6 rounded shadow border h-full">
            <h2 class="text-lg font-bold mb-4 text-gray-800">2. Select Delivery Receipts (DR)</h2>

            <?php if(!isset($_GET['customer'])): ?>
                <div class="text-center text-gray-400 py-10">Select a customer first.</div>
            <?php elseif(empty($openDrs)): ?>
                <div class="text-center text-gray-500 py-10 bg-gray-50 rounded border border-dashed">No uninvoiced DRs found.</div>
            <?php else: ?>
                
                <input type="text" id="drSearch" onkeyup="filterDrs()" placeholder="Search DR Number..." class="w-full border p-2 rounded text-sm mb-4">

                <div class="overflow-y-auto max-h-[600px] border rounded">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 uppercase text-xs">
                            <tr>
                                <th class="p-3 w-10">Select</th>
                                <th class="p-3">DR Number</th>
                                <th class="p-3">Date</th>
                                <th class="p-3">PO Number</th>
                                <th class="p-3 text-right">Amount (Inc VAT)</th>
                            </tr>
                        </thead>
                        <tbody id="drTableBody">
                            <?php foreach($openDrs as $dr): 
                                // Normalize amount for display
                                $amount = $dr['grand_total']; 
                                if($dr['is_vat_inc'] == 0) { $amount = $amount * 1.12; } 
                            ?>
                            <tr class="border-b hover:bg-blue-50 cursor-pointer" onclick="toggleCheckbox('check_<?= $dr['id'] ?>')">
                                <td class="p-3 text-center">
                                    <input type="checkbox" 
                                           class="dr-checkbox w-4 h-4 text-blue-600" 
                                           id="check_<?= $dr['id'] ?>" 
                                           value="<?= $dr['id'] ?>" 
                                           data-amount="<?= $amount ?>"
                                           onclick="event.stopPropagation(); updateSelection();">
                                </td>
                                <td class="p-3 font-bold text-gray-700 dr-num"><?= $dr['dr_number'] ?></td>
                                <td class="p-3"><?= $dr['date'] ?></td>
                                <td class="p-3 text-gray-500"><?= $dr['po_number'] ?></td>
                                <td class="p-3 text-right font-bold">₱<?= number_format($amount, 2) ?></td>
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
let currentGross = 0;

function toggleCheckbox(id) {
    const cb = document.getElementById(id);
    cb.checked = !cb.checked;
    updateSelection();
}

function updateSelection() {
    currentGross = 0;
    let count = 0;
    const container = document.getElementById('selectedDrContainer');
    container.innerHTML = ''; 

    document.querySelectorAll('.dr-checkbox:checked').forEach(cb => {
        currentGross += parseFloat(cb.dataset.amount);
        count++;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'dr_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    document.getElementById('grossDisplay').innerText = currentGross.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('countSelected').innerText = count;

    calculateWht();
}

function calculateWht() {
    // 1. Get Net of VAT (Vatable Sales)
    // Formula: Gross / 1.12
    const vatableSales = currentGross / 1.12;

    // 2. Calculate 1% WHT on Vatable Sales
    const whtVal = vatableSales * 0.01;
    
    // Update Input
    const whtInput = document.getElementById('whtInput');
    whtInput.value = whtVal.toFixed(2);
    
    updateNet();
}

function manualWht() {
    updateNet();
}

function updateNet() {
    const whtVal = parseFloat(document.getElementById('whtInput').value) || 0;
    const netVal = currentGross - whtVal;
    
    document.getElementById('netDisplay').innerText = '₱' + netVal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

function filterDrs() {
    const term = document.getElementById('drSearch').value.toLowerCase();
    document.querySelectorAll('#drTableBody tr').forEach(row => {
        const txt = row.innerText.toLowerCase();
        row.style.display = txt.includes(term) ? '' : 'none';
    });
}
</script>