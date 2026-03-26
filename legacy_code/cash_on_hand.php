<?php
// cash_on_hand.php

require_once "includes/header.php";
require_once "config/database.php";

// Fetch cash accounts
$accounts = [];
$sql = "SELECT id, account_name, current_balance FROM cash_accounts ORDER BY account_name ASC";
if ($result = $conn->query($sql)) {
    while($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}
$conn->close();
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-2xl md:text-3xl font-bold text-gray-800">Cash on Hand Management</h2>
    <button onclick="openAddModal()" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow">
        + Add New Cash Account
    </button>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto text-sm text-left">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="p-2 md:p-3 font-semibold whitespace-nowrap">Account Name</th>
                <th class="p-2 md:p-3 font-semibold text-right whitespace-nowrap">Current Balance</th>
                <th class="p-2 md:p-3 font-semibold text-center whitespace-nowrap">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if (!empty($accounts)): foreach ($accounts as $account): ?>
                <tr class="hover:bg-gray-50">
                    <td class="p-2 md:p-3 font-bold text-gray-800 whitespace-nowrap"><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td class="p-2 md:p-3 text-right font-bold text-green-700 whitespace-nowrap">₱<?php echo number_format($account['current_balance'], 2); ?></td>
                    <td class="p-2 md:p-3 text-center whitespace-nowrap">
                        <div class="flex justify-center gap-3">
                            <a href="cash_account_view.php?id=<?php echo $account['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">View</a>
                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($account), ENT_QUOTES, 'UTF-8'); ?>)' class="text-green-600 hover:text-green-800 font-medium">Edit</button>
                            <button onclick='openDeleteModal(<?php echo $account["id"]; ?>)' class="text-red-600 hover:text-red-800 font-medium">Delete</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="3" class="p-6 text-center text-gray-500 italic">No cash accounts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="accountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg md:w-full w-11/12">
        <form id="accountForm" method="POST">
            <h3 id="modalTitle" class="text-xl font-bold mb-4 text-gray-800">Add New Cash Account</h3>
            <input type="hidden" name="id" id="account_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700">Account Name</label>
                    <input type="text" name="account_name" id="account_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border" required placeholder="e.g. Petty Cash">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700">Initial Balance</label>
                    <input type="number" name="initial_balance" id="initial_balance" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2 border font-mono" required>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('accountModal')" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg font-medium hover:bg-gray-300">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 shadow">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md md:w-full w-11/12">
         <h3 class="text-xl font-bold mb-4 text-red-600">Confirm Deletion</h3>
         <p class="text-gray-600 mb-4">Are you sure you want to delete this account? This action cannot be undone and will fail if there are existing transactions.</p>
        <form id="deleteForm" method="POST" action="api/delete_cash_account.php">
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
        closeModal('accountModal');
        closeModal('deleteModal');
    }

    function openAddModal() {
        document.getElementById('accountForm').action = 'api/add_cash_account.php';
        document.getElementById('modalTitle').innerText = 'Add New Cash Account';
        document.getElementById('accountForm').reset();
        document.getElementById('account_id').value = '';
        document.getElementById('initial_balance').disabled = false;
        document.getElementById('initial_balance').classList.remove('bg-gray-100', 'cursor-not-allowed');
        openModal('accountModal');
    }

    function openEditModal(account) {
        document.getElementById('accountForm').action = 'api/update_cash_account.php';
        document.getElementById('modalTitle').innerText = 'Edit Cash Account';
        document.getElementById('account_id').value = account.id;
        document.getElementById('account_name').value = account.account_name;
        document.getElementById('initial_balance').value = account.current_balance;
        
        const balInput = document.getElementById('initial_balance');
        balInput.disabled = true;
        balInput.classList.add('bg-gray-100', 'cursor-not-allowed');
        openModal('accountModal');
    }

    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteModal');
    }
    
    document.getElementById('accountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        // Re-enable for submission if needed
        document.getElementById('initial_balance').disabled = false;
        
        fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    data.message = form.action.includes('add') ? 'Account added successfully!' : 'Account updated successfully!';
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
                    data.message = 'Account deleted successfully!';
                }
                handleApiResponse(data);
            })
            .catch(err => {
                handleApiResponse({ success: false, message: 'A network error occurred.' });
            });
    });
</script>

<?php require_once "includes/footer.php"; ?>