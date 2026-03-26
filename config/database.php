<?php
// config/database.php

// Database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u539825091_ospbizbeta');
define('DB_PASSWORD', 'Ospbizbeta1');
define('DB_NAME', 'u539825091_ospbizbeta');

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>