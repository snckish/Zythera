<?php
require 'config.php';
$userEmail = $_SESSION['logged_in_user'] ?? null;
$userName = null;
$uObj = null;
if ($userEmail) {
    $uObj = findAccountByEmail($userEmail);
    if ($uObj) {
        $userName = $uObj->name;
    }
}
$userRole = $_SESSION['role'] ?? 'user';
$loginTime = $_SESSION['login_time'] ?? null;
$cartCount = 0;
if ($userEmail && isset($_SESSION['cart'][$userEmail])) {
    foreach ($_SESSION['cart'][$userEmail] as $item) {
        $cartCount += is_array($item) ? (int)($item['qty'] ?? 1) : 1;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZYTHERA | About</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/about.css">

</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">

    <a class="navbar-brand fw-bold" href="website.php">
      <span style="font-family:'Playfair Display',serif;color:var(--deep);font-weight:700;letter-spacing:2px;"> ZYTHERA </span>
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">

        <!-- Home -->
        <li class="nav-item">
          <a href="website.php" class="nav-link fw-semibold">Home</a>
        </li>

        <!-- Menu dropdown -->
        <li class="nav-item dropdown">
          <a href="#" class="nav-link fw-semibold dropdown-toggle zythera-menu-toggle" id="menuDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Menu
          </a>
          <ul class="dropdown-menu shadow border-0 zythera-dropdown" aria-labelledby="menuDropdown">
            <li><a class="dropdown-item" href="about.php">About</a></li>
            <li><a class="dropdown-item" href="website.php#contact">Contact Us</a></li>
            <li><a class="dropdown-item" href="website.php#products">Products</a></li>
          </ul>
        </li>

        <?php if ($userEmail && $userRole !== 'admin'): ?>
        <!-- My Orders -->
        <li class="nav-item">
          <a href="profile.php?tab=orders" class="nav-link fw-semibold">My Orders</a>
        </li>
        <?php endif; ?>

        <?php if ($userEmail): ?>
        <!-- Profile Capsule -->
        <li class="nav-item">
          <div class="nav-user-capsule dropdown">
            <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" style="cursor:pointer;" aria-expanded="false">
              <div class="text-end d-none d-md-block">
                <p class="mb-0 fw-bold" style="font-size:.75rem;color:var(--green);line-height:1.2;"><?= htmlspecialchars($userName) ?></p>
                <?php if ($loginTime): ?>
                  <small class="text-muted" style="font-size:.58rem;"><span id="liveTime"></span></small>
                <?php endif; ?>
              </div>
              <?php $navPic = getAvatarURL($uObj->profile_pic ?? null, $uObj->email ?? null, $userName, 34); ?>
              <img src="<?= htmlspecialchars($navPic) ?>" class="rounded-circle" width="32" height="32"
                style="object-fit:cover;border:2px solid rgba(45,90,45,.2);" alt="<?= htmlspecialchars($userName) ?>">
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 zythera-dropdown mt-2" style="min-width:190px;">
              <?php if ($userRole !== 'admin'): ?>
                <li><a class="dropdown-item py-2" href="profile.php">My Profile</a></li>
              <?php endif; ?>
              <?php if ($userRole === 'admin'): ?>
                <li><a class="dropdown-item py-2" href="admin.php">Admin Panel</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider my-1"></li>
              <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openLogoutModal()">Logout</a></li>
            </ul>
          </div>
        </li>

        <?php if ($userRole !== 'admin'): ?>
        <!-- Cart -->
        <li class="nav-item">
          <a href="javascript:void(0)" onclick="openCart()" class="nav-cart-btn position-relative" title="Cart">
            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
              style="font-size:.5rem;background:var(--green);color:#fff;<?= $cartCount == 0 ? 'display:none;' : '' ?>">
              <?= $cartCount ?>
            </span>
          </a>
        </li>
        <?php endif; ?>

        <?php else: ?>
        <!-- Guest: Log In + Cart -->
        <li class="nav-item">
          <a href="logsign.php" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold ms-1">Log In</a>
        </li>
        <li class="nav-item">
          <a href="logsign.php" class="nav-cart-btn position-relative ms-1" title="Cart">
            <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
          </a>
        </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- Logout Confirmation Modal -->
<div id="logoutModalOverlay" class="logout-modal-overlay">
    <div class="logout-modal">
        <h2>Log Out Confirmation</h2>
        <p>Are you sure you want to log out of your account?</p>
        <div class="logout-modal-buttons">
            <button type="button" class="logout-cancel-btn" onclick="closeLogoutModal(event)">
                Stay
            </button>
            <button type="button" class="logout-confirm-btn" onclick="performLogout()">
                Logout
            </button>
        </div>
    </div>
</div>

<!-- ── HERO ── -->
<section class="about-hero">
  <div class="container">
    <span class="hero-badge">Our Story</span>
    <h1>Furniture made for<br><em>how you actually live.</em></h1>
    <p><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> started with a simple frustration, furniture that looked great in showrooms but fell short at home. We set out to fix that by curating pieces built to last, designed to feel right, and priced to be honest.</p>
  </div>
</section>

<!-- ── PHILOSOPHY ── -->
<section class="section">
  <div class="container">
    <div class="row align-items-center gy-5">
      <div class="col-lg-6">
        <p class="section-label">Our Philosophy</p>
        <div class="sage-divider"></div>
        <h2 class="section-title">We believe a room should<br>feel like a breath of fresh air.</h2>
        <p class="text-muted mb-4" style="line-height:1.8;font-size:.97rem;">
          Every piece in our collection goes through a strict review for comfort, build quality, and how it looks after two years, not just day one. If it doesn't pass our home test, it doesn't make the shelf.
        </p>
        <div class="d-flex flex-column gap-3">
          <div class="value-item">
            <div class="value-num">01</div>
            <div>
              <h6>Materials that age well</h6>
              <p>We favour solid wood, natural textiles, and metal that develops character over time, not veneers or materials that chip and fade.</p>
            </div>
          </div>
          <div class="value-item">
            <div class="value-num">02</div>
            <div>
              <h6>Comfort first, always</h6>
              <p>Every sofa, chair, and bed frame is tested for daily use not just to be photographed. If it isn't comfortable, it isn't <span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span>.</p>
            </div>
          </div>
          <div class="value-item">
            <div class="value-num">03</div>
            <div>
              <h6>Honest pricing</h6>
              <p>No inflated original prices. What you see is the real cost of a well-made piece, delivered to your door.</p>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="img-rounded" style="height:420px;">
          <img src="pci/image_8.png" alt="ZYTHERA furniture craftsmanship">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── METRICS ── -->
<div class="metric-strip">
  <div class="container">
    <div class="row g-0 justify-content-center">
      <div class="col-6 col-md-3">
        <div class="metric-item"><h3>50+</h3><p>Curated pieces across every room</p></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric-item"><h3>4.9</h3><p>Average customer rating out of 5</p></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric-item"><h3>95%</h3><p>Customers who order a second time</p></div>
      </div>
      <div class="col-6 col-md-3">
        <div class="metric-item"><h3>1 yrs</h3><p>Average warranty on structural pieces</p></div>
      </div>
    </div>
  </div>
</div>

<!-- ── WHY ZYTHERA ── -->
<section class="highlight-row">
  <div class="container">
    <div class="text-center mb-5">
      <p class="section-label">Why <span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></p>
      <div class="sage-divider mx-auto"></div>
      <h2 class="section-title">A different kind of furniture store</h2>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="feature-card">
          <div class="icon-wrap"><i class="fas fa-leaf"></i></div>
          <h5>Curated, not cluttered</h5>
          <p>We'd rather carry 100 great pieces than 300 average ones. Every product is hand-picked by our team before it reaches the site.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="icon-wrap"><i class="fas fa-ruler-combined"></i></div>
          <h5>Built for real homes</h5>
          <p>Apartment-sized, family-sized, rental-friendly; our collection is chosen with actual living spaces in mind, not staged showrooms.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="feature-card">
          <div class="icon-wrap"><i class="fas fa-headset"></i></div>
          <h5>Support that stays</h5>
          <p>Questions before you order, help when it arrives, and warranty support after. We're here through the whole experience, not just checkout.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── TEAM + CTA ── -->
<section class="section" style="background:var(--cream);">
  <div class="container">
    <div class="row align-items-start gy-5">
      <div class="col-lg-5">
        <div class="cta-panel">
          <span class="tag-badge" style="background:rgba(212,228,212,.15);color:var(--sage);border:1px solid rgba(212,228,212,.3);">Ready to start?</span>
          <h2>Find furniture that fits your space and your life.</h2>
          <p>Whether you're furnishing a studio or redesigning a family home, our team is here to help you choose pieces you'll actually love living with.</p>
          <div class="d-flex flex-wrap gap-3">
            <a href="website.php#products" class="btn-green btn">Browse Products</a>
            <a href="website.php#contact" class="btn-outline-green btn">Get in Touch</a>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <p class="section-label mb-1">The People Behind <span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></p>
        <div class="sage-divider"></div>
        <div class="row g-3">
          <div class="col-12">
            <div class="team-photo-card">
              <img src="pci/team.jpeg" alt="The ZYTHERA Team">
              <div class="team-caption">
                <h6>The <span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> Team</h6>
                <small>Design, sourcing, and customer experience all under one roof.</small>
              </div>
            </div>
          </div>
          <div class="col-12">
            <div class="feature-card" style="display:flex; align-items:center; gap:1rem; padding:1.25rem 1.5rem;">
              <div class="icon-wrap mb-0" style="flex-shrink:0;"><i class="fas fa-users"></i></div>
              <div>
                <h6 style="font-family:var(--logo-font);color:var(--deep);margin-bottom:.2rem;">A small team with high standards</h6>
                <p style="font-size:.88rem;margin:0;">Every order, inquiry, and delivery is handled by people who genuinely care about getting it right. Not a call centre, not a bot.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  

  <footer class="about-footer">
    <div class="container">
      <div class="row gy-4">
        <div class="col-12 col-md-4">
          <div class="d-flex align-items-center gap-2 mb-2">
            <img src="pci/Group_15.png" style="width:26px;opacity:.75;" alt="Zythera logo">
            <span style="font-family:'Playfair Display',serif;font-size:1.35rem;color:#fff;letter-spacing:2px;font-weight:700;">ZYTHERA</span>
          </div>
          <p style="font-size:.82rem;color:rgba(212,228,212,.55);line-height:1.75;max-width:210px;margin-top:8px;">Furniture crafted for lives that deserve stillness, beauty, and meaning.</p>
          <div class="d-flex gap-2 mt-3">
            <a href="#" class="about-footer-social" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="about-footer-social" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" class="about-footer-social" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <p class="about-footer-col-title">Navigate</p>
          <a href="website.php" class="about-footer-link">Home</a>
          <a href="website.php#products" class="about-footer-link">Products</a>
          <a href="about.php" class="about-footer-link">About</a>
          <a href="website.php#contact" class="about-footer-link">Contact</a>
        </div>
        <div class="col-6 col-md-3">
          <p class="about-footer-col-title">Contact Us</p>
          <a href="tel:+639123456789" class="about-footer-link"><i class="fas fa-phone me-2" style="font-size:.75rem;opacity:.55;"></i>+63 912 345 6789</a>
          <a href="mailto:zythera@gmail.com" class="about-footer-link"><i class="fas fa-envelope me-2" style="font-size:.75rem;opacity:.55;"></i>zythera@gmail.com</a>
          <span class="about-footer-link" style="cursor:default;"><i class="fas fa-map-marker-alt me-2" style="font-size:.75rem;opacity:.55;"></i>123 Furniture St, Philippines</span>
        </div>
        <div class="col-6 col-md-3">
          <p class="about-footer-col-title">Account</p>
          <?php if ($userEmail): ?>
          <a href="profile.php" class="about-footer-link">My Profile</a>
          <?php if ($userRole !== 'admin'): ?>
          <a href="orders.php" class="about-footer-link">My Orders</a>
          <?php endif; ?>
          <?php if ($userRole === 'admin'): ?>
          <a href="admin.php" class="about-footer-link">Admin Panel</a>
          <?php endif; ?>
          <?php else: ?>
          <a href="logsign.php" class="about-footer-link">Log In</a>
          <a href="logsign.php" class="about-footer-link">Sign Up</a>
          <?php endif; ?>
        </div>
      </div>
      <hr style="border-color:rgba(255,255,255,.08);margin:40px 0 0;">
      <div style="padding:18px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <span style="font-size:.77rem;color:rgba(212,228,212,.35);">&copy; <?= date('Y') ?> ZYTHERA. All rights reserved.</span>
        <span style="font-size:.77rem;color:rgba(212,228,212,.35);">Crafted with care in the Philippines.</span>
      </div>
    </div>
  </footer>

  <script src="assets/js/about.js"></script>
</body>
</html>