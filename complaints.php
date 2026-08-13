<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle complaint status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint_status'])) {
    validate_csrf();
    $complaintId = (int) ($_POST['complaint_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if (in_array($newStatus, ['in_progress', 'resolved'], true) && $complaintId > 0) {
        $stmt = $pdo->prepare("UPDATE complaints SET status = ? WHERE complaint_id = ?");
        $stmt->execute([$newStatus, $complaintId]);

        $labels = ['in_progress' => 'In progress', 'resolved' => 'Resolved'];
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => "Complaint #{$complaintId} marked as {$labels[$newStatus]}."
        ];
    } else {
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Invalid status update request.'
        ];
    }
    header('Location: ' . BASE_URL . '/admin/complaints.php');
    exit;
}

// Pagination
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Search
$search = trim($_GET['search'] ?? '');

// Status filter
$statusFilter = $_GET['status'] ?? '';
$allowedStatuses = ['open', 'in_progress', 'resolved'];

// Build WHERE clause
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(c.subject LIKE ? OR c.description LIKE ? OR u.full_name LIKE ? OR r.title LIKE ?)";
    $like  = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Total count for pagination
$countSql = "SELECT COUNT(*) FROM complaints c
             JOIN users u ON u.user_id = c.tenant_id
             JOIN rooms r ON r.room_id = c.room_id
             $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalComplaints = (int) $countStmt->fetchColumn();

// Fetch current page
$sql = "SELECT c.complaint_id, c.subject, c.description, c.status, c.created_at,
               u.full_name AS tenant_name, r.title AS room_title
        FROM complaints c
        JOIN users u ON u.user_id = c.tenant_id
        JOIN rooms r ON r.room_id = c.room_id
        $whereClause
        ORDER BY FIELD(c.status, 'open', 'in_progress', 'resolved'), c.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$complaints = $stmt->fetchAll();

// Counts for stat cards
$openCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'open'")->fetchColumn();
$progressCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'in_progress'")->fetchColumn();
$resolvedCount = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'resolved'")->fetchColumn();

$pageTitle = 'Complaints';
$pageSubtitle = 'Review and manage tenant complaints.';
$activeNav = 'complaints';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> mb-22">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="stat-grid mb-22">
    <div class="stat-card">
        <div class="stat-label">Open</div>
        <div class="stat-value accent"><?php echo $openCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In progress</div>
        <div class="stat-value"><?php echo $progressCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Resolved</div>
        <div class="stat-value"><?php echo $resolvedCount; ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Complaints (<?php echo $totalComplaints; ?>)</h2>
        <div class="panel-head-actions">
            <form class="panel-filter" method="get">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <label for="status-filter">Status</label>
                <select name="status" id="status-filter" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In progress</option>
                    <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
                <?php if ($statusFilter !== ''): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/complaints.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="filter-clear">Clear</a>
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
            <label for="search">Search complaints</label>
            <input type="text" id="search" name="search" placeholder="Search by subject, description, tenant, or room..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/complaints.php<?php echo $statusFilter !== '' ? '?status=' . urlencode($statusFilter) : ''; ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== '' && empty($complaints)): ?>
        <div class="empty-state">
            <strong>No complaints match "<?php echo htmlspecialchars($search); ?>"</strong>
            <p>Try a different search term or status filter.</p>
        </div>
    <?php elseif (empty($complaints) && $search === '' && $statusFilter === ''): ?>
        <div class="empty-state">
            <strong>No complaints filed</strong>
            <p>Tenant complaints will appear here for review.</p>
        </div>
    <?php elseif (empty($complaints)): ?>
        <div class="empty-state">
            <strong>No complaints found</strong>
            <p>Try adjusting your filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Description</th>
                        <th>Tenant</th>
                        <th>Room</th>
                        <th>Status</th>
                        <th>Filed</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['subject'] ?? 'No subject'); ?></td>
                            <td style="max-width:220px;">
                                <span title="<?php echo htmlspecialchars($c['description'] ?? ''); ?>"
                                      style="cursor:help; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?php
                                    $desc = $c['description'] ?? '';
                                    echo htmlspecialchars(mb_substr($desc, 0, 60)) . (mb_strlen($desc) > 60 ? '...' : '');
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($c['tenant_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['room_title']); ?></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($c['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                            <td class="cell-actions">
                                <?php if ($c['status'] === 'open'): ?>
                                    <button type="button" class="btn-sm btn-sm-approve"
                                            onclick="confirmAction('Mark complaint #<?php echo (int) $c['complaint_id']; ?> as <strong>in progress</strong>?', 'complaint_<?php echo (int) $c['complaint_id']; ?>_progress')">
                                        Acknowledge
                                    </button>
                                    <form method="post" id="complaint_<?php echo (int) $c['complaint_id']; ?>_progress" style="display:none;">
                                        <input type="hidden" name="complaint_id" value="<?php echo (int) $c['complaint_id']; ?>">
                                        <input type="hidden" name="new_status" value="in_progress">
                                        <input type="hidden" name="update_complaint_status" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                <?php elseif ($c['status'] === 'in_progress'): ?>
                                    <button type="button" class="btn-sm btn-sm-approve"
                                            onclick="confirmAction('Mark complaint #<?php echo (int) $c['complaint_id']; ?> as <strong>resolved</strong>?', 'complaint_<?php echo (int) $c['complaint_id']; ?>_resolve')">
                                        Resolve
                                    </button>
                                    <form method="post" id="complaint_<?php echo (int) $c['complaint_id']; ?>_resolve" style="display:none;">
                                        <input type="hidden" name="complaint_id" value="<?php echo (int) $c['complaint_id']; ?>">
                                        <input type="hidden" name="new_status" value="resolved">
                                        <input type="hidden" name="update_complaint_status" value="1">
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

        <?php render_pagination($totalComplaints, $page, BASE_URL . '/admin/complaints.php', $perPage);
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

// Close modal on overlay click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
