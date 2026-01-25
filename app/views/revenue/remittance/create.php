<div class="flex flex-col md:flex-row gap-6">
    
    <div class="w-full md:w-1/3">
        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-4 text-gray-800">1. Payment Details</h2>
            
            <form id="remittanceForm" action="/revenue/remittance/store" method="POST">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Customer</label>
                    <select name="customer_name" class="w-full border p-2 rounded bg-yellow-50" onchange="window.location.href='/revenue/remittance/create?customer='+this.value">
                        <option value="">-- Select Customer --</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?= htmlspecialchars($c['customer_name']) ?>" <?= (isset($_GET['customer']) && $_GET['customer'] == $c['customer_name']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['customer_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if(isset($_GET['customer'])): ?>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payment Date</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
                </div>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference No.</label>
                    <input type="text" name="reference_no" class="w-full border p-2 rounded" placeholder="e.g. Check #12345" required>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Deposit To</label>
                    <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                        <option value="">-- Select Account --</option>
                        <?php foreach($banks as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bg-gray-50 p-4 rounded border-t-4 border-green-500">
                    <h3 class="font-bold text-gray-700 mb-3 text-sm uppercase">Payment Calculation</h3>
                    
                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-gray-500">Total Invoices:</span>
                        <span class="font-bold text-gray-800" id="grossDisplay">0.00</span>
                    </div>

                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-gray-500">Less: WHT (1%)</span>
                        <span class="font-bold text-red-500" id="whtDisplay">(0.00)</span>
                    </div>

                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-gray-500">Less: Returns (RTS)</span>
                        <span class="font-bold text-red-500" id="rtsDisplay">(0.00)</span>
                    </div>

                    <div class="border-t border-gray-300 my-2"></div>

                    <div class="flex justify-between text-lg">
                        <span class="font-bold text-gray-700">Net Received:</span>
                        <span class="font-bold text-green-700" id="netDisplay">₱0.00</span>
                    </div>
                </div>

                <button type="submit" class="mt-4 w-full bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700 shadow">
                    Record Payment
                </button>

                <div id="selectedInvContainer"></div>
                <div id="selectedRtsContainer"></div>

                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="w-full md:w-2/3 flex flex-col gap-6">
        
        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-4 text-blue-800">2. Select Invoices (Add)</h2>
            <?php if(!isset($_GET['customer'])): ?>
                <div class="text-gray-400">Select customer first.</div>
            <?php elseif(empty($openInvoices)): ?>
                <div class="text-gray-500 italic">No unpaid invoices.</div>
            <?php else: ?>
                <div class="overflow-y-auto max-h-[300px] border rounded">
                    <table class="w-full text-sm">
                        <thead class="bg-blue-50 text-xs uppercase">
                            <tr>
                                <th class="p-2 text-center">✔</th>
                                <th class="p-2">Invoice #</th>
                                <th class="p-2">Date</th>
                                <th class="p-2 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($openInvoices as $inv): ?>
                            <tr class="border-b hover:bg-blue-50 cursor-pointer" onclick="toggleInv('inv_<?= $inv['id'] ?>')">
                                <td class="p-2 text-center">
                                    <input type="checkbox" class="inv-cb" id="inv_<?= $inv['id'] ?>" 
                                           value="<?= $inv['id'] ?>" 
                                           data-gross="<?= $inv['total_amount_due'] ?>"
                                           data-vatable="<?= $inv['vatable_sales'] ?>"
                                           onclick="event.stopPropagation(); updateCalc();">
                                </td>
                                <td class="p-2 font-mono font-bold text-blue-600"><?= $inv['invoice_number'] ?></td>
                                <td class="p-2"><?= $inv['date'] ?></td>
                                <td class="p-2 text-right">₱<?= number_format($inv['total_amount_due'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded shadow border">
            <h2 class="text-lg font-bold mb-4 text-red-800">3. Select Returns (Deduct)</h2>
            <?php if(!isset($_GET['customer'])): ?>
                <div class="text-gray-400">Select customer first.</div>
            <?php elseif(empty($openRts)): ?>
                <div class="text-gray-500 italic">No approved returns available.</div>
            <?php else: ?>
                <div class="overflow-y-auto max-h-[300px] border rounded">
                    <table class="w-full text-sm">
                        <thead class="bg-red-50 text-xs uppercase">
                            <tr>
                                <th class="p-2 text-center">✔</th>
                                <th class="p-2">RD #</th>
                                <th class="p-2">Date</th>
                                <th class="p-2 text-right">Deduction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($openRts as $r): 
                                $amt = $r['total_amount'];
                                if($r['is_vat_inc'] == 0) $amt *= 1.12; 
                            ?>
                            <tr class="border-b hover:bg-red-50 cursor-pointer" onclick="toggleRts('rts_<?= $r['id'] ?>')">
                                <td class="p-2 text-center">
                                    <input type="checkbox" class="rts-cb" id="rts_<?= $r['id'] ?>" 
                                           value="<?= $r['id'] ?>" 
                                           data-amount="<?= $amt ?>"
                                           onclick="event.stopPropagation(); updateCalc();">
                                </td>
                                <td class="p-2 font-mono font-bold text-red-600"><?= $r['rd_number'] ?></td>
                                <td class="p-2"><?= $r['date'] ?></td>
                                <td class="p-2 text-right">₱<?= number_format($amt, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function toggleInv(id) {
    const cb = document.getElementById(id);
    cb.checked = !cb.checked;
    updateCalc();
}
function toggleRts(id) {
    const cb = document.getElementById(id);
    cb.checked = !cb.checked;
    updateCalc();
}

function updateCalc() {
    let totalGross = 0;
    let totalVatable = 0;
    let totalRts = 0;

    const invContainer = document.getElementById('selectedInvContainer');
    const rtsContainer = document.getElementById('selectedRtsContainer');
    invContainer.innerHTML = '';
    rtsContainer.innerHTML = '';

    // Sum Invoices
    document.querySelectorAll('.inv-cb:checked').forEach(cb => {
        totalGross += parseFloat(cb.dataset.gross);
        totalVatable += parseFloat(cb.dataset.vatable);
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoice_ids[]';
        input.value = cb.value;
        invContainer.appendChild(input);
    });

    // Sum RTS
    document.querySelectorAll('.rts-cb:checked').forEach(cb => {
        totalRts += parseFloat(cb.dataset.amount);
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'rts_ids[]';
        input.value = cb.value;
        rtsContainer.appendChild(input);
    });

    // Calculate
    const wht = totalVatable * 0.01;
    const net = totalGross - wht - totalRts;

    // Display
    document.getElementById('grossDisplay').innerText = totalGross.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('whtDisplay').innerText = '(' + wht.toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
    document.getElementById('rtsDisplay').innerText = '(' + totalRts.toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
    
    const netEl = document.getElementById('netDisplay');
    netEl.innerText = '₱' + net.toLocaleString('en-US', {minimumFractionDigits: 2});
    
    if (net < 0) {
        netEl.classList.remove('text-green-700');
        netEl.classList.add('text-red-600');
    } else {
        netEl.classList.add('text-green-700');
        netEl.classList.remove('text-red-600');
    }
}
</script>