<?php
// fund_transfers.php
require_once "includes/header.php";
require_once "config/database.php";

// 1. ADMIN CHECK
$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$is_admin = false;

// Check Session
if ((isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin') || (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)) {
    $is_admin = true;
}
// Fallback DB Check
if (!$is_admin && $user_id > 0) {
    $sql = "SELECT 1 FROM app_user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = $user_id AND (r.role_name = 'Admin' OR ur.role_id = 1) LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) $is_admin = true;
}

// 2. FETCH ACCOUNTS
$accounts = [];
$pb_res = $conn->query("SELECT id, bank_name, account_number, current_balance FROM passbooks ORDER BY bank_name");
while($row = $pb_res->fetch_assoc()) {
    $accounts[] = ['type' => 'passbook', 'id' => $row['id'], 'name' => $row['bank_name'] . ' (' . $row['account_number'] . ')', 'bal' => $row['current_balance']];
}
$ca_res = $conn->query("SELECT id, account_name, current_balance FROM cash_accounts ORDER BY account_name");
while($row = $ca_res->fetch_assoc()) {
    $accounts[] = ['type' => 'cash', 'id' => $row['id'], 'name' => $row['account_name'], 'bal' => $row['current_balance']];
}

// 3. PAGINATION & FILTER
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where = ["1=1"];
if (!empty($_GET['from_date'])) $where[] = "ft.transfer_date >= '" . $conn->real_escape_string($_GET['from_date']) . "'";
if (!empty($_GET['to_date'])) $where[] = "ft.transfer_date <= '" . $conn->real_escape_string($_GET['to_date']) . "'";
$where_sql = implode(" AND ", $where);

// Count
$total_res = $conn->query("SELECT COUNT(*) as total FROM fund_transfers ft WHERE $where_sql");
$total_records = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Data - UPDATED: Join checks table
$sql = "
    SELECT ft.*, 
        CASE WHEN ft.from_account_type = 'Passbook' THEN pb_from.bank_name ELSE ca_from.account_name END as from_name,
        CASE WHEN ft.to_account_type = 'Passbook' THEN pb_to.bank_name ELSE ca_to.account_name END as to_name,
        c.check_number
    FROM fund_transfers ft
    LEFT JOIN passbooks pb_from ON ft.from_account_id = pb_from.id AND ft.from_account_type = 'Passbook'
    LEFT JOIN cash_accounts ca_from ON ft.from_account_id = ca_from.id AND ft.from_account_type = 'Cash Account'
    LEFT JOIN passbooks pb_to ON ft.to_account_id = pb_to.id AND ft.to_account_type = 'Passbook'
    LEFT JOIN cash_accounts ca_to ON ft.to_account_id = ca_to.id AND ft.to_account_type = 'Cash Account'
    LEFT JOIN checks c ON ft.check_id = c.id
    WHERE $where_sql
    ORDER BY ft.transfer_date DESC, ft.id DESC
    LIMIT $limit OFFSET $offset
";
$transfers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Fund Transfers</h2>
    <button onclick="openModal('transferModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">
        + New Transfer
    </button>
</div>

<div class="bg-white p-6 rounded-lg shadow-md">
    <form method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div><label class="text-xs font-bold text-gray-600">From</label><input type="date" name="from_date" value="<?php echo $_GET['from_date'] ?? ''; ?>" class="w-full border rounded p-2 text-sm"></div>
        <div><label class="text-xs font-bold text-gray-600">To</label><input type="date" name="to_date" value="<?php echo $_GET['to_date'] ?? ''; ?>" class="w-full border rounded p-2 text-sm"></div>
        <div>
            <label class="text-xs font-bold text-gray-600">Show Entries</label>
            <select name="limit" class="w-full border rounded p-2 text-sm" onchange="this.form.submit()">
                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
        <div><button type="submit" class="w-full bg-gray-600 text-white font-bold py-2 rounded text-sm hover:bg-gray-700">Filter</button></div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-600 uppercase border-b">
                <tr>
                    <th class="p-3">Date</th>
                    <th class="p-3">Source (Out)</th>
                    <th class="p-3">Dest (In)</th>
                    <th class="p-3 text-center">Method</th>
                    <th class="p-3 text-right">Amount</th>
                    <th class="p-3">Notes</th> 
                    <th class="p-3 text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if (empty($transfers)): ?>
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">No transfers found.</td></tr>
                <?php else: foreach ($transfers as $t): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3"><?php echo $t['transfer_date']; ?></td>
                        <td class="p-3 font-semibold text-red-600">
                            <?php echo htmlspecialchars($t['from_name']); ?>
                            <span class="text-xs text-gray-500 block"><?php echo $t['from_account_type']; ?></span>
                        </td>
                        <td class="p-3 font-semibold text-green-600">
                            <?php echo htmlspecialchars($t['to_name']); ?>
                            <span class="text-xs text-gray-500 block"><?php echo $t['to_account_type']; ?></span>
                        </td>
                        <td class="p-3 text-center">
                            <?php if (!empty($t['check_number'])): ?>
                                <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-bold border border-purple-200">
                                    Check #<?php echo htmlspecialchars($t['check_number']); ?>
                                </span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-bold">Direct</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-right font-bold">₱<?php echo number_format($t['amount'], 2); ?></td>
                        <td class="p-3 text-gray-600"><?php echo htmlspecialchars($t['notes']); ?></td>
                        <td class="p-3 text-center">
                            <?php if($is_admin): ?>
                                <button onclick="voidTransfer(<?php echo $t['id']; ?>)" class="text-red-500 hover:text-red-700 font-bold text-xs border border-red-200 px-2 py-1 rounded bg-red-50">VOID</button>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex justify-between items-center text-sm text-gray-600">
        <div>Showing <?php echo ($total_records > 0) ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?></div>
        <div class="flex gap-1">
            <?php 
                $q = $_GET; 
                if ($page > 1) { $q['page'] = $page - 1; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100">Prev</a>'; }
                if ($page < $total_pages) { $q['page'] = $page + 1; echo '<a href="?'.http_build_query($q).'" class="px-3 py-1 border rounded hover:bg-gray-100">Next</a>'; }
            ?>
        </div>
    </div>
</div>

<div id="transferModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="transferForm" action="api/add_fund_transfer.php" method="POST">
            <h3 class="text-xl font-bold mb-4 text-blue-800">New Transfer</h3>
            
            <div class="space-y-4">
                <div><label class="block text-sm font-bold">Date</label><input type="date" name="transfer_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required></div>

                <div class="p-3 bg-red-50 rounded border border-red-100">
                    <label class="block text-sm font-bold text-red-800">Source (Money Out)</label>
                    <select name="from_account" id="from_account" class="w-full border rounded p-2" onchange="checkBal()">
                        <option value="" data-bal="0">-- Select --</option>
                        <optgroup label="Banks">
                        <?php foreach($accounts as $acc): if($acc['type']=='passbook'): ?>
                            <option value="passbook-<?php echo $acc['id']; ?>" data-bal="<?php echo $acc['bal']; ?>"><?php echo $acc['name']; ?></option>
                        <?php endif; endforeach; ?>
                        </optgroup>
                        <optgroup label="Cash">
                        <?php foreach($accounts as $acc): if($acc['type']=='cash'): ?>
                            <option value="cash-<?php echo $acc['id']; ?>" data-bal="<?php echo $acc['bal']; ?>"><?php echo $acc['name']; ?></option>
                        <?php endif; endforeach; ?>
                        </optgroup>
                    </select>
                    <p class="text-right text-xs mt-1">Available: <span id="avail" class="font-bold text-red-600">0.00</span></p>
                </div>

                <div><label class="block text-sm font-bold">Amount</label><input type="number" step="0.01" name="amount" id="amount" class="w-full border rounded p-2 font-bold text-lg" required></div>

                <div class="p-3 bg-green-50 rounded border border-green-100">
                    <label class="block text-sm font-bold text-green-800">Destination (Money In)</label>
                    <select name="to_account" id="to_account" class="w-full border rounded p-2">
                        <option value="">-- Select --</option>
                        <optgroup label="Banks">
                        <?php foreach($accounts as $acc): if($acc['type']=='passbook'): ?>
                            <option value="passbook-<?php echo $acc['id']; ?>"><?php echo $acc['name']; ?></option>
                        <?php endif; endforeach; ?>
                        </optgroup>
                        <optgroup label="Cash">
                        <?php foreach($accounts as $acc): if($acc['type']=='cash'): ?>
                            <option value="cash-<?php echo $acc['id']; ?>"><?php echo $acc['name']; ?></option>
                        <?php endif; endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-bold">Method</label>
                    <select name="transfer_method" id="tm" class="w-full border rounded p-2" onchange="toggleC()">
                        <option value="Direct">Direct</option><option value="Check">Check</option>
                    </select>
                </div>
                <div id="cd" class="hidden space-y-2"><input type="text" name="check_number" placeholder="Check No." class="w-full border rounded p-2"><input type="date" name="check_date" class="w-full border rounded p-2"></div>
                <div><label class="block text-sm font-bold">Description / Notes</label><textarea name="notes" class="w-full border rounded p-2"></textarea></div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('transferModal')" class="bg-gray-200 py-2 px-4 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.remove('hidden');}
function closeModal(id){document.getElementById(id).classList.add('hidden');}
function toggleC(){document.getElementById('cd').classList.toggle('hidden', document.getElementById('tm').value !== 'Check');}
function checkBal(){
    const sel=document.getElementById('from_account');
    const bal=parseFloat(sel.options[sel.selectedIndex].getAttribute('data-bal'))||0;
    document.getElementById('avail').innerText=bal.toLocaleString(undefined,{minimumFractionDigits:2});
}
document.getElementById('transferForm').addEventListener('submit', function(e){
    e.preventDefault();
    const sel=document.getElementById('from_account');
    const bal=parseFloat(sel.options[sel.selectedIndex].getAttribute('data-bal'))||0;
    const amt=parseFloat(document.getElementById('amount').value)||0;
    
    if(amt<=0){alert("Invalid Amount");return;}
    if(amt>bal){alert("Insufficient Funds");return;}
    if(sel.value===document.getElementById('to_account').value){alert("Accounts must be different");return;}

    const btn=this.querySelector('button[type="submit"]');
    const old=btn.innerText; btn.disabled=true; btn.innerText="Processing...";
    
    fetch(this.action, {method:'POST', body:new FormData(this)})
    .then(r=>r.json()).then(d=>{
        if(d.success){alert("Success!");location.reload();}
        else{alert("Error: "+d.message);btn.disabled=false;btn.innerText=old;}
    }).catch(e=>{alert("Network Error");btn.disabled=false;btn.innerText=old;});
});
function voidTransfer(id){
    if(confirm("Void this transfer?")){
        const fd=new FormData(); fd.append('id', id);
        fetch('api/delete_fund_transfer.php',{method:'POST',body:fd})
        .then(r=>r.json()).then(d=>{
            if(d.success){alert(d.message);location.reload();}
            else{alert(d.message);}
        });
    }
}
</script>
<?php require_once "includes/footer.php"; ?>