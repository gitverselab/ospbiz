<?php
// includes/header.php
require_once __DIR__ . "/../config/access_control.php";

// Protect all pages. All logged-in roles can VIEW pages.
require_role(['Admin', 'Accountant', 'Viewer']);

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/print.css" media="print">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: background-color 0.2s, color 0.2s; }
        .sidebar-link.active { background-color: #4a5568; color: #ffffff; }
        .sidebar-link:hover { background-color: #2d3748; color: #ffffff; }
        .no-print { @media print { display: none !important; } }
        
        /* Select2 Tailwind Fixes */
        .select2-container .select2-selection--single {
            height: 42px !important;
            padding: 6px 12px;
            border: 1px solid #d1d5db; 
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
        .select2-container { z-index: 9999 !important; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen overflow-hidden bg-gray-100">
        
        <div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black opacity-50 hidden md:hidden transition-opacity" onclick="toggleSidebar()"></div>

        <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-800 text-white flex flex-col transition-transform duration-300 transform -translate-x-full md:translate-x-0 md:static md:inset-auto flex-shrink-0 no-print h-full">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h1 class="text-2xl font-bold">OSP Accounting</h1>
                <button class="md:hidden text-gray-400 hover:text-white" onclick="toggleSidebar()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <nav id="sidebar-nav" class="flex-1 overflow-y-auto mt-4 p-2 custom-scrollbar">
                <a href="index.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>

                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Management</span>
                <a href="checks.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'checks.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                   Check Status
                </a>
                <a href="passbooks.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['passbooks.php', 'passbook_view.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v11.494m0 0a7.5 7.5 0 007.5-7.5H4.5a7.5 7.5 0 007.5 7.5z"></path></svg>
                   Passbooks
                </a>
                 <a href="cash_on_hand.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['cash_on_hand.php', 'cash_account_view.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                   Cash on Hand
                </a>
                <a href="fund_transfers.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'fund_transfers.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                   Fund Transfers
                </a>
                <a href="calendar.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Calendar
                </a>

                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Projects</span>
                 <a href="projects.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['projects.php', 'view_project.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                   Projects
                </a>

                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Requests</span>
                 <a href="requests.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['requests.php', 'view_request.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                   Requests
                </a>

                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Expenses</span>
                 <a href="expenses.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['expenses.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                   Expenses
                </a>                
                 <a href="purchases.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['purchases.php', 'view_purchase.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                   Purchases
                </a>
                <a href="bills.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'bills.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                   Bills
                </a>
                <a href="recurring_bills.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'recurring_bills.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                   Recurring Bills
                </a>                
                <a href="credits.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'credits.php' ? 'active' : ''; ?>">
                  <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                  Credits
                </a>

                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Revenue</span>
                <a href="delivery_receipts.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'delivery_receipts.php' ? 'active' : ''; ?>">
                     <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8a1 1 0 001-1zM3 11h10"></path></svg>
                     Delivery Receipts
                </a>
                <a href="rts_receipts.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'rts_receipts.php' ? 'active' : ''; ?>">
                     <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l2 2h8a1 1 0 001-1zM3 11h10"></path></svg>
                     RTS Receipts
                </a>
                <a href="import_delivery_receipts.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'import_delivery_receipts.php' ? 'active' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    Import Receipts
                </a>
                <a href="import_rts_receipts.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'import_rts_receipts.php' ? 'active' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    Import RTS Receipts
                </a>
                <a href="sales.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo in_array($current_page, ['sales.php', 'view_sale.php']) ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                   Sales
                </a>
                <a href="sales_remittance.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'sales_remittance.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                   Payment Remittance
                </a>
                <a href="store_remittance.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'store_remittance.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                   Store Remittance
                </a>
                
                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Reports</span>
                <a href="reports.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                   Reports
                </a>               
                
                <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Settings</span>
                <a href="suppliers.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                   Suppliers
                </a>
                <a href="billers.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'billers.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                   Billers
                </a>                
                <a href="items.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'items.php' ? 'active' : ''; ?>">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                   Items
                </a>
                <a href="customers.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>">
                     <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197M15 21a6 6 0 00-9-5.197"></path></svg>
                     Customers
                </a>
                <a href="chart_of_accounts.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'chart_of_accounts.php' ? 'active' : ''; ?>">
                   <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                   Chart of Accounts
                </a>

                <?php if (has_role(['Admin'])): ?>
                    <span class="px-4 mt-4 text-xs text-gray-500 uppercase tracking-wider block">Admin</span>
                    <a href="manage_users.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Manage Users
                    </a>
                    <a href="manage_roles.php" class="flex items-center px-4 py-3 mt-2 text-gray-300 sidebar-link rounded-md <?php echo $current_page == 'manage_roles.php' ? 'active' : ''; ?>">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H5v-2H3v-2H1v-4a6 6 0 017.743-5.743A2 2 0 0115 7z"></path></svg>
                        Manage Roles
                    </a>
                <?php endif; ?>

            </nav>
            
            <div class="p-4 border-t border-gray-700">
                <a href="logout.php" class="flex items-center px-4 py-3 text-gray-300 sidebar-link rounded-md">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Logout
                </a>
            </div>
        </aside>

        <div class="flex-1 flex flex-col overflow-hidden h-full">
            <header class="bg-white shadow-md p-4 no-print flex justify-between items-center shrink-0">
                <button class="md:hidden text-gray-600 hover:text-gray-900 focus:outline-none" onclick="toggleSidebar()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>

                <div class="flex-1 flex justify-end items-center">
                    <span class="text-gray-600">Welcome, <strong><?php echo htmlspecialchars($_SESSION["username"]); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</strong>!</span>
                </div>
            </header>
            
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        // Toggle mobile classes
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('translate-x-0');
        overlay.classList.toggle('hidden');
    }

    // Auto-scroll to active link
    document.addEventListener("DOMContentLoaded", () => {
        const activeLink = document.querySelector('.sidebar-link.active');
        if (activeLink) {
            // Scroll the sidebar navigation container
            activeLink.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
</script>