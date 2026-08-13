<?php
/**
 * includes/header.php
 * -----------------------------------------------------------------
 * Shared dashboard chrome: sidebar + topbar. Include this AFTER
 * require_role() has already run, and after setting:
 *   $pageTitle    (string, required)
 *   $pageSubtitle (string, optional)
 *   $activeNav    (string, matches a 'key' below, required)
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/functions.php';

$role = current_user_role();

$navByRole = [
    'admin' => [
        ['key' => 'home',       'label' => 'Home',              'href' => BASE_URL . '/index.php'],
        ['key' => 'dashboard',  'label' => 'Dashboard',        'href' => BASE_URL . '/admin/dashboard.php'],
        ['key' => 'users',      'label' => 'Manage users',     'href' => BASE_URL . '/admin/users.php'],
        ['key' => 'verify',     'label' => 'Verify landlords',  'href' => BASE_URL . '/admin/verify_landlords.php'],
        ['key' => 'rooms',      'label' => 'Room listings',     'href' => BASE_URL . '/admin/rooms.php'],
        ['key' => 'bookings',   'label' => 'All bookings',      'href' => BASE_URL . '/admin/bookings.php'],
        ['key' => 'complaints', 'label' => 'Complaints',        'href' => BASE_URL . '/admin/complaints.php'],
        ['key' => 'reports',    'label' => 'Reports',           'href' => BASE_URL . '/admin/reports.php'],
    ],
    'landlord' => [
        ['key' => 'home',       'label' => 'Home',              'href' => BASE_URL . '/index.php'],
        ['key' => 'dashboard', 'label' => 'Dashboard',         'href' => BASE_URL . '/landlord/dashboard.php'],
        ['key' => 'profile',   'label' => 'My profile',        'href' => BASE_URL . '/landlord/profile.php'],
        ['key' => 'rooms',     'label' => 'My rooms',          'href' => BASE_URL . '/landlord/rooms.php'],
        ['key' => 'add_room',  'label' => 'Add a room',        'href' => BASE_URL . '/landlord/add_room.php'],
        ['key' => 'bookings',  'label' => 'Booking requests',  'href' => BASE_URL . '/landlord/bookings.php'],
        ['key' => 'reviews',   'label' => 'Reviews',           'href' => BASE_URL . '/landlord/reviews.php'],
    ],
    'tenant' => [
        ['key' => 'home',       'label' => 'Home',              'href' => BASE_URL . '/index.php'],
        ['key' => 'dashboard',  'label' => 'Dashboard',        'href' => BASE_URL . '/tenant/dashboard.php'],
        ['key' => 'profile',    'label' => 'My profile',       'href' => BASE_URL . '/tenant/profile.php'],
        ['key' => 'search',     'label' => 'Search rooms',     'href' => BASE_URL . '/tenant/search.php'],
        ['key' => 'bookings',   'label' => 'My bookings',      'href' => BASE_URL . '/tenant/bookings.php'],
        ['key' => 'favorites',  'label' => 'Favorites',        'href' => BASE_URL . '/tenant/favorites.php'],
        ['key' => 'complaints', 'label' => 'Complaints',       'href' => BASE_URL . '/tenant/complaints.php'],
    ],
];

$navItems = $navByRole[$role] ?? [];
$initials = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?> — Room Rental System</title>
<script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=19">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
</head>
<body>

<div class="dash">

  <aside class="dash-sidebar">
    <a href="<?php echo BASE_URL; ?>/index.php" class="brand">
      <span class="brand-icon">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </span>
      Room<span>Rental</span>
    </a>
    <?php echo render_role_badge($role); ?>

    <nav class="dash-nav">
      <?php foreach ($navItems as $item): ?>
        <a href="<?php echo $item['href']; ?>" class="<?php echo ($activeNav ?? '') === $item['key'] ? 'is-active' : ''; ?>">
          <span class="icon-dot"></span> <?php echo $item['label']; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="dash-sidebar-footer">
      <form method="post" action="<?php echo BASE_URL; ?>/logout.php" style="display:inline;">
        <?php echo csrf_field(); ?>
        <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit; font:inherit; padding:0; text-decoration:none;">Log out</button>
      </form>
    </div>
  </aside>

  <div class="dash-main">
    <header class="dash-topbar">
      <button class="dash-mobile-toggle" aria-label="Toggle menu" aria-expanded="false" id="dashMobileToggle">
        <span></span>
      </button>

      <div class="dash-topbar-title">
        <h1><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></h1>
        <?php if (!empty($pageSubtitle)): ?><p><?php echo htmlspecialchars($pageSubtitle); ?></p><?php endif; ?>
      </div>

      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" style="flex-shrink:0;">🌙</button>

      <div class="dash-user-chip">
        <?php
        $pic = $_SESSION['profile_picture'] ?? '';
        $name = $_SESSION['full_name'] ?? 'User';
        if ($pic): ?>
          <img src="<?php echo BASE_URL . '/' . htmlspecialchars($pic); ?>" alt="" class="avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; flex-shrink:0;">
        <?php else: ?>
          <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
        <?php endif; ?>
        <div>
          <div class="name"><?php echo htmlspecialchars($name); ?></div>
          <div class="role" style="display:flex; align-items:center; gap:6px;">
            <?php echo render_role_badge($role); ?>
          </div>
        </div>
        <div class="user-dropdown">
          <a href="<?php echo BASE_URL; ?>/index.php">Back to site</a>
          <form method="post" action="<?php echo BASE_URL; ?>/logout.php">
            <?php echo csrf_field(); ?>
            <button type="submit">Log out</button>
          </form>
        </div>
      </div>
    </header>

    <nav class="dash-mobile-menu" id="dashMobileMenu">
      <?php foreach ($navItems as $item): ?>
        <a href="<?php echo $item['href']; ?>"><?php echo $item['label']; ?></a>
      <?php endforeach; ?>
      <form method="post" action="<?php echo BASE_URL; ?>/logout.php" style="display:block; padding:10px 5px;">
        <?php echo csrf_field(); ?>
        <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit; font:inherit; padding:0; width:100%; text-align:left;">Log out</button>
      </form>
    </nav>

    <main class="dash-content">
