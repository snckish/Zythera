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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Lora',serif}
    body{font-family:var(--ui-font);}
    h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
    p,small,.caption,.text-muted{font-family:var(--text-font);}
  </style>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    :root {
      --green: #2d5a2d;
      --sage:  #d4e4d4;
      --cream: #f5f2ec;
      --terra: #bc8a7b;
      --deep:  #1a2e1a;
    }
    * { font-family: 'DM Sans', sans-serif; }
    body { background: var(--cream); padding-top: 70px; }

    .navbar { background: #fff !important; box-shadow: 0 1px 12px rgba(0,0,0,.07); }
    .navbar-brand { font-family: 'Playfair Display', serif; color: var(--green) !important; font-size: 1.55rem; letter-spacing: 2px; }
    .nav-link { font-weight: 500; color: #444 !important; font-size: .9rem; }
    .nav-link:hover { color: var(--green) !important; }

    .about-hero { background: var(--deep); color: #fff; padding: 5rem 0 4rem; position: relative; overflow: hidden; }
    .about-hero::after { content: ''; position: absolute; inset: 0; background: url('pci/download_(4).jpeg') center/cover no-repeat; opacity: .18; z-index: 0; }
    .about-hero .container { position: relative; z-index: 1; }
    .about-hero h1 { font-family: 'Playfair Display', serif; font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 700; line-height: 1.25; margin-bottom: 1rem; }
    .about-hero p { font-size: 1.05rem; opacity: .8; max-width: 540px; line-height: 1.8; }
    .hero-badge { display: inline-block; background: rgba(212,228,212,.15); border: 1px solid rgba(212,228,212,.35); color: var(--sage); font-size: .72rem; letter-spacing: 3px; text-transform: uppercase; padding: .35rem .9rem; border-radius: 50px; margin-bottom: 1.25rem; }

    .section { padding: 64px 0; }
    .section-label { font-size: .75rem; letter-spacing: 3px; text-transform: uppercase; color: #7b8c75; margin-bottom: .5rem; }
    .section-title { font-family: 'Playfair Display', serif; color: var(--green); font-size: 1.9rem; margin-bottom: 1.25rem; }

    .feature-card { background: #fff; border-radius: 20px; padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,.07); border: none; height: 100%; transition: transform .25s, box-shadow .25s; }
    .feature-card:hover { transform: translateY(-4px); box-shadow: 0 14px 38px rgba(0,0,0,.11); }
    .feature-card .icon-wrap { width: 44px; height: 44px; background: var(--sage); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
    .feature-card .icon-wrap i { color: var(--green); font-size: 1.1rem; }
    .feature-card h5 { font-family: 'Playfair Display', serif; color: var(--deep); font-size: 1.05rem; margin-bottom: .6rem; }
    .feature-card p { color: #55605b; line-height: 1.75; font-size: .93rem; margin: 0; }

    .metric-strip { background: var(--green); padding: 3.5rem 0; }
    .metric-item { text-align: center; padding: 1rem; }
    .metric-item h3 { font-family: 'Playfair Display', serif; font-size: 2.4rem; color: #fff; margin-bottom: .4rem; }
    .metric-item p { color: var(--sage); font-size: .88rem; margin: 0; line-height: 1.6; }

    .sage-divider { background: var(--sage); height: 3px; width: 48px; border-radius: 4px; margin-bottom: 1.5rem; }
    .highlight-row { background: #fff; padding: 5rem 0; }

    .img-rounded { border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.09); }
    .img-rounded img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* ── TEAM PHOTO CARD ── */
    .team-photo-card {
      background: #fff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,.07);
      transition: transform .25s, box-shadow .25s;
    }
    .team-photo-card:hover { transform: translateY(-4px); box-shadow: 0 14px 38px rgba(0,0,0,.11); }
   .team-photo-card img { width: 100%; height: 250px; object-fit: cover; object-position: center 60%; display: block; }
   .team-photo-card .team-caption { padding: 1.25rem 1.5rem; }
    .team-photo-card h6 { font-family: 'Playfair Display', serif; color: var(--deep); font-size: 1.05rem; margin-bottom: .3rem; }
    .team-photo-card small { color: #7b8c75; font-size: .82rem; }

    .cta-panel { background: var(--deep); border-radius: 20px; padding: 3rem; color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.13); }
    .cta-panel h2 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: #fff; margin-bottom: .85rem; }
    .cta-panel p { color: rgba(255,255,255,.72); line-height: 1.75; margin-bottom: 1.5rem; }

    .btn-green { background: var(--green); color: #fff; border: none; border-radius: 50px; padding: .65rem 1.6rem; font-weight: 600; font-size: .9rem; transition: .2s; }
    .btn-green:hover { background: var(--deep); color: #fff; }
    .btn-outline-green { background: transparent; color: #fff; border: 2px solid rgba(255,255,255,.45); border-radius: 50px; padding: .6rem 1.6rem; font-weight: 600; font-size: .9rem; transition: .2s; }
    .btn-outline-green:hover { border-color: #fff; color: #fff; }

    .tag-badge { background: var(--sage); color: var(--green); font-size: .72rem; font-weight: 600; border-radius: 6px; padding: 3px 10px; display: inline-block; margin-bottom: .75rem; }

    .value-item { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
    .value-num { flex-shrink: 0; width: 36px; height: 36px; background: var(--sage); color: var(--green); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .88rem; }
    .value-item p { color: #55605b; line-height: 1.75; font-size: .93rem; margin: 0; }
    .value-item h6 { color: var(--deep); font-weight: 600; margin-bottom: .25rem; }

    @media (max-width: 768px) {
      .about-hero { padding: 3.5rem 0 3rem; }
      .cta-panel { padding: 2rem 1.5rem; }
      .metric-item h3 { font-size: 1.9rem; }
      .team-photo-card img { height: 240px; }
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
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
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
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- ── HERO ── -->
  <section class="about-hero">
    <div class="container">
      <span class="hero-badge">Our Story</span>
      <h1>Furniture made for<br><em>how you actually live.</em></h1>
      <p>ZYTHERA started with a simple frustration — furniture that looked great in showrooms but fell short at home. We set out to fix that by curating pieces built to last, designed to feel right, and priced to be honest.</p>
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
            Every piece in our collection goes through a strict review — for comfort, build quality, and how it looks after two years, not just day one. If it doesn't pass our home test, it doesn't make the shelf.
          </p>
          <div class="d-flex flex-column gap-3">
            <div class="value-item">
              <div class="value-num">01</div>
              <div>
                <h6>Materials that age well</h6>
                <p>We favour solid wood, natural textiles, and metal that develops character over time — not veneers or materials that chip and fade.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-num">02</div>
              <div>
                <h6>Comfort first, always</h6>
                <p>Every sofa, chair, and bed frame is tested for daily use — not just to be photographed. If it isn't comfortable, it isn't ZYTHERA.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-num">03</div>
              <div>
                <h6>Honest pricing</h6>
                <p>No inflated "original prices." What you see is the real cost of a well-made piece, delivered to your door.</p>
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
          <div class="metric-item"><h3>250+</h3><p>Curated pieces across every room</p></div>
        </div>
        <div class="col-6 col-md-3">
          <div class="metric-item"><h3>4.9</h3><p>Average customer rating out of 5</p></div>
        </div>
        <div class="col-6 col-md-3">
          <div class="metric-item"><h3>95%</h3><p>Customers who order a second time</p></div>
        </div>
        <div class="col-6 col-md-3">
          <div class="metric-item"><h3>3 yrs</h3><p>Average warranty on structural pieces</p></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── WHY ZYTHERA ── -->
  <section class="highlight-row">
    <div class="container">
      <div class="text-center mb-5">
        <p class="section-label">Why ZYTHERA</p>
        <div class="sage-divider mx-auto"></div>
        <h2 class="section-title">A different kind of furniture store</h2>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card">
            <div class="icon-wrap"><i class="fas fa-leaf"></i></div>
            <h5>Curated, not cluttered</h5>
            <p>We'd rather carry 250 great pieces than 2,500 average ones. Every product is hand-picked by our team before it reaches the site.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="icon-wrap"><i class="fas fa-ruler-combined"></i></div>
            <h5>Built for real homes</h5>
            <p>Apartment-sized, family-sized, rental-friendly — our collection is chosen with actual living spaces in mind, not staged showrooms.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="feature-card">
            <div class="icon-wrap"><i class="fas fa-headset"></i></div>
            <h5>Support that stays</h5>
            <p>Questions before you order, help when it arrives, and warranty support after — we're here through the whole experience, not just checkout.</p>
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
          <p class="section-label mb-1">The People Behind ZYTHERA</p>
          <div class="sage-divider"></div>
          <div class="row g-3">
            <!-- ── REAL TEAM PHOTO ── -->
            <div class="col-12">
              <div class="team-photo-card">
                <img src="../team.jpeg" alt="The ZYTHERA Team">
                <div class="team-caption">
                  <h6>The ZYTHERA Team</h6>
                  <small>Design, sourcing, and customer experience — all under one roof.</small>
                </div>
              </div>
            </div>
            <!-- ── TAGLINE CARD ── -->
            <div class="col-12">
              <div class="feature-card" style="display:flex; align-items:center; gap:1rem; padding:1.25rem 1.5rem;">
                <div class="icon-wrap mb-0" style="flex-shrink:0;"><i class="fas fa-users"></i></div>
                <div>
                  <h6 style="font-family:'Playfair Display',serif;color:var(--deep);margin-bottom:.2rem;">A small team with high standards</h6>
                  <p style="font-size:.88rem;margin:0;">Every order, inquiry, and delivery is handled by people who genuinely care about getting it right — not a call centre, not a bot.</p>
                </div>
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