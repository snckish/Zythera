<?php
require 'config.php';
$userEmail  = $_SESSION['logged_in_user'] ?? null;
$userName = null;
$uObj = null;
if ($userEmail) {
  $uObj = findAccountByEmail($userEmail);
  if ($uObj) {
    $userName = $uObj->name;
  }
}
$userRole   = $_SESSION['role'] ?? 'user';
$loginTime  = $_SESSION['login_time'] ?? null;

// ── Handle "Get in Touch" contact form ────────────────────────
$contactSuccess = false;
$contactError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
  $cName    = trim($_POST['c_name']    ?? '');
  $cEmail   = trim($_POST['c_email']   ?? '');
  $cSubject = trim($_POST['c_subject'] ?? '');
  $cMsg     = trim($_POST['c_message'] ?? '');

  if ($cName && $cEmail && $cMsg) {
    try {
      $db2 = getDBConnection();
      $msgUserId = null;
      if ($userEmail) {
        $msgUser = findUserByEmail($userEmail);
        $msgUserId = $msgUser ? (string)$msgUser->user_id : null;
      }
      $newMsgId = generateCustomId('MSG');
      $ins = $db2->prepare("
                INSERT INTO messages (msg_id, user_id, full_name, email, subject, msg_content)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
      $ins->execute([$newMsgId, $msgUserId, $cName, $cEmail, $cSubject, $cMsg]);
      // If request is AJAX, return JSON so client can clear only the message field
      $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
      }
      // Non-AJAX fallback: PRG so the form clears and the message isn't re-submitted
      header('Location: website.php?contact_sent=1#contact');
      exit;
    } catch (PDOException $e) {
      $contactError = 'Could not send message. Please try again.';
      $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $contactError]);
        exit;
      }
    }
  } else {
    $contactError = 'Please fill in your name, email, and message.';
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'error' => $contactError]);
      exit;
    }
  }
}

// If redirected after successful send, show success message
if (!empty($_GET['contact_sent'])) {
  $contactSuccess = true;
}

$cartCount = 0;
if ($userEmail && !empty($_SESSION['cart'][$userEmail]) && is_array($_SESSION['cart'][$userEmail])) {
  $cartCount = count($_SESSION['cart'][$userEmail]);
}

// FIX: Fetch the inventory array directly from the database using your loadInventory() function
$rawInventory = loadInventory();
$inventory = [];

foreach ($rawInventory as $item) {
  $inventory[] = $item;
}

// Sort inventory by ID accurately
usort($inventory, function ($a, $b) {
  $ia = is_object($a) ? (string)$a->inv_id : (string)($a['inv_id'] ?? '');
  $ib = is_object($b) ? (string)$b->inv_id : (string)($b['inv_id'] ?? '');
  return $ia <=> $ib;
});

$reviews = loadReviews();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZYTHERA | FURNITURE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/website.css">
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

