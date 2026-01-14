<h2 class="text-2xl font-bold text-gray-800 mb-6">Create Journal Entry</h2>

<form action="/journal/create" method="POST" id="jeForm">
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Date</label>
                <input type="date" name="date" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Reference</label>
                <input type="text" name="reference_no" placeholder="e.g. JE-2023-001" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Description</label>
                <input type="text" name="description" placeholder="Brief description of transaction" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-2 border">
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 mb-6 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 mb-4">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase">Account</th>
                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase w-32">Debit</th>
                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase w-32">Credit</th>
                    <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase">Memo</th>
                    <th class="px-3 py-3 w-10"></th>
                </tr>
            </thead>
            <tbody id="linesContainer" class="divide-y divide-gray-200">
                </tbody>
        </table>
        
        <button type="button" onclick="addLine()" class="text-sm text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
            <i class="fa-solid fa-plus-circle"></i> Add Line
        </button>
    </div>

    <input type="hidden" name="lines_json" id="linesInput">
    
    <div class="flex justify-end gap-3">
        <a href="/dashboard" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 text-sm font-medium">Cancel</a>
        <button type="submit" onclick="prepareSubmit(event)" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium shadow-sm">Save Draft</button>
    </div>
</form>

<script>
// Parse PHP array to JS
const accounts = <?php echo json_encode($accounts ?? []); ?>;

function addLine() {
    const tbody = document.getElementById('linesContainer');
    const tr = document.createElement('tr');
    
    // Create options for select
    let options = '<option value="">Select Account...</option>';
    accounts.forEach(a => {
        options += `<option value="${a.id}">${a.code} - ${a.name}</option>`;
    });
    
    tr.innerHTML = `
        <td class="px-2 py-2">
            <select class="account-select block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-1 border">
                ${options}
            </select>
        </td>
        <td class="px-2 py-2">
            <input type="number" step="0.01" class="debit-input block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-1 border text-right" placeholder="0.00">
        </td>
        <td class="px-2 py-2">
            <input type="number" step="0.01" class="credit-input block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-1 border text-right" placeholder="0.00">
        </td>
        <td class="px-2 py-2">
            <input type="text" class="memo-input block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm p-1 border">
        </td>
        <td class="px-2 py-2 text-center">
            <button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function prepareSubmit(e) {
    // Prevent default just in case we need to validate first
    // e.preventDefault(); 
    
    const rows = document.querySelectorAll('#linesContainer tr');
    const lines = [];
    let isValid = true;
    
    rows.forEach(row => {
        const accId = row.querySelector('.account-select').value;
        const deb = parseFloat(row.querySelector('.debit-input').value) || 0;
        const cred = parseFloat(row.querySelector('.credit-input').value) || 0;
        
        if(accId) {
            lines.push({
                account_id: accId,
                debit: deb,
                credit: cred,
                memo: row.querySelector('.memo-input').value
            });
        }
    });

    if (lines.length === 0) {
        alert("Please add at least one transaction line.");
        e.preventDefault();
        return false;
    }

    document.getElementById('linesInput').value = JSON.stringify(lines);
    // document.getElementById('jeForm').submit(); // Uncomment if you removed the submit type
}

// Initialize with 2 empty lines
window.onload = function() {
    addLine(); 
    addLine();
};
</script>