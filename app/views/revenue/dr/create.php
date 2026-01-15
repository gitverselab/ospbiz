<form action="/revenue/dr/store" method="POST" class="max-w-6xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">Create Manual DR</h2>
        <a href="/revenue/dr" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">DR Number (Ref Doc)</label>
            <input type="text" name="dr_number" class="w-full border p-2 rounded" placeholder="e.g. 5086" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Delivery Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Customer Name</label>
            <input type="text" name="customer_name" class="w-full border p-2 rounded" placeholder="e.g. Zennith Foods" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Plant Code</label>
            <input type="text" name="plant_code" class="w-full border p-2 rounded" placeholder="e.g. PL01">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">PO Number</label>
            <input type="text" name="po_number" class="w-full border p-2 rounded" placeholder="Optional">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">GR Number</label>
            <input type="text" name="gr_number" class="w-full border p-2 rounded" placeholder="Optional">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Currency</label>
            <select name="currency" class="w-full border p-2 rounded bg-white">
                <option value="PHP">PHP</option>
                <option value="USD">USD</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">VAT Included?</label>
            <select name="is_vat_inc" class="w-full border p-2 rounded bg-white">
                <option value="1">Yes (Inc. VAT)</option>
                <option value="0">No (Add VAT)</option>
            </select>
        </div>
    </div>
    
    <div class="mb-6">
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Status</label>
        <select name="status" class="w-full md:w-1/4 border p-2 rounded bg-white">
            <option value="pending">Pending</option>
            <option value="delivered">Delivered</option>
        </select>
    </div>

    <h3 class="font-bold text-gray-700 mb-2">Items</h3>
    <table class="w-full mb-6 border-collapse">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Item Code</th>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Description</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-24">Qty</th>
                <th class="text-center p-2 text-xs font-bold text-gray-500 uppercase w-20">UOM</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Unit Price</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Total</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody id="drLines"></tbody>
    </table>
    
    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold hover:underline mb-6">
        <i class="fa-solid fa-plus-circle"></i> Add Item Line
    </button>
    
    <input type="hidden" name="lines_json" id="linesJson">

    <div class="flex justify-end items-center border-t pt-4 gap-4">
        <div class="text-right">
            <div class="text-xs text-gray-500 uppercase">Grand Total</div>
            <div class="text-2xl font-bold text-gray-800" id="grandTotalDisplay">0.00</div>
        </div>
        <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-6 py-3 rounded font-bold hover:bg-blue-700 shadow">
            Save Record
        </button>
    </div>
</form>

<script>
function addLine() {
    const tr = document.createElement('tr');
    tr.className = "border-b hover:bg-gray-50";
    tr.innerHTML = `
        <td class="p-2"><input class="w-full border p-1 rounded text-sm code" placeholder="Code..." required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm desc" placeholder="Description..." required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm text-right qty" type="number" step="0.0001" value="1" oninput="calcRow(this)" required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm text-center uom" placeholder="PCS" required></td>
        <td class="p-2"><input class="w-full border p-1 rounded text-sm text-right price" type="number" step="0.01" value="0" oninput="calcRow(this)" required></td>
        <td class="p-2 text-right font-bold text-gray-700 row-total">0.00</td>
        <td class="p-2 text-center"><button type="button" onclick="this.closest('tr').remove(); calcTotal();" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-times"></i></button></td>
    `;
    document.getElementById('drLines').appendChild(tr);
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
    document.querySelectorAll('#drLines tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        grandTotal += (qty * price);
    });
    document.getElementById('grandTotalDisplay').innerText = grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
}

function prepareSubmit(e) {
    const lines = [];
    document.querySelectorAll('#drLines tr').forEach(row => {
        lines.push({
            item_code: row.querySelector('.code').value,
            description: row.querySelector('.desc').value,
            quantity: row.querySelector('.qty').value,
            uom: row.querySelector('.uom').value,
            price: row.querySelector('.price').value
        });
    });
    
    if(lines.length === 0) {
        alert("Add at least one item.");
        e.preventDefault();
        return;
    }
    document.getElementById('linesJson').value = JSON.stringify(lines);
}

// Init
addLine();
</script>