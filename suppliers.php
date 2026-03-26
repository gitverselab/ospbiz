<?php
// suppliers.php
require_once "includes/header.php";
require_once "config/database.php";

// --- PAGINATION & SEARCH ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

// Build Query
$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    // Search in Supplier Info OR in the Items they supply
    $where .= " AND (
        s.supplier_name LIKE ? OR 
        s.contact_person LIKE ? OR 
        s.contact_number LIKE ? OR
        s.contact_email LIKE ? OR
        EXISTS (
            SELECT 1 FROM purchases p 
            JOIN purchase_items pi ON p.id = pi.purchase_id 
            JOIN items i ON pi.item_id = i.id 
            WHERE p.supplier_id = s.id AND i.item_name LIKE ?
        )
    )";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
    $types = "sssss";
}

// 1. Count Total
$count_sql = "SELECT COUNT(*) as total FROM suppliers s $where";
$stmt = $conn->prepare($count_sql);
if(!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// 2. Fetch Data (With Items Aggregation)
// We join purchases/items to show what they supply
$sql = "
    SELECT 
        s.*,
        (
            SELECT GROUP_CONCAT(DISTINCT i.item_name SEPARATOR ', ')
            FROM purchases p
            JOIN purchase_items pi ON p.id = pi.purchase_id
            JOIN items i ON pi.item_id = i.id
            WHERE p.supplier_id = s.id
            LIMIT 5
        ) as supplied_items
    FROM suppliers s
    $where
    ORDER BY s.supplier_name ASC
    LIMIT ?, ?
";

// Append limit params
$types .= "ii";
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-3xl font-bold text-gray-800">Suppliers Management</h2>
    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">
        + Add New Supplier
    </button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-grow w-full">
            <label class="text-xs font-bold text-gray-600 uppercase">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Name, Contact, Email, or Item..." 
                   class="w-full border rounded p-2 text-sm">
        </div>
        <div>
            <label class="text-xs font-bold text-gray-600 uppercase">Show</label>
            <select name="limit" class="border rounded p-2 text-sm" onchange="this.form.submit()">
                <option value="10" <?php if($limit==10) echo 'selected'; ?>>10</option>
                <option value="20" <?php if($limit==20) echo 'selected'; ?>>20</option>
                <option value="50" <?php if($limit==50) echo 'selected'; ?>>50</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded text-sm font-bold">Filter</button>
    </form>
</div>

<div class="bg-white rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="bg-gray-100 text-gray-600 uppercase border-b">
            <tr>
                <th class="p-3">Supplier Name</th>
                <th class="p-3">Contact Person</th>
                <th class="p-3">Contact #</th>
                <th class="p-3">Items Supplied</th>
                <th class="p-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php if (empty($suppliers)): ?>
                <tr><td colspan="5" class="p-6 text-center text-gray-500">No suppliers found.</td></tr>
            <?php else: foreach ($suppliers as $s): ?>
            <tr class="hover:bg-gray-50 group">
                <td class="p-3">
                    <span class="font-bold text-gray-800 block"><?php echo htmlspecialchars($s['supplier_name']); ?></span>
                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($s['contact_email']); ?></span>
                </td>
                <td class="p-3"><?php echo htmlspecialchars($s['contact_person'] ?? '-'); ?></td>
                <td class="p-3 font-mono text-gray-700"><?php echo htmlspecialchars($s['contact_number'] ?? '-'); ?></td>
                <td class="p-3">
                    <?php if(!empty($s['supplied_items'])): ?>
                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded border border-blue-100">
                            <?php 
                                $items = $s['supplied_items'];
                                echo (strlen($items) > 50) ? substr($items, 0, 50) . '...' : $items; 
                            ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs text-gray-400 italic">No history</span>
                    <?php endif; ?>
                </td>
                <td class="p-3 text-center space-x-2 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="openEditModal(<?php echo $s['id']; ?>)" class="text-green-600 hover:underline font-bold">Edit</button>
                    <button onclick="deleteSupplier(<?php echo $s['id']; ?>)" class="text-red-600 hover:underline">Delete</button>
                </td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-4 flex justify-between items-center text-sm text-gray-600">
    <div>Showing <?php echo $total_records > 0 ? $offset+1 : 0; ?> to <?php echo min($offset+$limit, $total_records); ?> of <?php echo $total_records; ?></div>
    <div class="flex gap-1">
        <?php if($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1 border rounded hover:bg-white">Prev</a>
        <?php endif; ?>
        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1 border rounded hover:bg-white">Next</a>
        <?php endif; ?>
    </div>
</div>

<div id="supplierModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="supplierForm">
            <input type="hidden" name="id" id="supplier_id">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Add Supplier</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700">Supplier Name <span class="text-red-500">*</span></label>
                    <input type="text" name="supplier_name" id="supplier_name" class="w-full border rounded p-2 focus:ring-2 focus:ring-blue-500 outline-none" required>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Contact Person</label>
                    <input type="text" name="contact_person" id="contact_person" class="w-full border rounded p-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Contact Number</label>
                    <input type="text" name="contact_number" id="contact_number" class="w-full border rounded p-2" placeholder="e.g. 0917-123-4567">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Email</label>
                    <input type="email" name="contact_email" id="contact_email" class="w-full border rounded p-2">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="bg-gray-200 py-2 px-4 rounded text-gray-700 font-bold hover:bg-gray-300">Cancel</button>
                <button type="submit" class="bg-blue-600 py-2 px-4 rounded text-white font-bold hover:bg-blue-700">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('supplierModal');
const form = document.getElementById('supplierForm');

function openAddModal() {
    form.reset();
    document.getElementById('supplier_id').value = '';
    document.getElementById('modalTitle').innerText = 'Add New Supplier';
    modal.classList.remove('hidden');
}

function openEditModal(id) {
    document.getElementById('modalTitle').innerText = 'Edit Supplier';
    fetch(`api/get_supplier_details.php?id=${id}`)
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                document.getElementById('supplier_id').value = d.data.id;
                document.getElementById('supplier_name').value = d.data.supplier_name;
                document.getElementById('contact_person').value = d.data.contact_person;
                document.getElementById('contact_number').value = d.data.contact_number || '';
                document.getElementById('contact_email').value = d.data.contact_email;
                modal.classList.remove('hidden');
            } else { alert(d.message); }
        });
}

function closeModal() { modal.classList.add('hidden'); }

form.addEventListener('submit', function(e) {
    e.preventDefault();
    const isEdit = document.getElementById('supplier_id').value;
    const url = isEdit ? 'api/update_supplier.php' : 'api/add_supplier.php';
    
    fetch(url, { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => {
        if(d.success) { location.reload(); }
        else { alert(d.message); }
    });
});

function deleteSupplier(id) {
    if(confirm('Are you sure? This cannot be undone.')) {
        const fd = new FormData(); fd.append('id', id);
        fetch('api/delete_supplier.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if(d.success) location.reload();
            else alert(d.message);
        });
    }
}
</script>

<?php require_once "includes/footer.php"; ?>