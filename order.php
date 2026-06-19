<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$userRole  = $_SESSION['role'] ?? 'user';

if ($userRole === 'admin') {
    header('Location: admin.php');
    exit;
}

$db    = getDBConnection();
$dbUser = findUserByEmail($userEmail);
$uObj = $dbUser;
$userName = $dbUser->name ?? '';
$loginTime = $_SESSION['login_time'] ?? null;

if (!$dbUser) {
    header('Location: logsign.php');
    exit;
}

// Handle review submission
$reviewErrors  = [];
$reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_order_id'])) {
    $reviewOrderId = trim($_POST['review_order_id'] ?? '');
    $ratingValue   = $_POST['rating'] ?? null;
    $rating        = is_numeric($ratingValue) ? (int)$ratingValue : 0;
    $comment       = trim($_POST['comment'] ?? '');

    if ($reviewOrderId === '') {
        $reviewErrors[] = 'Invalid order reference.';
    }
    if ($rating < 1 || $rating > 5) {
        $reviewErrors[] = 'Please select a rating from 1 to 5 stars.';
    }
    if ($comment === '') {
        $reviewErrors[] = 'Please write your review so we can share it with other customers.';
    }

    if (empty($reviewErrors)) {
        $checkStmt = $db->prepare("
            SELECT o.order_status AS status
            FROM orders o
            JOIN users u ON u.user_id = o.user_id
            WHERE o.order_id = ? AND u.email = ?
            LIMIT 1
        ");
        $checkStmt->execute([$reviewOrderId, $userEmail]);
        $reviewOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$reviewOrder) {
            $reviewErrors[] = 'Order not found.';
        } elseif (!in_array(strtolower($reviewOrder['status'] ?? $reviewOrder->status ?? ''), ['delivered', 'completed'], true)) {
            $reviewErrors[] = 'Reviews are only accepted after your order has been delivered.';
        }
    }

    if (empty($reviewErrors)) {
        saveReviewForOrder($userEmail, $reviewOrderId, $rating, $comment);
        header('Location: order.php?order_id=' . urlencode($reviewOrderId) . '&review_success=1');
        exit;
    }
}

if (!empty($_GET['review_success'])) {
    $reviewSuccess = 'Thanks! Your review has been submitted successfully.';
}

// Load all orders with their items
$allOrders = loadUserOrders($userEmail);

$orderId       = trim($_GET['order_id'] ?? '');
$returnTarget  = trim($_GET['return'] ?? '');
$allowedReturn = in_array($returnTarget, ['profile'], true) ? $returnTarget : '';
$backUrl       = 'profile.php';
$backLabel     = 'Back to Profile';
$selectedOrder = null;

if ($orderId !== '') {
    foreach ($allOrders as $order) {
        if (($order->order_id ?? '') === $orderId) {
            $selectedOrder = $order;
            break;
        }
    }
    if (!$selectedOrder) {
        header('Location: profile.php');
        exit;
    }
} else {
    $selectedOrder = $allOrders[0] ?? null;
}

if ($selectedOrder) {
    $orderId = $selectedOrder->order_id ?? $orderId;
    $oReview = loadReviewForOrder($orderId);
}

$selectedRating = 5;
$selectedComment = '';
$showReviewEditForm = false;
if ($oReview) {
    $selectedRating = (int)($oReview->rating ?? 5);
    $selectedComment = trim((string)($oReview->comment ?? ''));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_order_id'])) {
    $selectedRating = is_numeric($_POST['rating'] ?? null) ? (int)$_POST['rating'] : $selectedRating;
    $selectedComment = trim((string)($_POST['comment'] ?? $selectedComment));
    $showReviewEditForm = true;
} elseif (!empty($_GET['edit_review']) && $oReview) {
    $showReviewEditForm = true;
}

$cartItems = loadCartForUser($userEmail);
$cartCount = 0;
foreach ($cartItems as $ci) $cartCount += (int)($ci['qty'] ?? 1);

$orderPlacedFlash = '';
$orderPlacedId    = '';
if (!empty($_GET['order_placed']) && !empty($_GET['order_id'])) {
    $orderPlacedFlash = 'Your order has been placed successfully!';
    $orderPlacedId    = htmlspecialchars($_GET['order_id']);
}

$statusSteps = ['Pending', 'Processing', 'Shipped', 'Delivered'];

function getStepIndex(string $status): int {
    $map = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3, 'completed' => 3];
    return $map[strtolower($status)] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZYTHERA | Order Details</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/order.css">
  
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;">

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

