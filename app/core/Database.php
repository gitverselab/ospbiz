<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Updated with your specific credentials
        $host = 'localhost';
        $db   = 'u539825091_ospbiz';
        $user = 'u539825091_ospbiz';
        $pass = 'B@dw0lfz'; // ⚠️ Security Warning: See note below

        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log error to file instead of showing to user for security
            error_log("DB Connection Failed: " . $e->getMessage());
            die("Database connection failed. Please check logs.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}