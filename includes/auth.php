<?php
/**
 * Authentication and Session Management for SchoolLink Africa
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Hash password using PHP's password_hash function
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Authenticate user login
 * @param string $email
 * @param string $password
 * @return array|false
 */
function authenticateUser($email, $password) {
    $db = getDB();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            return $user;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Login user and create session
 * @param array $user
 */
function loginUser($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['school_id'] = $user['school_id'];
    $_SESSION['approved'] = $user['approved'];
    $_SESSION['logged_in'] = true;
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Logout user and destroy session
 */
function logoutUser() {
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    // Get full user data from database
    $db = getDB();
    if (!$db) {
        // Fallback to session data if database is unavailable
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'school_id' => $_SESSION['school_id'],
            'approved' => $_SESSION['approved'],
            'profile_photo' => null
        ];
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user;
        } else {
            // User not found in database, return session data
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role'],
                'school_id' => $_SESSION['school_id'],
                'approved' => $_SESSION['approved'],
                'profile_photo' => null
            ];
        }
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        // Fallback to session data
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'school_id' => $_SESSION['school_id'],
            'approved' => $_SESSION['approved'],
            'profile_photo' => null
        ];
    }
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

/**
 * Check if user is super admin
 * @return bool
 */
function isSuperAdmin() {
    return hasRole('super_admin');
}

/**
 * Check if user is school admin
 * @return bool
 */
function isSchoolAdmin() {
    return hasRole('school_admin');
}

/**
 * Check if user is student/alumni
 * @return bool
 */
function isStudent() {
    return hasRole('student');
}

/**
 * Check if user is admin (super_admin or school_admin)
 * @return bool
 */
function isAdmin() {
    return isSuperAdmin() || isSchoolAdmin();
}

/**
 * Require login - redirect to login page if not logged in
 * @param string $redirect_url
 */
function requireLogin($redirect_url = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Require specific role - redirect if user doesn't have required role
 * @param string $required_role
 * @param string $redirect_url
 */
function requireRole($required_role, $redirect_url = 'index.php') {
    requireLogin();
    
    if (!hasRole($required_role)) {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Require approval - redirect if user is not approved
 * @param string $redirect_url
 */
function requireApproval($redirect_url = 'pending-approval.php') {
    requireLogin();
    
    if (!$_SESSION['approved'] && $_SESSION['user_role'] !== 'super_admin') {
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Register new user
 * @param array $data
 * @return array
 */
function registerUser($data) {
    $db = getDB();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Insert new user
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, school_id, year_group, status, student_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $hashedPassword = hashPassword($data['password']);
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $hashedPassword,
            $data['role'],
            isset($data['school_id']) ? $data['school_id'] : null,
            isset($data['year_group']) ? $data['year_group'] : null,
            isset($data['status']) ? $data['status'] : null,
            isset($data['student_id']) ? $data['student_id'] : null
        ]);
        
        return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $db->lastInsertId()];
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>