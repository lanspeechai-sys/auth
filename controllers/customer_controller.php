<?php
require_once __DIR__ . '/../classes/customer_class.php';

class CustomerController {
    private $model;
    public function __construct() {
        $this->model = new Customer();
    }

    public function register_customer_ctr($kwargs) {
        $required = ['full_name', 'email', 'password', 'country', 'city', 'contact_number'];
        foreach ($required as $r) {
            if (!isset($kwargs[$r]) || trim($kwargs[$r]) === '') {
                throw new Exception("Missing required field: " . $r);
            }
        }

        $full_name = trim($kwargs['full_name']);
        $email     = strtolower(trim($kwargs['email']));
        $password  = $kwargs['password'];
        $country   = trim($kwargs['country']);
        $city      = trim($kwargs['city']);
        $contact   = trim($kwargs['contact_number']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        if (strlen($full_name) > 100) throw new Exception("Full name is too long (max 100).");
        if (strlen($email) > 50)      throw new Exception("Email is too long (max 50).");
        if (strlen($country) > 30)    throw new Exception("Country is too long (max 30).");
        if (strlen($city) > 30)       throw new Exception("City is too long (max 30).");
        if (strlen($contact) > 15)    throw new Exception("Contact number is too long (max 15).");

        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters.");
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        if ($password_hash === false) {
            throw new Exception("Password hashing failed.");
        }

        $id = $this->model->add([
            'full_name'      => $full_name,
            'email'          => $email,
            'password_hash'  => $password_hash,
            'country'        => $country,
            'city'           => $city,
            'contact_number' => $contact,
        ]);
        return $id;
    }
}
