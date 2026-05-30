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
        $cartCount += is_array($item) ? (int)($item['qty']??1) : 1;
    }
}


$inventory = array_values($_SESSION['inventory'] ?? []);
usort($inventory, function($a,$b){
    $ia = is_object($a)?(int)$a->id:(int)($a['id']??0);
    $ib = is_object($b)?(int)$b->id:(int)($b['id']??0);
    return $ia<=>$ib;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZAFIRAH | Furniture</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<style>

:root{--green:#2d5a2d;--sage:#d4e4d4;--cream:#f5f2ec;--terra:#bc8a7b;--deep:#1a2e1a;}
*{font-family:'DM Sans',sans-serif;}
body{background:var(--cream);padding-top:70px;}

.navbar{background:#fff!important;box-shadow:0 1px 12px rgba(0,0,0,.07);}
.navbar-brand{font-family:'Playfair Display',serif;color:var(--green)!important;font-size:1.55rem;letter-spacing:2px;}
.nav-link{font-weight:500;color:#444!important;font-size:.9rem;}
.nav-link:hover{color:var(--green)!important;}

.hero{
  position:relative;height:92vh;min-height:480px;overflow:hidden;
  background:linear-gradient(135deg,var(--deep) 0%,var(--sage) 55%,#4a7c4a 100%);
  display:flex;align-items:center;justify-content:center;
}
.hero-text{
  max-width:640px;text-align:center;color:#fff;
  font-family:'Playfair Display',serif;font-size:clamp(1.3rem,3vw,2rem);
  font-style:italic;line-height:1.7;padding:40px;
  border:1px solid rgba(147, 174, 153, 0.2);border-radius:6px;
  background:rgba(0,0,0,.18);backdrop-filter:blur(6px);
}
.hero-cta{
  margin-top:24px;
  display:inline-block;padding:12px 32px;
  background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.5);
  border-radius:50px;color:#fff;text-decoration:none;
  font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:600;
  backdrop-filter:blur(4px);transition:.25s;
}
.hero-cta:hover{background:rgba(255,255,255,.3);color:#fff;}

/* SECTION */
.section{padding:64px 0;}
.section-title{font-family:'Playfair Display',serif;color:var(--green);font-size:1.9rem;margin-bottom:36px;}

/* PRODUCT CARD */
.product-card{
  border:none;border-radius:20px;overflow:hidden;
  background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.07);
  transition:transform .25s,box-shadow .25s;height:100%;
}
.product-card:hover{transform:translateY(-6px);box-shadow:0 14px 38px rgba(0,0,0,.13);}
.product-card img{height:220px;object-fit:cover;width:100%;}
.product-name{font-family:'Playfair Display',serif;color:var(--green);font-size:1.05rem;}
.product-price{color:var(--terra);font-size:1.2rem;font-weight:700;}
.stock-badge{background:var(--green);color:#fff;font-size:.7rem;border-radius:6px;padding:3px 8px;}
.btn-cart{
  background:var(--green);color:#fff;border:none;border-radius:10px;
  padding:.65rem 1rem;font-weight:600;width:100%;
  font-family:'DM Sans',sans-serif;transition:.2s;
}
.btn-cart:hover{background:var(--deep);color:#fff;}
.btn-cart:disabled{opacity:.5;cursor:not-allowed;}

/* REVIEWS */
.review-wrap{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px;}
.review-item{background:#fff;border-radius:16px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.review-avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;}
.stars{color:#f59e0b;font-size:.88rem;}

/* CONTACT */
.contact-card{background:#fff;border-radius:20px;padding:36px;box-shadow:0 4px 20px rgba(0,0,0,.07);}
.input-box{position:relative;margin-bottom:20px;}
.input-box input,.input-box textarea{
  width:100%;padding:14px 16px;border:2px solid var(--sage);
  border-radius:12px;font-size:.9rem;background:var(--cream);outline:none;
  font-family:'DM Sans',sans-serif;transition:.2s;
}
.input-box input:focus,.input-box textarea:focus{border-color:var(--green);background:#fff;}
.input-box label{
  position:absolute;top:15px;left:16px;font-size:.84rem;color:#999;
  pointer-events:none;transition:.2s;background:transparent;
}
.input-box input:focus~label,.input-box input:not(:placeholder-shown)~label,
.input-box textarea:focus~label,.input-box textarea:not(:placeholder-shown)~label{
  top:0;font-size:.7rem;background:#fff;padding:0 4px;color:var(--green);border-radius:4px;
}
.input-box textarea{min-height:100px;resize:none;}
.social-icon{
  width:40px;height:40px;border-radius:50%;background:var(--sage);color:var(--green);
  display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;
}
.social-icon:hover{background:var(--green);color:#fff;}

.footer {
  display: flex;
  align-items: center;   /* vertical align */
  justify-content: center; /* center horizontally */
  gap: 8px; /* space between logo & text */
  padding: 20px;
}

.footer-logo {
  width: 40px;
}

.brand {
  font-size: 20px;
  font-weight: bold;
  color: var(--deep-green);
}

.toast-fixed{
  position:fixed;bottom:24px;right:24px;
  background:var(--green);color:#fff;padding:14px 22px;
  border-radius:12px;font-size:.86rem;z-index:9999;
  opacity:0;transform:translateY(10px);transition:.3s;pointer-events:none;
  max-width:300px;box-shadow:0 6px 24px rgba(0,0,0,.2);
}
.toast-fixed.show{opacity:1;transform:translateY(0);}
.toast-fixed.error{background:#dc2626;}
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
        <a href="#products" class="nav-link">Products</a>
        <a href="#about"    class="nav-link">About</a>

        <?php if ($userEmail): ?>

          <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border shadow-sm gap-2">
            <div class="text-end d-none d-md-block">
              <p class="mb-0 fw-bold" style="font-size:.8rem;color:var(--green);"><?= htmlspecialchars($userName) ?></p>
              <?php if ($loginTime): ?>
            <small class="text-muted" style="font-size:.6rem;">
  <span id="liveTime"></span>
</small>      <?php endif; ?>
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

  <!-- Cart icon -->
<a href="profile.php" class="position-relative text-decoration-none fs-5" title="Cart">
  🛒
  <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success"
        style="font-size:.55rem;">
        <?= $cartCount ?>
  </span>
</a>

        <?php else: ?>
          <a href="logsign.php" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold">Log In</a>
          <a href="logsign.php" class="position-relative text-decoration-none fs-5" title="Cart">🛒</a>
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
    <p>No products yet. <?php if ($userRole==='admin'): ?><a href="admin.php">Add products →</a><?php endif; ?></p>
  </div>
  <?php else: ?>
  <div class="row g-4">
    <?php foreach ($inventory as $item):
      $item       = (object)$item;
      $cleanPrice = (float)str_replace([',','₱',' '], '', $item->price);
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
            <span class="stock-badge <?= $outOfStock?'bg-danger':'' ?>">
              <?= $outOfStock ? 'Out of Stock' : 'Stock: '.$item->stock ?>
            </span>
          </div>
          <div class="product-price mb-2">₱<?= number_format($cleanPrice) ?></div>
          <div class="small text-muted mb-2">
            <?php if (!empty($item->size)):  ?><span><b>Size:</b> <?= htmlspecialchars($item->size) ?></span><?php endif; ?>
            <?php if (!empty($item->color)): ?> &nbsp;·&nbsp; <span><b>Color:</b> <?= htmlspecialchars($item->color) ?></span><?php endif; ?>
          </div>
          <p class="small text-dark mb-3" style="line-height:1.5;">
            <span class="desc-short"><?= htmlspecialchars(mb_substr($item->description??'', 0, 70)) ?>...</span>
            <span class="desc-full d-none"><?= htmlspecialchars($item->description??'') ?></span>
            <a href="javascript:void(0)" class="text-success see-more" onclick="toggleDesc(this)" style="font-size:.75rem;">See More</a>
          </p>

          <!-- Qty + Cart -->
          <div class="input-group mb-3" style="border:2px solid var(--sage);border-radius:10px;overflow:hidden;">
            <span class="input-group-text border-0 bg-white text-muted small">Qty</span>
            <input type="number" id="qty-<?= $item->id ?>" class="form-control border-0 text-center"
                   value="1" min="1" max="<?= $item->stock ?>" <?= $outOfStock?'disabled':'' ?>>
          </div>

          <button class="btn-cart" id="btn-<?= $item->id ?>"
            onclick="addToCart(<?= $item->id ?>, <?= json_encode($item->name) ?>, <?= $cleanPrice ?>)"
            <?= $outOfStock?'disabled':'' ?>>
            <i class="fas fa-shopping-cart me-2"></i>
            <?= $outOfStock ? 'Out of Stock' : 'Add to Cart' ?>
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
<div id="toast-msg" class="toast-fixed"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>

function updateTime() {
  const now = new Date();

  const options = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  };

  document.getElementById('liveTime').textContent =
    now.toLocaleString('en-PH', options);
}

// update every second
setInterval(updateTime, 1000);
updateTime();

function toggleDesc(btn){
  const p = btn.parentElement;
  const s = p.querySelector('.desc-short');
  const f = p.querySelector('.desc-full');
  const hidden = f.classList.contains('d-none');
  f.classList.toggle('d-none',!hidden);
  s.classList.toggle('d-none', hidden);
  btn.textContent = hidden ? 'See Less' : 'See More';
}

function showToast(msg, type='success'){
  const t = document.getElementById('toast-msg');
  t.textContent = msg;
  t.className   = 'toast-fixed show' + (type==='error'?' error':'');
  setTimeout(()=>t.classList.remove('show'), 3500);
}

function addToCart(id, name, price){
  <?php if (!$userEmail): ?>
    window.location.href = 'logsign.php'; return;
  <?php endif; ?>

  const qtyEl = document.getElementById('qty-' + id);
  const qty   = qtyEl ? parseInt(qtyEl.value) || 1 : 1;

  fetch('addcart.php', {
    method : 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body   : `id=${encodeURIComponent(id)}&name=${encodeURIComponent(name)}&price=${encodeURIComponent(price)}&qty=${qty}`
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.success){
      document.getElementById('cart-badge').textContent = data.total_items;
      showToast('🛒 ' + name + ' added to cart!');
    } else if(data.redirect){
      window.location.href = data.redirect;
    } else {
      showToast(data.message || 'Error.', 'error');
    }
  })
  .catch(()=>showToast('Connection error.','error'));
}


document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t=document.querySelector(a.getAttribute('href'));
    if(t){e.preventDefault();t.scrollIntoView({behavior:'smooth'});}
  });
});
</script>
</body>
</html>