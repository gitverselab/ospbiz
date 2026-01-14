<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mt-6">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount Out</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($expenses as $e): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-900"><?php echo $e['date']; ?></td>
                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                    <?php echo htmlspecialchars($e['description']); ?>
                    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($e['source_account']); ?></div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($e['category_name'] ?? '-'); ?></td>
                
                <td class="px-6 py-4 text-center">
                    <?php if ($e['is_pending_change']): ?>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full animate-pulse">
                            Pending Change
                        </span>
                    <?php else: ?>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                            Paid
                        </span>
                    <?php endif; ?>
                </td>

                <td class="px-6 py-4 text-right font-bold text-gray-800">
                    ₱<?php echo number_format($e['amount'], 2); ?>
                </td>
                
                <td class="px-6 py-4 text-center text-sm">
                    <?php if ($e['is_pending_change']): ?>
                        <button onclick='openSettleModal(<?php echo json_encode($e); ?>)' class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 shadow-sm mr-2">
                            <i class="fa-solid fa-hand-holding-dollar mr-1"></i> Settle Change
                        </button>
                    <?php else: ?>
                        <a href="#" class="text-green-600 hover:text-green-900 mr-2">Edit</a>
                        <a href="#" class="text-red-600 hover:text-red-900">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="expenseModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-bold text-xl text-gray-800">Add New Expense</h3>
        </div>
        
        <form action="/expenses/daily/create" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" class="w-full border p-2 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pay From</label>
                    <select name="financial_account_id" class="w-full border p-2 rounded text-sm bg-white">
                        <?php foreach($cashAccounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                <select name="category_id" class="w-full border p-2 rounded text-sm bg-white">
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
                <input type="text" name="description" class="w-full border p-2 rounded text-sm" placeholder="Payee Name / Purpose" required>
            </div>

            <div class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                <div class="flex items-center mb-4">
                    <input type="checkbox" name="is_pending_change" id="toggleChange" value="1" class="mr-2 h-4 w-4 text-blue-600 cursor-pointer" onchange="toggleCalculator()">
                    <label for="toggleChange" class="text-sm font-bold text-gray-700 cursor-pointer select-none">Change will be given later?</label>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Amount Given / Tendered</label>
                    <input type="number" step="0.01" name="tendered_amount" id="tenderedAmount" oninput="calculateChange()" class="w-full border-gray-300 rounded shadow-sm border p-2 text-lg font-bold" placeholder="0.00">
                    <div class="text-xs text-orange-600 mt-1 hidden" id="tenderedNote">* This amount will be deducted temporarily.</div>
                </div>

                <div id="actualSection" class="mt-3">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Actual Expense Cost</label>
                    <input type="number" step="0.01" name="actual_amount" id="actualAmount" oninput="calculateChange()" class="w-full border-gray-300 rounded shadow-sm border p-2 text-lg font-bold" placeholder="0.00">
                </div>
                
                <div class="text-right mt-2 text-sm font-bold text-green-600" id="changeDisplay"></div>
            </div>

            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('expenseModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="settleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
        <div class="px-6 py-4 border-b border-gray-200 bg-blue-50 rounded-t-lg">
            <h3 class="font-bold text-lg text-blue-900">Settle Transaction</h3>
        </div>
        
        <form action="/expenses/daily/settle" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="settleId">
            
            <div class="bg-gray-100 p-3 rounded text-sm text-gray-600 mb-4">
                We originally released <span class="font-bold text-gray-900" id="settleTenderedDisplay">₱0.00</span>. 
                Please enter the actual expense from the receipt.
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Final Actual Expense</label>
                <input type="number" step="0.01" name="final_actual_amount" id="settleActual" oninput="calcReturn()" class="w-full border-gray-300 rounded shadow-sm border p-2 text-xl font-bold text-gray-800" required>
            </div>

            <div class="text-right">
                <div class="text-xs text-gray-500 uppercase">Change to Return</div>
                <div class="text-2xl font-bold text-green-600" id="settleReturn">₱0.00</div>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('settleModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold">Confirm Settle</button>
            </div>
        </form>
    </div>
</div>

<script>
// Open Create Modal
function openModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
}

// UI Logic for Create Modal
function toggleCalculator() {
    const isChecked = document.getElementById('toggleChange').checked;
    const actualSection = document.getElementById('actualSection');
    const tenderedNote = document.getElementById('tenderedNote');
    
    if (isChecked) {
        // If checked: User only knows tendered amount. Actual is unknown or irrelevant for now.
        actualSection.classList.add('hidden'); 
        tenderedNote.classList.remove('hidden');
        document.getElementById('actualAmount').value = 0;
    } else {
        actualSection.classList.remove('hidden');
        tenderedNote.classList.add('hidden');
    }
    calculateChange();
}

function calculateChange() {
    const tendered = parseFloat(document.getElementById('tenderedAmount').value) || 0;
    const actual = parseFloat(document.getElementById('actualAmount').value) || 0;
    const isChecked = document.getElementById('toggleChange').checked;
    
    if (!isChecked && tendered > 0) {
        let change = tendered - actual;
        document.getElementById('changeDisplay').innerText = "Change: ₱" + change.toLocaleString('en-US', {minimumFractionDigits: 2});
    } else {
        document.getElementById('changeDisplay').innerText = "";
    }
}

// Open Settle Modal
function openSettleModal(data) {
    document.getElementById('settleModal').classList.remove('hidden');
    document.getElementById('settleId').value = data.id;
    document.getElementById('settleTenderedDisplay').innerText = '₱' + parseFloat(data.tendered_amount).toLocaleString('en-US');
    // Store tendered in a dataset for calc
    document.getElementById('settleActual').dataset.tendered = data.tendered_amount;
    document.getElementById('settleActual').value = '';
    document.getElementById('settleReturn').innerText = '₱0.00';
}

function calcReturn() {
    const tendered = parseFloat(document.getElementById('settleActual').dataset.tendered) || 0;
    const actual = parseFloat(document.getElementById('settleActual').value) || 0;
    const change = tendered - actual;
    document.getElementById('settleReturn').innerText = '₱' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
}
</script>