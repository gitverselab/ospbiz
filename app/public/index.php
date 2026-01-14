<?php
session_start();
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