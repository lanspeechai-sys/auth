<?php
session_start();
require_once 'includes/auth.php';

echo "<h2>Authentication Test</h2>";
echo "<p>Session data:</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<p>Is logged in: " . (isLoggedIn() ? 'YES' : 'NO') . "</p>";
echo "<p>Is admin: " . (isAdmin() ? 'YES' : 'NO') . "</p>";

if (function_exists('getCurrentUser')) {
    $user = getCurrentUser();
    echo "<p>Current user:</p>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";
}
?>