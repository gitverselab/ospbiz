<?php
// 1. Get the current URL so we can highlight the correct menu item
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'OSP Accounting'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --sidebar-hover: #334155;
            --active-link: #3b82f6;
        }
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        
        /* Custom Scrollbar for the Navigation Area only */
        .nav-scroll::-webkit-scrollbar { width: 5px; }
        .nav-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 5px; }
        
        .nav-header { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-top: 1.5rem; margin-bottom: 0.5rem; padding-left: 1.5rem; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: #cbd5e1; transition: all 0.2s; font-size: 0.9rem; }
        .nav-link:hover { background-color: var(--sidebar-hover); color: white; }
        
        /* Active Link Styling */
        .nav-link.active { background-color: #0f172a; border-right: 4px solid var(--active-link); color: white; }
        
        .nav-link i { width: 20px; text-align: center; margin-right: 10px; }
    </style>
</head>
<body class="h-screen flex overflow-hidden bg-gray-50">

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden transition-opacity opacity-0"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-slate-900 text-white flex flex-col shadow-2xl h-full transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0">
        
        <div class="h-16 flex items-center justify-between px-6 bg-slate-950 border-b border-slate-800 shrink-0">
            <div class="flex items-center">
                <i class="fa-solid fa-cube text-blue-500 text-xl mr-3"></i>
                <span class="font-bold text-lg tracking-wide">OSP Accounting</span>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto nav-scroll py-4">
            <div class="nav-header">Management</div>
            <a href="/" class="nav-link <?php echo ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge-high"></i> Dashboard
            </a>
            
            <div class="nav-header">Bank & Cash</div>
            <a href="/bank/checks" class="nav-link <?php echo (strpos($uri, '/bank/checks') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-money-check"></i> Checks
            </a>
            <a href="/bank/passbooks" class="nav-link <?php echo (strpos($uri, '/bank/passbooks') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-book"></i> Passbooks
            </a>
            <a href="/bank/cash-on-hand" class="nav-link <?php echo (strpos($uri, '/bank/cash-on-hand') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-wallet"></i> Petty Cash
            </a>
            <a href="/bank/transfers" class="nav-link <?php echo (strpos($uri, '/bank/transfers') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-arrow-right-arrow-left"></i> Fund Transfers
            </a>
            <a href="/bank/calendar" class="nav-link <?php echo (strpos($uri, '/bank/calendar') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-calendar-days"></i> Calendar
            </a>

            <div class="nav-header">Revenue</div>
            <a href="/revenue/dr" class="nav-link <?php echo (strpos($uri, '/revenue/dr') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-truck"></i> DR Management
            </a>
            <a href="/revenue/rts" class="nav-link <?php echo (strpos($uri, '/revenue/rts') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-rotate-left"></i> RTS Management
            </a>
            <a href="/revenue/imports" class="nav-link <?php echo (strpos($uri, '/revenue/imports') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-ship"></i> Import Receipts
            </a>
            <a href="/revenue/sales" class="nav-link <?php echo (strpos($uri, '/revenue/sales') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-cash-register"></i> Sales Invoices
            </a>
            <a href="/revenue/remittance" class="nav-link <?php echo (strpos($uri, '/revenue/remittance') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-hand-holding-dollar"></i> Remittance
            </a>

            <div class="nav-header">Expenses</div>
            
            <a href="/expenses/daily" class="nav-link <?php echo (strpos($uri, '/expenses/daily') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-wallet"></i> Daily Expenses
            </a>

            <a href="/expenses/purchases" class="nav-link <?php echo (strpos($uri, '/expenses/purchases') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-cart-shopping"></i> Purchase Orders
            </a>

            <a href="/expenses/payments" class="nav-link <?php echo (strpos($uri, '/expenses/payments') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-money-bill-transfer"></i> Purchase Payments
            </a>

            <a href="/expenses/bills" class="nav-link <?php echo ($uri === '/expenses/bills' || $uri === '/expenses/bills/create') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice"></i> Bills
            </a>

            <a href="/expenses/recurring" class="nav-link <?php echo (strpos($uri, '/expenses/recurring') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-arrows-rotate"></i> Recurring Bills
            </a>

            <a href="/expenses/bill-payments" class="nav-link <?php echo (strpos($uri, '/expenses/bill-payments') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-money-check-dollar"></i> Bill Payments
            </a>

            <a href="/expenses/loans" class="nav-link <?php echo (strpos($uri, '/expenses/loans') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-building-columns"></i> Credits/Loans
            </a>
            <a href="/expenses/loan-payments" class="nav-link <?php echo (strpos($uri, '/expenses/loan-payments') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-money-bill-wave"></i> Loan Payments
            </a>

            <div class="nav-header">Accounting</div>
            <a href="/journal/create" class="nav-link <?php echo (strpos($uri, '/journal/create') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-pen-fancy"></i> Journal Entry
            </a>
            <a href="/journal/list" class="nav-link <?php echo (strpos($uri, '/journal/list') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i> View Journals
            </a>
            <a href="/reports" class="nav-link <?php echo (strpos($uri, '/reports') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i> Reports
            </a>

            <div class="nav-header">Settings</div>
            <a href="/settings/coa" class="nav-link <?php echo (strpos($uri, '/settings/coa') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-sitemap"></i> Chart of Accounts
            </a>
            <a href="/settings/suppliers" class="nav-link <?php echo (strpos($uri, '/settings/suppliers') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> Suppliers
            </a>
            <a href="/settings/customers" class="nav-link <?php echo (strpos($uri, '/settings/customers') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-tag"></i> Customers
            </a>
            <a href="/settings/items" class="nav-link <?php echo (strpos($uri, '/settings/items') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-box"></i> Items
            </a>
            <a href="/settings/receipt" class="nav-link"<?php echo (strpos($uri, '/settings/receipt') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-scroll"></i> Receipt Settings
            </a>
            <a href="/settings/categories" class="nav-link <?php echo (strpos($uri, '/settings/categories') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-tags"></i> Expense Categories
            </a>

            <div class="nav-header">App System</div>
            <a href="/admin/users" class="nav-link <?php echo (strpos($uri, '/admin/users') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-gear"></i> Manage Users
            </a>
            <a href="/audit/logs" class="nav-link <?php echo (strpos($uri, '/audit/logs') === 0) ? 'active' : ''; ?>">
                <i class="fa-solid fa-shield-halved"></i> Audit Trail
            </a>
        </nav>

        <div class="p-4 bg-slate-950 border-t border-slate-800 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-xs font-bold">AD</div>
                <div class="overflow-hidden">
                    <div class="text-sm font-medium truncate">Admin User</div>
                    <div class="text-xs text-slate-400">Headquarters</div>
                </div>
                <a href="/logout" class="ml-auto text-slate-400 hover:text-white"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-gray-50">
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 md:px-8 shadow-sm shrink-0 z-0">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>

                <h2 class="text-lg md:text-xl font-bold text-gray-800 truncate">
                    <?php echo isset($pageTitle) ? $pageTitle : 'Financial Dashboard'; ?>
                </h2>
            </div>

            <div class="flex gap-4">
                <div class="text-right hidden md:block">
                    <div class="text-xs text-gray-500">Current Date</div>
                    <div class="text-sm font-bold text-gray-800"><?php echo date('F j, Y'); ?></div>
                </div>
                <div class="h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 border border-gray-200">
                    <i class="fa-regular fa-bell"></i>
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-6">
            <?php 
            if (isset($childView) && file_exists($childView)) {
                include $childView;
            } else {
                echo "<div class='flex flex-col items-center justify-center h-full text-gray-400'>
                        <i class='fa-solid fa-hammer text-6xl mb-4'></i>
                        <h2 class='text-2xl font-bold'>Under Construction</h2>
                        <p>This module is not yet implemented.</p>
                      </div>";
            }
            ?>
        </div>
    </main>

    <script>
        // 1. Toggle Sidebar Logic
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
                setTimeout(() => overlay.classList.remove('opacity-0'), 10);
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('opacity-0');
                setTimeout(() => overlay.classList.add('hidden'), 300);
            }
        }

        // 2. NEW: Auto-Scroll to Active Link
        document.addEventListener("DOMContentLoaded", function() {
            // Find the link with the 'active' class
            const activeLink = document.querySelector('.nav-link.active');
            
            if (activeLink) {
                // Scroll the link into view (centers it in the menu)
                activeLink.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>