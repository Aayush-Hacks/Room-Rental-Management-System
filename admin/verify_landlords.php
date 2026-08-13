<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Search
$search = trim($_GET['search'] ?? '');

// Count pending
$countWhere = "WHERE role = 'landlord' AND verification_status = 'pending'";
$countParams = [];
if ($search !== '') {
    $countWhere .= " AND (full_name LIKE ? OR email LIKE ?)";
    $like = '%' . $search . '%';
    $countParams = [$like, $like];
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $countWhere");
$countStmt->execute($countParams);
$totalPending = (int) $countStmt->fetchColumn();

// Pending landlords with pagination
$pendingSql = "SELECT user_id, full_name, email, phone, created_at
     FROM users
     WHERE role = 'landlord' AND verification_status = 'pending'";
$pendingParams = [];
if ($search !== '') {
    $pendingSql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $pendingParams = ['%' . $search . '%', '%' . $search . '%'];
}
$pendingSql .= " ORDER BY created_at ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($pendingSql);
$stmt->execute(array_merge($pendingParams, [$perPage, $offset]));
$pending = $stmt->fetchAll();

// Recent decided landlords
$decided = $pdo->query(
    "SELECT user_id, full_name, email, verification_status, updated_at
     FROM users
     WHERE role = 'landlord' AND verification_status != 'pending'
     ORDER BY updated_at DESC
     LIMIT 10"
)->fetchAll();

$pageTitle = 'Verify landlords';
$pageSubtitle = 'Approve or reject landlord accounts before they can list rooms.';
$activeNav = 'verify';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> mb-22">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-head">
        <h2>Pending verification (<?php echo $totalPending; ?>)</h2>
    </div>

    <!-- Search bar -->
    <form class="search-bar panel-section" method="get">
        <div class="field">
            <label for="search">Search pending landlords</label>
            <input type="text" id="search" name="search" placeholder="Search by name or email..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/verify_landlords.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== '' && empty($pending)): ?>
        <div class="empty-state">
            <strong>No pending landlords match "<?php echo htmlspecialchars($search); ?>"</strong>
            <p>Try a different name or email.</p>
        </div>
    <?php elseif (empty($pending)): ?>
        <div class="empty-state">
            <strong>No landlords waiting</strong>
            <p>New landlord registrations will appear here for review.</p>
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($l['email']); ?></td>
                            <td><?php echo htmlspecialchars($l['phone']); ?></td>
                            <td><?php echo htmlspecialchars($l['created_at']); ?></td>
                            <td class="cell-actions">
                                <form action="verify_landlord_process.php" method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $l['user_id']; ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="btn-sm btn-sm-approve">Approve</button>
                                </form>
                                <button type="button" class="btn-sm btn-sm-danger"
                                        onclick="confirmAction('Reject landlord application from <strong><?php echo htmlspecialchars($l['full_name']); ?></strong> (<?php echo htmlspecialchars($l['email']); ?>)?', 'reject_<?php echo (int) $l['user_id']; ?>')">
                                    Reject
                                </button>
                                <form action="verify_landlord_process.php" method="post" id="reject_<?php echo (int) $l['user_id']; ?>" style="display:none;">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $l['user_id']; ?>">
                                    <input type="hidden" name="decision" value="rejected">
                                    <?php echo csrf_field(); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalPending, $page, BASE_URL . '/admin/verify_landlords.php', $perPage);
        ?>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Recent decisions</h2>
    </div>

    <?php if (empty($decided)): ?>
        <div class="empty-state">
            <strong>No decisions yet</strong>
            <p>Approved and rejected landlords will show up here.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Decision</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($decided as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($l['email']); ?></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($l['verification_status']); ?>"><?php echo ucfirst($l['verification_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($l['updated_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
