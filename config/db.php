<?php
class DB {
    private $host = "127.0.0.1";   
    private $db_name = "dbforlab"; 
    private $username = "root";    
    private $password = "";        
    private static $conn = null;

    public static function conn() {
        if (self::$conn === null) {
            $self = new self();
            $dsn = "mysql:host={$self->host};dbname={$self->db_name};charset=utf8mb4";
            try {
                self::$conn = new PDO($dsn, $self->username, $self->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$conn;
    }
}
