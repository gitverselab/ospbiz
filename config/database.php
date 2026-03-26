<?php
// config/database.php

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u539825091_ospact');
define('DB_PASSWORD', 'B@dw0lfz');
define('DB_NAME', 'u539825091_ospact');

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>