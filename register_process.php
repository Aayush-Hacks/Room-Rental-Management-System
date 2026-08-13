<?php
/**
 * register_process.php
 * -----------------------------------------------------------------
 * Handles POST from register.php.
 * Validates + sanitizes input, checks email uniqueness, hashes the
 * password, and inserts into `users`. Landlords land in
 * verification_status='pending' automatically.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}

validate_csrf();

$errors = [];

$fullName = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = normalize_nepal_phone(trim($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? '';

// ---- Validation with max lengths ----
if (strlen($fullName) < 3) {
    $errors[] = 'Full name must be at least 3 characters.';
}
if (strlen($fullName) > 120) {
    $errors[] = 'Full name must be under 120 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
}
if (strlen($email) > 255) {
    $errors[] = 'Email must be under 255 characters.';
}
if (!is_valid_nepal_phone($phone)) {
    $errors[] = 'Enter a valid 10-digit Nepali mobile number (e.g., 9812345678).';
}
if (!is_strong_password($password)) {
    $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.';
} elseif (strlen($password) > 128) {
    $errors[] = 'Password must be under 128 characters.';
}
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}
if (!in_array($role, ['tenant', 'landlord'], true)) {
    $errors[] = 'Please choose an account type.';
}

if (empty($errors)) {
    // Check email isn't already registered
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errors[] = 'This email is already registered. Try logging in instead.';
    }
}

// ---- Citizenship documents (required for all accounts) ----
$citizenshipFront = '';
$citizenshipBack  = '';

if (empty($errors)) {
    $citFront = $_FILES['citizenship_front'] ?? null;
    $citBack  = $_FILES['citizenship_back'] ?? null;

    $frontMissing = !$citFront || $citFront['error'] === UPLOAD_ERR_NO_FILE;
    $backMissing  = !$citBack  || $citBack['error']  === UPLOAD_ERR_NO_FILE;

    if ($frontMissing || $backMissing) {
        $errors[] = 'Both sides (front and back) of your citizenship document are required.';
    } else {
        // Validate + save both sides, reporting every failing side at once
        $citizenshipFront = handle_citizenship_upload($citFront);
        $citizenshipBack  = handle_citizenship_upload($citBack);

        if (!str_starts_with($citizenshipFront, 'uploads/')) {
            $errors[] = 'Citizenship (front): ' . $citizenshipFront;
            $citizenshipFront = '';
        }
        if (!str_starts_with($citizenshipBack, 'uploads/')) {
            $errors[] = 'Citizenship (back): ' . $citizenshipBack;
            $citizenshipBack = '';
        }

        // If either side failed, remove the file the other side saved
        if ($citizenshipFront === '' && $citizenshipBack !== '') {
            @unlink(__DIR__ . '/' . $citizenshipBack);
            $citizenshipBack = '';
        } elseif ($citizenshipBack === '' && $citizenshipFront !== '') {
            @unlink(__DIR__ . '/' . $citizenshipFront);
            $citizenshipFront = '';
        }
    }
}

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old_input'] = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'role' => $role];
    header('Location: ' . BASE_URL . '/register.php');
    exit;
}

// ---- Insert ----
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Landlords start as 'pending', tenants auto-approved; citizenship is always 'pending' until an admin reviews it
$verificationStatus = ($role === 'landlord') ? 'pending' : 'approved';

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, phone, password, role, verification_status, citizenship_front, citizenship_back, citizenship_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$fullName, $email, $phone, $passwordHash, $role, $verificationStatus, $citizenshipFront, $citizenshipBack, 'pending']);

$newUserId = (int) $pdo->lastInsertId();

// ---- Redirect to login with the right success message ----
$redirect = BASE_URL . '/login.php?registered=1';
if ($role === 'landlord') {
    $redirect .= '&pending=1';
}
header('Location: ' . $redirect);
exit;
