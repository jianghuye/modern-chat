<?php
require_once 'config.php';

class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $conn;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->conn = null;
    }
    
    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return null;
        }
    }
    
    public function disconnect() {
        $this->conn = null;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// 创建数据库实例
$db = new Database();
$conn = $db->connect();

if (!$conn) {
    die("Database connection failed.");
}