<?php
// cash_account_view.php
require_once "includes/header.php";
require_once "config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: cash_on_hand.php"); exit;
}
$account_id = (int)$_GET['id'];

// 1. Fetch account details & current static database balance
$stmt = $conn->prepare("SELECT account_name, current_balance FROM cash_accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$account) { echo "Account not found."; exit; }

// 2. Fetch True Calculated Balance (The absolute sum of all transactions)
$stmt_true = $conn->prepare("SELECT SUM(credit) - SUM(debit) as true_bal FROM cash_transactions WHERE cash_account_id = ?");
$stmt_true->bind_param("i", $account_id);
$stmt_true->execute();
$true_bal = $stmt_true->get_result()->fetch_assoc()['true_bal'] ?? 0;
$stmt_true->close();

$db_balance = (float)$account['current_balance'];
$calculated_balance = (float)$true_bal;
$has_discrepancy = round($calculated_balance, 2) !== round($db_balance, 2);

// --- INITIALIZE VARIABLES & PAGINATION ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

// Filtering
$search_desc = $_GET['search_desc'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- BUILD DYNAMIC QUERY ---
$where_clauses = ["t.cash_account_id = ?"];
$params = [$account_id];
$types = 'i';

if (!empty($search_desc)) {
    $where_clauses[] = "t.description LIKE ?";
    $params[] = "%$search_desc%";
    $types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "t.transaction_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "t.transaction_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_sql = "WHERE " . implode(' AND ', $where_clauses);

// Fetch Total Count
$count_sql = "SELECT COUNT(t.id) as total FROM cash_transactions t $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// 3. FETCH TRANSACTIONS WITH DYNAMIC RUNNING BALANCE
// This subquery creates an accurate running balance row-by-row, sorting Credits before Debits on same days.
$sql = "
    SELECT t.*, 
           coa.account_name as coa_name,
           (SELECT SUM(c2.credit - c2.debit) 
            FROM cash_transactions c2 
            WHERE c2.cash_account_id = t.cash_account_id 
            AND (
                c2.transaction_date < t.transaction_date 
                OR (c2.transaction_date = t.transaction_date AND c2.credit > t.credit)
                OR (c2.transaction_date = t.transaction_date AND c2.credit = t.credit AND c2.id <= t.id)
            )
           ) AS running_balance
    FROM cash_transactions t
    LEFT JOIN chart_of_accounts coa ON t.chart_of_account_id = coa.id
    $where_sql
    ORDER BY t.transaction_date DESC, t.credit ASC, t.id DESC
    LIMIT ?, ?
";

$types .= 'ii';
$params[] = $offset;
$params[] = $records_per_page;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Chart of Accounts for dropdown
$coa_list = $conn->query("SELECT id, account_name, account_type FROM chart_of_accounts ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<style>
    @media print {
        @page { size: landscape; margin: 10mm; }
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
        .overflow-x-auto { overflow: visible !important; }
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <div>
        <a href="cash_on_hand.php" class="text-gray-500 hover:underline text-sm mb-2 inline-block">&larr; Back to Cash Accounts</a>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($account['account_name']); ?></h2>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="window.print()" class="flex-1 md:flex-none bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print</button>
        <button onclick="openAddModal()" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">+ Add Transaction</button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 no-print">
    <div class="bg-white p-6 rounded-lg shadow border-l-4 <?php echo $has_discrepancy ? 'border-red-500' : 'border-green-500'; ?>">
        <h3 class="text-sm font-bold text-gray-500 uppercase mb-1">True Calculated Balance</h3>
        <p class="text-3xl font-bold <?php echo ($calculated_balance < 0) ? 'text-red-600' : 'text-green-700'; ?>">
            ₱<?php echo number_format($calculated_balance, 2); ?>
        </p>
        <?php if ($has_discrepancy): ?>
            <p class="text-xs text-red-600 font-bold mt-2 bg-red-50 p-2 rounded">
                ⚠️ DISCREPANCY DETECTED: Your database table shows ₱<?php echo number_format($db_balance, 2); ?>, but your transactions mathematically add up to ₱<?php echo number_format($calculated_balance, 2); ?>. The trail below is the 100% correct math. (Run the sync_balances.php script to heal this).
            </p>
        <?php else: ?>
            <p class="text-xs text-gray-500 mt-2">Balances perfectly match transaction history.</p>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="cash_account_view.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <input type="hidden" name="id" value="<?php echo $account_id; ?>">
        <div>
            <label class="text-xs font-bold text-gray-700">Search Description</label>
            <input type="text" name="search_desc" value="<?php echo htmlspecialchars($search_desc); ?>" class="w-full border rounded p-2 text-sm" placeholder="e.g. Supplier name">
        </div>
        <div>
            <label class="text-xs font-bold text-gray-700">From Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-bold text-gray-700">To Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg w-full text-sm">Filter</button>
            <a href="cash_account_view.php?id=<?php echo $account_id; ?>" class="bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg w-full text-center text-sm leading-[20px]">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto" id="printable-area">
    <div class="hidden print:block text-center mb-4">
        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($account['account_name']); ?> - Ledger</h2>
        <p>As of <?php echo date('M d, Y'); ?></p>
    </div>
    <table class="w-full text-sm text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3 text-left">Date</th>
                <th class="p-2 md:p-3 text-left min-w-[200px]">Description</th>
                <th class="p-2 md:p-3 text-left">Category</th>
                <th class="p-2 md:p-3 text-right text-red-600">Money Out (Debit)</th>
                <th class="p-2 md:p-3 text-right text-green-600">Money In (Credit)</th>
                <th class="p-2 md:p-3 text-right font-bold text-blue-900">Running Balance</th>
                <th class="p-2 md:p-3 text-center no-print">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php if (!empty($transactions)): foreach ($transactions as $t): ?>
            <tr class="hover:bg-blue-50 transition-colors">
                <td class="p-2 md:p-3 whitespace-nowrap text-gray-700"><?php echo date("M d, Y", strtotime($t['transaction_date'])); ?></td>
                <td class="p-2 md:p-3 text-gray-800"><?php echo htmlspecialchars($t['description']); ?></td>
                <td class="p-2 md:p-3 text-xs text-gray-500"><?php echo htmlspecialchars($t['coa_name'] ?? '-'); ?></td>
                
                <td class="p-2 md:p-3 text-right font-medium text-red-600">
                    <?php echo ((float)$t['debit'] > 0) ? '₱' . number_format($t['debit'], 2) : '-'; ?>
                </td>
                
                <td class="p-2 md:p-3 text-right font-medium text-green-600">
                    <?php echo ((float)$t['credit'] > 0) ? '₱' . number_format($t['credit'], 2) : '-'; ?>
                </td>

                <td class="p-2 md:p-3 text-right font-bold <?php echo ($t['running_balance'] < 0) ? 'text-red-700 bg-red-50' : 'text-blue-900 bg-blue-50/30'; ?>">
                    ₱<?php echo number_format($t['running_balance'], 2); ?>
                </td>

                <td class="p-2 md:p-3 text-center space-x-2 no-print">
                    <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8"); ?>)' class="text-green-600 hover:underline font-bold text-sm">Edit</button>
                    <button onclick="deleteTransaction(<?php echo $t['id']; ?>)" class="text-red-600 hover:underline font-bold text-sm">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="p-8 text-center text-gray-500">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="mt-6 flex justify-between items-center no-print">
        <span class="text-sm text-gray-700">
            Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> Results
        </span>
        <div class="flex items-center space-x-1">
            <?php 
            $query_params = $_GET;
            unset($query_params['page']);
            $base_url = '?' . http_build_query($query_params) . '&page=';
            
            if ($page > 1): ?>
                <a href="<?php echo $base_url . ($page - 1); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">Prev</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?php echo $base_url . $i; ?>" class="px-3 py-1 border rounded <?php echo $i == $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?php echo $base_url . ($page + 1); ?>" class="px-3 py-1 border rounded hover:bg-gray-100">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="transactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md w-11/12">
        <form id="transactionForm">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add Transaction</h3>
            <input type="hidden" name="id" id="transaction_id">
            <input type="hidden" name="cash_account_id" value="<?php echo $account_id; ?>">
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-bold">Transaction Date</label>
                    <input type="date" name="transaction_date" id="transaction_date" class="w-full border rounded p-2" required>
                </div>
                <div>
                    <label class="text-sm font-bold">Type</label>
                    <select name="type" id="type" class="w-full border rounded p-2" onchange="toggleType()">
                        <option value="Credit">Money In (Credit)</option>
                        <option value="Debit">Money Out (Debit)</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-bold text-gray-700">Category (Chart of Accounts)</label>
                    <select name="chart_of_account_id" id="chart_of_account_id" class="w-full border rounded p-2" required>
                        <option value="">-- Select Category --</option>
                        <?php foreach($coa_list as $coa): ?>
                            <option value="<?php echo $coa['id']; ?>" data-type="<?php echo $coa['account_type']; ?>">
                                <?php echo htmlspecialchars($coa['account_name']); ?> (<?php echo $coa['account_type']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-bold">Amount</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="w-full border rounded p-2" required>
                </div>
                <div>
                    <label class="text-sm font-bold">Description</label>
                    <textarea name="description" id="description" class="w-full border rounded p-2" required></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('transactionModal')" class="bg-gray-200 py-2 px-4 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded text-sm font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function toggleType() {
        const type = document.getElementById('type').value;
        const coaSelect = document.getElementById('chart_of_account_id');
        const options = coaSelect.options;

        for (let i = 0; i < options.length; i++) {
            if (options[i].value === "") continue;
            const coaType = options[i].getAttribute('data-type');
            if (type === 'Credit') {
                if (coaType === 'Income' || coaType === 'Equity' || coaType === 'Liability' || coaType === 'Asset') {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            } else {
                if (coaType === 'Expense' || coaType === 'Asset' || coaType === 'Liability' || coaType === 'Equity') {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        }
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Add Transaction';
        document.getElementById('transactionForm').reset();
        document.getElementById('transaction_id').value = '';
        document.getElementById('transaction_date').value = new Date().toISOString().slice(0, 10);
        toggleType();
        openModal('transactionModal');
    }

    function openEditModal(transaction) {
        document.getElementById('modalTitle').innerText = 'Edit Transaction';
        document.getElementById('transaction_id').value = transaction.id;
        document.getElementById('transaction_date').value = transaction.transaction_date;
        document.getElementById('description').value = transaction.description;
        document.getElementById('amount').value = parseFloat(transaction.debit) > 0 ? transaction.debit : transaction.credit;
        document.getElementById('type').value = parseFloat(transaction.debit) > 0 ? 'Debit' : 'Credit';
        document.getElementById('chart_of_account_id').value = transaction.chart_of_account_id || '';
        toggleType();
        openModal('transactionModal');
    }

    function deleteTransaction(transactionId) {
        if (confirm('Are you sure you want to delete this transaction?')) {
            const formData = new FormData();
            formData.append('id', transactionId);

            fetch('api/delete_cash_transaction.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = window.location.pathname + window.location.search;
                } else {
                    alert('Error: ' + (data.message || 'Could not delete transaction.'));
                }
            });
        }
    }

    document.getElementById('transactionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        btn.disabled = true; btn.innerText = "Saving...";

        const id = document.getElementById('transaction_id').value;
        const url = id ? 'api/update_cash_transaction.php' : 'api/add_cash_transaction.php';

        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else { alert(data.message); btn.disabled = false; btn.innerText = originalText; }
        })
        .catch(err => { alert('Network error'); btn.disabled = false; btn.innerText = originalText; });
    });
</script>

<?php require_once "includes/footer.php"; ?>