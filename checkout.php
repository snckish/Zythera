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

// ── Load cart from DB ─────────────────────────────────────────
// Always reload cart from session to avoid stale data from cart sidebar changes
session_write_close();
session_start();

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
  'Quezon','Nueva Ecija','Cebu','Davao del Sur','Iloilo','Bohol','Pangasinan',
  'Bicol Region','Negros Occidental','Negros Oriental','Leyte','Samar','Zamboanga del Sur',
  'Misamis Oriental','Bukidnon','Cagayan','Isabela','Benguet','Mountain Province',
  'Tarlac','Zambales','Bataan','Aurora','Quezon City (Standalone)'
];

$provinceCities = [
  'Metro Manila' => ['Manila','Quezon City','Makati','Pasig','Taguig','Parañaque','Caloocan','Las Piñas','Mandaluyong','Marikina','Muntinlupa','Navotas','Pasay','Pateros','San Juan','Valenzuela'],
  'Cavite'      => ['Cavite City','Bacoor','Imus','Dasmariñas','General Trias','Tagaytay','Trece Martires','Silang'],
  'Laguna'      => ['Calamba','Biñan','San Pedro','Santa Rosa','Cabuyao','Bay','Los Baños','Pagsanjan'],
  'Batangas'    => ['Batangas City','Lipa','Tanauan','Nasugbu','Rosario'],
  'Bulacan'     => ['Malolos','Meycauayan','San Jose del Monte','Marilao','Bocaue'],
  'Pampanga'    => ['San Fernando','Angeles','Mabalacat','Guagua'],
  'Rizal'       => ['Antipolo','Cainta','Taytay','Binangonan','Angono'],
  'Quezon'      => ['Lucena','Tayabas','Candelaria','Sariaya'],
  'Nueva Ecija' => ['Cabanatuan','San Jose','Gapan','Muñoz','Palayan'],
  'Cebu'        => ['Cebu City','Mandaue','Lapu-Lapu','Talisay','Danao','Carcar'],
  'Davao del Sur'=> ['Davao City','Digos'],
  'Iloilo'      => ['Iloilo City','Passi','Pototan'],
  'Bohol'       => ['Tagbilaran'],
  'Pangasinan'  => ['Dagupan','Urdaneta','San Carlos','Lingayen','Alaminos'],
  'Bicol Region' => ['Daet','Vinzons','Labo','Talisay','Jose Panganiban','Mercedes','Capalonga','Naga','Iriga','Libmanan','Pili','Goa','Sipocot','Legazpi','Ligao','Tabaco','Polangui','Daraga','Sorsogon City','Bulan','Irosin','Masbate City','Cataingan','Virac'],
  'Negros Occidental'=> ['Bacolod','Bago','Cadiz','La Carlota','Sagay','Sipalay','Talisay','Victorias'],
  'Negros Oriental' => ['Dumaguete','Bais','Canlaon','Guihulngan','Tanjay'],
  'Leyte'           => ['Tacloban','Ormoc','Baybay','Palo'],
  'Samar'           => ['Catbalogan','Calbayog'],
  'Zamboanga del Sur'=> ['Zamboanga City','Pagadian','Dipolog'],
  'Misamis Oriental'=> ['Cagayan de Oro','Gingoog','El Salvador'],
  'Bukidnon'        => ['Malaybalay','Valencia'],
  'Cagayan'         => ['Tuguegarao','Aparri','Cauayan'],
  'Isabela'         => ['Ilagan','Cauayan','Santiago'],
  'Benguet'         => ['Baguio','La Trinidad','Itogon'],
  'Mountain Province'=> ['Bontoc'],
  'Tarlac'          => ['Tarlac City','Capas','Paniqui'],
  'Zambales'        => ['Olongapo','Iba','San Antonio'],
  'Bataan'          => ['Balanga','Mariveles','Orani'],
  'Aurora'          => ['Baler'],
];

