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
$dbUser = findUserByEmail($userEmail);

if (!$dbUser) {
    foreach (['logged_in_user','role','login_time','session_start'] as $_k) unset($_SESSION[$_k]);
    header('Location: logsign.php');
    exit;
}

$userName = $dbUser->name ?? '';
$uObj = $dbUser;
$loginTime = $_SESSION['login_time'] ?? null;
$savedAddresses = loadUserAddresses((string)$dbUser->user_id);
$defaultAddress = null;
foreach ($savedAddresses as $addr) {
    if ((int)($addr->is_default ?? 0) === 1) {
        $defaultAddress = $addr;
        break;
    }
}
if (!$defaultAddress && !empty($savedAddresses)) $defaultAddress = $savedAddresses[0];

// ── Load cart from DB ─────────────────────────────────────────
session_write_close();
session_start();

$cart = loadCartForUser($userEmail);
$_SESSION['cart'][$userEmail] = $cart;

if (empty($cart)) {
    header('Location: website.php');
    exit;
}

$selectedRaw = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRaw = trim($_POST['selected_items'] ?? '');
} elseif (isset($_GET['selected'])) {
    $selectedRaw = trim($_GET['selected'] ?? '');
}
if ($selectedRaw !== '') {
    $_SESSION['checkout_selected'][$userEmail] = $selectedRaw;
} else {
    $selectedRaw = trim($_SESSION['checkout_selected'][$userEmail] ?? '');
}

$selectedItemIds = array_values(array_unique(array_filter(array_map('trim', explode(',', $selectedRaw)), fn($id) => $id !== '')));
if (empty($selectedItemIds)) {
    header('Location: website.php?cart_error=select_items');
    exit;
}

$cart = array_values(array_filter($cart, function ($item) use ($selectedItemIds) {
    return in_array((string)($item['inv_id'] ?? ''), $selectedItemIds, true);
}));

if (empty($cart)) {
    unset($_SESSION['checkout_selected'][$userEmail]);
    header('Location: website.php?cart_error=select_items');
    exit;
}

// ── Compute totals ────────────────────────────────────────────
$subtotal = 0;
foreach ($cart as $ci) {
    $subtotal += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? 1);
}
$shipping = $subtotal > 0 ? 150 : 0;
$total    = $subtotal + $shipping;

// ── Allowed locations ──────────────────────────────────────────
require __DIR__ . '/includes/location_data.php';

// ── Handle checkout form submission ───────────────────────────
$errors      = [];
$orderPlaced = false;

