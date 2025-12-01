<?php
/**
 * Auto-fix database configuration
 */

$config_file = __DIR__ . '/config/database.php';
$config_content = file_get_contents($config_file);

// Replace the database settings
$config_content = str_replace("private \$host = 'localhost';", "private \$host = 'sql311.infinityfree.com';", $config_content);
$config_content = str_replace("private \$db_name = 'schoollink_africa';", "private \$db_name = 'ecommerce_2025A_splendour_kalu';", $config_content);
$config_content = str_replace("private \$username = 'root';", "private \$username = 'if0_37989095';", $config_content);
$config_content = str_replace("private \$password = '';", "private \$password = 'ecommerce2025';", $config_content);

// Write back to file
if (file_put_contents($config_file, $config_content)) {
    echo "✅ Database configuration updated successfully!<br><br>";
    echo "Now visit: <a href='debug_login.php'>debug_login.php</a> to test the connection.";
} else {
    echo "❌ Failed to update configuration. Check file permissions.";
}
?>
