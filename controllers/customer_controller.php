<?php
// controllers/customer_controller.php
require_once __DIR__ . '/../classes/customer_class.php';

class CustomerController {
    private $model;
    public function __construct() {
        $this->model = new Customer();
        $this->model->ensureTable();
    }

    public function register_customer_ctr($kwargs) {
        // Basic server-side validation
        $required = ['full_name', 'email', 'password', 'country', 'city', 'contact_number'];
        foreach ($required as $r) {
            if (!isset($kwargs[$r]) || trim($kwargs[$r]) === '') {
                throw new Exception("Missing required field: " . $r);
            }
        }

        if (!filter_var($kwargs['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        // Hash password
        $password_hash = password_hash($kwargs['password'], PASSWORD_BCRYPT);
        if ($password_hash === false) {
            throw new Exception("Password hashing failed.");
        }

        // Persist
        $id = $this->model->add([
            'full_name' => trim($kwargs['full_name']),
            'email' => strtolower(trim($kwargs['email'])),
            'password_hash' => $password_hash,
            'country' => trim($kwargs['country']),
            'city' => trim($kwargs['city']),
            'contact_number' => trim($kwargs['contact_number']),
        ]);
        return $id;
    }
}
