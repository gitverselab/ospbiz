<?php
// config/auth_db.php
// Dedicated connection for AUTH database (users table lives here)

define('AUTH_DB_SERVER',   'localhost');
define('AUTH_DB_USERNAME', 'u539825091_ostimebeta');
define('AUTH_DB_PASSWORD', 'Ostimebeta1');
define('AUTH_DB_NAME',     'u539825091_ostimebeta');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $authConn = new mysqli(AUTH_DB_SERVER, AUTH_DB_USERNAME, AUTH_DB_PASSWORD, AUTH_DB_NAME);
    $authConn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Fail closed for security
    http_response_code(500);
    die('Database connection failed (auth).');
}
