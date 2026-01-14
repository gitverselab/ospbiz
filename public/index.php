<?php
// Start Session
session_start();

// Define Root Path
define('ROOT_PATH', dirname(__DIR__));

// Require the Env loader manually
require_once ROOT_PATH . '/app/core/Env.php';

// Load Environment Variables
try {
    Env::load(ROOT_PATH . '/.env');
} catch (Exception $e) {
    die($e->getMessage());
}

// Now you can require other core files or your autoloader
require_once ROOT_PATH . '/app/core/Database.php';

// ... Router and Controller Logic continues below ...
// Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Autoloader (simplified)
spl_autoload_register(function ($class) {
    // Logic to load class files from /app/core, /app/controllers, /app/models
});

// Route dispatching
if ($uri === '/journal/create') {
    require '../app/controllers/JournalController.php';
    (new JournalController())->create();
} elseif ($uri === '/login') {
    // ...
} else {
    echo "404 Not Found";
}