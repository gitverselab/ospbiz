<?php
// expenses.php
require_once "includes/header.php";
require_once "config/database.php";

// --- 1. INITIALIZE VARIABLES & PAGINATION ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default 20
$offset = ($page - 1) * $limit;

// Filter Variables
$search_desc = $_GET['search_desc'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_categories = isset($_GET['categories']) ? $_GET['categories'] : []; // Array of IDs

// --- 2. BUILD DYNAMIC QUERY ---
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_desc)) {
    $where_clauses[] = "e.description LIKE ?";
    $params[] = "%$search_desc%";
    $types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "e.expense_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "e.expense_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}
// Multi-Select Category Logic
if (!empty($filter_categories)) {
    // Sanitize array to integers to prevent SQL injection
    $cat_ids = array_map('intval', $filter_categories);
    $in_list = implode(',', $cat_ids);
    $where_clauses[] = "e.chart_of_account_id IN ($in_list)";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// --- 3. COUNT & FETCH DATA ---
$count_sql = "SELECT COUNT(e.id) as total FROM expenses e $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// Fetch Data with LIMIT
$sql = "SELECT e.*, coa.account_name, c.status as check_status, c.check_number, c.check_date 
        FROM expenses e 
        JOIN chart_of_accounts coa ON e.chart_of_account_id = coa.id
        LEFT JOIN checks c ON e.transaction_id = c.id AND e.payment_method = 'Check'
        $where_sql
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT ?, ?";
        
$types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- MODAL DATA ---
$expense_accounts = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);
$cash_accounts = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<style>
    @media print {
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        table { font-size: 12px; }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">Daily Expenses</h2>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex items-center">
            Print Report
        </button>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Add New Expense</button>
    </div>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4 no-print" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="expenses.php" class="grid grid-cols-1 lg:grid-cols-5 gap-4 items-start">
        
        <div class="lg:col-span-1">
            <label class="text-xs font-bold text-gray-700 mb-1 block">Filter Categories</label>
            <div class="border rounded-md h-24 overflow-y-auto p-2 bg-gray-50 text-sm">
                <?php foreach ($expense_accounts as $acc): ?>
                    <label class="flex items-center space-x-2 mb-1 cursor-pointer">
                        <input type="checkbox" name="categories[]" value="<?php echo $acc['id']; ?>" 
                            <?php echo in_array($acc['id'], $filter_categories) ? 'checked' : ''; ?> 
                            class="rounded text-blue-600 focus:ring-blue-500">
                        <span><?php echo htmlspecialchars($acc['account_name']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1">Select multiple</p>
        </div>

        <div>
            <label class="text-xs font-bold text-gray-700 mb-1 block">Description</label>
            <input type="text" name="search_desc" value="<?php echo htmlspecialchars($search_desc); ?>" class="w-full border rounded p-2 text-sm" placeholder="Search description...">
        </div>

        <div>
            <label class="text-xs font-bold text-gray-700 mb-1 block">Date From</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>

        <div>
            <label class="text-xs font-bold text-gray-700 mb-1 block">Date To</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>

        <div class="flex flex-col gap-2">
            <div>
                <label class="text-xs font-bold text-gray-700 mb-1 block">Show Rows</label>
                <select name="limit" class="w-full border rounded p-2 text-sm" onchange="this.form.submit()">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20 Rows</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 Rows</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 Rows</option>
                </select>
            </div>
            <div class="flex gap-1">
                <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded text-sm w-full font-bold">Filter</button>
                <a href="expenses.php" class="bg-gray-300 text-gray-800 px-3 py-2 rounded text-sm w-full font-bold text-center">Reset</a>
            </div>
        </div>
    </form>
</div>

<div id="printable-area">
    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <h3 class="text-2xl font-bold mb-4 hidden print:block">Expense Report</h3>
        <table class="w-full table-auto text-sm">
            <thead>
                <tr class="bg-gray-50 border-b">
                    <th class="p-3 text-left">Date</th>
                    <th class="p-3 text-left">Description</th>
                    <th class="p-3 text-left">Category</th>
                    <th class="p-3 text-left">Payment Method</th>
                    <th class="p-3 text-center">Status</th>
                    <th class="p-3 text-right">Amount</th>
                    <th class="p-3 text-center no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($expenses)): foreach($expenses as $exp): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 whitespace-nowrap"><?php echo htmlspecialchars($exp['expense_date']); ?></td>
                    <td class="p-3 font-medium"><?php echo htmlspecialchars($exp['description']); ?></td>
                    <td class="p-3 text-gray-600"><?php echo htmlspecialchars($exp['account_name']); ?></td>
                    <td class="p-3">
                        <?php echo htmlspecialchars($exp['payment_method']); ?>
                        <?php if($exp['payment_method'] == 'Check') echo " <span class='text-xs text-gray-500 block'>#{$exp['check_number']}</span>"; ?>
                    </td>
                    
                    <td class="p-3 text-center">
                        <?php 
                        if ($exp['is_change_pending'] == 1) {
                            echo '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending Change</span>';
                        } elseif ($exp['payment_method'] === 'Check') {
                            if ($exp['check_status'] === 'Cleared') echo '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>';
                            elseif ($exp['check_status'] === 'Issued') echo '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Pending</span>';
                            else echo '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">' . htmlspecialchars($exp['check_status'] ?? 'Unknown') . '</span>';
                        } else {
                            echo '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Paid</span>';
                        }
                        ?>
                    </td>

                    <td class="p-3 text-right font-bold">
                        ₱<?php echo number_format($exp['amount'], 2); ?>
                        <?php if($exp['amount_tendered'] > $exp['amount'] && $exp['is_change_pending'] == 0): ?>
                            <br><span class="text-xs text-gray-400 font-normal">Given: ₱<?php echo number_format($exp['amount_tendered'], 2); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-center space-x-2 no-print">
                        <?php if ($exp['is_change_pending'] == 1): ?>
                            <button onclick='openSettleModal(<?php echo json_encode($exp); ?>)' class="text-purple-600 hover:underline font-bold">Settle Change</button>
                        <?php else: ?>
                            <button onclick='openEditModal(<?php echo json_encode($exp); ?>)' class="text-green-500 hover:underline">Edit</button>
                        <?php endif; ?>
                        <button onclick="deleteExpense(<?php echo $exp['id']; ?>)" class="text-red-500 hover:underline">Delete</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7" class="p-4 text-center text-gray-500">No expenses found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 flex justify-between items-center no-print">
    <span class="text-sm text-gray-700">
        Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Results
    </span>
    
    <div class="flex gap-1">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white font-medium">« First</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white font-medium">‹ Prev</a>
        <?php endif; ?>

        <?php 
        $range = 2; // Pages to show around current
        $start_num = max(1, $page - $range);
        $end_num = min($total_pages, $page + $range);

        if ($start_num > 1) { echo '<span class="px-2 py-1 text-gray-500">...</span>'; }

        for ($i = $start_num; $i <= $end_num; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
               class="px-3 py-1 border rounded <?php echo $i == $page ? 'bg-blue-600 text-white font-bold' : 'bg-white hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; 

        if ($end_num < $total_pages) { echo '<span class="px-2 py-1 text-gray-500">...</span>'; }
        ?>

        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white font-medium">Next ›</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white font-medium">Last »</a>
        <?php endif; ?>
    </div>
</div>

<div id="expenseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg overflow-y-auto max-h-screen">
        <form id="expenseForm">
            <input type="hidden" name="id" id="expense_id">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Expense</h3>
            <div class="space-y-4">
                <div><label>Date</label><input type="date" name="expense_date" id="expense_date" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                <div><label>Category</label><select name="chart_of_account_id" id="chart_of_account_id" class="mt-1 block w-full border-gray-300 rounded-md" required>
                    <option value="">Select Category...</option>
                    <?php foreach($expense_accounts as $acc): ?><option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option><?php endforeach; ?>
                </select></div>
                <div><label>Description (Payee/Purpose)</label><input type="text" name="description" id="description" class="mt-1 block w-full border-gray-300 rounded-md" required></div>
                
                <div><label>Pay From</label><select name="payment_method" id="payment_method" class="mt-1 block w-full border-gray-300 rounded-md" onchange="toggleAccountSelect()">
                    <option value="Cash on Hand">Cash on Hand</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Check">Check</option>
                </select></div>
                
                <div id="account_id_container"><label id="account_id_label">Source Account</label><select name="account_id" id="account_id" class="mt-1 block w-full border-gray-300 rounded-md"></select></div>

                <div id="cash_fields" class="hidden p-3 bg-green-50 border border-green-200 rounded-md space-y-3">
                    <div class="flex items-center mb-2">
                        <input type="checkbox" name="is_change_pending" id="is_change_pending" class="h-4 w-4 text-green-600" onchange="toggleChangeLogic()">
                        <label for="is_change_pending" class="ml-2 block text-sm text-gray-900 font-bold">Change will be given later?</label>
                    </div>
                    
                    <div>
                        <label class="text-sm font-semibold">Amount Given / Tendered</label>
                        <input type="number" name="amount_tendered" id="amount_tendered" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md" oninput="calculateChange()">
                    </div>
                </div>

                <div>
                    <label id="amount_label">Actual Expense Amount</label>
                    <input type="number" name="amount" id="amount" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md" required oninput="calculateChange()">
                </div>
                
                <div id="change_display" class="hidden text-right text-sm font-bold text-green-600">Change: ₱0.00</div>

                <div id="check_details_div" class="hidden p-3 bg-gray-50 border rounded-md space-y-3">
                    <p class="text-sm font-bold text-gray-700">Check Details</p>
                    <div><label class="text-sm">Check Number</label><input type="text" name="check_number" id="check_number" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                    <div><label class="text-sm">Check Date</label><input type="date" name="check_date" id="check_date" class="mt-1 block w-full border-gray-300 rounded-md"></div>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('expenseModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<div id="settleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
        <form id="settleForm">
            <input type="hidden" name="id" id="settle_id">
            <h3 class="text-xl font-bold mb-4 text-purple-700">Settle Change</h3>
            <p class="mb-4 text-gray-600 text-sm">You gave: <span id="settle_given" class="font-bold">₱0.00</span></p>
            
            <div class="mb-4">
                <label class="block font-semibold mb-1">Actual Receipt Total</label>
                <input type="number" name="actual_amount" id="settle_actual" step="0.01" class="w-full border-gray-300 rounded-md p-2 border" required>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('settleModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-purple-600 text-white py-2 px-4 rounded-lg">Update & Return Change</button>
            </div>
        </form>
    </div>
</div>

<script>
    const cashAccounts = <?php echo json_encode($cash_accounts); ?>;
    const passbooks = <?php echo json_encode($passbooks); ?>;
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function handleApiResponse(response) {
        if (response.success) {
            alertMessage.textContent = response.message || 'Success!';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
            alertBox.classList.remove('hidden');
            setTimeout(() => location.reload(), 1000);
        } else {
            alertMessage.textContent = response.message || 'An error occurred.';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
            alertBox.classList.remove('hidden');
        }
        closeModal('expenseModal');
        closeModal('settleModal');
    }

    // --- Helper for Button Locking ---
    function lockButton(form) {
        const btn = form.querySelector('button[type="submit"]');
        if(btn) { 
            btn.dataset.originalText = btn.innerText; 
            btn.disabled = true; 
            btn.innerText = "Saving..."; 
        }
        return btn;
    }
    function unlockButton(btn) {
        if(btn) { 
            btn.disabled = false; 
            btn.innerText = btn.dataset.originalText; 
        }
    }

    function toggleAccountSelect() {
        const method = document.getElementById('payment_method').value;
        const accountSelect = document.getElementById('account_id');
        const checkDiv = document.getElementById('check_details_div');
        const cashDiv = document.getElementById('cash_fields');
        
        accountSelect.innerHTML = '';
        
        let sourceData = [];
        if (method === 'Cash on Hand') {
            sourceData = cashAccounts;
            checkDiv.classList.add('hidden');
            cashDiv.classList.remove('hidden');
        } else {
            sourceData = passbooks;
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
            const option = new Option(text, acc.id);
            accountSelect.add(option);
        });
    }

    function toggleChangeLogic() {
        const isPending = document.getElementById('is_change_pending').checked;
        const actualInput = document.getElementById('amount');
        const changeDisplay = document.getElementById('change_display');
        
        if (isPending) {
            document.getElementById('amount_label').innerText = "Actual Expense (Enter 0 if unknown)";
            actualInput.required = false;
            changeDisplay.classList.add('hidden');
        } else {
            document.getElementById('amount_label').innerText = "Actual Expense Amount";
            actualInput.required = true;
            changeDisplay.classList.remove('hidden');
        }
    }

    function calculateChange() {
        const tendered = parseFloat(document.getElementById('amount_tendered').value) || 0;
        const actual = parseFloat(document.getElementById('amount').value) || 0;
        if(tendered > 0 && actual > 0) {
            const change = tendered - actual;
            document.getElementById('change_display').innerText = "Change: ₱" + change.toFixed(2);
        }
    }

    function openAddModal() {
        document.getElementById('expenseForm').reset();
        document.getElementById('modalTitle').innerText = 'Add New Expense';
        document.getElementById('expense_id').value = '';
        document.getElementById('expense_date').value = new Date().toISOString().slice(0, 10);
        toggleAccountSelect();
        toggleChangeLogic();
        openModal('expenseModal');
    }

    function openEditModal(exp) {
        document.getElementById('expenseForm').reset();
        document.getElementById('modalTitle').innerText = 'Edit Expense';
        document.getElementById('expense_id').value = exp.id;
        document.getElementById('expense_date').value = exp.expense_date;
        document.getElementById('chart_of_account_id').value = exp.chart_of_account_id;
        document.getElementById('description').value = exp.description;
        document.getElementById('amount').value = exp.amount;
        document.getElementById('payment_method').value = exp.payment_method;
        
        if(exp.amount_tendered > 0) {
             document.getElementById('amount_tendered').value = exp.amount_tendered;
        }
        document.getElementById('is_change_pending').checked = (exp.is_change_pending == 1);
        
        toggleAccountSelect();
        document.getElementById('account_id').value = exp.account_id;
        toggleChangeLogic();
        calculateChange();

        if(exp.payment_method === 'Check') {
            document.getElementById('check_number').value = exp.check_number || '';
            document.getElementById('check_date').value = exp.check_date || '';
        }
        
        openModal('expenseModal');
    }

    function openSettleModal(exp) {
        document.getElementById('settle_id').value = exp.id;
        document.getElementById('settle_given').innerText = "₱" + parseFloat(exp.amount_tendered).toFixed(2);
        document.getElementById('settle_actual').value = '';
        openModal('settleModal');
    }

    // --- EVENT LISTENERS WITH LOCK ---
    document.getElementById('settleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockButton(this);
        fetch('api/settle_change.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) { location.reload(); }
            else { handleApiResponse(data); unlockButton(btn); }
        })
        .catch(err => { handleApiResponse({success:false, message:'Network error'}); unlockButton(btn); });
    });

    document.getElementById('expenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockButton(this);
        const isEditing = !!document.getElementById('expense_id').value;
        const url = isEditing ? 'api/update_expense.php' : 'api/add_expense.php';
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) { location.reload(); }
            else { handleApiResponse(data); unlockButton(btn); }
        })
        .catch(err => { handleApiResponse({success:false, message:'Network error'}); unlockButton(btn); });
    });

    function deleteExpense(id) {
        if (confirm('Are you sure?')) {
            const formData = new FormData(); formData.append('id', id);
            fetch('api/delete_expense.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => location.reload());
        }
    }
    
    toggleAccountSelect();
</script>

<?php require_once "includes/footer.php"; ?>