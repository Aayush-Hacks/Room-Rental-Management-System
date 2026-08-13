<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';

// Gather system-wide stats
$totalUsers       = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLandlords   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord'")->fetchColumn();
$totalTenants     = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tenant'")->fetchColumn();
$totalAdmins      = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$totalRooms       = (int) $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$availableRooms   = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'available'")->fetchColumn();
$bookedRooms      = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'booked'")->fetchColumn();
$maintRooms       = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'maintenance'")->fetchColumn();

$totalBookings    = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingBookings  = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$approvedBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$rejectedBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'rejected'")->fetchColumn();
$cancelledBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

$totalComplaints  = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$openComplaints   = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'open'")->fetchColumn();
$progressComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'in_progress'")->fetchColumn();
$resolvedComplaints = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'resolved'")->fetchColumn();

$totalReviews     = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$avgRating        = $pdo->query("SELECT ROUND(AVG(rating), 2) FROM reviews")->fetchColumn();

// Room type breakdown
$roomTypes = $pdo->query(
    "SELECT room_type, COUNT(*) AS cnt FROM rooms GROUP BY room_type ORDER BY cnt DESC"
)->fetchAll();

// Monthly registrations (last 6 months)
$monthlyReg = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS cnt
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month
     ORDER BY month ASC"
)->fetchAll();

$pageTitle = 'Reports';
$pageSubtitle = 'System-wide analytics and summary.';
$activeNav = 'reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total users</div>
        <div class="stat-value"><?php echo $totalUsers; ?></div>
        <div class="stat-hint"><?php echo $totalAdmins; ?> admin<?php echo $totalAdmins !== 1 ? 's' : ''; ?>, <?php echo $totalLandlords; ?> landlord<?php echo $totalLandlords !== 1 ? 's' : ''; ?>, <?php echo $totalTenants; ?> tenant<?php echo $totalTenants !== 1 ? 's' : ''; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total rooms</div>
        <div class="stat-value"><?php echo $totalRooms; ?></div>
        <div class="stat-hint"><?php echo $availableRooms; ?> available, <?php echo $bookedRooms; ?> booked, <?php echo $maintRooms; ?> maintenance</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bookings</div>
        <div class="stat-value"><?php echo $totalBookings; ?></div>
        <div class="stat-hint"><?php echo $pendingBookings; ?> pending, <?php echo $approvedBookings; ?> approved, <?php echo $rejectedBookings; ?> rejected, <?php echo $cancelledBookings; ?> cancelled</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Complaints</div>
        <div class="stat-value"><?php echo $totalComplaints; ?></div>
        <div class="stat-hint"><?php echo $openComplaints; ?> open, <?php echo $progressComplaints; ?> in progress, <?php echo $resolvedComplaints; ?> resolved</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Reviews</div>
        <div class="stat-value"><?php echo $totalReviews; ?></div>
        <div class="stat-hint">Avg rating: <?php echo $avgRating !== false ? $avgRating : '—'; ?> / 5</div>
    </div>
</div>

<div class="dash-columns" style="margin-bottom:28px;">
    <!-- Room type breakdown -->
    <div class="panel" style="margin-bottom:0;">
        <div class="panel-head">
            <h2>Rooms by type</h2>
        </div>
        <?php if (empty($roomTypes)): ?>
            <div class="empty-state"><p>No rooms listed yet.</p></div>
        <?php else: ?>
            <div style="padding:18px 22px;">
                <?php foreach ($roomTypes as $rt): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--line);">
                        <span style="font-weight:500;"><?php echo htmlspecialchars(ucfirst($rt['room_type'])); ?></span>
                        <span style="font-family:var(--font-mono); font-weight:600;"><?php echo (int) $rt['cnt']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Monthly registrations -->
    <div class="panel" style="margin-bottom:0;">
        <div class="panel-head">
            <h2>Registrations (6 months)</h2>
        </div>
        <?php if (empty($monthlyReg)): ?>
            <div class="empty-state"><p>No data available.</p></div>
        <?php else: ?>
            <div style="padding:18px 22px;">
                <?php foreach ($monthlyReg as $mr): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--line);">
                        <span style="font-size:0.88rem;"><?php echo htmlspecialchars($mr['month']); ?></span>
                        <span style="font-family:var(--font-mono); font-weight:600; font-size:0.95rem;">+<?php echo (int) $mr['cnt']; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Platform summary</h2>
    </div>
    <div style="padding:4px 22px 18px;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px;">
            <div style="padding:14px 0;">
                <div style="font-family:var(--font-mono); font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--slate-light); margin-bottom:4px;">Bookings approved</div>
                <div style="font-size:1.5rem; font-weight:600; color:var(--success);"><?php echo $totalBookings > 0 ? round(($approvedBookings / $totalBookings) * 100) . '%' : '—'; ?></div>
            </div>
            <div style="padding:14px 0;">
                <div style="font-family:var(--font-mono); font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--slate-light); margin-bottom:4px;">Complaints resolved</div>
                <div style="font-size:1.5rem; font-weight:600; color:var(--success);"><?php echo $totalComplaints > 0 ? round(($resolvedComplaints / $totalComplaints) * 100) . '%' : '—'; ?></div>
            </div>
            <div style="padding:14px 0;">
                <div style="font-family:var(--font-mono); font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--slate-light); margin-bottom:4px;">Rooms available rate</div>
                <div style="font-size:1.5rem; font-weight:600; color:var(--ink);"><?php echo $totalRooms > 0 ? round(($availableRooms / $totalRooms) * 100) . '%' : '—'; ?></div>
            </div>
            <div style="padding:14px 0;">
                <div style="font-family:var(--font-mono); font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--slate-light); margin-bottom:4px;">Pending landlord approvals</div>
                <div style="font-size:1.5rem; font-weight:600; <?php echo $totalLandlords > 0 ? 'color:var(--brick);' : ''; ?>"><?php echo $totalLandlords > 0 ? round((($totalLandlords - (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord' AND verification_status = 'approved'")->fetchColumn()) / $totalLandlords) * 100) . '%' : '—'; ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
