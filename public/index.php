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
} catch (Exception $e) { /* Ignore */ }

// 5. Router Setup
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ==========================================
// 6. ROUTING LOGIC
// ==========================================

// --- DASHBOARD ---
if ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') {
    $c = new JournalController(); $c->index();
} 
// --- CHECKS (Bank) ---
elseif ($uri === '/bank/checks') {
    $c = new CheckController(); $c->index();
}
elseif ($uri === '/bank/checks/create') {
    $c = new CheckController(); $c->create();
}
elseif ($uri === '/bank/checks/store') {
    $c = new CheckController(); $c->store();
}
elseif ($uri === '/bank/checks/status') {
    $c = new CheckController(); $c->updateStatus();
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
// --- BANK: FUND TRANSFERS ---
elseif ($uri === '/bank/transfers') {
    $c = new FundTransferController(); $c->index();
}
elseif ($uri === '/bank/transfers/create') {
    $c = new FundTransferController(); $c->create();
}
elseif ($uri === '/bank/transfers/store') {
    $c = new FundTransferController(); $c->store();
}
// --- EXPENSES: PURCHASES ---
elseif ($uri === '/expenses/purchases') {
    $c = new PurchaseController(); $c->index();
}
elseif ($uri === '/expenses/purchases/create') {
    $c = new PurchaseController(); $c->create();
}
// NEW ROUTE FOR SAVING:
elseif ($uri === '/expenses/purchases/store') {
    $c = new PurchaseController(); $c->store();
}

// --- EXPENSES: PAYMENTS ---
elseif ($uri === '/expenses/payments') {
    $c = new PurchasePaymentController(); $c->index();
}
elseif ($uri === '/expenses/payments/create') {
    $c = new PurchasePaymentController(); $c->create();
}
// NEW ROUTE:
elseif ($uri === '/expenses/payments/store') {
    $c = new PurchasePaymentController(); $c->store();
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

// --- BILLS & RECURRING ---
elseif ($uri === '/expenses/bills') {
    $c = new BillController(); $c->index();
}
elseif ($uri === '/expenses/bills/create') {
    $c = new BillController(); $c->create();
}
elseif ($uri === '/expenses/bills/store') {
    $c = new BillController(); $c->store();
}
// Recurring
elseif ($uri === '/expenses/recurring') {
    $c = new BillController(); $c->recurringIndex();
}
elseif ($uri === '/expenses/recurring/create') {
    $c = new BillController(); $c->storeRecurring();
}
elseif ($uri === '/expenses/recurring/generate') {
    $c = new BillController(); $c->generate();
}
// --- BILL PAYMENTS ---
elseif ($uri === '/expenses/bill-payments') {
    $c = new BillPaymentController(); $c->index();
}
elseif ($uri === '/expenses/bill-payments/create') {
    $c = new BillPaymentController(); $c->create();
}
elseif ($uri === '/expenses/bill-payments/store') {
    $c = new BillPaymentController(); $c->store();
}
// --- EXPENSES: LOANS ---
elseif ($uri === '/expenses/loans') {
    $c = new LoanController(); $c->index();
}
elseif ($uri === '/expenses/loans/create') {
    $c = new LoanController(); $c->create();
}
elseif ($uri === '/expenses/loans/store') {
    $c = new LoanController(); $c->store();
}
// --- EXPENSES: LOAN PAYMENTS ---
elseif ($uri === '/expenses/loan-payments') {
    $c = new LoanPaymentController(); $c->index();
}
elseif ($uri === '/expenses/loan-payments/create') {
    $c = new LoanPaymentController(); $c->create();
}
elseif ($uri === '/expenses/loan-payments/store') {
    $c = new LoanPaymentController(); $c->store();
}
// --- REVENUE: DR ---
elseif ($uri === '/revenue/dr') {
    $c = new DrController(); $c->index();
}
elseif ($uri === '/revenue/dr/create') {
    $c = new DrController(); $c->create();
}
elseif ($uri === '/revenue/dr/import') {
    $c = new DrController(); $c->import();
}
elseif ($uri === '/revenue/dr/export') {
    $c = new DrController(); $c->export();
}
elseif ($uri === '/revenue/dr/template') {
    $c = new DrController(); $c->template();
}

// --- REVENUE: RTS ---
elseif ($uri === '/revenue/rts') {
    $c = new RtsController(); $c->index();
}
elseif ($uri === '/revenue/rts/create') {
    $c = new RtsController(); $c->create();
}
elseif ($uri === '/revenue/rts/import') {
    $c = new RtsController(); $c->import();
}
elseif ($uri === '/revenue/rts/template') {
    $c = new RtsController(); $c->template();
}
// --- SETTINGS ---
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

// --- CATCH ALL (Under Construction) ---
// THIS MUST BE LAST
elseif (preg_match('#^/(bank|expenses|revenue|settings|admin)#', $uri)) {
    $pageTitle = "Work In Progress";
    require_once ROOT_PATH . '/app/views/layouts/main.php';
}

// --- 404 ---
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
}