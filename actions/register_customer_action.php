<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../controllers/customer_controller.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $controller = new CustomerController();
    $id = $controller->register_customer_ctr($data);
    echo json_encode(['ok' => true, 'message' => 'Registration successful', 'id' => $id]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
