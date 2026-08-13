<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status'])) {
    validate_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if (in_array($newStatus, ['approved', 'rejected', 'cancelled'], true) && $bookingId > 0) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
        $stmt->execute([$newStatus, $bookingId]);

        // If approved, mark the room as booked
        if ($newStatus === 'approved') {
            $stmtRoom = $pdo->prepare("SELECT room_id FROM bookings WHERE booking_id = ?");
            $stmtRoom->execute([$bookingId]);
            $rid = $stmtRoom->fetchColumn();
            if ($rid) {
                $pdo->prepare("UPDATE rooms SET availability_status = 'booked' WHERE room_id = ?")
                    ->execute([$rid]);
            }
        }

        // If cancelled, free the room back to available
        if ($newStatus === 'cancelled') {
            $stmtRoom = $pdo->prepare("SELECT room_id FROM bookings WHERE booking_id = ?");
            $stmtRoom->execute([$bookingId]);
            $rid = $stmtRoom->fetchColumn();
            if ($rid) {
                $pdo->prepare("UPDATE rooms SET availability_status = 'available' WHERE room_id = ?")
                    ->execute([$rid]);
            }
        }

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Booking #{$bookingId} marked as {$newStatus}."
        ];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid status update.'];
    }
    header('Location: ' . BASE_URL . '/admin/bookings.php');
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

// Status filter
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled'];

// Build WHERE clause
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(r.title LIKE ? OR u.full_name LIKE ? OR l.full_name LIKE ?)";
    $like   = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "b.status = ?";
    $params[] = $statusFilter;
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Total count
$countSql = "SELECT COUNT(*) FROM bookings b
             JOIN rooms r ON r.room_id = b.room_id
             JOIN users u ON u.user_id = b.tenant_id
             JOIN users l ON l.user_id = r.landlord_id
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalBookings = (int) $countStmt->fetchColumn();

// Fetch current page
$hasCancelReason = bookings_has_cancel_reason($pdo);
$cancelReasonSelect = $hasCancelReason ? 'b.cancellation_reason,' : '';

$sql = "SELECT b.booking_id, b.status, b.move_in_date, b.created_at, $cancelReasonSelect
               r.title AS room_title, u.full_name AS tenant_name, l.full_name AS landlord_name
        FROM bookings b
        JOIN rooms r ON r.room_id = b.room_id
        JOIN users u ON u.user_id = b.tenant_id
        JOIN users l ON l.user_id = r.landlord_id
        $whereClause
        ORDER BY FIELD(b.status, 'pending', 'approved', 'rejected', 'cancelled'), b.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$bookings = $stmt->fetchAll();

// Counts for stat cards
$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved'")->fetchColumn();
$cancelledCount = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('rejected','cancelled')")->fetchColumn();