$cityZipCodes = [
  'Manila' => '1000', 'Quezon City' => '1100', 'Makati' => '1200', 'Pasig' => '1600',
  'Taguig' => '1630', 'Parañaque' => '1700', 'Caloocan' => '1400', 'Las Piñas' => '1740',
  'Mandaluyong' => '1550', 'Marikina' => '1800', 'Muntinlupa' => '1770', 'Navotas' => '1485',
  'Pasay' => '1300', 'Pateros' => '1620', 'San Juan' => '1500', 'Valenzuela' => '1440',
  'Cavite City' => '4100', 'Bacoor' => '4102', 'Imus' => '4103',
  'Dasmariñas' => '4114', 'General Trias' => '4107', 'Tagaytay' => '4120', 'Trece Martires' => '4109', 'Silang' => '4118',
  'Santa Rosa' => '4026', 'San Pedro' => '4023', 'Biñan' => '4024', 'Calamba' => '4027',
  'Cabuyao' => '4025', 'Bay' => '4033', 'Los Baños' => '4030', 'Pagsanjan' => '4008',
  'Batangas City' => '4200', 'Lipa' => '4217', 'Tanauan' => '4232', 'Nasugbu' => '4211', 'Rosario' => '4225',
  'Malolos' => '3000', 'Meycauayan' => '3020', 'San Jose del Monte' => '3023', 'Marilao' => '3019', 'Bocaue' => '3018',
  'San Fernando' => '2000', 'Angeles' => '2009', 'Mabalacat' => '2010', 'Guagua' => '2003',
  'Antipolo' => '1870', 'Cainta' => '1900', 'Taytay' => '1920', 'Binangonan' => '1940', 'Angono' => '1930',
  'Lucena' => '4301', 'Tayabas' => '4327', 'Candelaria' => '4323', 'Sariaya' => '4322',
  'Cabanatuan' => '3100', 'San Jose' => '3121', 'Gapan' => '3105', 'Muñoz' => '3119', 'Palayan' => '3132',
  'Tuguegarao' => '3500', 'Aparri' => '3515', 'Cauayan' => '3305',
  'Cebu City' => '6000', 'Mandaue' => '6014', 'Lapu-Lapu' => '6015', 'Talisay' => '6045', 'Danao' => '6004', 'Carcar' => '6019',
  'Davao City' => '8000', 'Digos' => '8002',
  'Iloilo City' => '5000', 'Passi' => '5037', 'Pototan' => '5012',
  'Bacolod' => '6100', 'Bago' => '6101', 'Cadiz' => '6121', 'La Carlota' => '6130', 'Sagay' => '6122', 'Sipalay' => '6113',
  'Tagbilaran' => '6300',
  'Dagupan' => '2400', 'Urdaneta' => '2428', 'San Carlos' => '2420', 'Lingayen' => '2401', 'Alaminos' => '2404',
  // Bicol Region
  'Daet' => '4600', 'Vinzons' => '4601', 'Labo' => '4603', 'Talisay' => '4602',
  'Jose Panganiban' => '4606', 'Mercedes' => '4604', 'Capalonga' => '4607',
  'Naga' => '4400', 'Iriga' => '4431', 'Libmanan' => '4407', 'Pili' => '4418', 'Goa' => '4422', 'Sipocot' => '4408',
  'Legazpi' => '4500', 'Ligao' => '4504', 'Tabaco' => '4511', 'Polangui' => '4506', 'Daraga' => '4501',
  'Sorsogon City' => '4700', 'Bulan' => '4706', 'Irosin' => '4707',
  'Masbate City' => '5400', 'Cataingan' => '5405',
  'Virac' => '4800',
  // Others
  'Tacloban' => '6500', 'Ormoc' => '6541', 'Baybay' => '6521', 'Palo' => '6501',
  'Catbalogan' => '6700', 'Calbayog' => '6710',
  'Zamboanga City' => '7000', 'Pagadian' => '7016', 'Dipolog' => '7100',
  'Cagayan de Oro' => '9000', 'Gingoog' => '9014', 'El Salvador' => '9017',
  'Malaybalay' => '8700', 'Valencia' => '8709',
  'Ilagan' => '3300', 'Santiago' => '3311',
  'Baguio' => '2600', 'La Trinidad' => '2601', 'Itogon' => '2604',
  'Bontoc' => '2616',
  'Tarlac City' => '2300', 'Capas' => '2315', 'Paniqui' => '2307',
  'Olongapo' => '2200', 'Iba' => '2201', 'San Antonio' => '2212',
  'Balanga' => '2100', 'Mariveles' => '2105', 'Orani' => '2112',
  'Baler' => '3200',
  'Dumaguete' => '6200', 'Bais' => '6206', 'Canlaon' => '6208', 'Guihulngan' => '6214', 'Tanjay' => '6204',
];

