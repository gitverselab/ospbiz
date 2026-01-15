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
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference No. (e.g. Check #, Doc No.)</label>
                    <input type="text" name="reference_no" class="w-full border p-2 rounded" placeholder="e.g. 200187284">
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Deposit To (Bank/Cash)</label>
                    <select name="financial_account_id" class="w-full border p-2 rounded bg-white" required>
                        <option value="">-- Select Account --</option>
                        <?php foreach($banks as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="bg-gray-50 p-4 rounded border-t-4 border-green-500">
                    <h3 class="font-bold text-gray-700 mb-3 text-sm uppercase">Payment Summary</h3>
                    
                    <div class="flex justify-between mb-2 text-sm">
                        <span class="text-gray-500">Total Gross (Invoice Amt):</span>
                        <span class="font-bold text-gray-800" id="grossDisplay">0.00</span>
                    </div>

                    <div class="flex justify-between mb-2 text-sm">
                        <div class="flex flex-col">
                            <span class="text-gray-500">Less: WHT (1%)</span>
                            <span class="text-[10px] text-gray-400 italic">(on Vatable Amount)</span>
                        </div>
                        <span class="font-bold text-red-500" id="whtDisplay">(0.00)</span>
                    </div>

                    <div class="border-t border-gray-300 my-2"></div>

                    <div class="flex justify-between text-lg">
                        <span class="font-bold text-gray-700">Net Amount Paid:</span>
                        <span class="font-bold text-green-700" id="netDisplay">₱0.00</span>
                    </div>
                </div>

                <button type="submit" class="mt-4 w-full bg-green-600 text-white py-3 rounded font-bold hover:bg-green-700 shadow">
                    Record Payment
                </button>

                <div id="selectedInvContainer"></div>

                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="w-full md:w-2/3">
        <div class="bg-white p-6 rounded shadow border h-full">
            <h2 class="text-lg font-bold mb-4 text-gray-800">2. Select Invoices to Pay</h2>

            <?php if(!isset($_GET['customer'])): ?>
                <div class="text-center text-gray-400 py-10">Select a customer first.</div>
            <?php elseif(empty($openInvoices)): ?>
                <div class="text-center text-gray-500 py-10 bg-gray-50 rounded border border-dashed">No unpaid invoices found for this customer.</div>
            <?php else: ?>
                
                <div class="overflow-y-auto max-h-[600px] border rounded">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 uppercase text-xs">
                            <tr>
                                <th class="p-3 w-10 text-center">Select</th>
                                <th class="p-3">Invoice No.</th>
                                <th class="p-3">Date</th>
                                <th class="p-3 text-right">Gross Amount</th>
                                <th class="p-3 text-right text-gray-500">Vatable Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($openInvoices as $inv): ?>
                            <tr class="border-b hover:bg-green-50 cursor-pointer" onclick="toggleCheckbox('check_<?= $inv['id'] ?>')">
                                <td class="p-3 text-center">
                                    <input type="checkbox" 
                                           class="inv-checkbox w-4 h-4 text-green-600" 
                                           id="check_<?= $inv['id'] ?>" 
                                           value="<?= $inv['id'] ?>" 
                                           data-gross="<?= $inv['total_amount_due'] ?>"
                                           data-vatable="<?= $inv['vatable_sales'] ?>"
                                           onclick="event.stopPropagation(); updateSelection();">
                                </td>
                                <td class="p-3 font-bold text-blue-600 font-mono"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                                <td class="p-3"><?= $inv['date'] ?></td>
                                <td class="p-3 text-right font-bold">₱<?= number_format($inv['total_amount_due'], 2) ?></td>
                                <td class="p-3 text-right text-gray-500">₱<?= number_format($inv['vatable_sales'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 text-right text-sm text-gray-500">
                    Selected: <span id="countSelected" class="font-bold text-gray-800">0</span> Invoices
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
    let totalGross = 0;
    let totalVatable = 0;
    let count = 0;
    const container = document.getElementById('selectedInvContainer');
    container.innerHTML = ''; 

    document.querySelectorAll('.inv-checkbox:checked').forEach(cb => {
        totalGross += parseFloat(cb.dataset.gross);
        totalVatable += parseFloat(cb.dataset.vatable);
        count++;

        // Create hidden input for form
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'invoice_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    // Calculate WHT (1% of Vatable) and Net
    const wht = totalVatable * 0.01;
    const net = totalGross - wht;

    // Update Displays
    document.getElementById('grossDisplay').innerText = totalGross.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('whtDisplay').innerText = '(' + wht.toLocaleString('en-US', {minimumFractionDigits: 2}) + ')';
    document.getElementById('netDisplay').innerText = '₱' + net.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('countSelected').innerText = count;
}
</script>