<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

$landlordId = current_user_id();

// --- Stat queries ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE landlord_id = ?");
$stmt->execute([$landlordId]);
$roomCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.room_id = b.room_id
     WHERE r.landlord_id = ? AND b.status = 'pending'"
);
$stmt->execute([$landlordId]);
$pendingRequests = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE landlord_id = ? AND availability_status = 'booked'");
$stmt->execute([$landlordId]);
$occupiedCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT ROUND(AVG(rev.rating), 1) FROM reviews rev
     JOIN rooms r ON r.room_id = rev.room_id
     WHERE r.landlord_id = ?"
);
$stmt->execute([$landlordId]);
$avgRating = $stmt->fetchColumn();

// --- Recent booking requests for the table ---
$stmt = $pdo->prepare(
    "SELECT b.booking_id, b.status, b.move_in_date, r.title, u.full_name AS tenant_name
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     JOIN users u ON u.user_id = b.tenant_id
     WHERE r.landlord_id = ?
     ORDER BY b.created_at DESC
     LIMIT 5"
);
$stmt->execute([$landlordId]);
$recentRequests = $stmt->fetchAll();

$pageTitle = 'Landlord dashboard';
$pageSubtitle = 'Manage your rooms and respond to booking requests.';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Your rooms</div>
    <div class="stat-value"><?php echo $roomCount; ?></div>
    <div class="stat-hint">Total listings</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending requests</div>
    <div class="stat-value accent"><?php echo $pendingRequests; ?></div>
    <div class="stat-hint">Need your response</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Occupied rooms</div>
    <div class="stat-value"><?php echo $occupiedCount; ?></div>
    <div class="stat-hint">Currently booked</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Average rating</div>
    <div class="stat-value"><?php echo $avgRating !== null ? $avgRating : '—'; ?></div>
    <div class="stat-hint">Across all rooms</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Recent booking requests</h2>
    <a href="<?php echo BASE_URL; ?>/landlord/bookings.php" class="btn btn-ghost">View all</a>
  </div>

  <?php if (empty($recentRequests)): ?>
    <div class="empty-state">
      <strong>No booking requests yet</strong>
      <p>Once you add a room, tenant requests will show up here.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Room</th>
          <th>Tenant</th>
          <th>Move-in date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentRequests as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['title']); ?></td>
            <td><?php echo htmlspecialchars($r['tenant_name']); ?></td>
            <td><?php echo htmlspecialchars($r['move_in_date']); ?></td>
            <td><span class="status-pill status-<?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($roomCount === 0): ?>
  <div class="panel">
    <div class="empty-state">
      <strong>You haven't listed a room yet</strong>
      <p>Add your first room to start receiving booking requests.</p>
      <div class="mt-16">
        <a href="<?php echo BASE_URL; ?>/landlord/add_room.php" class="btn btn-primary">Add a room</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
