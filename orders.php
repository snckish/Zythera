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

if (!$dbUser) {
    header('Location: logsign.php');
    exit;
}

header('Location: profile.php');
exit;

$reviewErrors = [];
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
        $orderCheck = $checkStmt->fetch();

        if (!$orderCheck) {
            $reviewErrors[] = 'Order not found.';
        } elseif (!in_array(strtolower($orderCheck->status), ['delivered', 'completed'], true)) {
            $reviewErrors[] = 'Reviews are only accepted after your order has been delivered.';
        } else {
            saveReviewForOrder($userEmail, $reviewOrderId, $rating, $comment);
            $reviewSuccess = 'Thanks! Your review has been submitted successfully.';
        }
    }
}

// FIX: Load orders then attach items from order_items table
$orders = loadUserOrders($userEmail);

$orderPlacedFlash = '';
$orderPlacedId    = '';
if (!empty($_GET['order_placed']) && !empty($_GET['order_id'])) {
    $orderPlacedFlash = 'Your order has been placed successfully!';
    $orderPlacedId    = htmlspecialchars($_GET['order_id']);
}

$cartItems = loadCartForUser($userEmail);
$cartCount = 0;
foreach ($cartItems as $ci) $cartCount += (int)($ci['qty'] ?? 1);

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
<title>ZYTHERA | My Orders</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
<style>
  :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Merriweather',serif}
  body{font-family:var(--ui-font);}
  h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
  p,small,.caption,.text-muted{font-family:var(--text-font);}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dark-mode.css">
