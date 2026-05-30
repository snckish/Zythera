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

$orders = loadUserOrders($userEmail);

// Flash message after placing order
$orderPlacedFlash = '';
$orderPlacedId    = '';
if (!empty($_GET['order_placed']) && !empty($_GET['order_id'])) {
    $orderPlacedFlash = 'Your order has been placed successfully!';
    $orderPlacedId    = htmlspecialchars($_GET['order_id']);
}

// Cart count for navbar badge
$cartItems = loadCartForUser($userEmail);
$cartCount = 0;
foreach ($cartItems as $ci) $cartCount += (int)($ci['qty'] ?? 1);

// Order status steps
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#2d5a2d;--sage:#d4e4d4;--cream:#f5f2ec;--deep:#1a2e1a;--mid:#7aab7a;--terra:#bc8a7b;}
*{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
body{background:var(--cream);min-height:100vh;margin:0;}

/* Navbar */
.navbar{background:#fff;box-shadow:0 1px 12px rgba(0,0,0,.07);}
.navbar-brand{font-family:'Playfair Display',serif;color:var(--green)!important;letter-spacing:4px;font-size:1.5rem;}

/* Page */
.page-header{padding:32px 0 8px;}
.page-header h2{font-family:'Playfair Display',serif;color:var(--deep);margin:0;}
.section-label{font-size:.68rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--mid);margin-bottom:4px;}

/* Order card */
.order-card{
  background:#fff;border-radius:20px;
  box-shadow:0 4px 20px rgba(0,0,0,.07);
  margin-bottom:24px;overflow:hidden;
}
.order-card-header{
  padding:18px 24px;
  border-bottom:2px solid var(--sage);
  display:flex;align-items:center;flex-wrap:wrap;gap:12px;
}
.order-card-body{padding:22px 24px;}

/* Status badge */
.status-pill{
  display:inline-flex;align-items:center;gap:6px;
  padding:5px 14px;border-radius:50px;
  font-size:.72rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
}
.sp-pending    {background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.sp-processing {background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.sp-shipped    {background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;}
.sp-delivered,
.sp-completed  {background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.sp-cancelled  {background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}

/* ── Timeline ── */
.timeline-wrap{
  display:flex;align-items:flex-start;
  justify-content:space-between;
  padding:18px 0 8px;
  position:relative;
}
.timeline-wrap::before{
  content:'';
  position:absolute;
  top:28px;left:calc(12.5% - 1px);
  width:75%;height:3px;
  background:var(--sage);
  z-index:0;
}
.tl-step{
  flex:1;text-align:center;position:relative;z-index:1;
}
.tl-dot{
  width:32px;height:32px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 8px;font-size:.82rem;
  border:3px solid var(--sage);background:#fff;
  transition:.3s;
}
.tl-dot.done  {background:var(--green);border-color:var(--green);color:#fff;}
.tl-dot.active{background:var(--green);border-color:var(--green);color:#fff;
  box-shadow:0 0 0 5px rgba(45,90,45,.18);animation:pulse 1.8s infinite;}
.tl-dot.future{background:#fff;border-color:var(--sage);color:#ccc;}

.tl-line-done{
  position:absolute;
  top:14px;left:50%;width:100%;height:3px;
  background:var(--green);z-index:0;
}

.tl-label{font-size:.72rem;font-weight:600;color:var(--deep);}
.tl-label.future{color:#bbb;}
.tl-label.cancelled{color:#b91c1c;}

@keyframes pulse{
  0%,100%{box-shadow:0 0 0 4px rgba(45,90,45,.18);}
  50%{box-shadow:0 0 0 8px rgba(45,90,45,.08);}
}

/* Cancelled bar */
.cancelled-bar{
  display:flex;align-items:center;gap:10px;
  background:#fef2f2;border-radius:14px;padding:14px 18px;
  color:#b91c1c;font-weight:600;font-size:.88rem;
  border:1px solid #fecaca;margin-bottom:8px;
}

/* Items list */
.item-row{
  display:flex;align-items:center;gap:12px;
  padding:10px 0;border-bottom:1px solid #f0f0eb;
}
.item-row:last-child{border-bottom:none;}
.item-thumb{width:46px;height:46px;object-fit:cover;border-radius:10px;flex-shrink:0;background:var(--sage);}
.item-name{font-weight:600;font-size:.86rem;color:var(--deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.item-meta{font-size:.74rem;color:#999;}
.item-price{font-weight:700;color:var(--green);white-space:nowrap;font-size:.88rem;}

/* Totals */
.totals-mini{
  background:var(--cream);border-radius:12px;padding:14px 18px;margin-top:14px;
}
.tr-row{display:flex;justify-content:space-between;font-size:.82rem;color:#888;padding:2px 0;}
.tr-row.grand{font-size:.95rem;font-weight:800;color:var(--green);border-top:2px solid var(--sage);padding-top:10px;margin-top:6px;}

/* Delivery info */
.delivery-chip{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--cream);border-radius:50px;
  padding:5px 14px;font-size:.78rem;color:#666;
}

/* Empty state */
.empty-state{text-align:center;padding:60px 20px;color:#bbb;}
.empty-state i{font-size:3rem;margin-bottom:16px;display:block;}

footer{
  background:#f5f2ec;padding:22px 20px;
  display:flex;align-items:center;justify-content:center;gap:12px;
  border-top:1px solid #e8e4dc;margin-top:auto;
}
footer .footer-brand{font-family:'Playfair Display',serif;color:var(--green);font-size:1rem;letter-spacing:4px;}
</style>
</head>
<body style="display:flex;flex-direction:column;min-height:100vh;">

<nav class="navbar navbar-light px-4 py-2 fixed-top">
  <a class="navbar-brand fw-bold" href="website.php">ZYTHERA</a>
  <div class="ms-auto d-flex gap-2 align-items-center">
    <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Shop</a>
    <a href="profile.php" class="btn btn-sm btn-light rounded-pill">My Profile</a>
    <a href="logout.php" class="btn btn-sm btn-danger rounded-pill">Logout</a>
  </div>
</nav>
<div style="height:60px;"></div>

<div class="flex-fill">
<div class="container py-4" style="max-width:780px;">

  <div class="page-header">
    <div class="section-label">ZYTHERA FURNITURE</div>
    <h2>My Orders
      <span class="badge rounded-pill ms-2"
            style="background:var(--mid);color:#fff;font-size:.75rem;font-weight:600;vertical-align:middle;padding:5px 12px;">
        <?= count($orders) ?>
      </span>
    </h2>
    <p class="text-muted mt-1" style="font-size:.85rem;">Track and view all your past orders.</p>
  </div>

  <?php if ($orderPlacedFlash): ?>
  <div class="alert-success-order" id="orderFlash" style="
    background:linear-gradient(135deg,#dcfce7,#bbf7d0);
    border:2px solid #86efac;border-radius:18px;
    padding:18px 24px;margin-bottom:24px;
    display:flex;align-items:center;gap:14px;
    box-shadow:0 4px 20px rgba(21,128,61,.12);">
    <div style="width:48px;height:48px;background:#15803d;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fas fa-check" style="color:#fff;font-size:1.2rem;"></i>
    </div>
    <div style="flex:1;">
      <div style="font-weight:700;color:#14532d;font-size:1rem;">🎉 Order Placed!</div>
      <div style="color:#15803d;font-size:.85rem;margin-top:2px;">
        <?= $orderPlacedFlash ?><?= $orderPlacedId ? ' — Order <strong>#' . $orderPlacedId . '</strong>' : '' ?>
      </div>
      <div style="color:#16a34a;font-size:.78rem;margin-top:4px;">
        You can track your delivery status below. We'll update it as your order progresses.
      </div>
    </div>
    <button onclick="document.getElementById('orderFlash').style.display='none'"
      style="background:none;border:none;color:#15803d;font-size:1.2rem;cursor:pointer;padding:4px;">✕</button>
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
    $oStatus  = $o->status ?? 'Pending';
    $isCancelled = strtolower($oStatus) === 'cancelled';
    $stepIndex = $isCancelled ? -1 : getStepIndex($oStatus);

    $shippingInfo = [
      'full_name' => $o->full_name ?? '',
      'phone'     => $o->phone    ?? '',
      'address'   => $o->address  ?? '',
      'city'      => $o->city     ?? '',
      'province'  => $o->province ?? '',
      'zip'       => $o->zip      ?? '',
      'notes'     => $o->notes    ?? '',
    ];

    $oItems   = $o->items ?? [];
    $subtotal = (float)($o->subtotal ?? 0);
    $shipping = (float)($o->shipping ?? 150);
    $total    = (float)($o->total ?? ($subtotal + $shipping));
    $oDate    = $o->date ?? '';
    $orderId  = $o->order_id ?? '—';
    $payMethod= $o->pay_method ?? '';

    $stClass = match(strtolower($oStatus)) {
      'processing'                => 'sp-processing',
      'shipped'                   => 'sp-shipped',
      'delivered', 'completed'    => 'sp-delivered',
      'cancelled'                 => 'sp-cancelled',
      default                     => 'sp-pending',
    };
  ?>
  <div class="order-card" data-order-id="<?= htmlspecialchars($orderId) ?>">
    <!-- Header -->
    <div class="order-card-header">
      <div>
        <div style="font-weight:700;color:var(--deep);font-size:.9rem;">
          <i class="fas fa-tag me-1" style="color:var(--mid);"></i>
          #<?= htmlspecialchars($orderId) ?>
        </div>
        <?php if ($oDate): ?>
        <div style="font-size:.74rem;color:#999;margin-top:2px;">
          <i class="fas fa-clock me-1"></i>
          <?= date('F d, Y — h:i A', strtotime($oDate)) ?>
        </div>
        <?php endif; ?>
      </div>
      <span class="status-pill <?= $stClass ?> dyn-status-badge">
        <i class="fas fa-circle" style="font-size:.4rem;"></i>
        <?= htmlspecialchars($oStatus) ?>
      </span>
      <?php if ($payMethod): ?>
      <span class="delivery-chip ms-auto">
        <i class="fas fa-credit-card" style="color:var(--mid);"></i>
        <?= htmlspecialchars($payMethod) ?>
      </span>
      <?php endif; ?>
    </div>

    <!-- Body -->
    <div class="order-card-body">

      <!-- ── Cancelled bar ── -->
      <?php if ($isCancelled): ?>
      <div class="cancelled-bar">
        <i class="fas fa-times-circle fa-lg"></i>
        This order was cancelled.
      </div>
      <?php else: ?>

      <!-- ── Status Timeline ── -->
      <div class="section-label mb-1">Order Status</div>
      <div class="timeline-wrap">
        <?php foreach ($statusSteps as $idx => $step):
          $isDone   = $idx < $stepIndex;
          $isActive = $idx === $stepIndex;
          $isFuture = $idx > $stepIndex;
          $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'future');
          $icons    = ['fas fa-clock', 'fas fa-cog', 'fas fa-truck', 'fas fa-check-circle'];
        ?>
        <div class="tl-step">
          <?php if ($isDone && $idx > 0): ?>
          <div class="tl-line-done"></div>
          <?php endif; ?>
          <div class="tl-dot <?= $dotClass ?>">
            <i class="<?= $icons[$idx] ?>"></i>
          </div>
          <div class="tl-label <?= $isFuture ? 'future' : '' ?>">
            <?= $step ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Status message -->
      <div style="background:var(--cream);border-radius:12px;padding:10px 16px;margin-bottom:18px;font-size:.84rem;color:#555;">
        <?php $statusMsgs = [
          'Pending'    => '⏳ Your order has been received and is awaiting confirmation.',
          'Processing' => '🔄 We\'re preparing your furniture for shipment.',
          'Shipped'    => '🚚 Your order is on its way! Estimated arrival in 3–7 business days.',
          'Delivered'  => '✅ Your order has been delivered. Enjoy your new furniture!',
          'Completed'  => '✅ Order completed. Thank you for shopping with us!',
        ];
        echo $statusMsgs[$oStatus] ?? 'Your order is being processed.';
        ?>
      </div>
      <?php endif; // not cancelled ?>

      <!-- ── Items ── -->
      <div class="section-label mb-2">Items Ordered</div>
      <div style="border:2px solid var(--sage);border-radius:14px;padding:8px 16px;margin-bottom:16px;">
        <?php foreach ($oItems as $oi):
          $oiName  = $oi->product_name ?? '?';
          $oiQty   = (int)($oi->qty   ?? 1);
          $oiPrice = (float)($oi->price ?? 0);
          $oiLine  = $oiPrice * $oiQty;
        ?>
        <div class="item-row">
          <div style="flex:1;min-width:0;">
            <div class="item-name"><?= htmlspecialchars($oiName) ?></div>
            <div class="item-meta">₱<?= number_format($oiPrice, 2) ?> × <?= $oiQty ?></div>
          </div>
          <span class="item-price">₱<?= number_format($oiLine, 2) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- ── Totals ── -->
      <div class="totals-mini">
        <div class="tr-row"><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>
        <div class="tr-row"><span><i class="fas fa-truck me-1"></i>Shipping</span><span>₱<?= number_format($shipping, 2) ?></span></div>
        <div class="tr-row grand"><span>Total Paid</span><span>₱<?= number_format($total, 2) ?></span></div>
      </div>

      <!-- ── Delivery Info ── -->
      <?php
      $deliveryLine = implode(', ', array_filter([
        $shippingInfo['full_name'] ?? '',
        $shippingInfo['address']   ?? '',
        $shippingInfo['city']      ?? '',
        $shippingInfo['province']  ?? '',
      ]));
      if ($deliveryLine): ?>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <span class="delivery-chip">
          <i class="fas fa-map-marker-alt" style="color:var(--terra);"></i>
          <?= htmlspecialchars($deliveryLine) ?>
        </span>
        <?php if (!empty($shippingInfo['phone'])): ?>
        <span class="delivery-chip">
          <i class="fas fa-phone" style="color:var(--mid);"></i>
          <?= htmlspecialchars($shippingInfo['phone']) ?>
        </span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- /order-card-body -->
  </div><!-- /order-card -->
  <?php endforeach; ?>

  <?php endif; // has orders ?>
</div>
</div>

<footer>
  <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
  <span class="footer-brand">ZYTHERA</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Live order status polling ─────────────────────────────────
// Polls every 30s so status updates from admin appear without refresh
function pollOrderStatuses() {
  fetch('get_order.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.orders) return;
      data.orders.forEach(o => {
        // Update status badge
        const badge = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-badge');
        if (badge) {
          badge.textContent = o.status;
          badge.className   = 'status-pill dyn-status-badge ' + statusClass(o.status);
        }
        // Update status message
        const msg = document.querySelector('[data-order-id="' + o.order_id + '"] .dyn-status-msg');
        if (msg) msg.textContent = statusMsg(o.status);
      });
    }).catch(() => {});
}

function statusClass(s) {
  const m = { pending:'sp-pending', processing:'sp-processing', shipped:'sp-shipped',
              delivered:'sp-delivered', completed:'sp-delivered', cancelled:'sp-cancelled' };
  return m[s.toLowerCase()] || 'sp-pending';
}

function statusMsg(s) {
  const m = {
    'Pending':    '⏳ Your order has been received and is awaiting confirmation.',
    'Processing': '🔄 Were preparing your furniture for shipment.',
    'Shipped':    '🚚 Your order is on its way! Estimated arrival in 3–7 business days.',
    'Delivered':  '✅ Your order has been delivered. Enjoy your new furniture!',
    'Completed':  '✅ Order completed. Thank you for shopping with us!',
    'Cancelled':  '❌ This order was cancelled.',
  };
  return m[s] || 'Your order is being processed.';
}

// Poll every 30 seconds
setInterval(pollOrderStatuses, 30000);
</script>
</body>
</html>