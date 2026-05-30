<?php
require 'config.php';
$userEmail  = $_SESSION['logged_in_user'] ?? null;
$userName   = ($userEmail && isset($_SESSION['users'][$userEmail]))
  ? $_SESSION['users'][$userEmail]['name'] : null;
$userRole   = $_SESSION['role'] ?? 'user';
$loginTime  = $_SESSION['login_time'] ?? null;


$cartCount = 0;
if ($userEmail && isset($_SESSION['cart'][$userEmail])) {
  foreach ($_SESSION['cart'][$userEmail] as $item) {
    $cartCount += is_array($item) ? (int)($item['qty'] ?? 1) : 1;
  }
}


$inventory = array_values($_SESSION['inventory'] ?? []);
usort($inventory, function ($a, $b) {
  $ia = is_object($a) ? (int)$a->id : (int)($a['id'] ?? 0);
  $ib = is_object($b) ? (int)$b->id : (int)($b['id'] ?? 0);
  return $ia <=> $ib;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZAFIRAH | FURNITURE</title>
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
      box-shadow: 0 1px 12px rgba(0, 0, 0, .07);
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
      font-size: .9rem;
    }

    .nav-link:hover {
      color: var(--green) !important;
    }

    .hero {
      position: relative;
      height: 92vh;
      min-height: 480px;
      overflow: hidden;
      background: linear-gradient(135deg, var(--deep) 0%, var(--sage) 55%, #4a7c4a 100%);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .hero-text {
      max-width: 700px;
      text-align: center;
      color: #fff;
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.3rem, 3vw, 2rem);
      font-style: italic;
      line-height: 1.7;
      padding: 40px;
      border: 1px solid rgba(147, 174, 153, 0.2);
      border-radius: 6px;
      background: rgba(0, 0, 0, .18);
      backdrop-filter: blur(6px);
    }

    .hero-cta {
      margin-top: 24px;
      display: inline-block;
      padding: 12px 32px;
      background: rgba(255, 255, 255, .15);
      border: 2px solid rgba(255, 255, 255, .5);
      border-radius: 50px;
      color: #fff;
      text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem;
      font-weight: 600;
      backdrop-filter: blur(4px);
      transition: .25s;
    }

    .hero-cta:hover {
      background: rgba(255, 255, 255, .3);
      color: #fff;
    }

    /* SECTION */
    .section {
      padding: 64px 0;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      color: var(--green);
      font-size: 1.9rem;
      margin-bottom: 36px;
    }

    /* PRODUCT CARD */
    .product-card {
      border: none;
      border-radius: 20px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
      transition: transform .25s, box-shadow .25s;
      height: 100%;
    }

    .product-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 14px 38px rgba(0, 0, 0, .13);
    }

    .product-card img {
      height: 220px;
      object-fit: cover;
      width: 100%;
    }

    .product-name {
      font-family: 'Playfair Display', serif;
      color: var(--green);
      font-size: 1.05rem;
    }

    .product-price {
      color: var(--terra);
      font-size: 1.2rem;
      font-weight: 700;
    }

    .stock-badge {
      background: var(--green);
      color: #fff;
      font-size: .7rem;
      border-radius: 6px;
      padding: 3px 8px;
    }

    .btn-cart {
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: .65rem 1rem;
      font-weight: 600;
      width: 100%;
      font-family: 'DM Sans', sans-serif;
      transition: .2s;
    }

    .btn-cart:hover {
      background: var(--deep);
      color: #fff;
    }

    .btn-cart:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    /* REVIEWS */
    .review-wrap {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
      gap: 18px;
    }

    .review-item {
      background: #fff;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
    }

    .review-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
    }

    .stars {
      color: darkgreen;
      font-size: .88rem;
    }

    /* CONTACT */
    .contact-card {
      background: #fff;
      border-radius: 20px;
      padding: 36px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
    }

    .input-box {
      position: relative;
      margin-bottom: 20px;
    }

    .input-box input,
    .input-box textarea {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--sage);
      border-radius: 12px;
      font-size: .9rem;
      background: var(--cream);
      outline: none;
      font-family: 'DM Sans', sans-serif;
      transition: .2s;
    }

    .input-box input:focus,
    .input-box textarea:focus {
      border-color: var(--green);
      background: #fff;
    }

    .input-box label {
      position: absolute;
      top: 15px;
      left: 16px;
      font-size: .84rem;
      color: #999;
      pointer-events: none;
      transition: .2s;
      background: transparent;
    }

    .input-box input:focus~label,
    .input-box input:not(:placeholder-shown)~label,
    .input-box textarea:focus~label,
    .input-box textarea:not(:placeholder-shown)~label {
      top: 0;
      font-size: .7rem;
      background: #fff;
      padding: 0 4px;
      color: var(--green);
      border-radius: 4px;
    }

    .input-box textarea {
      min-height: 100px;
      resize: none;
    }

    .social-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--sage);
      color: var(--green);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: .2s;
    }

    .social-icon:hover {
      background: var(--green);
      color: #fff;
    }

    .footer {
      display: flex;
      align-items: center;
      /* vertical align */
      justify-content: center;
      /* center horizontally */
      gap: 20px;
      /* space between logo & text */
      padding: 20px;
    }

    .footer-logo {
      width: 30px;
    }

    .brand {
      font-size: 20px;
      font-weight: bold;
      color: var(--deep-green);
    }

    .toast-fixed {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--green);
      color: #fff;
      padding: 14px 22px;
      border-radius: 12px;
      font-size: .86rem;
      z-index: 9999;
      opacity: 0;
      transform: translateY(10px);
      transition: .3s;
      pointer-events: none;
      max-width: 300px;
      box-shadow: 0 6px 24px rgba(0, 0, 0, .2);
    }

    .toast-fixed.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast-fixed.error {
      background: #dc2626;
    }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="website.php">ZAFIRAH</a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
          <a href="#products" class="nav-link fw-semibold" style="color:var(--green)!important;">Products</a>
          <a href="#about" class="nav-link fw-semibold" style="color:var(--green)!important;">About</a>
          <?php if ($userEmail): ?>

            <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border shadow-sm gap-2">
              <div class="text-end d-none d-md-block">
                <p class="mb-0 fw-bold" style="font-size:.8rem;color:var(--green);"><?= htmlspecialchars($userName) ?></p>
                <?php if ($loginTime): ?>
                  <small class="text-muted" style="font-size:.6rem;"><span id="liveTime"></span></small>
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
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
              </div>
            </div>

            <!-- Cart icon — hidden for admin -->
            <?php if ($userRole !== 'admin'): ?>
            <a href="javascript:void(0)" onclick="openCart()" class="position-relative text-decoration-none d-flex align-items-center" title="Cart" style="color:var(--green);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
              <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                style="font-size:.55rem;background:var(--green);color:#fff;">
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

  <!-- HERO -->
  <div class="hero">
    <img src="/php_work/e-commerce/pci/image_8.png" class="hero-img">
    <div class="hero-text">
      "A quiet collection of furniture crafted for lives that deserve stillness, beauty, and meaning."
      <br>
      <a href="#products" class="hero-cta mt-3 d-inline-block">Explore Collection ↓</a>
    </div>
  </div>

  <!-- PRODUCTS -->
  <section class="section" id="products">
    <div class="container">
      <h2 class="section-title text-center">Our Collection</h2>

      <?php if (empty($inventory)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-couch fa-3x mb-3 opacity-25"></i>
          <p>No products yet. <?php if ($userRole === 'admin'): ?><a href="admin.php">Add products →</a><?php endif; ?></p>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($inventory as $item):
            $item       = (object)$item;
            $cleanPrice = (float)str_replace([',', '₱', ' '], '', $item->price);
            $outOfStock = (int)$item->stock === 0;
          ?>
            <div class="col-sm-6 col-lg-4">
              <div class="product-card">
                <img src="<?= htmlspecialchars($item->image ?? '') ?>"
                  alt="<?= htmlspecialchars($item->name) ?>"
                  onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400&h=220&fit=crop'">
                <div class="p-4">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6 class="product-name mb-0"><?= htmlspecialchars($item->name) ?></h6>
                    <span class="stock-badge <?= $outOfStock ? 'bg-danger' : '' ?>">
                      <?= $outOfStock ? 'Out of Stock' : 'Stock: ' . $item->stock ?>
                    </span>
                  </div>
                  <div class="product-price mb-2">₱<?= number_format($cleanPrice) ?></div>
                  <div class="small text-muted mb-2">
                    <?php if (!empty($item->size)):  ?><span><b>Size:</b> <?= htmlspecialchars($item->size) ?></span><?php endif; ?>
                    <?php if (!empty($item->color)): ?> &nbsp;·&nbsp; <span><b>Color:</b> <?= htmlspecialchars($item->color) ?></span><?php endif; ?>
                  </div>
                  <p class="small text-dark mb-3" style="line-height:1.5;">
                    <span class="desc-short"><?= htmlspecialchars(mb_substr($item->description ?? '', 0, 70)) ?>...</span>
                    <span class="desc-full d-none"><?= htmlspecialchars($item->description ?? '') ?></span>
                    <a href="javascript:void(0)" class="text-success see-more" onclick="toggleDesc(this)" style="font-size:.75rem;">See More</a>
                  </p>

                  <!-- Qty + Cart -->
                  <div class="input-group mb-3" style="border:2px solid var(--sage);border-radius:10px;overflow:hidden;">
                    <span class="input-group-text border-0 bg-white text-muted small">Qty</span>
                    <input type="number" id="qty-<?= $item->id ?>" class="form-control border-0 text-center"
                      value="1" min="1" max="<?= $item->stock ?>" <?= $outOfStock ? 'disabled' : '' ?>>
                  </div>

                  <button class="btn-cart" id="btn-<?= $item->id ?>"
                    onclick='addToCart(<?= $item->id ?>, <?= json_encode($item->name) ?>, <?= $cleanPrice ?>, <?= json_encode($item->image ?? '') ?>)'
                    <?= ($outOfStock || $userRole === 'admin') ? 'disabled' : '' ?>>
                    <?php if ($userRole === 'admin'): ?>
                      Admin View Only
                    <?php elseif ($outOfStock): ?>
                      Out of Stock
                    <?php else: ?>
                      Add to Cart
                    <?php endif; ?>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <!-- REVIEWS -->
  <section class="reviews-section">
    <div class="container">

      <h2 class="section-title text-center">What Customers Say</h2>

      <div class="review-card">
        <div class="review-content">

          <!-- REVIEW ITEM -->
          <div class="review-item">
            <div class="review-header">
              <img src="https://i.pravatar.cc/40?img=1" class="avatar">
              <div>
                <b>Maria Santos</b>
                <div class="stars">★★★★★</div>
              </div>
            </div>
            <p>Very satisfied with the quality and overall design.</p>
          </div>

          <div class="review-item">
            <div class="review-header">
              <img src="https://i.pravatar.cc/40?img=2" class="avatar">
              <div>
                <b>John Cruz</b>
                <div class="stars">★★★★☆</div>
              </div>
            </div>
            <p>It completely upgraded the look of my living room.</p>
          </div>

          <div class="review-item">
            <div class="review-header">
              <img src="https://i.pravatar.cc/40?img=3" class="avatar">
              <div>
                <b>Ana Reyes</b>
                <div class="stars">★★★★★</div>
              </div>
            </div>
            <p>Simple, modern, and elegant design — exactly what I wanted.</p>
          </div>

          <div class="review-item">
            <div class="review-header">
              <img src="https://i.pravatar.cc/40?img=4" class="avatar">
              <div>
                <b>Chris Tan</b>
                <div class="stars">★★★★★</div>
              </div>
            </div>
            <p>Great quality and very comfortable. Worth the price.</p>
          </div>

          <div class="review-item">
            <div class="review-header">
              <img src="https://i.pravatar.cc/40?img=5" class="avatar">
              <div>
                <b>Mae Abiera</b>
                <div class="stars">★★★★☆</div>
              </div>
            </div>
            <p>The furniture feels premium and fits perfectly in my home interior.</p>
          </div>

        </div>
      </div>

    </div>
  </section>

  <!-- CONTACT -->
  <section class="section" id="about">
    <div class="container">
      <h2 class="section-title text-center">Get in Touch</h2>
      <div class="row g-4">
        <div class="col-md-6">
          <div class="contact-card">
            <h5 class="fw-bold mb-4" style="font-family:'Playfair Display',serif;color:var(--green);">Message Us</h5>
            <div class="input-box"><input type="text" placeholder=" "><label>Full Name</label></div>
            <div class="input-box"><input type="email" placeholder=" "><label>Email Address</label></div>
            <div class="input-box"><input type="text" placeholder=" "><label>Subject</label></div>
            <div class="input-box"><textarea placeholder=" "></textarea><label>Your Message</label></div>
            <button class="btn w-100 fw-bold text-white rounded-pill py-2" style="background:var(--green);">Send Message</button>
          </div>
        </div>
        <div class="col-md-6">
          <div class="contact-card h-100 d-flex flex-column justify-content-center">
            <h5 class="fw-bold mb-4" style="font-family:'Playfair Display',serif;color:var(--green);">Contact Info</h5>
            <div class="mb-4">
              <p class="text-uppercase small text-muted mb-1" style="letter-spacing:2px;font-size:.72rem;">Visit Us</p>
              <p class="mb-0">123 Furniture Street, Modern City, Philippines</p>
            </div>
            <div class="mb-4">
              <p class="text-uppercase small text-muted mb-1" style="letter-spacing:2px;font-size:.72rem;">Call Us</p>
              <p class="mb-0">+63 912 345 6789 &nbsp;·&nbsp; +63 2 888 0000</p>
            </div>
            <div class="mb-4">
              <p class="text-uppercase small text-muted mb-1" style="letter-spacing:2px;font-size:.72rem;">Email Us</p>
              <p class="mb-0">zafirah@gmail.com</p>
            </div>
            <div>
              <p class="text-uppercase small text-muted mb-2" style="letter-spacing:2px;font-size:.72rem;">Follow Us</p>
              <div class="d-flex gap-2">
                <div class="social-icon"><i class="fab fa-facebook-f"></i></div>
                <div class="social-icon"><i class="fab fa-instagram"></i></div>
                <div class="social-icon"><i class="fab fa-tiktok"></i></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="footer">
    <img src="/php_work/e-commerce/pci/Group_15.svg" class="footer-logo">
    <div class="brand">ZAFIRAH</div>
  </footer>
  <!-- ── CART SLIDE-OUT PANEL — hidden for admin ── -->
  <?php if ($userRole !== 'admin'): ?>
  <div id="cartPanel" style="
  position:fixed;top:0;right:-420px;width:400px;max-width:95vw;height:100vh;
  background:#fff;z-index:10000;box-shadow:-8px 0 40px rgba(0,0,0,.18);
  display:flex;flex-direction:column;transition:right .35s cubic-bezier(.4,0,.2,1);
  border-radius:20px 0 0 20px;overflow:hidden;">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);color:#fff;padding:20px 22px 16px;flex-shrink:0;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h5 style="margin:0;font-family:'Playfair Display',serif;font-weight:700;letter-spacing:1px;display:flex;align-items:center;gap:8px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="9" cy="21" r="1" />
              <circle cx="20" cy="21" r="1" />
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
            </svg>
            My Cart
          </h5>
          <small id="cartItemCount" style="opacity:.75;font-size:.75rem;">
            <?php
            $initCount = 0;
            if ($userEmail && !empty($_SESSION['cart'][$userEmail]))
              foreach ($_SESSION['cart'][$userEmail] as $ci) $initCount += (int)($ci['qty'] ?? 1);
            echo $initCount === 0 ? 'Your cart is empty' : $initCount . ' item' . ($initCount === 1 ? '' : 's') . ' in cart';
            ?>
          </small>
        </div>
        <button onclick="closeCart()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:36px;height:36px;font-size:1.1rem;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Items list -->
    <div id="cartItems" style="flex:1;overflow-y:auto;padding:16px;background:#f9f9f6;">
      <?php
      $initSubtotal = 0;
      // Build a stock lookup from session inventory
      $invStock = [];
      foreach ($_SESSION['inventory'] ?? [] as $inv) {
        $invStock[(int)$inv->id] = (int)$inv->stock;
      }
      if ($userEmail && !empty($_SESSION['cart'][$userEmail])):
        foreach ($_SESSION['cart'][$userEmail] as $ci):
          $ciPrice  = (float)($ci['price'] ?? 0);
          $ciQty    = (int)($ci['qty'] ?? 1);
          $ciId     = (int)($ci['id'] ?? 0);
          $ciTotal  = $ciPrice * $ciQty;
          $ciStock  = $invStock[$ciId] ?? 99;
          $initSubtotal += $ciTotal;
          $stockLabel = $ciStock === 0 ? 'Out of Stock' : ($ciStock <= 5 ? 'Low stock: ' . $ciStock . ' left' : 'In stock: ' . $ciStock);
          $stockColor = $ciStock === 0 ? '#dc2626' : ($ciStock <= 5 ? '#f59e0b' : '#16a34a');
      ?>
          <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <img src="<?= htmlspecialchars($ci['image'] ?? '') ?>" alt=""
                style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= htmlspecialchars($ci['name'] ?? '') ?>
                </div>
                <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱<?= number_format($ciPrice, 2) ?> each</div>
                <div style="font-size:.68rem;color:<?= $stockColor ?>;font-weight:600;margin-top:2px;">
                  <?= $stockLabel ?>
                </div>
              </div>
              <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                ₱<?= number_format($ciTotal, 2) ?>
              </div>
            </div>
            <!-- Qty stepper + remove row -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
              <div style="display:flex;align-items:center;gap:0;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                <button onclick="cartQty(<?= $ciId ?>, 'minus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                <span id="panel-qty-<?= $ciId ?>" style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;"><?= $ciQty ?></span>
                <button onclick="cartQty(<?= $ciId ?>, 'plus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
              </div>
              <button onclick="cartQty(<?= $ciId ?>, 'remove')"
                style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
              </button>
            </div>
          </div>
        <?php
        endforeach;
      else: ?>
        <div style="text-align:center;padding:60px 20px;color:#bbb;">
          <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round" style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
            <circle cx="9" cy="21" r="1" />
            <circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
          </svg>
          <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Footer with subtotal + checkout -->
    <div id="cartFooter" style="padding:16px 20px;background:#fff;border-top:2px solid #f0f0eb;flex-shrink:0;<?= ($initSubtotal > 0) ? '' : 'display:none;' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <span style="font-weight:600;color:#666;font-size:.85rem;">SUBTOTAL</span>
        <span id="cartSubtotal" style="font-weight:800;color:#2d5a2d;font-size:1.15rem;">₱<?= number_format($initSubtotal) ?></span>
      </div>
      <a href="checkout.php" style="display:block;background:var(--green);color:#fff;text-align:center;padding:14px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.95rem;transition:.2s;">
        Checkout Now
      </a>
      <a href="profile.php" style="display:block;text-align:center;margin-top:10px;color:#7aab7a;font-size:.8rem;text-decoration:none;">
        View full cart in My Profile →
      </a>
    </div>
  </div>
  <div id="cartBackdrop" onclick="closeCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;backdrop-filter:blur(2px);"></div>
  <?php endif; /* end admin cart hide */ ?>

  <div id="toast-msg" class="toast-fixed"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ── Cart state seeded from PHP session ───────────────────────
    let cartItemsJS = <?= json_encode(array_values($_SESSION['cart'][$userEmail] ?? [])) ?>;
    // Stock map from PHP inventory
    const stockMap = <?= json_encode(array_map(fn($i) => (int)$i->stock, $_SESSION['inventory'] ?? [])) ?>;

    // ── Open / Close cart panel ───────────────────────────────────
    function openCart() {
      document.getElementById('cartPanel').style.right = '0';
      document.getElementById('cartBackdrop').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeCart() {
      document.getElementById('cartPanel').style.right = '-420px';
      document.getElementById('cartBackdrop').style.display = 'none';
      document.body.style.overflow = '';
    }

    // ── Re-render cart panel items + subtotal + header count ──────
    function renderCart() {
      const container = document.getElementById('cartItems');
      const footer    = document.getElementById('cartFooter');
      const subEl     = document.getElementById('cartSubtotal');
      const countEl   = document.getElementById('cartItemCount');

      let subtotal = 0, totalQty = 0;

      if (cartItemsJS.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:60px 20px;color:#bbb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
              stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round"
              style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
          </div>`;
        if (footer)  footer.style.display  = 'none';
        if (countEl) countEl.textContent   = 'Your cart is empty';
        const badge = document.getElementById('cart-badge');
        if (badge) badge.textContent = '0';
        return;
      }

      let html = '';
      cartItemsJS.forEach(item => {
        const price     = Number(item.price) || 0;
        const qty       = Number(item.qty)   || 1;
        const lineTotal = price * qty;
        const stock     = stockMap[item.id] ?? 99;
        subtotal  += lineTotal;
        totalQty  += qty;
        const imgSrc = item.image || 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';

        const stockLabel = stock === 0 ? 'Out of Stock'
          : stock <= 5   ? 'Low stock: ' + stock + ' left'
          :                'In stock: '  + stock;
        const stockColor = stock === 0 ? '#dc2626' : stock <= 5 ? '#f59e0b' : '#16a34a';

        html += `
          <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <img src="${escHtml(imgSrc)}" alt=""
                style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  ${escHtml(String(item.name))}
                </div>
                <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱${price.toLocaleString('en-PH')} each</div>
                <div style="font-size:.68rem;color:${stockColor};font-weight:600;margin-top:2px;">${stockLabel}</div>
              </div>
              <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                ₱${lineTotal.toLocaleString('en-PH')}
              </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
              <div style="display:flex;align-items:center;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                <button onclick="cartQty(${item.id},'minus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                <span id="panel-qty-${item.id}"
                  style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;">${qty}</span>
                <button onclick="cartQty(${item.id},'plus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
              </div>
              <button onclick="cartQty(${item.id},'remove')"
                style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
              </button>
            </div>
          </div>`;
      });

      container.innerHTML = html;
      if (subEl)  subEl.textContent = '₱' + subtotal.toLocaleString('en-PH');
      if (footer) footer.style.display = 'block';
      if (countEl) {
        countEl.textContent = totalQty === 1 ? '1 item in cart' : totalQty + ' items in cart';
      }
      const badge = document.getElementById('cart-badge');
      if (badge) badge.textContent = totalQty;
    }

    // ── Qty stepper in cart sidebar (calls profile.php POST via fetch) ──
    function cartQty(itemId, action) {
      // Client-side stock cap before even hitting server
      if (action === 'plus') {
        const item = cartItemsJS.find(i => Number(i.id) === Number(itemId));
        const max  = stockMap[itemId] ?? 9999;
        if (item && Number(item.qty) >= max) {
          showToast('Maximum stock (' + max + ') already in cart.', 'error');
          return;
        }
      }

      fetch('profile.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'update_qty=1&item_id=' + itemId + '&qty_action=' + action
      }).then(r => {
        if (!r.ok) return;
        if (action === 'remove') {
          cartItemsJS = cartItemsJS.filter(i => Number(i.id) !== Number(itemId));
        } else {
          const item = cartItemsJS.find(i => Number(i.id) === Number(itemId));
          if (item) {
            const max = stockMap[itemId] ?? 9999;
            if (action === 'plus')  item.qty = Math.min(max, Number(item.qty) + 1);
            if (action === 'minus') item.qty = Math.max(1,   Number(item.qty) - 1);
          }
        }
        renderCart();
      }).catch(() => showToast('Could not update cart. Try again.', 'error'));
    }

    // ── Add item to local JS state then re-render ─────────────────
    function updateCartPanel(newItem) {
      const existing = cartItemsJS.find(i => Number(i.id) === Number(newItem.id));
      if (existing) {
        existing.qty = Number(existing.qty) + Number(newItem.qty);
        if (!existing.image && newItem.image) existing.image = newItem.image;
      } else {
        cartItemsJS.push({
          id:    newItem.id,
          name:  newItem.name,
          price: Number(newItem.price),
          qty:   Number(newItem.qty),
          image: newItem.image || ''
        });
      }
      renderCart();
    }

    function escHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Time display ──────────────────────────────────────────────
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

    function toggleDesc(btn) {
      const p = btn.parentElement;
      const s = p.querySelector('.desc-short');
      const f = p.querySelector('.desc-full');
      const hidden = f.classList.contains('d-none');
      f.classList.toggle('d-none', !hidden);
      s.classList.toggle('d-none', hidden);
      btn.textContent = hidden ? 'See Less' : 'See More';
    }

    function showToast(msg, type = 'success') {
      const t = document.getElementById('toast-msg');
      t.textContent = msg;
      t.className = 'toast-fixed show' + (type === 'error' ? ' error' : '');
      setTimeout(() => t.classList.remove('show'), 3500);
    }

    function addToCart(id, name, price, image) {
      <?php if (!$userEmail): ?>
        window.location.href = 'logsign.php';
        return;
      <?php endif; ?>

      const qtyEl = document.getElementById('qty-' + id);
      const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;

      const btn = document.getElementById('btn-' + id);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adding...';
      }

      fetch('addcart.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'id=' + encodeURIComponent(id) +
            '&name=' + encodeURIComponent(name) +
            '&price=' + encodeURIComponent(price) +
            '&qty=' + encodeURIComponent(qty) +
            '&image=' + encodeURIComponent(image || '')
        })
        .then(r => r.text())
        .then(raw => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          let data;
          try {
            data = JSON.parse(raw);
          } catch (e) {
            showToast('Server error — check PHP logs.', 'error');
            console.error('Non-JSON response:', raw);
            return;
          }

          if (data.success) {
            if (data.cart) cartItemsJS = data.cart;
            const badge = document.getElementById('cart-badge');
            if (badge) badge.textContent = data.total_items;
            renderCart();
            showToast('✓ ' + name + ' added to cart!');
          } else if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            showToast(data.message || 'Error adding to cart.', 'error');
          }
        })
        .catch(err => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          showToast('Connection error. Try again.', 'error');
          console.error(err);
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) {
          e.preventDefault();
          t.scrollIntoView({
            behavior: 'smooth'
          });
        }
      });
    });
  </script>
</body>

</html>