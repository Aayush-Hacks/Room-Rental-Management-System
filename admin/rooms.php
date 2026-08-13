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

// Handle delete room with transaction and image cleanup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    validate_csrf();
    $roomId = (int) ($_POST['room_id'] ?? 0);
    if ($roomId > 0) {
        try {
            $pdo->beginTransaction();

            // Delete images from disk
            $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ?");
            $stmt->execute([$roomId]);
            foreach ($stmt->fetchAll() as $img) {
                $path = __DIR__ . '/../' . $img['image_path'];
                if (file_exists($path)) unlink($path);
            }

            // Delete room — CASCADE handles all related DB records
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
            $stmt->execute([$roomId]);

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Room deleted successfully.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Admin room deletion error: ' . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'An error occurred while deleting the room.'];
        }
    }
    header('Location: ' . BASE_URL . '/admin/rooms.php');
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

// Status filter
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['available', 'booked', 'maintenance'];

// Type filter
$typeFilter = $_GET['type'] ?? '';
$allowedTypes = ['single', 'shared', 'apartment', 'studio'];

// Build WHERE clause
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(r.title LIKE ? OR r.location LIKE ? OR u.full_name LIKE ?)";
    $like   = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "r.availability_status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== '' && in_array($typeFilter, $allowedTypes, true)) {
    $where[] = "r.room_type = ?";
    $params[] = $typeFilter;
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Total count
$countSql = "SELECT COUNT(*) FROM rooms r JOIN users u ON u.user_id = r.landlord_id $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRooms = (int) $countStmt->fetchColumn();

// Fetch current page
$sql = "SELECT r.room_id, r.title, r.location, r.rent_amount, r.rent_type, r.room_type, r.availability_status, r.created_at,
               u.full_name AS landlord_name
        FROM rooms r
        JOIN users u ON u.user_id = r.landlord_id
        $whereClause
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$rooms = $stmt->fetchAll();

// Counts for stat cards
$availCount = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'available'")->fetchColumn();
$bookedCount = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'booked'")->fetchColumn();
$maintCount = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'maintenance'")->fetchColumn();

$pageTitle = 'Room listings';
$pageSubtitle = 'All rooms currently listed on the platform.';
$activeNav = 'rooms';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> mb-22">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="stat-grid mb-22">
    <div class="stat-card">
        <div class="stat-label">Available</div>
        <div class="stat-value stat-value--success"><?php echo $availCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Booked</div>
        <div class="stat-value stat-value--warn"><?php echo $bookedCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Maintenance</div>
        <div class="stat-value stat-value--accent"><?php echo $maintCount; ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>All rooms (<?php echo $totalRooms; ?>)</h2>
        <div class="panel-head-actions">
            <form class="panel-filter" method="get">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <label for="status-filter">Status</label>
                <select name="status" id="status-filter" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo $statusFilter === 'booked' ? 'selected' : ''; ?>>Booked</option>
                    <option value="maintenance" <?php echo $statusFilter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                </select>
                <label for="type-filter">Type</label>
                <select name="type" id="type-filter" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="single" <?php echo $typeFilter === 'single' ? 'selected' : ''; ?>>Single</option>
                    <option value="shared" <?php echo $typeFilter === 'shared' ? 'selected' : ''; ?>>Shared</option>
                    <option value="apartment" <?php echo $typeFilter === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
                    <option value="studio" <?php echo $typeFilter === 'studio' ? 'selected' : ''; ?>>Studio</option>
                </select>
                <?php if ($statusFilter !== '' || $typeFilter !== ''): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/rooms.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="filter-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Search bar -->
    <form class="search-bar panel-section" method="get">
        <?php if ($statusFilter !== ''): ?>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <?php endif; ?>
        <?php if ($typeFilter !== ''): ?>
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($typeFilter); ?>">
        <?php endif; ?>
        <div class="field">
            <label for="search">Search rooms</label>
            <input type="text" id="search" name="search" placeholder="Search by title, location, or landlord..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/rooms.php<?php
                $params = [];
                if ($statusFilter !== '') $params['status'] = $statusFilter;
                if ($typeFilter !== '') $params['type'] = $typeFilter;
                echo !empty($params) ? '?' . http_build_query($params) : '';
            ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== '' && empty($rooms)): ?>
        <div class="empty-state">
            <strong>No rooms match "<?php echo htmlspecialchars($search); ?>"</strong>
            <p>Try a different title, location, or landlord name.</p>
        </div>
    <?php elseif (empty($rooms) && $search === '' && $statusFilter === '' && $typeFilter === ''): ?>
        <div class="empty-state">
            <strong>No rooms listed yet</strong>
            <p>Rooms will appear here once landlords start adding them.</p>
        </div>
    <?php elseif (empty($rooms)): ?>
        <div class="empty-state">
            <strong>No rooms found</strong>
            <p>Try adjusting your filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Landlord</th>
                        <th>Location</th>
                        <th>Rent</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Listed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo htmlspecialchars($r['landlord_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['location']); ?></td>
                            <td>Rs <?php echo number_format((float) $r['rent_amount']); ?>/<?php echo $r['rent_type'] === 'weekly' ? 'wk' : 'mo'; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($r['room_type'])); ?></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($r['availability_status']); ?>"><?php echo ucfirst($r['availability_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                            <td class="cell-actions">
                                <button type="button" class="btn-sm btn-sm-danger"
                                        onclick="confirmAction('Delete room <strong><?php echo htmlspecialchars($r['title']); ?></strong>? This will also remove all associated bookings, reviews, and complaints.', 'delete_room_<?php echo (int) $r['room_id']; ?>')">
                                    Delete
                                </button>
                                <form method="post" id="delete_room_<?php echo (int) $r['room_id']; ?>" style="display:none;">
                                    <input type="hidden" name="room_id" value="<?php echo (int) $r['room_id']; ?>">
                                    <input type="hidden" name="delete_room" value="1">
                                    <?php echo csrf_field(); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalRooms, $page, BASE_URL . '/admin/rooms.php', $perPage);
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
