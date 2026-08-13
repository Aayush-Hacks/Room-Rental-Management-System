<?php
require_once __DIR__ . '/includes/auth.php';

// Require POST with CSRF token to prevent logout CSRF attacks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

validate_csrf();

// Clear all session data and destroy the session entirely.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;
