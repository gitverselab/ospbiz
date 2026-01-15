<form action="/journal/store" method="POST" class="max-w-6xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">Create Manual Journal Entry</h2>
        <a href="/journal/list" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Journal No.</label>
            <input type="text" name="reference_no" value="<?= $nextNum ?>" class="w-full border p-2 rounded bg-gray-50 font-mono" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description</label>
            <input type="text" name="description" class="w-full border p-2 rounded" placeholder="e.g. Monthly Depreciation" required>
        </div>
    </div>

    <table class="w-full mb-6 border-collapse">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase w-1/3">Account</th>
                <th class="text-left p-2 text-xs font-bold text-gray-500 uppercase">Line Description</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Debit</th>
                <th class="text-right p-2 text-xs font-bold text-gray-500 uppercase w-32">Credit</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody id="jvLines"></tbody>
        <tfoot class="bg-gray-50 font-bold text-gray-800">
            <tr>
                <td colspan="2" class="p-2 text-right">Total:</td>
                <td class="p-2 text-right" id="totalDr">0.00</td>
                <td class="p-2 text-right" id="totalCr">0.00</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="5" class="p-2 text-center text-xs font-normal" id="balanceStatus">
                    Difference: <span class="font-bold">0.00</span>
                </td>
            </tr>
        </tfoot>
    </table>

    <button type="button" onclick="addLine()" class="text-blue-600 text-sm font-bold hover:underline mb-6">
        <i class="fa-solid fa-plus-circle"></i> Add Line
    </button>

    <input type="hidden" name="lines_json" id="linesJson">

    <div class="text-right">
        <button type="submit" onclick="prepareSubmit(event)" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow opacity-50 cursor-not-allowed" id="saveBtn" disabled>
            Post Entry
        </button>
    </div>
</form>

<script>
const accounts = <?= json_encode($accounts) ?>;

function addLine() {
    let options = '<option value="">Select Account...</option>';
    accounts.forEach(acc => {
        options += `<option value="${acc.id}">${acc.code} - ${acc.name}</option>`;
    });

    const tr = document.createElement('tr');
    tr.className = "border-b hover:bg-gray-50";
    tr.innerHTML = `
        <td class="p-2">
            <select class="w-full border p-1 rounded text-sm acc-select" required>${options}</select>
        </td>
        <td class="p-2">
            <input class="w-full border p-1 rounded text-sm desc" placeholder="(Optional)">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" class="w-full border p-1 rounded text-sm text-right dr" value="0" oninput="calcTotals()">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" class="w-full border p-1 rounded text-sm text-right cr" value="0" oninput="calcTotals()">
        </td>
        <td class="p-2 text-center">
            <button type="button" onclick="this.closest('tr').remove(); calcTotals();" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-times"></i></button>
        </td>
    `;
    document.getElementById('jvLines').appendChild(tr);
}

function calcTotals() {
    let dr = 0, cr = 0;
    document.querySelectorAll('.dr').forEach(i => dr += parseFloat(i.value) || 0);
    document.querySelectorAll('.cr').forEach(i => cr += parseFloat(i.value) || 0);

    document.getElementById('totalDr').innerText = dr.toLocaleString('en-US', {minimumFractionDigits:2});
    document.getElementById('totalCr').innerText = cr.toLocaleString('en-US', {minimumFractionDigits:2});

    const diff = Math.abs(dr - cr);
    const status = document.getElementById('balanceStatus');
    const btn = document.getElementById('saveBtn');

    if (diff < 0.01 && dr > 0) {
        status.innerHTML = '<span class="text-green-600 font-bold"><i class="fa-solid fa-check"></i> Balanced</span>';
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    } else {
        status.innerHTML = `<span class="text-red-600 font-bold">Out of Balance by ${diff.toLocaleString('en-US', {minimumFractionDigits:2})}</span>`;
        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }
}

function prepareSubmit(e) {
    const lines = [];
    document.querySelectorAll('#jvLines tr').forEach(row => {
        lines.push({
            account_id: row.querySelector('.acc-select').value,
            description: row.querySelector('.desc').value,
            debit: row.querySelector('.dr').value,
            credit: row.querySelector('.cr').value
        });
    });
    document.getElementById('linesJson').value = JSON.stringify(lines);
}

// Add 2 lines by default
addLine();
addLine();
</script>