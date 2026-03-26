<?php
// categories.php
require_once "includes/header.php";
require_once "config/database.php";

// Fetch all categories
$sql = "SELECT * FROM categories ORDER BY type, name";
$categories = [];
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
$conn->close();
?>

<h2 class="text-3xl font-bold text-gray-800 mb-6">Category Management</h2>

<!-- Add Category Button -->
<div class="mb-4 text-right">
    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300">
        <svg class="w-5 h-5 inline-block -mt-1 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        Add New Category
    </button>
</div>

<!-- Categories Table -->
<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Category Name</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-left">Type</th>
                <th class="p-3 text-sm font-semibold tracking-wide text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="p-3 text-sm text-gray-700 font-semibold"><?php echo htmlspecialchars($category['name']); ?></td>
                        <td class="p-3 text-sm text-gray-700">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo ($category['type'] == 'Purchase') ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                <?php echo htmlspecialchars($category['type']); ?>
                            </span>
                        </td>
                        <td class="p-3 text-sm text-gray-700 text-center">
                            <button onclick="openEditModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>', '<?php echo $category['type']; ?>')" class="text-blue-500 hover:text-blue-700 mr-4">Edit</button>
                            <button onclick="openDeleteModal(<?php echo $category['id']; ?>)" class="text-red-500 hover:text-red-700">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="p-3 text-sm text-gray-500 text-center">No categories found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Category Modal -->
<div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 id="modalTitle" class="text-2xl font-bold">Add New Category</h3>
            <button onclick="closeModal('categoryModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
        </div>
        <form id="categoryForm" action="" method="POST">
            <input type="hidden" name="id" id="categoryId">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" name="name" id="categoryName" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
            </div>
            <div class="mt-4">
                <label for="type" class="block text-sm font-medium text-gray-700">Category Type</label>
                <select name="type" id="categoryType" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <option value="Purchase">Purchase</option>
                    <option value="Bill">Bill</option>
                </select>
            </div>
            <div class="mt-6 text-right">
                <button type="button" onclick="closeModal('categoryModal')" class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-2xl w-full max-w-sm p-6">
         <h3 class="text-lg font-bold text-gray-800 mb-4">Confirm Deletion</h3>
         <p class="text-gray-600 mb-6">Are you sure you want to delete this category? This action cannot be undone.</p>
        <form id="deleteForm" action="api/delete_category.php" method="POST">
            <input type="hidden" name="id" id="deleteCategoryId">
            <div class="text-right">
                 <button type="button" onclick="closeModal('deleteModal')" class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function openAddModal() {
        document.getElementById('categoryForm').action = 'api/add_category.php';
        document.getElementById('modalTitle').innerText = 'Add New Category';
        document.getElementById('categoryId').value = '';
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryType').value = 'Purchase';
        openModal('categoryModal');
    }

    function openEditModal(id, name, type) {
        document.getElementById('categoryForm').action = 'api/update_category.php';
        document.getElementById('modalTitle').innerText = 'Edit Category';
        document.getElementById('categoryId').value = id;
        document.getElementById('categoryName').value = name;
        document.getElementById('categoryType').value = type;
        openModal('categoryModal');
    }
    
    function openDeleteModal(id) {
        document.getElementById('deleteCategoryId').value = id;
        openModal('deleteModal');
    }
</script>

<?php
require_once "includes/footer.php";
?>
