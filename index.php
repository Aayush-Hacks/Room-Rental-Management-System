<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$loggedIn = isset($_SESSION['user_id']);
$loggedInRole = $loggedIn ? ($_SESSION['role'] ?? '') : '';

$dashboardLink = '';
if ($loggedIn) {
    switch ($loggedInRole) {
        case 'admin':    $dashboardLink = BASE_URL . '/admin/dashboard.php'; break;
        case 'landlord': $dashboardLink = BASE_URL . '/landlord/dashboard.php'; break;
        default:         $dashboardLink = BASE_URL . '/tenant/dashboard.php'; break;
    }
}

$liveRooms     = (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE availability_status = 'available'")->fetchColumn();
$liveLandlords = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord' AND verification_status = 'approved'")->fetchColumn();
$totalUsers    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();

$testimonials = $pdo->query(
    "SELECT rv.review_id, rv.rating, rv.comment, rv.created_at, u.full_name, u.user_id
     FROM reviews rv JOIN users u ON u.user_id = rv.tenant_id
     WHERE rv.comment IS NOT NULL AND rv.comment != ''
     ORDER BY rv.created_at DESC LIMIT 3"
)->fetchAll();

$featuredRooms = $pdo->query(
    "SELECT r.room_id, r.title, r.location, r.rent_amount, r.rent_type, r.room_type, r.capacity,
            u.full_name AS landlord_name,
            (SELECT image_path FROM room_images WHERE room_id = r.room_id AND is_primary = 1 LIMIT 1) AS primary_image,
            (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE room_id = r.room_id) AS avg_rating
     FROM rooms r JOIN users u ON u.user_id = r.landlord_id
     WHERE r.availability_status = 'available'
     ORDER BY r.created_at DESC LIMIT 6"
)->fetchAll();

// Average site-wide rating + the most recent room (for the hero visual)
$avgSiteRating = $pdo->query("SELECT ROUND(AVG(rating), 1) FROM reviews")->fetchColumn();
$heroRoom = $featuredRooms[0] ?? null;

function getRoomFacilities($pdo, $roomId) {
    $stmt = $pdo->prepare("SELECT facility_name FROM room_facilities WHERE room_id = ? LIMIT 4");
    $stmt->execute([$roomId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$faqs = [
    [
        'q' => 'How do I book a room?',
        'a' => 'Booking a room on our platform is simple. First, create a free account and browse through available listings. Use the search filters to narrow down by location, budget, and room type. Once you find a room you like, click "Book Now" to send a booking request directly to the landlord. The landlord will review your request and respond — approved requests reserve the room for you. You can track all your bookings from your dashboard.'
    ],
    [
        'q' => 'How are landlords verified?',
        'a' => 'Every landlord on our platform goes through a manual verification process. When a user registers as a landlord, their account is marked "pending" and reviewed by our admin team. We verify their identity documents, contact information, and ensure they are legitimate property owners or authorized agents. Only after approval can they list rooms. This process typically takes 1 to 2 business days and helps keep our platform safe and trustworthy.'
    ],
    [
        'q' => 'Can I cancel my booking?',
        'a' => 'Yes, you can cancel a booking request as long as it is still in "pending" status \u2014 simply go to your bookings page and cancel it. Once a landlord has approved your booking, the room is marked as booked. If you need to cancel an approved booking, we recommend contacting the landlord directly through the platform to discuss terms. Our admin team can assist if there are any disputes.'
    ],
    [
        'q' => 'Is registration free?',
        'a' => 'Absolutely. Registration is completely free for both tenants and landlords. There are no hidden fees, subscription charges, or commissions. Tenants can browse, search, and book rooms at no cost. Landlords can create listings, upload photos, and manage bookings without paying anything. We believe in making room rentals accessible and affordable for everyone in Nepal.'
    ],
    [
        'q' => 'How long does landlord approval take?',
        'a' => 'Landlord verification typically takes 1 to 2 business days. Once you register as a landlord, our admin team reviews your information and updates your status. You will receive a notification once your account is approved. Booking requests from tenants are usually responded to by landlords within 24 hours, though response times may vary. You can check the status of your requests anytime from your dashboard.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠Room Rental System — Find Your Perfect Room</title>
    <meta name="description" content="Search, compare, and book rental rooms directly with verified landlords across Nepal.">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <link rel="stylesheet" href="assets/css/style.css?v=19">

</head>
<body>

<!-- ========================= HEADER ============================== -->
<header class="header">
  <div class="container header-inner">
    <a href="<?php echo BASE_URL; ?>" class="brand">
      <span class="brand-mark">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      </span>
      Room<span class="brand-accent">Rental</span>
    </a>

    <nav class="nav">
      <a href="<?php echo BASE_URL; ?>" class="is-active">Home</a>
      <a href="tenant/search.php">Rooms</a>
      <a href="#faq">FAQ</a>
      <a href="<?php echo ($loggedIn && $loggedInRole === 'landlord') ? BASE_URL . '/landlord/add_room.php' : 'register.php?role=landlord'; ?>">List Your Room</a>
    </nav>

    <div class="header-actions">
      <?php if ($loggedIn): ?>
        <span style="display:inline-flex; align-items:center;"><?php echo render_role_badge($loggedInRole); ?></span>
        <a href="<?php echo $dashboardLink; ?>" class="btn btn-sm btn-outline">Dashboard</a>
        <form method="post" action="logout.php" style="display:inline;">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn-sm btn-ghost">Log out</button>
        </form>
      <?php else: ?>
        <a href="login.php" class="btn btn-sm btn-ghost">Log in</a>
        <a href="register.php" class="btn btn-sm btn-primary">Sign up</a>
      <?php endif; ?>
    </div>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">🌙</button>

    <button class="menu-btn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>

  <div class="mobile-menu">
    <a href="<?php echo BASE_URL; ?>">Home</a>
    <a href="tenant/search.php">Rooms</a>
    <a href="#faq">FAQ</a>
    <a href="<?php echo ($loggedIn && $loggedInRole === 'landlord') ? BASE_URL . '/landlord/add_room.php' : 'register.php?role=landlord'; ?>">List Your Room</a>      <hr>      <?php if ($loggedIn): ?>
        <span style="display:flex; align-items:center; padding:6px 14px;"><?php echo render_role_badge($loggedInRole); ?></span>
        <a href="<?php echo $dashboardLink; ?>">Dashboard</a>
        <form method="post" action="logout.php" style="display:block;">
          <?php echo csrf_field(); ?>
          <button type="submit" style="background:none; border:none; cursor:pointer; color:inherit; font:inherit; padding:6px 14px; font-size:0.85rem; width:100%; text-align:left;">Logout</button>
        </form>
      <?php else: ?>
        <a href="login.php">Log in</a>
        <a href="register.php">Sign up</a>
      <?php endif; ?>
  </div>
</header>

<main>

<!-- ========================== HERO =============================== -->
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-text">
      <span class="tag">Direct Room Rentals</span>
      <h1>Find Your<br><span class="hero-accent">Perfect Room</span></h1>
      <p>Search verified rental rooms across Nepal and book directly with trusted landlords — no broker fees, no hassle.</p>

      <form class="search-box" action="tenant/search.php" method="get">
        <div class="search-fields">
          <input type="text" name="location" placeholder="📍 Location..." class="search-input">
          <select name="room_type" class="search-select">
            <option value="">🏠 Type</option>
            <option value="single">Single</option>
            <option value="shared">Shared</option>
            <option value="apartment">Apartment</option>
            <option value="For Student">For Students Only</option>
          </select>
          <select name="max_rent" class="search-select">
            <option value="">💰 Budget</option>
            <option value="5000">Up to Rs 5,000</option>
            <option value="10000">Up to Rs 10,000</option>
            <option value="20000">Up to Rs 20,000</option>
            
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block-mobile">Search</button>
      </form>

      <div class="hero-badges">
        <span class="hero-badge-item">✅ 100% verified landlords</span>
        <span class="hero-badge-item">💰 Zero broker fee</span>
        <span class="hero-badge-item">🤝 Book directly</span>
        <span class="hero-badge-item">🔒 Secure &amp; private</span>
      </div>
    </div>

    <div class="hero-visual">
      <?php if ($heroRoom): ?>
        <span class="hero-float hero-float--rating">★ <?php echo htmlspecialchars((string) ($avgSiteRating ?: '—')); ?> avg rating</span>
        <span class="hero-float hero-float--rooms"><strong><?php echo number_format(max($liveRooms, 1)); ?>+</strong> rooms live</span>
      <?php endif; ?>
      <div class="hero-card">
        <div class="hero-card-img">
          <?php if ($heroRoom && !empty($heroRoom['primary_image'])): ?>
            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($heroRoom['primary_image']); ?>" alt="<?php echo htmlspecialchars($heroRoom['title']); ?>">
          <?php endif; ?>
          <span class="hero-badge">✓ Verified</span>
          <span class="hero-price">Rs <?php echo number_format((float) ($heroRoom['rent_amount'] ?? 8500)); ?>/<?php echo ($heroRoom['rent_type'] ?? 'monthly') === 'weekly' ? 'wk' : 'mo'; ?></span>
        </div>
        <div class="hero-card-body">
          <h3><?php echo htmlspecialchars($heroRoom['title'] ?? 'Single room, attached bath'); ?></h3>
          <p>📍 <?php echo htmlspecialchars($heroRoom['location'] ?? 'Kalanki, Kathmandu'); ?></p>
          <div class="hero-card-meta">
            <span><?php echo (int) ($heroRoom['capacity'] ?? 1); ?> tenant<?php echo ($heroRoom['capacity'] ?? 1) > 1 ? 's' : ''; ?></span>
            <span>·</span>
            <span>Available now</span>
            <span>·</span>
            <span>★ <?php echo htmlspecialchars((string) ($heroRoom['avg_rating'] ?: '—')); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="container">
    <div class="stats-bar">
      <div class="stat-item">
        <span class="stat-num"><?php echo number_format(max($liveRooms, 1)); ?>+</span>
        <span class="stat-lbl">Available Rooms</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-num"><?php echo number_format(max($liveLandlords, 1)); ?>+</span>
        <span class="stat-lbl">Verified Landlords</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-num"><?php echo number_format(max($totalUsers, 1)); ?>+</span>
        <span class="stat-lbl">Registered Users</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-num"><?php echo number_format(max($totalBookings, 1)); ?>+</span>
        <span class="stat-lbl">Successful Bookings</span>
      </div>
    </div>
  </div>
</section>

<!-- ==================== POPULAR LOCATIONS ======================= -->
<section class="section section-locations">
  <div class="container">
    <div class="section-top">
      <span class="tag">Popular areas</span>
      <h2>Find rooms across Nepal</h2>
      <p>Start with the country's most popular rental cities and towns.</p>
    </div>
    <div class="location-strip">
      <a href="tenant/search.php?location=Kathmandu" class="loc-chip">🏔️ Kathmandu</a>
      <a href="tenant/search.php?location=Lalitpur" class="loc-chip">🛕 Lalitpur</a>
      <a href="tenant/search.php?location=Bhaktapur" class="loc-chip">🛖 Bhaktapur</a>
      <a href="tenant/search.php?location=Pokhara" class="loc-chip">🏞️ Pokhara</a>
      <a href="tenant/search.php?location=Biratnagar" class="loc-chip">🏙️ Biratnagar</a>
      <a href="tenant/search.php?location=Chitwan" class="loc-chip">🌿 Chitwan</a>
    </div>
  </div>
</section>

<!-- ======================= ROOMS ================================ -->
<?php if (!empty($featuredRooms)): ?>
<section class="section">
  <div class="container">
    <div class="section-top">
      <span class="tag">Available now</span>
      <h2>Featured Rooms</h2>
      <p>Hand-picked rooms from verified landlords across Nepal.</p>
    </div>

    <div class="cards-grid">
      <?php foreach ($featuredRooms as $r):
        $facilities = getRoomFacilities($pdo, $r['room_id']);
      ?>
        <div class="card">
          <div class="card-img">
            <?php if ($r['primary_image']): ?>
              <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($r['primary_image']); ?>" alt="<?php echo htmlspecialchars($r['title']); ?>">
            <?php else: ?>
              <div class="card-placeholder">🏠</div>
            <?php endif; ?>
            <span class="card-badge">Available</span>
          </div>
          <div class="card-body">
            <span class="card-tag"><?php echo ucfirst($r['room_type']); ?></span>
            <h3 class="card-title"><?php echo htmlspecialchars($r['title']); ?></h3>
            <span class="card-location">📍 <?php echo htmlspecialchars($r['location']); ?></span>
            <span class="card-price">Rs <?php echo number_format((float) $r['rent_amount']); ?><span>/<?php echo $r['rent_type'] === 'weekly' ? 'wk' : 'mo'; ?></span></span>

            <?php if (!empty($facilities)): ?>
              <div class="card-facs">
                <?php foreach ($facilities as $f): ?>
                  <span class="card-fac"><?php echo htmlspecialchars($f); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="card-footer">
              <span class="card-rating">★ <?php echo $r['avg_rating'] ?: '—'; ?></span>
              <span class="card-host">by <?php echo htmlspecialchars($r['landlord_name']); ?></span>
            </div>

            <?php
            $bookLabel = $loggedIn ? 'Book Now' : 'Login to Book';
            $bookHref  = 'login.php?redirect=tenant/room_detail.php%3Froom_id%3D' . $r['room_id'];
            if ($loggedIn && $loggedInRole === 'tenant') {
                $bookHref = 'tenant/room_detail.php?room_id=' . $r['room_id'];
            }
            ?>
            <a href="<?php echo $bookHref; ?>" class="btn btn-primary btn-sm card-btn"><?php echo $bookLabel; ?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ====================== FEATURES ============================== -->
<section class="section section-alt">
  <div class="container">
    <div class="section-top">
      <span class="tag">Why Choose Us</span>
      <h2>Why Choose Room Rental?</h2>
      <p>We make room renting faster, safer, and easier for both tenants and landlords.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(75,122,91,0.12); color: var(--success);">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <h3>Verified Landlords</h3>
        <p>Every landlord is manually verified before they can list rooms.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(184,73,46,0.10); color: var(--brick);">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3>Zero Broker Fee</h3>
        <p>Book directly with landlords — no middlemen, no extra charges.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(227,167,46,0.14); color: #8a6512;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <h3>Easy Search</h3>
        <p>Filter by location, budget, room type, and facilities you need.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(28,43,46,0.06); color: var(--ink);">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h3>Secure Process</h3>
        <p>All booking requests are managed safely through the platform.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(53,113,179,0.12); color: #1f5a91;">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        </div>
        <h3>Transparent Info</h3>
        <p>Real photos, facilities, prices, and availability — all upfront.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: rgba(75,122,91,0.10); color: var(--success);">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>Fast Communication</h3>
        <p>Connect directly with landlords through the platform messaging.</p>
      </div>
    </div>
  </div>
</section>

<!-- ====================== STEPS ================================= -->
<section class="section">
  <div class="container">
    <div class="section-top">
      <span class="tag">How It Works</span>
      <h2>From search to move-in, in five steps</h2>
      <p>No middleman fees, no fake listings — every landlord is verified.</p>
    </div>

    <div class="steps-row">
      <div class="step-item">
        <div class="step-circle">1</div>
        <h3>Create Account</h3>
        <p>Sign up as tenant or landlord.</p>
      </div>
      <div class="step-connector">→</div>
      <div class="step-item">
        <div class="step-circle">2</div>
        <h3>Search Rooms</h3>
        <p>Filter by what matters to you.</p>
      </div>
      <div class="step-connector">→</div>
      <div class="step-item">
        <div class="step-circle">3</div>
        <h3>Send Request</h3>
        <p>Book your preferred room.</p>
      </div>
      <div class="step-connector">→</div>
      <div class="step-item">
        <div class="step-circle">4</div>
        <h3>Get Approved</h3>
        <p>Landlord confirms your booking.</p>
      </div>
      <div class="step-connector">→</div>
      <div class="step-item">
        <div class="step-circle">5</div>
        <h3>Move In</h3>
        <p>The room is yours. Welcome home!</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== TESTIMONIALS ============================ -->
<?php if (!empty($testimonials)): ?>
<section class="section section-alt">
  <div class="container">
    <div class="section-top">
      <span class="tag">Testimonials</span>
      <h2>What Our Users Say</h2>
      <p>Hear from tenants and landlords who use our platform.</p>
    </div>

    <div class="reviews-grid">
      <?php foreach ($testimonials as $t): ?>
        <div class="review-card">
          <div class="review-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span class="star <?php echo $i <= (int) $t['rating'] ? 'filled' : ''; ?>">★</span>
            <?php endfor; ?>
          </div>
          <p class="review-text">"<?php echo htmlspecialchars($t['comment']); ?>"</p>
          <div class="review-author">
            <span class="review-avatar"><?php echo strtoupper(substr($t['full_name'], 0, 1)); ?></span>
            <span class="review-name"><?php echo htmlspecialchars($t['full_name']); ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================= FAQ ================================= -->
<section class="section" id="faq">
  <div class="container">
    <div class="section-top">
      <span class="tag">FAQ</span>
      <h2>Frequently Asked Questions</h2>
      <p>Everything you need to know about using Room Rental.</p>
    </div>

    <div class="faq-list">
      <?php foreach ($faqs as $faq): ?>
        <div class="faq-item">
          <button class="faq-btn" aria-expanded="false">
            <span><?php echo htmlspecialchars($faq['q']); ?></span>
            <span class="faq-plus">+</span>
          </button>
          <div class="faq-answer">
            <p><?php echo htmlspecialchars($faq['a']); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== CTA BANNER ============================= -->
<?php if (!$loggedIn): ?>
<section class="section section-alt">
  <div class="container">
    <div class="cta-banner">
      <div>
        <span class="tag" style="color: var(--marigold);">For Landlords</span>
        <h2>List your room. Get verified. Get tenants.</h2>
        <p>Create a free listing, upload photos, and manage booking requests from one dashboard.</p>
      </div>
      <a href="register.php?role=landlord" class="btn btn-primary btn-lg">Become a Landlord</a>
    </div>
  </div>
</section>
<?php endif; ?>

</main>

<!-- ========================= FOOTER ============================= -->
<footer class="footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a href="<?php echo BASE_URL; ?>" class="footer-logo">Room<span>Rental</span></a>
      <p>A trusted platform connecting tenants with verified landlords across Nepal. No brokers, no fees — just direct room rentals.</p>
    </div>
    <div class="footer-col">
      <h4>Links</h4>
      <a href="<?php echo BASE_URL; ?>">Home</a>
      <a href="tenant/search.php">Browse Rooms</a>
      <?php if ($loggedIn && $loggedInRole === 'tenant'): ?>
        <a href="<?php echo BASE_URL; ?>/tenant/bookings.php">My Bookings</a>
      <?php elseif ($loggedIn && $loggedInRole === 'landlord'): ?>
        <a href="<?php echo BASE_URL; ?>/landlord/rooms.php">My Listings</a>
      <?php else: ?>
        <a href="register.php?role=landlord">Become a Landlord</a>
      <?php endif; ?>
    </div>
    <div class="footer-col">
      <h4>Top areas</h4>
      <a href="tenant/search.php?location=Kathmandu">Kathmandu</a>
      <a href="tenant/search.php?location=Lalitpur">Lalitpur</a>
      <a href="tenant/search.php?location=Bhaktapur">Bhaktapur</a>
      <a href="tenant/search.php?location=Pokhara">Pokhara</a>
      <a href="tenant/search.php?location=Biratnagar">Biratnagar</a>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <a href="#">FAQs</a>
      <a href="#">Privacy Policy</a>
      <a href="#">Contact Us</a>
    </div>
    <div class="footer-col">
      <h4>Contact</h4>
      <span>📞 +977 9765302844</span>
      <span>✉️ hello@roomrental.np</span>
      <span>📍 Kathmandu, Nepal</span>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container">
      <span>&copy; <?php echo date('Y'); ?> Room Rental Platform. All rights reserved.</span>
    </div>
  </div>
</footer>

<button class="top-btn" id="topBtn">↑</button>

<script src="assets/js/main.js?v=2"></script>
<script src="assets/js/cookie-consent.js?v=2"></script>
</body>
</html>
