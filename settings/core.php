<?php
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
              || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,                
        'path'     => $params['path'] ?? '/',
        'domain'   => $params['domain'] ?? '',
        'secure'   => $secure,          
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    // Give the session a clear name for this app
    session_name('authsid');
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function is_admin(): bool {
    return isset($_SESSION['user_role']) && (int)$_SESSION['user_role'] === 1;
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id'    => (int)($_SESSION['user_id'] ?? 0),
        'name'  => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => (int)($_SESSION['user_role'] ?? 0),
    ];
}

