<?php
// items.php
require_once "includes/header.php";
require_once "config/database.php";

// --- INITIALIZE VARIABLES ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Items per page
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

// --- BUILD QUERY ---
$where_sql = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_sql = "WHERE item_name LIKE ? OR item_description LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types = "ss";
}

// --- COUNT TOTAL RECORDS ---
$count_sql = "SELECT COUNT(id) as total FROM items $where_sql";
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();

// --- FETCH ITEMS ---
$sql = "SELECT id, item_name, item_description, unit FROM items $where_sql ORDER BY item_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// Append limit/offset params
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
    <h2 class="text-3xl font-bold text-gray-800">Items Management</h2>
    
    <div class="flex space-x-2">
        <a href="api/download_items_template.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-md">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Template
        </a>
        
        <button onclick="openModal('importModal')" class="bg-teal-500 hover:bg-teal-600 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-md">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            Import
        </button>

        <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg flex items-center shadow-md">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New
        </button>
    </div>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6">
    <form method="GET" action="items.php" class="flex gap-2">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search items..." class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md">Search</button>
        <?php if(!empty($search)): ?>
            <a href="items.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-md flex items-center">Reset</a>
        <?php endif; ?>
    </form>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead class="bg-gray-50 border-b-2 border-gray-200">
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Item Name</th>
                <th class="p-3 text-sm font-semibold text-left">Description</th>
                <th class="p-3 text-sm font-semibold text-left">Unit</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): foreach ($items as $item): ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-bold text-gray-700"><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td class="p-3 text-gray-600"><?php echo htmlspecialchars($item['item_description']); ?></td>
                <td class="p-3 text-gray-600"><?php echo htmlspecialchars($item['unit']); ?></td>
                <td class="p-3 text-center space-x-2">
                    <button onclick="openEditModal(<?php echo $item['id']; ?>)" class="text-green-500 hover:underline font-medium">Edit</button>
                    <button onclick="deleteItem(<?php echo $item['id']; ?>)" class="text-red-500 hover:underline font-medium">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="p-4 text-center text-gray-500">No items found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex justify-between items-center">
    <span class="text-sm text-gray-600">
        Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> results
    </span>
    <div class="flex items-center space-x-1">
        <?php 
        $query_params = $_GET;
        if ($page > 1) {
            $query_params['page'] = $page - 1;
            echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">&laquo; Prev</a>';
        }
        
        // Simple pagination logic (show current, prev, next)
        for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
            $query_params['page'] = $i;
            $active = $i == $page ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-100';
            echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md ' . $active . '">' . $i . '</a>';
        }

        if ($page < $total_pages) {
            $query_params['page'] = $page + 1;
            echo '<a href="?' . http_build_query($query_params) . '" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">Next &raquo;</a>';
        }
        ?>
    </div>
</div>

<div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="importForm" enctype="multipart/form-data">
            <h3 class="text-xl font-bold mb-4">Import Items from CSV</h3>
            
            <div class="mb-4 text-sm text-gray-600 bg-blue-50 p-3 rounded">
                <p>1. Download the template.</p>
                <p>2. Fill in Item Name, Description, and Unit.</p>
                <p>3. Upload the file here.</p>
                <p class="mt-1 font-semibold text-orange-600">Note: Duplicate names will be skipped.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Select CSV File</label>
                <input type="file" name="csv_file" accept=".csv" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" required>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('importModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white py-2 px-4 rounded-lg">Import</button>
            </div>
        </form>
    </div>
</div>

<div id="itemModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <form id="itemForm">
            <input type="hidden" name="id" id="item_id">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Add New Item</h3>
            
            <div class="space-y-4">
                <div>
                    <label for="item_name" class="block text-sm font-medium text-gray-700">Item Name</label>
                    <input type="text" name="item_name" id="item_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label for="item_description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="item_description" id="item_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>
                <div>
                    <label for="unit" class="block text-sm font-medium text-gray-700">Unit (e.g., pcs, kgs, box)</label>
                    <input type="text" name="unit" id="unit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('itemModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
const alertBox = document.getElementById('alertBox');
const alertMessage = document.getElementById('alertMessage');

// Unified Alert Handler
function handleApiResponse(response, modalId) {
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
    if(modalId) closeModal(modalId);
}

function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { 
    document.getElementById(id).classList.add('hidden'); 
    if(id === 'itemModal') {
        document.getElementById('itemForm').reset();
        document.getElementById('item_id').value = '';
    }
    if(id === 'importModal') {
        document.getElementById('importForm').reset();
    }
}

function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add New Item';
    openModal('itemModal');
}

function openEditModal(id) {
    document.getElementById('modalTitle').innerText = 'Edit Item';
    fetch(`api/get_item_details.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('item_id').value = data.data.id;
                document.getElementById('item_name').value = data.data.item_name;
                document.getElementById('item_description').value = data.data.item_description;
                document.getElementById('unit').value = data.data.unit;
                openModal('itemModal');
            } else {
                alert(data.message);
            }
        });
}

// Add/Edit Item Submit
document.getElementById('itemForm').addEventListener('submit', function(event) {
    event.preventDefault();
    const isUpdating = document.getElementById('item_id').value !== '';
    const url = isUpdating ? 'api/update_item.php' : 'api/add_item.php';

    fetch(url, { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => handleApiResponse(data, 'itemModal'))
    .catch(err => handleApiResponse({ success: false, message: 'Network error' }, 'itemModal'));
});

// Import Items Submit
document.getElementById('importForm').addEventListener('submit', function(event) {
    event.preventDefault();
    fetch('api/import_items.php', { method: 'POST', body: new FormData(this) })
    .then(res => res.json())
    .then(data => handleApiResponse(data, 'importModal'))
    .catch(err => handleApiResponse({ success: false, message: 'Network error' }, 'importModal'));
});

function deleteItem(id) {
    if (confirm('Are you sure you want to delete this item?')) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_item.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => handleApiResponse(data, null))
        .catch(err => handleApiResponse({ success: false, message: 'Network error' }, null));
    }
}
</script>

<?php require_once "includes/footer.php"; ?>