<!-- HERO -->
  <div class="hero">
    <img src="pci/image_8.png" class="hero-img">
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
                    <input type="number" id="qty-<?= $item->inv_id ?>" class="form-control border-0 text-center"
                      value="1" min="1" max="<?= $item->stock ?>" <?= $outOfStock ? 'disabled' : '' ?>>
                  </div>

                  <button class="btn-cart" id="btn-<?= $item->inv_id ?>"
                    onclick='addToCart(<?= json_encode((string)$item->inv_id) ?>, <?= json_encode($item->name) ?>, <?= $cleanPrice ?>, <?= json_encode($item->image ?? '') ?>)'
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

  <!-- REVIEW SECTION -->
  <section class="reviews-section" id="reviews">
    <div class="container">
      <h2 class="section-title text-center">Customer Reviews</h2>
      <p class="text-center text-muted mb-4" style="font-size:.9rem;margin-top:-20px;">What our customers say about our furniture</p>

      <div class="review-scroll-wrapper px-2">
        <!-- Left arrow -->
        <button class="scroll-btn left" onclick="document.getElementById('reviewTrack').scrollBy({left:-290,behavior:'smooth'})">
          <i class="fas fa-chevron-left"></i>
        </button>

        <div class="review-scroll-track" id="reviewTrack">
          <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
              <?php
                $stars = str_repeat('★', max(1, min(5, (int)($review->rating ?? 5))));
                $isOwn = ($userEmail && strtolower($userEmail) === strtolower($review->author_email ?? ''));
                $reviewOrderId = htmlspecialchars($review->order_id ?? '');
                $reviewIdInt   = (int)($review->review_id ?? 0);
                $cardId        = 'rv-' . $reviewIdInt;

                // Character limit: truncate at 300 chars for display
                $CHAR_LIMIT  = 300;
                $commentText = $review->comment;
                $isLong      = mb_strlen($commentText) > $CHAR_LIMIT || substr_count($commentText, "\n") >= 4;
              ?>
              <div class="review-item<?= $isOwn ? ' own-review' : '' ?>"
                   id="card-<?= $cardId ?>"
                   <?php if ($isOwn && $reviewOrderId): ?>
                     style="cursor:pointer;"
                     onclick="window.location.href='order.php?order_id=<?= $reviewOrderId ?>'"
                     title="Click to view your order"
                   <?php endif; ?>>

                <?php if ($isOwn): ?>
                <!-- ⋮ Three-dot menu (own reviews only) -->
                <div class="review-menu-wrapper" onclick="event.stopPropagation()">
                  <button class="review-menu-btn"
                          title="Review options"
                          onclick="toggleReviewMenu('<?= $cardId ?>')">&#8942;</button>
                  <div class="review-dropdown" id="menu-<?= $cardId ?>">
                  <!-- <button onclick="openEditReview(<?= $reviewIdInt ?>, <?= (int)($review->rating ?? 5) ?>, <?= json_encode($commentText) ?>)">
                      <i class="fas fa-pencil-alt"></i> Edit Review
                    </button>--->
                    <button class="danger" onclick="deleteMyReview(<?= $reviewIdInt ?>)">
                      <i class="fas fa-trash"></i> Delete Review
                    </button>
                  </div>
                </div>
                <?php endif; ?>

                <div class="review-header">
                  <?php $authorPic = getAvatarURL($review->author_pic ?? null, $review->author_email ?? null, $review->author_name ?? null, 80); ?>
                  <img src="<?= htmlspecialchars($authorPic) ?>" class="avatar" alt="<?= htmlspecialchars($review->author_name ?: 'Verified Buyer') ?>">
                  <div style="min-width:0;">
                    <div style="font-weight:700;color:#fff;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= htmlspecialchars($review->author_name ?: 'Verified Buyer') ?>
                      <?php if ($isOwn): ?><span style="font-size:.65rem;background:rgba(255,255,255,.18);border-radius:50px;padding:1px 7px;margin-left:5px;font-weight:500;white-space:nowrap;">You</span><?php endif; ?>
                    </div>
                    <div style="font-size:.72rem;color:rgba(212,228,212,.8);">Verified Buyer</div>
                  </div>
                </div>
                <div class="stars"><?= $stars ?></div>
                <p class="review-body<?= $isLong ? ' clamped' : '' ?>" id="body-<?= $cardId ?>">
                  <?= nl2br(htmlspecialchars($commentText)) ?>
                </p>
                <?php if ($isLong): ?>
                <button class="review-expand-btn" id="btn-<?= $cardId ?>"
                  onclick="event.stopPropagation();toggleReview('<?= $cardId ?>')">Read more</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="review-item">
              <div class="review-header">
                <img src="https://i.pravatar.cc/80?img=13" class="avatar" alt="Verified Buyer">
                <div>
                  <div style="font-weight:700;color:#fff;font-size:.9rem;">Verified Buyer</div>
                  <div style="font-size:.72rem;color:var(--sage);opacity:.85;">Verified Buyer</div>
                </div>
              </div>
              <div class="stars mb-2" style="color:#f5c842;">★★★★★</div>
              <p style="font-size:.88rem;color:var(--sage);line-height:1.6;margin:0;">
                The sofa exceeded my expectations. Very comfortable, elegant, and perfect for our living room.
              </p>
            </div>
            <div class="review-item">
              <div class="review-header">
                <img src="https://i.pravatar.cc/80?img=25" class="avatar" alt="Verified Buyer">
                <div>
                  <div style="font-weight:700;color:#fff;font-size:.9rem;">Verified Buyer</div>
                  <div style="font-size:.72rem;color:var(--sage);opacity:.85;">Verified Buyer</div>
                </div>
              </div>
              <div class="stars mb-2" style="color:#f5c842;">★★★★★</div>
              <p style="font-size:.88rem;color:var(--sage);line-height:1.6;margin:0;">
                Excellent quality and very fast delivery. The furniture looks premium and modern.
              </p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right arrow -->
        <button class="scroll-btn right" onclick="document.getElementById('reviewTrack').scrollBy({left:290,behavior:'smooth'})">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </section>

  

  

  <!-- CONTACT -->
  <section class="section" id="contact">
    <div class="container">
      <h2 class="section-title text-center">Get in Touch</h2>
      <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
          <div class="contact-card">
            <h5 class="fw-bold mb-5" style="font-family:'Playfair Display',serif;color:var(--green);font-size:1.5rem;">Contact Us</h5>
            <?php if ($contactSuccess): ?>
            <?php elseif ($contactError): ?>
              <div style="background:#fee2e2;color:#b91c1c;border-radius:12px;padding:14px 18px;margin-bottom:18px;font-weight:600;font-size:.9rem;">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($contactError) ?>
              </div>
            <?php endif; ?>
            <form id="contactForm" method="POST" action="website.php#contact">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="input-box"><input type="text" name="c_name" placeholder=" " value="<?= htmlspecialchars($_POST['c_name'] ?? ($userName ?? '')) ?>" required><label>Full Name</label></div>
                </div>
                <div class="col-md-6">
                  <div class="input-box"><input type="email" name="c_email" placeholder=" " value="<?= htmlspecialchars($_POST['c_email'] ?? ($userEmail ?? '')) ?>" required><label>Email Address</label></div>
                </div>
              </div>
              <div class="input-box mt-3"><input type="text" name="c_subject" placeholder=" " value="<?= htmlspecialchars($_POST['c_subject'] ?? '') ?>"><label>Subject</label></div>
              <div class="input-box mt-3"><textarea name="c_message" placeholder=" " required style="min-height:180px;"><?= htmlspecialchars($_POST['c_message'] ?? '') ?></textarea><label>Your Message</label></div>
              <button type="submit" name="send_message" class="btn w-100 fw-bold text-white rounded-pill py-3 mt-4" style="background:var(--green);font-size:1rem;letter-spacing:0.5px;">Send Message</button>
            </form>
            <div id="contactToast" class="toast-fixed" aria-live="polite" aria-atomic="true"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <div class="row gy-3">
        <!-- Brand col -->
        <div class="col-12 col-md-4 footer-col">
          <div class="d-flex align-items-center gap-2 mb-2">
            <img src="pci/Group_15.png" class="footer-logo-img" alt="Zythera logo">
            <span class="footer-brand-name">ZYTHERA</span>
          </div>
          <p class="footer-tagline">Furniture crafted for lives that deserve stillness, beauty, and meaning.</p>
          <div class="footer-social mt-3">
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          </div>
        </div>
        <!-- Navigate col -->
        <div class="col-12 col-md-2 footer-col">
          <p class="footer-col-title">Navigate</p>
          <a href="website.php" class="footer-link">Home</a>
          <a href="website.php#products" class="footer-link">Products</a>
          <a href="about.php" class="footer-link">About</a>
          <a href="website.php#contact" class="footer-link">Contact</a>
          <?php if ($userEmail): ?>
          <a href="profile.php" class="footer-link">My Profile</a>
          <?php else: ?>
          <a href="logsign.php" class="footer-link">Log In</a>
          <?php endif; ?>
        </div>
        <!-- Contact col -->
        <div class="col-12 col-md-3 footer-col">
          <p class="footer-col-title">Contact Us</p>
          <a href="tel:+639123456789" class="footer-link"><i class="fas fa-phone"></i>+63 912 345 6789</a>
          <a href="mailto:zythera@gmail.com" class="footer-link"><i class="fas fa-envelope"></i>zythera@gmail.com</a>
          <span class="footer-link" style="cursor:default;"><i class="fas fa-map-marker-alt"></i>123 Furniture St, Philippines</span>
          <span class="footer-link" style="cursor:default;"><i class="fas fa-clock"></i>Mon–Sat, 9 AM – 6 PM</span>
        </div>
        <!-- Reviews anchor col -->
        <div class="col-12 col-md-3 footer-col">
          <p class="footer-col-title">More</p>
          <a href="website.php#reviews" class="footer-link">Customer Reviews</a>
          <a href="website.php#contact" class="footer-link">Send a Message</a>
          <?php if ($userEmail && $userRole !== 'admin'): ?>
          <a href="orders.php" class="footer-link">My Orders</a>
          <?php endif; ?>
          <?php if ($userRole === 'admin'): ?>
          <a href="admin.php" class="footer-link">Admin Panel</a>
          <?php endif; ?>
        </div>
      </div>
      <hr class="footer-divider">
      <div class="footer-bottom">
        <span class="footer-bottom-text">&copy; <?= date('Y') ?> ZYTHERA. All rights reserved.</span>
        <span class="footer-bottom-text">Crafted with care in the Philippines.</span>
      </div>
    </div>
  </footer>
  <!-- ── CART SLIDE-OUT PANEL — hidden for admin ── -->
  <?php if ($userRole !== 'admin'): ?>
    <div id="cartPanel" style="
  position:fixed;top:0;right:-110vw;width:min(400px,100vw);height:100vh;
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
              $initDistinctCount = 0;
              if ($userEmail && !empty($_SESSION['cart'][$userEmail])) {
                $initDistinctCount = count($_SESSION['cart'][$userEmail]);
                foreach ($_SESSION['cart'][$userEmail] as $ci) $initCount += (int)($ci['qty'] ?? 1);
              }
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

      <!-- Select-All bar — only appears once there are 2+ distinct products in the cart.
           Styled to match the nav's utility-icon look (soft green pill, same border/colors
           as the Settings button) so it reads as a toolbar action, not just another row. -->
      <div id="cartSelectAllBar" style="display:<?= $initDistinctCount >= 2 ? 'flex' : 'none' ?>;align-items:center;justify-content:space-between;
        padding:10px 18px;background:#f0f7f0;border-bottom:1px solid rgba(45,90,45,.16);flex-shrink:0;">
        <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:.82rem;font-weight:700;color:var(--green);margin:0;">
          <input type="checkbox" id="cartSelectAllCheckbox" onchange="toggleSelectAll(this.checked)"
            style="width:17px;height:17px;accent-color:var(--green);cursor:pointer;">
          Select All
        </label>
        <span id="cartSelectAllCount" style="font-size:.74rem;color:#5a8a5a;font-weight:600;"></span>
      </div>

      <!-- Items list -->
      <div id="cartItems" style="flex:1;overflow-y:auto;padding:16px;background:#f9f9f6;">
        <?php
        $initSubtotal = 0;
        // Build a stock lookup from session inventory
        $invStock = [];
        foreach ($_SESSION['inventory'] ?? [] as $invId => $inv) {
          $invStock[$invId] = (int)$inv->stock;
        }
        if ($userEmail && !empty($_SESSION['cart'][$userEmail])):
          foreach ($_SESSION['cart'][$userEmail] as $ci):
            $ciPrice  = (float)($ci['price'] ?? 0);
            $ciQty    = (int)($ci['qty'] ?? 1);
            $ciId     = (string)($ci['inv_id'] ?? '');
            $ciTotal  = $ciPrice * $ciQty;
            $ciStock  = $invStock[$ciId] ?? 99;
            $initSubtotal += $ciTotal;
            $stockLabel = $ciStock === 0 ? 'Out of Stock' : ($ciStock <= 5 ? 'Low stock: ' . $ciStock . ' left' : 'In stock: ' . $ciStock);
            $stockColor = $ciStock === 0 ? '#dc2626' : ($ciStock <= 5 ? '#f59e0b' : '#16a34a');
        ?>
            <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <input type="checkbox" class="cart-select-checkbox" value="<?= htmlspecialchars($ciId) ?>"
                  onchange="toggleCartSelection('<?= htmlspecialchars($ciId) ?>', this.checked)"
                  style="width:18px;height:18px;accent-color:var(--green);flex-shrink:0;cursor:pointer;"
                  aria-label="Select <?= htmlspecialchars($ci['name'] ?? 'item') ?> for checkout">
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
                  <button onclick="cartQty('<?= $ciId ?>', 'minus')"
                    style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                  <span id="panel-qty-<?= $ciId ?>" style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;"><?= $ciQty ?></span>
                  <button onclick="cartQty('<?= $ciId ?>', 'plus')"
                    style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
                </div>
                <button onclick="cartQty('<?= $ciId ?>', 'remove')"
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
        <div id="cartSelectionError" style="display:none;color:#b91c1c;background:#fee2e2;border-radius:10px;padding:8px 10px;font-size:.78rem;font-weight:700;margin-bottom:10px;text-align:center;">
          Please select products first.
        </div>
        <a href="checkout.php" id="checkoutSelectedBtn" onclick="return goToSelectedCheckout(event)" style="display:block;background:var(--green);color:#fff;text-align:center;padding:14px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.95rem;transition:.2s;">
          Checkout Now
        </a>
      </div>
    </div>
    <div id="cartBackdrop" onclick="closeCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;backdrop-filter:blur(2px);"></div>
  <?php endif; /* end admin cart hide */ ?>

  <div id="toast-msg" class="toast-fixed"></div>
  <?php if (!empty($contactSuccess)): $toastMessage = "Message sent! We'll get back to you soon.";
    $toastType = 'success';
    include __DIR__ . '/includes/_toast.php';
  endif; ?>

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

  <!-- Edit Review Modal -->
  <div class="review-edit-modal-bg" id="editReviewModalBg" onclick="closeEditReview()">
    <div class="review-edit-modal" onclick="event.stopPropagation()">
      <h5><i class="fas fa-pencil-alt me-2" style="font-size:.9rem;"></i>Edit Your Review</h5>
      <div class="review-edit-stars" id="editStars">
        <span data-val="1">★</span>
        <span data-val="2">★</span>
        <span data-val="3">★</span>
        <span data-val="4">★</span>
        <span data-val="5">★</span>
      </div>
      <textarea id="editReviewText" maxlength="500"
        placeholder="Share your experience with this furniture..." rows="4"
        oninput="updateEditCharCount()"></textarea>
      <div class="review-char-count" id="editCharCount">0 / 500</div>
      <div class="review-edit-modal-actions">
        <button class="btn-cancel-review" onclick="closeEditReview()">Cancel</button>
        <button class="btn-save-review" id="saveReviewBtn" onclick="saveEditReview()">Save Changes</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  

  <script>
    /* PHP-seeded globals for website.js */
    let cartItemsJS = <?= json_encode(array_values(array_map(function ($i) {
      return ['inv_id' => (string)($i['inv_id'] ?? ''), 'name' => $i['name'] ?? '', 'price' => (float)($i['price'] ?? 0), 'qty' => (int)($i['qty'] ?? 1), 'image' => $i['image'] ?? ''];
    }, $_SESSION['cart'][$userEmail] ?? []))) ?>;
    let selectedCartIds = new Set(JSON.parse(localStorage.getItem('zythera_selected_cart') || '[]').map(String));
    const stockMap = <?= json_encode(array_combine(
      array_keys($_SESSION['inventory'] ?? []),
      array_map(fn($i) => (int)$i->stock, $_SESSION['inventory'] ?? [])
    )) ?>;
    const IS_LOGGED_IN = <?= json_encode((bool)$userEmail) ?>;
    const USER_ROLE = <?= json_encode($userRole ?? 'user') ?>;
  </script>
  <script src="assets/js/website.js"></script>
</body>

</html>
