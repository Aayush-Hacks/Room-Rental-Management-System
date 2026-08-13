<?php
/**
 * landlord/add_room_process.php
 * -----------------------------------------------------------------
 * Handles POST from add_room.php. Creates a new room listing.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/landlord/add_room.php');
    exit;
}

validate_csrf();

$landlordId = current_user_id();
$errors = [];

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$location    = trim($_POST['location'] ?? '');
$address     = trim($_POST['address'] ?? '');
$rentAmount  = trim($_POST['rent_amount'] ?? '');
$rentType    = $_POST['rent_type'] ?? 'monthly';
$roomType    = $_POST['room_type'] ?? 'single';
$capacity    = (int) ($_POST['capacity'] ?? 1);
$facilities  = $_POST['facilities'] ?? [];

// Validate
// Validate with max lengths
if (empty($title))                                                  $errors[] = 'Please enter a title.';
if (strlen($title) > 200)                                           $errors[] = 'Title must be under 200 characters.';
if (empty($description))                                            $errors[] = 'Please enter a description.';
if (strlen($description) > 5000)                                    $errors[] = 'Description must be under 5000 characters.';
if (empty($location))                                               $errors[] = 'Please enter a location.';
if (strlen($location) > 255)                                        $errors[] = 'Location must be under 255 characters.';
if (strlen($address) > 255)                                         $errors[] = 'Address must be under 255 characters.';
if (!is_numeric($rentAmount) || $rentAmount <= 0)                   $errors[] = 'Enter a valid rent amount.';
if (!in_array($rentType, ['monthly', 'weekly'], true))              $rentType = 'monthly';
if (!in_array($roomType, ['single', 'shared', 'apartment', 'studio'], true)) $roomType = 'single';
if ($capacity < 1) $capacity = 1;

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . BASE_URL . '/landlord/add_room.php');
    exit;
}

// Insert room
$stmt = $pdo->prepare(
    "INSERT INTO rooms (landlord_id, title, description, location, address, rent_amount, rent_type, room_type, capacity)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->execute([$landlordId, $title, $description, $location, $address, $rentAmount, $rentType, $roomType, $capacity]);
$roomId = (int) $pdo->lastInsertId();

// Insert facilities
if (!empty($facilities)) {
    $stmt = $pdo->prepare("INSERT INTO room_facilities (room_id, facility_name) VALUES (?, ?)");
    foreach ($facilities as $facility) {
        $stmt->execute([$roomId, trim($facility)]);
    }
}

// Handle image uploads with MIME type validation
if (!empty($_FILES['images']['name'][0]) && is_uploaded_file($_FILES['images']['tmp_name'][0])) {
    $uploadDir = __DIR__ . '/../uploads/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $maxFileSize = 5 * 1024 * 1024; // 5 MB limit
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $isFirst = true;
    $totalFiles = count($_FILES['images']['name']);
    for ($i = 0; $i < $totalFiles; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['images']['size'][$i] > $maxFileSize) continue;

        // Validate actual file content via MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['images']['tmp_name'][$i]);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowedMimeTypes, true)) continue;

        // Also check extension as a secondary measure
        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;

        $filename = 'room_' . $roomId . '_' . time() . '_' . $i . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $destPath)) {
            $imgStmt = $pdo->prepare(
                "INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)"
            );
            $imgStmt->execute([$roomId, 'uploads/rooms/' . $filename, $isFirst ? 1 : 0]);
            $isFirst = false;
        }
    }
}

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Room listed successfully!'];
header('Location: ' . BASE_URL . '/landlord/rooms.php');
exit;
