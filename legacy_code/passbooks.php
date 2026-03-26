<?php
// passbooks.php

require_once "includes/header.php";
require_once "config/database.php";

// Fetch passbooks
$passbooks = [];
$sql = "SELECT id, bank_name, account_number, account_holder, current_balance FROM passbooks ORDER BY bank_name ASC";
if ($result = $conn->query($sql)) {
    while($row = $result->fetch_assoc()) {
        $passbooks[] = $row;
    }
}
$conn->close();
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Passbook Management</h2>
    <button onclick="openAddModal()" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">
        + Add New Passbook
    </button>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3 font-semibold whitespace-nowrap">Bank Name</th>
                <th class="p-2 md:p-3 font-semibold whitespace-nowrap">Account #</th>
                <th class="p-2 md:p-3 font-semibold whitespace-nowrap">Account Holder</th>
                <th class="p-2 md:p-3 font-semibold text-right whitespace-nowrap">Current Balance</th>
                <th class="p-2 md:p-3 font-semibold text-center whitespace-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (!empty($passbooks)): foreach ($passbooks as $passbook): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 md:p-3 font-bold text-gray-800 whitespace-nowrap"><?php echo htmlspecialchars($passbook['bank_name']); ?></td>
                    <td class="p-2 md:p-3 font-mono text-gray-600 whitespace-nowrap"><?php echo htmlspecialchars($passbook['account_number']); ?></td>
                    <td class="p-2 md:p-3 text-gray-700 whitespace-nowrap"><?php echo htmlspecialchars($passbook['account_holder']); ?></td>
                    <td class="p-2 md:p-3 text-right font-bold text-green-700 whitespace-nowrap">₱<?php echo number_format($passbook['current_balance'], 2); ?></td>
                    <td class="p-2 md:p-3 text-center whitespace-nowrap">
                        <div class="flex justify-center gap-3">
                            <a href="passbook_view.php?id=<?php echo $passbook['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">View</a>
                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($passbook), ENT_QUOTES, 'UTF-8'); ?>)' class="text-green-600 hover:text-green-800 font-medium">Edit</button>
                            <button onclick='openDeleteModal(<?php echo $passbook["id"]; ?>)' class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="p-6 text-center text-gray-500 italic">No passbooks found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="passbookModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg md:w-full w-11/12">
        <form id="passbookForm" method="POST">
            <h3 id="modalTitle" class="text-xl font-bold mb-4 text-gray-800">Add New Passbook</h3>
            <input type="hidden" name="id" id="passbook_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700">Bank Name</label>
                    <input type="text" name="bank_name" id="bank_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border" required placeholder="e.g. Metrobank">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Account Number</label>
                    <input type="text" name="account_number" id="account_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border" required placeholder="000-000-000">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Account Holder</label>
                    <input type="text" name="account_holder" id="account_holder" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border" required placeholder="Company Name / Person">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Initial Balance</label>
                    <input type="number" name="initial_balance" id="initial_balance" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border font-mono" required>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('passbookModal')" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg font-medium hover:bg-gray-300">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 shadow">Save Passbook</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md md:w-full w-11/12">
         <h3 class="text-xl font-bold mb-4 text-red-600">Confirm Deletion</h3>
         <p class="text-gray-600 mb-4">Are you sure you want to delete this passbook? This action cannot be undone and will fail if there are existing transactions linked to it.</p>
        <form id="deleteForm" method="POST" action="api/delete_passbook.php">
            <input type="hidden" name="id" id="delete_id">
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('deleteModal')" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg font-medium hover:bg-gray-300">Cancel</button>
                <button type="submit" class="bg-red-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-700 shadow">Delete Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // Alert Handling
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');

    function handleApiResponse(response) {
        if (response.success) {
            alertMessage.textContent = response.message || 'Success!';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
            alertBox.classList.remove('hidden');
            setTimeout(() => location.reload(), 1000);
        } else {
            alertMessage.textContent = response.message || 'An error occurred.';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
            alertBox.classList.remove('hidden');
        }
        closeModal('passbookModal');
        closeModal('deleteModal');
    }

    function openAddModal() {
        document.getElementById('passbookForm').action = 'api/add_passbook.php';
        document.getElementById('modalTitle').innerText = 'Add New Passbook';
        document.getElementById('passbookForm').reset();
        document.getElementById('passbook_id').value = '';
        document.getElementById('initial_balance').disabled = false;
        document.getElementById('initial_balance').classList.remove('bg-gray-100', 'cursor-not-allowed');
        openModal('passbookModal');
    }

    function openEditModal(passbook) {
        document.getElementById('passbookForm').action = 'api/update_passbook.php';
        document.getElementById('modalTitle').innerText = 'Edit Passbook';
        document.getElementById('passbook_id').value = passbook.id;
        document.getElementById('bank_name').value = passbook.bank_name;
        document.getElementById('account_number').value = passbook.account_number;
        document.getElementById('account_holder').value = passbook.account_holder;
        document.getElementById('initial_balance').value = passbook.current_balance;
        
        // Disable balance editing during update to prevent sync issues
        const balInput = document.getElementById('initial_balance');
        balInput.disabled = true; 
        balInput.classList.add('bg-gray-100', 'cursor-not-allowed');
        
        openModal('passbookModal');
    }

    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteModal');
    }
    
    document.getElementById('passbookForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        // Re-enable balance field just for submission if needed, though for update it's usually ignored by backend
        document.getElementById('initial_balance').disabled = false;
        
        fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    data.message = form.action.includes('add') ? 'Passbook added successfully!' : 'Passbook updated successfully!';
                }
                handleApiResponse(data);
            })
            .catch(err => {
                handleApiResponse({ success: false, message: 'A network error occurred.' });
            });
    });

    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    data.message = 'Passbook deleted successfully!';
                }
                handleApiResponse(data);
            })
            .catch(err => {
                handleApiResponse({ success: false, message: 'A network error occurred.' });
            });
    });
</script>

<?php require_once "includes/footer.php"; ?>