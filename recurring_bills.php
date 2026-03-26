<?php
// recurring_bills.php
require_once "includes/header.php";
require_once "config/database.php";

// Fetch data for dropdowns
$billers = [];
$biller_sql = "SELECT id, biller_name FROM billers ORDER BY biller_name";
if ($result = $conn->query($biller_sql)) {
    while($row = $result->fetch_assoc()) { $billers[] = $row; }
}
$expense_accounts = [];
$coa_sql = "SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Expense' ORDER BY account_name";
if ($result = $conn->query($coa_sql)) {
    while($row = $result->fetch_assoc()) { $expense_accounts[] = $row; }
}

// Fetch recurring bill schedules
$sql = "SELECT rb.id, b.biller_name, coa.account_name, rb.amount, rb.frequency, rb.recur_day, rb.start_date, rb.end_date 
        FROM recurring_bills rb
        JOIN billers b ON rb.biller_id = b.id
        JOIN chart_of_accounts coa ON rb.chart_of_account_id = coa.id
        ORDER BY b.biller_name";
$recurring_bills = [];
if ($result = $conn->query($sql)) {
    while($row = $result->fetch_assoc()) { $recurring_bills[] = $row; }
}
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Recurring Bills</h2>
    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">New Schedule</button>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead>
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Biller</th>
                <th class="p-3 text-sm font-semibold text-left">Category</th>
                <th class="p-3 text-sm font-semibold text-right">Amount</th>
                <th class="p-3 text-sm font-semibold text-center">Frequency</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($recurring_bills)): foreach ($recurring_bills as $rb): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3 font-bold"><?php echo htmlspecialchars($rb['biller_name']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($rb['account_name']); ?></td>
                    <td class="p-3 text-right font-semibold">₱<?php echo number_format($rb['amount'], 2); ?></td>
                    <td class="p-3 text-center"><?php echo "Every " . $rb['recur_day'] . " of the " . $rb['frequency']; ?></td>
                    <td class="p-3 text-center space-x-2">
                        <button onclick='openEditModal(<?php echo $rb["id"]; ?>)' class="text-green-500 hover:underline">Edit</button>
                        <button onclick='deleteRecurringBill(<?php echo $rb["id"]; ?>)' class="text-red-500 hover:underline">Delete</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="p-3 text-center text-gray-500">No recurring bill schedules found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="recurringBillModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="recurringBillForm">
            <input type="hidden" name="id" id="recurring_bill_id">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">New Recurring Bill Schedule</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label>Biller</label>
                        <select name="biller_id" id="biller_id" class="mt-1 block w-full rounded-md" required>
                            <option value="">Select Biller...</option>
                            <?php foreach($billers as $biller): ?>
                                <option value="<?php echo $biller['id']; ?>"><?php echo htmlspecialchars($biller['biller_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Category</label>
                        <select name="chart_of_account_id" id="chart_of_account_id" class="mt-1 block w-full rounded-md" required>
                            <option value="">Select Category</option>
                            <?php foreach($expense_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div><label>Amount</label><input type="number" name="amount" id="amount" step="0.01" class="mt-1 block w-full rounded-md" required></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label>Frequency</label>
                        <select name="frequency" id="frequency" class="mt-1 block w-full rounded-md" required>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Yearly">Yearly</option>
                        </select>
                    </div>
                    <div>
                        <label>Day of Month to Recur (1-31)</label>
                        <input type="number" name="recur_day" id="recur_day" min="1" max="31" class="mt-1 block w-full rounded-md" required>
                    </div>
                </div>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label>Start Date</label><input type="date" name="start_date" id="start_date" class="mt-1 block w-full rounded-md" required></div>
                    <div><label>End Date (Optional)</label><input type="date" name="end_date" id="end_date" class="mt-1 block w-full rounded-md"></div>
                </div>
                <div><label>Description (Optional)</label><textarea name="description" id="description" rows="2" class="mt-1 block w-full rounded-md"></textarea></div>
            </div>
            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('recurringBillModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('recurringBillModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('recurringBillForm');
    const idField = document.getElementById('recurring_bill_id');

    function closeModal(id) { modal.classList.add('hidden'); form.reset(); }
    function openAddModal() {
        modalTitle.innerText = 'New Recurring Bill Schedule';
        idField.value = '';
        form.reset();
        modal.classList.remove('hidden');
    }

    function openEditModal(id) {
        modalTitle.innerText = 'Edit Recurring Bill Schedule';
        fetch(`api/get_recurring_bill_details.php?id=${id}`)
        .then(res => res.json()).then(data => {
            if (data.success) {
                const bill = data.data;
                idField.value = bill.id;
                document.getElementById('biller_id').value = bill.biller_id;
                document.getElementById('chart_of_account_id').value = bill.chart_of_account_id;
                document.getElementById('amount').value = bill.amount;
                document.getElementById('frequency').value = bill.frequency;
                document.getElementById('recur_day').value = bill.recur_day;
                document.getElementById('start_date').value = bill.start_date;
                document.getElementById('end_date').value = bill.end_date;
                document.getElementById('description').value = bill.description;
                modal.classList.remove('hidden');
            } else { alert(data.message); }
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const isEditing = idField.value !== '';
        const url = isEditing ? 'api/update_recurring_bill.php' : 'api/add_recurring_bill.php';
        fetch(url, { method: 'POST', body: new FormData(this) })
        .then(res => res.json()).then(data => {
            if (data.success) location.reload();
            else alert('Error: ' + data.message);
        });
    });

    function deleteRecurringBill(id) {
        if (confirm('Are you sure you want to delete this schedule?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('api/delete_recurring_bill.php', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>