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
<style>
  :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Merriweather',serif}
  body{font-family:var(--ui-font);}
  h1,h2,h3,h4,h5,.navbar-brand{font-family:var(--logo-font)}
  p,small{font-family:var(--text-font)}
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="dark-mode.css">
<script src="dark-mode.js"></script>
<style>
:root{--green:#2d5a2d;--sage:#d4e4d4;--cream:#f5f2ec;--deep:#1a2e1a;--terra:#bc8a7b;}
*{font-family: var(--ui-font);box-sizing:border-box;}
body{background:var(--cream);min-height:100vh;padding-top:70px;}

.navbar{background:#fff!important;box-shadow:0 1px 12px rgba(0,0,0,.07);}
.navbar-brand{font-family:'Playfair Display',serif;color:var(--green)!important;font-size:1.55rem;letter-spacing:2px;}

.step-label{font-size:.7rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--green);margin-bottom:6px;}

.checkout-card{background:#fff;border-radius:20px;box-shadow:0 4px 20px rgba(0,0,0,.07);padding:28px;margin-bottom:20px;}
.checkout-card h5{font-family:'Playfair Display',serif;color:var(--deep);font-size:1.15rem;margin-bottom:20px;}

.saved-address-grid{display:grid;gap:10px;margin-bottom:18px;}
.saved-address-option{display:flex;gap:12px;align-items:flex-start;border:2px solid var(--sage);border-radius:14px;padding:13px 14px;background:#fff;cursor:pointer;transition:.18s;}
.saved-address-option:hover,.saved-address-option.selected{border-color:var(--green);background:#f8fdf8;}
.saved-address-option input{accent-color:var(--green);margin-top:4px;}
.addr-label{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:var(--sage);color:var(--green);font-size:.7rem;font-weight:800;padding:3px 9px;margin-bottom:5px;}

.field{position:relative;margin-bottom:18px;}
.field input,.field select,.field textarea{
  width:100%;padding:15px 14px 7px;
  background:var(--sage);border:2px solid transparent;
  border-radius:14px;outline:none;
  font-family:var(--ui-font);font-size:.92rem;
  color:var(--deep);transition:.2s;appearance:none;
}
.field input.is-invalid,.field select.is-invalid,.field textarea.is-invalid{border-color:#dc3545;background:#fff !important;}
.live-error{color:#dc3545;font-size:.78rem;margin-top:6px;display:none;padding-left:6px}
.field textarea{min-height:80px;resize:none;padding-top:20px;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--green);background:#fff;}
.field label{position:absolute;left:14px;top:14px;font-size:.82rem;color:#999;pointer-events:none;transition:.2s;}
.field input:focus~label,
.field input:not(:placeholder-shown)~label,
.field textarea:focus~label,
.field textarea:not(:placeholder-shown)~label{top:4px;font-size:.67rem;color:var(--green);font-weight:600;}
.field select~label{top:4px;font-size:.67rem;color:var(--green);font-weight:600;}

/* readonly postal code styling */
.field input[readonly]{background:#eef4ee;color:#555;cursor:default;}
.field input[readonly]:focus{border-color:var(--sage);background:#eef4ee;}

.pay-option{display:flex;align-items:center;gap:12px;padding:14px 16px;border:2px solid var(--sage);border-radius:14px;cursor:pointer;transition:.2s;margin-bottom:10px;}
.pay-option:hover{border-color:var(--green);background:#f8fdf8;}
.pay-option input[type=radio]{accent-color:var(--green);width:16px;height:16px;flex-shrink:0;}
.pay-option.selected{border-color:var(--green);background:#f0f7f0;}
.pay-icon{width:36px;height:36px;border-radius:10px;background:var(--sage);display:flex;align-items:center;justify-content:center;color:var(--green);font-size:1rem;}

.order-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f0f0eb;}
.order-item:last-child{border-bottom:none;}
.order-item img{width:52px;height:52px;object-fit:cover;border-radius:10px;background:var(--sage);flex-shrink:0;}
.order-total-row{display:flex;justify-content:space-between;font-size:.88rem;color:#777;padding:4px 0;}
.order-total-row.grand{font-size:1.05rem;font-weight:800;color:var(--green);border-top:2px solid var(--sage);padding-top:12px;margin-top:6px;}

.btn-place{width:100%;padding:15px;border:none;background:var(--green);color:#fff;border-radius:50px;font-weight:700;font-size:1rem;cursor:pointer;transition:.2s;letter-spacing:.5px;}
.btn-place:hover{background:var(--deep);}
.btn-place:disabled{opacity:.6;cursor:not-allowed;}

.alert-errors{background:#fee2e2;border:1px solid #fca5a5;border-radius:14px;padding:14px 18px;margin-bottom:20px;color:#b91c1c;font-size:.85rem;}

footer{display:flex;align-items:center;justify-content:center;gap:12px;padding:24px;margin-top:40px;border-top:1px solid #e8e4dc;}
footer .footer-brand{font-family:'Playfair Display',serif;color:var(--green);font-size:1rem;letter-spacing:3px;}

.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar{width:5px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-track{background:var(--sage);border-radius:4px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-thumb{background:var(--green);border-radius:4px;}
.checkout-card > div[style*="overflow-y:auto"]::-webkit-scrollbar-thumb:hover{background:var(--deep);}

.pay-panel{display:none;background:#f8fdf8;border:1.5px solid var(--sage);border-radius:14px;padding:18px 18px 14px;margin-top:6px;margin-bottom:10px;animation:fadeSlide .2s ease;}
.pay-panel.show{display:block;}
@keyframes fadeSlide{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

.qr-block{text-align:center;padding:10px 0;}
.qr-block img{width:160px;height:160px;border-radius:12px;border:2px solid var(--sage);object-fit:cover;}
.qr-label{font-size:.78rem;color:#777;margin-top:8px;}
.qr-number{font-weight:700;font-size:1rem;color:var(--green);letter-spacing:1px;margin-top:4px;}

.card-field{position:relative;margin-bottom:14px;}
.card-field input{width:100%;padding:13px 14px 5px;background:#fff;border:1.5px solid var(--sage);border-radius:12px;outline:none;font-family:var(--ui-font);font-size:.9rem;color:var(--deep);transition:.2s;}
.card-field input:focus{border-color:var(--green);}
.card-field label{position:absolute;left:14px;top:13px;font-size:.8rem;color:#aaa;pointer-events:none;transition:.2s;}
.card-field input:focus~label,.card-field input:not(:placeholder-shown)~label{top:3px;font-size:.63rem;color:var(--green);font-weight:600;}
</style>
<script>
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
<link rel="stylesheet" href="responsive.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="website.php"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></a>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill px-3">
        <i class="fas fa-arrow-left me-1"></i> Keep Shopping
      </a>
      <a href="profile.php" class="btn btn-sm btn-light rounded-pill px-3">My Profile</a>
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
<script>
// ── Data from PHP ─────────────────────────────────────────────
const PROVINCE_CITIES  = <?= json_encode($provinceCities,  JSON_UNESCAPED_UNICODE) ?>;
const CITY_ZIP_CODES   = <?= json_encode($cityZipCodes,    JSON_UNESCAPED_UNICODE) ?>;
const CITY_BARANGAYS   = <?= json_encode($cityBarangays,   JSON_UNESCAPED_UNICODE) ?>;
const ALL_CITIES       = <?= json_encode($cities,          JSON_UNESCAPED_UNICODE) ?>;
const SAVED_BARANGAY   = <?= json_encode($checkoutAddress['barangay'] ?? '') ?>;

// ── Province → City filter ────────────────────────────────────
function filterCities() {
  const provinceEl = document.getElementById('province');
  const citySelect = document.getElementById('city');
  if (!provinceEl || !citySelect || citySelect.tagName !== 'SELECT') return;
  const province   = provinceEl.value;
  const savedCity  = citySelect.value;

  const list = (PROVINCE_CITIES[province] && PROVINCE_CITIES[province].length)
    ? PROVINCE_CITIES[province]
    : ALL_CITIES;

  citySelect.innerHTML = '<option value="">Select City / Municipality</option>';
  list.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c; opt.textContent = c;
    if (c === savedCity) opt.selected = true;
    citySelect.appendChild(opt);
  });

  if (savedCity && !list.includes(savedCity)) citySelect.value = '';
  updateZipCode();
  filterBarangays();
}

// ── City → ZIP auto-fill ──────────────────────────────────────
function updateZipCode() {
  const city = document.getElementById('city')?.value || '';
  const zip = document.getElementById('zip');
  if (zip) zip.value = CITY_ZIP_CODES[city] || zip.value || '';
}

// ── City → Barangay filter ────────────────────────────────────
function filterBarangays() {
  const city     = document.getElementById('city')?.value || '';
  const sel      = document.getElementById('barangay');
  if (!sel || sel.tagName !== 'SELECT') return;
  const previous = sel.value;

  let list = (CITY_BARANGAYS[city] && CITY_BARANGAYS[city].length)
    ? [...CITY_BARANGAYS[city]].sort()
    : ['Poblacion', ...Array.from({length:30}, (_,i) => 'Barangay ' + (i+1))];

  sel.innerHTML = '<option value="">Select Barangay</option>';
  list.forEach(b => {
    const opt = document.createElement('option');
    opt.value = b; opt.textContent = b;
    if (b === previous || b === SAVED_BARANGAY) opt.selected = true;
    sel.appendChild(opt);
  });
}

document.querySelectorAll('.saved-address-option').forEach(label => {
  label.addEventListener('click', () => {
    document.querySelectorAll('.saved-address-option').forEach(el => el.classList.remove('selected'));
    label.classList.add('selected');
    const radio = label.querySelector('input[type=radio]');
    if (radio) radio.checked = true;
    const data = JSON.parse(label.dataset.address || '{}');
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('province').value = data.province || '';
    filterCities();
    document.getElementById('city').value = data.city || '';
    updateZipCode();
    filterBarangays();
    const brgy = document.getElementById('barangay');
    if (brgy) {
      brgy.value = data.barangay || '';
      if (data.barangay && brgy.value !== data.barangay) {
        const opt = document.createElement('option');
        opt.value = data.barangay;
        opt.textContent = data.barangay;
        opt.selected = true;
        brgy.appendChild(opt);
      }
    }
    document.getElementById('address').value = data.address || '';
    document.getElementById('zip').value = data.zip || CITY_ZIP_CODES[data.city] || '';
  });
});

// ── Payment panel toggle ──────────────────────────────────────
const PAY_GROUPS = ['gcash','maya','bank'];

function showPay(group) {
  PAY_GROUPS.forEach(g => {
    document.getElementById('lbl-' + g)?.classList.toggle('selected', g === group);
    document.getElementById('panel-' + g)?.classList.toggle('show',   g === group);
  });
  const proofBlock = document.getElementById('proof-of-payment-block');
  const slot       = document.getElementById('proof-slot-' + group);
  if (proofBlock && slot) {
    slot.appendChild(proofBlock);
    proofBlock.style.display = 'block';
  } else if (proofBlock) {
    proofBlock.style.display = 'none';
  }
}

function togglePay(group) {
  const radio = document.getElementById('radio-' + group);
  if (radio) { radio.checked = true; showPay(group); }
}

function handleProofFile(input) {
  const nameEl = document.getElementById('proof-file-name');
  const areaEl = document.getElementById('proof-upload-area');
  if (input.files && input.files[0]) {
    if (nameEl) nameEl.textContent = '✓ ' + input.files[0].name;
    if (areaEl) { areaEl.style.borderColor = 'var(--green)'; areaEl.style.background = '#f0f7f0'; }
  } else {
    if (nameEl) nameEl.textContent = '';
    if (areaEl) { areaEl.style.borderColor = '#a7c7a7'; areaEl.style.background = '#fff'; }
  }
}

// ── Card formatters ───────────────────────────────────────────
function fmtCard(el)   { let v=el.value.replace(/\D/g,'').slice(0,16); el.value=v.replace(/(\d{4})(?=\d)/g,'$1 '); }
function fmtExpiry(el) { let v=el.value.replace(/\D/g,'').slice(0,4); if(v.length>=3) v=v.slice(0,2)+'/'+v.slice(2); el.value=v; }

function setError(input, errorEl, message) {
  if (input) input.classList.toggle('is-invalid', !!message);
  if (errorEl) { errorEl.textContent = message || '\u00A0'; errorEl.style.display = message ? 'block' : 'none'; }
}

function resetCardErrors() {
  ['card_name','card_number','card_expiry','card_cvv'].forEach(id => {
    setError(document.getElementById(id), document.getElementById(id.replace('card_','card')+'Error'), '');
  });
  setError(document.getElementById('card_name'),   document.getElementById('cardNameError'),   '');
  setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), '');
  setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), '');
  setError(document.getElementById('card_cvv'),    document.getElementById('cardCvvError'),    '');
}

// ── Live validation ───────────────────────────────────────────
(function(){
  const rules = [
    { id:'full_name', errId:'fullNameError', validate: v => {
      if (!v) return ''; if (!/^[\p{L} .'\-]*$/u.test(v)) return 'Invalid characters.';
      if (v.length<2) return 'Name too short.'; return '';
    }},
    { id:'phone', errId:'phoneError', validate: v => {
      if (!v) return ''; if (!/^[0-9]*$/.test(v)) return 'Digits only.';
      if (v.length>11) return 'Max 11 digits.'; if (v.length<10) return 'Min 10 digits.'; return '';
    }},
  ];
  rules.forEach(({id, errId, validate}) => {
    const inp = document.getElementById(id);
    const err = document.getElementById(errId);
    if (inp && err) inp.addEventListener('input', function(){
      const msg = validate((this.value||'').trim());
      err.textContent = msg || '\u00A0'; err.style.display = msg ? 'block' : 'none';
      this.classList.toggle('is-invalid', !!msg);
    });
  });
})();

// ── Submit validation ─────────────────────────────────────────
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
  const btn    = this.querySelector('.btn-place');
  const errs   = [];

  // Check if a saved address radio is selected (has a value)
  const savedAddrRadio = this.querySelector('input[name=saved_address_id]:checked');
  const usingSavedAddress = savedAddrRadio && savedAddrRadio.value !== '';

  const phone  = (document.getElementById('phone')?.value||'').trim();
  const prov   = (document.getElementById('province')?.value||'').trim();
  const city   = (document.getElementById('city')?.value||'').trim();
  const brgy   = (document.getElementById('barangay')?.value||'').trim();
  const addr   = (document.getElementById('address')?.value||'').trim();
  const zip    = (document.getElementById('zip')?.value||'').trim();
  const payVal = this.querySelector('input[name=pay_method]:checked')?.value||'';

  // Only validate address fields if NOT using a saved address
  if (!usingSavedAddress) {
    if (!prov)  errs.push('Please select a province.');
    if (!city)  errs.push('Please select a city.');
    if (!brgy)  errs.push('Please select a barangay.');
    if (!addr)  errs.push('Please enter your house / street address.');
    if (!zip)   errs.push('Postal code could not be auto-filled. Please select a valid city.');
    if (!/^[0-9]{10,11}$/.test(phone)) errs.push('Phone must be 10–11 digits.');
  }
  if (!payVal) errs.push('Please select a payment method.');

  const eWalletMethods = ['GCash','Maya','Bank Transfer'];
  if (eWalletMethods.includes(payVal)) {
    const proofInput = document.getElementById('pay_proof');
    const refInput   = document.getElementById('ref_no');
    if (!proofInput?.files?.length) errs.push('Please attach your proof of payment.');
    if (!refInput?.value.trim())    errs.push('Please enter your reference / transaction number.');
  }

  if (payVal === 'Bank Transfer') {
    const cardName = (document.getElementById('card_name')?.value||'').trim();
    const cardNum  = (document.getElementById('card_number')?.value||'').replace(/\s/g,'');
    const expiry   = (document.getElementById('card_expiry')?.value||'').trim();
    const cvv      = (document.getElementById('card_cvv')?.value||'').trim();
    resetCardErrors();
    if (!cardName) { setError(document.getElementById('card_name'),   document.getElementById('cardNameError'),   'Please enter the name on card.'); errs.push('Please enter the name on card.'); }
    if (!/^\d{13,16}$/.test(cardNum)) { setError(document.getElementById('card_number'), document.getElementById('cardNumberError'), 'Please enter a valid card number.'); errs.push('Please enter a valid card number.'); }
    if (!/^\d{2}\/\d{2}$/.test(expiry)||Number(expiry.slice(0,2))<1||Number(expiry.slice(0,2))>12) { setError(document.getElementById('card_expiry'), document.getElementById('cardExpiryError'), 'Please enter a valid expiry (MM/YY).'); errs.push('Please enter a valid expiry (MM/YY).'); }
    if (!/^\d{3,4}$/.test(cvv)) { setError(document.getElementById('card_cvv'), document.getElementById('cardCvvError'), 'Please enter a valid CVV.'); errs.push('Please enter a valid CVV.'); }
  } else {
    resetCardErrors();
  }

  if (errs.length) { e.preventDefault(); if (btn) btn.disabled=false; alert(errs.join('\n')); return false; }
  if (btn) { btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-2"></i>Placing Order...'; }
});

// ── Init on DOM ready ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // Restore province → city → barangay chain on page reload (after POST error)
  filterCities();

  // Restore payment panel
  PAY_GROUPS.forEach(g => {
    if (document.getElementById('radio-' + g)?.checked) showPay(g);
  });

  // Restore saved barangay after filterBarangays populates the list
  if (SAVED_BARANGAY) {
    const sel = document.getElementById('barangay');
    if (sel && !sel.value) {
      const opt = document.createElement('option');
      opt.value = SAVED_BARANGAY; opt.textContent = SAVED_BARANGAY; opt.selected = true;
      sel.appendChild(opt);
    }
  }
});

// ── Cart live sync (BroadcastChannel + polling) ───────────────
let checkoutCart = <?= json_encode(array_values(array_map(function($i){
    return ['inv_id'=>(string)($i['inv_id']??''),'name'=>$i['name']??'','price'=>(float)($i['price']??0),'qty'=>(int)($i['qty']??1),'image'=>$i['image']??''];
}, $cart))) ?>;
const CHECKOUT_SELECTED_IDS = new Set(<?= json_encode(array_values($selectedItemIds)) ?>.map(String));
const SHIPPING_FEE = 150;

function numFmt(n)    { return Number(n).toLocaleString('en-PH',{minimumFractionDigits:0,maximumFractionDigits:0}); }
function escHtml(s)   { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function rebuildOrderSummary(cart) {
  cart = (cart || []).filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id)));
  const container  = document.querySelector('.checkout-card div[style*="overflow-y:auto"]');
  const subtotalEl = document.querySelector('.order-total-row:nth-child(1) span:last-child');
  const shippingEl = document.querySelector('.order-total-row:nth-child(2) span:last-child');
  const totalEl    = document.querySelector('.order-total-row.grand span:last-child');
  if (!container) return;
  if (!cart || !cart.length) { window.location.href='website.php'; return; }
  let html='', subtotal=0;
  cart.forEach(item => {
    const qty=Number(item.qty)||1, price=Number(item.price)||0, line=price*qty;
    subtotal+=line;
    const img=item.image||'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';
    html+=`<div class="order-item"><img src="${escHtml(img)}" alt="" onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'"><div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:.85rem;color:var(--deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(item.name||'')}</div><div style="font-size:.76rem;color:#999;">₱${numFmt(price)} × ${qty}</div></div><div style="font-weight:700;color:var(--green);font-size:.88rem;white-space:nowrap;">₱${numFmt(line)}</div></div>`;
  });
  container.innerHTML=html;
  const shipping=subtotal>0?SHIPPING_FEE:0, total=subtotal+shipping;
  if(subtotalEl) subtotalEl.textContent='₱'+numFmt(subtotal);
  if(shippingEl) shippingEl.textContent='₱'+numFmt(shipping);
  if(totalEl)    totalEl.textContent='₱'+numFmt(total);
}

try {
  const bc=new BroadcastChannel('zythera_cart');
  bc.addEventListener('message',e=>{ if(e.data?.type==='cart_updated'&&Array.isArray(e.data.cart)){ checkoutCart=e.data.cart.filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id))); rebuildOrderSummary(checkoutCart); } });
} catch(_){}

setInterval(()=>{
  if(document.hidden) return;
  fetch('getcart.php',{credentials:'same-origin'}).then(r=>r.json()).then(data=>{
    if(data.success&&Array.isArray(data.cart)){
      const sig=a=>a.map(i=>i.inv_id+':'+i.qty).join(',');
      const selectedCart = data.cart.filter(item => CHECKOUT_SELECTED_IDS.has(String(item.inv_id)));
      if(sig(selectedCart)!==sig(checkoutCart)){ checkoutCart=selectedCart; rebuildOrderSummary(checkoutCart); }
    }
  }).catch(()=>{});
},5000);

// ── Logout modal ──────────────────────────────────────────────
function openLogoutModal()  { const o=document.getElementById('logoutModalOverlay'); if(o){o.classList.add('active');document.body.style.overflow='hidden';} }
function closeLogoutModal() { const o=document.getElementById('logoutModalOverlay'); if(o){o.classList.remove('active');document.body.style.overflow='';} }
function performLogout()    { const b=document.querySelector('.logout-confirm-btn'); if(b){b.disabled=true;b.textContent='Logging out...';} window.location.href='logout.php'; }
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLogoutModal(); });
document.getElementById('logoutModalOverlay')?.addEventListener('click',e=>{ if(e.target.id==='logoutModalOverlay') closeLogoutModal(); });
</script>

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

<style>
.logout-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10000;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.logout-modal-overlay.active{display:flex;}
.logout-modal{background:#fff;border-radius:20px;padding:32px 28px;width:min(420px,calc(100vw - 32px));box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;animation:slideDown .3s ease-out;}
@keyframes slideDown{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.logout-modal h2{font-family:'Playfair Display',serif;color:var(--deep);font-size:1.3rem;margin:0 0 12px;font-weight:700;}
.logout-modal p{color:#666;font-size:.95rem;margin:0 0 24px;line-height:1.5;}
body.dark .logout-modal{background:#1f2937;}
body.dark .logout-modal h2{color:#a8d4a8;}
body.dark .logout-modal p{color:#cbd5e1;}
body.dark .logout-cancel-btn{background:#2d3748;color:#cbd5e1;}
body.dark .logout-cancel-btn:hover{background:#374151;}
.logout-modal-buttons{display:flex;gap:12px;justify-content:center;}
.logout-modal-buttons button{padding:12px 28px;border-radius:50px;border:none;font-weight:600;font-size:.9rem;cursor:pointer;transition:.2s;font-family:var(--ui-font);}
.logout-cancel-btn{background:#f0ece4;color:#555;}
.logout-cancel-btn:hover{background:#e2ddd4;}
.logout-confirm-btn{background:var(--green);color:#fff;min-width:120px;}
.logout-confirm-btn:hover{background:var(--deep);}
.logout-confirm-btn:active{transform:scale(.98);}
</style>
</body>
</html>
