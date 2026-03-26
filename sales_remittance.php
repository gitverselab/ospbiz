<?php
// sales_remittance.php
require_once "includes/header.php";
require_once "config/database.php";

$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);
$cash_accounts = $conn->query("SELECT id, account_name FROM cash_accounts")->fetch_all(MYSQLI_ASSOC);
$passbooks = $conn->query("SELECT id, bank_name, account_number FROM passbooks")->fetch_all(MYSQLI_ASSOC);
$coa_list = $conn->query("SELECT id, account_name, account_type FROM chart_of_accounts WHERE account_type IN ('Income', 'Equity', 'Asset') ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Payment Remittance</h2>
    <a href="sales.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Back to Invoices</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-bold mb-4 text-blue-800">1. Select Customer & Invoices</h3>
            <div class="mb-4">
                <label class="font-semibold block mb-1">Customer</label>
                <select id="customer_id" class="w-full border rounded p-2 text-lg" onchange="loadUnpaidInvoices()">
                    <option value="">-- Select Customer --</option>
                    <?php foreach($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="overflow-x-auto border rounded-md">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="p-3 text-center w-10"><input type="checkbox" onchange="toggleAll(this)"></th>
                            <th class="p-3 text-left">Invoice #</th>
                            <th class="p-3 text-left">Date</th>
                            <th class="p-3 text-right">Total Amount</th>
                            <th class="p-3 text-right text-red-600">Less: WHT</th>
                            <th class="p-3 text-right">Net Payable</th>
                            <th class="p-3 text-right w-32">Payment Now</th> 
                        </tr>
                    </thead>
                    <tbody id="invoice_list">
                        <tr><td colspan="7" class="p-4 text-center text-gray-500">Select a customer.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-2 text-right text-sm text-gray-500" id="record_count">0 invoices found</div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-lg shadow-md sticky top-6">
            <h3 class="text-lg font-bold mb-4 text-green-800">2. Payment Details</h3>
            <form id="remittanceForm">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border rounded p-2" required>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-blue-700">Passbook Category (Ledger)</label>
                        <select name="chart_of_account_id" class="w-full border rounded p-2 border-blue-200 bg-blue-50">
                            <option value="">-- Default (Uncategorized) --</option>
                            <?php foreach($coa_list as $coa): ?>
                                <option value="<?php echo $coa['id']; ?>">
                                    <?php echo htmlspecialchars($coa['account_name']); ?> 
                                    <span class="text-gray-400 text-xs">(<?php echo $coa['account_type']; ?>)</span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="w-full border rounded p-2" onchange="toggleSource()">
                            <option value="Check">Check</option>
                            <option value="Cash on Hand">Cash on Hand</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div id="bank_div" class="hidden">
                        <label class="block text-sm font-semibold">Deposit To (Bank)</label>
                        <select name="passbook_id" class="w-full border rounded p-2">
                            <?php foreach($passbooks as $pb): ?>
                                <option value="<?php echo $pb['id']; ?>"><?php echo htmlspecialchars($pb['bank_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="cash_div" class="hidden">
                        <label class="block text-sm font-semibold">Deposit To (Cash)</label>
                        <select name="cash_account_id" class="w-full border rounded p-2">
                            <?php foreach($cash_accounts as $ca): ?>
                                <option value="<?php echo $ca['id']; ?>"><?php echo htmlspecialchars($ca['account_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="check_details" class="space-y-3 p-3 bg-gray-50 border rounded">
                        <div>
                            <label class="text-xs font-bold">Check Number</label>
                            <input type="text" name="check_number" class="w-full border rounded p-2" placeholder="e.g. 12345678">
                        </div>
                        <div>
                            <label class="text-xs font-bold">Check Date</label>
                            <input type="date" name="check_date" class="w-full border rounded p-2">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold">Notes / Remarks</label>
                        <textarea name="notes" class="w-full border rounded p-2" rows="2"></textarea>
                    </div>

                    <div class="border-t pt-4 space-y-2">
                        <div class="flex justify-between text-xl font-bold text-green-700 mt-2">
                            <span>Total Payment:</span>
                            <span id="lbl_net_total">₱0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg shadow-md mt-4">
                        Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleSource() {
        const method = document.getElementById('payment_method').value;
        const checkDetails = document.getElementById('check_details');
        
        // Show check details ONLY if method is Check
        if (method === 'Check') {
            checkDetails.classList.remove('hidden');
        } else {
            checkDetails.classList.add('hidden');
        }

        if(method === 'Cash on Hand') {
            document.getElementById('cash_div').classList.remove('hidden');
            document.getElementById('bank_div').classList.add('hidden');
        } else {
            document.getElementById('cash_div').classList.add('hidden');
            document.getElementById('bank_div').classList.remove('hidden');
        }
    }
    toggleSource(); 

    function loadUnpaidInvoices() {
        const cid = document.getElementById('customer_id').value;
        const tbody = document.getElementById('invoice_list');
        
        if(!cid) {
            tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-gray-500">Select a customer.</td></tr>';
            return;
        }

        tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-gray-500">Loading...</td></tr>';

        fetch(`api/get_customer_unpaid_invoices.php?id=${cid}`)
            .then(res => res.json())
            .then(data => {
                if(data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="p-4 text-center text-gray-500">No unpaid invoices found.</td></tr>';
                    document.getElementById('record_count').innerText = '0 invoices';
                    return;
                }

                let html = '';
                data.forEach(inv => {
                    const total = parseFloat(inv.total_amount);
                    const paid = parseFloat(inv.total_paid || 0);
                    const returns = parseFloat(inv.total_deductions || 0);
                    const wht_recorded = parseFloat(inv.withholding_tax || 0);
                    const net_payable = Math.max(0, total - paid - returns - wht_recorded);

                    html += `<tr class="border-b hover:bg-blue-50 transition">
                        <td class="p-3 text-center">
                            <input type="checkbox" class="inv-cb w-5 h-5" value="${inv.id}" onchange="rowSelect(this)">
                        </td>
                        <td class="p-3 font-mono font-bold text-blue-700">${inv.invoice_number}</td>
                        <td class="p-3 text-gray-600 text-xs">${inv.invoice_date}</td>
                        <td class="p-3 text-right text-gray-500">${total.toLocaleString()}</td>
                        <td class="p-3 text-right text-red-600 text-xs font-bold">(${wht_recorded.toLocaleString()})</td>
                        <td class="p-3 text-right font-bold text-gray-800">${net_payable.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="p-3 text-right">
                            <input type="number" step="0.01" class="pay-input w-full border rounded px-1 text-right font-bold text-green-700" 
                                disabled value="${net_payable.toFixed(2)}" oninput="calcTotals()">
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                document.getElementById('record_count').innerText = `${data.length} invoices found`;
                calcTotals();
            });
    }

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.inv-cb');
        checkboxes.forEach(cb => {
            cb.checked = source.checked;
            rowSelect(cb); 
        });
    }

    function rowSelect(cb) {
        const row = cb.closest('tr');
        const payInput = row.querySelector('.pay-input');
        if (cb.checked) {
            payInput.disabled = false;
            row.classList.add('bg-blue-100');
        } else {
            payInput.disabled = true;
            row.classList.remove('bg-blue-100');
        }
        calcTotals();
    }

    function calcTotals() {
        let subTotal = 0;
        document.querySelectorAll('.inv-cb:checked').forEach(cb => {
            const row = cb.closest('tr');
            const pay = parseFloat(row.querySelector('.pay-input').value) || 0;
            subTotal += pay;
        });
        document.getElementById('lbl_net_total').innerText = '₱' + subTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
    }

    document.getElementById('remittanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const payments = [];
        document.querySelectorAll('.inv-cb:checked').forEach(cb => {
            const row = cb.closest('tr');
            payments.push({
                sale_id: cb.value,
                amount: row.querySelector('.pay-input').value
            });
        });

        if (payments.length === 0) {
            alert("Please select at least one invoice to pay.");
            return;
        }

        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerText;
        btn.disabled = true; 
        btn.innerText = "Processing...";

        const formData = new FormData(this);
        formData.append('invoices', JSON.stringify(payments));

        fetch('api/record_remittance.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    loadUnpaidInvoices();
                    document.getElementById('remittanceForm').reset();
                    document.getElementById('lbl_net_total').innerText = '₱0.00';
                    toggleSource();
                    btn.disabled = false;
                    btn.innerText = originalText;
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.innerText = originalText;
                }
            })
            .catch(err => {
                alert('Network error occurred.');
                btn.disabled = false;
                btn.innerText = originalText;
            });
    });
</script>

<?php require_once "includes/footer.php"; ?>