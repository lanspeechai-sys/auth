<?php
// Diagnostic page to check what's missing on live server
echo "<h1>SchoolLink Africa - Live Server Diagnostic</h1>";
echo "<hr>";

// Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Required: 7.4 or higher<br>";
echo "<hr>";

// Check if files exist
echo "<h2>2. Required Files Check</h2>";
$requiredFiles = [
    'includes/auth.php',
    'product_class.php',
    'config/database.php',
    'all_products.php',
    'store.php',
    'cart.php',
    'checkout.php'
];

foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    $color = $exists ? 'green' : 'red';
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo "<span style='color:$color'>$status</span> - $file<br>";
}
echo "<hr>";

// Check database connection
echo "<h2>3. Database Connection</h2>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        echo "<span style='color:green'>✓ Database connection successful!</span><br>";
        
        // Check if tables exist
        echo "<h3>Database Tables:</h3>";
        $tables = ['users', 'schools', 'products', 'categories', 'brands', 'orders', 'order_items'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $color = $exists ? 'green' : 'red';
            $status = $exists ? '✓' : '✗';
            echo "<span style='color:$color'>$status $table</span><br>";
        }
    } else {
        echo "<span style='color:red'>✗ config/database.php not found</span><br>";
    }
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Database Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
echo "<hr>";

// Check write permissions
echo "<h2>4. Directory Permissions</h2>";
$directories = ['uploads', 'logs'];
foreach ($directories as $dir) {
    if (file_exists($dir)) {
        $writable = is_writable($dir);
        $color = $writable ? 'green' : 'orange';
        $status = $writable ? '✓ WRITABLE' : '⚠ NOT WRITABLE';
        echo "<span style='color:$color'>$status</span> - $dir/<br>";
    } else {
        echo "<span style='color:red'>✗ MISSING</span> - $dir/<br>";
    }
}
echo "<hr>";

// Check PHP extensions
echo "<h2>5. PHP Extensions</h2>";
$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $color = $loaded ? 'green' : 'red';
    $status = $loaded ? '✓ LOADED' : '✗ NOT LOADED';
    echo "<span style='color:$color'>$status</span> - $ext<br>";
}
echo "<hr>";

// Error reporting
echo "<h2>6. Error Display Settings</h2>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";
echo "<hr>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If files are missing: Upload them via FTP/SFTP</li>";
echo "<li>If database connection fails: Update config/database.php with correct credentials</li>";
echo "<li>If tables are missing: Import database/schoollink_africa.sql</li>";
echo "<li>If permissions are wrong: Set uploads/ to 755 or 777</li>";
echo "</ul>";

echo "<p><a href='index.php'>Go to Home Page</a> | <a href='all_products.php'>Try All Products</a></p>";
?>
