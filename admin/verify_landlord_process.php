<?php
/**
 * admin/verify_landlord_process.php
 * -----------------------------------------------------------------
 * Handles POST from verify_landlords.php. Approves or rejects a
 * landlord's verification_status. Admin-only, POST-only.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/verify_landlords.php');
    exit;
}

validate_csrf();

$userId = (int) ($_POST['user_id'] ?? 0);
$decision = $_POST['decision'] ?? '';

if (!in_array($decision, ['approved', 'rejected'], true) || $userId <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid verification request.'];
    header('Location: ' . BASE_URL . '/admin/verify_landlords.php');
    exit;
}

// Only ever touch rows that are actually pending landlords
$stmt = $pdo->prepare(
    "UPDATE users
     SET verification_status = ?
     WHERE user_id = ? AND role = 'landlord' AND verification_status = 'pending'"
);
$stmt->execute([$decision, $userId]);

if ($stmt->rowCount() === 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'That account was already reviewed or does not exist.'];
} else {
    $message = $decision === 'approved'
        ? 'Your landlord account has been approved. You can now add rooms.'
        : 'Your landlord application was not approved. Contact the site admin for details.';

    $notify = $pdo->prepare(
        "INSERT INTO notifications (user_id, message) VALUES (?, ?)"
    );
    $notify->execute([$userId, $message]);

    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => $decision === 'approved' ? 'Landlord approved.' : 'Landlord rejected.',
    ];
}

header('Location: ' . BASE_URL . '/admin/verify_landlords.php');
exit;
