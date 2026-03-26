<?php
// chart_of_accounts.php
require_once "includes/header.php";
require_once "config/database.php";

$accounts = [];
$sql = "SELECT id, account_name, account_type, description FROM chart_of_accounts ORDER BY account_type, account_name";
if($result = $conn->query($sql)) {
    while($row = $result->fetch_assoc()) { $accounts[] = $row; }
}
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Chart of Accounts</h2>
    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Add New Account</button>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Account Name</th>
                <th class="p-3 text-sm font-semibold text-left">Type</th>
                <th class="p-3 text-sm font-semibold text-left">Description</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(!empty($accounts)): foreach ($accounts as $account): ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-bold"><?php echo htmlspecialchars($account['account_name']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($account['account_type']); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($account['description']); ?></td>
                <td class="p-3 text-center space-x-2">
                    <button onclick='openEditModal(<?php echo json_encode($account); ?>)' class="text-green-500 hover:underline">Edit</button>
                    <button onclick='openDeleteModal(<?php echo $account["id"]; ?>)' class="text-red-500 hover:underline">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center p-4 text-gray-500">No accounts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="accountModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="accountForm" method="POST">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Account</h3>
            <input type="hidden" name="id" id="account_id">
            <div class="space-y-4">
                <div><label>Account Name</label><input type="text" name="account_name" id="account_name" class="mt-1 block w-full rounded-md border-gray-300" required></div>
                <div><label>Account Type</label><select name="account_type" id="account_type" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option>Asset</option><option>Liability</option><option>Equity</option><option>Income</option><option>Expense</option>
                </select></div>
                <div><label>Description</label><textarea name="description" id="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300"></textarea></div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('accountModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
         <h3 class="text-xl font-bold mb-4">Confirm Deletion</h3>
         <p>Are you sure you want to delete this account? This cannot be undone.</p>
        <form id="deleteForm" method="POST" action="api/delete_chart_of_account.php">
            <input type="hidden" name="id" id="delete_id">
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('deleteModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-red-600 text-white py-2 px-4 rounded-lg">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // --- NEW ---
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');

    // --- NEW ---
    // Handles showing success/error messages without a page reload
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
        document.getElementById('accountForm').action = 'api/add_chart_of_account.php';
        document.getElementById('modalTitle').innerText = 'Add New Account';
        document.getElementById('accountForm').reset();
        document.getElementById('account_id').value = '';
        openModal('accountModal');
    }

    function openEditModal(account) {
        document.getElementById('accountForm').action = 'api/update_chart_of_account.php';
        document.getElementById('modalTitle').innerText = 'Edit Account';
        document.getElementById('account_id').value = account.id;
        document.getElementById('account_name').value = account.account_name;
        document.getElementById('account_type').value = account.account_type;
        document.getElementById('description').value = account.description;
        openModal('accountModal');
    }

    function openDeleteModal(id) {
        document.getElementById('delete_id').value = id;
        openModal('deleteModal');
    }
    
    // --- UPDATED ---
    document.getElementById('accountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(res => res.json()) // Expect a JSON response
            .then(handleApiResponse)
            .catch(err => {
                handleApiResponse({ success: false, message: 'A network error occurred.' });
            });
    });

    // --- NEW ---
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        fetch(form.action, { method: 'POST', body: new FormData(form) })
            .then(res => res.json()) // Expect a JSON response
            .then(handleApiResponse)
            .catch(err => {
                handleApiResponse({ success: false, message: 'A network error occurred.' });
            });
    });
</script>

<?php require_once "includes/footer.php"; ?>