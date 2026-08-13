<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('tenant');
require_once __DIR__ . '/../includes/db.php';

// Build search query
$conditions = ["r.availability_status = 'available'"];
$params = [];

if (!empty($_GET['location'])) {
    $conditions[] = "r.location LIKE ?";
    $params[] = '%' . $_GET['location'] . '%';
}

if (isset($_GET['max_rent']) && $_GET['max_rent'] !== '') {
    if ($_GET['max_rent'] === '0') {
        $conditions[] = "r.rent_amount >= 20000";
    } else {
        $conditions[] = "r.rent_amount <= ?";
        $params[] = (float) $_GET['max_rent'];
    }
}

if (!empty($_GET['room_type'])) {
    $conditions[] = "r.room_type = ?";
    $params[] = $_GET['room_type'];
}

$where = implode(' AND ', $conditions);

$rooms = $pdo->prepare(
    "SELECT r.room_id, r.title, r.description, r.location, r.rent_amount, r.rent_type, r.room_type, r.capacity,
            u.full_name AS landlord_name, u.citizenship_status AS landlord_citizenship,
            (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1) AS primary_image,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE room_id = r.room_id) AS avg_rating
     FROM rooms r
     JOIN users u ON u.user_id = r.landlord_id
     WHERE $where
     ORDER BY r.created_at DESC
     LIMIT 20"
);
$rooms->execute($params);
$results = $rooms->fetchAll();

// Room IDs the tenant has already favorited (for the heart toggle)
$favStmt = $pdo->prepare("SELECT room_id FROM favorites WHERE user_id = ?");
$favStmt->execute([current_user_id()]);
$favRoomIds = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));

// Current search query string — preserved after toggling a favorite
$searchQuery = http_build_query($_GET);

$pageTitle = 'Search rooms';
$pageSubtitle = 'Find available rooms that match your needs.';
$activeNav = 'search';
require_once __DIR__ . '/../includes/header.php';
?>

