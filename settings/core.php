<?php
// settings/core.php
// Part 1: Session Management & Admin Privileges

// Start session with sensible cookie settings
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
              || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,                // session cookie
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $secure,          // true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Give the session a clear name for this app
    session_name('authsid');
    session_start();
}

/**
 * Check if a user is logged in.
 * True when the session contains a user_id.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

/**
 * Check if the current user is an administrator.
 * Assumes role 1 = admin, 2 = regular user.
 */
function is_admin(): bool {
    return isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 1;
}

/**
 * Helper: get current user info (optional, not required by Part 1).
 */
function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'    => (int)($_SESSION['user_id'] ?? 0),
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => (int)($_SESSION['user_role'] ?? 0),
    ];
}

/* ---- OPTIONAL GUARDS (useful later, not required for Part 1) ----
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /auth/public/login.php');
        exit;
    }
}

function require_admin(): void {
    if (!is_logged_in() || !is_admin()) {
        header('Location: /auth/public/login.php');
        exit;
    }
}
------------------------------------------------------------------- */
