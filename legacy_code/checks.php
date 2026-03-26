<?php
// checks.php
require_once "includes/header.php";
require_once "config/database.php";

// --- 1. CHECK PERMISSIONS ---
$can_delete = false;
if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') {
    $can_delete = true;
} elseif (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    $can_delete = true;
}

// --- 2. INITIALIZE VARIABLES & FILTERS ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; 
$offset = ($page - 1) * $limit;

$search_check = $_GET['search_check'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_bank = $_GET['bank_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- 3. FETCH DROPDOWNS ---
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks ORDER BY bank_name")->fetch_all(MYSQLI_ASSOC);

// --- 4. BUILD DYNAMIC QUERY ---
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_check)) {
    $where_clauses[] = "(c.check_number LIKE ? OR c.payee LIKE ?)";
    $params[] = "%$search_check%";
    $params[] = "%$search_check%";
    $types .= 'ss';
}
if (!empty($filter_status)) {
    $where_clauses[] = "c.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($filter_bank)) {
    $where_clauses[] = "c.passbook_id = ?";
    $params[] = $filter_bank;
    $types .= 'i';
}
if (!empty($start_date)) {
    $where_clauses[] = "c.check_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "c.check_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// --- 5. COUNT TOTAL RECORDS ---
$count_sql = "SELECT COUNT(c.id) as total FROM checks c $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_records / $limit);

// --- 6. FETCH DATA ---
$sql = "SELECT c.*, p.bank_name, p.account_number 
        FROM checks c 
        JOIN passbooks p ON c.passbook_id = p.id 
        $where_sql 
        ORDER BY c.check_date DESC, c.id DESC 
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$checks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<style>
    @media print {
        @page { size: landscape; margin: 10mm; }
        body * { visibility: hidden; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Check Management</h2>
    <div class="flex gap-2 w-full md:w-auto">
        <button onclick="window.print()" class="flex-1 md:flex-none justify-center bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 text-sm md:text-base">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
            <span class="hidden md:inline">Print List</span><span class="md:hidden">Print</span>
        </button>
        <button onclick="openModal('checkModal')" class="flex-1 md:flex-none justify-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg text-sm md:text-base">
            <span class="hidden md:inline">Issue New Check</span><span class="md:hidden">+ New Check</span>
        </button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="checks.php" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700">Search</label>
            <input type="text" name="search_check" value="<?php echo htmlspecialchars($search_check); ?>" class="w-full border rounded p-2 text-sm" placeholder="Check # or Payee">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700">Bank Account</label>
            <select name="bank_id" class="w-full border rounded p-2 text-sm">
                <option value="">All Banks</option>
                <?php foreach($passbooks as $pb): ?>
                    <option value="<?php echo $pb['id']; ?>" <?php echo $filter_bank == $pb['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pb['bank_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700">Status</label>
            <select name="filter_status" class="w-full border rounded p-2 text-sm">
                <option value="">All Statuses</option>
                <?php foreach(['Issued', 'Cleared', 'Canceled', 'Bounced'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filter_status == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700">Date From</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700">Date To</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="md:col-span-1 flex gap-2">
            <div class="w-1/2">
                <label class="block text-xs font-bold text-gray-700">Show</label>
                <select name="limit" class="w-full border rounded p-2 text-sm" onchange="this.form.submit()">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="w-1/2 flex items-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm w-full font-bold h-[38px]">Filter</button>
            </div>
        </div>
    </form>
</div>

<div id="printable-area" class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <h3 class="text-2xl font-bold mb-4 hidden print:block">Check Issuance Report</h3>
    
    <table class="w-full text-xs md:text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3 text-left">Date</th>
                <th class="p-2 md:p-3 text-left">Check #</th>
                <th class="p-2 md:p-3 text-left">Payee</th>
                <th class="p-2 md:p-3 text-left">Bank</th>
                <th class="p-2 md:p-3 text-right">Amount</th>
                <th class="p-2 md:p-3 text-center hidden md:table-cell">Release Date</th>
                <th class="p-2 md:p-3 text-center">Status</th>
                <th class="p-2 md:p-3 text-center no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($checks)): ?>
                <tr><td colspan="8" class="p-4 text-center text-gray-500">No checks found.</td></tr>
            <?php else: foreach ($checks as $row): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-2 md:p-3 whitespace-nowrap"><?php echo date('M d, y', strtotime($row['check_date'])); ?></td>
                    <td class="p-2 md:p-3 font-mono font-bold text-blue-700 print:text-black"><?php echo htmlspecialchars($row['check_number']); ?></td>
                    <td class="p-2 md:p-3 font-medium max-w-[120px] md:max-w-xs truncate" title="<?php echo htmlspecialchars($row['payee']); ?>">
                        <?php echo htmlspecialchars($row['payee']); ?>
                    </td>
                    <td class="p-2 md:p-3 text-gray-600 text-xs print:text-black max-w-[100px] truncate">
                        <?php echo htmlspecialchars($row['bank_name']); ?>
                    </td>
                    <td class="p-2 md:p-3 text-right font-bold text-gray-800 print:text-black">₱<?php echo number_format($row['amount'], 2); ?></td>
                    <td class="p-2 md:p-3 text-center text-gray-500 print:text-black hidden md:table-cell"><?php echo htmlspecialchars($row['release_date']); ?></td>
                    <td class="p-2 md:p-3 text-center">
                        <span class="px-2 py-1 rounded-full text-[10px] md:text-xs font-semibold
                            <?php 
                                if ($row['status'] === 'Cleared') echo 'bg-green-100 text-green-800';
                                elseif ($row['status'] === 'Issued') echo 'bg-blue-100 text-blue-800';
                                elseif ($row['status'] === 'Canceled') echo 'bg-red-100 text-red-800';
                                else echo 'bg-gray-100 text-gray-800';
                            ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td class="p-2 md:p-3 text-center relative no-print">
                        <div class="inline-block text-left relative">
                            <button onclick="toggleDropdown(<?php echo $row['id']; ?>)" class="text-gray-500 hover:text-gray-700 font-bold focus:outline-none p-1">
                                &#x22EE;
                            </button>
                            <div id="dropdown-<?php echo $row['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white border rounded-lg shadow-xl z-50 text-left">
                                <?php if ($row['status'] === 'Issued'): ?>
                                    <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit Details</button>
                                    <button onclick='openReconcileModal(<?php echo json_encode($row); ?>)' class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50 font-bold">Reconcile (Clear)</button>
                                    <button onclick="updateCheckStatus(<?php echo $row['id']; ?>, 'Canceled')" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Mark as Canceled</button>
                                    <?php if($can_delete): ?>
                                        <button onclick="deleteCheck(<?php echo $row['id']; ?>)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t">Delete Permanently</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="block w-full text-left px-4 py-2 text-xs text-gray-400 italic">No actions available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex flex-col md:flex-row justify-between items-center no-print gap-4">
    <span class="text-sm text-gray-700 text-center md:text-left">
        Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Results
    </span>
    
    <div class="flex gap-1 flex-wrap justify-center">
        <?php $q = $_GET; ?>
        <?php if ($page > 1): ?>
            <?php $q['page'] = 1; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white text-xs md:text-sm">« First</a>'; ?>
            <?php $q['page'] = $page - 1; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white text-xs md:text-sm">‹ Prev</a>'; ?>
        <?php endif; ?>

        <span class="px-3 py-1 border rounded bg-blue-600 text-white font-bold text-xs md:text-sm"><?php echo $page; ?></span>
        
        <?php if ($page < $total_pages): ?>
            <?php $q['page'] = $page + 1; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white text-xs md:text-sm">Next ›</a>'; ?>
            <?php $q['page'] = $total_pages; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white text-xs md:text-sm">Last »</a>'; ?>
        <?php endif; ?>
    </div>
</div>

<div id="checkModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-11/12 md:max-w-lg">
        <form id="checkForm">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Issue New Check</h3>
            <input type="hidden" name="id" id="check_id">
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div><label class="text-sm font-bold">Date</label><input type="date" name="check_date" id="check_date" class="w-full border rounded p-2" required></div>
                <div><label class="text-sm font-bold">Release Date</label><input type="date" name="release_date" id="release_date" class="w-full border rounded p-2"></div>
            </div>
            <div class="mb-4">
                <label class="text-sm font-bold">Bank Account</label>
                <select name="passbook_id" id="passbook_id" class="w-full border rounded p-2" required>
                    <option value="">Select Account...</option>
                    <?php foreach($passbooks as $pb): ?>
                        <option value="<?php echo $pb['id']; ?>"><?php echo htmlspecialchars($pb['bank_name'] . ' (' . $pb['account_number'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="col-span-1"><label class="text-sm font-bold">Check No.</label><input type="text" name="check_number" id="check_number" class="w-full border rounded p-2" required></div>
                <div class="col-span-2"><label class="text-sm font-bold">Payee</label><input type="text" name="payee" id="payee" class="w-full border rounded p-2" required></div>
            </div>
            <div class="mb-4"><label class="text-sm font-bold">Amount</label><input type="number" name="amount" id="amount" step="0.01" class="w-full border rounded p-2 font-bold" required></div>
            
            <div id="ref_section" class="mb-4 bg-gray-50 p-3 rounded border">
                <label class="block text-sm font-bold text-gray-700 mb-2">Payment For (Optional)</label>
                <div class="flex flex-wrap gap-4 text-sm mb-2">
                    <label><input type="radio" name="payment_for" value="manual" checked onclick="toggleRef(this.value)"> Manual</label>
                    <label><input type="radio" name="payment_for" value="purchase" onclick="toggleRef(this.value)"> Purchase</label>
                    <label><input type="radio" name="payment_for" value="bill" onclick="toggleRef(this.value)"> Bill</label>
                </div>
                <div id="ref_input_container" class="hidden">
                    <input type="number" name="link_id" id="link_id" placeholder="Enter Ref ID" class="w-full border rounded p-2 text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('checkModal')" class="bg-gray-200 py-2 px-4 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded">Save Check</button>
            </div>
        </form>
    </div>
</div>

<div id="reconcileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-11/12 md:max-w-sm">
        <form id="reconcileForm" action="api/reconcile_check.php" method="POST">
            <h3 class="text-lg font-bold mb-4 text-green-700">Reconcile Check</h3>
            <input type="hidden" name="check_id" id="rec_check_id">
            <input type="hidden" name="passbook_id" id="rec_passbook_id">
            
            <p class="mb-4 text-sm">Check #: <span id="rec_check_num" class="font-bold"></span><br>Amount: <span id="rec_amount" class="font-bold"></span></p>
            
            <div class="mb-4">
                <label class="block font-bold mb-1">Date Cleared</label>
                <input type="date" name="cleared_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required>
            </div>
            
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal('reconcileModal')" class="bg-gray-200 py-2 px-4 rounded">Cancel</button>
                <button type="submit" class="bg-green-600 text-white py-2 px-4 rounded">Confirm Clear</button>
            </div>
        </form>
    </div>
</div>

<script>
    function lockBtn(form) {
        const btn = form.querySelector('button[type="submit"]');
        btn.dataset.text = btn.innerText;
        btn.disabled = true; btn.innerText = "Processing...";
        return btn;
    }
    function unlockBtn(btn) {
        btn.disabled = false; btn.innerText = btn.dataset.text;
    }

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    
    // Improved Dropdown Toggle for Mobile touch/click
    function toggleDropdown(id) {
        // Close all others
        document.querySelectorAll('[id^="dropdown-"]').forEach(d => {
            if(d.id !== 'dropdown-'+id) d.classList.add('hidden');
        });
        
        const dropdown = document.getElementById('dropdown-' + id);
        
        // Mobile positioning safety
        if (window.innerWidth < 768) {
            // Slight adjustment if needed, but absolute positioning usually works inside relative td
        }
        
        dropdown.classList.toggle('hidden');
    }

    function toggleRef(val) {
        const cont = document.getElementById('ref_input_container');
        if(val === 'manual') {
            cont.classList.add('hidden');
            document.getElementById('link_id').required = false;
        } else {
            cont.classList.remove('hidden');
            document.getElementById('link_id').required = true;
        }
    }

    function openEditModal(check) {
        document.getElementById('modalTitle').innerText = 'Edit Check Details';
        document.getElementById('check_id').value = check.id;
        document.getElementById('check_date').value = check.check_date;
        document.getElementById('release_date').value = check.release_date;
        document.getElementById('passbook_id').value = check.passbook_id;
        document.getElementById('check_number').value = check.check_number;
        document.getElementById('payee').value = check.payee;
        document.getElementById('amount').value = check.amount;
        document.getElementById('ref_section').classList.add('hidden');
        openModal('checkModal');
    }

    function openReconcileModal(check) {
        document.getElementById('rec_check_id').value = check.id;
        document.getElementById('rec_passbook_id').value = check.passbook_id;
        document.getElementById('rec_check_num').innerText = check.check_number;
        document.getElementById('rec_amount').innerText = '₱' + parseFloat(check.amount).toFixed(2);
        openModal('reconcileModal');
    }

    document.getElementById('checkForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockBtn(this);
        const id = document.getElementById('check_id').value;
        const url = id ? 'api/update_check.php' : 'api/add_check.php';
        
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else { alert(data.message); unlockBtn(btn); }
        })
        .catch(err => { alert('Network error'); unlockBtn(btn); });
    });

    document.getElementById('reconcileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockBtn(this);
        fetch(this.action, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if(data.success) location.reload();
            else { alert(data.message); unlockBtn(btn); }
        })
        .catch(err => { alert('Network error'); unlockBtn(btn); });
    });

    function updateCheckStatus(id, newStatus) {
        if(confirm(`Are you sure you want to mark this check as ${newStatus}?`)) {
            const fd = new FormData(); 
            fd.append('id', id); 
            fd.append('status', newStatus);
            
            fetch('api/update_check_direct.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else alert('Error: ' + (data.error || data.message));
            });
        }
    }

    function deleteCheck(id) {
        if(confirm('Are you sure you want to DELETE this check? \n\nWARNING: If this check was used to pay a Purchase or Bill, that payment will be removed and the bill will become Unpaid.')) {
            const fd = new FormData(); 
            fd.append('id', id);
            
            fetch('api/delete_check.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }
    }

    // Close dropdowns when clicking outside
    window.onclick = function(event) {
        if (!event.target.matches('button') && !event.target.closest('button')) {
            document.querySelectorAll('[id^="dropdown-"]').forEach(d => d.classList.add('hidden'));
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>