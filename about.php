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
  <style>
    :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Merriweather',serif}
    body{font-family:var(--ui-font);}
    h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
    p,small,.caption,.text-muted{font-family:var(--text-font);}
  </style>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dark-mode.css">
  <script src="dark-mode.js"></script>
  <style>

        :root {
      --logo-font: 'Playfair Display', serif;
      --ui-font: 'Roboto', sans-serif;
      --text-font: 'Merriweather', serif
    }

    body {
      font-family: var(--ui-font);
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    .navbar-brand,
    .brand-name,
    .section-title,
    .page-header h2,
    footer .footer-brand {
      font-family: var(--logo-font);
    }

    :root {
      --green: #2d5a2d;
      --sage:  #d4e4d4;
      --cream: #f5f2ec;
      --terra: #bc8a7b;
      --deep:  #1a2e1a;
      --transition: .22s ease;
    }
    * { font-family: var(--ui-font); }
    body { background: var(--cream); padding-top: 70px; }

    .navbar { background: #fff !important; box-shadow: 0 1px 12px rgba(0,0,0,.07); }
    .navbar-brand { font-family: var(--logo-font); color: var(--green) !important; font-size: 1.55rem; letter-spacing: 2px; }
    .nav-link {
      font-weight: 500;
      color: #555 !important;
      font-size: .875rem;
      letter-spacing: .2px;
      padding: 6px 4px !important;
      position: relative;
      transition: color var(--transition);
    }
    .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 2px;
      background: var(--green);
      border-radius: 2px;
      transform: scaleX(0);
      transition: transform var(--transition);
    }
    .nav-link:hover { color: var(--green) !important; }
    .nav-link:hover::after { transform: scaleX(1); }

    /* User capsule in nav */
    .nav-user-capsule {
      display: flex;
      align-items: center;
      background: #ffffff;
      border-radius: 50px;
      padding: 6px 12px 6px 6px;
      gap: 8px;
      border: 1px solid rgba(45,90,45,.12);
      transition: all var(--transition);
      box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .nav-user-capsule:hover {
      border-color: rgba(45,90,45,.25);
      box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }
    .nav-user-capsule img {
      border: 2.5px solid rgba(45,90,45,.15);
      transition: border-color var(--transition);
    }
    .nav-user-capsule:hover img {
      border-color: rgba(45,90,45,.3);
    }
    body.dark .nav-user-capsule {
      background: #1f2937;
      border-color: rgba(168,212,168,.15);
    }
    body.dark .nav-user-capsule:hover {
      background: #2d3748;
      border-color: rgba(168,212,168,.3);
    }

    /* ── LOGOUT CONFIRMATION MODAL ── */
    .logout-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.6);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px);
    }
    .logout-modal-overlay.active { display: flex; }

    .logout-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px;
      width: min(420px, calc(100vw - 32px));
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      text-align: center;
      animation: slideDown .3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logout-modal h2 {
      font-family: 'Playfair Display', serif;
      color: var(--deep);
      font-size: 1.3rem;
      margin: 0 0 12px 0;
      font-weight: 700;
    }

    .logout-modal p {
      color: #666;
      font-size: .95rem;
      margin: 0 0 24px 0;
      line-height: 1.5;
    }

    .logout-modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .logout-modal-buttons button {
      padding: 12px 28px;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      font-size: .9rem;
      cursor: pointer;
      transition: .2s ease;
      font-family: var(--ui-font);
    }

    .logout-cancel-btn {
      background: #f0ece4;
      color: #555;
    }
    .logout-cancel-btn:hover {
      background: #e2ddd4;
    }

    .logout-confirm-btn {
      background: var(--green);
      color: #fff;
      min-width: 120px;
    }
    .logout-confirm-btn:hover {
      background: var(--deep);
    }
    .logout-confirm-btn:active {
      transform: scale(0.98);
    }

    .about-hero { background: var(--deep); color: #fff; padding: 5rem 0 4rem; position: relative; overflow: hidden; }
    .about-hero::after { content: ''; position: absolute; inset: 0; background: url('pci/download_(4).jpeg') center/cover no-repeat; opacity: .18; z-index: 0; }
    .about-hero .container { position: relative; z-index: 1; }
    .about-hero h1 { font-family: var(--logo-font); font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 700; line-height: 1.25; margin-bottom: 1rem; }
    .about-hero p { font-size: 1.05rem; opacity: .8; max-width: 540px; line-height: 1.8; }
    .hero-badge { display: inline-block; background: rgba(212,228,212,.15); border: 1px solid rgba(212,228,212,.35); color: var(--sage); font-size: .72rem; letter-spacing: 3px; text-transform: uppercase; padding: .35rem .9rem; border-radius: 50px; margin-bottom: 1.25rem; }

    .section { padding: 64px 0; }
    .section-label { font-size: .75rem; letter-spacing: 3px; text-transform: uppercase; color: #7b8c75; margin-bottom: .5rem; }
    .section-title { font-family: var(--logo-font); color: var(--green); font-size: 1.9rem; margin-bottom: 1.25rem; }

    .feature-card { background: #fff; border-radius: 20px; padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,.07); border: none; height: 100%; transition: transform .25s, box-shadow .25s; }
    .feature-card:hover { transform: translateY(-4px); box-shadow: 0 14px 38px rgba(0,0,0,.11); }
    .feature-card .icon-wrap { width: 44px; height: 44px; background: var(--sage); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
    .feature-card .icon-wrap i { color: var(--green); font-size: 1.1rem; }
    .feature-card h5 { font-family: var(--logo-font); color: var(--deep); font-size: 1.05rem; margin-bottom: .6rem; }
    .feature-card p { color: #55605b; line-height: 1.75; font-size: .93rem; margin: 0; }

    .metric-strip { background: var(--green); padding: 3.5rem 0; }
    .metric-item { text-align: center; padding: 1rem; }
    .metric-item h3 { font-family: var(--logo-font); font-size: 2.4rem; color: #fff; margin-bottom: .4rem; }
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
.navbar-brand span { font-family: 'Playfair Display', serif; }
    }
    .team-photo-card:hover { transform: translateY(-4px); box-shadow: 0 14px 38px rgba(0,0,0,.11); }
   .team-photo-card img { width: 100%; height: 250px; object-fit: cover; object-position: center 60%; display: block; }
   .team-photo-card .team-caption { padding: 1.25rem 1.5rem; }
    .team-photo-card h6 { font-family: var(--logo-font); color: var(--deep); font-size: 1.05rem; margin-bottom: .3rem; }
    .team-photo-card small { color: #7b8c75; font-size: .82rem; }

    .cta-panel { background: var(--deep); border-radius: 20px; padding: 3rem; color: #fff; box-shadow: 0 4px 20px rgba(0,0,0,.13); }
    .cta-panel h2 { font-family: var(--logo-font); font-size: 1.8rem; color: #fff; margin-bottom: .85rem; }
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

    /* ── FOOTER ── */
    .about-footer {
      background: var(--deep);
      padding: 56px 0 0;
      margin-top: 0;
    }
    .about-footer-col-title {
      font-size: .7rem;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      color: rgba(212,228,212,.4);
      margin-bottom: 14px;
      font-weight: 600;
    }
    .about-footer-link {
      display: block;
      color: rgba(212,228,212,.65);
      text-decoration: none;
      font-size: .84rem;
      padding: 5px 0;
      transition: color .15s;
    }
    .about-footer-link:hover { color: #fff; }
    .about-footer-social {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: rgba(255,255,255,.07);
      color: rgba(212,228,212,.65);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .82rem;
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .about-footer-social:hover { background: var(--green); color: #fff; }

    /* ── Dark mode footer ── */
    body.dark .about-footer { background: #0a140a !important; }
    body.dark .about-footer * { color: rgba(212,228,212,.65) !important; }
    body.dark .about-footer .about-footer-link:hover,
    body.dark .about-footer .about-footer-social:hover { color: #fff !important; }
  </style>
<script>
/* ZYTHERA dark mode — apply before paint to prevent flash */
(function(){
  if(localStorage.getItem('zythera_dark')==='1'){
    document.documentElement.style.background='#111e11';
    document.addEventListener('DOMContentLoaded',function(){
      document.body.classList.add('dark');
      document.documentElement.style.background='';
    });
  }
})();
</script>
<link rel="stylesheet" href="responsive.css">
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="website.php"><span style="font-family: 'Playfair Display', serif; color: var(--deep); font-weight: 700;"> ZYTHERA </span></a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
          <a href="website.php#products" class="nav-link fw-semibold" style="color:var(--green)!important;">Products</a>
          <a href="about.php" class="nav-link fw-semibold" style="color:var(--green)!important;">About</a>
          <a href="website.php#contact" class="nav-link fw-semibold" style="color:var(--green)!important;">Contact Us</a>
          <?php if ($userEmail && $userRole !== 'admin'): ?>
            <a href="profile.php?tab=orders" class="nav-link fw-semibold" style="color:var(--green)!important;">My Orders</a>
          <?php endif; ?>
          <?php if ($userEmail): ?>
            <div class="nav-user-capsule">
              <div class="text-end d-none d-md-block">
                <p class="mb-0 fw-bold" style="font-size:.78rem;color:var(--green);"><?= htmlspecialchars($userName) ?></p>
                <?php if ($loginTime): ?>
                  <small class="text-muted" style="font-size:.6rem;"><span id="liveTime"></span></small>
                <?php endif; ?>
              </div>
              <div class="dropdown">
                <?php
                    $navPic = getAvatarURL($uObj->profile_pic ?? null, $uObj->email ?? null, $userName, 34);
                ?>
                <img src="<?= htmlspecialchars($navPic) ?>" class="rounded-circle" width="32" height="32" style="cursor:pointer;border:2px solid rgba(45,90,45,.2);object-fit:cover;" data-bs-toggle="dropdown" alt="<?= htmlspecialchars($userName) ?>">
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;min-width:190px;">
                  <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-muted" style="font-size:.85rem;"></i>My Profile</a></li>
                  <?php if ($userRole === 'admin'): ?>
                    <li><a class="dropdown-item py-2" href="admin.php"><i class="fas fa-user-shield me-2 text-muted" style="font-size:.85rem;"></i>Admin Panel</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider my-1"></li>
                  <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt me-2" style="font-size:.85rem;"></i>Logout</a></li>
                </ul>
              </div>
            </div>
          <?php else: ?>
            <a href="logsign.php" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold">Log In</a>
          <?php endif; ?>
        </div>
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
  <script>
    function openLogoutModal() {
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }

    function closeLogoutModal(event) {
      if (event && event.target.id !== 'logoutModalOverlay') return;
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    }

    function performLogout() {
      const confirmBtn = document.querySelector('.logout-btn-confirm');
      if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Logging out...';
      }
      window.location.href = 'logout.php';
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeLogoutModal();
    });

    document.addEventListener('click', function(e) {
      const overlay = document.getElementById('logoutModalOverlay');
      if (overlay && e.target === overlay) closeLogoutModal(e);
    });

    // ── Live time update for nav-user-capsule ──
    function updateTime() {
      const el = document.getElementById('liveTime');
      if (el) el.textContent = new Date().toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }
    setInterval(updateTime, 1000);
    updateTime();
  </script>

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
</body>
</html>