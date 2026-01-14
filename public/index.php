<?php
// 1. Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. Define Root Path
define('ROOT_PATH', dirname(__DIR__));

// 3. Autoloader
spl_autoload_register(function ($class) {
    $paths = [
        ROOT_PATH . '/app/core/' . $class . '.php',
        ROOT_PATH . '/app/controllers/' . $class . '.php',
        ROOT_PATH . '/app/models/' . $class . '.php'
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 4. Load Env
require_once ROOT_PATH . '/app/core/Env.php';
try {
    if (file_exists(ROOT_PATH . '/.env')) {
        Env::load(ROOT_PATH . '/.env');
    }
} catch (Exception $e) { /* Ignore if missing */ }

// ==========================================
// 5. ROUTER SETUP (THIS WAS MISSING)
// ==========================================

// Get the URL path (e.g., "/journal/create")
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Optional: Handle sub-folder installation if not on root domain
// $uri = str_replace('/ospbiz/public', '', $uri); 

// ==========================================
// 6. ROUTING LOGIC
// ==========================================

// --- DASHBOARD ---
if ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') {
    $c = new JournalController(); $c->index();
} 

// --- EXPENSES: PURCHASES (Fixing the Under Construction Issue) ---
// These MUST be placed above the catch-all
elseif ($uri === '/expenses/purchases') {
    $c = new PurchaseController(); $c->index();
}
elseif ($uri === '/expenses/purchases/create') {
    $c = new PurchaseController(); $c->create();
}
elseif ($uri === '/expenses/payments') {
    $c = new PurchasePaymentController(); $c->index();
}
elseif ($uri === '/expenses/payments/create') {
    $c = new PurchasePaymentController(); $c->create();
}

// --- EXPENSES: DAILY EXPENSES ---
elseif ($uri === '/expenses/daily') {
    $c = new DailyExpenseController(); $c->index();
}
elseif ($uri === '/expenses/daily/create') {
    $c = new DailyExpenseController(); $c->store();
}
elseif ($uri === '/expenses/daily/settle') {
    $c = new DailyExpenseController(); $c->settle();
}

// --- EXPENSES: BILLS ---
elseif ($uri === '/expenses/bills') {
    $c = new BillController(); $c->index();
}
elseif ($uri === '/expenses/bills/create') {
    $c = new BillController(); $c->create();
}

// --- JOURNAL ENTRIES ---
elseif ($uri === '/journal/create') {
    $c = new JournalController(); $c->create();
}
elseif ($uri === '/journal/list') {
    $c = new JournalController(); $c->list();
}
elseif ($uri === '/journal/approve') {
    $c = new JournalController(); $c->approve();
}

// --- AUDIT TRAIL ---
elseif ($uri === '/audit/logs') {
    $c = new AuditController(); $c->index();
}

// --- SETTINGS & MASTER DATA ---
elseif ($uri === '/settings/coa') {
    $c = new COAController(); $c->index();
}
elseif ($uri === '/settings/coa/create') {
    $c = new COAController(); $c->create();
}
elseif ($uri === '/settings/suppliers') {
    $c = new ContactController(); $c->suppliers();
}
elseif ($uri === '/settings/suppliers/create') {
    $c = new ContactController(); $c->createSupplier();
}
elseif ($uri === '/settings/customers') {
    $c = new ContactController(); $c->customers();
}
elseif ($uri === '/settings/customers/create') {
    $c = new ContactController(); $c->createCustomer();
}

// --- BANK & CASH ---
elseif (strpos($uri, '/bank/passbooks') === 0) {
    $c = new BankController();
    if ($uri === '/bank/passbooks') $c->index();
    elseif ($uri === '/bank/passbooks/create') $c->store();
    elseif ($uri === '/bank/passbooks/view') $c->show();
    elseif ($uri === '/bank/passbooks/transaction') $c->storeTransaction();
}
elseif (strpos($uri, '/bank/cash-on-hand') === 0) {
    $c = new CashController();
    if ($uri === '/bank/cash-on-hand') $c->index();
    elseif ($uri === '/bank/cash-on-hand/create') $c->store();
    elseif ($uri === '/bank/cash-on-hand/view') $c->show();
    elseif ($uri === '/bank/cash-on-hand/transaction') $c->storeTransaction();
    elseif ($uri === '/bank/cash-on-hand/transaction/update') $c->updateTransaction();
    elseif ($uri === '/bank/cash-on-hand/transaction/delete') $c->deleteTransaction();
}

// --- CATCH ALL (Under Construction) ---
// This handles any other /expenses/ or /bank/ url NOT defined above
elseif (preg_match('#^/(bank|expenses|revenue|settings|admin)#', $uri)) {
    $pageTitle = "Work In Progress";
    require_once ROOT_PATH . '/app/views/layouts/main.php';
}
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
}