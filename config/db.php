<?php
// config/db.php
// Simple PDO connection helper. Update credentials for your environment.
class DB {
    private $host = "localhost";
    private $db_name = "ecomm_lab";
    private $username = "root";
    private $password = "";
    private static $conn = null;

    public static function conn() {
        if (self::$conn === null) {
            $dsn = "mysql:host=" . (new self())->host . ";dbname=" . (new self())->db_name . ";charset=utf8mb4";
            try {
                self::$conn = new PDO($dsn, (new self())->username, (new self())->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$conn;
    }
}
