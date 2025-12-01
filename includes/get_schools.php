<?php
require_once 'functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'schools' => []];

try {
    $db = getDB();
    if ($db) {
        $stmt = $db->prepare("SELECT id, name FROM schools WHERE approved = 1 ORDER BY name ASC");
        $stmt->execute();
        $schools = $stmt->fetchAll();
        
        $response['success'] = true;
        $response['schools'] = $schools;
    } else {
        $response['message'] = 'Database connection failed';
    }
} catch (Exception $e) {
    $response['message'] = 'Error fetching schools';
    error_log("Get schools error: " . $e->getMessage());
}

echo json_encode($response);