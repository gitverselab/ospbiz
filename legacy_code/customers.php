<?php
// customers.php
require_once "includes/header.php";
require_once "config/database.php";

$customers = $conn->query("SELECT * FROM customers ORDER BY customer_name ASC")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Customer Management</h2>
    <button onclick="openModal('addCustomerModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md">
        Add New Customer
    </button>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Customer Name</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Contact Person</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Contact Email</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($customers)): foreach ($customers as $customer): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="p-3 text-sm text-gray-700 font-bold"><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                    <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($customer['contact_person']); ?></td>
                    <td class="p-3 text-sm text-gray-700"><?php echo htmlspecialchars($customer['contact_email']); ?></td>
                    <td class="p-3 text-sm text-center">
                        <button onclick='openEditModal(<?php echo json_encode($customer); ?>)' class="text-blue-500 hover:underline">Edit</button>
                        <button onclick="openDeleteModal(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars(addslashes($customer['customer_name'])); ?>')" class="text-red-500 hover:underline ml-2">Delete</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="p-3 text-sm text-gray-500 text-center">No customers found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="addCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-6">
        <form id="addForm">
            <h3 class="text-2xl font-bold mb-4">Add New Customer</h3>
            <div class="space-y-4">
                <div>
                    <label for="add_customer_name" class="block text-sm font-medium">Customer Name</label>
                    <input type="text" name="customer_name" id="add_customer_name" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div>
                    <label for="add_contact_person" class="block text-sm font-medium">Contact Person</label>
                    <input type="text" name="contact_person" id="add_contact_person" class="mt-1 block w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="add_contact_email" class="block text-sm font-medium">Contact Email</label>
                    <input type="email" name="contact_email" id="add_contact_email" class="mt-1 block w-full rounded-md border-gray-300">
                </div>
            </div>
            <div class="mt-6 text-right">
                <button type="button" onclick="closeModal('addCustomerModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<div id="editCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-lg p-6">
        <form id="editForm">
            <h3 class="text-2xl font-bold mb-4">Edit Customer</h3>
            <input type="hidden" name="id" id="edit_id">
             <div class="space-y-4">
                <div>
                    <label for="edit_customer_name" class="block text-sm font-medium">Customer Name</label>
                    <input type="text" name="customer_name" id="edit_customer_name" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div>
                    <label for="edit_contact_person" class="block text-sm font-medium">Contact Person</label>
                    <input type="text" name="contact_person" id="edit_contact_person" class="mt-1 block w-full rounded-md border-gray-300">
                </div>
                <div>
                    <label for="edit_contact_email" class="block text-sm font-medium">Contact Email</label>
                    <input type="email" name="contact_email" id="edit_contact_email" class="mt-1 block w-full rounded-md border-gray-300">
                </div>
            </div>
            <div class="mt-6 text-right">
                <button type="button" onclick="closeModal('editCustomerModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteCustomerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
        <form id="deleteForm">
            <h3 class="text-2xl font-bold text-center">Confirm Deletion</h3>
            <p class="text-center my-4">Are you sure you want to delete <strong id="delete_customer_name"></strong>?</p>
            <input type="hidden" name="id" id="delete_id">
            <div class="flex justify-center">
                <button type="button" onclick="closeModal('deleteCustomerModal')" class="bg-gray-200 py-2 px-6 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-red-600 text-white py-2 px-6 rounded-lg">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) { document.getElementById(modalId).classList.remove('hidden'); }
    function closeModal(modalId) { 
        document.getElementById(modalId).classList.add('hidden');
        if (modalId === 'addCustomerModal') document.getElementById('addForm').reset();
    }
    
    function openEditModal(customer) {
        document.getElementById('edit_id').value = customer.id;
        document.getElementById('edit_customer_name').value = customer.customer_name;
        document.getElementById('edit_contact_person').value = customer.contact_person;
        document.getElementById('edit_contact_email').value = customer.contact_email;
        openModal('editCustomerModal');
    }

    function openDeleteModal(id, name) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_customer_name').innerText = name;
        openModal('deleteCustomerModal');
    }

    // Handle Add Form
    document.getElementById('addForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/add_customer.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) { location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    });

    // Handle Edit Form
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/update_customer.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) { location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    });

    // Handle Delete Form
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api/delete_customer.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) { location.reload(); }
            else { alert('Error: ' + data.message); }
        });
    });
</script>

<?php require_once "includes/footer.php"; ?>