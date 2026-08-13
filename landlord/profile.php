<?php
/**
 * landlord/profile.php
 * -----------------------------------------------------------------
 * Landlord profile page. Shows current user info and allows
 * editing email, phone, password, and profile picture. The name is locked
 * to the citizenship document and cannot be changed after sign-up.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = current_user_id();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Fetch current user data first — needed by upload/remove handlers below
$stmt = $pdo->prepare("SELECT full_name, email, phone, profile_picture, citizenship_status, created_at FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . BASE_URL . '/landlord/dashboard.php');
    exit;
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    validate_csrf();

    $result = handle_profile_picture_upload($_FILES['profile_picture']);

    if ($result === '') {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please select an image to upload.'];
    } elseif (str_starts_with($result, 'uploads/')) {
        // Success — save path and remove old picture
        $oldPic = $user['profile_picture'] ?? '';
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        $stmt->execute([$result, $userId]);
        $_SESSION['profile_picture'] = $result;

        // Delete old file
        if ($oldPic && $oldPic !== $result) {
            $oldPath = __DIR__ . '/../' . $oldPic;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile picture updated!'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $result];
    }
    header('Location: ' . BASE_URL . '/landlord/profile.php');
    exit;
}

// Handle profile picture removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_photo'])) {
    validate_csrf();

    $oldPic = $user['profile_picture'] ?? '';
    if ($oldPic) {
        $oldPath = __DIR__ . '/../' . $oldPic;
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE user_id = ?");
        $stmt->execute([$userId]);
        $_SESSION['profile_picture'] = null;
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile picture removed.'];
    header('Location: ' . BASE_URL . '/landlord/profile.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    validate_csrf();

    // full_name is intentionally not read here — it's locked to match the
    // citizenship document and cannot be changed after sign-up.
    $email    = trim($_POST['email'] ?? '');
    $phone    = normalize_nepal_phone(trim($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!is_valid_nepal_phone($phone)) {
        $errors[] = 'Enter a valid 10-digit Nepali mobile number (e.g., 9812345678).';
    }

    // Check email uniqueness (exclude current user)
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already in use by another account.';
        }
    }

    // Password validation (optional change)
    if (!empty($password)) {
        if (!is_strong_password($password)) {
            $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (empty($errors)) {
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "UPDATE users SET email = ?, phone = ?, password = ? WHERE user_id = ?"
            );
            $stmt->execute([$email, $phone, $passwordHash, $userId]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE users SET email = ?, phone = ? WHERE user_id = ?"
            );
            $stmt->execute([$email, $phone, $userId]);
        }

        // Update session with new email
        $_SESSION['email'] = $email;

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully!'];
        header('Location: ' . BASE_URL . '/landlord/profile.php');
        exit;
    } else {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['old_input'] = [
            'email' => $email,
            'phone' => $phone,
        ];
        header('Location: ' . BASE_URL . '/landlord/profile.php');
        exit;
    }
}

$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput   = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

// Use old input on validation failure, otherwise DB values
// (full_name is locked and not editable, so it always comes from the DB)
$displayEmail = $oldInput['email'] ?? $user['email'];
$displayPhone = display_nepal_phone($oldInput['phone'] ?? $user['phone']);

$pageTitle = 'My profile';
$pageSubtitle = 'Update your personal information and password.';
$activeNav = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if (!empty($formErrors)): ?>
    <div class="alert alert-error" style="margin-bottom:22px;">
        <?php foreach ($formErrors as $error): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="panel" style="max-width: 600px;">
    <div class="panel-head">
        <h2>Profile information</h2>
        <a href="dashboard.php" class="btn btn-ghost">&larr; Back</a>
    </div>

    <form method="post" style="padding: 24px;" enctype="multipart/form-data">
        <!-- Profile picture card -->
        <div style="display:flex; align-items:center; gap:20px; padding:18px; background:var(--paper-soft); border-radius:var(--radius-md); margin-bottom:22px; flex-wrap:wrap;">
            <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user['profile_picture']); ?>" alt=""
                     style="width:72px; height:72px; border-radius:50%; object-fit:cover; flex-shrink:0; border:3px solid var(--white); box-shadow:0 2px 8px rgba(0,0,0,0.12);">
            <?php else: ?>
                <div style="width:72px; height:72px; border-radius:50%; background:var(--brick); color:var(--white); display:flex; align-items:center; justify-content:center; font-size:1.6rem; font-weight:700; flex-shrink:0;">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div style="flex:1;">
                <div style="font-weight:600; font-size:1.05rem;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div style="font-size:0.85rem; color:var(--slate); margin-bottom:8px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                  <?php echo render_role_badge('landlord'); ?>
                  <span>— Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                  <?php echo render_citizenship_badge($user['citizenship_status'] ?? null); ?>
                </div>
                <?php if (($user['citizenship_status'] ?? '') === 'pending'): ?>
                    <div style="font-size:0.78rem; color:var(--slate);">🪪 Your citizenship document is under admin review.</div>
                <?php elseif (($user['citizenship_status'] ?? '') === 'rejected'): ?>
                    <div style="font-size:0.78rem; color:var(--brick);">🪪 Your citizenship was not approved. Contact the site admin.</div>
                <?php elseif (($user['citizenship_status'] ?? '') === 'approved'): ?>
                    <div style="font-size:0.78rem; color:var(--success);">🪪 Citizenship verified — other users can see your Verified ID badge.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile picture upload -->
        <div style="display:flex; align-items:center; gap:12px; padding:14px 18px; background:var(--paper-soft); border-radius:var(--radius-sm); margin-bottom:22px; flex-wrap:wrap;">
            <label for="profile_picture" class="btn btn-sm btn-ghost" style="cursor:pointer;">
                📷 <?php echo empty($user['profile_picture']) ? 'Upload photo' : 'Change photo'; ?>
            </label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp"
                   style="display:none;" onchange="this.form.submit()">
            <input type="hidden" name="upload_photo" value="1">
            <?php echo csrf_field(); ?>
            <span style="font-size:0.78rem; color:var(--slate-light);">JPG, PNG, GIF or WebP — max 2MB</span>
            <?php if (!empty($user['profile_picture'])): ?>
                <button type="submit" name="remove_photo" value="1" class="btn btn-sm btn-sm-danger" style="padding:5px 12px; font-size:0.75rem; border-radius:6px; cursor:pointer; border:1.5px solid transparent; background:rgba(184,73,46,0.12); color:var(--brick-dark);">Remove</button>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="full_name">Full name</label>
            <div class="field-static" id="full_name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <p class="form-help" style="margin-top:6px;">🔒 Your name is locked to match your citizenship document and cannot be changed after sign-up. If it's incorrect, contact the site admin.</p>
        </div>

        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" placeholder="you@example.com"
                   value="<?php echo htmlspecialchars($displayEmail); ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Phone number <span class="field-hint">Nepali mobile — 10 digits</span></label>
            <div class="phone-input">
                <span class="phone-prefix" aria-hidden="true">🇳🇵 +977</span>
                <input type="tel" id="phone" name="phone" placeholder="98XXXXXXXX"
                       inputmode="numeric" autocomplete="tel-national"
                       value="<?php echo htmlspecialchars($displayPhone); ?>" required>
            </div>
        </div>

        <hr style="border:none; border-top:1px solid var(--line); margin:24px 0;">

        <h3 style="font-size:1rem; margin-bottom:16px;">Change password (optional)</h3>
        <p style="font-size:0.85rem; color:var(--slate); margin-bottom:16px;">Leave blank to keep your current password.</p>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" placeholder="8+ chars: upper, lower, number & symbol" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" autocomplete="new-password">
            </div>
        </div>

        <?php echo csrf_field(); ?>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="submit" name="update_profile" value="1" class="btn btn-primary">Save changes</button>
            <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>