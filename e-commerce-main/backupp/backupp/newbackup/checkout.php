<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
if (!isset($_SESSION['users'][$userEmail])) {
    // Clear only login keys — do NOT session_destroy() so cart/orders persisted
    // to file are still reloaded from carts.json on next login.
    $keysToRemove = ['logged_in_user', 'role', 'login_time', 'session_start'];
    foreach ($keysToRemove as $_k) unset($_SESSION[$_k]);
    header('Location: logsign.php');
    exit;
}

$user   = &$_SESSION['users'][$userEmail];
$cart   = $_SESSION['cart'][$userEmail] ?? [];

if (empty($cart)) {
    header('Location: website.php');
    exit;
}

// ── Handle checkout form submission ───────────────────────────
$errors      = [];

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
    if (!$address)   $errors[] = 'Delivery address is required.';
    if (!$city)      $errors[] = 'City is required.';
    if (!$province)  $errors[] = 'Province is required.';
    if (!$zip)       $errors[] = 'ZIP code is required.';
    if (!$payMethod) $errors[] = 'Please select a payment method.';

    if (empty($errors)) {
        // Compute totals
        $subtotalAmt = 0;
        foreach ($cart as $ci) {
            $subtotalAmt += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? 1);
        }
        $shippingFee = $subtotalAmt > 0 ? 150 : 0;
        $totalAmt    = $subtotalAmt + $shippingFee;

        // Deduct stock from inventory
        $invList = loadInventory();
        $invMap  = [];
        foreach ($invList as $inv) {
            $obj = is_object($inv) ? $inv : (object)$inv;
            $invMap[(int)$obj->id] = $obj;
        }
        foreach ($cart as $ci) {
            $pid = (int)($ci['id'] ?? 0);
            $qty = (int)($ci['qty'] ?? 1);
            if (isset($invMap[$pid])) {
                $invMap[$pid]->stock = max(0, (int)$invMap[$pid]->stock - $qty);
            }
        }
        saveInventory($invMap);

        // Save order
        if (!isset($_SESSION['orders'][$userEmail])) $_SESSION['orders'][$userEmail] = [];
        $newOrder = [
            'order_id'        => 'ZPH-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'items'           => $cart,
            'subtotal'        => $subtotalAmt,
            'shipping'        => $shippingFee,
            'total'           => $totalAmt,
            'date'            => date('Y-m-d H:i:s'),
            'status'          => 'Pending',
            'shipping_address'=> htmlspecialchars($address . ', ' . $city . ', ' . $province . ' ' . $zip),
            'pay_method'      => htmlspecialchars($payMethod),
            'shipping_info'   => [
                'full_name' => htmlspecialchars($fullName),
                'phone'     => htmlspecialchars($phone),
                'address'   => htmlspecialchars($address),
                'city'      => htmlspecialchars($city),
                'province'  => htmlspecialchars($province),
                'zip'       => htmlspecialchars($zip),
                'notes'     => htmlspecialchars($notes),
            ],
        ];

        // Load current orders from file, add new order, save back to file
        $allOrders = loadOrders();
        if (!isset($allOrders[$userEmail])) $allOrders[$userEmail] = [];
        $allOrders[$userEmail][] = $newOrder;
        saveOrders($allOrders); // persists to JSON + syncs $_SESSION['orders']

        // Clear only the items that were just ordered — not the entire cart
        $orderedIds = array_column($newOrder['items'], 'id');
        $orderedIds = array_map('intval', $orderedIds);
        $_SESSION['cart'][$userEmail] = array_values(
            array_filter($_SESSION['cart'][$userEmail], function($ci) use ($orderedIds) {
                return !in_array((int)($ci['id'] ?? 0), $orderedIds);
            })
        );
        $allCarts = loadCarts();
        $allCarts[$userEmail] = $_SESSION['cart'][$userEmail];
        saveCarts($allCarts);

        // Store flash data in session so profile.php can show the confirmation
        $_SESSION['order_flash'] = [
            'order_id'  => $newOrder['order_id'],
            'total'     => $newOrder['total'],
            'items'     => $newOrder['items'],
            'subtotal'  => $newOrder['subtotal'],
            'shipping'  => $newOrder['shipping'],
            'pay_method'=> $newOrder['pay_method'],
            'date'      => $newOrder['date'],
            'shipping_info' => $newOrder['shipping_info'],
        ];

        // Redirect straight to profile order history
        header('Location: profile.php?order_placed=1');
        exit;
    } // end if (empty($errors))
} // end if POST

// ── Compute subtotals ─────────────────────────────────────────
$subtotal = 0;
foreach ($cart as $ci) {
    $subtotal += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? 1);
}
$shipping = $subtotal > 0 ? 150 : 0;
$total    = $subtotal + $shipping;

