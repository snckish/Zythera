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

// ── Allowed locations (used for validation and datalists) ──────
$provinces = [
  'Metro Manila','Cavite','Laguna','Batangas','Bulacan','Pampanga','Rizal',
  'Quezon','Nueva Ecija','Cebu','Davao del Sur','Iloilo','Bohol','Pangasinan'
];

$cities = [
  'Manila','Quezon City','Makati','Pasig','Taguig','Parañaque','Caloocan','Las Piñas',
  'Cavite City','Bacoor','Imus','Santa Rosa','San Pedro','Biñan','Calamba','Batangas City',
  'Malolos','San Fernando','Angeles','Antipolo','Lucena','Tuguegarao','Cebu City','Mandaue',
  'Davao City','Iloilo City','Bacolod','Tagbilaran','Dagupan'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName    = trim($_POST['full_name']    ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $country     = trim($_POST['country']      ?? 'Philippines');
    $province    = trim($_POST['province']     ?? '');
    $city        = trim($_POST['city']         ?? '');
    $zip         = trim($_POST['zip']          ?? '');
    $payMethod   = trim($_POST['pay_method']   ?? '');
    $notes       = trim($_POST['notes']        ?? '');

    if (!$fullName)  $errors[] = 'Full name is required.';
    if (!$phone)     $errors[] = 'Phone number is required.';
    if (!$address)   $errors[] = 'Complete address is required.';
    if (!$province)  $errors[] = 'Province is required.';
    if (!$city)      $errors[] = 'City is required.';
    if (!$zip)       $errors[] = 'ZIP Code is required.';
    if (!$payMethod) $errors[] = 'Please select a payment method.';

    if ($province && !in_array($province, $provinces, true))
      $errors[] = 'Please select a valid province from the list.';
    if ($city && !in_array($city, $cities, true))
      $errors[] = 'Please select a valid city from the list.';
    if ($phone && !preg_match('/^[0-9]{10,11}$/', $phone))
      $errors[] = 'Phone number must be 10 or 11 digits (numbers only).';
    if ($zip && !preg_match('/^[0-9]{4}$/', $zip))
      $errors[] = 'ZIP Code must be 4 digits.';
    if ($fullName && !preg_match('/^[\p{L} .\'-]{2,100}$/u', $fullName))
      $errors[] = 'Full name appears invalid.';

    $allowedPay = ['Cash on Delivery (COD)','GCash','Bank Transfer','Maya'];
    if ($payMethod && !in_array($payMethod, $allowedPay, true))
      $errors[] = 'Invalid payment method.';

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

            header('Location: order.php?order_placed=1&order_id=' . urlencode($orderId));
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Lora',serif}
  body{font-family:var(--ui-font);}
  h1,h2,h3,h4,h5,.navbar-brand{font-family:var(--logo-font)}
  p,small{font-family:var(--text-font)}
</style>
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

.field input.is-invalid,.field select.is-invalid,.field textarea.is-invalid{
  border-color:#dc3545;background:#fff !important;
}
.live-error{color:#dc3545;font-size:.78rem;margin-top:6px;display:none;padding-left:6px}
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

/* ── Payment expand panels ── */
.pay-panel{
  display:none;
  background:#f8fdf8;border:1.5px solid var(--sage);
  border-radius:14px;padding:18px 18px 14px;
  margin-top:6px;margin-bottom:10px;
  animation:fadeSlide .2s ease;
}
.pay-panel.show{display:block;}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

/* QR block */
.qr-block{text-align:center;padding:10px 0;}
.qr-block img{width:160px;height:160px;border-radius:12px;border:2px solid var(--sage);object-fit:cover;}
.qr-label{font-size:.78rem;color:#777;margin-top:8px;}
.qr-number{font-weight:700;font-size:1rem;color:var(--green);letter-spacing:1px;margin-top:4px;}

/* Card input fields inside panel */
.card-field{position:relative;margin-bottom:14px;}
.card-field input{
  width:100%;padding:13px 14px 5px;
  background:#fff;border:1.5px solid var(--sage);
  border-radius:12px;outline:none;
  font-family:'DM Sans',sans-serif;font-size:.9rem;
  color:var(--deep);transition:.2s;
}
.card-field input:focus{border-color:var(--green);}
.card-field label{
  position:absolute;left:14px;top:13px;
  font-size:.8rem;color:#aaa;pointer-events:none;transition:.2s;
}
.card-field input:focus~label,
.card-field input:not(:placeholder-shown)~label{
  top:3px;font-size:.63rem;color:var(--green);font-weight:600;
}

/* COD info box */
.cod-info{
  display:flex;align-items:flex-start;gap:12px;
  background:#f0fdf4;border-radius:12px;padding:14px;
}
.cod-info i{color:var(--green);font-size:1.2rem;margin-top:2px;flex-shrink:0;}
.cod-info p{margin:0;font-size:.85rem;color:#444;line-height:1.5;}
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
    <input type="hidden" name="place_order" value="1">
  <div class="row g-4">

    <!-- LEFT: Delivery + Payment -->
    <div class="col-lg-7">

      <!-- Delivery Details -->
      <div class="checkout-card">
        <h5><i class="fas fa-map-marker-alt me-2" style="color:var(--green);"></i>Delivery Details</h5>

        <!-- 1. Full Name -->
        <div class="field">
          <input type="text" id="full_name" name="full_name"
            value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>"
            placeholder=" " required>
          <label>Full Name *</label>
          <div id="fullNameError" class="live-error">&nbsp;</div>
        </div>

        <!-- 2. Phone Number -->
        <div class="field">
          <input type="tel" name="phone" id="phone" placeholder=" " required
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          <label>Phone Number *</label>
          <div id="phoneError" class="live-error">&nbsp;</div>
        </div>

        <!-- 3. House / Street -->
        <div class="field">
          <input type="text" name="address" placeholder=" " required
            value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
          <label>House / Unit / Street Address *</label>
        </div>

        <!-- 4. Country (dropdown) -->
        <div class="field">
          <select name="country" id="country" required>
            <option value="Philippines" selected>Philippines</option>
          </select>
          <label>Country *</label>
        </div>

        <!-- 5. Province (dropdown) -->
        <div class="field">
          <select name="province" id="province" required onchange="filterCities()">
            <option value=""> Select Province </option>
            <?php foreach ($provinces as $_p): ?>
              <option value="<?= htmlspecialchars($_p) ?>"
                <?= ($_POST['province'] ?? '') === $_p ? 'selected' : '' ?>>
                <?= htmlspecialchars($_p) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label>Province *</label>
        </div>

        <!-- 6. City (dropdown) -->
        <div class="field">
          <select name="city" id="city" required>
            <option value=""> Select City </option>
            <?php foreach ($cities as $_c): ?>
              <option value="<?= htmlspecialchars($_c) ?>"
                <?= ($_POST['city'] ?? '') === $_c ? 'selected' : '' ?>>
                <?= htmlspecialchars($_c) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <label>City / Municipality *</label>
        </div>

        <!-- 7. ZIP Code -->
        <div class="field">
          <input type="text" id="zip" name="zip" placeholder=" " required maxlength="4"
            value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
          <label>ZIP Code *</label>
          <div id="zipError" class="live-error">&nbsp;</div>
        </div>

        <!-- 8. Delivery Notes -->
        <div class="field">
          <textarea name="notes" placeholder=" "><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          <label>Delivery Notes (optional)</label>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="checkout-card">
        <h5><i class="fas fa-credit-card me-2" style="color:var(--green);"></i>Payment Method</h5>

        <!-- ── COD ── -->
        <label class="pay-option" id="lbl-cod" onclick="togglePay('cod')">
          <input type="radio" name="pay_method" value="Cash on Delivery (COD)" id="radio-cod"
            <?= ($_POST['pay_method'] ?? '') === 'Cash on Delivery (COD)' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-hand-holding-usd"></i></div>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;">Cash on Delivery</div>
            <div style="font-size:.75rem;color:#999;">Pay when your furniture arrives</div>
          </div>
        </label>
        <div class="pay-panel" id="panel-cod">
          <div class="cod-info">
            <i class="fas fa-truck"></i>
            <p>No payment needed now. Our rider will collect the exact amount of <strong>₱<?= number_format($total) ?></strong> upon delivery. Please prepare the exact amount.</p>
          </div>
        </div>

        <!-- ── GCash ── -->
        <label class="pay-option" id="lbl-gcash" onclick="togglePay('gcash')">
          <input type="radio" name="pay_method" value="GCash" id="radio-gcash"
            <?= ($_POST['pay_method'] ?? '') === 'GCash' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-mobile-alt"></i></div>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;">GCash</div>
            <div style="font-size:.75rem;color:#999;">Pay via GCash e-wallet</div>
          </div>
        </label>
        <div class="pay-panel" id="panel-gcash">
          <div class="qr-block">
            <img src="pci/GCash.png"
                 onerror="this.outerHTML='<div style=\'width:160px;height:160px;border-radius:12px;border:2px dashed #d4e4d4;display:flex;align-items:center;justify-content:center;margin:0 auto;background:#f8fdf8;\'><span style=\'font-size:.75rem;color:#aaa;text-align:center;\'>GCash QR<br>Code Here</span></div>'"
                 alt="GCash QR Code">
            <div class="qr-label">Scan with your GCash app</div>
            <div class="qr-number"><i class="fas fa-mobile-alt me-1"></i>0917-123-4567</div>
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Account Name: <strong>ZYTHERA FURNITURE</strong></div>
          </div>
          <div style="background:#fffbeb;border-radius:10px;padding:10px 14px;font-size:.78rem;color:#92400e;margin-top:10px;">
            <i class="fas fa-info-circle me-1"></i>
            Send <strong>₱<?= number_format($total) ?></strong> and screenshot your receipt. Our team will verify the payment before shipping.
          </div>
        </div>

        <!-- ── Maya ── -->
        <label class="pay-option" id="lbl-maya" onclick="togglePay('maya')">
          <input type="radio" name="pay_method" value="Maya" id="radio-maya"
            <?= ($_POST['pay_method'] ?? '') === 'Maya' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-wallet"></i></div>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;">Maya</div>
            <div style="font-size:.75rem;color:#999;">Pay via Maya (PayMaya) e-wallet</div>
          </div>
        </label>
        <div class="pay-panel" id="panel-maya">
          <div class="qr-block">
            <img src="pci/Maya.png"
                 onerror="this.outerHTML='<div style=\'width:160px;height:160px;border-radius:12px;border:2px dashed #d4e4d4;display:flex;align-items:center;justify-content:center;margin:0 auto;background:#f8fdf8;\'><span style=\'font-size:.75rem;color:#aaa;text-align:center;\'>Maya QR<br>Code Here</span></div>'"
                 alt="Maya QR Code">
            <div class="qr-label">Scan with your Maya app</div>
            <div class="qr-number"><i class="fas fa-wallet me-1"></i>0917-765-4321</div>
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Account Name: <strong>ZYTHERA FURNITURE</strong></div>
          </div>
          <div style="background:#fffbeb;border-radius:10px;padding:10px 14px;font-size:.78rem;color:#92400e;margin-top:10px;">
            <i class="fas fa-info-circle me-1"></i>
            Send <strong>₱<?= number_format($total) ?></strong> and screenshot your receipt. Our team will verify the payment before shipping.
          </div>
        </div>

        <!-- ── Bank Transfer / Card ── -->
        <label class="pay-option" id="lbl-bank" onclick="togglePay('bank')">
          <input type="radio" name="pay_method" value="Bank Transfer" id="radio-bank"
            <?= ($_POST['pay_method'] ?? '') === 'Bank Transfer' ? 'checked' : '' ?>>
          <div class="pay-icon"><i class="fas fa-university"></i></div>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:.9rem;">Bank Transfer / Card</div>
            <div style="font-size:.75rem;color:#999;">BDO, BPI, Metrobank, UnionBank</div>
          </div>
        </label>
        <div class="pay-panel" id="panel-bank">
          <div style="font-size:.8rem;font-weight:600;color:var(--green);margin-bottom:12px;">
            <i class="fas fa-credit-card me-1"></i>Card Details
          </div>
          <div class="card-field">
            <input type="text" id="card_name" name="card_name" placeholder=" "
              value="<?= htmlspecialchars($_POST['card_name'] ?? '') ?>"
              autocomplete="cc-name">
            <label>Name on Card</label>
            <div id="cardNameError" class="live-error">&nbsp;</div>
          </div>
          <div class="card-field">
            <input type="text" id="card_number" name="card_number" placeholder=" "
              value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>"
              maxlength="19" autocomplete="cc-number" oninput="fmtCard(this)">
            <label>Card Number</label>
            <div id="cardNumberError" class="live-error">&nbsp;</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="card-field">
              <input type="text" id="card_expiry" name="card_expiry" placeholder=" "
                value="<?= htmlspecialchars($_POST['card_expiry'] ?? '') ?>"
                maxlength="5" autocomplete="cc-exp" oninput="fmtExpiry(this)">
              <label>MM / YY</label>
              <div id="cardExpiryError" class="live-error">&nbsp;</div>
            </div>
            <div class="card-field">
              <input type="password" id="card_cvv" name="card_cvv" placeholder=" "
                value="" maxlength="4" autocomplete="cc-csc">
              <label>CVV</label>
              <div id="cardCvvError" class="live-error">&nbsp;</div>
            </div>
          </div>
          <div style="background:#fffbeb;border-radius:10px;padding:10px 14px;font-size:.78rem;color:#92400e;margin-top:4px;">
            <i class="fas fa-lock me-1"></i>Your card details are encrypted and secure.
          </div>
        </div>

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
// ── Payment panel toggle ──────────────────────────────────────
const PAY_GROUPS = ['cod','gcash','maya','bank'];

function showPay(group) {
  PAY_GROUPS.forEach(g => {
    const lbl   = document.getElementById('lbl-' + g);
    const panel = document.getElementById('panel-' + g);
    if (g === group) {
      lbl?.classList.add('selected');
      panel?.classList.add('show');
    } else {
      lbl?.classList.remove('selected');
      panel?.classList.remove('show');
    }
  });
}

function togglePay(group) {
  const radio = document.getElementById('radio-' + group);
  if (radio) {
    radio.checked = true;
    showPay(group);
  }
}

// Restore on page reload (e.g. after server-side error)
(function(){
  PAY_GROUPS.forEach(g => {
    const radio = document.getElementById('radio-' + g);
    if (radio?.checked) {
      showPay(g);
    }
  });

  const payValueToGroup = value => {
    if (value === 'Bank Transfer') return 'bank';
    if (value === 'Cash on Delivery (COD)') return 'cod';
    return value.toLowerCase();
  };

  document.querySelectorAll('input[name="pay_method"]').forEach(input => {
    input.addEventListener('change', function(){
      if (this.checked) showPay(payValueToGroup(this.value));
    });
  });
})();

// ── Card number formatter (XXXX XXXX XXXX XXXX) ──────────────
function fmtCard(el) {
  let v = el.value.replace(/\D/g,'').slice(0,16);
  el.value = v.replace(/(\d{4})(?=\d)/g,'$1 ');
}

// ── Expiry formatter (MM/YY) ─────────────────────────────────
function fmtExpiry(el) {
  let v = el.value.replace(/\D/g,'').slice(0,4);
  if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
  el.value = v;
}

function setError(input, errorEl, message) {
  if (input) input.classList.toggle('is-invalid', !!message);
  if (errorEl) {
    errorEl.textContent = message || '\u00A0';
    errorEl.style.display = message ? 'block' : 'none';
  }
}

function resetCardErrors() {
  setError(document.getElementById('card_name'), document.getElementById('cardNameError'), '');
  setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), '');
  setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), '');
  setError(document.getElementById('card_cvv'), document.getElementById('cardCvvError'), '');
}

// ── Live validation ───────────────────────────────────────────
(function(){
  const phoneInput = document.getElementById('phone');
  const phoneError = document.getElementById('phoneError');
  const fullInput  = document.getElementById('full_name');
  const fullError  = document.getElementById('fullNameError');
  const zipInput   = document.getElementById('zip');
  const zipError   = document.getElementById('zipError');

  if (fullInput && fullError) {
    fullInput.addEventListener('input', function(){
      const v = (this.value||'').trim();
      if (!v) { fullError.style.display='none'; this.classList.remove('is-invalid'); return; }
      if (!/^[\p{L} .'\-]*$/u.test(v)) { fullError.textContent='Invalid characters.'; fullError.style.display='block'; this.classList.add('is-invalid'); return; }
      if (v.length<2) { fullError.textContent='Name too short.'; fullError.style.display='block'; this.classList.add('is-invalid'); return; }
      fullError.style.display='none'; this.classList.remove('is-invalid');
    });
  }

  if (phoneInput && phoneError) {
    phoneInput.addEventListener('input', function(){
      const v = (this.value||'').trim();
      if (!v) { phoneError.style.display='none'; this.classList.remove('is-invalid'); return; }
      if (!/^[0-9]*$/.test(v)) { phoneError.textContent='Digits only.'; phoneError.style.display='block'; this.classList.add('is-invalid'); return; }
      if (v.length>11) { phoneError.textContent='Max 11 digits.'; phoneError.style.display='block'; this.classList.add('is-invalid'); return; }
      if (v.length<10) { phoneError.textContent='Min 10 digits.'; phoneError.style.display='block'; this.classList.add('is-invalid'); return; }
      phoneError.style.display='none'; this.classList.remove('is-invalid');
    });
  }

  if (zipInput && zipError) {
    zipInput.addEventListener('input', function(){
      const v = (this.value||'').trim();
      if (!v) { zipError.style.display='none'; this.classList.remove('is-invalid'); return; }
      if (!/^[0-9]*$/.test(v)) { zipError.textContent='Digits only.'; zipError.style.display='block'; this.classList.add('is-invalid'); return; }
      if (v.length>4) { zipError.textContent='Max 4 digits.'; zipError.style.display='block'; this.classList.add('is-invalid'); return; }
      if (v.length<4) { zipError.textContent='Must be 4 digits.'; zipError.style.display='block'; this.classList.add('is-invalid'); return; }
      zipError.style.display='none'; this.classList.remove('is-invalid');
    });
  }
})();

// ── Submit validation ─────────────────────────────────────────
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
  const btn      = this.querySelector('.btn-place');
  const errs     = [];
  const phoneVal = (document.getElementById('phone')?.value||'').trim();
  const zipVal   = (document.getElementById('zip')?.value||'').trim();
  const provVal  = (document.getElementById('province')?.value||'').trim();
  const cityVal  = (document.getElementById('city')?.value||'').trim();
  const payVal   = this.querySelector('input[name=pay_method]:checked')?.value||'';

  if (!provVal) errs.push('Please select a province.');
  if (!cityVal) errs.push('Please select a city.');
  if (!payVal)  errs.push('Please select a payment method.');
  if (!/^[0-9]{10,11}$/.test(phoneVal)) errs.push('Phone must be 10–11 digits.');
  if (!/^[0-9]{4}$/.test(zipVal)) errs.push('ZIP Code must be 4 digits.');

  if (payVal === 'Bank Transfer') {
    const cardName = (document.getElementById('card_name')?.value||'').trim();
    const cardNum  = (document.getElementById('card_number')?.value||'').replace(/\s/g,'');
    const expiry   = (document.getElementById('card_expiry')?.value||'').trim();
    const cvv      = (document.getElementById('card_cvv')?.value||'').trim();

    resetCardErrors();

    if (!cardName) {
      setError(document.getElementById('card_name'), document.getElementById('cardNameError'), 'Please enter the name on card.');
      errs.push('Please enter the name on card.');
    }
    if (!/^\d{13,16}$/.test(cardNum)) {
      setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), 'Please enter a valid card number.');
      errs.push('Please enter a valid card number.');
    }
    if (!/^\d{2}\/\d{2}$/.test(expiry) || Number(expiry.slice(0,2)) < 1 || Number(expiry.slice(0,2)) > 12) {
      setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), 'Please enter a valid expiry (MM/YY).');
      errs.push('Please enter a valid expiry (MM/YY).');
    }
    if (!/^\d{3,4}$/.test(cvv)) {
      setError(document.getElementById('card_cvv'), document.getElementById('cardCvvError'), 'Please enter a valid CVV.');
      errs.push('Please enter a valid CVV.');
    }
  } else {
    resetCardErrors();
  }

  if (errs.length) {
    e.preventDefault();
    if (btn) btn.disabled = false;
    alert(errs.join('\n'));
    return false;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Placing Order...';
  }
});
</script>
</body>
</html>