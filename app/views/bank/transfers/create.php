<div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-sm border border-gray-200">
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800">New Fund Transfer Request</h2>
        <a href="/bank/transfers" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
    </div>

    <form action="/bank/transfers/store" method="POST" class="space-y-6">
        
        <div class="bg-blue-50 p-4 rounded border border-blue-100">
            <label class="block text-sm font-bold text-blue-800 uppercase mb-2">1. Select Transfer Method</label>
            <select name="method" id="methodSelect" onchange="updateAccountOptions()" class="w-full border p-2 rounded text-sm font-bold bg-white" required>
                <option value="">-- Select Method First --</option>
                <option value="bank_transfer">Online / Bank Transfer</option>
                <option value="check">Issue Check</option>
                <option value="cash_handover">Cash Handover</option>
            </select>
            <p class="text-xs text-blue-600 mt-1">This will determine which accounts are available below.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Date</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full border p-2 rounded" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Reference No.</label>
                <input type="text" name="reference_no" class="w-full border p-2 rounded" placeholder="Ref No...">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <label class="block text-xs font-bold text-red-700 uppercase mb-2">From (Source)</label>
                <select name="from_account_id" id="sourceSelect" class="w-full border p-2 rounded bg-white" required>
                    <option value="">-- Select Method First --</option>
                </select>
            </div>
            <div class="bg-gray-50 p-4 rounded border border-gray-200">
                <label class="block text-xs font-bold text-green-700 uppercase mb-2">To (Destination)</label>
                <select name="to_account_id" id="destSelect" class="w-full border p-2 rounded bg-white" required>
                    <option value="">-- Select Method First --</option>
                </select>
            </div>
        </div>

        <div id="checkField" class="hidden bg-yellow-50 p-4 rounded border border-yellow-200">
            <label class="block text-xs font-bold text-yellow-800 uppercase mb-1">Check Number</label>
            <input type="text" name="check_number" id="checkInput" class="w-full border p-2 rounded font-mono bg-white" placeholder="e.g. 0001234">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Amount</label>
                <input type="number" step="0.01" name="amount" class="w-full border p-2 rounded text-xl font-bold text-right" placeholder="0.00" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Description / Memo</label>
                <input type="text" name="description" class="w-full border p-2 rounded" placeholder="Reason for transfer...">
            </div>
        </div>

        <div class="pt-4 flex justify-end">
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-bold hover:bg-blue-700 shadow">
                Create Request
            </button>
        </div>
    </form>
</div>

<script>
// Pass PHP Accounts to JS
const accounts = <?= json_encode($accounts) ?>;

function updateAccountOptions() {
    const method = document.getElementById('methodSelect').value;
    const sourceSel = document.getElementById('sourceSelect');
    const destSel = document.getElementById('destSelect');
    const checkField = document.getElementById('checkField');
    const checkInput = document.getElementById('checkInput');

    // Reset Dropdowns
    sourceSel.innerHTML = '<option value="">-- Select Source --</option>';
    destSel.innerHTML = '<option value="">-- Select Destination --</option>';

    if (!method) return;

    // Toggle Check Field
    if (method === 'check') {
        checkField.classList.remove('hidden');
        checkInput.required = true;
    } else {
        checkField.classList.add('hidden');
        checkInput.required = false;
        checkInput.value = '';
    }

    // Filter Logic based on User Request
    let sourceFilter = [];
    let destFilter = [];

    if (method === 'bank_transfer') {
        // Bank -> Bank
        sourceFilter = accounts.filter(a => a.type !== 'cash');
        destFilter = accounts.filter(a => a.type !== 'cash');
    } else if (method === 'check') {
        // Source MUST be Bank. Dest can be anything (Bank or Cash).
        sourceFilter = accounts.filter(a => a.type !== 'cash'); 
        destFilter = accounts; 
    } else if (method === 'cash_handover') {
        // Cash -> Cash
        sourceFilter = accounts.filter(a => a.type === 'cash');
        destFilter = accounts.filter(a => a.type === 'cash');
    }

    // Populate Source
    sourceFilter.forEach(acc => {
        let opt = new Option(acc.name + " (" + acc.type.toUpperCase() + ") - ₱" + parseFloat(acc.current_balance).toLocaleString(), acc.id);
        sourceSel.add(opt);
    });

    // Populate Destination
    destFilter.forEach(acc => {
        let opt = new Option(acc.name + " (" + acc.type.toUpperCase() + ")", acc.id);
        destSel.add(opt);
    });
}
</script>