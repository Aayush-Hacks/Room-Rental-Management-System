<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$tenantId = current_user_id();

// Handle booking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'], $_POST['room_id'])) {
    validate_csrf();

    $roomId = (int) $_POST['room_id'];
    $moveInDate = $_POST['move_in_date'] ?? date('Y-m-d', strtotime('+7 days'));

    try {
        $pdo->beginTransaction();

        // Check if room is still available (lock row to prevent race condition)
        $stmt = $pdo->prepare("SELECT availability_status FROM rooms WHERE room_id = ? FOR UPDATE");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if (!$room || $room['availability_status'] !== 'available') {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'This room is no longer available.'];
            header('Location: ' . BASE_URL . '/tenant/bookings.php');
            exit;
        }

        // Check if tenant already has a pending/approved booking for this room
        $stmt = $pdo->prepare(
            "SELECT booking_id FROM bookings WHERE tenant_id = ? AND room_id = ? AND status IN ('pending', 'approved')"
        );
        $stmt->execute([$tenantId, $roomId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'You already have a booking request for this room.'];
            header('Location: ' . BASE_URL . '/tenant/bookings.php');
            exit;
        }

        // Create the booking
        $stmt = $pdo->prepare(
            "INSERT INTO bookings (tenant_id, room_id, move_in_date, status) VALUES (?, ?, ?, 'pending')"
        );
        $stmt->execute([$tenantId, $roomId, $moveInDate]);

        $pdo->commit();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Booking request sent!'];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Booking error: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred. Please try again.'];
    }
    header('Location: ' . BASE_URL . '/tenant/bookings.php');
    exit;
}

// Handle cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'], $_POST['booking_id'])) {
    validate_csrf();
    $cancelBookingId = (int) $_POST['booking_id'];
    $cancelReason = trim($_POST['cancellation_reason'] ?? '');

    // Cancellation reason is required (min 20 characters)
    if (mb_strlen($cancelReason) < 20) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please provide a reason for cancellation (at least 20 characters).'];
        header('Location: ' . BASE_URL . '/tenant/bookings.php');
        exit;
    }

    // Check the booking's current status before cancelling
    $stmt = $pdo->prepare(
        "SELECT b.status, b.room_id FROM bookings b WHERE b.booking_id = ? AND b.tenant_id = ?"
    );
    $stmt->execute([$cancelBookingId, $tenantId]);
    $cancelBooking = $stmt->fetch();

    if (!$cancelBooking || !in_array($cancelBooking['status'], ['pending', 'approved'], true)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not cancel this booking. It may already be cancelled or rejected.'];
        header('Location: ' . BASE_URL . '/tenant/bookings.php');
        exit;
    }

    $wasApproved = $cancelBooking['status'] === 'approved';

    // Cancel the booking and store the tenant's reason (if the column exists)
    if (bookings_has_cancel_reason($pdo)) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ? WHERE booking_id = ?");
        $stmt->execute([$cancelReason, $cancelBookingId]);
    } else {
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?");
        $stmt->execute([$cancelBookingId]);
    }

    // If the booking was approved, restore the room to available
    if ($wasApproved) {
        $stmt = $pdo->prepare("UPDATE rooms SET availability_status = 'available' WHERE room_id = ?");
        $stmt->execute([$cancelBooking['room_id']]);
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Booking cancelled successfully.'];
    header('Location: ' . BASE_URL . '/tenant/bookings.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$hasCancelReason = bookings_has_cancel_reason($pdo);
$cancelReasonSelect = $hasCancelReason ? 'b.cancellation_reason,' : '';

$bookings = $pdo->prepare(
    "SELECT b.booking_id, b.status, b.move_in_date, b.created_at, $cancelReasonSelect
            r.title AS room_title, r.location, r.rent_amount, r.room_id,
            u.full_name AS landlord_name, u.citizenship_status AS landlord_citizenship
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     JOIN users u ON u.user_id = r.landlord_id
     WHERE b.tenant_id = ?
     ORDER BY b.created_at DESC
     LIMIT 50"
);
$bookings->execute([$tenantId]);
$myBookings = $bookings->fetchAll();

$pageTitle = 'My bookings';
$pageSubtitle = 'View and manage your booking requests.';
$activeNav = 'bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Your bookings (<?php echo count($myBookings); ?>)</h2>
        <a href="search.php" class="btn btn-primary" style="padding:10px 20px; font-size:0.85rem;">Search rooms</a>
    </div>

    <?php if (empty($myBookings)): ?>
        <div class="empty-state">
            <strong>No bookings yet</strong>
            <p>Search for available rooms to book.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Landlord</th>
                    <th>Location</th>
                    <th>Rent</th>
                    <th>Move-in date</th>
                    <th>Status</th>
                    <th>Booked</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myBookings as $b): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['room_title']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($b['landlord_name']); ?>
                            <?php echo render_citizenship_badge($b['landlord_citizenship'] ?? null); ?>
                        </td>
                        <td><?php echo htmlspecialchars($b['location']); ?></td>
                        <td>Rs <?php echo number_format((float) $b['rent_amount']); ?></td>
                        <td><?php echo htmlspecialchars($b['move_in_date']); ?></td>
                        <td>
                            <span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span>
                            <?php if ($hasCancelReason && $b['status'] === 'cancelled' && !empty($b['cancellation_reason'])): ?>
                                <div style="font-size:0.78rem; color:var(--slate); margin-top:4px; max-width:220px;">"<?php echo htmlspecialchars($b['cancellation_reason']); ?>"</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($b['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($myBookings)): ?>
    <div class="panel">
        <div class="panel-head">
            <h2>Cancel a booking</h2>
        </div>
        <div style="padding:18px 22px;">
            <p style="font-size:0.9rem; color:var(--slate); margin-bottom:14px;">Cancel a pending or approved booking if you no longer need the room. Please tell us why you are cancelling.</p>
            <form method="post" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                <div class="form-group">
                    <label for="cancellation_reason">Reason for cancellation <span style="color:var(--brick);">*</span></label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3"
                              minlength="20" required
                              placeholder="e.g. Found a cheaper room closer to college, so I no longer need this one..."
                              style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); resize:vertical; font-family:inherit;"></textarea>
                    <div class="form-help" style="margin-top:6px;">Minimum 20 characters.</div>
                </div>
                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <select name="booking_id" style="flex:1; min-width:200px; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);">
                        <option value="">Select a booking...</option>
                        <?php foreach ($myBookings as $b): ?>
                            <?php if ($b['status'] === 'pending' || $b['status'] === 'approved'): ?>
                                <option value="<?php echo (int) $b['booking_id']; ?>">
                                    <?php echo htmlspecialchars($b['room_title']); ?> — <?php echo htmlspecialchars($b['status']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="cancel_booking" value="1" class="btn btn-ghost" style="padding:12px 20px;">Cancel booking</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
