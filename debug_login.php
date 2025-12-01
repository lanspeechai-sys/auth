<?php
/**
 * Debug Login Script
 * This script helps diagnose authentication issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Login Information</h2>";

// Test 1: Database Connection
echo "<h3>1. Testing Database Connection</h3>";
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($db) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed<br>";
    die("Cannot proceed without database connection");
}

// Test 2: Check if user exists
echo "<h3>2. Checking User in Database</h3>";
$email = 'skalu@gmail.com';
$stmt = $db->prepare("SELECT id, email, role, password, LENGTH(password) as pwd_len FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "✅ User found in database<br>";
    echo "- ID: " . $user['id'] . "<br>";
    echo "- Email: " . $user['email'] . "<br>";
    echo "- Role: " . $user['role'] . "<br>";
    echo "- Password Length: " . $user['pwd_len'] . " characters<br>";
    echo "- Password Hash: " . substr($user['password'], 0, 30) . "...<br>";
} else {
    echo "❌ User NOT found in database<br>";
    die("User does not exist");
}

// Test 3: Test Password Verification
echo "<h3>3. Testing Password Verification</h3>";
$test_password = 'admin234';
$stored_hash = $user['password'];

echo "Testing password: <strong>admin234</strong><br>";
echo "Stored hash: " . $stored_hash . "<br>";

$verify_result = password_verify($test_password, $stored_hash);

if ($verify_result) {
    echo "✅ Password verification SUCCESSFUL<br>";
} else {
    echo "❌ Password verification FAILED<br>";
    
    // Try generating a new hash and test it
    echo "<br><strong>Testing fresh hash generation:</strong><br>";
    $fresh_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "Fresh hash: " . $fresh_hash . "<br>";
    $fresh_verify = password_verify($test_password, $fresh_hash);
    echo "Fresh hash verify: " . ($fresh_verify ? "✅ SUCCESS" : "❌ FAILED") . "<br>";
}

// Test 4: Test authenticateUser function
echo "<h3>4. Testing authenticateUser() Function</h3>";
require_once 'includes/auth.php';

$auth_result = authenticateUser($email, $test_password);

if ($auth_result) {
    echo "✅ authenticateUser() returned user<br>";
    echo "- User ID: " . $auth_result['id'] . "<br>";
    echo "- User Email: " . $auth_result['email'] . "<br>";
    echo "- User Role: " . $auth_result['role'] . "<br>";
} else {
    echo "❌ authenticateUser() returned FALSE<br>";
}

// Test 5: Check all super admins
echo "<h3>5. All Super Admin Users</h3>";
$stmt = $db->prepare("SELECT id, email, role, LENGTH(password) as pwd_len FROM users WHERE role = 'super_admin'");
$stmt->execute();
$admins = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Email</th><th>Role</th><th>Password Length</th></tr>";
foreach ($admins as $admin) {
    echo "<tr>";
    echo "<td>" . $admin['id'] . "</td>";
    echo "<td>" . $admin['email'] . "</td>";
    echo "<td>" . $admin['role'] . "</td>";
    echo "<td>" . $admin['pwd_len'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>Conclusion</h3>";
echo "Review the results above to identify the issue.";
?>