<div class="flex-fill">
<div class="container py-4" style="max-width:900px;">
  <div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <div class="section-label">Order Tracking</div>
        <h2><?php if ($orderId !== ''): ?>Order #<?= htmlspecialchars($orderId) ?><?php else: ?>Latest Order<?php endif; ?></h2>
        <p class="text-muted mt-1" style="font-size:.85rem;">Track the current status of your order and see delivery information.</p>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary rounded-pill">
          <i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars($backLabel) ?>
        </a>
        <button type="button" class="btn btn-sm btn-success rounded-pill" onclick="downloadReceipt()" id="downloadReceiptBtn">
          <i class="fas fa-download me-1"></i> Download Receipt
        </button>
      </div>
    </div>
  </div>

  

  <?php if (!$selectedOrder): ?>
  <div class="empty-state">
    <i class="fas fa-box-open"></i>
    <p class="fw-semibold" style="color:#aaa;">Could not find that order.</p>
    <a href="profile.php" class="btn btn-sm btn-outline-success rounded-pill mt-2 px-4">Back to Profile</a>
  </div>
  <?php else: ?>

  <?php
    $o           = $selectedOrder;
    $oStatus     = $o->status ?? 'Pending';
    $isCancelled = strtolower($oStatus) === 'cancelled';
    $stepIndex   = $isCancelled ? -1 : getStepIndex($oStatus);
    $oItems      = $o->items ?? [];
    $subtotal    = (float)($o->subtotal ?? 0);
    $shipping    = (float)($o->shipping ?? 150);
    $total       = (float)($o->total ?? ($subtotal + $shipping));
    $oDate       = $o->date ?? '';
    $orderId     = $o->order_id ?? '—';
    $payMethod   = $o->pay_method ?? '';
    $payStatus   = $o->pay_status ?? 'pending';
    $payRef      = $o->pay_reference ?? '';
    $fullName    = $o->full_name ?? '';
    $phone       = $o->phone     ?? '';
    $address     = $o->address   ?? '';
    $barangay    = $o->barangay  ?? '';
    $city        = $o->city      ?? '';
    $province    = $o->province  ?? '';
    $zip         = $o->zip       ?? '';
    $notes       = $o->notes     ?? '';
    $isDelivered = in_array(strtolower($oStatus), ['delivered', 'completed'], true);
    $totalLabel  = ($payMethod === 'Cash on Delivery (COD)' && !$isDelivered) ? 'Total Due' : 'Total Paid';

    $stClass = match(strtolower($oStatus)) {
      'processing'             => 'st-processing',
      'shipped'                => 'st-shipped',
      'delivered','completed'  => 'st-delivered',
      'cancelled'              => 'st-cancelled',
      default                  => 'st-pending',
    };

    $progressPct = 0;
    if (!$isCancelled) {
        $progressPct = min(100, $stepIndex * (100 / 3));  // 0, 33.33, 66.66, 100
    }
  ?>

  <div class="section-card">
    <div class="order-box" data-order-id="<?= htmlspecialchars($orderId) ?>">

      <!-- Order Meta Grid -->
      <div class="row g-2 align-items-center mb-3">
        <div class="col-6 col-md-3">
          <small class="text-muted d-block" style="font-size:.7rem;">Order #</small>
          <div class="fw-bold" style="color:var(--deep);font-size:.88rem;"><?= htmlspecialchars($orderId) ?></div>
        </div>
        <div class="col-6 col-md-3">
          <small class="text-muted d-block" style="font-size:.7rem;">Date &amp; Time</small>
          <div class="fw-bold" style="color:var(--deep);font-size:.88rem;"><?= $oDate ? date('M d, Y h:i A', strtotime($oDate)) : 'N/A' ?></div>
        </div>
        <div class="col-6 col-md-3">
          <small class="text-muted d-block" style="font-size:.7rem;">Payment Method</small>
          <div class="fw-bold" style="color:var(--deep);font-size:.88rem;"><?= htmlspecialchars($payMethod ?: 'N/A') ?></div>
          <?php if ($payMethod === 'Cash on Delivery (COD)' && !$isDelivered): ?>
          <div style="font-size:.78rem;color:#b45309;margin-top:6px;">Cash on Delivery — payment due on delivery.</div>
          <?php else: ?>
          <?php
            $psColors = [
                'pending'  => ['bg'=>'#fff7ed','color'=>'#b45309','border'=>'#fde68a'],
                'verified' => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0'],
                'rejected' => ['bg'=>'#fef2f2','color'=>'#b91c1c','border'=>'#fecaca'],
            ];
            $psc = $psColors[$payStatus] ?? $psColors['pending'];
          ?>
          <span style="display:inline-block;margin-top:4px;background:<?= $psc['bg'] ?>;color:<?= $psc['color'] ?>;border:1px solid <?= $psc['border'] ?>;border-radius:50px;padding:1px 9px;font-size:.68rem;font-weight:700;text-transform:capitalize;">
            <?php if ($payStatus === 'verified'): ?>
              <i class="fas fa-check-circle" style="margin-right:3px;"></i>Payment Verified
            <?php elseif ($payStatus === 'rejected'): ?>
              <i class="fas fa-times-circle" style="margin-right:3px;"></i>Payment Rejected
            <?php else: ?>
              <i class="fas fa-clock" style="margin-right:3px;"></i>Awaiting Verification
            <?php endif; ?>
          </span>
          <?php if ($payRef): ?>
          <div style="font-size:.7rem;color:#888;margin-top:2px;">Ref: <?= htmlspecialchars($payRef) ?></div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="col-6 col-md-3 text-md-end">
          <small class="text-muted d-block" style="font-size:.7rem;">Status</small>
          <span class="order-status <?= $stClass ?> dyn-status-badge"><?= htmlspecialchars($oStatus) ?></span>
        </div>
      </div>

      <!-- Order Status Timeline -->
      <?php if ($isCancelled): ?>
      <div class="cancelled-bar"><i class="fas fa-times-circle fa-lg"></i>This order was cancelled.</div>
      <?php else: ?>
      <div class="section-label mb-1">Order Status</div>

      <div class="timeline-wrap" id="timeline-<?= htmlspecialchars($orderId) ?>">
        <!-- Green progress overlay (positioned by PHP, exact percentage) -->
        <div class="timeline-progress" style="width: calc(<?= $progressPct ?>% * 6 / 8);"></div>

        <?php foreach ($statusSteps as $idx => $step):
          $isDone   = $idx < $stepIndex;
          $isActive = $idx === $stepIndex;
          $isFuture = $idx > $stepIndex;
          $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'future');
          $icons    = ['fas fa-clock','fas fa-cog fa-spin-on-active','fas fa-truck','fas fa-check-circle'];
          $icon     = ($icons[$idx] === 'fas fa-cog fa-spin-on-active') ? ($isActive ? 'fas fa-cog fa-spin' : 'fas fa-cog') : $icons[$idx];
        ?>
        <div class="tl-step">
          <div class="tl-dot <?= $dotClass ?>"><i class="<?= $icon ?>"></i></div>
          <div class="tl-label <?= $isFuture ? 'future' : '' ?>"><?= $step ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="dyn-status-msg mb-3" style="background:var(--cream);border-radius:12px;padding:10px 16px;font-size:.84rem;color:#555;">
        <?php $statusMsgs = [
          'Pending'    => 'Your order has been received and is awaiting confirmation.',
          'Processing' => 'We\'re preparing your furniture for shipment.',
          'Shipped'    => 'Your order is on its way! Estimated arrival in 3–7 business days.',
          'Delivered'  => 'Your order has been delivered. Enjoy your new furniture!',
          'Completed'  => 'Order completed. Thank you for shopping with us!',
        ];
        echo $statusMsgs[$oStatus] ?? 'Your order is being processed.';
        ?>
      </div>
      <?php endif; ?>

      <!-- Items Ordered -->
      <div class="fw-bold mb-2" style="font-size:.78rem;letter-spacing:1px;text-transform:uppercase;color:var(--green);">Items Ordered</div>
      <div style="border:2px solid var(--sage);border-radius:12px;overflow:hidden;margin-bottom:12px;">
        <?php foreach ($oItems as $oi):
          $oiName  = $oi->product_name ?? '?';
          $oiQty   = (int)($oi->qty   ?? 1);
          $oiPrice = (float)($oi->price ?? 0);
          $oiLine  = $oiPrice * $oiQty;
          $oiImg   = trim((string)($oi->image ?? '')) ?: 'pci/Group_15.png';
        ?>
        <div class="d-flex align-items-center gap-3 px-3 py-2" style="border-bottom:1px solid var(--sage);">
          <div style="width:72px;min-width:72px;">
            <img src="<?= htmlspecialchars($oiImg) ?>" alt="<?= htmlspecialchars($oiName) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:14px;border:1px solid #e5e5e5;background:#fff;">
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.88rem;font-weight:700;color:var(--deep);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($oiName) ?></div>
            <div style="font-size:.76rem;color:#999;">₱<?= number_format($oiPrice, 2) ?> × <?= $oiQty ?></div>
          </div>
          <span style="font-weight:700;color:var(--green);font-size:.88rem;white-space:nowrap;">₱<?= number_format($oiLine, 2) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Totals -->
      <div class="totals-box mb-3">
        <div class="totals-row"><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
        <div class="totals-row"><span><i class="fas fa-truck me-1"></i>Shipping</span><span>₱<?= number_format($shipping, 2) ?></span></div>
        <div class="totals-row grand"><span><?= htmlspecialchars($totalLabel) ?></span><span>₱<?= number_format($total, 2) ?></span></div>
      </div>

      <!-- Customer Details -->
      <div class="p-3" style="background:var(--cream);border-radius:10px;">
        <small class="d-block mb-1" style="font-size:.68rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--mid);">Customer Details</small>
        <div class="fw-bold" style="color:var(--deep);"><?= htmlspecialchars($fullName ?: ($dbUser->name ?? '')) ?></div>
        <div style="font-size:.83rem;color:#666;"><?= htmlspecialchars(implode(', ', array_filter([$address, $barangay, $city, $province, $zip])) ?: 'No Address Provided') ?></div>
        <div style="font-size:.83rem;color:#666;"><?= htmlspecialchars($phone ?: 'No Contact Number') ?></div>
        <?php if ($notes): ?>
        <div style="font-size:.82rem;color:#999;margin-top:4px;font-style:italic;"><i class="fas fa-sticky-note me-1" style="color:var(--terra);"></i><?= htmlspecialchars($notes) ?></div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- ── Review Section — only shown when Delivered / Completed ── -->
  <?php if ($isDelivered): ?>
  <div class="section-card">

    <?php if (!empty($reviewSuccess)): ?>
      <!-- Floating toast handled at page footer for consistent positioning -->
    <?php endif; ?>
    <?php if (!empty($reviewErrors)): ?>
      <div class="alert alert-danger p-3 mb-3" style="border-radius:16px;font-size:.88rem;">
        <?php foreach ($reviewErrors as $error): ?>
          <div><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($oReview)): ?>
      <!-- Review Form -->
      <div class="section-label mb-2">Leave a Review</div>
      <p style="font-size:.83rem;color:#888;margin-bottom:16px;">Share your experience with this order — your feedback helps other customers!</p>
      <form method="POST">
        <input type="hidden" name="review_order_id" value="<?= htmlspecialchars($orderId) ?>">

        <!-- Star Rating (RTL so :checked ~ highlights correctly) -->
        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Your Rating</label>
          <div class="review-star-group" id="starGroup">
            <?php for ($star = 5; $star >= 1; $star--): ?>
              <input type="radio" name="rating" id="star<?= $star ?>" value="<?= $star ?>"
                     <?= ($selectedRating === $star) ? 'checked' : '' ?>>
              <label for="star<?= $star ?>" title="<?= $star ?> star<?= $star > 1 ? 's' : '' ?>">&#9733;</label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" style="font-size:.85rem;">Write Your Review</label>
          <textarea name="comment" rows="4" class="form-control" style="border-radius:12px;border:2px solid var(--sage);font-size:.88rem;resize:vertical;"
            placeholder="What did you love about your purchase?
