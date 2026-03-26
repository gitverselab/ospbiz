<?php
// bills.php

// Debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/header.php";
require_once "config/database.php";

// --- 1. INITIALIZE VARIABLES & PAGINATION ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; 
$offset = ($page - 1) * $limit;

// Filters
$search_text = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- 2. FETCH DROPDOWNS ---
$billers_res = $conn->query("SELECT id, biller_name FROM billers ORDER BY biller_name");
$billers = $billers_res ? $billers_res->fetch_all(MYSQLI_ASSOC) : [];

$accounts_res = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name");
$expense_accounts = $accounts_res ? $accounts_res->fetch_all(MYSQLI_ASSOC) : [];

// --- 3. BUILD SEARCH QUERY ---
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_text)) {
    $where_clauses[] = "(b.bill_number LIKE ? OR bl.biller_name LIKE ? OR b.description LIKE ?)";
    $search_param = "%$search_text%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}
if (!empty($filter_status)) {
    $where_clauses[] = "b.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "b.due_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "b.due_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// --- 4. COUNT TOTALS ---
$count_sql = "SELECT COUNT(b.id) as total FROM bills b LEFT JOIN billers bl ON b.biller_id = bl.id $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// --- 5. FETCH DATA ---
$sql = "SELECT b.*, bl.biller_name, coa.account_name 
        FROM bills b 
        LEFT JOIN billers bl ON b.biller_id = bl.id 
        LEFT JOIN chart_of_accounts coa ON b.chart_of_account_id = coa.id
        $where_sql 
        ORDER BY b.due_date DESC, b.id DESC 
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <h2 class="text-3xl font-bold text-gray-800">Bills Payable</h2>
    
    <div class="flex flex-wrap gap-2 justify-end">
        <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-3 rounded-lg flex items-center text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
            Print
        </button>

        <a href="recurring_bills.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-3 rounded-lg flex items-center text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Manage Recurring
        </a>

        <button onclick="generateBills()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-lg flex items-center text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Run Generator
        </button>

        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-lg text-sm">
            + New Bill
        </button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="bills.php" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-700 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search_text); ?>" 
                   class="w-full border rounded p-2 text-sm" placeholder="Bill #, Biller, or Desc">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">Status</label>
            <select name="status" class="w-full border rounded p-2 text-sm">
                <option value="">All Statuses</option>
                <?php foreach(['Unpaid', 'Partially Paid', 'Paid'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filter_status === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">Due From</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">Due To</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="md:col-span-1 flex gap-2">
            <div class="w-1/2">
                <label class="block text-xs font-bold text-gray-700 uppercase">Show</label>
                <select name="limit" class="w-full border rounded p-2 text-sm" onchange="this.form.submit()">
                    <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="w-1/2 flex items-end">
                <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white px-3 py-2 rounded text-sm w-full font-bold">Filter</button>
            </div>
        </div>
    </form>
</div>

<div id="printable-area" class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <h3 class="text-2xl font-bold mb-4 hidden print:block">Bills Payable Report</h3>
    
    <table class="w-full text-sm text-left">
        <thead>
            <tr class="bg-gray-100 border-b uppercase text-gray-600 text-xs">
                <th class="p-3">Bill #</th>
                <th class="p-3">Biller</th>
                <th class="p-3">Description</th>
                <th class="p-3">Due Date</th>
                <th class="p-3">Category</th>
                <th class="p-3 text-right">Amount</th>
                <th class="p-3 text-center">Status</th>
                <th class="p-3 text-center no-print">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($bills)): ?>
                <tr><td colspan="8" class="p-4 text-center text-gray-500">No bills found.</td></tr>
            <?php else: foreach($bills as $b): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3 font-mono font-bold text-blue-600 print:text-black">
                        <a href="view_bill.php?id=<?php echo $b['id']; ?>" class="hover:underline no-print">
                            <?php echo htmlspecialchars($b['bill_number']); ?>
                        </a>
                        <span class="hidden print:inline"><?php echo htmlspecialchars($b['bill_number']); ?></span>
                    </td>
                    <td class="p-3 font-medium text-gray-800 print:text-black"><?php echo htmlspecialchars($b['biller_name']); ?></td>
                    <td class="p-3 text-gray-500 text-xs max-w-xs truncate print:text-black print:whitespace-normal"><?php echo htmlspecialchars($b['description']); ?></td>
                    <td class="p-3 <?php echo ($b['status'] != 'Paid' && $b['due_date'] < date('Y-m-d')) ? 'text-red-600 font-bold' : 'text-gray-600'; ?> print:text-black">
                        <?php echo htmlspecialchars($b['due_date']); ?>
                        <?php if($b['status'] != 'Paid' && $b['due_date'] < date('Y-m-d')) echo " (Overdue)"; ?>
                    </td>
                    <td class="p-3 text-gray-500 print:text-black"><?php echo htmlspecialchars($b['account_name'] ?? '-'); ?></td>
                    <td class="p-3 text-right font-bold text-gray-800 print:text-black">₱<?php echo number_format($b['amount'], 2); ?></td>
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            <?php 
                                if ($b['status'] === 'Paid') echo 'bg-green-100 text-green-800';
                                elseif ($b['status'] === 'Partially Paid') echo 'bg-yellow-100 text-yellow-800';
                                else echo 'bg-red-100 text-red-800';
                            ?>">
                            <?php echo htmlspecialchars($b['status']); ?>
                        </span>
                    </td>
                    <td class="p-3 text-center space-x-2 no-print">
                        <a href="view_bill.php?id=<?php echo $b['id']; ?>" class="text-blue-500 hover:text-blue-700 font-bold text-xs border border-blue-200 px-2 py-1 rounded">View</a>
                        
                        <?php if ($b['status'] !== 'Paid'): ?>
                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8'); ?>)' class="text-green-500 hover:text-green-700 font-bold text-xs border border-green-200 px-2 py-1 rounded">Edit</button>
                            <button onclick="deleteBill(<?php echo $b['id']; ?>)" class="text-red-500 hover:text-red-700 font-bold text-xs border border-red-200 px-2 py-1 rounded">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex flex-col sm:flex-row justify-between items-center no-print">
    <span class="text-sm text-gray-700 mb-2 sm:mb-0">
        Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> Results
    </span>
    
    <div class="flex gap-1">
        <?php $query_params = $_GET; ?>

        <?php if ($page > 1): ?>
            <?php $query_params['page'] = 1; ?>
            <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">« First</a>
            
            <?php $query_params['page'] = $page - 1; ?>
            <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">‹ Prev</a>
        <?php else: ?>
            <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">« First</span>
            <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">‹ Prev</span>
        <?php endif; ?>

        <?php 
        $range = 2; 
        $start_num = max(1, $page - $range);
        $end_num = min($total_pages, $page + $range);

        if ($start_num > 1) { echo '<span class="px-2 py-1">...</span>'; }

        for ($i = $start_num; $i <= $end_num; $i++): 
            $query_params['page'] = $i;
        ?>
            <a href="?<?php echo http_build_query($query_params); ?>" 
               class="px-3 py-1 border rounded <?php echo $i == $page ? 'bg-blue-600 text-white font-bold' : 'bg-white hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; 

        if ($end_num < $total_pages) { echo '<span class="px-2 py-1">...</span>'; }
        ?>

        <?php if ($page < $total_pages): ?>
            <?php $query_params['page'] = $page + 1; ?>
            <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">Next ›</a>
            
            <?php $query_params['page'] = $total_pages; ?>
            <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1 border rounded hover:bg-gray-100 bg-white">Last »</a>
        <?php else: ?>
            <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">Next ›</span>
            <span class="px-3 py-1 border rounded bg-gray-100 text-gray-400 cursor-not-allowed">Last »</span>
        <?php endif; ?>
    </div>
</div>

<div id="billModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="billForm">
            <h3 class="text-2xl font-bold mb-4" id="modalTitle">Add New Bill</h3>
            <input type="hidden" name="id" id="bill_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block font-semibold">Biller</label>
                    <select name="biller_id" id="biller_id" class="w-full border rounded p-2" required>
                        <option value="">Select Biller...</option>
                        <?php foreach($billers as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['biller_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold">Bill # (Invoice No.)</label>
                    <input type="text" name="bill_number" id="bill_number" class="w-full border rounded p-2" required>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-semibold">Bill Date</label>
                        <input type="date" name="bill_date" id="bill_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required>
                    </div>
                    <div>
                        <label class="block font-semibold">Due Date</label>
                        <input type="date" name="due_date" id="due_date" class="w-full border rounded p-2" required>
                    </div>
                </div>
                <div>
                    <label class="block font-semibold">Category</label>
                    <select name="chart_of_account_id" id="chart_of_account_id" class="w-full border rounded p-2" required>
                        <option value="">Select Expense Category...</option>
                        <?php foreach($expense_accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold">Amount</label>
                    <input type="number" name="amount" id="amount" step="0.01" class="w-full border rounded p-2 font-bold" required>
                </div>
                <div>
                    <label class="block font-semibold">Description</label>
                    <textarea name="description" id="description" class="w-full border rounded p-2"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('billModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Bill</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // --- BUTTON LOCKING (Duplicate Prevention) ---
    function lockButton(form) {
        const btn = form.querySelector('button[type="submit"]');
        if(btn) { 
            btn.dataset.originalText = btn.innerText; 
            btn.disabled = true; 
            btn.innerText = "Processing..."; 
        }
        return btn;
    }
    function unlockButton(btn) {
        if(btn) { 
            btn.disabled = false; 
            btn.innerText = btn.dataset.originalText || "Save"; 
        }
    }

    function openAddModal() {
        document.getElementById('billForm').reset();
        document.getElementById('modalTitle').innerText = 'Add New Bill';
        document.getElementById('bill_id').value = '';
        openModal('billModal');
    }

    function openEditModal(bill) {
        document.getElementById('modalTitle').innerText = 'Edit Bill';
        document.getElementById('bill_id').value = bill.id;
        document.getElementById('biller_id').value = bill.biller_id;
        document.getElementById('bill_number').value = bill.bill_number;
        document.getElementById('bill_date').value = bill.bill_date;
        document.getElementById('due_date').value = bill.due_date;
        document.getElementById('chart_of_account_id').value = bill.chart_of_account_id;
        document.getElementById('amount').value = bill.amount;
        document.getElementById('description').value = bill.description;
        openModal('billModal');
    }

    // --- FORM SUBMISSION ---
    document.getElementById('billForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockButton(this); // Lock
        const isEditing = document.getElementById('bill_id').value !== '';
        const url = isEditing ? 'api/update_bill.php' : 'api/add_bill.php';
        
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reload page but keep pagination/search params if possible, or just reload clean
                // window.location.href = window.location.pathname + window.location.search;
                location.reload();
            } else { 
                alert('Error: ' + data.message); 
                unlockButton(btn); // Unlock
            }
        })
        .catch(err => { 
            alert('Network error'); 
            unlockButton(btn); // Unlock
        });
    });

    function deleteBill(id) {
        if (confirm('Are you sure you want to delete this bill?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('api/delete_bill.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) { 
                    location.reload();
                } else { 
                    alert('Error: ' + data.message); 
                }
            });
        }
    }
    
    function generateBills() {
        if (confirm('Check and generate recurring bills?')) {
            document.body.style.cursor = 'wait';
            fetch('api/generate_recurring_bills.php', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                document.body.style.cursor = 'default';
                if(data.success) { alert(data.message); location.reload(); } 
                else { alert('Error: ' + data.message); }
            })
            .catch(error => {
                document.body.style.cursor = 'default';
                alert('An unexpected error occurred.');
            });
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>