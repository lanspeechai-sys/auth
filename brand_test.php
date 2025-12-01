<?php
session_start();
require_once 'includes/auth.php';

// Check authentication
if (!isLoggedIn() || !isAdmin()) {
    echo "Authentication required";
    exit;
}

echo "<h2>Testing Brand Controller</h2>";

// Test direct fetch
echo "<h3>Direct API Test:</h3>";
$url = 'http://localhost/schoollink-africa/brand_controller.php?action=fetch_brands';
echo "<p>Testing URL: <a href='$url' target='_blank'>$url</a></p>";

// Test with curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/schoollink-africa/brand_controller.php?action=fetch_brands');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Copy session cookie
$cookies = [];
foreach ($_COOKIE as $name => $value) {
    $cookies[] = $name . '=' . $value;
}
if (!empty($cookies)) {
    curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookies));
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: $httpCode</p>";
echo "<p>Response:</p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Test database connection in Brand class
echo "<h3>Brand Class Test:</h3>";
try {
    require_once 'brand_class.php';
    $brand = new Brand();
    $categories = $brand->getAllCategories();
    echo "<p>Categories found: " . count($categories) . "</p>";
    echo "<pre>" . print_r($categories, true) . "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>