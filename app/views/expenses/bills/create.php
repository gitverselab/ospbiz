<form action="/expenses/bills/store" method="POST" class="max-w-5xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Bill</h2>
        <a href="/expenses/bills" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Supplier / Biller</label>
            <select name="supplier_id" class="w-full border p-2 rounded bg-white" required>
                <option value="">Select Supplier...</option>
                <?php foreach($suppliers as $s): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bill # (Invoice No.)</label>
            <input type="text" name="bill_number" class="w-full border p-2 rounded" placeholder="e.g. INV-001" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bill Date</label>
            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Due Date</label>
            <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" class="w-full border p-2 rounded">
        </div>
    </div>

    <h3 class="font-bold text-gray-700 mb-2">Expenses / Items</h3>
    <table class="w-full mb-6 border-collapse">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase w-1/3">Expense Category</th>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Description</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Amount</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody id="billLines">
            </tbody>
    </table>
    
    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold hover:underline mb-6">
        <i class="fa-solid fa-plus-circle"></i> Add Expense Line
    </button>
    
    <input type="hidden" name="lines_json" id="linesJson">

    <div class="flex justify-end items-center border-t pt-4">
        <div class="mr-6 text-right">
            <div class="text-xs text-gray-500 uppercase">Total Payable</div>
            <div class="text-2xl font-bold text-gray-800" id="grandTotalDisplay">₱0.00</div>
        </div>
        <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-6 py-3 rounded font-bold hover:bg-blue-700 shadow">
            Save Bill
        </button>
    </div>
</form>

<script>
// Pass PHP accounts to JS
const accounts = <?php echo json_encode($accounts); ?>;

function addLine() {
    const tr = document.createElement('tr');
    tr.className = "border-b hover:bg-gray-50";
    
    // Create Account Dropdown options
    let options = '<option value="">Select Account...</option>';
    accounts.forEach(acc => {
        options += `<option value="${acc.id}">${acc.code} - ${acc.name}</option>`;
    });

    tr.innerHTML = `
        <td class="p-2 align-top">
            <select class="w-full border p-1 rounded text-sm acc-select" required>${options}</select>
        </td>
        <td class="p-2 align-top">
            <input class="w-full border p-1 rounded text-sm desc" placeholder="Description...">
        </td>
        <td class="p-2 align-top">
            <input class="w-full border p-1 rounded text-sm text-right amt" type="number" step="0.01" value="0" oninput="calcTotal()" required>
        </td>
        <td class="p-2 text-center align-top">
            <button type="button" onclick="this.closest('tr').remove(); calcTotal();" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-times"></i></button>
        </td>
    `;
    document.getElementById('billLines').appendChild(tr);
}

function calcTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.amt').forEach(inp => {
        grandTotal += parseFloat(inp.value) || 0;
    });
    document.getElementById('grandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

function prepareSubmit(e) {
    const lines = [];
    document.querySelectorAll('#billLines tr').forEach(row => {
        lines.push({
            account_id: row.querySelector('.acc-select').value,
            description: row.querySelector('.desc').value,
            amount: parseFloat(row.querySelector('.amt').value) || 0
        });
    });
    
    if (lines.length === 0) {
        alert("Please add at least one expense line.");
        e.preventDefault();
        return;
    }
    
    document.getElementById('linesJson').value = JSON.stringify(lines);
}

// Initialize
addLine();
</script>