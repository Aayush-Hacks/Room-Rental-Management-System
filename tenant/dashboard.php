<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';

$tenantId = current_user_id();

// --- Stat queries ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE tenant_id = ? AND status = 'pending'");
$stmt->execute([$tenantId]);
$pendingCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE tenant_id = ? AND status = 'approved'");
$stmt->execute([$tenantId]);
$approvedCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
$stmt->execute([$tenantId]);
$favoritesCount = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$reviewsCount = (int) $stmt->fetchColumn();

// --- Recent bookings for the table ---
$stmt = $pdo->prepare(
    "SELECT b.booking_id, b.status, b.move_in_date, b.created_at, r.title, r.location, r.rent_amount
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     WHERE b.tenant_id = ?
     ORDER BY b.created_at DESC
     LIMIT 5"
);
$stmt->execute([$tenantId]);
$recentBookings = $stmt->fetchAll();

$pageTitle = 'Your dashboard';
$pageSubtitle = 'A quick look at your bookings and saved rooms.';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Pending requests</div>
    <div class="stat-value accent"><?php echo $pendingCount; ?></div>
    <div class="stat-hint">Awaiting landlord response</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Approved bookings</div>
    <div class="stat-value"><?php echo $approvedCount; ?></div>
    <div class="stat-hint">Confirmed rooms</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Saved rooms</div>
    <div class="stat-value"><?php echo $favoritesCount; ?></div>
    <div class="stat-hint">In your favorites</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Reviews written</div>
    <div class="stat-value"><?php echo $reviewsCount; ?></div>
    <div class="stat-hint">Help future tenants</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Recent booking requests</h2>
    <a href="<?php echo BASE_URL; ?>/tenant/bookings.php" class="btn btn-ghost">View all</a>
  </div>

  <?php if (empty($recentBookings)): ?>
    <div class="empty-state">
      <strong>No bookings yet</strong>
      <p>Search for a room and send your first booking request.</p>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Room</th>
          <th>Location</th>
          <th>Rent</th>
          <th>Move-in date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><?php echo htmlspecialchars($b['title']); ?></td>
            <td><?php echo htmlspecialchars($b['location']); ?></td>
            <td>Rs <?php echo number_format((float) $b['rent_amount']); ?></td>
            <td><?php echo htmlspecialchars($b['move_in_date']); ?></td>
            <td><span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
