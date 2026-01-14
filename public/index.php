<?php
// 1. Enable Error Reporting (Turn off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. Define Root Path (One level up from this file)
define('ROOT_PATH', dirname(__DIR__));

// 3. Autoloader (Magically loads classes when needed)
spl_autoload_register(function ($class) {
    // Convert class name to file path
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

// 4. Load Environment Variables
try {
    if (class_exists('Env')) {
        Env::load(ROOT_PATH . '/.env');
    } else {
        die("Core Env class not found. Check /app/core/Env.php");
    }
} catch (Exception $e) {
    die("Env Error: " . $e->getMessage());
}

// 5. Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If app is in a subfolder, remove it from URI (Optional logic, but good for safety)
// For now, we assume domain points to /public so URI is just / or /journal/create

// ROUTING LOGIC
if ($uri === '/' || $uri === '/index.php') {
    echo "<h1>It Works!</h1><a href='/journal/create'>Create Journal</a>";
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
    echo "404 Not Found - URI: " . htmlspecialchars($uri);
}