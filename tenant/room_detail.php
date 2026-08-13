<?php
/**
 * tenant/room_detail.php
 * -----------------------------------------------------------------
 * Detailed view of a single room. Shows room info, landlord info,
 * all images, facilities, reviews, and booking controls.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';

$tenantId = current_user_id();
$roomId = (int) ($_GET['room_id'] ?? 0);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    validate_csrf();
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please select a rating between 1 and 5 stars.'];
    } elseif (empty($comment)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please write a comment for your review.'];
    } else {
        // Check tenant has an approved booking for this room
        $stmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE tenant_id = ? AND room_id = ? AND status = 'approved'");
        $stmt->execute([$tenantId, $roomId]);
        $hasApprovedBooking = (bool) $stmt->fetch();

        if (!$hasApprovedBooking) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'You can only review rooms you have booked and stayed in.'];
        } else {
            // Check if tenant already reviewed this room
            $stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE tenant_id = ? AND room_id = ?");
            $stmt->execute([$tenantId, $roomId]);
            if ($stmt->fetch()) {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'You have already reviewed this room.'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO reviews (room_id, tenant_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$roomId, $tenantId, $rating, $comment]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Review submitted successfully!'];
            }
        }
    }
    header('Location: ' . BASE_URL . '/tenant/room_detail.php?room_id=' . $roomId);
    exit;
}

// Check if tenant can review (has approved booking and hasn't reviewed yet)
$stmt = $pdo->prepare("SELECT booking_id FROM bookings WHERE tenant_id = ? AND room_id = ? AND status = 'approved'");
$stmt->execute([$tenantId, $roomId]);
$canReview = (bool) $stmt->fetch();
if ($canReview) {
    $stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE tenant_id = ? AND room_id = ?");
    $stmt->execute([$tenantId, $roomId]);
    $canReview = !(bool) $stmt->fetch();
}

// Handle add/remove favorite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    validate_csrf();
    $stmt = $pdo->prepare("SELECT favorite_id FROM favorites WHERE user_id = ? AND room_id = ?");
    $stmt->execute([$tenantId, $roomId]);
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND room_id = ?")->execute([$tenantId, $roomId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Removed from favorites.'];
    } else {
        $pdo->prepare("INSERT IGNORE INTO favorites (user_id, room_id) VALUES (?, ?)")->execute([$tenantId, $roomId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Added to favorites!'];
    }
    header('Location: ' . BASE_URL . '/tenant/room_detail.php?room_id=' . $roomId);
    exit;
}

// Fetch room with landlord info
$stmt = $pdo->prepare(
    "SELECT r.*, u.full_name AS landlord_name, u.email AS landlord_email, u.phone AS landlord_phone,
            u.verification_status AS landlord_verified, u.citizenship_status AS landlord_citizenship,
            u.created_at AS landlord_member_since,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE room_id = r.room_id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE room_id = r.room_id) AS review_count
     FROM rooms r
     JOIN users u ON u.user_id = r.landlord_id
     WHERE r.room_id = ?"
);
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: ' . BASE_URL . '/tenant/search.php');
    exit;
}

// Fetch all images
$stmt = $pdo->prepare("SELECT image_path, is_primary FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, image_id ASC");
$stmt->execute([$roomId]);
$images = $stmt->fetchAll();

// Fetch facilities
$stmt = $pdo->prepare("SELECT facility_name FROM room_facilities WHERE room_id = ?");
$stmt->execute([$roomId]);
$facilities = array_column($stmt->fetchAll(), 'facility_name');

// Fetch reviews with tenant names
$stmt = $pdo->prepare(
    "SELECT rev.rating, rev.comment, rev.created_at, u.full_name AS tenant_name
     FROM reviews rev
     JOIN users u ON u.user_id = rev.tenant_id
     WHERE rev.room_id = ?
     ORDER BY rev.created_at DESC"
);
$stmt->execute([$roomId]);
$reviews = $stmt->fetchAll();

// Check if tenant already favorited this room
$stmt = $pdo->prepare("SELECT favorite_id FROM favorites WHERE user_id = ? AND room_id = ?");
$stmt->execute([$tenantId, $roomId]);
$isFavorite = (bool) $stmt->fetch();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = htmlspecialchars($room['title']);
$pageSubtitle = htmlspecialchars($room['location']);
$activeNav = 'search';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:22px;">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div style="display:flex; align-items:center; gap:12px; margin-bottom:24px;">
    <a href="search.php" class="btn btn-ghost" style="padding:8px 16px; font-size:0.85rem;">&larr; Back to search</a>
</div>

<!-- ===================== IMAGE GALLERY ========================= -->
<div class="panel" style="overflow:hidden; padding:0;">
    <?php if (!empty($images)): ?>
        <div style="position:relative;">
            <div id="galleryMain" style="width:100%; height:400px; background:var(--ink-soft); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                <img id="mainImage" src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($images[0]['image_path']); ?>"
                     alt="<?php echo htmlspecialchars($room['title']); ?>"
                     style="width:100%; height:100%; object-fit:cover; transition:opacity 0.3s;">
            </div>
            <?php if (count($images) > 1): ?>
                <div style="display:flex; gap:8px; padding:12px 16px; background:var(--white); overflow-x:auto; border-top:1px solid var(--line);">
                    <?php foreach ($images as $i => $img): ?>
                        <div onclick="document.getElementById('mainImage').src='<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($img['image_path']); ?>'; document.querySelectorAll('.thumb').forEach(el=>el.style.border='2px solid transparent'); this.style.border='2px solid var(--brick)';"
                             class="thumb"
                             style="flex-shrink:0; width:80px; height:60px; border-radius:6px; overflow:hidden; cursor:pointer; border:2px solid <?php echo $i === 0 ? 'var(--brick)' : 'transparent'; ?>; transition:border 0.2s;">
                            <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($img['image_path']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="height:300px; background:linear-gradient(135deg, var(--ink-soft), var(--slate)); display:flex; align-items:center; justify-content:center; color:var(--paper); font-size:1.1rem;">
            📷 No photos available
        </div>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns: 1fr 360px; gap:24px; align-items:start;">

    <!-- =================== LEFT COLUMN: ROOM INFO =================== -->
    <div>

        <!-- Title & rating -->
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div>
                <h2 style="font-size:1.4rem; margin:0 0 4px 0;"><?php echo htmlspecialchars($room['title']); ?></h2>
                <p style="color:var(--slate); margin:0; font-size:0.9rem;">
                    📍 <?php echo htmlspecialchars($room['location']); ?>
                    <?php if ($room['address']): ?> — <?php echo htmlspecialchars($room['address']); ?><?php endif; ?>
                </p>
            </div>
            <div style="text-align:right;">
                <div style="font-family:var(--font-mono); font-size:1.4rem; font-weight:700; color:var(--ink);">
                    Rs <?php echo number_format((float) $room['rent_amount']); ?>
                    <span style="font-size:0.85rem; font-weight:400; color:var(--slate);">/<?php echo $room['rent_type'] === 'weekly' ? 'wk' : 'mo'; ?></span>
                </div>
                <?php if ($room['avg_rating']): ?>
                    <div style="font-size:0.9rem; color:var(--marigold); margin-top:4px;">
                        ★ <?php echo htmlspecialchars((string) $room['avg_rating']); ?>
                        <span style="color:var(--slate);">(<?php echo (int) $room['review_count']; ?> review<?php echo $room['review_count'] !== 1 ? 's' : ''; ?>)</span>
                    </div>
                <?php else: ?>
                    <div style="font-size:0.85rem; color:var(--slate); margin-top:4px;">No reviews yet</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status & type tags -->
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin:16px 0;">
            <span class="status-pill status-<?php echo htmlspecialchars($room['availability_status']); ?>">
                <?php echo $room['availability_status'] === 'available' ? '✓ Available' : ucfirst($room['availability_status']); ?>
            </span>
            <span class="status-pill" style="background:var(--paper-soft);"><?php echo ucfirst($room['room_type']); ?></span>
            <span class="status-pill" style="background:var(--paper-soft);">👤 <?php echo (int) $room['capacity']; ?> occupant<?php echo $room['capacity'] !== 1 ? 's' : ''; ?></span>
            <?php if ($room['rent_type'] === 'weekly'): ?>
                <span class="status-pill" style="background:var(--paper-soft);">Weekly rent</span>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="panel">
            <div class="panel-head"><h3>About this room</h3></div>
            <div style="padding:16px 22px; line-height:1.7; color:var(--ink-soft);">
                <?php echo nl2br(htmlspecialchars($room['description'] ?? 'No description provided.')); ?>
            </div>
        </div>

        <!-- Facilities -->
        <?php if (!empty($facilities)): ?>
            <div class="panel">
                <div class="panel-head"><h3>Facilities &amp; amenities</h3></div>
                <div style="padding:16px 22px; display:flex; flex-wrap:wrap; gap:10px;">
                    <?php foreach ($facilities as $fac): ?>
                        <span style="display:inline-flex; align-items:center; gap:6px; background:var(--paper-soft); border:1.5px solid var(--line); border-radius:20px; padding:8px 14px; font-size:0.85rem;">
                            ✅ <?php echo htmlspecialchars($fac); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Write a review form -->
        <?php if ($canReview): ?>
            <div class="panel" style="margin-bottom:22px;">
                <div class="panel-head">
                    <h3>✍️ Write a review</h3>
                </div>
                <form method="post" style="padding:18px 22px;">
                    <div class="form-group">
                        <label for="rating">Rating</label>
                        <div id="rating" style="display:flex; gap:8px; font-size:1.4rem; cursor:pointer;" onclick="
                            var stars = event.target.closest('.star-rating');
                            if (stars) {
                                var val = stars.dataset.value;
                                document.getElementById('ratingInput').value = val;
                                stars.querySelectorAll('span').forEach(function(s, i) {
                                    s.style.color = i < val ? 'var(--marigold)' : 'var(--line)';
                                });
                            }
                        ">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-rating" data-value="<?php echo $i; ?>"
                                      onmouseover="this.style.transform='scale(1.2)'"
                                      onmouseout="this.style.transform='scale(1)'"
                                      style="color:var(--line); transition:transform 0.15s; display:inline-block; cursor:pointer;">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" id="ratingInput" name="rating" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="comment">Your review</label>
                        <textarea id="comment" name="comment" rows="4"
                                  placeholder="Share your experience about this room..."
                                  style="width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:var(--radius-sm); resize:vertical; font-family:inherit;" required></textarea>
                    </div>
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="submit_review" value="1" class="btn btn-primary">Submit review</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Reviews -->
        <div class="panel">
            <div class="panel-head">
                <h3>Reviews (<?php echo count($reviews); ?>)</h3>
            </div>
            <?php if (empty($reviews)): ?>
                <div class="empty-state">
                    <strong>No reviews yet</strong>
                    <?php if ($canReview): ?>
                        <p>Be the first to leave a review using the form above.</p>
                    <?php else: ?>
                        <p>Reviews will appear here after tenants book and stay in this room.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding:16px 22px;">
                    <?php foreach ($reviews as $rev): ?>
                        <div style="padding:14px 0; border-bottom:1px solid var(--line);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <strong><?php echo htmlspecialchars($rev['tenant_name']); ?></strong>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span style="color:var(--marigold); font-weight:600;">
                                        <?php echo str_repeat('★', (int) $rev['rating']) . str_repeat('☆', 5 - (int) $rev['rating']); ?>
                                    </span>
                                    <span style="font-size:0.8rem; color:var(--slate);"><?php echo htmlspecialchars($rev['created_at']); ?></span>
                                </div>
                            </div>
                            <p style="margin:0; font-size:0.9rem; color:var(--ink-soft);"><?php echo nl2br(htmlspecialchars($rev['comment'] ?? '')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- =================== RIGHT COLUMN: SIDEBAR =================== -->
    <div>

        <!-- Book this room -->
        <div class="panel" style="position:sticky; top:24px;">
            <div class="panel-head">
                <h3>Book this room</h3>
            </div>
            <div style="padding:18px 22px;">
                <?php if ($room['availability_status'] === 'available'): ?>
                    <form action="bookings.php" method="post" onsubmit="return confirm('Are you sure you want to send a booking request for this room?');">
                        <input type="hidden" name="room_id" value="<?php echo (int) $room['room_id']; ?>">
                        <div class="form-group">
                            <label for="move_in_date">Move-in date</label>
                            <input type="date" id="move_in_date" name="move_in_date"
                                   value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                        </div>
                        <?php echo csrf_field(); ?>
                        <button type="submit" name="book" value="1" class="btn btn-primary btn-block" style="padding:12px; font-size:0.95rem;">
                            Request to book
                        </button>
                    </form>
                <?php else: ?>
                    <div style="text-align:center; padding:12px; background:var(--paper-soft); border-radius:var(--radius-sm);">
                        <strong style="color:var(--brick);">❌ Not available</strong>
                        <p style="font-size:0.85rem; color:var(--slate); margin:6px 0 0 0;">This room is currently <?php echo htmlspecialchars($room['availability_status']); ?>.</p>
                    </div>
                <?php endif; ?>

                <!-- Favorite button -->
                <form method="post" style="margin-top:12px;">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="toggle_favorite" value="1"
                            class="btn btn-ghost btn-block" style="padding:10px; font-size:0.88rem;">
                        <?php echo $isFavorite ? '❤️ Remove from favorites' : '🤍 Save to favorites'; ?>
                    </button>
                </form>
            </div>

            <!-- Landlord card -->
            <div style="border-top:1px solid var(--line); padding:18px 22px;">
                <h4 style="font-size:0.85rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--slate); margin:0 0 12px 0;">Listed by</h4>
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="avatar" style="width:44px; height:44px; font-size:1rem; background:var(--brick); color:var(--white);">
                        <?php echo strtoupper(substr($room['landlord_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight:600; display:flex; align-items:center; gap:6px; flex-wrap:wrap;"><?php echo htmlspecialchars($room['landlord_name']); ?> <?php echo render_role_badge('landlord'); ?> <?php echo render_citizenship_badge($room['landlord_citizenship'] ?? null); ?></div>
                        <div style="font-size:0.82rem; color:var(--slate);">
                            <?php if ($room['landlord_verified'] === 'approved'): ?>
                                <span style="color:var(--success);">✓ Verified landlord</span>
                            <?php else: ?>
                                <span style="color:var(--marigold);">⏳ Verification pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="margin-top:12px; font-size:0.85rem; color:var(--ink-soft);">
                    <div>📧 <?php echo htmlspecialchars($room['landlord_email']); ?></div>
                    <?php if ($room['landlord_phone']): ?>
                        <div style="margin-top:4px;">📞 <?php echo htmlspecialchars($room['landlord_phone']); ?></div>
                    <?php endif; ?>
                    <div style="margin-top:4px;">🕐 Member since <?php echo date('M Y', strtotime($room['landlord_member_since'])); ?></div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>