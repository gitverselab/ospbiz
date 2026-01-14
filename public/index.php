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
    $controller = new JournalController();
    $controller->index();
} 

// --- JOURNAL ENTRIES ---
elseif ($uri === '/journal/create') {
    $controller = new JournalController();
    $controller->create();
}
elseif ($uri === '/journal/list') {
    $controller = new JournalController();
    $controller->list();
}
elseif ($uri === '/journal/approve') {
    $controller = new JournalController();
    $controller->approve();
}

// --- AUDIT TRAIL ---
elseif ($uri === '/audit/logs') {
    $controller = new AuditController();
    $controller->index();
}

// --- SETTINGS: COA ---
elseif ($uri === '/settings/coa') {
    $controller = new COAController();
    $controller->index();
}
elseif ($uri === '/settings/coa/create') {
    $controller = new COAController();
    $controller->create();
}

// --- SETTINGS: MASTER DATA (Suppliers/Customers) ---
elseif ($uri === '/settings/suppliers') {
    $controller = new ContactController(); $controller->suppliers();
}
elseif ($uri === '/settings/suppliers/create') {
    $controller = new ContactController(); $controller->createSupplier();
}
elseif ($uri === '/settings/customers') {
    $controller = new ContactController(); $controller->customers();
}
elseif ($uri === '/settings/customers/create') {
    $controller = new ContactController(); $controller->createCustomer();
}

// --- BANK: PASSBOOKS ---
elseif ($uri === '/bank/passbooks') {
    $c = new BankController(); $c->index();
}
elseif ($uri === '/bank/passbooks/create') {
    $c = new BankController(); $c->store();
}
elseif ($uri === '/bank/passbooks/view') {
    $c = new BankController(); $c->show();
}
elseif ($uri === '/bank/passbooks/transaction') {
    $c = new BankController(); $c->storeTransaction();
}

// --- BANK: CASH ON HAND ---
elseif ($uri === '/bank/cash-on-hand') {
    $c = new CashController(); $c->index();
}
elseif ($uri === '/bank/cash-on-hand/create') {
    $c = new CashController(); $c->store();
}
elseif ($uri === '/bank/cash-on-hand/view') {
    $c = new CashController(); $c->show();
}
elseif ($uri === '/bank/cash-on-hand/transaction') {
    $c = new CashController(); $c->storeTransaction();
}
elseif ($uri === '/bank/cash-on-hand/transaction/update') {
    $c = new CashController(); $c->updateTransaction();
}
elseif ($uri === '/bank/cash-on-hand/transaction/delete') {
    $c = new CashController(); $c->deleteTransaction();
}

// --- EXPENSES: DAILY EXPENSES (New Module) ---
elseif ($uri === '/expenses/daily') {
    $c = new DailyExpenseController(); $c->index();
}
elseif ($uri === '/expenses/daily/create') {
    $c = new DailyExpenseController(); $c->store();
}
elseif ($uri === '/expenses/daily/settle') {
    $c = new DailyExpenseController(); $c->settle();
}

// --- EXPENSES: PURCHASE ORDERS ---
elseif ($uri === '/expenses/purchases') {
    $c = new PurchaseController(); $c->index();
}
elseif ($uri === '/expenses/purchases/create') {
    $c = new PurchaseController(); $c->create();
}

// --- EXPENSES: PURCHASE PAYMENTS ---
elseif ($uri === '/expenses/payments') {
    $c = new PurchasePaymentController(); $c->index();
}
elseif ($uri === '/expenses/payments/create') {
    $c = new PurchasePaymentController(); $c->create();
}

// --- EXPENSES: BILLS ---
elseif ($uri === '/expenses/bills') {
    $c = new BillController(); $c->index();
}
elseif ($uri === '/expenses/bills/create') {
    $c = new BillController(); $c->create();
}

// --- CATCH ALL (Under Construction) ---
// This MUST come LAST. It catches anything that wasn't defined above.
elseif (preg_match('#^/(bank|expenses|revenue|settings|admin)#', $uri)) {
    $pageTitle = "Work In Progress";
    require_once ROOT_PATH . '/app/views/layouts/main.php';
}

// --- 404 NOT FOUND ---
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1><p>The page $uri could not be found.</p>";
}