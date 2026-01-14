<?php $childView = 'journal_form_content.php'; include '../app/views/layouts/main.php'; ?>

<h2 class="text-2xl font-bold mb-6">Create Journal Entry</h2>

<form action="/journal/create" method="POST" id="jeForm">
    <div class="bg-white p-6 rounded shadow mb-6">
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date" class="mt-1 block w-full border-gray-300 rounded shadow-sm p-2 border" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Reference</label>
                <input type="text" name="reference_no" class="mt-1 block w-full border-gray-300 rounded shadow-sm p-2 border" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded shadow-sm p-2 border">
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded shadow mb-6">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="p-2">Account</th>
                    <th class="p-2 w-32">Debit</th>
                    <th class="p-2 w-32">Credit</th>
                    <th class="p-2">Memo</th>
                    <th class="p-2 w-10"></th>
                </tr>
            </thead>
            <tbody id="linesContainer">
                </tbody>
        </table>
        <button type="button" onclick="addLine()" class="mt-4 text-blue-600 hover:text-blue-800">+ Add Line</button>
    </div>

    <input type="hidden" name="lines_json" id="linesInput">
    
    <div class="flex justify-end gap-2">
        <button type="button" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
        <button type="submit" onclick="prepareSubmit()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Draft</button>
    </div>
</form>

<script>
const accounts = <?php echo json_encode($accounts); ?>;
function addLine() {
    const tbody = document.getElementById('linesContainer');
    const tr = document.createElement('tr');
    let options = accounts.map(a => `<option value="${a.id}">${a.code} - ${a.name}</option>`).join('');
    
    tr.innerHTML = `
        <td class="p-2"><select class="w-full border p-1 account-select">${options}</select></td>
        <td class="p-2"><input type="number" step="0.01" class="w-full border p-1 debit-input" value="0.00"></td>
        <td class="p-2"><input type="number" step="0.01" class="w-full border p-1 credit-input" value="0.00"></td>
        <td class="p-2"><input type="text" class="w-full border p-1 memo-input"></td>
        <td class="p-2"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500">x</button></td>
    `;
    tbody.appendChild(tr);
}

function prepareSubmit() {
    const rows = document.querySelectorAll('#linesContainer tr');
    const lines = [];
    rows.forEach(row => {
        lines.push({
            account_id: row.querySelector('.account-select').value,
            debit: row.querySelector('.debit-input').value,
            credit: row.querySelector('.credit-input').value,
            memo: row.querySelector('.memo-input').value
        });
    });
    document.getElementById('linesInput').value = JSON.stringify(lines);
}
// Init one line
addLine(); addLine();
</script>