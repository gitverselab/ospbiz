<?php
// manage_roles.php
require_once "includes/header.php";
require_once "config/access_control.php";

// This page is for Admins only.
// We use the new permission-based check!
check_permission('roles.manage');
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Role & Permission Management</h2>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="md:col-span-1">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Roles</h3>
            <ul id="role-list" class="space-y-2">
                <li>Loading roles...</li>
            </ul>
        </div>
    </div>

    <div class="md:col-span-2">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-xl font-semibold mb-4">Permissions for <span id="selected-role-name" class="text-blue-600">...</span></h3>
            
            <form id="permissionsForm">
                <input type="hidden" name="role_id" id="role_id">
                <div id="permissions-container" class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                    <p class="text-gray-500">Please select a role to see its permissions.</p>
                </div>
                <div class="mt-6 border-t pt-4 flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
                        Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let allData = { roles: [], permissions: [], role_permissions: {} };
    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');
    const roleList = document.getElementById('role-list');
    const permsContainer = document.getElementById('permissions-container');
    const selectedRoleName = document.getElementById('selected-role-name');
    const roleIdInput = document.getElementById('role_id');
    const permsForm = document.getElementById('permissionsForm');

    document.addEventListener('DOMContentLoaded', loadData);

    function loadData() {
        fetch('api/get_roles_permissions.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) throw new Error(data.message);
                allData = data;
                renderRoleList();
                // Select the first role by default
                if (data.roles.length > 0) {
                    selectRole(data.roles[0].id, data.roles[0].role_name);
                }
            })
            .catch(err => {
                roleList.innerHTML = `<li class="text-red-500">${err.message}</li>`;
            });
    }

    function renderRoleList() {
        roleList.innerHTML = '';
        allData.roles.forEach(role => {
            const li = document.createElement('li');
            li.innerHTML = `<button type="button" data-role-id="${role.id}" data-role-name="${role.role_name}" class="w-full text-left p-3 rounded-md hover:bg-gray-100 focus:outline-none focus:bg-blue-100 focus:font-semibold">
                                ${role.role_name}
                           </button>`;
            roleList.appendChild(li);
        });
        
        // Add click event listener to the list
        roleList.addEventListener('click', e => {
            const button = e.target.closest('button');
            if (button) {
                const roleId = button.dataset.roleId;
                const roleName = button.dataset.roleName;
                selectRole(roleId, roleName);
            }
        });
    }

    function selectRole(roleId, roleName) {
        selectedRoleName.textContent = roleName;
        roleIdInput.value = roleId;
        
        // Un-highlight all buttons
        roleList.querySelectorAll('button').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white', 'font-semibold');
            btn.classList.add('hover:bg-gray-100');
        });
        
        // Highlight the selected one
        const selectedButton = roleList.querySelector(`button[data-role-id='${roleId}']`);
        if(selectedButton) {
            selectedButton.classList.add('bg-blue-600', 'text-white', 'font-semibold');
            selectedButton.classList.remove('hover:bg-gray-100');
        }

        renderPermissions(roleId);
    }

    function renderPermissions(roleId) {
        permsContainer.innerHTML = '';
        const rolePerms = allData.role_permissions[roleId] || [];
        
        // Group permissions by their prefix (e.g., 'bills', 'users')
        const groupedPerms = allData.permissions.reduce((acc, perm) => {
            const group = perm.permission_name.split('.')[0] || 'general';
            if (!acc[group]) acc[group] = [];
            acc[group].push(perm);
            return acc;
        }, {});

        for (const group in groupedPerms) {
            const groupDiv = document.createElement('div');
            groupDiv.className = 'border rounded-md p-4';
            
            const groupTitle = document.createElement('h4');
            groupTitle.className = 'text-lg font-semibold capitalize border-b pb-2 mb-3';
            groupTitle.textContent = group;
            groupDiv.appendChild(groupTitle);
            
            const gridDiv = document.createElement('div');
            gridDiv.className = 'grid grid-cols-1 md:grid-cols-2 gap-2';

            groupedPerms[group].forEach(perm => {
                const isChecked = rolePerms.includes(perm.id);
                const label = document.createElement('label');
                label.className = 'flex items-center space-x-2 p-1 rounded-md hover:bg-gray-50';
                label.innerHTML = `
                    <input type="checkbox" name="permission_ids[]" value="${perm.id}" class="h-4 w-4 rounded" ${isChecked ? 'checked' : ''}>
                    <span>${perm.permission_name}</span>
                `;
                gridDiv.appendChild(label);
            });
            groupDiv.appendChild(gridDiv);
            permsContainer.appendChild(groupDiv);
        }
    }
    
    permsForm.addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/update_role_permissions.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alertMessage.textContent = data.message || 'Permissions updated!';
                    alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
                    alertBox.classList.remove('hidden');
                    // Reload all data to reflect changes
                    loadData();
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(err => {
                alertMessage.textContent = err.message || 'An error occurred.';
                alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
                alertBox.classList.remove('hidden');
            });
    });

</script>

<?php require_once "includes/footer.php"; ?>