<?php
// purchases.php

// Error reporting for debugging
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
$suppliers_res = $conn->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
$suppliers = $suppliers_res ? $suppliers_res->fetch_all(MYSQLI_ASSOC) : [];

$accounts_res = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name");
$expense_accounts = $accounts_res ? $accounts_res->fetch_all(MYSQLI_ASSOC) : [];

$items_res = $conn->query("SELECT * FROM items ORDER BY item_name");
$items_list = $items_res ? $items_res->fetch_all(MYSQLI_ASSOC) : [];

// --- 3. BUILD SEARCH QUERY ---
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_text)) {
    $where_clauses[] = "(p.po_number LIKE ? OR s.supplier_name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_text%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}
if (!empty($filter_status)) {
    $where_clauses[] = "p.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "p.purchase_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "p.purchase_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// --- 4. COUNT TOTALS ---
$count_sql = "SELECT COUNT(p.id) as total FROM purchases p JOIN suppliers s ON p.supplier_id = s.id $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// --- 5. FETCH PURCHASES ---
$sql = "SELECT p.*, s.supplier_name, coa.account_name 
        FROM purchases p 
        JOIN suppliers s ON p.supplier_id = s.id 
        LEFT JOIN chart_of_accounts coa ON p.chart_of_account_id = coa.id
        $where_sql
        ORDER BY p.purchase_date DESC, p.id DESC
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
    }
</style>

<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">Purchase Orders</h2>
    <div class="flex gap-2">
        <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2-4h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm9-2a1 1 0 11-2 0 1 1 0 012 0z"></path></svg>
            Print List
        </button>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            + New Purchase
        </button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="purchases.php" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-700 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search_text); ?>" 
                   class="w-full border rounded p-2 text-sm" placeholder="PO #, Supplier, or Description">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">Status</label>
            <select name="status" class="w-full border rounded p-2 text-sm">
                <option value="">All Statuses</option>
                <?php foreach(['Unpaid', 'Partially Paid', 'Paid', 'Canceled'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $filter_status === $s ? 'selected' : ''; ?>>
                        <?php echo $s; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">From Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm">
        </div>
        <div class="md:col-span-1">
            <label class="block text-xs font-bold text-gray-700 uppercase">To Date</label>
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
    <h3 class="text-2xl font-bold mb-4 hidden print:block">Purchase Order Report</h3>
    
    <table class="w-full text-sm text-left">
        <thead>
            <tr class="bg-gray-100 border-b uppercase text-gray-600 text-xs">
                <th class="p-3">PO Number</th>
                <th class="p-3">Date</th>
                <th class="p-3">Supplier</th>
                <th class="p-3">Category</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Amount</th>
                <th class="p-3 text-center no-print">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if (empty($purchases)): ?>
                <tr><td colspan="7" class="p-4 text-center text-gray-500">No purchases found.</td></tr>
            <?php else: foreach($purchases as $p): ?>
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-3 font-mono font-bold text-blue-600 print:text-black">
                        <a href="view_purchase.php?id=<?php echo $p['id']; ?>" class="hover:underline no-print">
                            <?php echo htmlspecialchars($p['po_number']); ?>
                        </a>
                        <span class="hidden print:inline"><?php echo htmlspecialchars($p['po_number']); ?></span>
                    </td>
                    <td class="p-3 text-gray-600 print:text-black"><?php echo htmlspecialchars($p['purchase_date']); ?></td>
                    <td class="p-3 font-medium text-gray-800 print:text-black">
                        <?php echo htmlspecialchars($p['supplier_name']); ?>
                        <div class="text-xs text-gray-400 font-normal print:text-gray-600"><?php echo htmlspecialchars($p['description']); ?></div>
                    </td>
                    <td class="p-3 text-gray-500 print:text-black"><?php echo htmlspecialchars($p['account_name'] ?? '-'); ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            <?php 
                                if ($p['status'] === 'Paid') echo 'bg-green-100 text-green-800';
                                elseif ($p['status'] === 'Partially Paid') echo 'bg-yellow-100 text-yellow-800';
                                elseif ($p['status'] === 'Canceled') echo 'bg-red-100 text-red-800';
                                else echo 'bg-gray-100 text-gray-800';
                            ?>">
                            <?php echo htmlspecialchars($p['status']); ?>
                        </span>
                    </td>
                    <td class="p-3 text-right font-bold text-gray-800 print:text-black">₱<?php echo number_format($p['amount'], 2); ?></td>
                    <td class="p-3 text-center space-x-2 no-print">
                        <a href="view_purchase.php?id=<?php echo $p['id']; ?>" class="text-blue-500 hover:text-blue-700 font-bold text-xs border border-blue-200 px-2 py-1 rounded">View</a>
                        
                        <?php if ($p['status'] !== 'Paid' && $p['status'] !== 'Canceled'): ?>
                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)' class="text-green-500 hover:text-green-700 font-bold text-xs border border-green-200 px-2 py-1 rounded">Edit</button>
                        <?php endif; ?>
                        
                        <?php if ($p['status'] === 'Unpaid'): ?>
                            <button onclick="deletePurchase(<?php echo $p['id']; ?>)" class="text-red-500 hover:text-red-700 font-bold text-xs border border-red-200 px-2 py-1 rounded">Delete</button>
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

<div id="purchaseModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
        <form id="purchaseForm">
            <h3 class="text-2xl font-bold mb-4" id="modalTitle">Add New Purchase</h3>
            <input type="hidden" name="id" id="purchase_id">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold">Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="w-full border rounded p-2" required>
                        <option value="">Select Supplier...</option>
                        <?php foreach($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold">PO Number</label>
                    <input type="text" name="po_number" id="po_number" class="w-full border rounded p-2" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Category</label>
                    <select name="chart_of_account_id" id="chart_of_account_id" class="w-full border rounded p-2">
                        <option value="">None / Default</option>
                        <?php foreach($expense_accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Purchase Date</label>
                    <input type="date" name="purchase_date" id="purchase_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold">Due Date</label>
                    <input type="date" name="due_date" id="due_date" class="w-full border rounded p-2">
                </div>
                <div>
                    <label class="block text-sm font-semibold">Description</label>
                    <input type="text" name="description" id="description" class="w-full border rounded p-2">
                </div>
            </div>

            <div class="mb-4">
                <div class="flex justify-between items-center border-b pb-2 mb-2">
                    <h4 class="font-bold text-lg">Items</h4>
                    <button type="button" onclick="addItemRow()" class="text-blue-600 hover:text-blue-800 text-sm font-bold">+ Add Item</button>
                </div>
                <div id="item-lines" class="space-y-2">
                    </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('purchaseModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg font-bold">Save Purchase</button>
            </div>
        </form>
    </div>
</div>

<script>
    const itemsList = <?php echo json_encode($items_list); ?>;

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function handleApiResponse(response, modalId) {
        if (response.success) {
            alert(response.message || 'Success!');
            location.reload();
        } else {
            alert('Error: ' + response.message);
        }
        if(modalId) closeModal(modalId);
    }

    // --- BUTTON LOCKING ---
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

    // --- SUBMIT ---
    document.getElementById('purchaseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = lockButton(this);
        const isEditing = document.getElementById('purchase_id').value !== '';
        const url = isEditing ? 'api/update_purchase.php' : 'api/add_purchase.php';
        
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                handleApiResponse(data, 'purchaseModal');
            } else {
                alert(data.message);
                unlockButton(btn);
            }
        })
        .catch(err => { 
            alert('Network error'); 
            unlockButton(btn); 
        });
    });

    function openAddModal() {
        document.getElementById('purchaseForm').reset();
        document.getElementById('modalTitle').innerText = 'Add New Purchase';
        document.getElementById('purchase_id').value = '';
        document.getElementById('purchase_date').value = new Date().toISOString().slice(0, 10);
        document.getElementById('item-lines').innerHTML = '';
        addItemRow(); 
        openModal('purchaseModal');
    }

    function openEditModal(p) {
        document.getElementById('purchaseForm').reset();
        document.getElementById('modalTitle').innerText = 'Edit Purchase';
        document.getElementById('purchase_id').value = p.id;
        document.getElementById('supplier_id').value = p.supplier_id;
        document.getElementById('po_number').value = p.po_number;
        document.getElementById('chart_of_account_id').value = p.chart_of_account_id;
        document.getElementById('purchase_date').value = p.purchase_date;
        document.getElementById('due_date').value = p.due_date;
        document.getElementById('description').value = p.description;
        
        fetch(`api/get_purchase_details.php?id=${p.id}`)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const items = data.purchase.items || [];
                    const container = document.getElementById('item-lines');
                    container.innerHTML = '';
                    if(items.length > 0) {
                        items.forEach(item => addItemRow(item));
                    } else {
                        addItemRow();
                    }
                    openModal('purchaseModal');
                } else {
                    alert('Error loading items.');
                }
            });
    }

    function addItemRow(item = null) {
        const div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-2 items-center border p-2 rounded bg-gray-50';
        
        let options = '<option value="">Select Item</option>';
        itemsList.forEach(i => {
            const selected = (item && item.item_id == i.id) ? 'selected' : '';
            // Handle different price column names safely
            const price = i.cost_price || i.price || i.unit_price || 0;
            options += `<option value="${i.id}" ${selected} data-price="${price}">${i.item_name}</option>`;
        });

        div.innerHTML = `
            <div class="col-span-5">
                <select name="items[item_id][]" class="w-full border rounded p-1 text-sm" required onchange="updatePrice(this)">${options}</select>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[quantity][]" placeholder="Qty" class="w-full border rounded p-1 text-sm" value="${item ? item.quantity : ''}" step="any" required>
            </div>
            <div class="col-span-3">
                <input type="number" name="items[unit_price][]" placeholder="Price" class="w-full border rounded p-1 text-sm price-input" value="${item ? item.unit_price : ''}" step="any" required>
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
        if(price && priceInput.value === '') {
            priceInput.value = price;
        }
    }

    function deletePurchase(id) {
        if (confirm('Are you sure you want to delete this purchase? This cannot be undone.')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('api/delete_purchase.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>