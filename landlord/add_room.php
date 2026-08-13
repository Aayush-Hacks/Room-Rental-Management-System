<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$formErrors = $_SESSION['form_errors'] ?? [];
$oldInput   = $_SESSION['old_input'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['old_input']);

$facilityOptions = ['WiFi', 'Parking', 'Attached bathroom', 'Furnished', '24/7 water supply', 'Kitchen access'];
$oldFacilities = $oldInput['facilities'] ?? [];

$pageTitle = 'Add a room';
$pageSubtitle = 'List a new room for tenants to find.';
$activeNav = 'add_room';
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
    <h2>Room details</h2>
  </div>

  <form action="add_room_process.php" method="post" enctype="multipart/form-data" style="padding: 24px;">

    <div class="form-group">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" placeholder="e.g. Single room, attached bath" value="<?php echo htmlspecialchars($oldInput['title'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="4" placeholder="Describe the room, nearby landmarks, house rules..." required><?php echo htmlspecialchars($oldInput['description'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
      <label for="location">Location (area / city)</label>
      <input type="text" id="location" name="location" placeholder="e.g. Baneshwor, Kathmandu" value="<?php echo htmlspecialchars($oldInput['location'] ?? ''); ?>" required>
    </div>

    <div class="form-group">
      <label for="address">Full address</label>
      <input type="text" id="address" name="address" placeholder="Street / ward / landmark" value="<?php echo htmlspecialchars($oldInput['address'] ?? ''); ?>">
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="form-group">
        <label for="rent_amount">Rent amount (Rs)</label>
        <input type="number" id="rent_amount" name="rent_amount" min="1" step="1" value="<?php echo htmlspecialchars($oldInput['rent_amount'] ?? ''); ?>" required>
      </div>
      <div class="form-group">
        <label for="rent_type">Rent type</label>
        <select id="rent_type" name="rent_type" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
          <option value="monthly" <?php echo ($oldInput['rent_type'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
          <option value="weekly" <?php echo ($oldInput['rent_type'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
        </select>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
      <div class="form-group">
        <label for="room_type">Room type</label>
        <select id="room_type" name="room_type" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
          <option value="single">Single</option>
          <option value="shared">Shared</option>
          <option value="apartment">Apartment</option>
          <option value="studio">Studio</option>
        </select>
      </div>
      <div class="form-group">
        <label for="capacity">Capacity (max occupants)</label>
        <input type="number" id="capacity" name="capacity" min="1" step="1" value="<?php echo htmlspecialchars($oldInput['capacity'] ?? '1'); ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label>Facilities</label>
      <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:6px;">
        <?php foreach ($facilityOptions as $facility): ?>
          <label style="display:flex; align-items:center; gap:6px; background:var(--paper-soft); border:1.5px solid var(--line); border-radius:20px; padding:8px 14px; font-size:0.85rem;">
            <input type="checkbox" name="facilities[]" value="<?php echo htmlspecialchars($facility); ?>"
              <?php echo in_array($facility, $oldFacilities, true) ? 'checked' : ''; ?>>
            <?php echo htmlspecialchars($facility); ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-group">
      <label for="images">Room photos</label>
      <input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
      <p style="font-size:0.8rem; color:var(--slate); margin-top:6px;">JPG, PNG, or WebP. The first photo becomes the cover image.</p>
    </div>

    <?php echo csrf_field(); ?>
    <button type="submit" class="btn btn-primary">List this room</button>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
