<?php
// view_project.php
require_once "includes/header.php";
require_once "config/database.php";

$id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) { echo "Project not found."; exit; }

// --- 1. FETCH PROJECT DATA ---
$expenses = $conn->query("SELECT * FROM expenses WHERE project_id = $id ORDER BY expense_date DESC")->fetch_all(MYSQLI_ASSOC);
$purchases = $conn->query("SELECT * FROM purchases WHERE project_id = $id ORDER BY purchase_date DESC")->fetch_all(MYSQLI_ASSOC);

// --- 2. FETCH DROPDOWNS FOR MODALS ---
$suppliers = $conn->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name")->fetch_all(MYSQLI_ASSOC);
$expense_accounts = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);
$cash_accounts = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);
$items_list = $conn->query("SELECT * FROM items ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);

// --- 3. CALCULATIONS ---
$total_expenses = array_sum(array_column($expenses, 'amount'));
$total_purchases = array_sum(array_column($purchases, 'amount'));
$total_spent = $total_expenses + $total_purchases;
$remaining = $project['budget'] - $total_spent;

// --- 4. TIMELINE ---
$start = strtotime($project['start_date']);
$end = $project['end_date'] ? strtotime($project['end_date']) : time();
$now = time();
$total_days = ($end - $start) / (60 * 60 * 24);
$days_passed = ($now - $start) / (60 * 60 * 24);
$time_percent = ($total_days > 0) ? min(100, max(0, ($days_passed / $total_days) * 100)) : 0;
?>

<style>
    @media print {
        body * { visibility: hidden; }
        #printableArea, #printableArea * { visibility: visible; }
        #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        .shadow-md { box-shadow: none !important; border: 1px solid #ccc; }
    }
</style>

<div id="printableArea">
    <div class="flex flex-col lg:flex-row justify-between items-start mb-6 gap-4">
        <div class="w-full lg:w-auto">
            <div class="flex flex-wrap items-center gap-2 md:gap-3">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($project['project_name']); ?></h2>
                <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-800 text-xs md:text-sm font-bold border border-blue-200"><?php echo $project['status']; ?></span>
                
                <div class="flex gap-2 ml-1 no-print">
                    <button onclick="openEditProjectModal()" class="text-gray-500 hover:text-blue-600" title="Edit Project">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick="deleteProject(<?php echo $id; ?>)" class="text-gray-500 hover:text-red-600" title="Delete Project">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </div>
            </div>
            <p class="text-gray-600 mt-2 text-sm italic">
                <strong>Goal:</strong> <?php echo nl2br(htmlspecialchars($project['description'])); ?>
            </p>
            <p class="text-xs text-gray-500 mt-1">
                Period: <?php echo $project['start_date']; ?> to <?php echo $project['end_date'] ?? 'Ongoing'; ?>
            </p>
        </div>
        
        <div class="flex flex-wrap gap-2 w-full lg:w-auto justify-start lg:justify-end no-print">
            <button onclick="window.print()" class="flex-1 lg:flex-none bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-3 rounded-lg flex items-center justify-center gap-2 text-sm">🖨️ Print</button>
            <a href="export_project_details.php?id=<?php echo $id; ?>" class="flex-1 lg:flex-none bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-3 rounded-lg flex items-center justify-center gap-2 text-sm">📄 Word</a>
            <div class="hidden lg:block h-8 w-px bg-gray-300 mx-1"></div>
            <button onclick="openAddExpenseModal()" class="flex-1 lg:flex-none bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-3 rounded-lg text-sm">+ Expense</button>
            <button onclick="openAddPurchaseModal()" class="flex-1 lg:flex-none bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-3 rounded-lg text-sm">+ Purchase</button>
            <a href="projects.php" class="flex-1 lg:flex-none bg-gray-500 text-white py-2 px-4 rounded-lg font-bold text-center text-sm">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-blue-500">
            <p class="text-xs text-gray-500 uppercase font-bold">Total Budget</p>
            <p class="text-xl md:text-2xl font-bold">₱<?php echo number_format($project['budget'], 2); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-red-500">
            <p class="text-xs text-gray-500 uppercase font-bold">Total Spent</p>
            <p class="text-xl md:text-2xl font-bold text-red-600">₱<?php echo number_format($total_spent, 2); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-500">
            <p class="text-xs text-gray-500 uppercase font-bold">Remaining</p>
            <p class="text-xl md:text-2xl font-bold text-green-600">₱<?php echo number_format($remaining, 2); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-yellow-500">
            <p class="text-xs text-gray-500 uppercase font-bold">Timeline Status</p>
            <div class="mt-2 w-full bg-gray-200 rounded-full h-2.5 print:hidden">
                <div class="bg-yellow-500 h-2.5 rounded-full" style="width: <?php echo $time_percent; ?>%"></div>
            </div>
            <p class="text-xs text-right mt-1"><?php echo number_format($time_percent, 0); ?>% Elapsed</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md border border-gray-200">
            <h3 class="text-lg md:text-xl font-bold mb-4 text-red-800 border-b pb-2">Direct Expenses</h3>
            <div class="overflow-x-auto">
                <div class="overflow-y-auto max-h-96">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-red-50 text-red-900">
                            <tr>
                                <th class="p-2 whitespace-nowrap">Date</th>
                                <th class="p-2 whitespace-nowrap">Description</th>
                                <th class="p-2 text-right whitespace-nowrap">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if(empty($expenses)): ?>
                                <tr><td colspan="3" class="p-4 text-center text-gray-500">No expenses recorded.</td></tr>
                            <?php else: foreach($expenses as $e): ?>
                            <tr>
                                <td class="p-2 whitespace-nowrap"><?php echo $e['expense_date']; ?></td>
                                <td class="p-2 min-w-[200px]"><?php echo htmlspecialchars($e['description']); ?></td>
                                <td class="p-2 text-right font-bold whitespace-nowrap">₱<?php echo number_format($e['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="bg-white p-4 md:p-6 rounded-lg shadow-md border border-gray-200">
            <h3 class="text-lg md:text-xl font-bold mb-4 text-purple-800 border-b pb-2">Purchase Orders</h3>
            <div class="overflow-x-auto">
                <div class="overflow-y-auto max-h-96">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-purple-50 text-purple-900">
                            <tr>
                                <th class="p-2 whitespace-nowrap">PO #</th>
                                <th class="p-2 whitespace-nowrap">Status</th>
                                <th class="p-2 text-right whitespace-nowrap">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php if(empty($purchases)): ?>
                                <tr><td colspan="3" class="p-4 text-center text-gray-500">No POs recorded.</td></tr>
                            <?php else: foreach($purchases as $p): ?>
                            <tr>
                                <td class="p-2 font-mono text-blue-600 font-bold whitespace-nowrap">
                                    <a href="view_purchase.php?id=<?php echo $p['id']; ?>" class="hover:underline"><?php echo $p['po_number']; ?></a>
                                </td>
                                <td class="p-2 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold border bg-gray-100"><?php echo $p['status']; ?></span>
                                </td>
                                <td class="p-2 text-right font-bold whitespace-nowrap">₱<?php echo number_format($p['amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="editProjectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md w-11/12">
        <form id="editProjectForm">
            <h3 class="text-xl font-bold mb-4">Edit Project Details</h3>
            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
            <div class="space-y-3">
                <div><label class="text-sm font-bold">Project Name</label><input type="text" name="project_name" value="<?php echo htmlspecialchars($project['project_name']); ?>" class="w-full border rounded p-2" required></div>
                <div><label class="text-sm font-bold">Budget</label><input type="number" step="0.01" name="budget" value="<?php echo $project['budget']; ?>" class="w-full border rounded p-2" required></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="text-sm font-bold">Start Date</label><input type="date" name="start_date" value="<?php echo $project['start_date']; ?>" class="w-full border rounded p-2" required></div>
                    <div><label class="text-sm font-bold">Target End</label><input type="date" name="end_date" value="<?php echo $project['end_date']; ?>" class="w-full border rounded p-2"></div>
                </div>
                <div><label class="text-sm font-bold">Status</label>
                    <select name="status" class="w-full border rounded p-2">
                        <?php foreach(['Planning','In Progress','On Hold','Completed','Cancelled'] as $s) {
                            $sel = ($project['status'] == $s) ? 'selected' : '';
                            echo "<option value='$s' $sel>$s</option>";
                        } ?>
                    </select>
                </div>
                <div><label class="text-sm font-bold">Description</label><textarea name="description" class="w-full border rounded p-2"><?php echo htmlspecialchars($project['description']); ?></textarea></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="closeModal('editProjectModal')" class="bg-gray-200 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="expenseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg w-11/12 overflow-y-auto max-h-[90vh]">
        <form id="expenseForm">
            <input type="hidden" name="project_id" value="<?php echo $id; ?>">
            <h3 class="text-xl font-bold mb-4">Add Project Expense</h3>
            <div class="space-y-4">
                <div><label class="text-sm font-bold">Date</label><input type="date" name="expense_date" id="expense_date" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                <div><label class="text-sm font-bold">Category</label><select name="chart_of_account_id" id="chart_of_account_id" class="mt-1 block w-full border-gray-300 rounded-md" required>
                    <option value="">Select Category...</option>
                    <?php foreach($expense_accounts as $acc): ?><option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option><?php endforeach; ?>
                </select></div>
                <div><label class="text-sm font-bold">Description</label><input type="text" name="description" id="description" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                
                <div><label class="text-sm font-bold">Pay From</label><select name="payment_method" id="payment_method" class="mt-1 block w-full border-gray-300 rounded-md" onchange="toggleAccountSelect()">
                    <option value="Cash on Hand">Cash on Hand</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Check">Check</option>
                </select></div>
                
                <div id="account_id_container"><label class="text-sm font-bold">Source Account</label><select name="account_id" id="account_id" class="mt-1 block w-full border-gray-300 rounded-md"></select></div>

                <div id="cash_fields" class="hidden p-3 bg-green-50 border border-green-200 rounded-md space-y-3">
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="is_change_pending" id="is_change_pending" class="h-4 w-4 text-green-600" onchange="toggleChangeLogic()">
                        <label for="is_change_pending" class="ml-2 block text-sm text-gray-900 font-bold">Change Pending?</label>
                    </div>
                    <div><label class="text-sm font-semibold">Amount Tendered</label><input type="number" name="amount_tendered" id="amount_tendered" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md" oninput="calculateChange()"></div>
                </div>

                <div>
                    <label id="amount_label" class="text-sm font-bold">Amount</label>
                    <input type="number" name="amount" id="amount" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md" required oninput="calculateChange()">
                </div>
                <div id="change_display" class="hidden text-right text-sm font-bold text-green-600">Change: ₱0.00</div>

                <div id="check_details_div" class="hidden p-3 bg-gray-50 border rounded-md space-y-3">
                    <p class="text-sm font-bold text-gray-700">Check Details</p>
                    <div><label class="text-sm">Check Number</label><input type="text" name="check_number" id="check_number" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                    <div><label class="text-sm">Check Date</label><input type="date" name="check_date" id="check_date" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('expenseModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="purchaseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl w-11/12 max-h-[90vh] overflow-y-auto">
        <form id="purchaseForm">
            <input type="hidden" name="project_id" value="<?php echo $id; ?>">
            <h3 class="text-xl md:text-2xl font-bold mb-4">Add Project Purchase Order</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div><label class="block text-sm font-semibold">Supplier</label>
                    <select name="supplier_id" class="w-full border rounded p-2" required>
                        <option value="">Select Supplier...</option>
                        <?php foreach($suppliers as $s): ?><option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-semibold">PO Number</label><input type="text" name="po_number" class="w-full border rounded p-2" required></div>
                <div><label class="block text-sm font-semibold">Category</label>
                    <select name="chart_of_account_id" class="w-full border rounded p-2">
                        <option value="">None / Default</option>
                        <?php foreach($expense_accounts as $acc): ?><option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block text-sm font-semibold">Date</label><input type="date" name="purchase_date" id="purchase_date" class="w-full border rounded p-2" required></div>
                <div><label class="block text-sm font-semibold">Due Date</label><input type="date" name="due_date" class="w-full border rounded p-2"></div>
                <div><label class="block text-sm font-semibold">Description</label><input type="text" name="description" class="w-full border rounded p-2"></div>
            </div>

            <div class="mb-4">
                <div class="flex justify-between items-center border-b pb-2 mb-2">
                    <h4 class="font-bold text-lg">Items</h4>
                    <button type="button" onclick="addItemRow()" class="text-blue-600 hover:text-blue-800 text-sm font-bold">+ Add Item</button>
                </div>
                <div id="item-lines" class="space-y-2"></div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('purchaseModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-purple-600 text-white py-2 px-4 rounded-lg font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- DATA ---
    const cashAccounts = <?php echo json_encode($cash_accounts); ?>;
    const passbooks = <?php echo json_encode($passbooks); ?>;
    const itemsList = <?php echo json_encode($items_list); ?>;

    // --- MODAL HELPERS ---
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    
    // --- TRIGGER FUNCTIONS ---
    function openEditProjectModal() { openModal('editProjectModal'); }
    
    function openAddExpenseModal() {
        document.getElementById('expenseForm').reset();
        document.getElementById('expense_date').value = new Date().toISOString().slice(0, 10);
        toggleAccountSelect();
        toggleChangeLogic();
        openModal('expenseModal');
    }

    function openAddPurchaseModal() {
        document.getElementById('purchaseForm').reset();
        document.getElementById('purchase_date').value = new Date().toISOString().slice(0, 10);
        document.getElementById('item-lines').innerHTML = '';
        addItemRow(); 
        openModal('purchaseModal');
    }

    // --- PROJECT EDITING ---
    document.getElementById('editProjectForm').addEventListener('submit', function(e){
        e.preventDefault();
        fetch('api/update_project.php', { method: 'POST', body: new FormData(this) })
        .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.message); });
    });

    function deleteProject(id) {
        if(confirm('Are you sure? This cannot be undone.')) {
            let fd = new FormData(); fd.append('id', id);
            fetch('api/delete_project.php', { method: 'POST', body: fd })
            .then(r=>r.json()).then(d=>{ if(d.success) window.location.href = 'projects.php'; else alert(d.message); });
        }
    }

    // --- EXPENSE LOGIC ---
    function toggleAccountSelect() {
        const method = document.getElementById('payment_method').value;
        const accountSelect = document.getElementById('account_id');
        const checkDiv = document.getElementById('check_details_div');
        const cashDiv = document.getElementById('cash_fields');
        
        accountSelect.innerHTML = '';
        let sourceData = (method === 'Cash on Hand') ? cashAccounts : passbooks;
        
        if (method === 'Cash on Hand') {
            checkDiv.classList.add('hidden');
            cashDiv.classList.remove('hidden');
        } else {
            cashDiv.classList.add('hidden');
            document.getElementById('is_change_pending').checked = false; 
            toggleChangeLogic();
            if (method === 'Check') {
                checkDiv.classList.remove('hidden');
                if(!document.getElementById('check_date').value) document.getElementById('check_date').value = new Date().toISOString().slice(0, 10);
            } else {
                checkDiv.classList.add('hidden');
            }
        }
        
        sourceData.forEach(acc => {
            const text = acc.account_name || `${acc.bank_name} (${acc.account_number})`;
            accountSelect.add(new Option(text, acc.id));
        });
    }

    function toggleChangeLogic() {
        const isPending = document.getElementById('is_change_pending').checked;
        const actualInput = document.getElementById('amount');
        if (isPending) {
            document.getElementById('amount_label').innerText = "Actual Expense (Enter 0 if unknown)";
            actualInput.required = false;
            document.getElementById('change_display').classList.add('hidden');
        } else {
            document.getElementById('amount_label').innerText = "Amount";
            actualInput.required = true;
            document.getElementById('change_display').classList.remove('hidden');
        }
    }

    function calculateChange() {
        const tendered = parseFloat(document.getElementById('amount_tendered').value) || 0;
        const actual = parseFloat(document.getElementById('amount').value) || 0;
        if(tendered > 0 && actual > 0) {
            document.getElementById('change_display').innerText = "Change: ₱" + (tendered - actual).toFixed(2);
        }
    }

    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/add_expense.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if(data.success) location.reload(); else alert('Error: ' + data.message);
        });
    });

    // --- PURCHASE LOGIC ---
    function addItemRow() {
        const div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-2 items-center border p-2 rounded bg-gray-50';
        
        let options = '<option value="">Select Item</option>';
        itemsList.forEach(i => {
            const price = i.cost_price || i.price || i.unit_price || 0;
            options += `<option value="${i.id}" data-price="${price}">${i.item_name}</option>`;
        });

        div.innerHTML = `
            <div class="col-span-5">
                <select name="items[item_id][]" class="w-full border rounded p-1 text-sm" required onchange="updatePrice(this)">${options}</select>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[quantity][]" placeholder="Qty" class="w-full border rounded p-1 text-sm" step="any" required>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[unit_price][]" placeholder="Price" class="w-full border rounded p-1 text-sm price-input" step="any" required>
            </div>
            <div class="col-span-1 text-center">
                <button type="button" onclick="this.closest('.grid').remove()" class="text-red-500 hover:text-red-700 font-bold">X</button>
            </div>
        `;
        document.getElementById('item-lines').appendChild(div);
    }

    function updatePrice(select) {
        const price = select.options[select.selectedIndex].dataset.price;
        const row = select.closest('.grid');
        const priceInput = row.querySelector('.price-input');
        if(price && priceInput.value === '') priceInput.value = price;
    }

    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/add_purchase.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if(data.success) location.reload(); else alert('Error: ' + data.message);
        });
    });

    // Init
    toggleAccountSelect();
</script>

<?php require_once "includes/footer.php"; ?>