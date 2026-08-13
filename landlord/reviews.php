<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');
require_once __DIR__ . '/../includes/db.php';

$landlordId = current_user_id();

$reviews = $pdo->prepare(
    "SELECT rev.review_id, rev.rating, rev.comment, rev.created_at,
            r.title AS room_title, u.full_name AS tenant_name
     FROM reviews rev
     JOIN rooms r ON r.room_id = rev.room_id
     JOIN users u ON u.user_id = rev.tenant_id
     WHERE r.landlord_id = ?
     ORDER BY rev.created_at DESC
     LIMIT 50"
);
$reviews->execute([$landlordId]);
$myReviews = $reviews->fetchAll();

$pageTitle = 'Reviews';
$pageSubtitle = 'What tenants are saying about your rooms.';
$activeNav = 'reviews';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Reviews (<?php echo count($myReviews); ?>)</h2>
    </div>

    <?php if (empty($myReviews)): ?>
        <div class="empty-state">
            <strong>No reviews yet</strong>
            <p>Tenant reviews will appear here once tenants book and leave feedback.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Room</th>
                    <th>Tenant</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myReviews as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['room_title']); ?></td>
                        <td><?php echo htmlspecialchars($r['tenant_name']); ?></td>
                        <td><?php echo str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']); ?></td>
                        <td><?php echo htmlspecialchars(mb_substr($r['comment'] ?? '', 0, 80)) . (mb_strlen($r['comment'] ?? '') > 80 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
