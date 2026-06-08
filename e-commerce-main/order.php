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
$uStmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$uStmt->execute([$userEmail]);
$dbUser = $uStmt->fetch();

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
        $checkStmt = $db->prepare("SELECT status FROM orders WHERE order_id = ? AND email = ? LIMIT 1");
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
$allOrders = [];
$oStmt = $db->prepare("SELECT * FROM orders WHERE email = ? ORDER BY date DESC");
$oStmt->execute([$userEmail]);
$rawOrders = $oStmt->fetchAll();
foreach ($rawOrders as $ord) {
    $iStmt = $db->prepare("SELECT oi.*, inv.image AS image FROM order_items oi LEFT JOIN inventory inv ON inv.inv_id = oi.inv_id WHERE oi.ord_no = ?");
    $iStmt->execute([$ord->ord_no ?? $ord->id ?? 0]);
    $ord->items = $iStmt->fetchAll();
    $allOrders[] = $ord;
}

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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Lora',serif}
  body{font-family:var(--ui-font);}
  h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
  p,small,.caption,.text-muted{font-family:var(--text-font);}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dark-mode.css">
<script src="dark-mode.js"></script>
<style>
    :root {
        --green: #2d5a2d;
        --sage: #d4e4d4;
        --cream: #f5f2ec;
        --deep: #1a2e1a;
        --mid: #7aab7a;
        --terra: #bc8a7b;
    }
    * { font-family: var(--ui-font); box-sizing: border-box; }
    body { background: var(--cream); display: flex; flex-direction: column; min-height: 100vh; margin: 0; }
    .navbar { background: #fff; box-shadow: 0 1px 12px rgba(0,0,0,.07); }
    .navbar-brand { font-family: 'Playfair Display', serif; color: var(--green) !important; letter-spacing: 4px; font-size: 1.5rem; }

    .page-header { padding: 32px 0 8px; }
    .page-header h2 { font-family: 'Playfair Display', serif; color: var(--deep); margin: 0; }
    .section-label { font-size: .68rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--mid); margin-bottom: 4px; }

    .section-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,.05);
    }

    .order-box {
        border: 2px solid var(--sage);
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 14px;
        transition: .2s;
    }
    .order-box:hover { border-color: var(--mid); }

    .order-status {
        display: inline-block;
        font-size: .68rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        letter-spacing: .5px;
        text-transform: uppercase;
    }
    .st-pending    { background: #fef9c3; color: #b45309; }
    .st-processing { background: #dbeafe; color: #1d4ed8; }
    .st-shipped    { background: #e0f2fe; color: #0369a1; }
    .st-completed,
    .st-delivered  { background: #dcfce7; color: #16a34a; }
    .st-cancelled  { background: #fee2e2; color: #b91c1c; }

    /* ── Timeline ── */
    .timeline-wrap {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 18px 0 8px;
        position: relative;
    }
    /* Grey base track — spans between first and last dot centres */
    .timeline-wrap::before {
        content: '';
        position: absolute;
        top: 34px; /* vertically centres on the 32px dot (18px padding + 16px = 34px) */
        left: calc(100% / 8);        /* centre of step 1 (of 4) */
        width: calc(100% * 6 / 8);   /* from step 1 centre to step 4 centre */
        height: 3px;
        background: var(--sage);
        z-index: 0;
    }
    /* Green progress track — overlays the grey track */
    .timeline-progress {
        position: absolute;
        top: 34px;
        left: calc(100% / 8);
        height: 3px;
        background: var(--green);
        z-index: 1;
        transition: width .4s ease;
    }

    .tl-step { flex: 1; text-align: center; position: relative; z-index: 2; }
    .tl-dot {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 8px; font-size: .82rem;
        border: 3px solid var(--sage); background: #fff;
        transition: .3s; position: relative; z-index: 2;
    }
    .tl-dot.done   { background: var(--green); border-color: var(--green); color: #fff; }
    .tl-dot.active { background: var(--green); border-color: var(--green); color: #fff; box-shadow: 0 0 0 5px rgba(45,90,45,.18); animation: pulse 1.8s infinite; }
    .tl-dot.future { background: #fff; border-color: var(--sage); color: #ccc; }
    .tl-label { font-size: .72rem; font-weight: 600; color: var(--deep); }
    .tl-label.future { color: #bbb; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 4px rgba(45,90,45,.18);}50%{box-shadow:0 0 0 8px rgba(45,90,45,.08);} }

    .cancelled-bar { display: flex; align-items: center; gap: 10px; background: #fef2f2; border-radius: 14px; padding: 14px 18px; color: #b91c1c; font-weight: 600; font-size: .88rem; border: 1px solid #fecaca; margin-bottom: 8px; }

    .totals-box { background: var(--cream); border-radius: 14px; padding: 14px 18px; margin-top: 4px; }
    .totals-row { display: flex; justify-content: space-between; font-size: .85rem; color: #777; padding: 3px 0; }
    .totals-row.grand { font-size: 1rem; font-weight: 800; color: var(--green); border-top: 2px solid var(--sage); padding-top: 10px; margin-top: 6px; }

    .empty-state { text-align: center; padding: 60px 20px; color: #bbb; }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }

    /* ── Review section ── */
    .review-star-group { display: flex; gap: 6px; font-size: 2rem; color: #d1d5db; }
    .review-star-group label { cursor: pointer; transition: color .15s; }
    .review-star-group input[type="radio"] { display: none; }
    .review-star-group input[type="radio"]:checked ~ label,
    .review-star-group label:hover,
    .review-star-group label:hover ~ label { color: var(--green); }
    /* RTL trick so :checked ~ works correctly */
    .review-star-group { flex-direction: row-reverse; justify-content: flex-end; }

    footer { background: #f5f2ec; padding: 22px 20px; display: flex; align-items: center; justify-content: center; gap: 12px; border-top: 1px solid #e8e4dc; margin-top: auto; }
    footer .footer-brand { font-family: 'Playfair Display', serif; color: var(--green); font-size: 1rem; letter-spacing: 4px; }
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
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;">

<nav class="navbar navbar-light px-4 py-2 fixed-top">
  <a class="navbar-brand fw-bold" href="website.php"><span style="color:var(--deep)">ZYTHERA</span></a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Shop</a>
    <a href="logout.php" class="btn btn-sm btn-danger rounded-pill">Logout</a>
  </div>
</nav>
<div style="height:60px;"></div>

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
    $fullName    = $o->full_name ?? '';
    $phone       = $o->phone     ?? '';
    $address     = $o->address   ?? '';
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
        <div style="font-size:.83rem;color:#666;"><?= htmlspecialchars(implode(', ', array_filter([$address, $city, $province, $zip])) ?: 'No Address Provided') ?></div>
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
            placeholder="What did you love about your purchase? How was delivery? Any feedback for us?"
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
        <?php if (!empty($oReview->reply)): ?>
        <div style="margin-top:16px;padding:14px;border-radius:14px;background:var(--sage-light);color:#2d5a2d;font-size:.86rem;line-height:1.7;">
          <strong style="display:block;margin-bottom:8px;color:var(--deep-green);">Admin Reply</strong>
          <?= nl2br(htmlspecialchars($oReview->reply)) ?>
          <?php if (!empty($oReview->reply_created_at)): ?>
          <div style="margin-top:10px;color:#666;font-size:.78rem;"><i class="fas fa-clock me-1"></i>Replied on <?= htmlspecialchars(date('F d, Y', strtotime($oReview->reply_created_at))) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
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
  <span class="footer-brand"><span style="color:var(--deep)">ZYTHERA</span></span>
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
/* ── Star rating hover effect ── */
(function () {
  const group = document.getElementById('starGroup');
  if (!group) return;
  const labels = [...group.querySelectorAll('label')].reverse(); // re-reverse for display order
  labels.forEach((lbl, i) => {
    lbl.addEventListener('mouseenter', () => {
      labels.forEach((l, j) => l.style.color = j <= i ? '#f5c842' : '#d1d5db');
    });
    lbl.addEventListener('mouseleave', () => {
      labels.forEach(l => l.style.color = '');
    });
  });
})();

/* ── Live status polling ── */
function pollOrderStatus() {
  fetch('get_order.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.orders) return;
      data.orders.forEach(o => {
        const badge = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-badge');
        if (badge) {
          badge.textContent = o.status;
          badge.className = 'order-status dyn-status-badge ' + statusClass(o.status);
        }
        const msg = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-msg');
        if (msg) msg.textContent = statusMsg(o.status);
      });
    }).catch(() => {});
}

function statusClass(s) {
  const m = { pending:'st-pending', processing:'st-processing', shipped:'st-shipped', delivered:'st-delivered', completed:'st-delivered', cancelled:'st-cancelled' };
  return m[s.toLowerCase()] || 'st-pending';
}
function statusMsg(s) {
  const m = {
    'Pending':    'Your order has been received and is awaiting confirmation.',
    'Processing': 'We\'re preparing your furniture for shipment.',
    'Shipped':    'Your order is on its way! Estimated arrival in 3–7 business days.',
    'Delivered':  'Your order has been delivered. Enjoy your new furniture!',
    'Completed':  'Order completed. Thank you for shopping with us!',
    'Cancelled':  'This order was cancelled.',
  };
  return m[s] || 'Your order is being processed.';
}

function downloadReceipt() {
  const data = window.orderReceiptData || {};
  const receiptHTML = `
  <div style="font-family: Arial, Helvetica, sans-serif; color:#163a2d; padding:12px; max-width:540px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <img src="pci/Group_15.png" style="width:48px;height:auto;object-fit:contain;" alt="Zythera">
      <div>
        <div style="font-size:18px;font-weight:700;color:#0f5132;letter-spacing:0.6px;">ZYTHERA</div>
        <div style="font-size:11px;color:#4b5563;margin-top:2px;">Official Receipt</div>
      </div>
    </div>
    <div style="height:8px"></div>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
      <div style="font-size:12px;color:#274c3b;line-height:1.35;">
        <div><strong style="font-weight:600;color:#0f5132;">Order:</strong> ${data.orderId || ''}</div>
        <div><strong style="font-weight:600;color:#0f5132;">Date:</strong> ${data.date || ''}</div>
        <div><strong style="font-weight:600;color:#0f5132;">Payment:</strong> ${data.payMethod || ''}</div>
      </div>
      <div style="text-align:right;font-size:13px;color:#0f5132;font-weight:700;">₱${Number(data.total || 0).toFixed(2)}</div>
    </div>

    <div style="height:10px"></div>
    <div style="font-size:12px;color:#334e3d;">
      <div style="font-weight:600;color:#0f5132;margin-bottom:6px;">Bill To</div>
      <div style="line-height:1.4;">${data.fullName || ''}</div>
      <div style="line-height:1.4;">${data.phone || ''}</div>
      <div style="line-height:1.4;">${data.address || ''}${data.city ? ', ' + data.city : ''}${data.province ? ', ' + data.province : ''}${data.zip ? ' ' + data.zip : ''}</div>
    </div>

    <div style="height:12px"></div>
    <table style="width:100%;border-collapse:collapse;font-size:12px;color:#142a22;">
      ${(data.items || []).map(function(item){ return `
        <tr>
          <td style="padding:6px 0;border-bottom:1px solid #f3f7f3;vertical-align:top;">${item.name}<div style=\"font-size:11px;color:#566b5f;margin-top:4px;\">Qty: ${item.qty} × ₱${Number(item.price).toFixed(2)}</div></td>
          <td style="padding:6px 0;text-align:right;border-bottom:1px solid #f3f7f3;vertical-align:top;">₱${Number(item.subtotal).toFixed(2)}</td>
        </tr>`; }).join('')}
    </table>

    <div style="margin-top:8px;font-size:12px;display:flex;justify-content:space-between;color:#2f3f35;">
      <div>Subtotal</div>
      <div>₱${Number(data.subtotal || 0).toFixed(2)}</div>
    </div>
    <div style="font-size:12px;display:flex;justify-content:space-between;color:#2f3f35;">
      <div>Shipping</div>
      <div>₱${Number(data.shipping || 0).toFixed(2)}</div>
    </div>

    <div style="margin-top:8px;padding:10px;background:#f0f9f4;border-radius:8px;display:flex;justify-content:space-between;align-items:center;font-size:14px;font-weight:700;color:#0f5132;">
      <div>Total Due</div>
      <div>₱${Number(data.total || 0).toFixed(2)}</div>
    </div>

    ${ (data.payMethod === 'Cash on Delivery (COD)' && !data.isDelivered) ? `<div style="margin-top:10px;color:#b45309;font-size:12px;">NOTE: Cash on Delivery — payment due on delivery.</div>` : '' }

    <div style="height:10px"></div>
    <div style="font-size:11px;color:#476157;">Thank you for shopping with Zythera. For support, visit our website or contact us.</div>
  </div>
  `;

  // create a temporary container to render HTML
  const container = document.createElement('div');
  container.style.width = '750px';
  container.style.background = '#fff';
  container.style.padding = '6px';
  container.innerHTML = receiptHTML;
  document.body.appendChild(container);

  // if jspdf + html2canvas loaded, use them
  if (window.jspdf && window.html2canvas) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    doc.html(container, {
      callback: function (doc) {
        doc.save('ZYTHERA_receipt_' + (data.orderId || 'order') + '.pdf');
        if (container.parentNode) container.parentNode.removeChild(container);
      },
      x: 20,
      y: 20,
      html2canvas: { scale: 1.2 }
    });
  } else {
    // fallback: open printable window (user can Save as PDF)
    const w = window.open('', '_blank');
    w.document.write('<html><head><title>Receipt</title></head><body>' + receiptHTML + '</body></html>');
    w.document.close();
    w.focus();
    w.print();
    if (container.parentNode) container.parentNode.removeChild(container);
  }
}

window.orderReceiptData = {
  orderId: <?= json_encode($orderId) ?>,
  date: <?= json_encode($oDate) ?>,
  status: <?= json_encode($oStatus) ?>,
  payMethod: <?= json_encode($payMethod) ?>,
  fullName: <?= json_encode($fullName) ?>,
  phone: <?= json_encode($phone) ?>,
  address: <?= json_encode($address) ?>,
  city: <?= json_encode($city) ?>,
  province: <?= json_encode($province) ?>,
  zip: <?= json_encode($zip) ?>,
  subtotal: <?= json_encode($subtotal) ?>,
  shipping: <?= json_encode($shipping) ?>,
  total: <?= json_encode($total) ?>,
  isDelivered: <?= json_encode($isDelivered) ?>,
  items: <?= json_encode(array_map(function($oi){ return [
      'name' => $oi->product_name ?? '',
      'qty' => (int)($oi->qty ?? 1),
      'price' => (float)($oi->price ?? 0),
      'subtotal' => (float)($oi->price ?? 0) * (int)($oi->qty ?? 1),
    ];}, $oItems)) ?>
};

function toggleReviewEditForm(show) {
  const form = document.getElementById('review-edit-form');
  if (!form) return;
  form.style.display = show ? '' : 'none';
  if (show) {
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

setInterval(pollOrderStatus, 30000);
</script>
</body>
</html>