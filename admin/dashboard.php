<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

// --- Core stat queries (same as before) ----
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLandlords = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord'")->fetchColumn();
$totalTenants = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tenant'")->fetchColumn();

$pendingLandlords = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'landlord' AND verification_status = 'pending'"
)->fetchColumn();

$approvedLandlords = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'landlord' AND verification_status = 'approved'"
)->fetchColumn();

$totalRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$availableRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'available'")->fetchColumn();
$bookedRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'booked'")->fetchColumn();
$maintRooms = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'maintenance'")->fetchColumn();

$openComplaints = (int) $pdo->query(
    "SELECT COUNT(*) FROM complaints WHERE status != 'resolved'"
)->fetchColumn();

$resolvedComplaints = (int) $pdo->query(
    "SELECT COUNT(*) FROM complaints WHERE status = 'resolved'"
)->fetchColumn();

$pendingBookings = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"
)->fetchColumn();

$totalBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$approvedBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$totalComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();

// --- Richer queries for the enhanced dashboard ---

// New registrations today
$newToday = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

// New registrations this week
$newThisWeek = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)"
)->fetchColumn();

// Bookings this month
$bookingsThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) FROM bookings WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
)->fetchColumn();

// Complaints filed this month
$complaintsThisMonth = (int) $pdo->query(
    "SELECT COUNT(*) FROM complaints WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
)->fetchColumn();

// Total reviews count
$totalReviews = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

// Average rating across all reviews
$avgRating = $pdo->query("SELECT ROUND(AVG(rating), 1) FROM reviews")->fetchColumn();

// --- Landlords waiting on verification (preview list) ---
$stmt = $pdo->query(
    "SELECT user_id, full_name, email, phone, created_at
     FROM users
     WHERE role = 'landlord' AND verification_status = 'pending'
     ORDER BY created_at ASC
     LIMIT 5"
);
$pendingList = $stmt->fetchAll();

// --- Recent bookings ---
$recentBookings = $pdo->query(
    "SELECT b.booking_id, b.status, b.created_at,
            r.title AS room_title, u.full_name AS tenant_name
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     JOIN users u ON u.user_id = b.tenant_id
     ORDER BY b.created_at DESC
     LIMIT 5"
)->fetchAll();

// --- Recent complaints ---
$recentComplaints = $pdo->query(
    "SELECT c.complaint_id, c.subject, c.status, c.created_at,
            u.full_name AS tenant_name
     FROM complaints c
     JOIN users u ON u.user_id = c.tenant_id
     ORDER BY c.created_at DESC
     LIMIT 5"
)->fetchAll();

// --- Recent user registrations (latest 4) ---
$recentUsers = $pdo->query(
    "SELECT user_id, full_name, role, created_at
     FROM users
     ORDER BY created_at DESC
     LIMIT 4"
)->fetchAll();

// Calculate percentages
$occupancyRate = $totalRooms > 0 ? round(($bookedRooms / $totalRooms) * 100) : 0;
$bookingApprovalRate = $totalBookings > 0 ? round(($approvedBookings / $totalBookings) * 100) : 0;
$complaintResolvedRate = $totalComplaints > 0 ? round(($resolvedComplaints / $totalComplaints) * 100) : 0;
$landlordVerificationRate = $totalLandlords > 0 ? round(($approvedLandlords / $totalLandlords) * 100) : 0;

// Current date/time for greeting
$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
$currentDate = date('l, F j, Y');