How was delivery? 
Any feedback for us?"
            required><?= htmlspecialchars($selectedComment) ?></textarea>
        </div>

        <button type="submit" class="btn btn-success px-4 rounded-pill" style="background:var(--green);border-color:var(--green);">
        Submit Review
        </button>
      </form>

    <?php else: ?>
      <!-- Existing Review -->
      <div class="section-label mb-2">Your Review</div>
      <div style="background:var(--cream);border-radius:16px;padding:20px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
          <?php
            $authorPic = getAvatarURL($oReview->author_pic ?? null, $oReview->author_email ?? null, $oReview->author_name ?? null, 60);
          ?>
          <img src="<?= htmlspecialchars($authorPic) ?>"
               alt="Reviewer" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--sage);">
          <div>
            <div style="font-weight:700;color:var(--deep);font-size:.95rem;"><?= htmlspecialchars($oReview->author_name ?: ($fullName ?: 'You')) ?></div>
            <div style="color:#f5c842;font-size:1.1rem;letter-spacing:2px;"><?= str_repeat('★', max(1, min(5, (int)$oReview->rating))) ?></div>
          </div>
        </div>
        <p style="line-height:1.75;color:#555;margin:0;font-size:.88rem;"><?= nl2br(htmlspecialchars($oReview->comment)) ?></p>
        <div class="d-flex align-items-center justify-content-between gap-3" style="margin-top:16px;flex-wrap:wrap;">
          <div style="color:#aaa;font-size:.78rem;"><i class="fas fa-calendar-alt me-1"></i>Submitted on <?= htmlspecialchars(date('F d, Y', strtotime($oReview->created_at))) ?></div>
          <button type="button" class="btn btn-outline-success btn-sm" onclick="toggleReviewEditForm(true)">
            <i class="fas fa-edit me-1"></i>Edit Review
          </button>
        </div>
      </div>

      <div id="review-edit-form" style="display:<?= $showReviewEditForm ? '' : 'none' ?>;margin-top:20px;padding:20px;background:var(--cream);border-radius:16px;">
        <div class="section-label mb-2"><?= $oReview ? 'Edit Your Review' : 'Leave a Review' ?></div>
        <p style="font-size:.83rem;color:#888;margin-bottom:16px;">Update your rating or comment and save the changes.</p>
        <form method="POST">
          <input type="hidden" name="review_order_id" value="<?= htmlspecialchars($orderId) ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Rating</label>
            <div class="review-star-group" id="starEditGroup">
              <?php for ($star = 5; $star >= 1; $star--): ?>
                <input type="radio" name="rating" id="editStar<?= $star ?>" value="<?= $star ?>"
                       <?= ($selectedRating === $star) ? 'checked' : '' ?>>
                <label for="editStar<?= $star ?>" title="<?= $star ?> star<?= $star > 1 ? 's' : '' ?>">&#9733;</label>
              <?php endfor; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Review</label>
            <textarea name="comment" rows="4" class="form-control" style="border-radius:12px;border:2px solid var(--sage);font-size:.88rem;resize:vertical;"
              placeholder="What did you love about your purchase? How was delivery? Any feedback for us?"
              required><?= htmlspecialchars($selectedComment) ?></textarea>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-success px-4 rounded-pill" style="background:var(--green);border-color:var(--green);">
              Save Changes
            </button>
            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="toggleReviewEditForm(false)">
              Cancel
            </button>
          </div>
        </form>
      </div>
    <?php endif; ?>

  </div><!-- /review section-card -->
  <?php endif; ?>

  <?php endif; ?>
