<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';

$tenantId = current_user_id();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    validate_csrf();
    $roomId = (int) ($_POST['room_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($roomId > 0 && !empty($description)) {
        $stmt = $pdo->prepare(
            "INSERT INTO complaints (tenant_id, room_id, subject, description) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$tenantId, $roomId, $subject, $description]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Complaint filed successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fill in all required fields.'];
    }
    header('Location: ' . BASE_URL . '/tenant/complaints.php');
    exit;
}

// Get tenant's bookings for the form dropdown
$bookings = $pdo->prepare(
    "SELECT b.room_id, r.title, r.location
     FROM bookings b
     JOIN rooms r ON r.room_id = b.room_id
     WHERE b.tenant_id = ? AND b.status = 'approved'
     ORDER BY b.created_at DESC"
);
$bookings->execute([$tenantId]);
$myRooms = $bookings->fetchAll();

// Get existing complaints
$complaints = $pdo->prepare(
    "SELECT c.complaint_id, c.subject, c.description, c.status, c.created_at, r.title AS room_title
     FROM complaints c
     JOIN rooms r ON r.room_id = c.room_id
     WHERE c.tenant_id = ?
     ORDER BY c.created_at DESC"
);
$complaints->execute([$tenantId]);
$myComplaints = $complaints->fetchAll();

$pageTitle = 'Complaints';
$pageSubtitle = 'Report issues with your booked rooms.';
$activeNav = 'complaints';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="panel" style="max-width: 640px;">
    <div class="panel-head">
        <h2>File a complaint</h2>
    </div>
    <form method="post" style="padding:22px;">
        <div class="form-group">
            <label for="room_id">Room</label>
            <select id="room_id" name="room_id" style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--paper-soft);" required>
                <option value="">Select a room...</option>
                <?php foreach ($myRooms as $r): ?>
                    <option value="<?php echo $r['room_id']; ?>"><?php echo htmlspecialchars($r['title']) . ' — ' . htmlspecialchars($r['location']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Brief subject line" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Describe the issue in detail..." required></textarea>
        </div>
        <?php echo csrf_field(); ?>
        <button type="submit" name="submit_complaint" class="btn btn-primary">Submit complaint</button>
    </form>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Your complaints (<?php echo count($myComplaints); ?>)</h2>
    </div>

    <?php if (empty($myComplaints)): ?>
        <div class="empty-state">
            <strong>No complaints filed</strong>
            <p>If you have an issue with a room, you can file a complaint above.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Filed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myComplaints as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['room_title']); ?></td>
                        <td><?php echo htmlspecialchars($c['subject'] ?? 'No subject'); ?></td>
                        <td><span class="status-pill status-<?php echo htmlspecialchars($c['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