$pageTitle = 'Dashboard';
$pageSubtitle = 'System-wide overview and pending actions.';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ───── Welcome Banner ───── -->
<div class="welcome-banner">
  <div class="welcome-banner-text">
    <span class="welcome-banner-greeting"><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?> 👋</span>
    <h2 class="welcome-banner-title">Platform overview</h2>
    <p class="welcome-banner-date"><?php echo $currentDate; ?></p>
  </div>
  <div class="welcome-banner-actions">
    <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-ghost">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
      View reports
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/verify_landlords.php" class="btn btn-primary">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
      <?php echo $pendingLandlords > 0 ? "Verify landlords ({$pendingLandlords})" : 'Verify landlords'; ?>
    </a>
  </div>
  <?php if ($newToday > 0 || $pendingLandlords > 0 || $pendingBookings > 0): ?>
  <div class="welcome-alerts">
    <?php if ($newToday > 0): ?>
      <span class="welcome-alert-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <strong><?php echo $newToday; ?></strong> new user<?php echo $newToday !== 1 ? 's' : ''; ?> joined today
      </span>
    <?php endif; ?>
    <?php if ($pendingLandlords > 0): ?>
      <span class="welcome-alert-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <strong><?php echo $pendingLandlords; ?></strong> landlord<?php echo $pendingLandlords !== 1 ? 's' : ''; ?> pending verification
      </span>
    <?php endif; ?>
    <?php if ($pendingBookings > 0): ?>
      <span class="welcome-alert-item">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <strong><?php echo $pendingBookings; ?></strong> pending booking<?php echo $pendingBookings !== 1 ? 's' : ''; ?>
      </span>
    <?php endif; ?>
    <?php if ($openComplaints > 0): ?>
      <span class="welcome-alert-item welcome-alert-item--warn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <strong><?php echo $openComplaints; ?></strong> open complaint<?php echo $openComplaints !== 1 ? 's' : ''; ?>
      </span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ───── Enhanced Stat Cards ───── -->
<div class="stat-grid">
  <div class="stat-card stat-card--users">
    <div class="stat-card-icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
    </div>
    <div class="stat-card-body">
      <div class="stat-label">Total users</div>
      <div class="stat-value"><?php echo $totalUsers; ?></div>
      <div class="stat-hint">
        <span class="stat-hint-dot" style="background:#4B7A5B;"></span>
        <?php echo $totalLandlords; ?> landlords
        <span class="stat-hint-dot" style="background:var(--brick);"></span>
        <?php echo $totalTenants; ?> tenants
      </div>
    </div>
    <?php if ($newToday > 0): ?>
      <span class="stat-card-badge">+<?php echo $newToday; ?> today</span>
    <?php endif; ?>
  </div>

  <div class="stat-card stat-card--rooms">
    <div class="stat-card-icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 10.5L12 3l9 7.5"/>
        <path d="M5 9v9a1 1 0 0 0 1 1h4v-5h4v5h4a1 1 0 0 0 1-1V9"/>
      </svg>
    </div>
    <div class="stat-card-body">
      <div class="stat-label">Total rooms</div>
      <div class="stat-value"><?php echo $totalRooms; ?></div>
      <div class="stat-hint"><?php echo $availableRooms; ?> available, <?php echo $bookedRooms; ?> booked</div>
    </div>
    <?php if ($totalRooms > 0): ?>
      <div class="stat-card-bar">
        <div class="stat-card-bar-track">
          <div class="stat-card-bar-fill" style="width:<?php echo $occupancyRate; ?>%;"></div>
        </div>
        <span class="stat-card-bar-label"><?php echo $occupancyRate; ?>% occupied</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="stat-card stat-card--bookings">
    <div class="stat-card-icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8" y1="2" x2="8" y2="6"/>
        <line x1="3" y1="10" x2="21" y2="10"/>
      </svg>
    </div>
    <div class="stat-card-body">
      <div class="stat-label">Bookings</div>
      <div class="stat-value"><?php echo $totalBookings; ?></div>
      <div class="stat-hint">
        <span class="stat-hint-dot" style="background:var(--marigold);"></span>
        <?php echo $pendingBookings; ?> pending
        <span class="stat-hint-dot" style="background:var(--success);"></span>
        <?php echo $approvedBookings; ?> approved
      </div>
    </div>
    <?php if ($totalBookings > 0): ?>
      <div class="stat-card-bar">
        <div class="stat-card-bar-track">
          <div class="stat-card-bar-fill" style="width:<?php echo $bookingApprovalRate; ?>%; background:var(--success);"></div>
        </div>
        <span class="stat-card-bar-label"><?php echo $bookingApprovalRate; ?>% approved</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="stat-card stat-card--landlords">
    <div class="stat-card-icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>
    <div class="stat-card-body">
      <div class="stat-label">Landlord verification</div>
      <div class="stat-value <?php echo $pendingLandlords > 0 ? 'accent' : ''; ?>"><?php echo $pendingLandlords; ?></div>
      <div class="stat-hint">Pending approval</div>
    </div>
    <?php if ($totalLandlords > 0): ?>
      <div class="stat-card-bar">
        <div class="stat-card-bar-track">
          <div class="stat-card-bar-fill" style="width:<?php echo $landlordVerificationRate; ?>%; background:var(--success);"></div>
        </div>
        <span class="stat-card-bar-label"><?php echo $landlordVerificationRate; ?>% verified</span>
      </div>
    <?php endif; ?>
  </div>

  <div class="stat-card stat-card--complaints">
    <div class="stat-card-icon">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <div class="stat-card-body">
      <div class="stat-label">Complaints</div>
      <div class="stat-value <?php echo $openComplaints > 0 ? 'accent' : ''; ?>"><?php echo $totalComplaints; ?></div>
      <div class="stat-hint"><?php echo $openComplaints; ?> unresolved</div>
    </div>
    <?php if ($totalComplaints > 0): ?>
      <div class="stat-card-bar">
        <div class="stat-card-bar-track">
          <div class="stat-card-bar-fill" style="width:<?php echo $complaintResolvedRate; ?>%; background:var(--success);"></div>
        </div>
        <span class="stat-card-bar-label"><?php echo $complaintResolvedRate; ?>% resolved</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ───── Quick Actions ───── -->