$userName = $user['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZAFIRAH | Checkout</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#2d5a2d;--sage:#d4e4d4;--cream:#f5f2ec;--deep:#1a2e1a;--terra:#bc8a7b;}
*{font-family:'DM Sans',sans-serif;box-sizing:border-box;}
body{background:var(--cream);min-height:100vh;padding-top:70px;}

/* Navbar */
.navbar{background:#fff!important;box-shadow:0 1px 12px rgba(0,0,0,.07);}
.navbar-brand{font-family:'Playfair Display',serif;color:var(--green)!important;font-size:1.55rem;letter-spacing:2px;}

/* Section label */
.step-label{
  font-size:.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
  color:var(--green);margin-bottom:6px;
}

/* Cards */
.checkout-card{
  background:#fff;border-radius:20px;
  box-shadow:0 4px 20px rgba(0,0,0,.07);
  padding:28px;margin-bottom:20px;
}
.checkout-card h5{
  font-family:'Playfair Display',serif;
  color:var(--deep);font-size:1.15rem;margin-bottom:20px;
}

/* Floating label inputs */
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

/* Payment options */
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

/* Order summary */
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

/* Place order button */
.btn-place{
  width:100%;padding:15px;border:none;
  background:var(--green);color:#fff;
  border-radius:50px;font-weight:700;font-size:1rem;
  cursor:pointer;transition:.2s;letter-spacing:.5px;
}
.btn-place:hover{background:var(--deep);}
.btn-place:disabled{opacity:.6;cursor:not-allowed;}

/* Error alert */
.alert-errors{
  background:#fee2e2;border:1px solid #fca5a5;
  border-radius:14px;padding:14px 18px;margin-bottom:20px;
  color:#b91c1c;font-size:.85rem;
}

/* Success overlay */
.success-wrap{
  text-align:center;padding:60px 30px;
}
.success-icon{
  width:80px;height:80px;border-radius:50%;
  background:linear-gradient(135deg,var(--green),#4a7c4a);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;font-size:2rem;color:#fff;
}
.order-id-badge{
  display:inline-block;background:var(--sage);
  color:var(--green);font-weight:700;font-size:.9rem;
  padding:8px 20px;border-radius:50px;letter-spacing:1px;
  margin-bottom:20px;
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
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="website.php">ZAFIRAH</a>
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

  <!-- Page title -->
  <div class="mb-4">
    <p class="step-label">ZAFIRAH FURNITURE</p>
    <h2 style="font-family:'Playfair Display',serif;color:var(--deep);margin:0;">Checkout</h2>
  </div>


  <!-- ── CHECKOUT FORM ── -->

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

    <!-- LEFT: Form -->
    <div class="col-lg-7">

      <!-- Delivery Details -->
      <div class="checkout-card">
        <h5><i class="fas fa-map-marker-alt me-2" style="color:var(--terra);"></i>Delivery Details</h5>

        <div class="field">
          <input type="text" name="full_name"
            value="<?= htmlspecialchars($userName) ?>" placeholder=" " required>
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
        <h5><i class="fas fa-credit-card me-2" style="color:var(--terra);"></i>Payment Method</h5>

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
      <div class="checkout-card" style="position:sticky;top:82px;">
        <h5><i class="fas fa-shopping-bag me-2" style="color:var(--terra);"></i>Order Summary</h5>

        <!-- Cart Items -->
        <div style="max-height:320px;overflow-y:auto;margin-bottom:16px;">
          <?php foreach ($cart as $ci):
            $ciPrice = (float)($ci['price'] ?? 0);
            $ciQty   = (int)($ci['qty']   ?? 1);
            $ciTotal = $ciPrice * $ciQty;
          ?>
            <div class="order-item">
              <img src="<?= htmlspecialchars($ci['image'] ?? '') ?>" alt=""
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:.85rem;color:var(--deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
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

        <!-- Totals -->
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

        <!-- Place Order -->
        <button type="submit" name="place_order" class="btn-place mt-4">
          <i class="fas fa-lock me-2"></i>Place Order
        </button>

        <p style="text-align:center;font-size:.72rem;color:#bbb;margin-top:12px;">
          <i class="fas fa-shield-alt me-1"></i>Your information is secure and encrypted
        </p>
      </div>
    </div>

  </div>
  </form>

</div><!-- /container -->

<footer>
  <img src="pci/Group_15.svg" style="width:28px;" alt="Zafirah logo">
  <span class="footer-brand">ZAFIRAH</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Highlight selected payment option
document.querySelectorAll('.pay-option input[type=radio]').forEach(radio => {
  // Init on load
  if (radio.checked) radio.closest('.pay-option').classList.add('selected');

  radio.addEventListener('change', () => {
    document.querySelectorAll('.pay-option').forEach(o => o.classList.remove('selected'));
    if (radio.checked) radio.closest('.pay-option').classList.add('selected');
  });
});

// Disable button on submit to prevent double-click
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