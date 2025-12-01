<?php
/**
 * Database Configuration File for SchoolLink Africa
 * 
 * This file contains the database connection settings and PDO connection class.
 * Update the database credentials according to your hosting environment.
 */

class Database {
    // Database configuration - Update these values for your hosting environment
    private $host = 'localhost';
    private $db_name = 'u922239638_go';
    private $username = 'u922239638_go';  // Hostinger database username
    private $password = 'DreamBig2020@$';      // Hostinger database password
    private $charset = 'utf8mb4';
    
    public $conn;

    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}

/**
 * Global function to get database connection
 * @return PDO|null
 */
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Test database connection (comment out in production)
/*
$db = getDB();
if ($db) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
*/
?>