<div class="quick-actions">
  <div class="quick-actions-head">
    <h2>Quick actions</h2>
  </div>
  <div class="quick-actions-grid">
    <a href="<?php echo BASE_URL; ?>/admin/verify_landlords.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--verify">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
      </div>
      <div class="quick-action-label">Verify landlords</div>
      <div class="quick-action-count"><?php echo $pendingLandlords; ?> pending</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/bookings.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--bookings">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div class="quick-action-label">Review bookings</div>
      <div class="quick-action-count"><?php echo $pendingBookings; ?> pending</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/complaints.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--complaints">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="quick-action-label">Handle complaints</div>
      <div class="quick-action-count"><?php echo $openComplaints; ?> open</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/rooms.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--rooms">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9v9a1 1 0 0 0 1 1h4v-5h4v5h4a1 1 0 0 0 1-1V9"/></svg>
      </div>
      <div class="quick-action-label">Manage rooms</div>
      <div class="quick-action-count"><?php echo $totalRooms; ?> total</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/users.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--users">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="quick-action-label">Manage users</div>
      <div class="quick-action-count"><?php echo $totalUsers; ?> total</div>
    </a>
    <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="quick-action-card">
      <div class="quick-action-icon quick-action-icon--reports">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
      </div>
      <div class="quick-action-label">View reports</div>
      <div class="quick-action-count">Analytics</div>
    </a>
  </div>
</div>

