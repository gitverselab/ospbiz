<?php
// reports.php
require_once "includes/header.php";
?>

<div class="flex justify-between items-center mb-6">
    <h2 class="text-3xl font-bold text-gray-800">Financial Reports</h2>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <a href="report_profit_loss.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-blue-600">Profit & Loss Statement</h3>
        <p class="text-gray-600 mt-2">View a summary of your revenues and expenses for a specific period.</p>
    </a>

    <a href="report_balance_sheet.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-green-600">Balance Sheet</h3>
        <p class="text-gray-600 mt-2">A snapshot of your company's assets, liabilities, and equity.</p>
    </a>
    
    <a href="report_ap_aging.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-red-600">A/P Aging</h3>
        <p class="text-gray-600 mt-2">A summary of money you owe, grouped by how long it has been outstanding.</p>
    </a>    
    
    <a href="report_payables_schedule.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-purple-600">A/P Schedule</h3>
        <p class="text-gray-600 mt-2">See upcoming payables and any checks that have been issued for them.</p>
    </a>

    </a>
    <a href="report_receivables_schedule.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg">
        <h3 class="text-xl font-bold text-teal-600">A/R Schedule</h3>
        <p class="text-gray-600 mt-2">See upcoming receivables and any checks that have been issued from clients.</p>        
    </a>    
    
    <a href="report_vat.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-yellow-600">VAT Report</h3>
        <p class="text-gray-600 mt-2">Calculate your Output and Input VAT for tax filing.</p>
    </a>

    <a class="bg-white p-6 rounded-lg shadow-md opacity-50">
        <h3 class="text-xl font-bold text-amber-600">Report Reconciliation (Coming Soon)</h3>
        <p class="text-gray-600 mt-2">Comming Soon</p>
    </a>
    
    <a href="report_supplier_balances.php" class="bg-white p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
        <h3 class="text-xl font-bold text-lime-600">Supplier Report</h3>
        <p class="text-gray-600 mt-2">A summary of suppliers and bills payables. </p>
    </a>

    <div class="bg-white p-6 rounded-lg shadow-md opacity-50">
        <h3 class="text-xl font-bold text-sky-600">Sales Tax Report (Coming Soon)</h3>
        <p class="text-gray-600 mt-2">A summary of sales and the sales tax collected from customers.</p>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>