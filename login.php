<?php
require_once __DIR__ . '/includes/auth.php';
// If already logged in, honor a pending redirect (e.g. clicking "Book Now" on a room card),
// otherwise send them to their dashboard.
if (isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? '';
    $role = $_SESSION['role'] ?? '';
    // A tenant clicking "Book Now" should land straight on the room details,
    // not be bounced to their dashboard.
    if ($role === 'tenant' && !empty($redirect) && str_starts_with($redirect, 'tenant/')) {
        header('Location: ' . BASE_URL . '/' . $redirect);
        exit;
    }
    switch ($role) {
        case 'admin':
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        case 'landlord':
            header('Location: ' . BASE_URL . '/landlord/dashboard.php');
            exit;
        case 'tenant':
        default:
            header('Location: ' . BASE_URL . '/tenant/dashboard.php');
            exit;
    }
}

$justRegistered = isset($_GET['registered']);
$isPendingLandlord = isset($_GET['pending']);

// Store redirect URL from query string (e.g. login.php?redirect=tenant/room_detail.php%3Froom_id%3D5)
$redirect = $_GET['redirect'] ?? '';
$_SESSION['login_redirect'] = $redirect;

// Get form errors from session (set by login_process.php)
$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput   = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — Room Rental System</title>
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <link rel="stylesheet" href="assets/css/style.css?v=19">
</head>

<body>

    <div class="auth-shell">
        <div class="auth-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <a href="index.php" class="auth-back">&larr; Back to Room Rental System</a>
                    <h1>Welcome back</h1>
                    <p class="sub">Log in to manage your bookings, listings, or account.</p>
                </div>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" style="flex-shrink:0;">🌙</button>
            </div>

            <?php if ($justRegistered): ?>
            <div class="alert alert-success">
                <?php echo $isPendingLandlord
                        ? 'Account created. Your landlord account is pending admin verification — you\'ll be able to list rooms once approved. Your citizenship document is also under admin review.'
                        : 'Account created successfully. You can log in now — your citizenship document is under admin review.'; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($formErrors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_process.php" method="post" novalidate>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
                        required value="<?php echo htmlspecialchars($oldInput['email'] ?? ''); ?>">
                    <div class="form-error">Enter a valid email address.</div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" placeholder="Your password"
                            autocomplete="current-password" required>
                        <button type="button" class="password-toggle" id="passwordToggle"
                                aria-label="Show password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                    <div class="form-error">Enter your password.</div>
                </div>

                <?php if ($redirect): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <?php endif; ?>
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>

            <p class="auth-switch">New to Room Rental System? <a href="register.php">Create an account</a></p>
        </div>
    </div>

    <script src="assets/js/main.js?v=2"></script>
</body>

</html>
