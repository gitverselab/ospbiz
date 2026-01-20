<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Daily Expenses</h2>
    <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 font-medium">
        <i class="fa-solid fa-plus mr-2"></i> Add Expense
    </button>
</div>

<form method="GET" action="/expenses/daily" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Description..." class="w-full border p-2 rounded text-sm">
        </div>

        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">Category</label>
            <select name="category" class="w-full border p-2 rounded text-sm bg-white">
                <option value="">All Categories</option>
                <?php foreach($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo (($filters['category'] ?? '') == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($filters['from'] ?? ''); ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-32">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($filters['to'] ?? ''); ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-24">
            <label class="text-xs font-bold text-gray-500 uppercase">Show</label>
            <select name="limit" class="w-full border p-2 rounded text-sm bg-white">
                <option value="10" <?php echo ($filters['limit'] == 10) ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo ($filters['limit'] == 25) ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo ($filters['limit'] == 50) ? 'selected' : ''; ?>>50</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/daily" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>
</form>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Description</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Category</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($expenses)): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500 italic">No expenses found matching your criteria.</td></tr>
            <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                    <?php 
                        $rowClass = "hover:bg-gray-50"; 
                        if ($e['is_voided']) {
                            $rowClass = "bg-gray-100 text-gray-400 line-through"; 
                        } elseif (empty($e['verified_at'])) {
                            $rowClass = "bg-yellow-50"; 
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="px-6 py-4 text-sm"><?php echo $e['date']; ?></td>
                        <td class="px-6 py-4 text-sm font-medium">
                            <?php echo htmlspecialchars($e['description']); ?>
                            
                            <div class="text-xs opacity-75">
                                <?php echo htmlspecialchars($e['source_account'] ?? 'Pending Check'); ?>
                            </div>
                            
                            <?php if($e['is_voided']): ?>
                                <span class="text-red-500 font-bold text-xs no-underline ml-2">VOIDED</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm opacity-75"><?php echo htmlspecialchars($e['category_name'] ?? '-'); ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($e['is_voided']): ?>
                                <span class="px-2 py-1 bg-gray-200 text-gray-500 text-xs font-bold rounded-full">Voided</span>
                            <?php else: ?>
                                <div class="flex flex-col gap-1 items-center">
                                    <?php if (empty($e['verified_at'])): ?>
                                        <span class="px-2 py-1 bg-yellow-200 text-yellow-800 text-xs font-bold rounded-full animate-pulse">To Verify</span>
                                    <?php endif; ?>
                                    <?php if ($e['is_pending_change']): ?>
                                        <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs font-bold rounded-full">Pending Change</span>
                                    <?php elseif (!empty($e['verified_at'])): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Verified Paid</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right font-bold">₱<?php echo number_format($e['amount'], 2); ?></td>
                        <td class="px-6 py-4 text-center text-sm">
                            <?php if (!$e['is_voided']): ?>
                                <div class="flex justify-center gap-2 items-center">
                                    <?php if (empty($e['verified_at'])): ?>
                                        <form action="/expenses/daily/verify" method="POST" title="Approve">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-800 bg-white border border-green-200 px-2 py-1 rounded shadow-sm"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($e['is_pending_change']): ?>
                                        <button onclick='openSettleModal(<?php echo json_encode($e); ?>)' class="bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700 shadow-sm whitespace-nowrap">Settle</button>
                                    <?php endif; ?>
                                    <button onclick='openVoidModal(<?= json_encode($e) ?>)' class="text-red-400 hover:text-red-600 px-2 py-1" title="Void"><i class="fa-solid fa-ban"></i></button>
                                </div>
                            <?php else: ?>
                                <span class="text-xs italic text-gray-400">No Actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">Showing Page <?php echo $filters['page']; ?> of <?php echo $filters['total_pages']; ?> (Total <?php echo $filters['total_records']; ?> records)</div>
    <div class="flex gap-2">
        <?php $params = $_GET; unset($params['page']); $baseUrl = '?' . http_build_query($params) . '&page='; ?>
        <?php if ($filters['page'] > 1): ?>
            <a href="<?php echo $baseUrl . ($filters['page'] - 1); ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Previous</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Previous</span>
        <?php endif; ?>
        <?php for($i = 1; $i <= $filters['total_pages']; $i++): ?>
            <a href="<?php echo $baseUrl . $i; ?>" class="px-3 py-1 border rounded text-sm <?php echo ($i == $filters['page']) ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?>
            <a href="<?php echo $baseUrl . ($filters['page'] + 1); ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Next</a>
        <?php else: ?>
            <span class="px-3 py-1 bg-gray-100 border rounded text-gray-400 text-sm cursor-not-allowed">Next</span>
        <?php endif; ?>
    </div>
</div>

<div id="expenseModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-bold text-xl text-gray-800">Add New Expense</h3>
        </div>
        
        <form action="/expenses/daily/store" method="POST" class="p-6 space-y-4">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                    <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" class="w-full border p-2 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pay From</label>
                    <select id="payFromType" name="payment_source_type" class="w-full border p-2 rounded text-sm bg-white" onchange="updateFormUI()">
                        <option value="cash">Cash on Hand</option>
                        <option value="bank">Bank Account</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Source Account</label>
                <select name="financial_account_id" id="sourceAccountSelect" class="w-full border p-2 rounded text-sm bg-white" required>
                    </select>
            </div>

            <div id="bankOptions" class="hidden bg-blue-50 p-3 rounded border border-blue-100 space-y-3">
                <div class="flex items-center gap-4">
                    <label class="flex items-center text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="radio" name="payment_method" value="transfer" class="mr-2" checked onchange="toggleCheckInputs()">
                        Online Transfer / Debit
                    </label>
                    <label class="flex items-center text-sm font-medium text-gray-700 cursor-pointer">
                        <input type="radio" name="payment_method" value="check" class="mr-2" onchange="toggleCheckInputs()">
                        Issue Check
                    </label>
                </div>
                <div id="checkInputs" class="hidden grid grid-cols-2 gap-3 mt-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Check Number</label>
                        <input type="text" name="check_number" id="inputCheckNum" class="w-full border p-2 rounded text-sm font-mono" placeholder="e.g. 000123">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payee Name</label>
                        <input type="text" name="payee_name" id="inputPayee" class="w-full border p-2 rounded text-sm" placeholder="Name on check">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category</label>
                <select name="category_id" class="w-full border p-2 rounded text-sm bg-white" required>
                    <option value="">Select Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo $cat['code'] . ' - ' . $cat['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
                <input type="text" name="description" class="w-full border p-2 rounded text-sm" placeholder="Purpose of expense" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                <input type="number" step="0.01" name="amount" id="mainAmount" class="w-full border-gray-300 rounded shadow-sm border p-2 text-xl font-bold" placeholder="0.00" required>
            </div>

            <div id="calculatorContainer" class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
                <div class="flex items-center mb-2">
                    <input type="checkbox" name="is_pending_change" id="toggleChange" value="1" class="mr-2 h-4 w-4 text-blue-600 cursor-pointer" onchange="toggleCalculator()">
                    <label for="toggleChange" class="text-xs font-bold text-gray-700 cursor-pointer select-none uppercase">Calculate Change (Tendered vs Actual)</label>
                </div>
                
                <div id="tenderedSection" class="hidden mt-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Amount Given / Tendered</label>
                    <input type="number" step="0.01" name="tendered_amount" id="tenderedAmount" oninput="calculateChange()" class="w-full border p-2 rounded text-sm" placeholder="0.00">
                    <p class="text-[10px] text-gray-500 mt-1">We will record the tendered amount as the expense for now.</p>
                </div>
                
                <div class="text-right mt-2 text-sm font-bold text-green-600" id="changeDisplay"></div>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('expenseModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-bold">Save Expense</button>
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
<div id="voidModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm">
        <div class="px-6 py-4 border-b border-red-100 bg-red-50 rounded-t-lg">
            <h3 class="font-bold text-lg text-red-800">Void Transaction</h3>
        </div>
        
        <form action="/expenses/daily/void" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="voidId">
            
            <p class="text-sm text-gray-600">
                Are you sure you want to void the expense for 
                <span class="font-bold text-gray-800" id="voidDesc"></span>?
            </p>
            
            <div class="bg-yellow-50 border border-yellow-200 p-3 rounded text-xs text-yellow-800">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                This will reverse the Journal Entry and restore the cash balance.
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reason for Voiding</label>
                <input type="text" name="void_reason" class="w-full border p-2 rounded text-sm" placeholder="e.g. Wrong amount encoded" required>
            </div>

            <div class="pt-2 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('voidModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded hover:bg-red-700 font-bold">Confirm Void</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVoidModal(data) {
    document.getElementById('voidModal').classList.remove('hidden');
    document.getElementById('voidId').value = data.id;
    document.getElementById('voidDesc').innerText = data.description + " (₱" + data.amount + ")";
}
</script>
<script>
// Pass PHP data to JS
const allAccounts = <?php echo json_encode($allFinancialAccounts); ?>;

function openModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
    updateFormUI(); 
}

function updateFormUI() {
    const type = document.getElementById('payFromType').value;
    const select = document.getElementById('sourceAccountSelect');
    const calc = document.getElementById('calculatorContainer');
    const bankOpts = document.getElementById('bankOptions');

    select.innerHTML = "";
    const filtered = allAccounts.filter(acc => acc.type === type);
    filtered.forEach(acc => {
        const option = document.createElement("option");
        option.value = acc.id;
        option.text = acc.name + " (Bal: ₱" + parseFloat(acc.current_balance).toLocaleString() + ")";
        select.appendChild(option);
    });

    if (type === 'bank') {
        calc.style.display = 'none'; // Hide Calculator
        bankOpts.classList.remove('hidden'); // Show Check Options
        document.getElementById('toggleChange').checked = false; 
    } else {
        calc.style.display = 'block'; // Show Calculator
        bankOpts.classList.add('hidden'); // Hide Check Options
        toggleCheckInputs();
    }
}

function toggleCheckInputs() {
    const method = document.querySelector('input[name="payment_method"]:checked')?.value;
    const inputs = document.getElementById('checkInputs');
    const checkNum = document.getElementById('inputCheckNum');
    const payee = document.getElementById('inputPayee');
    const payFromType = document.getElementById('payFromType').value;

    if (payFromType === 'bank' && method === 'check') {
        inputs.classList.remove('hidden');
        checkNum.required = true;
        payee.required = true;
    } else {
        inputs.classList.add('hidden');
        checkNum.required = false;
        payee.required = false;
        checkNum.value = '';
        payee.value = '';
    }
}

function toggleCalculator() {
    const isChecked = document.getElementById('toggleChange').checked;
    const tenderedSec = document.getElementById('tenderedSection');
    if (isChecked) {
        tenderedSec.classList.remove('hidden');
    } else {
        tenderedSec.classList.add('hidden');
        document.getElementById('tenderedAmount').value = '';
    }
}

function calculateChange() {
    const tendered = parseFloat(document.getElementById('tenderedAmount').value) || 0;
    const actual = parseFloat(document.getElementById('mainAmount').value) || 0;
    if (tendered > 0) {
        let change = tendered - actual;
        document.getElementById('changeDisplay').innerText = "Change: ₱" + change.toLocaleString('en-US', {minimumFractionDigits: 2});
    } else {
        document.getElementById('changeDisplay').innerText = "";
    }
}

function openSettleModal(data) {
    document.getElementById('settleModal').classList.remove('hidden');
    document.getElementById('settleId').value = data.id;
    document.getElementById('settleTenderedDisplay').innerText = '₱' + parseFloat(data.tendered_amount).toLocaleString('en-US');
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