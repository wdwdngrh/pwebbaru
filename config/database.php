<?php
date_default_timezone_set('Asia/Jakarta');

// Database configuration for PDO
define('DB_HOST', getenv('DB_HOST'));  // Use the environment variable for the database host
define('DB_USER', getenv('DB_USER'));  // Use the environment variable for the database user
define('DB_PASS', getenv('DB_PASS'));  // Use the environment variable for the database password
define('DB_NAME', getenv('DB_NAME'));  // Use the environment variable for the database name

class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            // Use PDO instead of mysqli
            $this->conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function escape($string) {
        return $this->conn->quote($string); // PDO's escape
    }
    
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
