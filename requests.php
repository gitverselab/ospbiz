<?php
// requests.php
require_once "includes/header.php";
require_once "config/database.php";

// Fetch Requests Summary
$sql = "
    SELECT r.*, 
    (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE request_id = r.id) as total_expenses,
    (SELECT COALESCE(SUM(amount), 0) FROM purchases WHERE request_id = r.id AND status != 'Canceled') as total_purchases
    FROM requests r 
    ORDER BY r.due_date ASC
";
$requests = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Requests & Maintenance</h2>
    <button onclick="openModal('requestModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
        + New Request
    </button>
</div>

<div class="mb-6 flex gap-2">
    <button onclick="filterRequests('All')" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 font-bold text-sm">All</button>
    <button onclick="filterRequests('Vehicle Maintenance')" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 font-bold text-sm">Vehicle Maint.</button>
    <button onclick="filterRequests('Scheduled Purchase')" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 font-bold text-sm">Scheduled Purchase</button>
    <button onclick="filterRequests('General Maintenance')" class="px-3 py-1 rounded bg-gray-200 hover:bg-gray-300 font-bold text-sm">General Maint.</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="requestGrid">
    <?php foreach ($requests as $r): 
        $total_spent = $r['total_expenses'] + $r['total_purchases'];
        $progress = ($r['estimated_cost'] > 0) ? ($total_spent / $r['estimated_cost']) * 100 : 0;
        
        // Status Badge Color
        $badge_color = 'bg-gray-100 text-gray-800';
        if ($r['status'] == 'Approved') $badge_color = 'bg-blue-100 text-blue-800';
        if ($r['status'] == 'In Progress') $badge_color = 'bg-yellow-100 text-yellow-800';
        if ($r['status'] == 'Completed') $badge_color = 'bg-green-100 text-green-800';
    ?>
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition cursor-pointer request-card" 
         data-category="<?php echo htmlspecialchars($r['category']); ?>"
         onclick="window.location='view_request.php?id=<?php echo $r['id']; ?>'">
        
        <div class="flex justify-between items-start mb-2">
            <div>
                <span class="text-xs font-bold text-purple-600 uppercase tracking-wide"><?php echo htmlspecialchars($r['category']); ?></span>
                <h3 class="text-xl font-bold text-gray-800 mt-1"><?php echo htmlspecialchars($r['request_name']); ?></h3>
            </div>
            <span class="px-2 py-1 text-xs rounded-full font-bold <?php echo $badge_color; ?>"><?php echo $r['status']; ?></span>
        </div>
        
        <p class="text-gray-500 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($r['description']); ?></p>
        
        <div class="mb-4">
            <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                <span>Cost vs Estimate</span>
                <span><?php echo number_format($progress, 0); ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min(100, $progress); ?>%"></div>
            </div>
        </div>

        <div class="flex justify-between text-sm mt-4 pt-4 border-t">
            <div>
                <p class="text-gray-400 text-xs">Estimate</p>
                <p class="font-bold">₱<?php echo number_format($r['estimated_cost'], 2); ?></p>
            </div>
            <div class="text-right">
                <p class="text-gray-400 text-xs">Actual Spent</p>
                <p class="font-bold text-red-600">₱<?php echo number_format($total_spent, 2); ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="requestModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="requestForm">
            <h3 class="text-xl font-bold mb-4">Create New Request</h3>
            <div class="space-y-3">
                <div><label>Request Title</label><input type="text" name="request_name" class="w-full border rounded p-2" required></div>
                <div><label>Category</label>
                    <select name="category" class="w-full border rounded p-2">
                        <option>Vehicle Maintenance</option>
                        <option>Scheduled Purchase</option>
                        <option>General Maintenance</option>
                        <option>IT/System Upgrade</option>
                        <option>Other</option>
                    </select>
                </div>
                <div><label>Estimated Cost</label><input type="number" step="0.01" name="estimated_cost" class="w-full border rounded p-2"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label>Request Date</label><input type="date" name="request_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required></div>
                    <div><label>Due Date</label><input type="date" name="due_date" class="w-full border rounded p-2"></div>
                </div>
                <div><label>Status</label>
                    <select name="status" class="w-full border rounded p-2">
                        <option>Pending</option><option>Approved</option><option>In Progress</option>
                    </select>
                </div>
                <div><label>Description</label><textarea name="description" class="w-full border rounded p-2"></textarea></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('requestModal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
document.getElementById('requestForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('api/add_request.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => {
        if(d.success) location.reload(); else alert('Error saving request');
    });
});

function filterRequests(cat) {
    const cards = document.querySelectorAll('.request-card');
    cards.forEach(card => {
        if (cat === 'All' || card.dataset.category === cat) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once "includes/footer.php"; ?>