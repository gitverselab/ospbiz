<?php
// projects.php
require_once "includes/header.php";
require_once "config/database.php";

// Fetch Projects with financial summary
$sql = "
    SELECT p.*, 
    (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE project_id = p.id) as total_expenses,
    (SELECT COALESCE(SUM(amount), 0) FROM purchases WHERE project_id = p.id AND status != 'Canceled') as total_purchases
    FROM projects p 
    ORDER BY p.start_date DESC
";
$projects = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Project Management</h2>
    <button onclick="openModal('projectModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
        + New Project
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($projects as $p): 
        $total_spent = $p['total_expenses'] + $p['total_purchases'];
        $progress = ($p['budget'] > 0) ? ($total_spent / $p['budget']) * 100 : 0;
        $progress_color = ($progress > 100) ? 'bg-red-500' : 'bg-green-500';
    ?>
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition cursor-pointer" onclick="window.location='view_project.php?id=<?php echo $p['id']; ?>'">
        <div class="flex justify-between items-start mb-2">
            <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($p['project_name']); ?></h3>
            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 font-bold"><?php echo $p['status']; ?></span>
        </div>
        <p class="text-gray-500 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($p['description']); ?></p>
        
        <div class="mb-4">
            <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                <span>Budget Used</span>
                <span><?php echo number_format($progress, 1); ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="<?php echo $progress_color; ?> h-2.5 rounded-full" style="width: <?php echo min(100, $progress); ?>%"></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500 text-xs">Total Budget</p>
                <p class="font-bold">₱<?php echo number_format($p['budget'], 2); ?></p>
            </div>
            <div class="text-right">
                <p class="text-gray-500 text-xs">Total Spent</p>
                <p class="font-bold text-red-600">₱<?php echo number_format($total_spent, 2); ?></p>
            </div>
        </div>
        
        <div class="mt-4 pt-4 border-t text-xs text-gray-500 flex justify-between">
            <span>Start: <?php echo date('M d, Y', strtotime($p['start_date'])); ?></span>
            <span>End: <?php echo $p['end_date'] ? date('M d, Y', strtotime($p['end_date'])) : 'Ongoing'; ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="projectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="projectForm">
            <h3 class="text-xl font-bold mb-4">Create New Project</h3>
            <div class="space-y-3">
                <div><label>Project Name</label><input type="text" name="project_name" class="w-full border rounded p-2" required></div>
                <div><label>Budget</label><input type="number" step="0.01" name="budget" class="w-full border rounded p-2" required></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label>Start Date</label><input type="date" name="start_date" class="w-full border rounded p-2" required></div>
                    <div><label>Target End Date</label><input type="date" name="end_date" class="w-full border rounded p-2"></div>
                </div>
                <div><label>Status</label>
                    <select name="status" class="w-full border rounded p-2">
                        <option>Planning</option><option>In Progress</option><option>On Hold</option><option>Completed</option>
                    </select>
                </div>
                <div><label>Description (Goal)</label><textarea name="description" class="w-full border rounded p-2"></textarea></div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('projectModal').classList.add('hidden')" class="bg-gray-200 px-4 py-2 rounded">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create Project</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
document.getElementById('projectForm').addEventListener('submit', function(e){
    e.preventDefault();
    fetch('api/add_project.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(d => {
        if(d.success) location.reload();
        else alert(d.message);
    });
});
</script>

<?php require_once "includes/footer.php"; ?>