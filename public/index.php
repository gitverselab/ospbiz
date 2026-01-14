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
else {
    header("HTTP/1.0 404 Not Found");
    echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
    echo "<h1>404 Not Found</h1>";
    echo "<p>The page <strong>" . htmlspecialchars($uri) . "</strong> does not exist.</p>";
    echo "</div>";
}