<?php
/**
 * login_process.php
 * -----------------------------------------------------------------
 * Handles POST from login.php.
 * Validates credentials and starts a session on success.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

validate_csrf();

// ---- Rate limiting ----
$rateLimitKey = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$maxAttempts = 5;
$rateLimitWindow = 60; // 60 seconds

$rateData = $_SESSION[$rateLimitKey] ?? ['count' => 0, 'time' => 0];
$currentAttempts = $rateData['count'];
$firstAttemptTime = $rateData['time'];

// Reset if window has expired
if (time() - $firstAttemptTime > $rateLimitWindow) {
    $currentAttempts = 0;
    $firstAttemptTime = time();
    $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
}

if ($currentAttempts >= $maxAttempts) {
    $retryAfter = $rateLimitWindow - (time() - $firstAttemptTime);
    $_SESSION['form_errors'] = ['Too many login attempts. Please try again in ' . max(1, $retryAfter) . ' seconds.'];
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$errors = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email or password.';
}
if (empty($password)) {
    $errors[] = 'Invalid email or password.';
}

if (empty($errors)) {
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, password, role, verification_status, profile_picture FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = 'Invalid email or password.';
    } elseif ($user['role'] === 'landlord' && $user['verification_status'] === 'pending') {
        $errors[] = 'Your landlord account is still awaiting admin verification. Please check back later.';
    } elseif ($user['role'] === 'landlord' && $user['verification_status'] === 'rejected') {
        $errors[] = 'Your landlord application was not approved. Contact the site admin for details.';
    }
}

if (!empty($errors)) {
    // Increment rate limit counter on failed attempt
    $_SESSION[$rateLimitKey] = [
        'count' => $currentAttempts + 1,
        'time'  => $firstAttemptTime ?: time(),
    ];
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old_input'] = ['email' => $email];
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ---- Login success ----
// Clear rate limit on successful login
unset($_SESSION[$rateLimitKey]);
session_regenerate_id(true);
$_SESSION['user_id']           = (int) $user['user_id'];
$_SESSION['full_name']         = $user['full_name'];
$_SESSION['email']             = $user['email'];
$_SESSION['role']              = $user['role'];
$_SESSION['profile_picture']   = $user['profile_picture'];

// Check for a redirect URL (from room card clicks on landing page)
$redirect = $_POST['redirect'] ?? $_SESSION['login_redirect'] ?? '';
unset($_SESSION['login_redirect']);

if (!empty($redirect) && str_starts_with($redirect, 'tenant/')) {
    header('Location: ' . BASE_URL . '/' . $redirect);
    exit;
}

// Redirect to the right dashboard
switch ($user['role']) {
    case 'admin':
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        break;
    case 'landlord':
        header('Location: ' . BASE_URL . '/landlord/dashboard.php');
        break;
    default:
        header('Location: ' . BASE_URL . '/tenant/dashboard.php');
        break;
}
exit;
