<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$landlordId = current_user_id();

// Handle booking approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['action'])) {
    validate_csrf();
    $bookingId = (int) $_POST['booking_id'];
    $action = $_POST['action'];

    if (in_array($action, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare(
            "UPDATE bookings b
             JOIN rooms r ON r.room_id = b.room_id
             SET b.status = ?
             WHERE b.booking_id = ? AND r.landlord_id = ? AND b.status = 'pending'"
        );
        $stmt->execute([$action, $bookingId, $landlordId]);

        if ($action === 'approved') {
            // Mark the room as booked
            $pdo->prepare(
                "UPDATE rooms r
                 JOIN bookings b ON b.room_id = r.room_id
                 SET r.availability_status = 'booked'
                 WHERE b.booking_id = ?"
            )->execute([$bookingId]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Booking ' . $action . '.'];
    }
    header('Location: ' . BASE_URL . '/landlord/bookings.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$hasCancelReason = bookings_has_cancel_reason($pdo);
$cancelReasonSelect = $hasCancelReason ? 'b.cancellation_reason,' : '';

$bookings = $pdo->prepare(
    "SELECT b.booking_id, b.status, b.move_in_date, b.created_at, $cancelReasonSelect
            r.title AS room_title, u.full_name AS tenant_name, u.citizenship_status AS tenant_citizenship
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     JOIN users u ON u.user_id = b.tenant_id
     WHERE r.landlord_id = ?
     ORDER BY b.created_at DESC
     LIMIT 50"
);
$bookings->execute([$landlordId]);
$myBookings = $bookings->fetchAll();

// ---- Fetch ALL system-wide bookings (read-only overview) ----
$allBookings = $pdo->query(
    "SELECT b.booking_id, b.status, b.move_in_date, b.created_at,
            r.title AS room_title, u.full_name AS tenant_name, u.citizenship_status AS tenant_citizenship,
            l.full_name AS landlord_name, l.citizenship_status AS landlord_citizenship
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     JOIN users u ON u.user_id = b.tenant_id
     JOIN users l ON l.user_id = r.landlord_id
     ORDER BY b.created_at DESC
     LIMIT 20"
)->fetchAll();

// Stats
$pendingCount = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.room_id = b.room_id WHERE r.landlord_id = ? AND b.status = 'pending'");
$pendingCount->execute([$landlordId]);
$pendingCount = (int) $pendingCount->fetchColumn();

$approvedCount = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN rooms r ON r.room_id = b.room_id WHERE r.landlord_id = ? AND b.status = 'approved'");
$approvedCount->execute([$landlordId]);
$approvedCount = (int) $approvedCount->fetchColumn();

$pageTitle = 'Booking requests';
$pageSubtitle = 'Review your room bookings and see system-wide activity.';
$activeNav = 'bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<!-- Stats cards -->
<div class="stat-grid" style="margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value accent"><?php echo $pendingCount; ?></div>
        <div class="stat-hint">Awaiting your response</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value"><?php echo $approvedCount; ?></div>
        <div class="stat-hint">Confirmed bookings</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total yours</div>
        <div class="stat-value"><?php echo count($myBookings); ?></div>
        <div class="stat-hint">All bookings for your rooms</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">System-wide</div>
        <div class="stat-value"><?php echo count($allBookings); ?></div>
        <div class="stat-hint">Recent bookings across all landlords</div>
    </div>
</div>

<!-- My bookings -->
<div class="panel">
    <div class="panel-head">
        <h2>My room bookings (<?php echo count($myBookings); ?>)</h2>
    </div>

    <?php if (empty($myBookings)): ?>
        <div class="empty-state">
            <strong>No bookings for your rooms yet</strong>
            <p>When tenants request to book your rooms, they'll show up here.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Tenant</th>
                    <th>Move-in date</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myBookings as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['room_title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($b['tenant_name']); ?>
                            <?php echo render_citizenship_badge($b['tenant_citizenship'] ?? null); ?>
                        </td>
                        <td><?php echo htmlspecialchars($b['move_in_date']); ?></td>
                        <td>
                            <span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span>
                            <?php if ($hasCancelReason && $b['status'] === 'cancelled' && !empty($b['cancellation_reason'])): ?>
                                <div style="font-size:0.78rem; color:var(--slate); margin-top:4px; max-width:220px;">"<?php echo htmlspecialchars($b['cancellation_reason']); ?>"</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($b['created_at']); ?></td>
                        <td>
                            <?php if ($b['status'] === 'pending'): ?>
                                <div style="display:flex; gap:6px;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <input type="hidden" name="action" value="approved">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-primary" style="padding:6px 14px; font-size:0.82rem;">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                        <input type="hidden" name="action" value="rejected">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="btn btn-ghost" style="padding:6px 14px; font-size:0.82rem;">Reject</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- All system bookings (read-only overview) -->
<div class="panel">
    <div class="panel-head">
        <h2>📋 Recent activity — all landlords</h2>
    </div>

    <?php if (empty($allBookings)): ?>
        <div class="empty-state">
            <strong>No system-wide bookings yet</strong>
            <p>Booking activity across the platform will appear here.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Tenant</th>
                    <th>Landlord</th>
                    <th>Move-in</th>
                    <th>Status</th>
                    <th>Requested</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allBookings as $b): ?>
                    <tr<?php echo $b['landlord_name'] === $_SESSION['full_name'] ? ' style="background:rgba(128,30,40,0.04);"' : ''; ?>>
                        <td><?php echo htmlspecialchars($b['room_title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($b['tenant_name']); ?>
                            <?php echo render_citizenship_badge($b['tenant_citizenship'] ?? null); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($b['landlord_name']); ?>
                            <?php echo render_citizenship_badge($b['landlord_citizenship'] ?? null); ?>
                        </td>
                        <td><?php echo htmlspecialchars($b['move_in_date']); ?></td>
                        <td><span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($b['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="padding:8px 22px 12px; font-size:0.8rem; color:var(--slate);">
            💡 Rows highlighted in light red are your own bookings.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
