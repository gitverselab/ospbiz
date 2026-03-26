<?php
// credits.php
require_once "includes/header.php";
require_once "config/database.php";

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;
$search_ref = $_GET['search_ref'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_ref)) {
    $where_clauses[] = "c.credit_ref_number LIKE ?";
    $params[] = "%$search_ref%";
    $types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "c.credit_date >= ?";
    $params[] = $start_date;
    $types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "c.credit_date <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : '';

$count_sql = "SELECT COUNT(c.id) as total FROM credits c $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt_count->close();

// Fetch Data with Subqueries for Received and Paid
$sql = "SELECT c.id, c.credit_date, c.creditor_name, c.amount, c.status, c.due_date, c.description, c.credit_ref_number,
        (SELECT COALESCE(SUM(amount), 0) FROM credit_receipts WHERE credit_id = c.id) as total_received,
        (SELECT COALESCE(SUM(cp.principal_amount), 0) FROM credit_payments cp LEFT JOIN checks ch ON cp.reference_id = ch.id AND cp.payment_method = 'Check' WHERE cp.credit_id = c.id AND (cp.payment_method != 'Check' OR ch.status = 'Cleared')) as total_paid
        FROM credits c
        $where_sql
        ORDER BY c.credit_date DESC
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $offset;
$params[] = $records_per_page;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$credits = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 no-print gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Credits & Loans</h2>
    <div class="w-full md:w-auto flex gap-2">
        <button onclick="window.print()" class="flex-1 md:flex-none bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg text-sm">Print</button>
        <button onclick="openAddModal()" class="flex-1 md:flex-none bg-blue-600 text-white font-bold py-2 px-4 rounded-lg text-sm">+ Add New</button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="credits.php" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div><label class="text-xs font-bold text-gray-700">Search Ref #</label><input type="text" name="search_ref" value="<?php echo htmlspecialchars($search_ref); ?>" class="w-full border rounded p-2 text-sm"></div>
        <div><label class="text-xs font-bold text-gray-700">From Date</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="w-full border rounded p-2 text-sm"></div>
        <div><label class="text-xs font-bold text-gray-700">To Date</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="w-full border rounded p-2 text-sm"></div>
        <div class="flex space-x-2">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg w-full text-sm">Filter</button>
            <a href="credits.php" class="bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg w-full text-center text-sm leading-[20px]">Reset</a>
        </div>
    </form>
</div>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left whitespace-nowrap">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3">Date</th>
                <th class="p-2 md:p-3">Ref #</th>
                <th class="p-2 md:p-3">Creditor</th>
                <th class="p-2 md:p-3 text-right">Total Approved</th>
                <th class="p-2 md:p-3 text-right text-blue-700">Received (Debt)</th>
                <th class="p-2 md:p-3 text-right text-red-600">Balance Due</th>
                <th class="p-2 md:p-3 text-center">Status</th>
                <th class="p-2 md:p-3 text-center no-print">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php if (!empty($credits)): foreach ($credits as $credit):
                $balance_due = $credit['total_received'] - $credit['total_paid'];
            ?>
            <tr class="hover:bg-gray-50">
                <td class="p-2 md:p-3"><?php echo htmlspecialchars($credit['credit_date']); ?></td>
                <td class="p-2 md:p-3 font-mono font-bold text-gray-700"><?php echo htmlspecialchars($credit['credit_ref_number'] ?? ''); ?></td>
                <td class="p-2 md:p-3 font-bold"><?php echo htmlspecialchars($credit['creditor_name']); ?></td>
                <td class="p-2 md:p-3 text-right text-gray-500">₱<?php echo number_format($credit['amount'], 2); ?></td>
                <td class="p-2 md:p-3 text-right font-bold text-blue-700">₱<?php echo number_format($credit['total_received'], 2); ?></td>
                <td class="p-2 md:p-3 text-right font-bold <?php echo ($balance_due > 0) ? 'text-red-600' : 'text-green-600'; ?>">₱<?php echo number_format(max(0,$balance_due), 2); ?></td>
                <td class="p-2 md:p-3 text-center">
                    <span class="px-2 py-0.5 text-[10px] md:text-xs font-semibold rounded border <?php 
                        if ($credit['status'] == 'Paid') echo 'bg-green-50 text-green-800 border-green-200';
                        elseif ($credit['status'] == 'Received') echo 'bg-blue-50 text-blue-800 border-blue-200';
                        elseif ($credit['status'] == 'Partially Received') echo 'bg-purple-50 text-purple-800 border-purple-200';
                        else echo 'bg-yellow-50 text-yellow-800 border-yellow-200'; 
                    ?>"><?php echo htmlspecialchars($credit['status']); ?></span>
                </td>
                <td class="p-2 md:p-3 text-center space-x-2 no-print">
                    <a href="view_credit.php?id=<?php echo $credit['id']; ?>" class="text-blue-600 hover:underline font-bold">Manage</a>
                    <?php if ($credit['status'] === 'Pending'): ?>
                        <button onclick='openEditModal(<?php echo $credit["id"]; ?>)' class="text-green-600 hover:underline">Edit</button>
                        <button onclick="deleteCredit(<?php echo $credit['id']; ?>)" class="text-red-600 hover:underline">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" class="p-4 text-center text-gray-500">No credits found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="creditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg w-11/12">
        <form id="creditForm">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Credit</h3>
            <input type="hidden" name="id" id="credit_id">
            <div class="space-y-3">
                <div><label class="text-sm font-bold">Date</label><input type="date" name="credit_date" id="credit_date" class="w-full border rounded p-2" required></div>
                <div><label class="text-sm font-bold">Creditor Name</label><input type="text" name="creditor_name" id="creditor_name" class="w-full border rounded p-2" required></div>
                <div><label class="text-sm font-bold">Ref #</label><input type="text" name="credit_ref_number" id="credit_ref_number" class="w-full border rounded p-2"></div>
                <div><label class="text-sm font-bold">Total Approved Loan</label><input type="number" name="amount" id="amount" step="0.01" class="w-full border rounded p-2" required></div>
                <div><label class="text-sm font-bold">Due Date</label><input type="date" name="due_date" id="due_date" class="w-full border rounded p-2"></div>
                <div><label class="text-sm font-bold">Description</label><textarea name="description" id="description" class="w-full border rounded p-2"></textarea></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="closeModal('creditModal')" class="bg-gray-200 py-2 px-4 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded text-sm font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Add New Credit';
        document.getElementById('creditForm').reset();
        document.getElementById('credit_id').value = '';
        document.getElementById('credit_date').value = new Date().toISOString().slice(0, 10);
        openModal('creditModal');
    }

    function openEditModal(id) {
        fetch(`api/get_credit_details.php?id=${id}`).then(r => r.json()).then(d => {
            if (d.success) {
                const c = d.data;
                document.getElementById('modalTitle').innerText = 'Edit Credit';
                document.getElementById('credit_id').value = c.id;
                document.getElementById('credit_date').value = c.credit_date;
                document.getElementById('creditor_name').value = c.creditor_name;
                document.getElementById('credit_ref_number').value = c.credit_ref_number;
                document.getElementById('amount').value = c.amount;
                document.getElementById('due_date').value = c.due_date;
                document.getElementById('description').value = c.description;
                openModal('creditModal');
            } else alert(d.message);
        });
    }
    
    document.getElementById('creditForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const ogText = btn.innerText; btn.disabled = true; btn.innerText = "Saving...";
        const url = document.getElementById('credit_id').value ? 'api/update_credit.php' : 'api/add_credit.php';
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(r => r.json()).then(d => {
            if (d.success) location.reload(); else { alert(d.message); btn.disabled = false; btn.innerText = ogText; }
        }).catch(err => { alert('Network error'); btn.disabled = false; btn.innerText = ogText; });
    });

    function deleteCredit(id) {
        if (confirm('Delete this pending credit?')) {
            let fd = new FormData(); fd.append('id', id);
            fetch('api/delete_credit.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d=>{
                if(d.success) location.reload(); else alert(d.message);
            });
        }
    }
</script>
<?php require_once "includes/footer.php"; ?>