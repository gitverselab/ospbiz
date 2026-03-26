<?php
// rts_receipts.php
require_once "includes/header.php";
require_once "config/database.php";

// --- FILTERING & PAGINATION ---
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_rts = $_GET['search_rts'] ?? ''; // GR Number
$search_rd = $_GET['search_rd'] ?? '';   // RD Number
$search_po = $_GET['search_po'] ?? '';
$search_customer = $_GET['search_customer'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_rts)) { $where_clauses[] = "r.rts_number LIKE ?"; $params[] = "%$search_rts%"; $types .= 's'; }
if (!empty($search_rd)) { $where_clauses[] = "r.rd_number LIKE ?"; $params[] = "%$search_rd%"; $types .= 's'; }
if (!empty($search_po)) { $where_clauses[] = "r.po_number LIKE ?"; $params[] = "%$search_po%"; $types .= 's'; }
if (!empty($search_customer)) { $where_clauses[] = "c.customer_name LIKE ?"; $params[] = "%$search_customer%"; $types .= 's'; }

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

// Count Total
$count_sql = "SELECT COUNT(r.id) as total FROM return_receipts r JOIN customers c ON r.customer_id = c.id $where_sql";
$stmt = $conn->prepare($count_sql);
if (!empty($types)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch Data
$sql = "SELECT r.*, c.customer_name 
        FROM return_receipts r 
        JOIN customers c ON r.customer_id = c.id 
        $where_sql 
        ORDER BY r.rts_date DESC, r.id DESC 
        LIMIT ?, ?";
$types .= 'ii';
$params[] = $offset;
$params[] = $limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rts_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Customers for Modal
$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Return Receipts (RTS)</h2>
    <div>
        <a href="import_rts_receipts.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg mr-2">Import CSV</a>
        <button onclick="openModal('rtsModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Add Manual RTS</button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
        <div><label class="text-xs font-bold text-gray-700">GR Number (RTS)</label><input type="text" name="search_rts" value="<?php echo htmlspecialchars($search_rts); ?>" class="w-full border rounded p-2"></div>
        <div><label class="text-xs font-bold text-gray-700">RD Number</label><input type="text" name="search_rd" value="<?php echo htmlspecialchars($search_rd); ?>" class="w-full border rounded p-2"></div>
        <div><label class="text-xs font-bold text-gray-700">PO Number</label><input type="text" name="search_po" value="<?php echo htmlspecialchars($search_po); ?>" class="w-full border rounded p-2"></div>
        <div><label class="text-xs font-bold text-gray-700">Customer</label><input type="text" name="search_customer" value="<?php echo htmlspecialchars($search_customer); ?>" class="w-full border rounded p-2"></div>
        <div><button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded w-full">Filter</button></div>
    </form>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-3 text-left">Date</th>
                <th class="p-3 text-left">GR # (RTS)</th>
                <th class="p-3 text-left">RD #</th>
                <th class="p-3 text-left">Customer</th>
                <th class="p-3 text-left">Item / Description</th>
                <th class="p-3 text-right">Qty</th>
                <th class="p-3 text-right">Amount</th>
                <th class="p-3 text-center">Status</th>
                <th class="p-3 text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rts_data)): ?>
                <tr><td colspan="9" class="p-4 text-center text-gray-500">No return receipts found.</td></tr>
            <?php else: foreach ($rts_data as $row): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3"><?php echo htmlspecialchars($row['rts_date']); ?></td>
                    <td class="p-3 font-mono font-bold"><?php echo htmlspecialchars($row['rts_number']); ?></td>
                    <td class="p-3 font-mono text-gray-600"><?php echo htmlspecialchars($row['rd_number'] ?? '-'); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                    <td class="p-3">
                        <span class="font-bold"><?php echo htmlspecialchars($row['item_code']); ?></span><br>
                        <span class="text-xs text-gray-600"><?php echo htmlspecialchars($row['description']); ?></span>
                    </td>
                    <td class="p-3 text-right"><?php echo number_format($row['quantity'], 2) . ' ' . $row['uom']; ?></td>
                    <td class="p-3 text-right font-bold text-red-600">-₱<?php echo number_format($row['total_amount'], 2); ?></td>
                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo ($row['status'] == 'Pending') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>
                    <td class="p-3 text-center space-x-2">
                        <?php if ($row['status'] == 'Pending'): ?>
                            <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="text-blue-600 hover:underline">Edit</button>
                            <button onclick="deleteRTS(<?php echo $row['id']; ?>)" class="text-red-600 hover:underline">Delete</button>
                        <?php else: ?>
                            <span class="text-gray-400 italic">Deducted</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div id="rtsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg overflow-y-auto max-h-screen">
        <form id="rtsForm">
            <h3 class="text-xl font-bold mb-4" id="modalTitle">Add New RTS</h3>
            <input type="hidden" name="id" id="rts_id">
            <div class="space-y-4">
                <div><label>Customer</label>
                    <select name="customer_id" id="customer_id" class="w-full border rounded p-2" required>
                        <option value="">Select Customer</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label>GR Number (RTS)</label><input type="text" name="rts_number" id="rts_number" class="w-full border rounded p-2" required></div>
                    <div><label>RTS Date</label><input type="date" name="rts_date" id="rts_date" class="w-full border rounded p-2" required></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label>PO Number</label><input type="text" name="po_number" id="po_number" class="w-full border rounded p-2"></div>
                    <div><label>RD Number</label><input type="text" name="rd_number" id="rd_number" class="w-full border rounded p-2"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label>Item Code</label><input type="text" name="item_code" id="item_code" class="w-full border rounded p-2" required></div>
                    <div><label>UOM</label><input type="text" name="uom" id="uom" class="w-full border rounded p-2"></div>
                </div>
                <div><label>Description</label><input type="text" name="description" id="description" class="w-full border rounded p-2" required></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label>Quantity</label><input type="number" step="0.0001" name="quantity" id="quantity" class="w-full border rounded p-2" required></div>
                    <div><label>Total Amount (Inc. VAT)</label><input type="number" step="0.01" name="total_amount" id="total_amount" class="w-full border rounded p-2" required></div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('rtsModal')" class="bg-gray-200 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Edit RTS';
        document.getElementById('rts_id').value = data.id;
        document.getElementById('customer_id').value = data.customer_id;
        document.getElementById('rts_number').value = data.rts_number;
        document.getElementById('rd_number').value = data.rd_number;
        document.getElementById('po_number').value = data.po_number;
        document.getElementById('rts_date').value = data.rts_date;
        document.getElementById('item_code').value = data.item_code;
        document.getElementById('description').value = data.description;
        document.getElementById('quantity').value = data.quantity;
        document.getElementById('uom').value = data.uom;
        document.getElementById('total_amount').value = data.total_amount;
        openModal('rtsModal');
    }

    document.getElementById('rtsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const url = document.getElementById('rts_id').value ? 'api/update_rts.php' : 'api/add_rts.php';
        fetch(url, { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if(data.success) location.reload();
                else alert(data.message);
            });
    });

    function deleteRTS(id) {
        if(confirm('Are you sure?')) {
            const fd = new FormData(); fd.append('id', id);
            fetch('api/delete_rts.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.success) location.reload();
                    else alert(data.message);
                });
        }
    }
</script>
<?php require_once "includes/footer.php"; ?>