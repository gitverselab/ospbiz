<?php
// manage_users.php
require_once "includes/header.php";
// config/access_control.php is already included by header.php
// This page is for Admins only.
require_role(['Admin']);
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">User Role Management</h2>
</div>

<div id="alertBox" class="hidden no-print mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50">
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Username</th>
                <th class="p-3 text-sm font-semibold text-left">Employee ID (from Auth DB)</th>
                <th class="p-3 text-sm font-semibold text-center">Accounting App Role</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody id="user-table-body">
            <tr><td colspan="4" class="p-4 text-center">Loading users...</td></tr>
        </tbody>
    </table>
</div>

<div id="roleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="roleForm">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Edit Role for <span id="username_span"></span></h3>
            <input type="hidden" name="user_id" id="user_id">
            <div class="space-y-4">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="None">None (Access Revoked)</option>
                        <option value="Viewer">Viewer</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('roleModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Role</button>
            </div>
        </form>
    </div>
</div>

<script>
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');
    const tableBody = document.getElementById('user-table-body');
    const roleForm = document.getElementById('roleForm');

    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    // Handles showing success/error messages
    function handleApiResponse(response) {
        if (response.success) {
            alertMessage.textContent = response.message || 'Success!';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
            alertBox.classList.remove('hidden');
            loadUsers(); // Refresh the table
        } else {
            alertMessage.textContent = response.message || 'An error occurred.';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
            alertBox.classList.remove('hidden');
        }
        closeModal('roleModal');
    }

    function openEditModal(userId, username, currentRole) {
        document.getElementById('user_id').value = userId;
        document.getElementById('username_span').textContent = username;
        // --- THIS IS THE FIX ---
        // We are setting the value to the ROLE NAME (e.g., 'Admin', 'Viewer', or 'None')
        document.getElementById('role').value = currentRole || 'None';
        openModal('roleModal');
    }

    function loadUsers() {
        fetch('api/get_app_users.php')
            .then(res => {
                if (!res.ok) {
                    // Handle HTTP 500 errors
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                tableBody.innerHTML = ''; // Clear loading/old data
                if (data.users.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="4" class="p-4 text-center">No users found in the authentication database.</td></tr>';
                    return;
                }
                
                data.users.forEach(user => {
                    const role = user.app_role || 'None'; // This is the role name
                    let roleDisplay = `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">None</span>`;
                    if (role === 'Admin') {
                        roleDisplay = `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-200 text-red-800">Admin</span>`;
                    } else if (role === 'Accountant') {
                        roleDisplay = `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-200 text-blue-800">Accountant</span>`;
                    } else if (role === 'Viewer') {
                        roleDisplay = `<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-200 text-green-800">Viewer</span>`;
                    }

                    const tr = document.createElement('tr');
                    tr.className = 'border-b hover:bg-gray-50';
                    tr.innerHTML = `
                        <td class="p-3 font-bold">${user.username}</td>
                        <td class="p-3">${user.employee_id || 'N/A'}</td>
                        <td class="p-3 text-center">${roleDisplay}</td>
                        <td class="p-3 text-center">
                            <button onclick="openEditModal(${user.user_id}, '${user.username}', '${role}')" class="text-blue-500 hover:underline">
                                Edit Role
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(tr);
                });
            })
            .catch(err => {
                tableBody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-red-500">Error loading users: ${err.message}</td></tr>`;
            });
    }
    
    roleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/update_user_role.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(handleApiResponse)
            .catch(err => handleApiResponse({ success: false, message: 'A network error occurred.' }));
    });

    // Load users on page start
    document.addEventListener('DOMContentLoaded', loadUsers);
</script>

<?php require_once "includes/footer.php"; ?>