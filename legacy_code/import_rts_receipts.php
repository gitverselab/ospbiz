<?php
// import_rts_receipts.php
session_start();
require_once "includes/header.php";
require_once "config/database.php";

$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md mt-10">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Import Return Receipts (RTS)</h2>
    
    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
        <p class="text-sm text-blue-700">Ensure your CSV matches the template: <strong>GR Number, RD Number, PO Number</strong> must be in correct columns.</p>
    </div>

    <form id="importForm" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">Select Customer</label>
            <select name="customer_id" class="w-full border rounded px-3 py-2" required>
                <option value="">-- Select Customer --</option>
                <?php foreach($customers as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-bold mb-2">Upload CSV File</label>
            <input type="file" name="csv_file" accept=".csv" class="w-full border rounded px-3 py-2" required>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">
            Import Returns
        </button>
    </form>
    
    <div id="result" class="mt-4 hidden p-4 rounded"></div>
</div>

<script>
    document.getElementById('importForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const resultDiv = document.getElementById('result');
        resultDiv.classList.add('hidden');
        
        const btn = this.querySelector('button');
        btn.disabled = true;
        btn.innerText = "Importing...";

        fetch('api/import_rts.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = "Import Returns";
                resultDiv.classList.remove('hidden');
                if (data.success) {
                    resultDiv.className = 'mt-4 p-4 rounded bg-green-100 text-green-700';
                    resultDiv.innerText = data.message;
                    setTimeout(() => window.location.href = 'rts_receipts.php', 1500);
                } else {
                    resultDiv.className = 'mt-4 p-4 rounded bg-red-100 text-red-700';
                    resultDiv.innerText = data.message;
                }
            })
            .catch(err => {
                btn.disabled = false;
                alert('Network Error');
            });
    });
</script>
<?php require_once "includes/footer.php"; ?>