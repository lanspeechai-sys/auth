<?php
// classes/customer_class.php
require_once __DIR__ . '/../config/db.php';

class Customer {
    private $conn;
    public function __construct() {
        $this->conn = DB::conn();
    }

    /**
     * Create customers table if it does not exist.
     * (Helpful for quick lab setup; in production use migrations.)
     */
    public function ensureTable() {
        $sql = "CREATE TABLE IF NOT EXISTS customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            country VARCHAR(60) NOT NULL,
            city VARCHAR(60) NOT NULL,
            contact_number VARCHAR(30) NOT NULL,
            image VARCHAR(255) DEFAULT NULL,
            user_role TINYINT NOT NULL DEFAULT 2, -- 1=admin, 2=customer
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->conn->exec($sql);
    }

    public function emailExists($email) {
        $stmt = $this->conn->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    }

    public function add($args) {
        // args: full_name, email, password_hash, country, city, contact_number
        // image and user_role are ignored at signup (image nullable; user_role default at SQL level)
        if ($this->emailExists($args['email'])) {
            throw new Exception("Email is already registered.");
        }
        $sql = "INSERT INTO customers (full_name, email, password_hash, country, city, contact_number)
                VALUES (:full_name, :email, :password_hash, :country, :city, :contact_number)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':full_name' => $args['full_name'],
            ':email' => $args['email'],
            ':password_hash' => $args['password_hash'],
            ':country' => $args['country'],
            ':city' => $args['city'],
            ':contact_number' => $args['contact_number'],
        ]);
        return $this->conn->lastInsertId();
    }
}
