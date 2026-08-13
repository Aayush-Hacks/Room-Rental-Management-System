<?php
/**
 * landlord/edit_room_process.php
 * -----------------------------------------------------------------
 * Handles POST from edit_room.php. Updates the room listing and
 * replaces facilities/photos as needed.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/landlord/rooms.php');
    exit;
}

validate_csrf();

$landlordId = current_user_id();
$roomId     = (int) ($_POST['room_id'] ?? 0);
$errors     = [];

$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$location    = trim($_POST['location'] ?? '');
$address     = trim($_POST['address'] ?? '');
$rentAmount  = trim($_POST['rent_amount'] ?? '');
$rentType    = $_POST['rent_type'] ?? 'monthly';
$roomType    = $_POST['room_type'] ?? 'single';
$capacity    = (int) ($_POST['capacity'] ?? 1);
$availabilityStatus = $_POST['availability_status'] ?? 'available';
$facilities  = $_POST['facilities'] ?? [];

// Verify room belongs to this landlord
$stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_id = ? AND landlord_id = ?");
$stmt->execute([$roomId, $landlordId]);
if (!$stmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Room not found.'];
    header('Location: ' . BASE_URL . '/landlord/rooms.php');
    exit;
}

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
if (!in_array($availabilityStatus, ['available', 'booked', 'maintenance'], true)) $availabilityStatus = 'available';
if ($capacity < 1) $capacity = 1;

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['old_input'] = $_POST;
    header('Location: ' . BASE_URL . '/landlord/edit_room.php?room_id=' . $roomId);
    exit;
}

// Update room
$stmt = $pdo->prepare(
    "UPDATE rooms
     SET title = ?, description = ?, location = ?, address = ?, rent_amount = ?,
         rent_type = ?, room_type = ?, capacity = ?, availability_status = ?
     WHERE room_id = ? AND landlord_id = ?"
);
$stmt->execute([
    $title, $description, $location, $address, $rentAmount,
    $rentType, $roomType, $capacity, $availabilityStatus,
    $roomId, $landlordId
]);

// Replace facilities: delete old, insert new
$pdo->prepare("DELETE FROM room_facilities WHERE room_id = ?")->execute([$roomId]);
if (!empty($facilities)) {
    $stmt = $pdo->prepare("INSERT INTO room_facilities (room_id, facility_name) VALUES (?, ?)");
    foreach ($facilities as $facility) {
        $stmt->execute([$roomId, trim($facility)]);
    }
}

// Handle new image uploads — only if files were provided
if (!empty($_FILES['images']['name'][0]) && is_uploaded_file($_FILES['images']['tmp_name'][0])) {
    $uploadDir = __DIR__ . '/../uploads/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $maxFileSize = 5 * 1024 * 1024; // 5 MB
    $uploadedCount = 0;
    $totalFiles = count($_FILES['images']['name']);

    // First pass: count valid uploads before removing old images
    for ($i = 0; $i < $totalFiles; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['images']['size'][$i] > $maxFileSize) continue;
        $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;
        $uploadedCount++;
    }

    // Only remove old images and upload new ones if at least one valid file exists
    if ($uploadedCount > 0) {
        // Remove old images from disk and DB
        $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ?");
        $stmt->execute([$roomId]);
        foreach ($stmt->fetchAll() as $old) {
            $oldPath = __DIR__ . '/../' . $old['image_path'];
            if (file_exists($oldPath)) unlink($oldPath);
        }
        $pdo->prepare("DELETE FROM room_images WHERE room_id = ?")->execute([$roomId]);

        // Upload new images
        $isFirst = true;
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
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
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $filename)) {
                $imgStmt = $pdo->prepare(
                    "INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)"
                );
                $imgStmt->execute([$roomId, 'uploads/rooms/' . $filename, $isFirst ? 1 : 0]);
                $isFirst = false;
            }
        }
    }
}

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Room updated successfully!'];
header('Location: ' . BASE_URL . '/landlord/rooms.php');
exit;