<script src="dark-mode.js" defer></script>
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

    /* ── Page header ── */
    .page-header { padding: 32px 0 8px; }
    .page-header h2 { font-family: 'Playfair Display', serif; color: var(--deep); margin: 0; }
    .section-label { font-size: .68rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--mid); margin-bottom: 4px; }

    /* ── Section card — white card wrapper (same as profile.php) ── */
    .section-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,.05);
    }

    /* ── Order box — same border card as profile.php ── */
    .order-box {
        border: 2px solid var(--sage);
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 14px;
        transition: .2s;
    }
    .order-box:hover { border-color: var(--mid); }

    /* ── Status pills — same as profile.php st-* ── */
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
    .timeline-wrap { display: flex; align-items: flex-start; justify-content: space-between; padding: 18px 0 8px; position: relative; }
    .timeline-wrap::before { content: ''; position: absolute; top: 28px; left: calc(12.5% - 1px); width: 75%; height: 3px; background: var(--sage); z-index: 0; }
    .tl-step { flex: 1; text-align: center; position: relative; z-index: 1; }
    .tl-dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: .82rem; border: 3px solid var(--sage); background: #fff; transition: .3s; }
    .tl-dot.done   { background: var(--green); border-color: var(--green); color: #fff; }
    .tl-dot.active { background: var(--green); border-color: var(--green); color: #fff; box-shadow: 0 0 0 5px rgba(45,90,45,.18); animation: pulse 1.8s infinite; }
    .tl-dot.future { background: #fff; border-color: var(--sage); color: #ccc; }
    .tl-line-done { position: absolute; top: 14px; left: 50%; width: 100%; height: 3px; background: var(--green); z-index: 0; }
    .tl-label { font-size: .72rem; font-weight: 600; color: var(--deep); }
    .tl-label.future { color: #bbb; }
    @keyframes pulse { 0%,100%{box-shadow:0 0 0 4px rgba(45,90,45,.18);}50%{box-shadow:0 0 0 8px rgba(45,90,45,.08);} }

    /* ── Cancelled bar ── */
    .cancelled-bar { display: flex; align-items: center; gap: 10px; background: #fef2f2; border-radius: 14px; padding: 14px 18px; color: #b91c1c; font-weight: 600; font-size: .88rem; border: 1px solid #fecaca; margin-bottom: 8px; }

    /* ── Totals — same as profile.php totals-row ── */
    .totals-box { background: var(--cream); border-radius: 14px; padding: 14px 18px; margin-top: 4px; }
    .totals-row { display: flex; justify-content: space-between; font-size: .85rem; color: #777; padding: 3px 0; }
    .totals-row.grand { font-size: 1rem; font-weight: 800; color: var(--green); border-top: 2px solid var(--sage); padding-top: 10px; margin-top: 6px; }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 60px 20px; color: #bbb; }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; display: block; }

    footer { background: #f5f2ec; padding: 22px 20px; display: flex; align-items: center; justify-content: center; gap: 12px; border-top: 1px solid #e8e4dc; margin-top: auto; }
    footer .footer-brand { font-family: 'Playfair Display', serif; color: var(--green); font-size: 1rem; letter-spacing: 4px; }
</style>
<link rel="stylesheet" href="responsive.css">
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;">

<nav class="navbar navbar-light px-4 py-2 fixed-top">
  <a class="navbar-brand fw-bold" href="website.php"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Shop</a>
    <a href="profile.php" class="btn btn-sm btn-light rounded-pill">My Profile</a>
    <a href="javascript:void(0)" onclick="openLogoutModal()" class="btn btn-sm btn-danger rounded-pill">Logout</a>
  </div>
</nav>
<div style="height:60px;"></div>

<div class="flex-fill">
<div class="container py-4" style="max-width:780px;">

  <div class="page-header">
    <div class="section-label"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> FURNITURE</div>
    <h2>My Orders
      <span class="badge rounded-pill ms-2"
            style="background:var(--mid);color:#fff;font-size:.75rem;font-weight:600;vertical-align:middle;padding:5px 12px;">
        <?= count($orders) ?>
      </span>
    </h2>
    <p class="text-muted mt-1" style="font-size:.85rem;">Track and view all your past orders.</p>
  </div>

  <?php if ($orderPlacedFlash): ?>
  <div id="orderFlash" style="background:linear-gradient(135deg,#dcfce7,#bbf7d0);border:2px solid #86efac;border-radius:18px;padding:18px 24px;margin-bottom:24px;display:flex;align-items:center;gap:14px;box-shadow:0 4px 20px rgba(21,128,61,.12);">
    <div style="width:48px;height:48px;background:#15803d;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fas fa-check" style="color:#fff;font-size:1.2rem;"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:700;color:#14532d;font-size:1rem;">Order Placed!</div>
      <div style="color:#15803d;font-size:.85rem;margin-top:2px;"><?= $orderPlacedFlash ?><?= $orderPlacedId ? ' — Order <strong>#' . $orderPlacedId . '</strong>' : '' ?></div>
      <div style="color:#16a34a;font-size:.78rem;margin-top:4px;">You can track your delivery status below. We'll update it as your order progresses.</div>
    </div>
    <button onclick="document.getElementById('orderFlash').style.display='none'" style="background:none;border:none;color:#15803d;font-size:1.2rem;cursor:pointer;padding:4px;">✕</button>
  </div>
  <?php endif; ?>

  <?php if (!empty($reviewSuccess)): ?>
  <div class="alert-success-order" style="
    background:linear-gradient(135deg,#dcfce7,#bbf7d0);
    border:2px solid #86efac;border-radius:18px;
    padding:18px 24px;margin-bottom:24px;
    color:#14532d;">
    <?= htmlspecialchars($reviewSuccess) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($reviewErrors)): ?>
  <div class="alert-errors" style="margin-bottom:24px;">
    <?php foreach ($reviewErrors as $error): ?>
      <?= htmlspecialchars($error) ?><br>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>
  <div class="empty-state">
    <i class="fas fa-box-open"></i>
    <p class="fw-semibold" style="color:#aaa;">No orders placed yet.</p>
    <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill mt-2 px-4">Start Shopping</a>
  </div>
  <?php else: ?>

  <?php foreach ($orders as $o):
    $oStatus     = $o->status ?? 'Pending';
    $isCancelled = strtolower($oStatus) === 'cancelled';
    $stepIndex   = $isCancelled ? -1 : getStepIndex($oStatus);

    $oItems   = $o->items ?? [];
    $subtotal = (float)($o->subtotal ?? 0);
    $shipping = (float)($o->shipping ?? 150);
    $total    = (float)($o->total ?? ($subtotal + $shipping));
    $oDate    = $o->date ?? '';
    $orderId  = $o->order_id ?? '—';
    $payMethod = $o->pay_method ?? '';
    $payStatus = $o->pay_status ?? 'pending';
    $payRef    = $o->pay_reference ?? '';
    $fullName  = $o->full_name ?? '';
    $address   = $o->address   ?? '';
    $city      = $o->city      ?? '';
    $province  = $o->province  ?? '';
    $zip       = $o->zip       ?? '';
    $phone     = $o->phone     ?? '';
    $notes     = $o->notes     ?? '';

    $stClass = match(strtolower($oStatus)) {
      'processing'             => 'st-processing',
      'shipped'                => 'st-shipped',
      'delivered','completed'  => 'st-delivered',
      'cancelled'              => 'st-cancelled',
      default                  => 'st-pending',
    };
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
        </div>
        <div class="col-6 col-md-3 text-md-end">
          <small class="text-muted d-block" style="font-size:.7rem;">Status</small>
          <span class="order-status <?= $stClass ?> dyn-status-badge"><?= htmlspecialchars($oStatus) ?></span>
        </div>
      </div>
      <div class="d-flex justify-content-end mb-3">
        <a href="order.php?order_id=<?= urlencode($orderId) ?>"
           class="btn btn-sm btn-outline-success rounded-pill" style="font-size:.75rem;">
          <i class="fas fa-eye me-1"></i>View Order Details
        </a>
      </div>

      <!-- Order Status Timeline -->
      <?php if ($isCancelled): ?>
      <div class="cancelled-bar"><i class="fas fa-times-circle fa-lg"></i>This order was cancelled.</div>
      <?php else: ?>
      <div class="section-label mb-1">Order Status</div>
      <div class="timeline-wrap">
        <?php foreach ($statusSteps as $idx => $step):
          $isDone   = $idx < $stepIndex;
          $isActive = $idx === $stepIndex;
          $isFuture = $idx > $stepIndex;
          $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'future');
          $icons    = ['fas fa-clock','fas fa-cog','fas fa-truck','fas fa-check-circle'];
        ?>
        <div class="tl-step">
          <?php if ($isDone && $idx > 0): ?><div class="tl-line-done"></div><?php endif; ?>
          <div class="tl-dot <?= $dotClass ?>"><i class="<?= $icons[$idx] ?>"></i></div>
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
        ?>
        <div class="d-flex align-items-center gap-2 px-3 py-2" style="border-bottom:1px solid var(--sage);">
          <div style="flex:1;min-width:0;">
            <div style="font-size:.88rem;font-weight:700;color:var(--deep);"><?= htmlspecialchars($oiName) ?></div>
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
        <div class="totals-row grand"><span>Total Paid</span><span>₱<?= number_format($total, 2) ?></span></div>
      </div>

      <?php if (in_array(strtolower($oStatus), ['delivered', 'completed'], true)): ?>
        <?php if (empty($o->review)): ?>
        <div class="checkout-card" style="margin-top:22px;padding:18px;">
          <div class="section-label mb-2">Leave a Review</div>
          <p style="color:#555;font-size:.9rem;margin-bottom:14px;">Tell other buyers about your experience with this order.</p>
          <form method="POST" style="display:grid;gap:12px;">
            <input type="hidden" name="review_order_id" value="<?= htmlspecialchars($orderId) ?>">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <?php for ($star = 1; $star <= 5; $star++): ?>
                <label style="cursor:pointer;font-size:1.25rem;color:#f5c842;">
                  <input type="radio" name="rating" value="<?= $star ?>" style="display:none;">
                  <?= $star <= 5 ? '★' : '☆' ?>
                </label>
              <?php endfor; ?>
            </div>
            <textarea name="comment" rows="4" style="width:100%;border:2px solid #d4e4d4;border-radius:14px;padding:14px;font-family: var(--ui-font);resize:none;" placeholder="Share your thoughts about the furniture delivery and quality..."></textarea>
            <button type="submit" class="btn-place" style="width:auto;padding:12px 18px;">Submit Review</button>
          </form>
        </div>
        <?php else: ?>
        <div class="checkout-card" style="margin-top:22px;padding:18px;">
          <div class="section-label mb-2">Your Review</div>
          <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div>
              <?php $revAvatar = getAvatarURL($o->review->author_pic ?? null, $o->review->author_email ?? null, $o->review->author_name ?? null, 70); ?>
              <img src="<?= htmlspecialchars($revAvatar) ?>" style="width:70px;height:70px;border-radius:18px;object-fit:cover;" alt="<?= htmlspecialchars($o->review->author_name ?: 'Reviewer') ?>">
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;color:var(--deep);margin-bottom:4px;">Thank you for sharing your experience.</div>
              <div style="font-size:1.25rem;color:#f5c842;margin-bottom:10px;"><?= str_repeat('★', max(1, min(5, (int)$o->review->rating))) ?></div>
              <p style="margin:0;color:#555;line-height:1.6;"><?= nl2br(htmlspecialchars($o->review->comment)) ?></p>
              <div style="margin-top:10px;font-size:.82rem;color:#777;">Submitted on <?= htmlspecialchars(date('F d, Y', strtotime($o->review->created_at))) ?></div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Customer Details — same layout as profile.php and order.php -->
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
  </div><!-- /section-card -->

  <?php endforeach; ?>
  <?php endif; ?>

</div>
</div>

<footer>
  <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
  <span class="footer-brand"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function pollOrderStatuses() {
  fetch('get_order.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.orders) return;
      data.orders.forEach(o => {
        const badge = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-badge');
        if (badge) {
          badge.textContent = o.status;
          badge.className   = 'order-status dyn-status-badge ' + statusClass(o.status);
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
    'Processing': 'We re preparing your furniture for shipment.',
    'Shipped':    'Your order is on its way! Estimated arrival in 3–7 business days.',
    'Delivered':  'Your order has been delivered. Enjoy your new furniture!',
    'Completed':  'Order completed. Thank you for shopping with us!',
    'Cancelled':  'This order was cancelled.',
  };
  return m[s] || 'Your order is being processed.';
}
setInterval(pollOrderStatuses, 30000);
</script>
</body>
</html>
