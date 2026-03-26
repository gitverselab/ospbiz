<?php
// delivery_receipts.php
session_start();
require_once "includes/header.php";
require_once "config/database.php";

// --- FILTERING AND PAGINATION LOGIC ---
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_dr = $_GET['search_dr'] ?? '';
$search_po = $_GET['search_po'] ?? '';
$search_item = $_GET['search_item'] ?? '';
$search_status = $_GET['search_status'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_dr)) { $where_clauses[] = "dr.dr_number LIKE ?"; $params[] = "%$search_dr%"; $types .= 's'; }
if (!empty($search_po)) { $where_clauses[] = "dr.po_number LIKE ?"; $params[] = "%$search_po%"; $types .= 's'; }
if (!empty($search_item)) { $where_clauses[] = "(dr.item_code LIKE ? OR dr.description LIKE ?)"; $params[] = "%$search_item%"; $params[] = "%$search_item%"; $types .= 'ss'; }
if (!empty($search_status)) { $where_clauses[] = "dr.delivery_status = ?"; $params[] = $search_status; $types .= 's'; }
if (!empty($search_customer)) { $where_clauses[] = "dr.customer_id = ?"; $params[] = $search_customer; $types .= 'i'; }

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$count_sql = "SELECT COUNT(dr.id) as total FROM delivery_receipts dr {$where_sql}";
$stmt_count = $conn->prepare($count_sql);
if (count($params) > 0) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

$data_sql = "SELECT dr.*, c.customer_name 
             FROM delivery_receipts dr
             JOIN customers c ON dr.customer_id = c.id
             {$where_sql}
             ORDER BY dr.delivery_date DESC, dr.id DESC
             LIMIT ? OFFSET ?";
$data_types = $types . 'ii';
$data_params = array_merge($params, [$limit, $offset]);
$stmt_data = $conn->prepare($data_sql);
$stmt_data->bind_param($data_types, ...$data_params);
$stmt_data->execute();
$receipts = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_data->close();

$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Delivery Receipts Management</h2>
    <div>
        <a href="import_delivery_receipts.php" class="bg-teal-500 hover:bg-teal-600 text-white font-bold py-2 px-4 rounded-lg mr-2">Import CSV</a>
        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Add New Receipt</button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form action="delivery_receipts.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <input type="text" name="search_dr" value="<?php echo htmlspecialchars($search_dr); ?>" placeholder="Search DR Number..." class="w-full px-3 py-2 border border-gray-300 rounded-md">
        <input type="text" name="search_po" value="<?php echo htmlspecialchars($search_po); ?>" placeholder="Search PO Number..." class="w-full px-3 py-2 border border-gray-300 rounded-md">
        <input type="text" name="search_item" value="<?php echo htmlspecialchars($search_item); ?>" placeholder="Search Item Code/Desc..." class="w-full px-3 py-2 border border-gray-300 rounded-md">
        <select name="search_customer" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            <option value="">All Customers</option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?php echo $customer['id']; ?>" <?php echo $search_customer == $customer['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($customer['customer_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="search_status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            <option value="">All Statuses</option>
            <option value="Delivered" <?php echo $search_status == 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
            <option value="Pending" <?php echo $search_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Failed" <?php echo $search_status == 'Failed' ? 'selected' : ''; ?>>Failed</option>
            <option value="Returned" <?php echo $search_status == 'Returned' ? 'selected' : ''; ?>>Returned</option>
            <option value="Redelivered" <?php echo $search_status == 'Redelivered' ? 'selected' : ''; ?>>Redelivered</option>
        </select>
        <div class="flex items-center space-x-2">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Filter</button>
            <a href="delivery_receipts.php" class="w-full text-center bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Reset</a>
        </div>
    </form>
</div>


<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
         <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">DR # / GR #</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Delivery Date</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Customer</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Item Description</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-right">Qty</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-right">Total (ex-VAT)</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-right">Total (inc-VAT)</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-center">Status</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($receipts)): foreach ($receipts as $receipt): ?>
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($receipt['dr_number']); ?><br><small class="text-gray-500">GR: <?php echo htmlspecialchars($receipt['gr_number']); ?></small></td>
                <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($receipt['delivery_date']); ?></td>
                <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($receipt['customer_name']); ?></td>
                <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($receipt['description']); ?><br><small class="text-gray-500">Item: <?php echo htmlspecialchars($receipt['item_code']); ?></small></td>
                <td class="p-3 text-sm text-gray-700 text-right"><?php echo htmlspecialchars($receipt['quantity']) . ' ' . htmlspecialchars($receipt['uom']); ?></td>
                <td class="p-3 text-sm text-gray-700 text-right">₱<?php echo number_format($receipt['total_value'], 2); ?></td>
                <td class="p-3 text-sm text-gray-700 text-right font-semibold">₱<?php echo number_format($receipt['vat_inclusive_amount'], 2); ?></td>
                <td class="p-3 text-sm text-center">
                     <span class="px-2 py-1 text-xs font-semibold text-white rounded-full <?php 
                            switch($receipt['delivery_status']) {
                                case 'Delivered': echo 'bg-green-500'; break;
                                case 'Pending': echo 'bg-yellow-500'; break;
                                default: echo 'bg-gray-400';
                            }
                        ?>"><?php echo htmlspecialchars($receipt['delivery_status']); ?></span>
                </td>
                <td class="p-3 text-sm text-center">
                    <button onclick='openEditModal(<?php echo json_encode($receipt); ?>)' class="text-blue-500 hover:underline">Edit</button>
                    <button onclick="openDeleteModal(<?php echo $receipt['id']; ?>)" class="text-red-500 hover:underline ml-2">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" class="p-3 text-sm text-gray-500 text-center">No delivery receipts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex justify-between items-center">
    <span class="text-sm text-gray-600">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
    </span>
    <div class="flex items-center space-x-1">
        <?php 
        if ($total_pages > 1):
            $query_params = $_GET;
            
            if ($page > 1) {
                $query_params['page'] = $page - 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">&laquo; Prev</a>';
            }

            $max_links = 10;
            $start_page = max(1, $page - floor($max_links / 2));
            $end_page = min($total_pages, $start_page + $max_links - 1);
            
            if ($end_page - $start_page < $max_links - 1) {
                $start_page = max(1, $end_page - $max_links + 1);
            }

            if ($start_page > 1) {
                $query_params['page'] = 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">1</a>';
                if ($start_page > 2) {
                    echo '<span class="px-3 py-1">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                 $query_params['page'] = $i;
                 $is_active = $i == $page;
                 echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md ' . ($is_active ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-100') . '">' . $i . '</a>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span class="px-3 py-1">...</span>';
                }
                $query_params['page'] = $total_pages;
                echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">' . $total_pages . '</a>';
            }

            if ($page < $total_pages) {
                $query_params['page'] = $page + 1;
                echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">Next &raquo;</a>';
            }
        endif; 
        ?>
    </div>
