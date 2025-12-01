<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

echo "<h1>E-commerce System Test</h1>";

// Check authentication
echo "<h2>Authentication Status:</h2>";
echo "<p>Is logged in: " . (isLoggedIn() ? 'YES' : 'NO') . "</p>";

if (isLoggedIn()) {
    $user = getCurrentUser();
    echo "<p>User role: " . $user['role'] . "</p>";
    echo "<p>Is admin: " . (isAdmin() ? 'YES' : 'NO') . "</p>";
} else {
    echo "<p><strong>Please login as a school_admin to test e-commerce features.</strong></p>";
    echo "<p><a href='login.php'>Login here</a></p>";
    exit;
}

// Check database connection
echo "<h2>Database Connection:</h2>";
try {
    $db = getDB();
    if ($db) {
        echo "<p>Database connection: ✓ SUCCESS</p>";
        
        // Check tables
        echo "<h3>Database Tables:</h3>";
        // Get all tables first
        $stmt = $db->query("SHOW TABLES");
        $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>All tables: " . implode(', ', $all_tables) . "</p>";
        
        // Filter for e-commerce tables
        $ecommerce_tables = array_filter($all_tables, function($table) {
            return strpos($table, 'brand') !== false || 
                   strpos($table, 'product') !== false || 
                   strpos($table, 'categor') !== false;
        });
        
        if (!empty($ecommerce_tables)) {
            echo "<p>✓ E-commerce tables found: " . implode(', ', $ecommerce_tables) . "</p>";
        } else {
            echo "<p>❌ E-commerce tables not found!</p>";
        }
        
        // Test categories
        $stmt = $db->query("SELECT COUNT(*) FROM categories");
        $categoryCount = $stmt->fetchColumn();
        echo "<p>Categories in database: " . $categoryCount . "</p>";
        
        if ($categoryCount == 0) {
            echo "<p><strong>No categories found! Let's add some default categories:</strong></p>";
            
            $defaultCategories = [
                'Academic Courses',
                'Vocational Training', 
                'Professional Development',
                'Language Learning',
                'Technology & IT'
            ];
            
            $stmt = $db->prepare("INSERT INTO categories (category_name) VALUES (?)");
            foreach ($defaultCategories as $category) {
                $stmt->execute([$category]);
                echo "<p>✓ Added category: $category</p>";
            }
        }
        
    } else {
        echo "<p>❌ Database connection failed</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>File Permissions:</h2>";
echo "<p>Uploads directory exists: " . (is_dir('uploads') ? 'YES' : 'NO') . "</p>";
echo "<p>Uploads directory writable: " . (is_writable('uploads') ? 'YES' : 'NO') . "</p>";

if (isAdmin()) {
    echo "<h2>Test Navigation:</h2>";
    echo "<p><a href='brand.php'>Test Brand Management</a></p>";
    echo "<p><a href='product.php'>Test Product Management</a></p>";
}
?>