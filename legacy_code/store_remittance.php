<?php
// store_remittance.php
require_once "includes/header.php";
require_once "config/database.php";

// FILTERS
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// FETCH DATA
$sql = "
    SELECT sr.*, coa.account_name as category,
    CASE 
        WHEN sr.destination_type = 'Bank' THEN (SELECT bank_name FROM passbooks WHERE id = sr.destination_id)
        ELSE (SELECT account_name FROM cash_accounts WHERE id = sr.destination_id)
    END as dest_name
    FROM store_remittances sr
    LEFT JOIN chart_of_accounts coa ON sr.chart_of_account_id = coa.id
    WHERE sr.remittance_date BETWEEN ? AND ?
    ORDER BY sr.remittance_date DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$remittances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// FETCH DROPDOWNS
$income_cats = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Income'")->fetch_all(MYSQLI_ASSOC);
$cash_accs = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$bank_accs = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);

$total_remitted = array_sum(array_column($remittances, 'amount'));
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Store Remittance</h2>
    <button onclick="openModal()" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow">
        + Add Remittance
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-6">
    <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase font-bold">Total Remitted (Selected Period)</p>
        <p class="text-2xl font-bold text-green-700">₱<?php echo number_format($total_remitted, 2); ?></p>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div><label class="text-sm font-bold">From</label><input type="date" name="start_date" value="<?php echo $start_date; ?>" class="w-full border rounded p-2"></div>
        <div><label class="text-sm font-bold">To</label><input type="date" name="end_date" value="<?php echo $end_date; ?>" class="w-full border rounded p-2"></div>
        <button type="submit" class="bg-gray-600 text-white font-bold py-2 rounded">Filter</button>
    </form>
</div>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-3 whitespace-nowrap">Date</th>
                <th class="p-3 whitespace-nowrap">Category</th>
                <th class="p-3 whitespace-nowrap">Destination</th>
                <th class="p-3 whitespace-nowrap">Description</th>
                <th class="p-3 text-right whitespace-nowrap">Amount</th>
                <th class="p-3 text-center whitespace-nowrap">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <?php if(empty($remittances)): ?>
                <tr><td colspan="6" class="p-4 text-center text-gray-500">No remittances found.</td></tr>
            <?php else: foreach($remittances as $r): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-3 whitespace-nowrap"><?php echo $r['remittance_date']; ?></td>
                    <td class="p-3 font-semibold text-blue-600 whitespace-nowrap"><?php echo htmlspecialchars($r['category']); ?></td>
                    <td class="p-3 whitespace-nowrap">
                        <span class="px-2 py-1 rounded text-xs font-bold <?php echo ($r['destination_type']=='Bank')?'bg-purple-100 text-purple-700':'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo $r['destination_type']; ?>
                        </span>
                        <span class="text-gray-600 text-xs block"><?php echo htmlspecialchars($r['dest_name']); ?></span>
                    </td>
                    <td class="p-3 min-w-[200px]"><?php echo htmlspecialchars($r['description']); ?></td>
                    <td class="p-3 text-right font-bold text-green-700 whitespace-nowrap">₱<?php echo number_format($r['amount'], 2); ?></td>
                    <td class="p-3 text-center whitespace-nowrap">
                        <button onclick="deleteRemittance(<?php echo $r['id']; ?>)" class="text-red-500 hover:text-red-700 font-bold text-xs">Delete</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div id="remitModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md w-11/12">
        <form id="remitForm">
            <h3 class="text-xl font-bold mb-4 text-green-800">Add Store Remittance</h3>
            <div class="space-y-3">
                <div><label class="text-sm font-bold">Date</label><input type="date" name="remittance_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required></div>
                
                <div><label class="text-sm font-bold">Income Category</label>
                    <select name="chart_of_account_id" class="w-full border rounded p-2" required>
                        <option value="">Select Category...</option>
                        <?php foreach($income_cats as $c) echo "<option value='{$c['id']}'>{$c['account_name']}</option>"; ?>
                    </select>
                </div>

                <div><label class="text-sm font-bold">Deposit To</label>
                    <select name="destination_account" class="w-full border rounded p-2" required>
                        <option value="">Select Account...</option>
                        <optgroup label="Cash Accounts">
                            <?php foreach($cash_accs as $c) echo "<option value='Cash-{$c['id']}'>{$c['account_name']}</option>"; ?>
                        </optgroup>
                        <optgroup label="Bank Accounts">
                            <?php foreach($bank_accs as $b) echo "<option value='Bank-{$b['id']}'>{$b['bank_name']}</option>"; ?>
                        </optgroup>
                    </select>
                </div>

                <div><label class="text-sm font-bold">Amount</label><input type="number" step="0.01" name="amount" class="w-full border rounded p-2 text-lg font-bold" required></div>
                
                <div><label class="text-sm font-bold">Description / Notes</label><textarea name="description" class="w-full border rounded p-2"></textarea></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('remitModal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){ document.getElementById('remitModal').classList.remove('hidden'); }

document.getElementById('remitForm').addEventListener('submit', function(e){
    e.preventDefault();
    if(!confirm("Confirm Remittance?")) return;
    
    fetch('api/add_store_remittance.php', {method:'POST', body:new FormData(this)})
    .then(r=>r.json()).then(d=>{
        if(d.success){ location.reload(); }
        else{ alert(d.message); }
    });
});

function deleteRemittance(id){
    if(confirm("Delete this remittance? This will deduct the amount from the account.")){
        let fd = new FormData(); fd.append('id', id);
        fetch('api/delete_store_remittance.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(d=>{
            if(d.success){ location.reload(); }
            else{ alert(d.message); }
        });
    }
}
</script>

<?php require_once "includes/footer.php"; ?>