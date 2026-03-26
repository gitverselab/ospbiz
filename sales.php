<?php
// sales.php
require_once "includes/header.php";
require_once "config/database.php";

// Fetch customers
$customers = $conn->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetch_all(MYSQLI_ASSOC);

// Fetch Income Categories
$income_accounts = $conn->query("SELECT id, account_name FROM chart_of_accounts WHERE account_type = 'Income' ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);

// --- 1. PAGINATION & SEARCH ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Count Total Sales (For Pagination)
$total_sql = "SELECT COUNT(id) as total FROM sales";
$total_records = $conn->query($total_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch Sales with LIMIT
$sales = $conn->query("
    SELECT s.*, c.customer_name, coa.account_name 
    FROM sales s 
    JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN chart_of_accounts coa ON s.chart_of_account_id = coa.id
    ORDER BY s.invoice_date DESC, s.invoice_number DESC
    LIMIT $offset, $records_per_page
")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Sales Invoices</h2>
    <button onclick="openModal('saleModal')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Create New Invoice</button>
</div>

<div id="alertBox" class="hidden mb-4 p-4 rounded-md shadow-sm border-l-4" role="alert">
    <p id="alertMessage"></p>
</div>

<div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
    <table class="w-full table-auto">
        <thead>
            <tr>
                <th class="p-3 text-sm font-semibold text-left">Invoice #</th>
                <th class="p-3 text-sm font-semibold text-left">Customer</th>
                <th class="p-3 text-sm font-semibold text-left">Category</th>
                <th class="p-3 text-sm font-semibold text-left">Due Date</th>
                <th class="p-3 text-sm font-semibold text-right">Gross Amount</th>
                <th class="p-3 text-sm font-semibold text-right text-red-600">Less: Returns</th>
                <th class="p-3 text-sm font-semibold text-right text-orange-600">Less: WHT</th>
                <th class="p-3 text-sm font-semibold text-right">Net Receivable</th>
                <th class="p-3 text-sm font-semibold text-center">Status</th>
                <th class="p-3 text-sm font-semibold text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($sales)): foreach ($sales as $sale): 
                // Math Logic:
                // 'total_amount' in DB is (Gross - RTS).
                // Gross = total_amount + total_deductions (RTS).
                // Net Receivable (Cash Expectation) = total_amount - withholding_tax.
                
                $rts = $sale['total_deductions'] ?? 0;
                $wht = $sale['withholding_tax'] ?? 0;
                $gross = $sale['total_amount'] + $rts;
                $net_receivable = $sale['total_amount'] - $wht;
            ?>
            <tr class="border-b hover:bg-gray-50">
                <td class="p-3 font-semibold">
                    <a href="view_sale.php?id=<?php echo $sale['id']; ?>" class="text-blue-600 hover:underline">
                        <?php echo htmlspecialchars($sale['invoice_number']); ?>
                    </a>
                </td>
                <td class="p-3"><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                <td class="p-3 text-sm text-gray-600"><?php echo htmlspecialchars($sale['account_name'] ?? 'N/A'); ?></td>
                <td class="p-3"><?php echo htmlspecialchars($sale['payment_due_date']); ?></td>
                
                <td class="p-3 text-right text-gray-500">₱<?php echo number_format($gross, 2); ?></td>
                
                <td class="p-3 text-right text-red-600"><?php echo ($rts > 0) ? '-₱'.number_format($rts, 2) : '-'; ?></td>
                
                <td class="p-3 text-right text-orange-600"><?php echo ($wht > 0) ? '-₱'.number_format($wht, 2) : '-'; ?></td>
                
                <td class="p-3 text-right font-bold text-gray-800">₱<?php echo number_format($net_receivable, 2); ?></td>
                
                <td class="p-3 text-center">
                    <span class="px-2 py-1 text-xs rounded-full font-semibold <?php 
                        if ($sale['status'] === 'Paid') { echo 'bg-green-200 text-green-800'; } 
                        elseif ($sale['status'] === 'Partial') { echo 'bg-blue-200 text-blue-800'; } 
                        else { echo 'bg-yellow-200 text-yellow-800'; }
                    ?>">
                        <?php echo htmlspecialchars($sale['status']); ?>
                    </span>
                </td>

                <td class="p-3 text-center space-x-2">
                    <a href="view_sale.php?id=<?php echo $sale['id']; ?>" class="text-blue-500 hover:underline">View</a>
                    <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($sale), ENT_QUOTES, 'UTF-8'); ?>)' class="text-green-500 hover:underline">Edit</button>
                    <button onclick="deleteSale(<?php echo $sale['id']; ?>)" class="text-red-500 hover:underline">Delete</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="p-4 text-center text-gray-500">No sales invoices found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex justify-between items-center no-print">
    <span class="text-sm text-gray-700">
        Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> Results
    </span>
    <div class="flex items-center space-x-1">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-md hover:bg-gray-100">Previous</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="px-3 py-1 border rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-md hover:bg-gray-100">Next</a>
        <?php endif; ?>
    </div>
</div>

<div id="saleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
        <form id="saleForm">
            <input type="hidden" name="withholding_tax" id="withholding_tax" value="0.00">

            <h3 class="text-2xl font-bold mb-4">Create Sales Invoice</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="font-semibold">Customer</label>
                    <select name="customer_id" id="customer_id" class="mt-1 block w-full rounded-md border-gray-300" required onchange="loadCustomerData()">
                        <option value="">Select a customer...</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="font-semibold">Income Category</label>
                    <select name="chart_of_account_id" id="chart_of_account_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                        <option value="">Select Category...</option>
                        <?php foreach($income_accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                 <div>
                    <label class="font-semibold">Official Invoice #</label>
                    <input type="text" name="invoice_number" placeholder="e.g. 001234" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div>
                    <label class="font-semibold">Invoice Date</label>
                    <input type="date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
                <div>
                    <label class="font-semibold">Payment Due Date</label>
                    <input type="date" name="due_date" class="mt-1 block w-full rounded-md border-gray-300" required>
                </div>
            </div>
            
            <div class="mt-4 mb-2 flex justify-between items-center">
                <h4 class="font-semibold text-blue-700">1. Select Delivery Receipts (Add)</h4>
                <input type="text" id="search_dr" placeholder="Search DR #" class="border rounded px-2 py-1 text-sm" onkeyup="filterTable('dr_list_container', this.value)">
            </div>
            <div id="dr_list_container" class="border rounded-md p-4 h-40 overflow-y-auto bg-gray-50 mb-4">
                <p class="text-gray-500">Please select a customer to see available delivery receipts.</p>
            </div>

            <div class="mt-4 mb-2 flex justify-between items-center">
                <h4 class="font-semibold text-red-700">2. Select Returns / RTS (Deduct)</h4>
                <input type="text" id="search_rts" placeholder="Search RTS #" class="border rounded px-2 py-1 text-sm" onkeyup="filterTable('rts_list_container', this.value)">
            </div>
            <div id="rts_list_container" class="border rounded-md p-4 h-32 overflow-y-auto bg-gray-50">
                <p class="text-gray-500">Please select a customer to see available returns.</p>
            </div>

            <div class="flex flex-col items-end mt-4">
                <table class="text-right">
                    <tr><td class="pr-4 text-gray-600">Subtotal (DRs):</td><td class="font-bold w-32" id="sub_total">₱0.00</td></tr>
                    <tr><td class="pr-4 text-red-600">Less Returns (RTS):</td><td class="font-bold text-red-600 border-b border-gray-300 pb-1" id="rts_total">-₱0.00</td></tr>
                    <tr><td class="pr-4 text-gray-800 font-semibold pt-1">Total Invoice Amount:</td><td class="font-bold text-gray-800 pt-1" id="gross_total">₱0.00</td></tr>
                    <tr><td class="pr-4 text-orange-600 font-semibold">Less WHT (1% of Base):</td><td class="font-bold text-orange-600 border-b border-gray-400 pb-1" id="wht_total">-₱0.00</td></tr>
                    <tr><td class="pr-4 text-blue-800 font-bold text-xl pt-2">Net Payable:</td><td class="font-bold text-blue-800 text-2xl pt-2" id="net_total">₱0.00</td></tr>
                </table>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('saleModal')" class="bg-gray-200 py-2 px-4 rounded-lg mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<div id="editSaleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg">
        <form id="editSaleForm">
            <h3 class="text-2xl font-bold mb-4">Edit Invoice Dates</h3>
            <input type="hidden" name="id" id="edit_sale_id">
            <div class="space-y-4">
                <div><label>Invoice Date</label><input type="date" name="invoice_date" id="edit_invoice_date" class="mt-1 block w-full" required></div>
                <div><label>Payment Due Date</label><input type="date" name="payment_due_date" id="edit_payment_due_date" class="mt-1 block w-full" required></div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" onclick="closeModal('editSaleModal')" class="bg-gray-200 py-2 px-4 rounded-lg">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    const alertBox = document.getElementById('alertBox');
    const alertMessage = document.getElementById('alertMessage');

    function handleApiResponse(response) {
        if (response.success) {
            alertMessage.textContent = response.message || 'Success!';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-green-100 border-green-500 text-green-700';
            alertBox.classList.remove('hidden');
            setTimeout(() => location.reload(), 1000);
        } else {
            alertMessage.textContent = response.message || 'An error occurred.';
            alertBox.className = 'mb-4 p-4 rounded-md shadow-sm border-l-4 bg-red-100 border-red-500 text-red-700';
            alertBox.classList.remove('hidden');
        }
        closeModal('saleModal');
        closeModal('editSaleModal');
    }

    function disableButton(form) {
        const btn = form.querySelector('button[type="submit"]');
        if(btn) {
            btn.dataset.originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = "Processing...";
        }
        return btn;
    }

    function enableButton(btn) {
        if(btn) {
            btn.disabled = false;
            btn.innerText = btn.dataset.originalText || "Save";
        }
    }

    // --- ADD SALE ---
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = disableButton(this);
        
        fetch('api/add_sale.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(data => {
            if (data.success) handleApiResponse(data);
            else { alert(data.message); enableButton(btn); }
        })
        .catch(err => { alert('Network error'); enableButton(btn); });
    });

    // --- UPDATE SALE ---
    document.getElementById('editSaleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = disableButton(this);
        
        fetch('api/update_sale.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(data => {
                if(data.success) handleApiResponse(data); 
                else { alert(data.message); enableButton(btn); }
            })
            .catch(err => { alert('Network error'); enableButton(btn); });
    });

    function loadCustomerData() {
        const id = document.getElementById('customer_id').value;
        const drContainer = document.getElementById('dr_list_container');
        const rtsContainer = document.getElementById('rts_list_container');

        if (!id) {
            drContainer.innerHTML = '<p class="text-gray-500">Please select a customer.</p>';
            rtsContainer.innerHTML = '<p class="text-gray-500">Please select a customer.</p>';
            updateTotal();
            return;
        }

        drContainer.innerHTML = '<p class="text-gray-500">Loading DRs...</p>';
        rtsContainer.innerHTML = '<p class="text-gray-500">Loading Returns...</p>';

        fetch(`api/get_customer_drs.php?customer_id=${id}`)
            .then(res => res.json())
            .then(data => renderTable('dr_list_container', data.drs || [], 'dr_ids[]', 'dr_number', 'vat_inclusive_amount', false));

        fetch(`api/get_customer_rts.php?customer_id=${id}`)
            .then(res => res.json())
            .then(data => renderTable('rts_list_container', data || [], 'rts_ids[]', 'rts_number', 'total_amount', true));
    }

    function renderTable(containerId, data, inputName, numField, amtField, isRts) {
        let html = `<table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="w-8 p-1 text-center">
                        <input type="checkbox" onclick="toggleSelectAll(this, '${containerId}')" title="Select All Visible">
                    </th>
                    <th class="text-left p-1">Number</th>
                    <th class="text-left p-1">Item/Desc</th>
                    <th class="text-right p-1">Amount</th>
                </tr>
            </thead>
            <tbody>`;

        if (data.length === 0) {
            document.getElementById(containerId).innerHTML = '<p class="text-gray-500 p-2">No records found.</p>';
            return;
        }

        data.forEach(d => {
            let color = isRts ? 'text-red-600' : '';
            let prefix = isRts ? '-' : '';
            html += `<tr>
                <td class="text-center p-1">
                    <input type="checkbox" name="${inputName}" value="${d.id}" onchange="updateTotal()" data-amount="${d[amtField]}">
                </td>
                <td class="font-medium p-1">${d[numField]}</td>
                <td class="truncate max-w-[150px] text-xs text-gray-600 p-1">${d.item_code || d.description || ''}</td>
                <td class="text-right ${color} p-1">${prefix}₱${parseFloat(d[amtField]).toFixed(2)}</td>
            </tr>`;
        });
        document.getElementById(containerId).innerHTML = html + '</tbody></table>';
        updateTotal();
    }

    function toggleSelectAll(source, containerId) {
        const container = document.getElementById(containerId);
        const rows = container.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const cb = row.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = source.checked;
            }
        });
        updateTotal();
    }

    function filterTable(containerId, searchText) {
        const filter = searchText.toLowerCase();
        const container = document.getElementById(containerId);
        const rows = container.querySelectorAll('tbody tr');

        const headerCheckbox = container.querySelector('thead input[type="checkbox"]');
        if(headerCheckbox) headerCheckbox.checked = false;

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    }

    // --- UPDATED CALCULATOR WITH WHT LOGIC ---
    function updateTotal() {
        let drTotal = 0;
        let rtsTotal = 0;

        document.querySelectorAll('input[name="dr_ids[]"]:checked').forEach(cb => {
            drTotal += parseFloat(cb.dataset.amount) || 0;
        });
        document.querySelectorAll('input[name="rts_ids[]"]:checked').forEach(cb => {
            rtsTotal += parseFloat(cb.dataset.amount) || 0;
        });

        // VAT Inclusive Total (Gross - RTS)
        const grossTotal = drTotal - rtsTotal;

        // Base amount (without VAT)
        const vatableSales = grossTotal / 1.12;
        
        // 1% WHT on Base
        const whtAmount = vatableSales * 0.01;

        // Final Net Payable (Cash out)
        const amountDue = grossTotal - whtAmount;

        // Populate hidden input so DB saves the tax amount
        const whtInput = document.getElementById('withholding_tax');
        if (whtInput) {
            whtInput.value = whtAmount.toFixed(2);
        }

        // Update visual display
        document.getElementById('sub_total').innerText = '₱' + drTotal.toFixed(2);
        document.getElementById('rts_total').innerText = '-₱' + rtsTotal.toFixed(2);
        document.getElementById('gross_total').innerText = '₱' + grossTotal.toFixed(2);
        document.getElementById('wht_total').innerText = '-₱' + whtAmount.toFixed(2);
        document.getElementById('net_total').innerText = '₱' + amountDue.toFixed(2);
    }

    function openEditModal(sale) {
        document.getElementById('edit_sale_id').value = sale.id;
        document.getElementById('edit_invoice_date').value = sale.invoice_date;
        document.getElementById('edit_payment_due_date').value = sale.payment_due_date;
        openModal('editSaleModal');
    }

    function deleteSale(id) {
        if (confirm('Delete this invoice?')) {
            const formData = new FormData();
            formData.append('id', id);
            fetch('api/delete_sale.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(handleApiResponse)
            .catch(err => handleApiResponse({ success: false, message: 'Network error' }));
        }
    }
</script>

<?php require_once "includes/footer.php"; ?>