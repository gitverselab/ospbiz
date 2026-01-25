<?php 
$isEdit = isset($po); 
$actionUrl = $isEdit ? "/expenses/purchases/update" : "/expenses/purchases/store";
?>

<form action="<?= $actionUrl ?>" method="POST" class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    
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
                <option value="">Select Supplier...</option>
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

    <h3 class="font-bold text-gray-700 mb-2">Items</h3>
    <table class="w-full mb-6 border-collapse">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Item Description</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-24">Qty</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Unit Price</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Total</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody id="poLines"></tbody>
    </table>
    
    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold hover:underline mb-6">
        <i class="fa-solid fa-plus-circle"></i> Add Item Line
    </button>
    
    <input type="hidden" name="lines_json" id="linesJson">

    <div class="flex justify-end items-center border-t pt-4">
        <div class="mr-6 text-right">
            <div class="text-xs text-gray-500 uppercase">Grand Total</div>
            <div class="text-2xl font-bold text-gray-800" id="grandTotalDisplay">₱0.00</div>
        </div>
        <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-6 py-3 rounded font-bold hover:bg-blue-700 shadow">
            <?= $isEdit ? "Update" : "Save" ?> Purchase Order
        </button>
    </div>
</form>

<script>
// If Editing, load existing lines
const existingLines = <?= $isEdit ? json_encode($lines) : '[]' ?>;

function addLine(data = null) {
    const tr = document.createElement('tr');
    tr.className = "border-b hover:bg-gray-50";
    tr.innerHTML = `
        <td class="p-2"><input class="w-full border p-1 rounded text-sm desc" value="${data ? data.description : ''}" placeholder="Enter item name..." required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm text-right qty" type="number" step="0.01" value="${data ? data.quantity : 1}" oninput="calcRow(this)" required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm text-right price" type="number" step="0.01" value="${data ? data.unit_price : 0}" oninput="calcRow(this)" required></td>
        <td class="p-2 text-right font-bold text-gray-700 row-total">0.00</td>
        <td class="p-2 text-center"><button type="button" onclick="this.closest('tr').remove(); calcTotal();" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-times"></i></button></td>
    `;
    document.getElementById('poLines').appendChild(tr);
    calcRow(tr.querySelector('.qty')); // Calc initial total
}

function calcRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const total = qty * price;
    row.querySelector('.row-total').innerText = total.toLocaleString('en-US', {minimumFractionDigits: 2});
    calcTotal();
}

function calcTotal() {
    let grandTotal = 0;
    document.querySelectorAll('#poLines tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        grandTotal += (qty * price);
    });
    document.getElementById('grandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

function prepareSubmit(e) {
    const lines = [];
    document.querySelectorAll('#poLines tr').forEach(row => {
        lines.push({
            description: row.querySelector('.desc').value,
            quantity: row.querySelector('.qty').value,
            unit_price: row.querySelector('.price').value,
            amount: (parseFloat(row.querySelector('.qty').value) * parseFloat(row.querySelector('.price').value))
        });
    });
    
    if(lines.length === 0) {
        alert("Please add at least one item.");
        e.preventDefault();
        return;
    }
    document.getElementById('linesJson').value = JSON.stringify(lines);
}

// Initialize
if (existingLines.length > 0) {
    existingLines.forEach(line => addLine(line));
} else {
    addLine();
}
</script>