</div>

<div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-3xl max-h-screen overflow-y-auto">
        <form id="receiptForm">
            <input type="hidden" name="id" id="id">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Delivery Receipt</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label>Customer</label><select name="customer_id" id="customer_id" class="mt-1 block w-full" required><?php foreach($customers as $c):?><option value="<?php echo $c['id'];?>"><?php echo htmlspecialchars($c['customer_name']);?></option><?php endforeach;?></select></div>
                <div><label>Delivery Date</label><input type="date" name="delivery_date" id="delivery_date" class="mt-1 block w-full" required></div>
                <div><label>DR Number</label><input type="text" name="dr_number" id="dr_number" class="mt-1 block w-full"></div>
                <div><label>PO Number</label><input type="text" name="po_number" id="po_number" class="mt-1 block w-full"></div>
                <div class="md:col-span-2"><label>Item Code</label><input type="text" name="item_code" id="item_code" class="mt-1 block w-full" required></div>
                <div class="md:col-span-2"><label>Description</label><textarea name="description" id="description" rows="2" class="mt-1 block w-full"></textarea></div>
                <div><label>Quantity</label><input type="number" step="any" name="quantity" id="quantity" class="mt-1 block w-full" required></div>
                <div><label>UOM</label><input type="text" name="uom" id="uom" class="mt-1 block w-full"></div>
                <div><label>Price (per unit, ex-VAT)</label><input type="number" step="any" name="price" id="price" class="mt-1 block w-full" required></div>
                <div><label>Delivery Status</label><select name="delivery_status" id="delivery_status" class="mt-1 block w-full"><option>Delivered</option><option>Pending</option></select></div>
            </div>
            <div class="mt-6 flex justify-end"><button type="button" onclick="closeModal('receiptModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button><button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg ml-2">Save</button></div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="deleteForm">
            <h3 class="text-xl font-bold mb-4">Confirm Deletion</h3>
            <p>Are you sure you want to delete this delivery receipt?</p>
            <input type="hidden" name="id" id="delete_id">
            <div class="mt-6 flex justify-end"><button type="button" onclick="closeModal('deleteModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button><button type="submit" class="bg-red-600 text-white py-2 px-4 rounded-lg ml-2">Delete</button></div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openAddModal() {
        document.getElementById('receiptForm').reset();
        document.getElementById('id').value = '';
        document.getElementById('modalTitle').innerText = 'Add New Delivery Receipt';
        openModal('receiptModal');
    }

    function openEditModal(receipt) {
        document.getElementById('receiptForm').reset();
        document.getElementById('modalTitle').innerText = 'Edit Delivery Receipt';
        document.getElementById('id').value = receipt.id;
        document.getElementById('customer_id').value = receipt.customer_id;
        document.getElementById('delivery_date').value = receipt.delivery_date;
        document.getElementById('dr_number').value = receipt.dr_number;
        document.getElementById('po_number').value = receipt.po_number;
        document.getElementById('item_code').value = receipt.item_code;
        document.getElementById('description').value = receipt.description;
        document.getElementById('quantity').value = receipt.quantity;
        document.getElementById('uom').value = receipt.uom;
        document.getElementById('price').value = receipt.price;
        document.getElementById('delivery_status').value = receipt.delivery_status;
        openModal('receiptModal');
    }

    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteModal');
    }

    document.getElementById('receiptForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        btn.disabled = true; btn.innerText = "Saving...";

        const isEditing = document.getElementById('id').value !== '';
        const url = isEditing ? 'api/update_delivery_receipt.php' : 'api/add_delivery_receipt.php';

        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else { alert('Error: ' + (data.message || 'Could not save receipt.')); btn.disabled = false; btn.innerText = originalText; }
        })
        .catch(err => { alert('Network error'); btn.disabled = false; btn.innerText = originalText; });
    });

    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/delete_delivery_receipt.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) { location.reload(); }
            else { alert('Error: ' + (data.message || 'Could not delete receipt.')); }
        });
    });
</script>

<?php require_once "includes/footer.php"; ?>