$cities = array_values(array_unique(array_merge(...array_values($provinceCities))));

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
            // Generate unique order ID using the custom ID system
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
                    'city'      => $city,
                    'province'  => $province,
                    'zip'       => $zip,
                    'notes'     => $notes,
                ],
                'items'         => $cart,
            ];

            $db->beginTransaction();

            // 1. Resolve/create address and payment records
            $userId    = (string)$dbUser->user_id;
            $addressId = findOrCreateAddress($userId, $phone, $address, $city, $province, $zip);
            $paymentId = createPayment($payMethod, 'pending');

            // 2. Insert order (normalized schema)
            $oStmt = $db->prepare("
                INSERT INTO orders
                (order_id, user_id, address_id, payment_id, total_ammount, shipping_fee, user_note, order_date, order_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $oStmt->execute([
                $orderId, $userId, $addressId, $paymentId, $total, $shipping, $notes, 'Pending'
            ]);
            $dbOrdNo = $orderId;   // The custom OR-ZY### id IS the primary key

            // 3. Insert order items with correct schema columns
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

            // 4. Deduct inventory safely
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

            // 5. Clear cart
            clearCartForUser($userEmail);

            $db->commit();

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
  font-family: var(--ui-font);font-size:.92rem;
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
  font-family: var(--ui-font);font-size:.9rem;
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
          <input type="text" id="zip" name="zip" placeholder=" " required maxlength="4" readonly
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
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Account Name: <strong><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> FURNITURE</strong></div>
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
            <div style="font-size:.72rem;color:#aaa;margin-top:4px;">Account Name: <strong><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span> FURNITURE</strong></div>
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
  <span class="footer-brand"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Payment panel toggle ──────────────────────────────────────
const PAY_GROUPS = ['gcash','maya','bank'];

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

  if (!document.getElementById('province')?.value) errs.push('Please select a province.');
  if (!document.getElementById('city')?.value) errs.push('Please select a city.');

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

function filterCities() {
  const province = document.getElementById('province')?.value || '';
  const citySelect = document.getElementById('city');
  if (!citySelect) return;

  const provinceCities = <?php echo json_encode($provinceCities, JSON_UNESCAPED_UNICODE); ?>;
  const allCities = <?php echo json_encode($cities, JSON_UNESCAPED_UNICODE); ?>;
  const selectedCity = citySelect.value;
  const cities = provinceCities[province] && provinceCities[province].length > 0 ? provinceCities[province] : allCities;

  citySelect.innerHTML = '<option value=""> Select City </option>';
  cities.forEach((city) => {
    const opt = document.createElement('option');
    opt.value = city;
    opt.textContent = city;
    if (city === selectedCity) opt.selected = true;
    citySelect.appendChild(opt);
  });

  if (selectedCity && !cities.includes(selectedCity)) {
    citySelect.value = '';
  }

  updateZipCode();
}

function updateZipCode() {
  const city = document.getElementById('city')?.value || '';
  const zipInput = document.getElementById('zip');
  if (!zipInput) return;

  const cityZipCodes = <?php echo json_encode($cityZipCodes, JSON_UNESCAPED_UNICODE); ?>;
  zipInput.value = cityZipCodes[city] || '';
}

document.addEventListener('DOMContentLoaded', function() {
  filterCities();
  document.getElementById('city')?.addEventListener('change', updateZipCode);
});

// ── Logout modal functions ──
function openLogoutModal() {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeLogoutModal(event) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function performLogout() {
  const confirmBtn = document.querySelector('.logout-confirm-btn');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Logging out...';
  }
  window.location.href = 'logout.php';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutModal();
  }
});

// Close modal when clicking overlay
const logoutOverlay = document.getElementById('logoutModalOverlay');
if (logoutOverlay) {
  logoutOverlay.addEventListener('click', function(e) {
    if (e.target.id === 'logoutModalOverlay') {
      closeLogoutModal();
    }
  });
}
</script>

<script>
// ── Cart ↔ Checkout live synchronisation ──────────────────────
// Seed initial cart from PHP (authoritative at page render time)
let checkoutCart = <?= json_encode(array_values(array_map(function($i){
    return [
        'inv_id' => (string)($i['inv_id'] ?? ''),
        'name'   => $i['name']  ?? '',
        'price'  => (float)($i['price'] ?? 0),
        'qty'    => (int)($i['qty']   ?? 1),
        'image'  => $i['image'] ?? '',
    ];
}, $cart))) ?>;

const SHIPPING_FEE = 150;

/**
 * Re-render the Order Summary panel from checkoutCart.
 * If the cart becomes empty, redirect back to the shop.
 */
