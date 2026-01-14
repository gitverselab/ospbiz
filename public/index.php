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
    Env::load(ROOT_PATH . '/.env');
} catch (Exception $e) { /* Ignore if missing for now */ }

// ... (Keep the top part of index.php same as before) ...

// ROUTING LOGIC
if ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') {
    $controller = new JournalController();
    $controller->index();
} 
elseif ($uri === '/journal/create') {
    $controller = new JournalController();
    $controller->create();
}
// NEW: View Journals
elseif ($uri === '/journal/list') {
    $controller = new JournalController();
    $controller->list();
}
// NEW: Audit Trail
elseif ($uri === '/audit/logs') {
    // Autoloader will load AuditController.php automatically
    $controller = new AuditController();
    $controller->index();
}
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1 style='text-align:center;margin-top:50px'>404 Not Found</h1>";
}