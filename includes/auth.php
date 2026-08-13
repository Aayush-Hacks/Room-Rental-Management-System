<?php
/**
 * includes/auth.php
 * -----------------------------------------------------------------
 * Session helpers. Include this at the top of any page that needs
 * to know who's logged in, or that must be restricted to a role.
 * -----------------------------------------------------------------
 */

// Base URL — change this if the app is deployed at a different path
if (!defined('BASE_URL')) {
    define('BASE_URL', '/RRMS');
}

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters before starting the session
    session_set_cookie_params([
        'lifetime' => 0,               // until browser closes
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,           // set to true if using HTTPS
        'httponly' => true,            // not accessible via JavaScript
        'samesite' => 'Lax',           // CSRF mitigation at cookie level
    ]);
    session_start();
}

/** Is anyone logged in right now? */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** Redirect to login.php if nobody's logged in. Call at the top of protected pages. */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Redirect to login.php if nobody's logged in, OR to a "not authorized"
 * page if they're logged in but hold the wrong role.
 */
function require_role(string $role): void
{
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/** Convenience getter for the logged-in user's id, or null. */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/** Convenience getter for the logged-in user's role, or null. */
function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Convenience getter for the logged-in user's full name. */
function current_user_name(): ?string
{
    return $_SESSION['full_name'] ?? null;
}

/** Where should this role land after login? */
function dashboard_path_for_role(string $role): string
{
    switch ($role) {
        case 'admin':
            return BASE_URL . '/admin/dashboard.php';
        case 'landlord':
            return BASE_URL . '/landlord/dashboard.php';
        default:
            return BASE_URL . '/tenant/dashboard.php';
    }
}

/**
 * Generate or retrieve a CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/** Render a hidden CSRF token input. Call inside any HTML <form>. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/** Validate the submitted CSRF token. Dies with 403 on failure. */
function validate_csrf(): void
{
    $token = $_POST['_csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