function rebuildOrderSummary(cart) {
  const container = document.querySelector('.checkout-card div[style*="overflow-y:auto"]');
  const subtotalEl = document.querySelector('.order-total-row:nth-child(1) span:last-child');
  const shippingEl = document.querySelector('.order-total-row:nth-child(2) span:last-child');
  const totalEl    = document.querySelector('.order-total-row.grand span:last-child');

  if (!container) return;

  // If cart emptied, bounce back to shop
  if (!cart || cart.length === 0) {
    window.location.href = 'website.php';
    return;
  }

  // Rebuild items HTML
  let html = '';
  let subtotal = 0;
  cart.forEach(item => {
    const qty   = Number(item.qty)   || 1;
    const price = Number(item.price) || 0;
    const line  = price * qty;
    subtotal   += line;
    const imgSrc = item.image
      ? item.image
      : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';
    html += `
      <div class="order-item">
        <img src="${escapeHtml(imgSrc)}" alt=""
          onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:.85rem;color:var(--deep);
            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            ${escapeHtml(item.name || '')}
          </div>
          <div style="font-size:.76rem;color:#999;">
            ₱${numFmt(price)} × ${qty}
          </div>
        </div>
        <div style="font-weight:700;color:var(--green);font-size:.88rem;white-space:nowrap;">
          ₱${numFmt(line)}
        </div>
      </div>`;
  });
  container.innerHTML = html;

  const shipping = subtotal > 0 ? SHIPPING_FEE : 0;
  const total    = subtotal + shipping;

  if (subtotalEl) subtotalEl.textContent = '₱' + numFmt(subtotal);
  if (shippingEl) shippingEl.textContent = '₱' + numFmt(shipping);
  if (totalEl)    totalEl.textContent    = '₱' + numFmt(total);
}

function numFmt(n) {
  return Number(n).toLocaleString('en-PH', {minimumFractionDigits:0, maximumFractionDigits:0});
}

function escapeHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── BroadcastChannel: listen for cart changes from website.php ─
try {
  const bc = new BroadcastChannel('zythera_cart');
  bc.addEventListener('message', e => {
    if (e.data && e.data.type === 'cart_updated' && Array.isArray(e.data.cart)) {
      checkoutCart = e.data.cart;
      rebuildOrderSummary(checkoutCart);
    }
  });
} catch(_) { /* BroadcastChannel not supported */ }

// ── Polling fallback: re-fetch cart every 5 s while tab is visible
// This covers the case where the user edits the cart in the same tab
// (website.php embedded iframe or navigates back).
let _pollTimer = null;
function startCartPoll() {
  if (_pollTimer) return;
  _pollTimer = setInterval(() => {
    if (document.hidden) return;
    fetch('getcart.php', { credentials: 'same-origin' })
      .then(r => r.json())
      .then(data => {
        if (data.success && Array.isArray(data.cart)) {
          // Only re-render if something actually changed
          const sig = a => a.map(i => i.inv_id + ':' + i.qty).join(',');
          if (sig(data.cart) !== sig(checkoutCart)) {
            checkoutCart = data.cart;
            rebuildOrderSummary(checkoutCart);
          }
        }
      })
      .catch(() => {});
  }, 5000);
}
startCartPoll();
</script>
<!-- Logout Confirmation Modal -->
<div id="logoutModalOverlay" class="logout-modal-overlay">
    <div class="logout-modal">
        <h2>Log Out Confirmation</h2>
        <p>Are you sure you want to log out of your account?</p>
        <div class="logout-modal-buttons">
            <button type="button" class="logout-cancel-btn" onclick="closeLogoutModal(event)">
                Stay
            </button>
            <button type="button" class="logout-confirm-btn" onclick="performLogout()">
                Logout
            </button>
        </div>
    </div>
</div>

<style>
    .logout-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.6);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px);
    }
    .logout-modal-overlay.active { display: flex; }

    .logout-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px;
      width: min(420px, calc(100vw - 32px));
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      text-align: center;
      animation: slideDown .3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logout-modal h2 {
      font-family: 'Playfair Display', serif;
      color: var(--deep);
      font-size: 1.3rem;
      margin: 0 0 12px 0;
      font-weight: 700;
    }

    .logout-modal p {
      color: #666;
      font-size: .95rem;
      margin: 0 0 24px 0;
      line-height: 1.5;
    }

    body.dark .logout-modal {
      background: #1f2937;
    }
    body.dark .logout-modal h2 {
      color: #a8d4a8;
    }
    body.dark .logout-modal p {
      color: #cbd5e1;
    }
    body.dark .logout-cancel-btn {
      background: #2d3748;
      color: #cbd5e1;
    }
    body.dark .logout-cancel-btn:hover {
      background: #374151;
    }

    .logout-modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .logout-modal-buttons button {
      padding: 12px 28px;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      font-size: .9rem;
      cursor: pointer;
      transition: .2s ease;
      font-family: var(--ui-font);
    }

    .logout-cancel-btn {
      background: #f0ece4;
      color: #555;
    }
    .logout-cancel-btn:hover {
      background: #e2ddd4;
    }

    .logout-confirm-btn {
      background: var(--green);
      color: #fff;
      min-width: 120px;
    }
    .logout-confirm-btn:hover {
      background: var(--deep);
    }
    .logout-confirm-btn:active {
      transform: scale(0.98);
    }
</style>
</body>
</html>