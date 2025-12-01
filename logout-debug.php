<?php
// Debug logout - this will help identify issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Debug Logout Process</h3>";

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";

// Show current session data
echo "<p><strong>Current Session Data:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if user role exists
$user_role = $_SESSION['role'] ?? 'none';
echo "<p><strong>User Role:</strong> " . $user_role . "</p>";

// Clear session
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

echo "<p><strong>Session destroyed successfully</strong></p>";

// Determine redirect URL
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

echo "<p><strong>Redirect URL:</strong> " . $redirect_url . "</p>";
echo "<p><a href='" . $redirect_url . "'>Click here to redirect manually</a></p>";

// Auto redirect after 3 seconds
echo "<script>
setTimeout(function() {
    window.location.href = '" . $redirect_url . "';
}, 3000);
</script>";
echo "<p><em>Auto-redirecting in 3 seconds...</em></p>";
?>