<form class="search-bar" style="margin-bottom: 28px;" method="get">
  <div class="field">
    <label for="location">Location</label>
    <input type="text" id="location" name="location" placeholder="e.g. Baneshwor, Kathmandu" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
  </div>
  <div class="field">
    <label for="max_rent">Max rent</label>
    <select id="max_rent" name="max_rent">
      <option value="">Any budget</option>
      <option value="5000" <?php echo ($_GET['max_rent'] ?? '') === '5000' ? 'selected' : ''; ?>>Up to Rs 5,000</option>
      <option value="10000" <?php echo ($_GET['max_rent'] ?? '') === '10000' ? 'selected' : ''; ?>>Up to Rs 10,000</option>
      <option value="20000" <?php echo ($_GET['max_rent'] ?? '') === '20000' ? 'selected' : ''; ?>>Up to Rs 20,000</option>
      <option value="0" <?php echo ($_GET['max_rent'] ?? '') === '0' ? 'selected' : ''; ?>>Rs 20,000+</option>
    </select>
  </div>
  <div class="field">
    <label for="room_type">Room type</label>
    <select id="room_type" name="room_type">
      <option value="">All types</option>
      <option value="single" <?php echo ($_GET['room_type'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
      <option value="shared" <?php echo ($_GET['room_type'] ?? '') === 'shared' ? 'selected' : ''; ?>>Shared</option>
      <option value="apartment" <?php echo ($_GET['room_type'] ?? '') === 'apartment' ? 'selected' : ''; ?>>Apartment</option>
      <option value="studio" <?php echo ($_GET['room_type'] ?? '') === 'studio' ? 'selected' : ''; ?>>Studio</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Search</button>
</form>

<div class="panel">
  <div class="panel-head">
    <h2><?php echo count($results); ?> room<?php echo count($results) !== 1 ? 's' : ''; ?> found</h2>
  </div>

  <?php if (empty($results)): ?>
    <div class="empty-state">
      <strong>No rooms match your search</strong>
      <p>Try adjusting your filters or location.</p>
    </div>
  <?php else: ?>
    <div style="display:grid; gap:16px; padding:16px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
      <?php foreach ($results as $r): ?>
        <?php $isFav = isset($favRoomIds[$r['room_id']]); ?>
        <div style="background:var(--white); border:1px solid var(--line); border-radius:var(--radius-md); overflow:hidden; transition:box-shadow 0.2s, transform 0.2s;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.1)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.boxShadow='none'; this.style.transform='none'">
          <div style="position:relative;">
            <a href="room_detail.php?room_id=<?php echo (int) $r['room_id']; ?>" style="display:block;">
              <div style="height:160px; background:linear-gradient(135deg, var(--ink-soft), var(--slate)); display:flex; align-items:center; justify-content:center; color:var(--paper); font-size:0.85rem;">
                <?php if ($r['primary_image']): ?>
                  <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($r['primary_image']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>" style="width:100%; height:100%; object-fit:cover; transition:transform 0.3s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                <?php else: ?>
                  <span>📷 View details</span>
                <?php endif; ?>
              </div>
            </a>
            <!-- Favorite heart toggle -->
            <form class="fav-form" method="post" action="favorites.php" style="position:absolute; top:10px; right:10px; margin:0; z-index:2;">
              <input type="hidden" name="room_id" value="<?php echo (int) $r['room_id']; ?>">
              <input type="hidden" name="fav_action" value="<?php echo $isFav ? 'remove_favorite' : 'add_favorite'; ?>">
              <?php echo csrf_field(); ?>
              <button type="submit" class="fav-btn" aria-label="<?php echo $isFav ? 'Remove from favorites' : 'Add to favorites'; ?>"
                      title="<?php echo $isFav ? 'Remove from favorites' : 'Add to favorites'; ?>"
                      data-fav="<?php echo $isFav ? '1' : '0'; ?>"
                      style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.92); border:1px solid var(--line); box-shadow:0 2px 8px rgba(0,0,0,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:1.05rem; line-height:1; cursor:pointer; transition:transform 0.15s;"
                      onmouseover="this.style.transform='scale(1.12)'" onmouseout="this.style.transform='scale(1)'">
                <?php echo $isFav ? '❤️' : '🤍'; ?>
              </button>
            </form>
          </div>
          <div style="padding:18px;">
            <a href="room_detail.php?room_id=<?php echo (int) $r['room_id']; ?>" style="text-decoration:none; color:inherit;">
              <h3 style="font-size:1.05rem; margin-bottom:4px; transition:color 0.2s;" onmouseover="this.style.color='var(--brick)'" onmouseout="this.style.color='inherit'"><?php echo htmlspecialchars($r['title']); ?></h3>
            </a>
            <p style="font-size:0.85rem; color:var(--slate); margin-bottom:10px;"><?php echo htmlspecialchars($r['location']); ?> — <?php echo htmlspecialchars($r['landlord_name']); ?> <?php echo render_citizenship_badge($r['landlord_citizenship'] ?? null); ?></p>
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <span style="font-family:var(--font-mono); font-weight:600; font-size:1.1rem;">Rs <?php echo number_format((float) $r['rent_amount']); ?>/<?php echo $r['rent_type'] === 'weekly' ? 'wk' : 'mo'; ?></span>
              <span style="font-size:0.85rem; color:var(--slate);">
                <?php echo $r['avg_rating'] ? '★ ' . htmlspecialchars((string) $r['avg_rating']) : 'No reviews'; ?>
              </span>
            </div>
            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
              <span class="status-pill" style="background:var(--paper-soft);"><?php echo ucfirst($r['room_type']); ?></span>
              <span class="status-pill" style="background:var(--paper-soft);">👤 <?php echo $r['capacity']; ?> occupant(s)</span>
            </div>
            <div style="margin-top:14px; display:flex; gap:8px;">
              <a href="room_detail.php?room_id=<?php echo (int) $r['room_id']; ?>" class="btn btn-primary" style="flex:1; padding:10px; font-size:0.85rem; text-align:center;">Book now</a>
              <a href="room_detail.php?room_id=<?php echo (int) $r['room_id']; ?>" class="btn btn-ghost" style="flex:1; padding:10px; font-size:0.85rem; text-align:center;">View details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Heart toggle on room cards — adds/removes the favorite without a page reload.
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('fav-form')) return;
    e.preventDefault();

    var btn = form.querySelector('.fav-btn');
    var roomId = form.querySelector('input[name="room_id"]').value;
    var action = form.querySelector('input[name="fav_action"]').value;
    var wasFav = btn.dataset.fav === '1';

    fetch('favorites.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'fetch' },
        body: new FormData(form)
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.ok) {
            var nowFav = data.favorite;
            btn.dataset.fav = nowFav ? '1' : '0';
            btn.innerHTML = nowFav ? '❤️' : '🤍';
            btn.setAttribute('aria-label', nowFav ? 'Remove from favorites' : 'Add to favorites');
            btn.setAttribute('title', nowFav ? 'Remove from favorites' : 'Add to favorites');
            form.querySelector('input[name="fav_action"]').value = nowFav ? 'remove_favorite' : 'add_favorite';
            // Small pop so the user notices the change
            btn.style.transform = 'scale(1.25)';
            setTimeout(function () { btn.style.transform = 'scale(1)'; }, 150);
        }
    })
    .catch(function () {
        // Fallback: submit normally (page reload, keeps working without JS)
        form.submit();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>