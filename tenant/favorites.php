<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';

$tenantId = current_user_id();

// Return URL after toggling a favorite (so heart clicks on the search page
// land you back on the search page with your filters intact).
$returnUrl = $_POST['redirect'] ?? '';
if ($returnUrl === '' || str_starts_with($returnUrl, 'http')) {
    $returnUrl = BASE_URL . '/tenant/favorites.php';
} else {
    $returnUrl = BASE_URL . '/' . ltrim($returnUrl, '/');
}

// AJAX (heart button on search cards) — respond with JSON, no redirect.
$isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// Determine the action from the new unified field or the legacy field names.
if (isset($_POST['fav_action'])) {
    $action = $_POST['fav_action'];
} elseif (isset($_POST['remove_favorite'])) {
    $action = 'remove_favorite';
} elseif (isset($_POST['add_favorite'])) {
    $action = 'add_favorite';
} else {
    $action = '';
}

// Handle remove from favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'remove_favorite' && isset($_POST['room_id'])) {
    validate_csrf();
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND room_id = ?");
    $stmt->execute([$tenantId, (int) $_POST['room_id']]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'favorite' => false, 'room_id' => (int) $_POST['room_id']]);
        exit;
    }
    header('Location: ' . $returnUrl);
    exit;
}

// Handle add to favorites
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_favorite' && isset($_POST['room_id'])) {
    validate_csrf();
    $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (user_id, room_id) VALUES (?, ?)");
    $stmt->execute([$tenantId, (int) $_POST['room_id']]);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'favorite' => true, 'room_id' => (int) $_POST['room_id']]);
        exit;
    }
    header('Location: ' . $returnUrl);
    exit;
}

$favorites = $pdo->prepare(
    "SELECT f.favorite_id, f.created_at AS saved_at,
            r.room_id, r.title, r.location, r.rent_amount, r.rent_type, r.room_type,
            (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1) AS primary_image
     FROM favorites f
     JOIN rooms r ON r.room_id = f.room_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC"
);
$favorites->execute([$tenantId]);
$myFavorites = $favorites->fetchAll();

$pageTitle = 'Favorites';
$pageSubtitle = 'Rooms you\'ve saved for later.';
$activeNav = 'favorites';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Saved rooms (<?php echo count($myFavorites); ?>)</h2>
        <a href="search.php" class="btn btn-ghost">Find more rooms</a>
    </div>

    <?php if (empty($myFavorites)): ?>
        <div class="empty-state">
            <strong>No favorites yet</strong>
            <p>Save rooms you like by clicking the heart icon while searching.</p>
        </div>
    <?php else: ?>
        <div style="display:grid; gap:16px; padding:16px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
            <?php foreach ($myFavorites as $f): ?>
                <div style="background:var(--white); border:1px solid var(--line); border-radius:var(--radius-md); overflow:hidden; display:flex; flex-direction:column; transition:box-shadow 0.2s, transform 0.2s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none'; this.style.transform='none'">
                    <a href="room_detail.php?room_id=<?php echo (int) $f['room_id']; ?>" style="display:block;">
                        <div style="height:140px; background:linear-gradient(135deg, var(--ink-soft), var(--slate)); display:flex; align-items:center; justify-content:center; color:var(--paper); font-size:0.85rem;">
                            <?php if ($f['primary_image']): ?>
                                <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($f['primary_image']); ?>" alt="<?php echo htmlspecialchars($f['title']); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span>📷 No photo</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div style="padding:16px; flex:1; display:flex; flex-direction:column;">
                        <a href="room_detail.php?room_id=<?php echo (int) $f['room_id']; ?>" style="text-decoration:none; color:inherit;">
                            <h3 style="font-size:1rem; margin-bottom:4px; transition:color 0.2s;" onmouseover="this.style.color='var(--brick)'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($f['title']); ?></h3>
                            <p style="font-size:0.85rem; color:var(--slate); margin-bottom:8px;"><?php echo htmlspecialchars($f['location']); ?></p>
                        </a>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:auto;">
                            <a href="room_detail.php?room_id=<?php echo (int) $f['room_id']; ?>" style="font-family:var(--font-mono); font-weight:600; color:var(--brick);">Rs <?php echo number_format((float) $f['rent_amount']); ?>/<?php echo $f['rent_type'] === 'weekly' ? 'wk' : 'mo'; ?></a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="room_id" value="<?php echo $f['room_id']; ?>">
                                <input type="hidden" name="remove_favorite" value="1">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="btn btn-ghost" style="padding:6px 12px; font-size:0.82rem;">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
