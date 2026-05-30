<?php
require 'config.php';
$userEmail = $_SESSION['logged_in_user'] ?? null;
$userName = null;
if ($userEmail) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$userEmail]);
    $uObj = $stmt->fetch();
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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    :root {
      --green: #2d5a2d;
      --sage: #d4e4d4;
      --cream: #f5f2ec;
      --terra: #bc8a7b;
      --deep: #1a2e1a;
      --soft: #f7f6f1;
    }
    * {
      font-family: 'DM Sans', sans-serif;
    }
    body {
      background: var(--cream);
      padding-top: 70px;
    }
    .navbar {
      background: #fff !important;
      box-shadow: 0 1px 15px rgba(0, 0, 0, .08);
    }
    .navbar-brand {
      font-family: 'Playfair Display', serif;
      color: var(--green) !important;
      font-size: 1.55rem;
      letter-spacing: 2px;
    }
    .nav-link {
      font-weight: 500;
      color: #444 !important;
      font-size: .95rem;
    }
    .nav-link:hover {
      color: var(--green) !important;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.5rem, 4vw, 3rem);
      color: var(--deep);
      margin-bottom: 1.5rem;
    }
    .feature-card,
    .metric-card,
    .panel-card {
      background: #fff;
      border-radius: 28px;
      padding: 2rem;
      box-shadow: 0 22px 50px rgba(20, 32, 24, .06);
      border: 1px solid rgba(45, 90, 45, .08);
      height: 100%;
    }
    .metric-card h3 {
      font-size: 2.3rem;
      color: var(--green);
      margin-bottom: .75rem;
    }
    .metric-card p,
    .feature-card p {
      color: #55605b;
      line-height: 1.8;
    }
    .highlight-row {
      background: var(--soft);
      padding: 4rem 0;
    }
    .stats-list {
      letter-spacing: 1px;
      text-transform: uppercase;
      color: #6b7256;
      font-size: .8rem;
      margin-bottom: 1rem;
    }
    .cta-panel {
      border-radius: 28px;
      padding: 2.5rem;
      background: var(--deep);
      color: #fff;
      box-shadow: 0 20px 50px rgba(0,0,0,.18);
    }
    .cta-panel h2 {
      margin-bottom: 1rem;
      color: #fff;
    }
    .team-card {
      background: #fff;
      border-radius: 24px;
      padding: 1.75rem;
      box-shadow: 0 18px 40px rgba(20, 32, 24, .06);
      text-align: center;
    }
    .team-card img {
      width: 100%;
      border-radius: 20px;
      object-fit: cover;
      margin-bottom: 1.25rem;
    }
    .team-card h5 {
      margin-bottom: .5rem;
      color: var(--deep);
    }
    .team-card small {
      color: #7b8c75;
    }
    @media (max-width: 768px) {
      .hero {
        min-height: auto;
        padding: 4rem 0;
      }
      .hero-content {
        padding: 2.5rem 1rem;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="website.php">ZYTHERA</a>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
          <a href="website.php#products" class="nav-link fw-semibold" style="color:var(--green)!important;">Products</a>
          <a href="about.php" class="nav-link fw-semibold" style="color:var(--green)!important;">About</a>
          <a href="website.php#contact" class="nav-link fw-semibold" style="color:var(--green)!important;">Contact Us</a>
          <?php if ($userEmail): ?>
            <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border shadow-sm gap-2">
              <div class="text-end d-none d-md-block">
                <p class="mb-0 fw-bold" style="font-size:.8rem;color:var(--green);"><?= htmlspecialchars($userName) ?></p>
                <?php if ($loginTime): ?>
                  <small class="text-muted" style="font-size:.6rem;">Logged in</small>
                <?php endif; ?>
              </div>
              <div class="dropdown">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($userName) ?>&background=2d5a2d&color=fff"
                  class="rounded-circle" width="34" style="cursor:pointer;" data-bs-toggle="dropdown"
                  alt="<?= htmlspecialchars($userName) ?>">
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                  <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                  <?php if ($userRole === 'admin'): ?>
                    <li><a class="dropdown-item py-2" href="admin.php"><i class="fas fa-user-shield me-2"></i>Admin Panel</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
              </div>
            </div>
            <?php if ($userRole !== 'admin'): ?>
            <a href="javascript:void(0)" onclick="openCart()" class="position-relative text-decoration-none d-flex align-items-center" title="Cart" style="color:var(--green);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
              <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                style="font-size:.55rem;background:var(--green);color:#fff;<?= $cartCount == 0 ? 'display:none;' : '' ?>">
                <?= $cartCount ?>
              </span>
            </a>
            <?php endif; ?>
          <?php else: ?>
            <a href="logsign.php" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold">Log In</a>
            <a href="logsign.php" class="position-relative text-decoration-none d-flex align-items-center" title="Cart" style="color:var(--green);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <section class="section py-5">
    <div class="container">
      <div class="row align-items-center gy-5">
        <div class="col-lg-6">
          <p class="text-uppercase text-secondary mb-3" style="letter-spacing: 3px; font-size: .8rem;">Our Philosophy</p>
          <h2 class="section-title">Design that feels easy to live with.</h2>
          <p class="text-muted mb-4" style="font-size:1.05rem; line-height:1.8;">At ZYTHERA, we create furniture for everyday moments. Each piece is chosen to bring warmth, function, and a restful aesthetic to your home without overwhelming the room.</p>
          <div class="row g-3">
            <div class="col-12">
              <div class="feature-card">
                <h5 class="fw-bold mb-3">Thoughtful form and function</h5>
                <p>We look for simple silhouettes, premium finishes, and materials that look richer over time.</p>
              </div>
            </div>
            <div class="col-12">
              <div class="feature-card">
                <h5 class="fw-bold mb-3">Comfort as a priority</h5>
                <p>Every selection is vetted for usability and comfort so your home feels welcoming and easy to enjoy.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="rounded-4 overflow-hidden shadow-lg" style="background: #f8f7f2;">
            <img src="pci/image_8.png" class="img-fluid" alt="ZYTHERA furniture" style="display:block; width:100%; height:100%; object-fit:cover;">
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="highlight-row">
    <div class="container">
      <div class="text-center mb-5">
        <p class="text-uppercase text-secondary mb-2" style="letter-spacing: 3px; font-size: .75rem;">By the numbers</p>
        <h2 class="section-title">The experience behind every order.</h2>
      </div>
      <div class="row g-4 text-center">
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <h3>250+</h3>
            <p>Unique pieces chosen for quality and attention to detail.</p>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <h3>95%</h3>
            <p>Customers return for a second purchase because they love the collection.</p>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <h3>4.9/5</h3>
            <p>Average rating for product quality, styling, and service.</p>
          </div>
        </div>
        <div class="col-sm-6 col-lg-3">
          <div class="metric-card">
            <h3>100%</h3>
            <p>Committed support from first browse to final delivery.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section py-5">
    <div class="container">
      <div class="text-center mb-5">
        <p class="text-uppercase text-secondary mb-2" style="letter-spacing: 3px; font-size: .75rem;">Why ZYTHERA</p>
        <h2 class="section-title">A better furniture experience</h2>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card h-100">
            <h5 class="fw-bold mb-3">Curated quality</h5>
            <p>We only offer pieces that meet our standards for comfort, appearance, and build.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card h-100">
            <h5 class="fw-bold mb-3">Modern warmth</h5>
            <p>Our collection combines soft textures, natural tones, and clean lines for a timeless look.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card h-100">
            <h5 class="fw-bold mb-3">Support you can trust</h5>
            <p>We're here to help with selection, ordering, and ensuring your furniture fits your space.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section py-5">
    <div class="container">
      <div class="row align-items-center gy-4">
        <div class="col-lg-6">
          <div class="cta-panel">
            <p class="text-uppercase text-secondary mb-2" style="letter-spacing: 4px; opacity: .8;">Ready to refresh your space?</p>
            <h2>Explore furniture that feels made for your home.</h2>
            <p class="text-white-75 mb-4">Whether you're updating one room or styling a whole house, we make it simple to choose pieces that look beautiful and work beautifully.</p>
            <div class="d-flex flex-wrap gap-3">
              <a href="website.php#products" class="btn btn-light btn-lg rounded-pill px-4">Browse Products</a>
              <a href="website.php#contact" class="btn btn-outline-light btn-lg rounded-pill px-4">Contact Us</a>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="row g-4">
            <div class="col-sm-6">
              <div class="team-card">
                <img src="https://images.unsplash.com/photo-1540574163026-643ea20ade25?auto=format&fit=crop&w=500&q=60" alt="Design lead">
                <h5>Anna Reyes</h5>
                <small>Creative Director</small>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="team-card">
                <img src="https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=500&q=60" alt="Customer support">
                <h5>Marco Santos</h5>
                <small>Customer Care</small>
              </div>
            </div>
            <div class="col-12">
              <div class="team-card">
                <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?auto=format&fit=crop&w=1200&q=60" alt="Team collaboration">
                <h5>Our Team</h5>
                <small>Design, sourcing, and support working together</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
