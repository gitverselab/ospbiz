<?php
// import_delivery_receipts.php

require_once "includes/header.php";
require_once "config/database.php";

// Fetch customers for the dropdown
$customers = [];
$customer_sql = "SELECT id, customer_name FROM customers ORDER BY customer_name";
if ($result = $conn->query($customer_sql)) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}
$conn->close();
?>

<h2 class="text-3xl font-bold text-gray-800 mb-6">Import & Manage Delivery Receipts</h2>

<div class="bg-white p-6 rounded-lg shadow-md">
    
    <!-- Instructions -->
    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700">
        <h3 class="font-bold">Instructions for CSV Import</h3>
        <ul class="list-disc list-inside mt-2 text-sm">
            <li>Download the CSV template to ensure your data is in the correct format.</li>
            <li>The `GR Number` and `Item Code` columns are required and used to prevent duplicate entries.</li>
            <li>The `GR Date` column must be in a recognizable date format (e.g., `YYYY-MM-DD`, `M/D/YYYY`).</li>
        </ul>
    </div>
    
    <!-- Download Template Link -->
    <div class="mb-6">
        <a href="api/download_template.php" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 bg-blue-100 hover:bg-blue-200 px-4 py-2 rounded-md transition duration-300">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download CSV Template
        </a>
    </div>

    <!-- Import Form -->
    <form action="api/process_import.php" method="POST" enctype="multipart/form-data" class="border-t pt-6">
        <div class="mb-4">
            <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">1. Select Customer for this Action <span class="text-red-500">*</span></label>
            <select name="customer_id" id="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">-- Select a Customer --</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?php echo $customer['id']; ?>">
                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-6">
            <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">2. Upload CSV File (for import)</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        </div>

        <div class="flex justify-end items-center space-x-3">
             <!-- <button type="button" onclick="openClearDataModal()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300">
                <svg class="w-5 h-5 inline-block -mt-1 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                Clear Data
            </button> -->
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition duration-300">
                <svg class="w-5 h-5 inline-block -mt-1 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Import Data
            </button>
        </div>
    </form>

    <!-- Display Import/Clear Results -->
    <?php if (isset($_SESSION['import_status'])): 
        $status = $_SESSION['import_status'];
        $is_success = $status['success'];
        $alert_class = $is_success ? 'bg-green-50 border-green-400 text-green-700' : 'bg-red-50 border-red-400 text-red-700';
    ?>
        <div class="mt-6 p-4 rounded-md <?php echo $alert_class; ?>">
            <h4 class="font-bold"><?php echo htmlspecialchars($status['message']); ?></h4>
            
            <?php if (isset($status['total_rows'])): // Show details only for import ?>
            <ul class="list-disc list-inside text-sm mt-2">
                <li>Total rows in file: <strong><?php echo $status['total_rows']; ?></strong></li>
                <li>Successfully imported: <strong class="text-green-800"><?php echo $status['imported_count']; ?></strong></li>
                <li>Skipped (duplicates): <strong class="text-orange-800"><?php echo $status['duplicate_count']; ?></strong></li>
                <li>Skipped (errors): <strong class="text-red-800"><?php echo $status['error_count']; ?></strong></li>
            </ul>
            <?php endif; ?>
            
            <?php if (!empty($status['error_file'])): ?>
            <div class="mt-4 pt-2 border-t border-gray-300">
                <a href="api/download_error_report.php?file=<?php echo urlencode($status['error_file']); ?>" class="inline-flex items-center text-sm font-medium text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Download Skipped Rows Report
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php unset($_SESSION['import_status']); // Clear the status after displaying it ?>
    <?php endif; ?>

</div>

<!-- Clear Data Confirmation Modal -->
<div id="clearDataModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-xl font-bold mb-4 text-red-700">Confirm Data Deletion</h3>
        <p>Are you sure you want to delete ALL delivery receipts for the selected customer? <strong class="font-bold">This action cannot be undone.</strong></p>
        <form id="clearDataForm" action="api/clear_delivery_receipts.php" method="POST">
            <input type="hidden" name="customer_id" id="clear_customer_id">
            <div class="mt-6 flex justify-end space-x-2">
                <button type="button" onclick="closeModal('clearDataModal')" class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg">Yes, Delete All</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function openClearDataModal() {
        const customerSelect = document.getElementById('customer_id');
        const selectedCustomerId = customerSelect.value;
        const selectedCustomerName = customerSelect.options[customerSelect.selectedIndex].text;

        if (!selectedCustomerId) {
            alert('Please select a customer first.');
            return;
        }

        document.getElementById('clear_customer_id').value = selectedCustomerId;
        const confirmationText = document.querySelector('#clearDataModal p');
        confirmationText.innerHTML = `Are you sure you want to delete ALL delivery receipts for <strong>${selectedCustomerName}</strong>? <strong class="font-bold">This action cannot be undone.</strong>`;
        
        openModal('clearDataModal');
    }
</script>

<?php
require_once "includes/footer.php";
?>

