<?php

require_once __DIR__ . '/../config/db.php';

class Customer {
    private $conn;
    public function __construct() {
        $this->conn = DB::conn();
    }

    public function emailExists($email) {
        $stmt = $this->conn->prepare("SELECT customer_id FROM customer WHERE customer_email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetchColumn();
    }

    public function add($args) {
        if ($this->emailExists($args['email'])) {
            throw new Exception("Email is already registered.");
        }
        $sql = "INSERT INTO customer (
                    customer_name, customer_email, customer_pass,
                    customer_country, customer_city, customer_contact,
                    customer_image, user_role
                ) VALUES (
                    :full_name, :email, :password_hash,
                    :country, :city, :contact_number,
                    NULL, 2
                )";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':full_name'       => $args['full_name'],
                ':email'           => $args['email'],
                ':password_hash'   => $args['password_hash'],
                ':country'         => $args['country'],
                ':city'            => $args['city'],
                ':contact_number'  => $args['contact_number'],
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                throw new Exception("That email is already taken.");
            }
            throw $e;
        }
        return $this->conn->lastInsertId();
    }

    public function getByEmail($email) {
        $stmt = $this->conn->prepare("SELECT customer_id, customer_name, customer_email, customer_pass, user_role FROM customer WHERE customer_email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Authenticate and return safe user array without password on success.
     * @return array|false
     */
    public function authenticate($email, $password) {
        $row = $this->getByEmail($email);
        if (!$row) return false;
        if (!password_verify($password, $row['customer_pass'])) return false;
        return [
            'id'    => (int)$row['customer_id'],
            'name'  => $row['customer_name'],
            'email' => $row['customer_email'],
            'role'  => (int)$row['user_role'],
        ];
    }
}
