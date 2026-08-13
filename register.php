<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
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

$preselectedRole = ($_GET['role'] ?? '') === 'landlord' ? 'landlord' : 'tenant';

// Get form errors from session (set by register_process.php)
$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput   = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

// Re-select the account type the user chose if validation failed
if (($oldInput['role'] ?? '') === 'landlord') {
    $preselectedRole = 'landlord';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create your account — Room Rental System</title>
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <link rel="stylesheet" href="assets/css/style.css?v=19">
</head>

<body>

    <div class="auth-shell">
        <div class="auth-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <a href="index.php" class="auth-back">&larr; Back to Room Rental System</a>
                    <h1>Create your account</h1>
                    <p class="sub">Join as a tenant to search rooms, or a landlord to list them.</p>
                </div>
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" style="flex-shrink:0;">🌙</button>
            </div>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($formErrors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" action="register_process.php" method="post" enctype="multipart/form-data" novalidate>

                    <div class="role-toggle" role="radiogroup" aria-label="Account type">
                        <label class="role-option">
                            <input type="radio" name="role" value="tenant" <?php echo $preselectedRole === 'tenant'
                                ? 'checked' : ''; ?>>
                            <span class="btn">Tenant</span>
                        </label>
                        <label class="role-option">
                            <input type="radio" name="role" value="landlord" <?php echo $preselectedRole === 'landlord'
                                ? 'checked' : ''; ?>>
                            <span class="btn">Landlord</span>
                        </label>
                    </div>
                    <div class="form-error role-error" style="margin: -14px 0 18px;">Please choose an account type.</div>

                    <div class="form-note <?php echo $preselectedRole === 'landlord' ? 'is-visible' : ''; ?>">
                        Landlord accounts are reviewed by an admin before you can publish room listings. This usually takes
                        1–2 days.
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full name <span class="field-hint">exactly as on citizenship — cannot be changed later</span></label>
                        <input type="text" id="full_name" name="full_name" placeholder="Name as printed on your citizenship" autocomplete="name"
                            required value="<?php echo htmlspecialchars($oldInput['full_name'] ?? ''); ?>">
                        <div class="form-error">Enter your full name (at least 3 characters).</div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email"
                            required value="<?php echo htmlspecialchars($oldInput['email'] ?? ''); ?>">
                        <div class="form-error">Enter a valid email address.</div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone number </label>
                        <div class="phone-input">
                            <span class="phone-prefix" aria-hidden="true">🇳🇵 +977</span>
                            <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX"
                                inputmode="numeric" autocomplete="tel-national" required
                                value="<?php echo htmlspecialchars(display_nepal_phone($oldInput['phone'] ?? '')); ?>">
                        </div>
                        <div class="form-error">Enter a valid 10-digit Nepali mobile number (e.g., 9812345678).</div>
                    </div>

                    <div class="form-group">
                        <label>Citizenship document <span class="field-hint">Required — both sides</span></label>
                        <p class="form-help">JPG, PNG or WebP, under 2MB each. Only admins can view these; approved uploads add a "Verified ID" badge to your name.</p>
                    </div>
                    <div class="doc-upload-grid">
                        <div class="form-group">
                            <label class="doc-upload" for="citizenship_front">
                                <input type="file" id="citizenship_front" name="citizenship_front"
                                    accept="image/jpeg,image/png,image/webp" required>
                                <span class="doc-upload-icon">🪪</span>
                                <span class="doc-upload-title">Front side</span>
                                <span class="doc-upload-sub"></span>
                            </label>
                            <div class="form-error">Front side of your citizenship is required.</div>
                        </div>
                        <div class="form-group">
                            <label class="doc-upload" for="citizenship_back">
                                <input type="file" id="citizenship_back" name="citizenship_back"
                                    accept="image/jpeg,image/png,image/webp" required>
                                <span class="doc-upload-icon">🪪</span>
                                <span class="doc-upload-title">Back side</span>
                                <span class="doc-upload-sub"></span>
                            </label>
                            <div class="form-error">Back side of your citizenship is required.</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create a strong password"
                            autocomplete="new-password" required aria-describedby="passwordStrength">
                        <div class="form-error">Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.</div>
                        <div class="strength-meter" id="passwordStrength" data-level="0" aria-live="polite">
                            <div class="strength-bars" aria-hidden="true">
                                <span class="strength-bar"></span>
                                <span class="strength-bar"></span>
                                <span class="strength-bar"></span>
                                <span class="strength-bar"></span>
                                <span class="strength-bar"></span>
                            </div>
                            <div class="strength-label">Password strength</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Re-enter your password" autocomplete="new-password" required>
                        <div class="form-error">Passwords do not match.</div>
                    </div>

                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>

    <script src="assets/js/main.js?v=2"></script>
</body>

</html>
