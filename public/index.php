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

// 5. Router Logic
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Simple Route Map
if ($uri === '/' || $uri === '/index.php' || $uri === '/dashboard') {
    $controller = new JournalController(); // reusing for dashboard for now
    $controller->index();
} 
elseif ($uri === '/journal/create') {
    $controller = new JournalController();
    $controller->create();
}
elseif ($uri === '/journal/approve') {
    $controller = new JournalController();
    $controller->approve();
}
else {
    header("HTTP/1.0 404 Not Found");
    echo "<h1 style='font-family:sans-serif; text-align:center; margin-top:50px;'>404 Not Found</h1>";
    echo "<p style='text-align:center;'>The page $uri does not exist.</p>";
}