$pageTitle = 'All bookings';
$pageSubtitle = 'Complete booking history across all rooms.';
$activeNav = 'bookings';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> mb-22">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="stat-grid mb-22">
    <div class="stat-card">
        <div class="stat-label">Pending</div>
        <div class="stat-value stat-value--accent"><?php echo $pendingCount; ?></div>
        <div class="stat-hint">Awaiting action</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Approved</div>
        <div class="stat-value stat-value--success"><?php echo $approvedCount; ?></div>
        <div class="stat-hint">Confirmed bookings</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Rejected / Cancelled</div>
        <div class="stat-value"><?php echo $cancelledCount; ?></div>
        <div class="stat-hint">Inactive bookings</div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>All bookings (<?php echo $totalBookings; ?>)</h2>
        <div class="panel-head-actions">
            <form class="panel-filter" method="get">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <label for="status-filter">Status</label>
                <select name="status" id="status-filter" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <?php if ($statusFilter !== ''): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/bookings.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="filter-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Search bar -->
    <form class="search-bar panel-section" method="get">
        <?php if ($statusFilter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <?php endif; ?>
        <div class="field">
            <label for="search">Search bookings</label>
            <input type="text" id="search" name="search" placeholder="Search by room, tenant, or landlord..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/bookings.php<?php echo $statusFilter !== '' ? '?status=' . urlencode($statusFilter) : ''; ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== '' && empty($bookings)): ?>
        <div class="empty-state">
            <strong>No bookings match "<?php echo htmlspecialchars($search); ?>"</strong>
            <p>Try a different room, tenant, or landlord name.</p>
        </div>
    <?php elseif (empty($bookings) && $search === '' && $statusFilter === ''): ?>
        <div class="empty-state">
            <strong>No bookings yet</strong>
            <p>Booking requests will appear here once tenants start booking.</p>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="empty-state">
            <strong>No bookings found</strong>
            <p>Try adjusting your filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Tenant</th>
                        <th>Landlord</th>
                        <th>Move-in date</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['room_title']); ?></td>
                            <td><?php echo htmlspecialchars($b['tenant_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['landlord_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['move_in_date']); ?></td>
                            <td>
                                <span class="status-pill status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo ucfirst($b['status']); ?></span>
                                <?php if ($hasCancelReason && $b['status'] === 'cancelled' && !empty($b['cancellation_reason'])): ?>
                                    <div style="font-size:0.78rem; color:var(--slate); margin-top:4px; max-width:220px;">"<?php echo htmlspecialchars($b['cancellation_reason']); ?>"</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($b['created_at']); ?></td>
                            <td class="cell-actions">
                                <?php if ($b['status'] === 'pending'): ?>
                                    <button type="button" class="btn-sm btn-sm-approve"
                                            onclick="confirmAction('Approve booking <strong>#<?php echo (int) $b['booking_id']; ?></strong> for room \"<?php echo htmlspecialchars($b['room_title']); ?>\"?', 'approve_<?php echo (int) $b['booking_id']; ?>')">
                                        Approve
                                    </button>
                                    <button type="button" class="btn-sm btn-sm-danger"
                                            onclick="confirmAction('Reject booking <strong>#<?php echo (int) $b['booking_id']; ?></strong> for room \"<?php echo htmlspecialchars($b['room_title']); ?>\"?', 'reject_<?php echo (int) $b['booking_id']; ?>')">
                                        Reject
                                    </button>
                                    <form method="post" id="approve_<?php echo (int) $b['booking_id']; ?>" style="display:none;">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $b['booking_id']; ?>">
                                        <input type="hidden" name="new_status" value="approved">
                                        <input type="hidden" name="update_booking_status" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                    <form method="post" id="reject_<?php echo (int) $b['booking_id']; ?>" style="display:none;">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $b['booking_id']; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <input type="hidden" name="update_booking_status" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                <?php elseif ($b['status'] === 'approved'): ?>
                                    <button type="button" class="btn-sm btn-sm-danger"
                                            onclick="confirmAction('Cancel approved booking <strong>#<?php echo (int) $b['booking_id']; ?></strong>? This will free the room.', 'cancel_<?php echo (int) $b['booking_id']; ?>')">
                                        Cancel
                                    </button>
                                    <form method="post" id="cancel_<?php echo (int) $b['booking_id']; ?>" style="display:none;">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $b['booking_id']; ?>">
                                        <input type="hidden" name="new_status" value="cancelled">
                                        <input type="hidden" name="update_booking_status" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:0.82rem; color:var(--slate);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalBookings, $page, BASE_URL . '/admin/bookings.php', $perPage);
        ?>
    <?php endif; ?>
</div>

<!-- Confirmation modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3>Confirm action</h3>
        <p id="confirmMessage">Are you sure?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

<script>
function confirmAction(message, formId) {
    document.getElementById('confirmMessage').innerHTML = message;
    var modal = document.getElementById('confirmModal');
    modal.classList.add('is-open');
    document.getElementById('confirmBtn').onclick = function() {
        document.getElementById(formId).submit();
    };
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('is-open');
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