</div>
</div>

<footer>
  <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
  <span class="footer-brand"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></span>
</footer>

<!-- Review success toast (lower-left) -->
<?php if (!empty($reviewSuccess)): ?>
  <?php $toastMessage = $reviewSuccess; $toastType = 'success'; include __DIR__ . '/includes/_toast.php'; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jsPDF + html2canvas for client-side PDF receipt generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNa5m3fW6VYqk1+g6z3Kx1Yb3bA8gQXk3YJm1XJZ0q5Qk6YFzE6YjH0j1xK9Qm2L5h2w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" integrity="sha512-+qXK2mWzV4p2J5s9sV6jQ1x1v9y8g3r0lR5t8V6s2r7w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script>
    /* PHP-seeded globals for order.js */
    const ORDER_DATA = {
      orderId:    <?= json_encode($orderId) ?>,
      date:       <?= json_encode($oDate) ?>,
      status:     <?= json_encode($oStatus) ?>,
      payMethod:  <?= json_encode($payMethod) ?>,
      fullName:   <?= json_encode($fullName) ?>,
      phone:      <?= json_encode($phone) ?>,
      address:    <?= json_encode($address) ?>,
      barangay:   <?= json_encode($barangay) ?>,
      city:       <?= json_encode($city) ?>,
      province:   <?= json_encode($province) ?>,
      zip:        <?= json_encode($zip) ?>,
      subtotal:   <?= json_encode($subtotal) ?>,
      shipping:   <?= json_encode($shipping) ?>,
      total:      <?= json_encode($total) ?>,
      isDelivered:<?= json_encode($isDelivered) ?>,
      items:      <?= json_encode(array_map(function($oi){ return [
        'name'     => $oi->product_name ?? $oi->item_name ?? '',
        'qty'      => (int)($oi->qty ?? $oi->quantity ?? 1),
        'price'    => (float)($oi->price ?? 0),
        'subtotal' => (float)($oi->price ?? 0) * (int)($oi->qty ?? $oi->quantity ?? 1),
        'image'    => $oi->image ?? '',
      ];}, $oItems)) ?>
    };
  </script>
  <script src="assets/js/order.js"></script>
</body>
</html>
