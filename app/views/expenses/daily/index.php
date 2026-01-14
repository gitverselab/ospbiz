<form method="GET" action="/expenses/daily" class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <div class="flex-1 w-full bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-col md:flex-row gap-4 items-end">
        <div class="w-full">
            <label class="text-xs font-bold text-gray-500 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Description or Ref..." class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-gray-500 uppercase">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>" class="w-full border p-2 rounded text-sm">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
            <a href="/expenses/daily" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium hover:bg-gray-300 flex items-center">Reset</a>
        </div>
    </div>

    <div class="flex gap-2 shrink-0">
        <button type="button" onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 text-sm font-bold">
            <i class="fa-solid fa-plus mr-2"></i> Add New Expense
        </button>
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
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No expenses found matching your criteria.</td></tr>
            <?php else: ?>
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
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full animate-pulse">Pending Change</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">Paid</span>
                        <?php endif; ?>
                    </td>

                    <td class="px-6 py-4 text-right font-bold text-gray-800">
                        ₱<?php echo number_format($e['amount'], 2); ?>
                    </td>
                    
                    <td class="px-6 py-4 text-center text-sm">
                        <?php if ($e['is_pending_change']): ?>
                            <button onclick='openSettleModal(<?php echo json_encode($e); ?>)' class="bg-blue-600 text-white px-3 py-1 rounded text-xs hover:bg-blue-700 shadow-sm">Settle Change</button>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total <?php echo $totalRecords; ?>)
    </div>
    <div class="flex gap-2">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo $_GET['search']??''; ?>&from=<?php echo $_GET['from']??''; ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Previous</a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo $_GET['search']??''; ?>&from=<?php echo $_GET['from']??''; ?>" class="px-3 py-1 bg-white border rounded hover:bg-gray-50 text-sm">Next</a>
        <?php endif; ?>
    </div>
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
                    <select id="payFromType" class="w-full border p-2 rounded text-sm bg-white" onchange="filterAccounts()">
                        <option value="cash">Cash on Hand</option>
                        <option value="bank">Bank / Check</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Source Account</label>
                <select name="financial_account_id" id="sourceAccountSelect" class="w-full border p-2 rounded text-sm bg-white" required>
                    </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Category (Chart of Accounts)</label>
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
                <input type="text" name="description" class="w-full border p-2 rounded text-sm" placeholder="Payee Name / Purpose" required>
            </div>

            <div id="calculatorContainer" class="p-4 bg-gray-50 border border-gray-200 rounded-md">
                <div class="flex items-center mb-4">
                    <input type="checkbox" name="is_pending_change" id="toggleChange" value="1" class="mr-2 h-4 w-4 text-blue-600 cursor-pointer" onchange="toggleCalculator()">
                    <label for="toggleChange" class="text-sm font-bold text-gray-700 cursor-pointer select-none">Change will be given later?</label>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Amount Given / Tendered</label>
                    <input type="number" step="0.01" name="tendered_amount" id="tenderedAmount" oninput="calculateChange()" class="w-full border-gray-300 rounded shadow-sm border p-2 text-lg font-bold" placeholder="0.00">
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

<script>
// Pass PHP data to JS
const allAccounts = <?php echo json_encode($allFinancialAccounts); ?>;

function openModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
    filterAccounts(); // Initialize dropdown
}

function filterAccounts() {
    const type = document.getElementById('payFromType').value; // 'cash' or 'bank'
    const select = document.getElementById('sourceAccountSelect');
    const calc = document.getElementById('calculatorContainer');

    // 1. Filter Dropdown Options
    select.innerHTML = "";
    const filtered = allAccounts.filter(acc => acc.type === type);
    
    filtered.forEach(acc => {
        const option = document.createElement("option");
        option.value = acc.id;
        option.text = acc.name + " (Bal: ₱" + parseFloat(acc.current_balance).toLocaleString() + ")";
        select.appendChild(option);
    });

    // 2. Hide "Change Calculator" if Bank is selected (Banks don't give loose change)
    if (type === 'bank') {
        calc.style.display = 'none';
        // Reset calculator values to prevent errors
        document.getElementById('toggleChange').checked = false;
        document.getElementById('tenderedAmount').value = '';
    } else {
        calc.style.display = 'block';
    }
}
function toggleCalculator(){ 
    const isChecked = document.getElementById('toggleChange').checked;
    document.getElementById('actualSection').classList.toggle('hidden', isChecked);
    calculateChange();
}
function calculateChange(){
    const tendered = parseFloat(document.getElementById('tenderedAmount').value)||0;
    const actual = parseFloat(document.getElementById('actualAmount').value)||0;
    if(!document.getElementById('toggleChange').checked && tendered > 0) {
        document.getElementById('changeDisplay').innerText = "Change: ₱" + (tendered - actual).toFixed(2);
    } else { document.getElementById('changeDisplay').innerText = ""; }
}
function openSettleModal(data){
    document.getElementById('settleModal').classList.remove('hidden');
    document.getElementById('settleId').value = data.id;
    document.getElementById('settleTenderedDisplay').innerText = '₱'+parseFloat(data.tendered_amount).toLocaleString();
    document.getElementById('settleActual').dataset.tendered = data.tendered_amount;
}
function calcReturn(){
    const tendered = parseFloat(document.getElementById('settleActual').dataset.tendered)||0;
    const actual = parseFloat(document.getElementById('settleActual').value)||0;
    document.getElementById('settleReturn').innerText = '₱'+(tendered - actual).toFixed(2);
}
</script>