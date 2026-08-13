<?php
/**
 * landlord/edit_room.php
 * -----------------------------------------------------------------
 * Edit an existing room listing. Pre-fills the form with current
 * room data, facilities, and primary image. Same layout as add_room.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

$landlordId = current_user_id();
$roomId = (int) ($_GET['room_id'] ?? 0);

// Fetch room — only if it belongs to this landlord
$stmt = $pdo->prepare(
    "SELECT * FROM rooms WHERE room_id = ? AND landlord_id = ?"
);
$stmt->execute([$roomId, $landlordId]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: ' . BASE_URL . '/landlord/rooms.php');
    exit;
}

// Fetch existing facilities
$stmt = $pdo->prepare("SELECT facility_name FROM room_facilities WHERE room_id = ?");
$stmt->execute([$roomId]);
$existingFacilities = array_column($stmt->fetchAll(), 'facility_name');

// Fetch primary image
$stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ? AND is_primary = 1 LIMIT 1");
$stmt->execute([$roomId]);
$primaryImage = $stmt->fetchColumn();

$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput   = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

// If we have old input (from a failed save), use that instead of DB values
$formData = !empty($oldInput) ? $oldInput : $room;
$formFacilities = !empty($oldInput) ? ($oldInput['facilities'] ?? []) : $existingFacilities;

$facilityOptions = ['WiFi', 'Parking', 'Attached bathroom', 'Furnished', '24/7 water supply', 'Kitchen access'];

$pageTitle = 'Edit room';
$pageSubtitle = 'Update your room listing details.';
$activeNav = 'rooms';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($formErrors)): ?>
  <div class="alert alert-error" style="margin-bottom:22px;">
    <?php foreach ($formErrors as $error): ?>
      <div><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="panel" style="max-width: 720px;">
  <div class="panel-head">
    <h2>Edit room</h2>
    <a href="rooms.php" class="btn btn-ghost" style="padding:8px 16px; font-size:0.85rem;">&larr; Back</a>
  </div>

  <form action="edit_room_process.php" method="post" enctype="multipart/form-data" style="padding: 24px;">
    <input type="hidden" name="room_id" value="<?php echo (int) $room['room_id']; ?>">

    <div class="form-group">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" placeholder="e.g. Single room, attached bath"
        value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" placeholder="Describe the room, nearby landmarks, house rules..."
        required><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
      <label for="location">Location (area / city)</label>
      <input type="text" id="location" name="location" placeholder="e.g. Baneshwor, Kathmandu"
        value="<?php echo htmlspecialchars($formData['location'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
      <label for="address">Full address</label>
      <input type="text" id="address" name="address" placeholder="Street / ward / landmark"
        value="<?php echo htmlspecialchars($formData['address'] ?? ''); ?>">
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="form-group">
        <label for="rent_amount">Rent amount (Rs)</label>
        <input type="number" id="rent_amount" name="rent_amount" min="1" step="1"
          value="<?php echo htmlspecialchars($formData['rent_amount'] ?? ''); ?>" required>
      </div>
      <div class="form-group">
        <label for="rent_type">Rent type</label>
        <select id="rent_type" name="rent_type" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
          <option value="monthly" <?php echo ($formData['rent_type'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
          <option value="weekly" <?php echo ($formData['rent_type'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
        </select>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="form-group">
        <label for="room_type">Room type</label>
        <select id="room_type" name="room_type" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
          <option value="single" <?php echo ($formData['room_type'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
          <option value="shared" <?php echo ($formData['room_type'] ?? '') === 'shared' ? 'selected' : ''; ?>>Shared</option>
          <option value="apartment" <?php echo ($formData['room_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
          <option value="studio" <?php echo ($formData['room_type'] ?? '') === 'studio' ? 'selected' : ''; ?>>Studio</option>
        </select>
      </div>
      <div class="form-group">
        <label for="capacity">Capacity (max occupants)</label>
        <input type="number" id="capacity" name="capacity" min="1" step="1"
          value="<?php echo htmlspecialchars($formData['capacity'] ?? '1'); ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label for="availability_status">Status</label>
      <select id="availability_status" name="availability_status" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
        <option value="available" <?php echo ($formData['availability_status'] ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
        <option value="booked" <?php echo ($formData['availability_status'] ?? '') === 'booked' ? 'selected' : ''; ?>>Booked</option>
        <option value="maintenance" <?php echo ($formData['availability_status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
      </select>
    </div>

    <div class="form-group">
      <label>Facilities</label>
      <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:6px;">
        <?php foreach ($facilityOptions as $facility): ?>
          <label style="display:flex; align-items:center; gap:6px; background:var(--paper-soft); border:1.5px solid var(--line); border-radius:20px; padding:8px 14px; font-size:0.85rem;">
            <input type="checkbox" name="facilities[]" value="<?php echo htmlspecialchars($facility); ?>"
              <?php echo in_array($facility, $formFacilities, true) ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars($facility); ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label for="images">Room photos</label>
      <?php if ($primaryImage): ?>
        <div style="margin-bottom:10px;">
          <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($primaryImage); ?>" alt="Current photo"
            style="max-width:200px; border-radius:var(--radius-sm); border:1.5px solid var(--line);">
          <p style="font-size:0.8rem; color:var(--slate); margin-top:4px;">Current cover photo. Upload new images below to replace.</p>
        </div>
      <?php endif; ?>
      <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
      <p style="font-size:0.8rem; color:var(--slate); margin-top:6px;">JPG, PNG, or WebP. Leave empty to keep existing photos.</p>
    </div>

    <?php echo csrf_field(); ?>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">Save changes</button>
      <a href="rooms.php" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
