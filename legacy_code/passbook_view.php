<?php
// passbook_view.php
require_once "includes/header.php";
require_once "config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: passbooks.php"); exit;
}
$passbook_id = (int)$_GET['id'];

// --- GET PASSBOOK DETAILS ---
$stmt = $conn->prepare("SELECT bank_name, account_number, current_balance FROM passbooks WHERE id = ?");
$stmt->bind_param("i", $passbook_id);
$stmt->execute();
$passbook = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$passbook) { echo "Passbook not found."; exit; }

// 2. Fetch True Calculated Balance (The absolute sum of all transactions)
$stmt_true = $conn->prepare("SELECT SUM(credit) - SUM(debit) as true_bal FROM passbook_transactions WHERE passbook_id = ?");
$stmt_true->bind_param("i", $passbook_id);
$stmt_true->execute();
$true_bal = $stmt_true->get_result()->fetch_assoc()['true_bal'] ?? 0;
$stmt_true->close();

$db_balance = (float)$passbook['current_balance'];
$calculated_balance = (float)$true_bal;
$has_discrepancy = round($calculated_balance, 2) !== round($db_balance, 2);

// --- INITIALIZE VARIABLES ---
// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 25;
$offset = ($page - 1) * $records_per_page;

// Filtering
$search_desc = $_GET['search_desc'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- BUILD DYNAMIC QUERY ---
$where_clauses = ["t.passbook_id = ?"];
$params = [$passbook_id];
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

// --- FETCH TOTAL COUNT FOR PAGINATION ---
$count_sql = "SELECT COUNT(t.id) as total FROM passbook_transactions t $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// --- FETCH DATA WITH DYNAMIC RUNNING BALANCE ---
$transactions = [];
$sql = "
    SELECT t.*, 
           (SELECT SUM(c2.credit - c2.debit) 
            FROM passbook_transactions c2 
            WHERE c2.passbook_id = t.passbook_id 
            AND (
                c2.transaction_date < t.transaction_date 
                OR (c2.transaction_date = t.transaction_date AND c2.credit > t.credit)
                OR (c2.transaction_date = t.transaction_date AND c2.credit = t.credit AND c2.id <= t.id)
            )
           ) AS running_balance
    FROM passbook_transactions t
    $where_sql 
    ORDER BY t.transaction_date DESC, t.credit ASC, t.id DESC 
    LIMIT ?, ?
";

$types .= 'ii';
$params[] = $offset;
$params[] = $records_per_page;

$stmt_trans = $conn->prepare($sql);
$stmt_trans->bind_param($types, ...$params);
$stmt_trans->execute();
$result = $stmt_trans->get_result();
while($row = $result->fetch_assoc()) { $transactions[] = $row; }
$stmt_trans->close();

// --- FETCH DATA FOR MODALS ---
// Only fetch Issued checks for reconciliation list
$issued_checks = $conn->query("SELECT id, check_number, amount, payee FROM checks WHERE passbook_id = $passbook_id AND status = 'Issued' ORDER BY check_date DESC")->fetch_all(MYSQLI_ASSOC);
$chart_of_accounts = $conn->query("SELECT id, account_name, account_type FROM chart_of_accounts ORDER BY account_type, account_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    @media print {
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        table { font-size: 12px; }
        .page-header { display: block !important; }
    }
    /* Select2 Styling Fixes */
    .select2-container .select2-selection--single {
        height: 42px !important;
        padding: 6px 12px;
        border: 1px solid #d1d5db; border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
    .select2-container { z-index: 9999 !important; } /* Ensure dropdown appears above modal */
</style>

<div class="page-header hidden print:block mb-6">
    <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($passbook['bank_name']); ?></h1>
    <p class="text-lg"><?php echo htmlspecialchars($passbook['account_number']); ?></p>
    <p class="text-sm text-gray-600">Statement as of <?php echo date("F j, Y"); ?></p>
    <?php if ($start_date || $end_date): ?>
        <p class="text-sm text-gray-600">Period: <?php echo htmlspecialchars($start_date ?: 'Start'); ?> to <?php echo htmlspecialchars($end_date ?: 'End'); ?></p>
    <?php endif; ?>
</div>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 no-print gap-4">
    <div>
        <a href="passbooks.php" class="text-gray-500 hover:underline text-sm mb-2 inline-block">&larr; Back to Passbooks</a>
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($passbook['bank_name']); ?></h2>
        <p class="text-gray-500 font-mono text-sm md:text-base"><?php echo htmlspecialchars($passbook['account_number']); ?></p>
    </div>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="window.print()" class="flex-1 md:flex-none bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print</button>
        <button onclick="openReconcileModal()" class="flex-1 md:flex-none bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow">Reconcile Check</button>
        <button onclick="openAddTransactionModal()" class="flex-1 md:flex-none bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">+ Add Transaction</button>
    </div>
</div>

<div id="alertBox" class="hidden no-print mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
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
    <form method="GET" action="passbook_view.php" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="id" value="<?php echo $passbook_id; ?>">
        <div class="md:col-span-2">
            <label for="search_desc" class="text-xs font-bold text-gray-700">Search</label>
            <input type="text" name="search_desc" id="search_desc" value="<?php echo htmlspecialchars($search_desc); ?>" class="w-full border rounded p-2 text-sm" placeholder="Description...">
        </div>
        <div>
            <label for="start_date" class="text-xs font-bold text-gray-700">From</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label for="end_date" class="text-xs font-bold text-gray-700">To</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="flex space-x-2 md:col-start-4">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded text-sm">Filter</button>
            <a href="passbook_view.php?id=<?php echo $passbook_id; ?>" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 rounded text-sm text-center">Reset</a>
        </div>
    </form>
</div>

<div id="printable-area" class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3 whitespace-nowrap">Date</th>
                <th class="p-2 md:p-3 whitespace-nowrap min-w-[200px]">Description</th>
                <th class="p-2 md:p-3 text-right whitespace-nowrap text-red-600">Money Out (Debit)</th>
                <th class="p-2 md:p-3 text-right whitespace-nowrap text-green-600">Money In (Credit)</th>
                <th class="p-2 md:p-3 text-right font-bold text-blue-900">Running Balance</th>
                <th class="p-2 md:p-3 text-center no-print whitespace-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php if (!empty($transactions)): foreach($transactions as $t): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2 md:p-3 whitespace-nowrap"><?php echo htmlspecialchars($t['transaction_date']); ?></td>
                    <td class="p-2 md:p-3 font-medium text-gray-700">
                        <?php echo htmlspecialchars($t['description']); ?>
                    </td>
                    <td class="p-2 md:p-3 text-right font-medium text-red-600 whitespace-nowrap"><?php echo ($t['debit'] > 0) ? '₱' . number_format($t['debit'], 2) : '-'; ?></td>
                    <td class="p-2 md:p-3 text-right font-medium text-green-600 whitespace-nowrap"><?php echo ($t['credit'] > 0) ? '₱' . number_format($t['credit'], 2) : '-'; ?></td>
                    
                    <td class="p-2 md:p-3 text-right font-bold <?php echo ($t['running_balance'] < 0) ? 'text-red-700 bg-red-50' : 'text-blue-900 bg-blue-50/30'; ?> whitespace-nowrap">
                        ₱<?php echo number_format($t['running_balance'], 2); ?>
                    </td>

                    <td class="p-2 md:p-3 text-center space-x-2 no-print whitespace-nowrap">
                        <?php if (empty($t['check_ref_id'])): ?>
                            <button onclick='openEditTransactionModal(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8"); ?>)' class="text-green-600 hover:text-green-800 font-bold text-xs">Edit</button>
                            <button onclick='deleteTransaction(<?php echo $t["id"]; ?>)' class="text-red-600 hover:text-red-800 font-bold text-xs">Delete</button>
                        <?php else: ?>
                            <span class="text-gray-400 text-xs italic bg-gray-100 px-2 py-1 rounded">Linked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center p-6 text-gray-500 italic">No transactions found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex flex-col md:flex-row justify-between items-center no-print gap-4">
    <span class="text-sm text-gray-700 text-center md:text-left">
        Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> Results
    </span>
    <div class="flex items-center space-x-1 justify-center">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">Prev</a>
        <?php endif; ?>
        
        <span class="px-3 py-1 border rounded bg-blue-600 text-white font-bold text-sm"><?php echo $page; ?></span>
        
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 text-sm">Next</a>
        <?php endif; ?>
    </div>
</div>

<div id="reconcileCheckModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg md:w-full w-11/12" style="min-height: 400px;">
        <form id="reconcileForm" action="api/reconcile_check.php" method="POST">
            <h3 class="text-xl font-bold mb-4 text-green-700">Reconcile Check</h3>
            <input type="hidden" name="passbook_id" value="<?php echo $passbook_id; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Select Check</label>
                    <select name="check_id" id="check_id_select" class="w-full border rounded p-2" required style="width: 100%;">
                        <option value="">Search check...</option>
                        <?php foreach($issued_checks as $check): ?>
                            <option value="<?php echo $check['id']; ?>">#<?php echo htmlspecialchars($check['check_number']); ?> - ₱<?php echo number_format($check['amount'], 2); ?> (<?php echo htmlspecialchars($check['payee']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">Date Cleared</label>
                    <input type="date" name="cleared_date" class="w-full border rounded p-2" required>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('reconcileCheckModal')" class="bg-gray-200 py-2 px-4 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-green-600 text-white font-bold py-2 px-4 rounded text-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>

<div id="transactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg md:w-full w-11/12">
        <form id="transactionForm" method="POST">
             <h3 id="transactionModalTitle" class="text-xl font-bold mb-4 text-blue-800">Add Manual Transaction</h3>
             <input type="hidden" name="passbook_id" value="<?php echo $passbook_id; ?>">
             <input type="hidden" name="id" id="transaction_id">
             <div class="space-y-3">
                <div><label class="block text-xs font-bold text-gray-700">Date</label><input type="date" name="transaction_date" id="transaction_date" class="w-full border rounded p-2" required></div>
                <div><label class="block text-xs font-bold text-gray-700">Type</label><select name="type" id="transaction_type" class="w-full border rounded p-2"><option value="Credit">Credit (Money In)</option><option value="Debit">Debit (Money Out)</option></select></div>
                <div><label class="block text-xs font-bold text-gray-700">Category</label>
                    <select name="chart_of_account_id" id="chart_of_account_id" class="w-full border rounded p-2" required>
                       <option value="">Select Category...</option>
                       <?php foreach($chart_of_accounts as $account): ?>
                           <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                       <?php endforeach; ?>
                   </select>
               </div>
                <div><label class="block text-xs font-bold text-gray-700">Amount</label><input type="number" name="amount" id="transaction_amount" step="0.01" class="w-full border rounded p-2 font-bold" required></div>
                <div><label class="block text-xs font-bold text-gray-700">Notes</label><textarea name="description" id="transaction_description" rows="2" class="w-full border rounded p-2"></textarea></div>
             </div>
             <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('transactionModal')" class="bg-gray-200 py-2 px-4 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded text-sm">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('#check_id_select').select2({
            dropdownParent: $('#reconcileCheckModal'), 
            placeholder: "Search check...",
            allowClear: true,
            width: '100%'
        });
    });

    function openReconcileModal() {
        $('#check_id_select').val(null).trigger('change'); 
        openModal('reconcileCheckModal');
    }

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');
    
    function handleApiResponse(response, modalToClose = 'transactionModal') {
        if (response.success) {
            alertMessage.textContent = response.message || 'Success!';
            alertBox.className = 'no-print mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
            alertBox.classList.remove('hidden');
            setTimeout(() => window.location.href = window.location.pathname + window.location.search, 1000);
        } else {
            alertMessage.textContent = response.message || 'An error occurred.';
            alertBox.className = 'no-print mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
            alertBox.classList.remove('hidden');
        }
        closeModal(modalToClose);
    }

    function openAddTransactionModal() {
        document.getElementById('transactionForm').action = 'api/add_passbook_transaction.php';
        document.getElementById('transactionModalTitle').innerText = 'Add Manual Transaction';
        document.getElementById('transactionForm').reset();
        document.getElementById('transaction_id').value = '';
        document.getElementById('transaction_date').value = new Date().toISOString().slice(0, 10);
        openModal('transactionModal');
    }

    function openEditTransactionModal(transaction) {
        document.getElementById('transactionForm').action = 'api/update_passbook_transaction.php';
        document.getElementById('transactionModalTitle').innerText = 'Edit Transaction';
        document.getElementById('transaction_id').value = transaction.id;
        document.getElementById('transaction_date').value = transaction.transaction_date;
        document.getElementById('transaction_description').value = transaction.description;
        document.getElementById('chart_of_account_id').value = transaction.chart_of_account_id || '';

        if (parseFloat(transaction.debit) > 0) {
            document.getElementById('transaction_type').value = 'Debit';
            document.getElementById('transaction_amount').value = transaction.debit;
        } else {
            document.getElementById('transaction_type').value = 'Credit';
            document.getElementById('transaction_amount').value = transaction.credit;
        }
        openModal('transactionModal');
    }

    function deleteTransaction(transactionId) {
        if (confirm('Are you sure you want to delete this transaction? This may affect linked records and cannot be undone.')) {
            const formData = new FormData();
            formData.append('transaction_id', transactionId);
            formData.append('account_type', 'passbook');
            formData.append('account_id', <?php echo $passbook_id; ?>);

            fetch('api/delete_transaction.php', { method: 'POST', body: formData })
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

    function handleFormSubmit(event, formId) {
        event.preventDefault();
        const form = document.getElementById(formId);
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        
        if(btn.disabled) return; 
        btn.disabled = true;
        btn.innerText = "Processing...";

        fetch(form.action, { method: 'POST', body: new FormData(form) })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else { alert(data.message); btn.disabled = false; btn.innerText = originalText; }
        })
        .catch(err => { alert('Network error'); btn.disabled = false; btn.innerText = originalText; });
    }

    document.getElementById('transactionForm').addEventListener('submit', function(e) {
        this.action = document.getElementById('transaction_id').value ? 'api/update_passbook_transaction.php' : 'api/add_passbook_transaction.php';
        handleFormSubmit(e, 'transactionForm');
    });

    document.getElementById('reconcileForm').addEventListener('submit', function(e) {
        handleFormSubmit(e, 'reconcileForm');
    });
</script>

<?php require_once "includes/footer.php"; ?>