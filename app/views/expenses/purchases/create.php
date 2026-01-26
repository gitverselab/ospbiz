<?php 
// Determine if we are in "Edit" or "Create" mode
$isEdit = isset($po); 
$actionUrl = $isEdit ? "/expenses/purchases/update" : "/expenses/purchases/store";
?>

<form action="<?= $actionUrl ?>" method="POST" class="max-w-6xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    
    <?php if($isEdit): ?>
        <input type="hidden" name="id" value="<?= $po['id'] ?>">
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800"><?= $isEdit ? "Edit" : "New" ?> Purchase Order</h2>
        <a href="/expenses/purchases" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Supplier</label>
            <select name="supplier_id" class="w-full border p-2 rounded bg-white" required>
                <option value="">-- Select Supplier --</option>
                <?php foreach($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($isEdit && $po['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">PO Number</label>
            <input type="text" name="po_number" value="<?= $isEdit ? htmlspecialchars($po['po_number']) : '' ?>" class="w-full border p-2 rounded" placeholder="e.g. PO-2023-001" required>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">PO Date</label>
            <input type="date" name="date" value="<?= $isEdit ? $po['date'] : date('Y-m-d') ?>" class="w-full border p-2 rounded">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Expected Delivery</label>
            <input type="date" name="expected_delivery_date" value="<?= $isEdit ? $po['expected_delivery_date'] : '' ?>" class="w-full border p-2 rounded">
        </div>
    </div>

    <h3 class="font-bold text-gray-700 mb-2">Order Items</h3>
    <div class="overflow-x-auto">
        <table class="w-full mb-6 border-collapse min-w-[800px]">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase w-1/4">Category</th>
                    <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Item Description</th>
                    <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-24">Qty</th>
                    <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Unit Price</th>
                    <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Total</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody id="poLines">
                </tbody>
        </table>
    </div>
    
    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold hover:underline mb-6 flex items-center">
        <i class="fa-solid fa-plus-circle mr-1"></i> Add Item Line
    </button>
    
    <input type="hidden" name="lines_json" id="linesJson">

    <div class="flex justify-end items-center border-t pt-4">
        <div class="mr-6 text-right">
            <div class="text-xs text-gray-500 uppercase">Grand Total</div>
            <div class="text-2xl font-bold text-gray-800" id="grandTotalDisplay">₱0.00</div>
        </div>
        <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-6 py-3 rounded font-bold hover:bg-blue-700 shadow">
            <?= $isEdit ? "Update Order" : "Save Order" ?>
        </button>
    </div>
</form>

<script>
// 1. Prepare Data for Edit Mode
const existingLines = <?= $isEdit ? json_encode($lines) : '[]' ?>;

// 2. Prepare Category Options (Server-side rendered into JS string)
// We build the <option> list once here to perform fast row addition
const categoryOptions = `
    <option value="">-- Select Category --</option>
    <?php foreach($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
    <?php endforeach; ?>
`;

// 3. Function to Add a Row
function addLine(data = null) {
    const tr = document.createElement('tr');
    tr.className = "border-b hover:bg-gray-50 align-top";
    
    // HTML Structure of a Row
    tr.innerHTML = `
        <td class="p-2">
            <select class="w-full border p-1 rounded text-sm category-select bg-gray-50 border-gray-300">
                ${categoryOptions}
            </select>
        </td>
        <td class="p-2">
            <input class="w-full border p-1 rounded text-sm desc border-gray-300" 
                   value="${data ? (data.description || '') : ''}" 
                   placeholder="Item name..." required>
        </td>
        <td class="p-2">
            <input class="w-full border p-1 rounded text-sm text-right qty border-gray-300" 
                   type="number" step="0.01" 
                   value="${data ? (data.quantity || 1) : 1}" 
                   oninput="calcRow(this)" required>
        </td>
        <td class="p-2">
            <input class="w-full border p-1 rounded text-sm text-right price border-gray-300" 
                   type="number" step="0.01" 
                   value="${data ? (data.unit_price || 0) : 0}" 
                   oninput="calcRow(this)" required>
        </td>
        <td class="p-2 text-right font-bold text-gray-700 row-total align-middle">
            0.00
        </td>
        <td class="p-2 text-center align-middle">
            <button type="button" onclick="this.closest('tr').remove(); calcTotal();" class="text-red-400 hover:text-red-600">
                <i class="fa-solid fa-times"></i>
            </button>
        </td>
    `;
    
    document.getElementById('poLines').appendChild(tr);
    
    // Set selected category if editing
    if(data && data.category_id) {
        tr.querySelector('.category-select').value = data.category_id;
    }
    
    // Calculate initial totals for this row
    calcRow(tr.querySelector('.qty'));
}

// 4. Calculate Row Total
function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const total = qty * price;
    
    row.querySelector('.row-total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2});
    
    calcTotal();
}

// 5. Calculate Grand Total
function calcTotal() {
    let grandTotal = 0;
    document.querySelectorAll('#poLines tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        grandTotal += (qty * price);
    });
    document.getElementById('grandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

// 6. Form Submission Handler
function prepareSubmit(e) {
    const lines = [];
    const rows = document.querySelectorAll('#poLines tr');
    
    rows.forEach(row => {
        const catId = row.querySelector('.category-select').value;
        const desc = row.querySelector('.desc').value;
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        
        lines.push({
            category_id: catId,
            description: desc,
            quantity: qty,
            unit_price: price,
            amount: qty * price
        });
    });
    
    // Validation
    if(lines.length === 0) {
        alert("Please add at least one item line.");
        e.preventDefault();
        return;
    }

    // Convert array to JSON string for PHP
    document.getElementById('linesJson').value = JSON.stringify(lines);
}

// 7. Initialize View
if (existingLines.length > 0) {
    existingLines.forEach(line => addLine(line));
} else {
    addLine(); // Add one empty row by default
}
</script>