<?php
require_once __DIR__ . '/../settings/core.php';
header('Content-Type: text/plain; charset=utf-8');

if (is_logged_in()) {
    $u = current_user();
    echo "Logged in as: " . ($u['name'] ?? '(no name)') . " <" . ($u['email'] ?? '?') . ">" . PHP_EOL;
    echo "Role: " . ($u['role'] ?? 0) . PHP_EOL;
    echo is_admin() ? "You ARE an admin." . PHP_EOL : "You are NOT an admin." . PHP_EOL;
} else {
    echo "Not logged in." . PHP_EOL;
    echo "Log in, then reload this page." . PHP_EOL;
}
