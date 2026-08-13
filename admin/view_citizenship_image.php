<?php
/**
 * admin/view_citizenship_image.php
 * -----------------------------------------------------------------
 * Admin-only endpoint that streams a user's citizenship document
 * image (front or back side). Direct file access to uploads/
 * is blocked via .htaccess, so this is the only way to view them.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

$userId = (int) ($_GET['user_id'] ?? 0);
$side = $_GET['side'] ?? 'front';

if ($userId <= 0 || !in_array($side, ['front', 'back'], true)) {
    http_response_code(400);
    exit('Bad request.');
}

$stmt = $pdo->prepare('SELECT citizenship_front, citizenship_back FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || empty($user['citizenship_' . $side])) {
    http_response_code(404);
    exit('Document not found.');
}

$path = __DIR__ . '/../' . $user['citizenship_' . $side];

// Defense in depth: the resolved file must live inside uploads/citizenships/
$realPath = realpath($path);
$realUploadsDir = realpath(__DIR__ . '/../uploads/citizenships/');
if ($realPath === false || $realUploadsDir === false
    || strpos($realPath, $realUploadsDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403);
    exit('Forbidden.');
}

// Only ever serve images
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    http_response_code(500);
    exit('Server error.');
}
$mime = finfo_file($finfo, $realPath);
finfo_close($finfo);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(403);
    exit('Forbidden.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('X-Content-Type-Options: nosniff');
readfile($realPath);
exit;
