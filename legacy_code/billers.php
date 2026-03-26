<?php
// billers.php
require_once "includes/header.php";
require_once "config/database.php";

$billers = [];
$sql = "SELECT id, biller_name FROM billers ORDER BY biller_name";
if($result = $conn->query($sql)) {
    while($row = $result->fetch_assoc()) { $billers[] = $row; }
}
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Biller Management</h2>
    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Add New Biller</button>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Biller Name</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($billers)): foreach ($billers as $biller): ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-bold"><?php echo htmlspecialchars($biller['biller_name']); ?></td>
                <td class="p-3 text-center space-x-2">
                    <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($biller)); ?>)" class="text-green-500 hover:underline">Edit</button>
                    <button onclick="deleteBiller(<?php echo $biller['id']; ?>)" class="text-red-500 hover:underline">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="2" class="p-3 text-center text-gray-500">No billers found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Biller Modal -->
<div id="billerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="billerForm">
            <input type="hidden" name="id" id="biller_id">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Biller</h3>
            <div>
                <label for="biller_name" class="block text-sm font-medium text-gray-700">Biller Name</label>
                <input type="text" name="biller_name" id="biller_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('billerModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Biller</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('billerModal');
    const modalTitle = document.getElementById('modalTitle');
    const billerForm = document.getElementById('billerForm');
    const billerIdField = document.getElementById('biller_id');

    function closeModal(id) { modal.classList.add('hidden'); billerForm.reset(); }
    function openAddModal() {
        modalTitle.innerText = 'Add New Biller';
        billerIdField.value = '';
        modal.classList.remove('hidden');
    }

    function openEditModal(biller) {
        modalTitle.innerText = 'Edit Biller';
        billerIdField.value = biller.id;
        document.getElementById('biller_name').value = biller.biller_name;
        modal.classList.remove('hidden');
    }

    billerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const isEditing = billerIdField.value !== '';
        const url = isEditing ? 'api/update_biller.php' : 'api/add_biller.php';
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    });

    function deleteBiller(id) {
        if (confirm('Are you sure you want to delete this biller?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('api/delete_biller.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>