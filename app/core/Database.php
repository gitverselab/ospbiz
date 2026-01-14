<?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Load credentials from environment variables
        $host = getenv('DB_HOST') ?: 'localhost';
        $db   = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        if (!$db || !$user) {
            die("Database configuration is missing. Check your .env file.");
        }

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Log error privately
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