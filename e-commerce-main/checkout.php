<?php
require 'config.php';

// ── Auth guard ────────────────────────────────────────────────
if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$userRole  = $_SESSION['role'] ?? 'user';

// Admins cannot checkout
if ($userRole === 'admin') {
    header('Location: admin.php');
    exit;
}

// ── Load user from DB ─────────────────────────────────────────
$db    = getDBConnection();
$uStmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$uStmt->execute([$userEmail]);
$dbUser = $uStmt->fetch();

if (!$dbUser) {
    foreach (['logged_in_user','role','login_time','session_start'] as $_k) unset($_SESSION[$_k]);
    header('Location: logsign.php');
    exit;
}

$userName = $dbUser->name ?? '';

// ── Load cart from DB ─────────────────────────────────────────
$cart = loadCartForUser($userEmail);
$_SESSION['cart'][$userEmail] = $cart;

if (empty($cart)) {
    header('Location: website.php');
    exit;
}

// ── Compute totals ────────────────────────────────────────────
$subtotal = 0;
foreach ($cart as $ci) {
    $subtotal += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? 1);
}
$shipping = $subtotal > 0 ? 150 : 0;
$total    = $subtotal + $shipping;

// ── Handle checkout form submission ───────────────────────────
$errors      = [];
$orderPlaced = false;
$placedOrderInfo = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fullName    = trim($_POST['full_name']    ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $city        = trim($_POST['city']         ?? '');
    $province    = trim($_POST['province']     ?? '');
    $zip         = trim($_POST['zip']          ?? '');
    $payMethod   = trim($_POST['pay_method']   ?? '');
    $notes       = trim($_POST['notes']        ?? '');

    if (!$fullName)  $errors[] = 'Full name is required.';
    if (!$phone)     $errors[] = 'Phone number is required.';
    if (!$address)   $errors[] = 'Complete address is required.';
    if (!$city)      $errors[] = 'City is required.';
    if (!$province)  $errors[] = 'Province is required.';
    if (!$zip)       $errors[] = 'ZIP Code is required.';
    if (!$payMethod) $errors[] = 'Please select a payment method.';

    if (empty($errors)) {
        try {
            // Generate unique order ID
            $orderId = 'ORD-' . strtoupper(substr(md5(uniqid($userEmail, true)), 0, 8));

            $orderData = [
                'order_id'      => $orderId,
                'subtotal'      => $subtotal,
                'shipping'      => $shipping,
                'total'         => $total,
                'status'        => 'Pending',
                'pay_method'    => $payMethod,
                'shipping_info' => [
                    'full_name' => $fullName,
                    'phone'     => $phone,
                    'address'   => $address,
                    'city'      => $city,
                    'province'  => $province,
                    'zip'       => $zip,
                    'notes'     => $notes,
                ],
                'items'         => $cart,
            ];

            $db->beginTransaction();

            // 1. Insert order with correct schema columns
            $oStmt = $db->prepare("
                INSERT INTO orders
                (order_id, email, subtotal, shipping, total, date, status, pay_method,
                 full_name, phone, address, city, province, zip, notes)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $oStmt->execute([
                $orderId, $userEmail, $subtotal, $shipping, $total,
                'Pending', $payMethod,
                $fullName, $phone, $address, $city, $province, $zip, $notes
            ]);
            $dbOrdNo = $db->lastInsertId();

            // 2. Insert order items with correct schema columns
            $oiStmt = $db->prepare("
                INSERT INTO order_items (ord_no, inv_id, product_name, price, qty)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($cart as $ci) {
                $oiStmt->execute([
                    $dbOrdNo,
                    (int)($ci['inv_id'] ?? 0),
                    trim($ci['name']   ?? ''),
                    (float)($ci['price'] ?? 0),
                    (int)($ci['qty']   ?? 1),
                ]);
            }

            // 3. Deduct inventory safely
            $deductStmt = $db->prepare("
                UPDATE inventory SET stock = stock - ?
                WHERE inv_id = ? AND stock >= ?
            ");
            foreach ($cart as $ci) {
                $qty = (int)($ci['qty'] ?? 1);
                $pid = (int)($ci['inv_id'] ?? 0);
                $deductStmt->execute([$qty, $pid, $qty]);
                if ($deductStmt->rowCount() === 0) {
                    throw new Exception('Insufficient stock for product ID ' . $pid);
                }
            }

            // 4. Clear cart
            $db->prepare("DELETE FROM carts WHERE email = ?")->execute([$userEmail]);

            $db->commit();

            // Sync session
            $_SESSION['cart'][$userEmail] = [];

            // Store order flash for profile page modal
            $_SESSION['order_flash'] = [
                'order_id'    => $orderId,
                'pay_method'  => $payMethod,
                'subtotal'    => $subtotal,
                'shipping'    => $shipping,
                'total'       => $total,
                'date'        => date('Y-m-d H:i:s'),
                'items'       => $cart,
                'shipping_info' => $orderData['shipping_info'],
            ];

            header('Location: orders.php?order_placed=1&order_id=' . urlencode($orderId));
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = "Checkout processing failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZYTHERA | Checkout</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#2d5a2d;--sage:#d4e4d4;--cream:#f5f2ec;--deep:#1a2e1a;--terra:#bc8a7b;}
*{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
body{background:var(--cream);min-height:100vh;padding-top:70px;}

.navbar{background:#fff!important;box-shadow:0 1px 12px rgba(0,0,0,.07);}
.navbar-brand{font-family:'Playfair Display',serif;color:var(--green)!important;font-size:1.55rem;letter-spacing:2px;}

.step-label{
  font-size:.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
  color:var(--green);margin-bottom:6px;
}

.checkout-card{
  background:#fff;border-radius:20px;
  box-shadow:0 4px 20px rgba(0,0,0,.07);
  padding:28px;margin-bottom:20px;
}
.checkout-card h5{
  font-family:'Playfair Display',serif;
  color:var(--deep);font-size:1.15rem;margin-bottom:20px;
}

.field{position:relative;margin-bottom:18px;}
.field input,.field select,.field textarea{
  width:100%;padding:15px 14px 7px;
  background:var(--sage);border:2px solid transparent;
  border-radius:14px;outline:none;
  font-family:'DM Sans',sans-serif;font-size:.92rem;
  color:var(--deep);transition:.2s;appearance:none;
}
.field textarea{min-height:80px;resize:none;padding-top:20px;}
.field input:focus,.field select:focus,.field textarea:focus{
  border-color:var(--green);background:#fff;
}
.field label{
  position:absolute;left:14px;top:14px;
  font-size:.82rem;color:#999;pointer-events:none;transition:.2s;
}
.field input:focus~label,
.field input:not(:placeholder-shown)~label,
.field textarea:focus~label,
.field textarea:not(:placeholder-shown)~label{
  top:4px;font-size:.67rem;color:var(--green);font-weight:600;
}
.field select~label{top:4px;font-size:.67rem;color:var(--green);font-weight:600;}

.pay-option{
  display:flex;align-items:center;gap:12px;
  padding:14px 16px;border:2px solid var(--sage);
  border-radius:14px;cursor:pointer;transition:.2s;margin-bottom:10px;
}
.pay-option:hover{border-color:var(--green);background:#f8fdf8;}
.pay-option input[type=radio]{accent-color:var(--green);width:16px;height:16px;flex-shrink:0;}
.pay-option.selected{border-color:var(--green);background:#f0f7f0;}
.pay-icon{width:36px;height:36px;border-radius:10px;background:var(--sage);
  display:flex;align-items:center;justify-content:center;color:var(--green);font-size:1rem;}

.order-item{
  display:flex;align-items:center;gap:12px;
  padding:10px 0;border-bottom:1px solid #f0f0eb;
}
.order-item:last-child{border-bottom:none;}
.order-item img{
  width:52px;height:52px;object-fit:cover;
  border-radius:10px;background:var(--sage);flex-shrink:0;
}
.order-total-row{
  display:flex;justify-content:space-between;
  font-size:.88rem;color:#777;padding:4px 0;
}
.order-total-row.grand{
  font-size:1.05rem;font-weight:800;
  color:var(--green);border-top:2px solid var(--sage);
  padding-top:12px;margin-top:6px;
}

.btn-place{
  width:100%;padding:15px;border:none;
  background:var(--green);color:#fff;
  border-radius:50px;font-weight:700;font-size:1rem;
  cursor:pointer;transition:.2s;letter-spacing:.5px;
}
.btn-place:hover{background:var(--deep);}
.btn-place:disabled{opacity:.6;cursor:not-allowed;}

.alert-errors{
  background:#fee2e2;border:1px solid #fca5a5;
  border-radius:14px;padding:14px 18px;margin-bottom:20px;
  color:#b91c1c;font-size:.85rem;
}

footer{
  display:flex;align-items:center;justify-content:center;
  gap:12px;padding:24px;margin-top:40px;
  border-top:1px solid #e8e4dc;
}
footer .footer-brand{
  font-family:'Playfair Display',serif;
  color:var(--green);font-size:1rem;letter-spacing:3px;
}

/* Order summary scroll area — thin green scrollbar */
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar{width:5px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-track{background:var(--sage);border-radius:4px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-thumb{background:var(--green);border-radius:4px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-thumb:hover{background:var(--deep);}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="website.php">ZYTHERA</a>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Keep Shopping
      </a>
      <a href="profile.php" class="btn btn-sm btn-light rounded-pill px-3">My Profile</a>
      <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4" style="max-width:980px;">

  <div class="mb-4">
    <p class="step-label">ZYTHERA FURNITURE</p>
    <h2 style="font-family:'Playfair Display',serif;color:var(--deep);margin:0;">Checkout</h2>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert-errors">
      <i class="fas fa-exclamation-circle me-2"></i>
      <?php foreach ($errors as $e): ?>
        <?= htmlspecialchars($e) ?><br>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="checkoutForm">
  <div class="row g-4">

    <!-- LEFT: Delivery + Payment -->
    <div class="col-lg-7">

      <!-- Delivery Details -->
      <div class="checkout-card">
        <h5><i class="fas fa-map-marker-alt me-2" style="color:var(--green);"></i>Delivery Details</h5>

        <div class="field">
          <input type="text" name="full_name"
            value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>"
            placeholder=" " required>
          <label>Full Name *</label>
        </div>

        <div class="field">
          <input type="tel" name="phone" placeholder=" " required
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          <label>Phone Number *</label>
        </div>

        <div class="field">
          <input type="text" name="address" placeholder=" " required
            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
          <label>House / Unit / Street Address *</label>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <div class="field">
              <input type="text" name="city" placeholder=" " required
                value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
              <label>City / Municipality *</label>
            </div>
          </div>
          <div class="col-6">
            <div class="field">
              <input type="text" name="province" placeholder=" " required
                value="<?= htmlspecialchars($_POST['province'] ?? '') ?>">
              <label>Province *</label>
            </div>
          </div>
        </div>

        <div class="row g-2">
          <div class="col-6">
            <div class="field">
              <input type="text" name="zip" placeholder=" " required maxlength="4"
                value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
              <label>ZIP Code *</label>
            </div>
          </div>
          <div class="col-6">
            <div class="field">
              <input type="text" value="Philippines" readonly placeholder=" ">
              <label>Country</label>
            </div>
          </div>
        </div>

        <div class="field">
          <textarea name="notes" placeholder=" "><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          <label>Delivery Notes (optional)</label>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="checkout-card">
        <h5><i class="fas fa-credit-card me-2" style="color:var(--green);"></i>Payment Method</h5>

        <label class="pay-option" id="pay-cod">
          <input type="radio" name="pay_method" value="Cash on Delivery (COD)"
            <?= ($_POST['pay_method'] ?? '') === 'Cash on Delivery (COD)' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-hand-holding-usd"></i></div>
          <div>
            <div style="font-weight:600;font-size:.9rem;">Cash on Delivery</div>
            <div style="font-size:.75rem;color:#999;">Pay when your furniture arrives</div>
          </div>
        </label>

        <label class="pay-option" id="pay-gcash">
          <input type="radio" name="pay_method" value="GCash"
            <?= ($_POST['pay_method'] ?? '') === 'GCash' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-mobile-alt"></i></div>
          <div>
            <div style="font-weight:600;font-size:.9rem;">GCash</div>
            <div style="font-size:.75rem;color:#999;">Pay via GCash e-wallet</div>
          </div>
        </label>

        <label class="pay-option" id="pay-bank">
          <input type="radio" name="pay_method" value="Bank Transfer"
            <?= ($_POST['pay_method'] ?? '') === 'Bank Transfer' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-university"></i></div>
          <div>
            <div style="font-weight:600;font-size:.9rem;">Bank Transfer</div>
            <div style="font-size:.75rem;color:#999;">BDO, BPI, Metrobank, UnionBank</div>
          </div>
        </label>

        <label class="pay-option" id="pay-maya">
          <input type="radio" name="pay_method" value="Maya"
            <?= ($_POST['pay_method'] ?? '') === 'Maya' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-wallet"></i></div>
          <div>
            <div style="font-weight:600;font-size:.9rem;">Maya</div>
            <div style="font-size:.75rem;color:#999;">Pay via Maya (PayMaya) e-wallet</div>
          </div>
        </label>
      </div>

    </div><!-- /col-lg-7 -->

    <!-- RIGHT: Order Summary -->
    <div class="col-lg-5">
      <div class="checkout-card" style="position:sticky;top:82px;display:flex;flex-direction:column;max-height:calc(100vh - 100px);overflow:hidden;">
        <h5 style="flex-shrink:0;"><i class="fas fa-shopping-bag me-2" style="color:var(--green);"></i>Order Summary</h5>

        <div style="flex:1;min-height:0;overflow-y:auto;margin-bottom:16px;padding-right:4px;">
          <?php foreach ($cart as $ci):
            $ciPrice = (float)($ci['price'] ?? 0);
            $ciQty   = (int)($ci['qty']   ?? 1);
            $ciTotal = $ciPrice * $ciQty;
          ?>
            <div class="order-item">
              <img src="<?= htmlspecialchars($ci['image'] ?? '') ?>" alt=""
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:.85rem;color:var(--deep);
                  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= htmlspecialchars($ci['name'] ?? '') ?>
                </div>
                <div style="font-size:.76rem;color:#999;">
                  ₱<?= number_format($ciPrice) ?> × <?= $ciQty ?>
                </div>
              </div>
              <div style="font-weight:700;color:var(--green);font-size:.88rem;white-space:nowrap;">
                ₱<?= number_format($ciTotal) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="flex-shrink:0;">
        <div class="order-total-row">
          <span>Subtotal</span>
          <span>₱<?= number_format($subtotal) ?></span>
        </div>
        <div class="order-total-row">
          <span>Shipping Fee</span>
          <span>₱<?= number_format($shipping) ?></span>
        </div>
        <div class="order-total-row grand">
          <span>Total</span>
          <span>₱<?= number_format($total) ?></span>
        </div>

        <button type="submit" name="place_order" class="btn-place mt-4">
          <i class="fas fa-lock me-2"></i>Place Order
        </button>

        <p style="text-align:center;font-size:.72rem;color:#bbb;margin-top:12px;">
          <i class="fas fa-shield-alt me-1"></i>Your information is secure and encrypted
        </p>
        </div><!-- /flex-shrink:0 totals wrapper -->
      </div>
    </div>

  </div>
  </form>

</div>

<footer>
  <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
  <span class="footer-brand">ZYTHERA</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Highlight selected payment option
document.querySelectorAll('.pay-option input[type=radio]').forEach(radio => {
  if (radio.checked) radio.closest('.pay-option').classList.add('selected');
  radio.addEventListener('change', () => {
    document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('selected'));
    if (radio.checked) radio.closest('.pay-option').classList.add('selected');
  });
});

// Prevent double-submit
document.getElementById('checkoutForm')?.addEventListener('submit', function() {
  const btn = this.querySelector('.btn-place');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Placing Order...';
  }
});
</script>
</body>
</html>