<!-- ───── Two-column layout for tables ───── -->
<div class="dash-columns">
  <!-- Left column: Landlords awaiting verification -->
  <div class="panel">
    <div class="panel-head">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Landlords awaiting verification
      </h2>
      <a href="<?php echo BASE_URL; ?>/admin/verify_landlords.php" class="btn btn-ghost">View all</a>
    </div>

    <?php if (empty($pendingList)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <strong>All caught up</strong>
        <p>No landlord accounts are waiting for verification right now.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Registered</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingList as $l): ?>
              <tr>
                <td>
                  <div class="cell-name">
                    <span class="cell-avatar"><?php echo strtoupper(substr($l['full_name'], 0, 1)); ?></span>
                    <?php echo htmlspecialchars($l['full_name']); ?>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($l['email']); ?></td>
                <td><?php echo htmlspecialchars($l['phone']); ?></td>
                <td class="cell-date"><?php echo htmlspecialchars(date('M j, g:ia', strtotime($l['created_at']))); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right column: Recent bookings -->
  <div class="panel">
    <div class="panel-head">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Recent bookings
      </h2>
      <a href="<?php echo BASE_URL; ?>/admin/bookings.php" class="btn btn-ghost">View all</a>
    </div>

    <?php if (empty($recentBookings)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <strong>No bookings yet</strong>
        <p>Booking requests will appear here once tenants start booking.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Room</th>
              <th>Tenant</th>
              <th>Status</th>
              <th>Requested</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentBookings as $b): ?>
              <tr>
                <td><?php echo htmlspecialchars($b['room_title']); ?></td>
                <td>
                  <div class="cell-name">
                    <span class="cell-avatar cell-avatar--sm"><?php echo strtoupper(substr($b['tenant_name'], 0, 1)); ?></span>
                    <?php echo htmlspecialchars($b['tenant_name']); ?>
                  </div>
                </td>
                <td><span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span></td>
                <td class="cell-date"><?php echo htmlspecialchars(date('M j, g:ia', strtotime($b['created_at']))); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ───── Bottom section: Recent complaints + Recent activities ───── -->
<div class="dash-columns">
  <?php if (!empty($recentComplaints)): ?>
  <div class="panel">
    <div class="panel-head">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Recent complaints
      </h2>
      <a href="<?php echo BASE_URL; ?>/admin/complaints.php" class="btn btn-ghost">View all</a>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Subject</th>
            <th>Tenant</th>
            <th>Status</th>
            <th>Filed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentComplaints as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['subject'] ?? 'No subject'); ?></td>
              <td><?php echo htmlspecialchars($c['tenant_name']); ?></td>
              <td><span class="status-pill status-<?php echo htmlspecialchars($c['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
              <td class="cell-date"><?php echo htmlspecialchars(date('M j, g:ia', strtotime($c['created_at']))); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Platform at a glance -->
  <div class="panel">
    <div class="panel-head">
      <h2>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px; margin-right:6px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Platform at a glance
      </h2>
    </div>
    <div class="glance-grid">
      <div class="glance-item">
        <div class="glance-item-label">This month</div>
        <div class="glance-item-stats">
          <div class="glance-stat">
            <span class="glance-stat-value"><?php echo $bookingsThisMonth; ?></span>
            <span class="glance-stat-desc">bookings</span>
          </div>
          <div class="glance-stat">
            <span class="glance-stat-value"><?php echo $complaintsThisMonth; ?></span>
            <span class="glance-stat-desc">complaints</span>
          </div>
          <div class="glance-stat">
            <span class="glance-stat-value"><?php echo $newThisWeek; ?></span>
            <span class="glance-stat-desc">new this week</span>
          </div>
        </div>
      </div>
      <div class="glance-item">
        <div class="glance-item-label">Reviews</div>
        <div class="glance-item-stats">
          <div class="glance-stat">
            <span class="glance-stat-value"><?php echo $totalReviews; ?></span>
            <span class="glance-stat-desc">total reviews</span>
          </div>
          <div class="glance-stat">
            <span class="glance-stat-value"><?php echo $avgRating !== false ? $avgRating : '—'; ?></span>
            <span class="glance-stat-desc">avg rating ⭐</span>
          </div>
        </div>
      </div>
      <?php if (!empty($recentUsers)): ?>
      <div class="glance-item">
        <div class="glance-item-label">Newest members</div>
        <div class="glance-users">
          <?php foreach ($recentUsers as $ru): ?>
            <div class="glance-user">
              <span class="cell-avatar cell-avatar--xs"><?php echo strtoupper(substr($ru['full_name'], 0, 1)); ?></span>
              <div>
                <div class="glance-user-name"><?php echo htmlspecialchars($ru['full_name']); ?></div>
                <div class="glance-user-meta"><?php echo ucfirst($ru['role']); ?> · <?php echo date('M j', strtotime($ru['created_at'])); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