function savePaymentProofUpload(array $file, array &$errors): ?string {
    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please attach your proof of payment (screenshot/receipt).';
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        $uploadMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Proof of payment is larger than the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'Proof of payment is larger than the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'Proof of payment upload was interrupted. Please choose the image again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded proof.',
            UPLOAD_ERR_EXTENSION  => 'Server blocked the proof upload.',
        ];
        $errors[] = $uploadMessages[$uploadError] ?? 'Proof of payment upload failed. Please choose the image again.';
        return null;
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Proof of payment upload could not be verified. Please try again.';
        return null;
    }

    $fileSize = (int)($file['size'] ?? 0);
    if ($fileSize <= 0) {
        $errors[] = 'Proof of payment image is empty. Please choose another file.';
        return null;
    }
    if ($fileSize > 5 * 1024 * 1024) {
        $errors[] = 'Proof of payment image must be under 5MB.';
        return null;
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $fileType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $fileType = (string)finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if ($fileType === '' && function_exists('mime_content_type')) {
        $fileType = (string)mime_content_type($file['tmp_name']);
    }
    if ($fileType === '' && function_exists('getimagesize')) {
        $imageInfo = @getimagesize($file['tmp_name']);
        $fileType = (string)($imageInfo['mime'] ?? '');
    }
    if (!isset($allowedTypes[$fileType])) {
        $errors[] = 'Proof of payment must be an image (JPG, PNG, GIF, WebP).';
        return null;
    }

    $uploadsDir = __DIR__ . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }
    @chmod($uploadsDir, 0777);

    $proofDir = $uploadsDir . '/proofs';
    if (!is_dir($proofDir)) {
        @mkdir($proofDir, 0777, true);
    }
    if (!is_writable($proofDir)) {
        @chmod($proofDir, 0777);
    }
    if (!is_writable($proofDir)) {
        $errors[] = 'Proof upload folder is not writable. Please run: chmod -R 777 uploads/ on your server.';
        return null;
    }

    $proofName = 'proof_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$fileType];
    $target    = $proofDir . '/' . $proofName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $errors[] = 'Failed to upload proof of payment. Please try again.';
        return null;
    }

    return 'uploads/proofs/' . $proofName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedAddressId = trim($_POST['saved_address_id'] ?? '');
    $fullName    = trim($_POST['full_name']    ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $address     = trim($_POST['address']      ?? '');
    $barangay    = trim($_POST['barangay']     ?? '');
    $province    = trim($_POST['province']     ?? '');
    $city        = trim($_POST['city']         ?? '');
    $zip         = trim($_POST['zip']          ?? '');
    $payMethod   = trim($_POST['pay_method']   ?? '');
    $refNo       = trim($_POST['ref_no']       ?? '');
    $notes       = trim($_POST['notes']        ?? '');

    if ($savedAddressId !== '') {
        $savedAddress = getUserAddress((string)$dbUser->user_id, $savedAddressId);
        if ($savedAddress) {
            $phone    = trim($savedAddress->phone_num ?? '');
            $address  = trim($savedAddress->st_address ?? '');
            $barangay = trim($savedAddress->barangay ?? '');
            $province = trim($savedAddress->province ?? '');
            $city     = trim($savedAddress->city_municipality ?? '');
            $zip      = trim($savedAddress->zip_code ?? '');
        } else {
            $errors[] = 'Selected saved address could not be found.';
        }
    }

    $proofPath = null;
    $eWalletMethods = ['GCash', 'Maya', 'Bank Transfer'];
    if (in_array($payMethod, $eWalletMethods, true)) {
        $proofPath = savePaymentProofUpload($_FILES['pay_proof'] ?? [], $errors);
        if (empty($errors) && !$refNo) {
            $errors[] = 'Please enter your reference / transaction number.';
        }
    }

    if (!$fullName)  $errors[] = 'Full name is required.';
    if (!$phone)     $errors[] = 'Phone number is required.';
    if (!$address)   $errors[] = 'Complete address is required.';
    if (!$barangay)  $errors[] = 'Barangay is required.';
    if (!$province)  $errors[] = 'Province is required.';
    if (!$city)      $errors[] = 'City is required.';
    if (!$zip)       $errors[] = 'ZIP Code is required.';
    if (!$payMethod) $errors[] = 'Please select a payment method.';
    if ($city && !in_array($city, $cities, true))
      $errors[] = 'Please select a valid city from the list.';
    if ($province && $city && isset($provinceCities[$province]) && !empty($provinceCities[$province])
        && !in_array($city, $provinceCities[$province], true)) {
      $errors[] = 'Please select a city that belongs to the selected province.';
    }
    if ($city && isset($cityZipCodes[$city]) && $zip !== $cityZipCodes[$city]) {
      $errors[] = 'ZIP Code does not match the selected city.';
    }
    if ($phone && !preg_match('/^[0-9]{10,11}$/', $phone))
      $errors[] = 'Phone number must be 10 or 11 digits (numbers only).';
    if ($zip && !preg_match('/^[0-9]{4}$/', $zip))
      $errors[] = 'ZIP Code must be 4 digits.';
    if ($fullName && !preg_match('/^[\p{L} .\'-]{2,100}$/u', $fullName))
      $errors[] = 'Full name appears invalid.';

    $allowedPay = ['GCash','Bank Transfer','Maya'];
    if ($payMethod && !in_array($payMethod, $allowedPay, true))
      $errors[] = 'Invalid payment method.';

    if (empty($errors)) {
        try {
            $orderId = generateCustomId('OR');

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
                    'barangay'  => $barangay,
                    'city'      => $city,
                    'province'  => $province,
                    'zip'       => $zip,
                    'notes'     => $notes,
                ],
                'items'         => $cart,
            ];

            $db->beginTransaction();

            $userId    = (string)$dbUser->user_id;
            $addressId = findOrCreateAddress($userId, $phone, $address, $barangay, $city, $province, $zip);
            $paymentId = createPayment($payMethod, 'pending', $refNo ?: null, $proofPath);

            $oStmt = $db->prepare("
                INSERT INTO orders
                (order_id, user_id, address_id, payment_id, total_ammount, shipping_fee, user_note, order_date, order_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $oStmt->execute([
                $orderId, $userId, $addressId, $paymentId, $total, $shipping, $notes, 'Pending'
            ]);
            $dbOrdNo = $orderId;

            $oiStmt = $db->prepare("
                INSERT INTO order_items (orderitem_id, order_id, prod_id, prod_name, quantity, unit_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($cart as $ci) {
                $oiStmt->execute([
                    generateCustomId('ODR'),
                    $dbOrdNo,
                    (string)($ci['inv_id'] ?? ''),
                    trim($ci['name']       ?? ''),
                    (int)($ci['qty']       ?? 1),
                    (float)($ci['price']   ?? 0),
                ]);
            }

            $deductStmt = $db->prepare("
                UPDATE product_inv SET prod_stock = prod_stock - ?
                WHERE prod_id = ? AND prod_stock >= ?
            ");
            foreach ($cart as $ci) {
                $qty = (int)($ci['qty'] ?? 1);
                $pid = (string)($ci['inv_id'] ?? '');
                $deductStmt->execute([$qty, $pid, $qty]);
                if ($deductStmt->rowCount() === 0) {
                    throw new Exception('Insufficient stock for product ID ' . $pid);
                }
            }

            removeCartItemsForUser($userEmail, $selectedItemIds);
            unset($_SESSION['checkout_selected'][$userEmail]);

            $db->commit();

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
            if ($proofPath && is_file(__DIR__ . '/' . $proofPath)) {
                @unlink(__DIR__ . '/' . $proofPath);
            }
            $errors[] = "Checkout processing failed: " . $e->getMessage();
        }
    }
}

$checkoutAddress = [
    'phone' => $_POST['phone'] ?? ($defaultAddress->phone_num ?? ''),
    'province' => $_POST['province'] ?? ($defaultAddress->province ?? ''),
    'city' => $_POST['city'] ?? ($defaultAddress->city_municipality ?? ''),
    'barangay' => $_POST['barangay'] ?? ($defaultAddress->barangay ?? ''),
    'address' => $_POST['address'] ?? ($defaultAddress->st_address ?? ''),
    'zip' => $_POST['zip'] ?? ($defaultAddress->zip_code ?? ''),
    'selected_id' => $_POST['saved_address_id'] ?? ($defaultAddress->address_id ?? ''),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZYTHERA | Checkout</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="assets/css/responsive.css">

  <link rel="stylesheet" href="assets/css/checkout.css">
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

<div class="container py-4" style="max-width:980px;">

  <div class="mb-4">
    <p class="step-label"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> FURNITURE</p>
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

  <form method="POST" id="checkoutForm" enctype="multipart/form-data">
    <input type="hidden" name="place_order" value="1">
    <input type="hidden" name="selected_items" id="selected_items" value="<?= htmlspecialchars(implode(',', $selectedItemIds)) ?>">
  <div class="row g-4">

    <!-- LEFT: Delivery + Payment -->
    <div class="col-lg-7">

      <!-- Delivery Details -->
      <div class="checkout-card">
        <h5><i class="fas fa-map-marker-alt me-2" style="color:var(--green);"></i>Delivery Details</h5>

        <?php if ($defaultAddress): ?>
          <div class="saved-address-grid" id="savedAddressGrid">
            <div class="saved-address-option selected" style="cursor:default;">
              <input type="radio" name="saved_address_id" value="<?= htmlspecialchars((string)$defaultAddress->address_id) ?>" checked>
              <div>
                <span class="addr-label"><i class="fas fa-tag"></i><?= htmlspecialchars($defaultAddress->address_label ?? 'Home') ?></span>
                <?php if ((int)($defaultAddress->is_default ?? 0) === 1): ?>
                  <span class="badge rounded-pill text-bg-success ms-1">Default</span>
                <?php endif; ?>
                <div style="font-weight:700;color:var(--deep);font-size:.9rem;margin-top:6px;">
                  <?= htmlspecialchars($defaultAddress->st_address ?? '') ?>, <?= htmlspecialchars($defaultAddress->barangay ?? '') ?>
                </div>
                <div style="font-size:.78rem;color:#777;">
                  <?= htmlspecialchars($defaultAddress->city_municipality ?? '') ?>, <?= htmlspecialchars($defaultAddress->province ?? '') ?> <?= htmlspecialchars($defaultAddress->zip_code ?? '') ?>
                </div>
                <div style="font-size:.78rem;color:#777;margin-top:3px;">
                  <i class="fas fa-phone me-1"></i><?= htmlspecialchars($defaultAddress->phone_num ?? '') ?>
                </div>
              </div>
            </div>
          </div>
          <div style="font-size:.78rem;color:#777;margin:-6px 0 18px;">
            To change your delivery address, update your default address in <a href="profile.php?tab=addresses" style="color:var(--green);font-weight:700;">Profile</a>.
          </div>
        <?php else: ?>
          <div class="alert alert-warning" style="border-radius:14px;font-size:.88rem;">
            Please add a delivery address in <a href="profile.php?tab=addresses" class="alert-link">Profile</a> before checking out.
          </div>
        <?php endif; ?>

        <input type="hidden" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? $userName) ?>">
        <input type="hidden" name="phone" id="phone" value="<?= htmlspecialchars($checkoutAddress['phone']) ?>">
        <input type="hidden" name="province" id="province" value="<?= htmlspecialchars($checkoutAddress['province']) ?>">
        <input type="hidden" name="city" id="city" value="<?= htmlspecialchars($checkoutAddress['city']) ?>">
        <input type="hidden" name="barangay" id="barangay" value="<?= htmlspecialchars($checkoutAddress['barangay']) ?>">
        <input type="hidden" name="address" id="address" value="<?= htmlspecialchars($checkoutAddress['address']) ?>">
        <input type="hidden" id="zip" name="zip" value="<?= htmlspecialchars($checkoutAddress['zip']) ?>">

        <!-- 8. Delivery Notes -->
        <div class="field">
          <textarea name="notes" placeholder=" "><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          <label>Delivery Notes (optional)</label>
        </div>
      </div>

      <!-- Payment Method -->
      <div class="checkout-card">
        <h5><i class="fas fa-credit-card me-2" style="color:var(--green);"></i>Payment Method</h5>

        <!-- GCash -->
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
          <div class="proof-slot" id="proof-slot-gcash"></div>
        </div>

        <!-- Maya -->
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
          <div class="proof-slot" id="proof-slot-maya"></div>
        </div>

        <!-- Bank Transfer / Card -->
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
          <div class="proof-slot" id="proof-slot-bank"></div>
        </div>

        <!-- Shared Proof of Payment block -->
        <div id="proof-of-payment-block" style="display:none;margin-top:4px;background:#f8fdf8;border:1.5px solid #d4e4d4;border-radius:14px;padding:14px 16px;">
          <div style="font-size:.78rem;font-weight:700;color:#2d5a2d;margin-bottom:10px;">
            <i class="fas fa-paperclip me-1"></i>Attach Proof of Payment *
          </div>
          <div id="proof-upload-area" onclick="document.getElementById('pay_proof').click()"
               style="border:2px dashed #a7c7a7;border-radius:12px;padding:20px;text-align:center;cursor:pointer;background:#fff;transition:border-color .2s,background .2s;">
            <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:#7aab7a;margin-bottom:6px;display:block;"></i>
            <div style="font-size:.78rem;color:#555;">Click to upload your screenshot / receipt</div>
            <div style="font-size:.7rem;color:#aaa;margin-top:3px;">JPG, PNG, WebP — max 5MB</div>
            <div id="proof-file-name" style="font-size:.76rem;color:#2d5a2d;margin-top:8px;font-weight:700;"></div>
          </div>
          <input type="file" id="pay_proof" name="pay_proof" accept="image/*" style="display:none;"
                 onchange="handleProofFile(this)">
          <div style="margin-top:10px;">
            <input type="text" id="ref_no" name="ref_no"
              placeholder="Reference / Transaction No. *"
              value="<?= htmlspecialchars($_POST['ref_no'] ?? '') ?>"
              style="width:100%;padding:9px 12px;font-size:.82rem;border-radius:10px;border:1.5px solid #d4e4d4;background:#fff;outline:none;font-family:inherit;box-sizing:border-box;">
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
        </div>
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

<!-- Logout Modal -->
<div id="logoutModalOverlay" class="logout-modal-overlay">
  <div class="logout-modal">
    <h2>Log Out Confirmation</h2>
    <p>Are you sure you want to log out of your account?</p>
    <div class="logout-modal-buttons">
      <button type="button" class="logout-cancel-btn"  onclick="closeLogoutModal()">Stay</button>
      <button type="button" class="logout-confirm-btn" onclick="performLogout()">Logout</button>
    </div>
  </div>
</div>

  <script>
    /* PHP-seeded globals for checkout.js */
    const PROVINCE_CITIES  = <?= json_encode($provinceCities,  JSON_UNESCAPED_UNICODE) ?>;
    const CITY_ZIP_CODES   = <?= json_encode($cityZipCodes,    JSON_UNESCAPED_UNICODE) ?>;
    const CITY_BARANGAYS   = <?= json_encode($cityBarangays,   JSON_UNESCAPED_UNICODE) ?>;
    const ALL_CITIES       = <?= json_encode($cities,          JSON_UNESCAPED_UNICODE) ?>;
    const SAVED_BARANGAY   = <?= json_encode($checkoutAddress['barangay'] ?? '') ?>;
    let checkoutCart = <?= json_encode(array_values(array_map(function($i){
      return ['inv_id'=>(string)($i['inv_id']??''),'name'=>$i['name']??'','price'=>(float)($i['price']??0),'qty'=>(int)($i['qty']??1),'image'=>$i['image']??'','stock'=>(int)($i['stock']??0)];
    }, $cart))) ?>;
    const CHECKOUT_SELECTED_IDS = new Set(<?= json_encode(array_values($selectedItemIds)) ?>.map(String));
  </script>
  <script src="assets/js/checkout.js"></script>
</body>
</html>
