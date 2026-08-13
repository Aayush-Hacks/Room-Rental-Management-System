<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

$landlordId = current_user_id();

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    validate_csrf();
    $deleteRoomId = (int) ($_POST['room_id'] ?? 0);

    // Wrap deletion in a transaction to keep DB and disk in sync
    try {
        $pdo->beginTransaction();

        // Delete images from disk first
        $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ?");
        $stmt->execute([$deleteRoomId]);
        foreach ($stmt->fetchAll() as $img) {
            $path = __DIR__ . '/../' . $img['image_path'];
            if (file_exists($path)) unlink($path);
        }

        // Delete room — CASCADE handles all related DB records
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ? AND landlord_id = ?");
        $stmt->execute([$deleteRoomId, $landlordId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Room deleted successfully.'];
        } else {
            $pdo->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not delete room.'];
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Room deletion error: ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred while deleting the room.'];
    }
    header('Location: ' . BASE_URL . '/landlord/rooms.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$rooms = $pdo->prepare(
    "SELECT r.room_id, r.title, r.location, r.rent_amount, r.availability_status, r.room_type, r.created_at,
            (SELECT COUNT(*) FROM bookings WHERE room_id = r.room_id AND status = 'pending') AS pending_bookings
     FROM rooms r
     WHERE r.landlord_id = ?
     ORDER BY r.created_at DESC"
);
$rooms->execute([$landlordId]);
$myRooms = $rooms->fetchAll();

$pageTitle = 'My rooms';
$pageSubtitle = 'Manage your room listings.';
$activeNav = 'rooms';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Your rooms (<?php echo count($myRooms); ?>)</h2>
        <a href="add_room.php" class="btn btn-primary" style="padding:10px 20px; font-size:0.85rem;">+ Add a room</a>
    </div>

    <?php if (empty($myRooms)): ?>
        <div class="empty-state">
            <strong>You haven't listed any rooms yet</strong>
            <p>Click "Add a room" to create your first listing.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Rent</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Pending bookings</th>
                    <th>Listed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myRooms as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['title']); ?></td>
                        <td><?php echo htmlspecialchars($r['location']); ?></td>
                        <td>Rs <?php echo number_format((float) $r['rent_amount']); ?></td>
                        <td><?php echo ucfirst($r['room_type']); ?></td>
                        <td><span class="status-pill status-<?php echo htmlspecialchars($r['availability_status']); ?>"><?php echo ucfirst($r['availability_status']); ?></span></td>
                        <td><?php echo $r['pending_bookings']; ?></td>
                        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <a href="edit_room.php?room_id=<?php echo (int) $r['room_id']; ?>" class="btn btn-ghost" style="padding:6px 12px; font-size:0.82rem;">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this room? This cannot be undone and will remove all associated bookings, reviews, and complaints.');">
                                    <input type="hidden" name="room_id" value="<?php echo (int) $r['room_id']; ?>">
                                    <input type="hidden" name="delete_room" value="1">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:0.82rem; color:var(--brick);">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
