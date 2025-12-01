<?php
// Prevent any output before headers
ob_start();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include auth for session handling
require_once 'includes/auth.php';

try {
    // Get user role before logout for proper redirection
    $user_role = $_SESSION['role'] ?? 'user';
    
    // Clear all session data
    $_SESSION = array();
    
    // Destroy the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clean any output buffer
    ob_end_clean();
    
    // Determine redirect URL based on user role
    switch ($user_role) {
        case 'admin':
        case 'super_admin':
            $redirect_url = 'admin/login.php?message=' . urlencode('You have been logged out successfully.') . '&type=success';
            break;
        case 'school_admin':
            $redirect_url = 'login.php?message=' . urlencode('You have been logged out successfully.') . '&type=success';
            break;
        case 'student':
        case 'user':
        default:
            $redirect_url = 'index.php?message=' . urlencode('You have been logged out successfully.') . '&type=success';
            break;
    }
    
    // Perform redirect
    header('Location: ' . $redirect_url);
    exit();
    
} catch (Exception $e) {
    // If anything fails, force redirect to homepage
    ob_end_clean();
    header('Location: index.php?message=' . urlencode('Logged out') . '&type=info');
    exit();
}
?>