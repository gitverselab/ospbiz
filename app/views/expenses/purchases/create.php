<form action="/expenses/purchases/create" method="POST" id="poForm" class="bg-white p-6 rounded shadow">
    <div class="grid grid-cols-3 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">Supplier</label>
            <select name="supplier_id" class="w-full border p-2 rounded" required>
                <?php foreach($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">PO Number</label>
            <input type="text" name="po_number" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded">
        </div>
    </div>

    <table class="w-full mb-4">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-2">Description / Item</th>
                <th class="text-right p-2 w-24">Qty</th>
                <th class="text-right p-2 w-32">Unit Price</th>
                <th class="text-right p-2 w-32">Total</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody id="poLines"></tbody>
    </table>
    
    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold mb-6">+ Add Item</button>

    <input type="hidden" name="lines_json" id="linesJson">
    <div class="text-right">
        <button type="submit" onclick="prepareSubmit()" class="bg-blue-600 text-white px-6 py-2 rounded">Create PO</button>
    </div>
</form>

<script>
function addLine() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-1"><input class="w-full border p-1" name="desc" placeholder="Item name"></td>
        <td class="p-1"><input class="w-full border p-1 text-right" type="number" name="qty" value="1" oninput="calcRow(this)"></td>
        <td class="p-1"><input class="w-full border p-1 text-right" type="number" name="price" value="0" oninput="calcRow(this)"></td>
        <td class="p-1 text-right font-bold row-total">0.00</td>
        <td class="p-1 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500">x</button></td>
    `;
    document.getElementById('poLines').appendChild(tr);
}
function calcRow(el) {
    const row = el.closest('tr');
    const q = parseFloat(row.querySelector('[name="qty"]').value) || 0;
    const p = parseFloat(row.querySelector('[name="price"]').value) || 0;
    row.querySelector('.row-total').innerText = (q * p).toFixed(2);
}
function prepareSubmit() {
    const lines = [];
    document.querySelectorAll('#poLines tr').forEach(row => {
        lines.push({
            description: row.querySelector('[name="desc"]').value,
            quantity: row.querySelector('[name="qty"]').value,
            unit_price: row.querySelector('[name="price"]').value,
            amount: row.querySelector('.row-total').innerText
        });
    });
    document.getElementById('linesJson').value = JSON.stringify(lines);
}
addLine();
</script>