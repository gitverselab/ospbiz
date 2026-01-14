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

if ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') {
    $controller = new JournalController();
    $controller->index();
}
elseif ($uri === '/bank/passbooks') {
    $controller = new BankController();
    $controller->index();
}
elseif ($uri === '/bank/passbooks/create') {
    $controller = new BankController();
    $controller->store();
}
elseif ($uri === '/bank/passbooks/view') {
    $controller = new BankController();
    $controller->show();
}
elseif ($uri === '/bank/passbooks/transaction') {
    $controller = new BankController();
    $controller->storeTransaction();
}

// Reuse similar logic for /bank/cash-on-hand routes mapped to CashController
elseif ($uri === '/bank/cash-on-hand') {
    $controller = new CashController();
    $controller->index();
}
elseif ($uri === '/bank/cash-on-hand/create') {
    $controller = new CashController();
    $controller->store();
}
elseif ($uri === '/bank/cash-on-hand/view') {
    $controller = new CashController();
    $controller->show();
}
elseif ($uri === '/bank/cash-on-hand/transaction') {
    $controller = new CashController();
    $controller->storeTransaction();
}
// PURCHASES
elseif ($uri === '/expenses/purchases') {
    $c = new PurchaseController(); $c->index();
}
elseif ($uri === '/expenses/purchases/create') {
    $c = new PurchaseController(); $c->create();
}
// PURCHASE PAYMENTS
elseif ($uri === '/expenses/payments') {
    $c = new PurchasePaymentController(); $c->index();
}
elseif ($uri === '/expenses/payments/create') {
    $c = new PurchasePaymentController(); $c->create();
} 
elseif ($uri === '/journal/create') {
    $controller = new JournalController();
    $controller->create();
}
elseif ($uri === '/journal/list') {
    $controller = new JournalController();
    $controller->list();
}
elseif ($uri === '/audit/logs') {
    $controller = new AuditController();
    $controller->index();
}
elseif ($uri === '/settings/coa') {
    $controller = new COAController();
    $controller->index();
}
elseif ($uri === '/settings/coa/create') {
    $controller = new COAController();
    $controller->create();
}
// SUPPLIERS
elseif ($uri === '/settings/suppliers') {
    $controller = new ContactController(); // Auto-loads ContactController.php
    $controller->suppliers();
}
elseif ($uri === '/settings/suppliers/create') {
    $controller = new ContactController();
    $controller->createSupplier();
}
// CUSTOMERS
elseif ($uri === '/settings/customers') {
    $controller = new ContactController();
    $controller->customers();
}
elseif ($uri === '/settings/customers/create') {
    $controller = new ContactController();
    $controller->createCustomer();
}
// CATCH ALL: If the route starts with /bank, /expenses, /revenue, etc.
elseif (preg_match('#^/(bank|expenses|revenue|settings|admin)#', $uri)) {
    // Show the "Under Construction" view using the Main Layout
    $pageTitle = "Work In Progress";
    // We don't set $childView, so the main.php fallback will trigger
    require_once ROOT_PATH . '/app/views/layouts/main.php';
}
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
}