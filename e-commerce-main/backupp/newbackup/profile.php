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

$user     = &$_SESSION['users'][$userEmail];
$userRole = $_SESSION['role'] ?? 'user';

if (!isset($_SESSION['profile_pic'][$userEmail])) {
    $_SESSION['profile_pic'][$userEmail] = $user['profile_pic'] ?? null;
}

if ($userRole !== 'admin') {
    if (!isset($_SESSION['cart'][$userEmail]))   $_SESSION['cart'][$userEmail]   = [];
    if (!isset($_SESSION['orders'][$userEmail])) $_SESSION['orders'][$userEmail] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Update profile ──────────────────────────────────────────
    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        if ($newName) $user['name'] = htmlspecialchars($newName);
        if ($newPass && strlen($newPass) >= 6)
            $user['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        saveUsers($_SESSION['users']); // ← persist to file
        header('Location: profile.php?updated=1');
        exit;
    }

    // ── Update cart quantity (+/-/remove) — AJAX-friendly ──────
    if (isset($_POST['update_qty']) && $userRole !== 'admin') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $action = $_POST['qty_action'] ?? '';
        $cart   = &$_SESSION['cart'][$userEmail];

        $invStock = [];
        foreach ($_SESSION['inventory'] ?? [] as $inv) {
            $invStock[(int)$inv->id] = (int)$inv->stock;
        }

        foreach ($cart as $k => &$ci) {
            if ((int)$ci['id'] === $itemId) {
                $maxStock = $invStock[$itemId] ?? 9999;
                if ($action === 'plus')   $ci['qty'] = min($maxStock, (int)$ci['qty'] + 1);
                if ($action === 'minus')  $ci['qty'] = max(1, (int)$ci['qty'] - 1);
                if ($action === 'remove') unset($cart[$k]);
                break;
            }
        }
        unset($ci);
        $cart = array_values($cart);

        // Persist cart changes to file
        $allCarts = loadCarts();
        $allCarts[$userEmail] = $cart;
        saveCarts($allCarts);

        // AJAX response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            $totalQty = 0;
            foreach ($cart as $c) $totalQty += (int)($c['qty'] ?? 1);
            echo json_encode(['success' => true, 'cart' => array_values($cart), 'total_items' => $totalQty]);
            exit;
        }
        header('Location: profile.php');
        exit;
    }

    // ── Upload profile photo ──────────────────────────────────────
    if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic'])) {
        $f = $_FILES['profile_pic'];
        if ($f['error'] === 0 && $f['size'] <= 5 * 1024 * 1024) {
            $ext     = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $mimeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
                            'png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
                $b64     = base64_encode(file_get_contents($f['tmp_name']));
                $picData = 'data:' . ($mimeMap[$ext] ?? 'image/jpeg') . ';base64,' . $b64;
                // Store in session (fast access) AND persist to users.json
                $_SESSION['profile_pic'][$userEmail] = $picData;
                $user['profile_pic'] = $picData;
                saveUsers($_SESSION['users']);
            }
        }
        header('Location: profile.php');
        exit;
    }

    // ── Remove profile photo ────────────────────────────────────
    if (isset($_POST['remove_pic'])) {
        $_SESSION['profile_pic'][$userEmail] = null;
        $user['profile_pic'] = null;
        saveUsers($_SESSION['users']);
        header('Location: profile.php');
        exit;
    }
}

// ── Data for rendering ──────────────────────────────────────────
$cart   = ($userRole !== 'admin') ? ($_SESSION['cart'][$userEmail] ?? []) : [];
// Always load orders fresh from file
$orders = ($userRole !== 'admin') ? (loadOrders()[$userEmail] ?? []) : [];
$pic    = $_SESSION['profile_pic'][$userEmail];

// Stock map from current inventory
$stockMap = [];
foreach ($_SESSION['inventory'] ?? [] as $inv) {
    $stockMap[(int)$inv->id] = (int)$inv->stock;
}

// Total qty in cart (for navbar badge)
$cartTotalQty = 0;
foreach ($cart as $ci) $cartTotalQty += (int)($ci['qty'] ?? 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAFIRAH | MY PROFILE</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #2d5a2d; --sage: #d4e4d4; --cream: #f5f2ec;
            --deep: #1a2e1a;  --mid: #7aab7a;  --terra: #bc8a7b;
        }
        * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }
        body { background: var(--cream); display: flex; flex-direction: column; min-height: 100vh; margin: 0; }
        .page-wrapper { flex: 1; }

        /* Navbar */
        .navbar { background: #fff; box-shadow: 0 1px 12px rgba(0,0,0,.07); }
        .navbar-brand { font-family:'Playfair Display',serif; color:var(--green)!important; letter-spacing:4px; font-size:1.5rem; }

        /* Cards */
        .profile-card { border:none; border-radius:20px; box-shadow:0 6px 28px rgba(0,0,0,.08); margin-bottom:22px; overflow:hidden; }
        .profile-header {
            background: linear-gradient(135deg, var(--deep), var(--green));
            color:#fff; padding:36px; text-align:center;
        }
        .section-card { background:#fff; border-radius:16px; padding:24px; margin-bottom:20px; box-shadow:0 2px 12px rgba(0,0,0,.05); }
        .section-title { font-family:'Playfair Display',serif; color:var(--deep); font-size:1.05rem; margin-bottom:18px; display:flex; align-items:center; gap:8px; }

        /* Avatar */
        .avatar-ring {
            width:100px; height:100px; border-radius:50%;
            background:rgba(255,255,255,.15); border:3px solid rgba(255,255,255,.5);
            margin:0 auto 14px; overflow:hidden;
            display:flex; align-items:center; justify-content:center;
            font-size:2.4rem; font-weight:700; color:#fff;
            cursor:pointer; position:relative;
        }
        .avatar-ring img { width:100%; height:100%; object-fit:cover; }
        .avatar-overlay {
            position:absolute; inset:0; background:rgba(0,0,0,.42);
            display:flex; align-items:center; justify-content:center;
            opacity:0; transition:.2s; border-radius:50%;
        }
        .avatar-ring:hover .avatar-overlay { opacity:1; }

        .badge-role {
            display:inline-block; padding:4px 14px; border-radius:20px;
            font-size:.72rem; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; margin-top:6px;
        }
        .badge-user  { background:rgba(255,255,255,.2); color:#fff; }
        .badge-admin { background:#fee2e2; color:#b91c1c; }

        /* Form */
        .form-control, .form-select {
            background:var(--sage); border:2px solid transparent; border-radius:12px;
            padding:.75rem 1rem; color:var(--deep); transition:.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color:var(--green); background:#fff; box-shadow:none; color:var(--deep);
        }
        .btn-green {
            background:var(--green); color:#fff; border:none; border-radius:50px;
            padding:.65rem 1.8rem; font-weight:600; transition:.2s; text-decoration:none; display:inline-block;
        }
        .btn-green:hover { background:var(--deep); color:#fff; }

        /* Cart item */
        .cart-item {
            display:flex; align-items:center; gap:12px;
            padding:12px 14px; background:var(--cream); border-radius:12px; margin-bottom:8px; transition:.15s;
        }
        .cart-item:hover { background:#ede9e0; }
        .cart-thumb { width:52px; height:52px; flex-shrink:0; object-fit:cover; border-radius:10px; background:var(--sage); }

        /* Qty stepper */
        .qty-stepper { display:inline-flex; align-items:center; border:2px solid var(--sage); border-radius:10px; overflow:hidden; flex-shrink:0; }
        .qty-stepper button {
            width:30px; height:30px; border:none; background:var(--sage);
            color:var(--green); font-weight:700; font-size:1rem; cursor:pointer; transition:.15s; line-height:1;
        }
        .qty-stepper button:hover { background:var(--mid); color:#fff; }
        .qty-stepper button:disabled { opacity:.3; cursor:not-allowed; }
        .qty-stepper .qty-val { width:34px; text-align:center; font-weight:700; font-size:.9rem; color:var(--deep); background:#fff; }

        /* Stock chips */
        .stock-chip { display:inline-block; font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:20px; letter-spacing:.5px; }
        .sc-ok  { background:#dcfce7; color:#16a34a; }
        .sc-low { background:#fef9c3; color:#b45309; }
        .sc-out { background:#fee2e2; color:#b91c1c; }

        /* Totals */
        .totals-box { background:var(--cream); border-radius:14px; padding:14px 18px; margin-top:14px; }
        .totals-row { display:flex; justify-content:space-between; font-size:.85rem; color:#777; padding:3px 0; }
        .totals-row.grand { font-size:1rem; font-weight:800; color:var(--green); border-top:2px solid var(--sage); padding-top:10px; margin-top:6px; }

        /* Order history */
        .order-box { border:2px solid var(--sage); border-radius:14px; padding:16px; margin-bottom:14px; transition:.2s; }
        .order-box:hover { border-color:var(--mid); }
        .order-status { display:inline-block; font-size:.68rem; font-weight:700; padding:4px 12px; border-radius:20px; letter-spacing:.5px; text-transform:uppercase; }
        .st-pending    { background:#fef9c3; color:#b45309; }
        .st-processing { background:#dbeafe; color:#1d4ed8; }
        .st-shipped    { background:#e0f2fe; color:#0369a1; }
        .st-completed,
        .st-delivered  { background:#dcfce7; color:#16a34a; }
        .st-cancelled  { background:#fee2e2; color:#b91c1c; }

        /* Empty states */
        .empty-state { text-align:center; padding:36px 20px; color:#bbb; }
        .empty-state i { font-size:2.5rem; margin-bottom:12px; display:block; }
        .empty-state p { font-size:.88rem; margin:0; }

        /* Toast */
        .p-toast {
            position:fixed; bottom:28px; right:28px;
            background:var(--deep); color:#fff;
            padding:12px 22px; border-radius:50px;
            font-size:.85rem; font-weight:600;
            opacity:0; transform:translateY(12px);
            transition:.3s; pointer-events:none; z-index:9999;
        }
        .p-toast.show { opacity:1; transform:translateY(0); }
        .p-toast.err  { background:#b91c1c; }

        /* Alert banner */
        .alert-banner { padding:12px 20px; font-size:.88rem; font-weight:600; text-align:center; }
        .alert-banner.success { background:#dcfce7; color:#166534; }

        footer { background:#f5f2ec; padding:22px 20px; display:flex; align-items:center; justify-content:center; gap:12px; border-top:1px solid #e8e4dc; }
        footer .footer-brand { font-family:'Playfair Display',serif; color:var(--green); font-size:1rem; letter-spacing:4px; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-light px-4 py-2 fixed-top">
    <a class="navbar-brand fw-bold" href="website.php">ZAFIRAH</a>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <?php if ($userRole !== 'admin'): ?>
        <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">
           Shop
        </a>
        <a href="checkout.php" class="btn btn-sm rounded-pill fw-semibold position-relative"
           style="background:var(--green);color:#fff;padding:.4rem 1rem;">
            Checkout
            <?php if ($cartTotalQty > 0): ?>
            <span id="navBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"
                  style="font-size:.6rem;"><?= $cartTotalQty ?></span>
            <?php else: ?>
            <span id="navBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"
                  style="font-size:.6rem;display:none;">0</span>
            <?php endif; ?>
        </a>
        <?php else: ?>
        <a href="admin.php" class="btn btn-sm btn-dark rounded-pill">
            Admin Panel
        </a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-sm btn-danger rounded-pill">Logout</a>
    </div>
</nav>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert-banner success" style="margin-top:56px;">
        <i class="fas fa-check-circle me-2"></i>Profile updated successfully.
    </div>
<?php else: ?>
<div style="height:56px;"></div>
<?php endif; ?>

<div class="p-toast" id="pToast"></div>

<div class="page-wrapper">
<div class="container py-4" style="max-width:780px;">

    <!-- ── PROFILE CARD ── -->
    <div class="profile-card">
        <div class="profile-header">
            <!-- Clicking avatar triggers hidden file input -->
            <div class="avatar-ring" onclick="document.getElementById('picInput').click();" title="Click to change photo">
                <?php if ($pic): ?>
                    <img src="<?= htmlspecialchars($pic) ?>" alt="Profile photo">
                <?php else: ?>
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
                <div class="avatar-overlay"><i class="fas fa-camera" style="color:#fff;font-size:1.3rem;"></i></div>
            </div>

            <h4 class="mb-1 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
            <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
            <span class="badge-role <?= $userRole === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                <?= strtoupper($userRole) ?>
            </span>

            <!-- Hidden upload form -->
            <form method="POST" enctype="multipart/form-data" id="picForm" style="display:none;">
                <input type="file" name="profile_pic" id="picInput" accept="image/*"
                       onchange="document.getElementById('picForm').submit();">
                <button name="upload_pic" type="submit"></button>
            </form>

            <?php if ($pic): ?>
            <form method="POST" class="mt-3">
                <button name="remove_pic" type="submit"
                    style="background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
                           color:#fff;border-radius:50px;padding:4px 16px;font-size:.74rem;cursor:pointer;transition:.2s;">
                    <i class="fas fa-trash me-1"></i>Remove Photo
                </button>
            </form>
            <?php endif; ?>
            <p class="mt-2 mb-0 opacity-50" style="font-size:.7rem;">
                <i class="fas fa-camera me-1"></i>Click avatar to change · Max 5 MB
            </p>
        </div>

        <!-- Edit profile form -->
        <div class="p-4">
            <div class="section-title"><i class="fas fa-pen" style="color:var(--dark-green);font-size:.9rem;"></i>Edit Profile</div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold" style="color:var(--green);">Full Name</label>
                    <input class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold" style="color:var(--green);">New Password</label>
                    <div class="position-relative">
                        <input class="form-control" name="password" type="password" id="pwField"
                               placeholder="Min 6 characters" autocomplete="new-password">
                        <button type="button" onclick="togglePw()" tabindex="-1"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;color:var(--green);cursor:pointer;">
                            <i class="fas fa-eye" id="pwEye"></i>
                        </button>
                    </div>
                </div>
                <button name="update_profile" class="btn-green btn w-100">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <?php if ($userRole !== 'admin'): ?>

    <!-- ── CART ── -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-cart-shopping" style="color:var(--dark--green);"></i>
            My Cart
            <span id="cartBadge" class="badge rounded-pill ms-1"
                  style="background:var(--green);color:#fff;font-size:.7rem;padding:4px 9px;">
                <?= $cartTotalQty ?>
            </span>
        </div>
        <div id="cartBody"></div>
        <div id="cartTotals" class="totals-box" style="<?= empty($cart) ? 'display:none;' : '' ?>"></div>
        <div id="cartActions" class="d-flex gap-2 mt-3 flex-wrap" style="<?= empty($cart) ? 'display:none!important;' : '' ?>">
            <a href="checkout.php" class="btn-green btn flex-fill" style="text-align:center;padding:.75rem;">
                Proceed to Checkout
            </a>
            <a href="website.php" class="btn btn-outline-secondary rounded-pill" style="padding:.75rem 1.4rem;font-weight:600;">
                <i class="fas fa-plus me-1"></i>Add More
            </a>
        </div>
    </div>

    <!-- ── ORDER HISTORY ── -->
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-receipt" style="color:var(--dark--green);"></i>
            Order History
            <span class="badge rounded-pill ms-1"
                  style="background:var(--mid);color:#fff;font-size:.7rem;padding:4px 9px;">
                <?= count($orders) ?>
            </span>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No orders placed yet.</p>
                <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill mt-2">Browse Products</a>
            </div>
        <?php else: ?>
            <?php foreach (array_reverse($orders) as $o): ?>
                <?php
                $oStatus  = $o['status'] ?? 'Pending';
                $stCls    = match(strtolower($oStatus)) {
                    'delivered', 'completed' => 'st-delivered',
                    'cancelled'              => 'st-cancelled',
                    'shipped'                => 'st-shipped',
                    'processing'             => 'st-processing',
                    default                  => 'st-pending'
                };
                $oSub     = (float)($o['subtotal'] ?? 0);
                $oShip    = is_numeric($o['shipping'] ?? null) ? (float)$o['shipping'] : 150;
                $oTotal   = (float)($o['total'] ?? ($oSub + $oShip));
                ?>
                <div class="order-box">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <div class="fw-bold" style="color:var(--deep);font-size:.9rem;">
                                <i class="fas fa-tag me-1" style="color:var(--mid);"></i>
                                #<?= htmlspecialchars($o['order_id'] ?? '—') ?>
                            </div>
                            <div class="text-muted" style="font-size:.74rem;margin-top:3px;">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('F d, Y — h:i A', strtotime($o['date'] ?? 'now')) ?>
                            </div>
                        </div>
                        <span class="order-status <?= $stCls ?>"><?= htmlspecialchars($oStatus) ?></span>
                    </div>

                    <!-- Items -->
                    <div style="background:var(--cream);border-radius:10px;padding:10px 14px;margin-bottom:12px;">
                        <?php foreach ($o['items'] as $oi): ?>
                            <?php
                            $oiName  = is_array($oi) ? ($oi['name']  ?? '?') : $oi;
                            $oiQty   = is_array($oi) ? (int)($oi['qty']   ?? 1) : 1;
                            $oiPrice = is_array($oi) ? (float)($oi['price'] ?? 0) : 0;
                            $oiImg   = is_array($oi) ? ($oi['image'] ?? '') : '';
                            $oiId    = is_array($oi) ? (int)($oi['id'] ?? 0) : 0;
                            $oiLine  = $oiPrice * $oiQty;
                            $curStock = $stockMap[$oiId] ?? null;
                            ?>
                            <div class="d-flex align-items-center gap-2 py-2"
                                 style="border-bottom:1px dashed #e8e4dc;">
                                <?php if ($oiImg): ?>
                                <img src="<?= htmlspecialchars($oiImg) ?>"
                                     style="width:38px;height:38px;object-fit:cover;border-radius:8px;flex-shrink:0;background:var(--sage);"
                                     onerror="this.style.display='none'" alt="">
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:.85rem;font-weight:600;color:var(--deep);
                                                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($oiName) ?>
                                        <span style="color:var(--mid);font-weight:700;"> ×<?= $oiQty ?></span>
                                    </div>
                                    <?php if ($curStock !== null): ?>
                                    <div style="margin-top:3px;">
                                        <?php if ($curStock === 0): ?>
                                            <span class="stock-chip sc-out">Out of Stock</span>
                                        <?php elseif ($curStock <= 5): ?>
                                            <span class="stock-chip sc-low">Low Stock: <?= $curStock ?> left</span>
                                        <?php else: ?>
                                            <span class="stock-chip sc-ok">In Stock: <?= $curStock ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($oiPrice > 0): ?>
                                <span style="font-weight:700;color:var(--green);white-space:nowrap;font-size:.88rem;">
                                    ₱<?= number_format($oiLine, 2) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Breakdown -->
                        <div style="margin-top:10px;">
                            <div class="d-flex justify-content-between" style="font-size:.8rem;color:#888;padding:2px 0;">
                                <span>Subtotal</span><span>₱<?= number_format($oSub, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between" style="font-size:.8rem;color:#888;padding:2px 0;">
                                <span><i class="fas fa-truck me-1"></i>Shipping</span>
                                <span>₱<?= number_format($oShip, 2) ?></span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold"
                                 style="font-size:.9rem;color:var(--green);
                                        border-top:2px solid var(--sage);padding-top:8px;margin-top:6px;">
                                <span>Total Paid</span>
                                <span>₱<?= number_format($oTotal, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery & payment info -->
                    <div class="d-flex flex-wrap gap-3" style="font-size:.76rem;color:#888;">
                        <?php if (!empty($o['shipping_address'])): ?>
                        <span>
                            <i class="fas fa-map-marker-alt me-1" style="color:var(--terra);"></i>
                            <?= htmlspecialchars($o['shipping_address']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($o['pay_method'])): ?>
                        <span>
                            <i class="fas fa-credit-card me-1" style="color:var(--mid);"></i>
                            <?= htmlspecialchars($o['pay_method']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>
</div><!-- /page-wrapper -->

<footer>
    <img src="pci/Group_15.svg" style="width:28px;" alt="Zafirah logo">
    <span class="footer-brand">ZAFIRAH</span>
</footer>

<script>
// ── Initial cart data & stock map from PHP ────────────────────
let cartItems = <?= json_encode(array_values($cart), JSON_HEX_APOS | JSON_HEX_TAG) ?>;
const stockMap = <?= json_encode($stockMap) ?>;

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function stockChip(id) {
    if (stockMap[id] === undefined) return '';
    const s = stockMap[id];
    if (s === 0)  return '<span class="stock-chip sc-out">Out of Stock</span>';
    if (s <= 5)   return '<span class="stock-chip sc-low">Low: ' + s + ' left</span>';
    return '<span class="stock-chip sc-ok">In Stock: ' + s + '</span>';
}

function renderCart() {
    const body    = document.getElementById('cartBody');
    const totals  = document.getElementById('cartTotals');
    const actions = document.getElementById('cartActions');
    const badge   = document.getElementById('cartBadge');
    if (!body) return;

    if (cartItems.length === 0) {
        body.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-couch"></i>
                <p>Your cart is empty.</p>
                <a href="website.php">
                     </a>
            </div>`;
        if (totals)  totals.style.display  = 'none';
        if (actions) actions.style.display = 'none';
        if (badge)   badge.textContent = '0';
        updateNavBadge(0);
        return;
    }

    let html = '';
    let subtotal = 0;
    let totalQty = 0;

    cartItems.forEach(item => {
        const price = parseFloat(item.price) || 0;
        const qty   = parseInt(item.qty) || 1;
        const id    = parseInt(item.id);
        const line  = price * qty;
        const max   = stockMap[id] !== undefined ? stockMap[id] : 9999;
        const oos   = max === 0;
        subtotal += line;
        totalQty += qty;

        html += `
        <div class="cart-item" data-id="${id}">
            <img src="${esc(item.image || '')}" class="cart-thumb"
                 onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'" alt="">
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:.88rem;color:var(--deep);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    ${esc(item.name)}
                </div>
                <div style="font-size:.76rem;color:#888;">₱${price.toLocaleString('en-PH',{minimumFractionDigits:2})} each</div>
                <div style="margin-top:3px;">${stockChip(id)}</div>
            </div>
            <div class="qty-stepper ms-2">
                <button onclick="doQty(${id},'minus')" ${qty <= 1 ? 'disabled' : ''}>−</button>
                <span class="qty-val">${qty}</span>
                <button onclick="doQty(${id},'plus')" ${oos || qty >= max ? 'disabled' : ''}>+</button>
            </div>
            <div style="font-weight:700;color:var(--green);white-space:nowrap;font-size:.9rem;min-width:76px;text-align:right;">
                ₱${line.toLocaleString('en-PH',{minimumFractionDigits:2})}
            </div>
            <button onclick="doQty(${id},'remove')" title="Remove"
                style="background:none;border:none;color:#dc2626;cursor:pointer;padding:4px 6px;margin-left:4px;font-size:.9rem;">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>`;
    });

    body.innerHTML = html;

    const ship  = subtotal > 0 ? 150 : 0;
    const total = subtotal + ship;

    if (totals) {
        totals.style.display = '';
        totals.innerHTML = `
            <div class="totals-row"><span>Subtotal</span><span>₱${subtotal.toLocaleString('en-PH',{minimumFractionDigits:2})}</span></div>
            <div class="totals-row"><span><i class="fas fa-truck me-1"></i>Shipping</span><span>₱${ship.toLocaleString('en-PH',{minimumFractionDigits:2})}</span></div>
            <div class="totals-row grand"><span>Total</span><span>₱${total.toLocaleString('en-PH',{minimumFractionDigits:2})}</span></div>`;
    }
    if (actions) actions.style.display = '';
    if (badge)   badge.textContent = totalQty;
    updateNavBadge(totalQty);
}

function updateNavBadge(count) {
    const b = document.getElementById('navBadge');
    if (!b) return;
    b.textContent = count;
    b.style.display = count > 0 ? '' : 'none';
}

function doQty(itemId, action) {
    if (action === 'plus') {
        const item = cartItems.find(i => parseInt(i.id) === itemId);
        const max  = stockMap[itemId] !== undefined ? stockMap[itemId] : 9999;
        if (item && parseInt(item.qty) >= max) {
            showToast('Maximum stock (' + max + ') already in cart.', true);
            return;
        }
    }

    // Optimistic update
    if (action === 'remove') {
        cartItems = cartItems.filter(i => parseInt(i.id) !== itemId);
    } else {
        const item = cartItems.find(i => parseInt(i.id) === itemId);
        if (item) {
            const max = stockMap[itemId] !== undefined ? stockMap[itemId] : 9999;
            if (action === 'plus')  item.qty = Math.min(max, parseInt(item.qty) + 1);
            if (action === 'minus') item.qty = Math.max(1,   parseInt(item.qty) - 1);
        }
    }
    renderCart();

    // Sync to server (non-blocking)
    fetch('profile.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'update_qty=1&item_id=' + itemId + '&qty_action=' + action
    }).catch(() => showToast('Could not sync. Try refreshing.', true));
}

function showToast(msg, isErr) {
    const t = document.getElementById('pToast');
    t.textContent = msg;
    t.className = 'p-toast show' + (isErr ? ' err' : '');
    setTimeout(() => t.classList.remove('show'), 3200);
}

function togglePw() {
    const f = document.getElementById('pwField');
    const e = document.getElementById('pwEye');
    const hidden = f.type === 'password';
    f.type = hidden ? 'text' : 'password';
    e.className = hidden ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Initial render
renderCart();
</script>

<?php
// ── Order placed flash ────────────────────────────────────────
$flash = null;
if (isset($_GET['order_placed']) && !empty($_SESSION['order_flash'])) {
    $flash = $_SESSION['order_flash'];
    unset($_SESSION['order_flash']); // consume it — only shows once
}
?>

<?php if ($flash): ?>
<?php
  $fItems    = $flash['items']    ?? [];
  $fSub      = (float)($flash['subtotal']  ?? 0);
  $fShip     = (float)($flash['shipping']  ?? 0);
  $fTotal    = (float)($flash['total']     ?? 0);
  $fPay      = $flash['pay_method'] ?? '';
  $fId       = $flash['order_id']   ?? '';
  $fDate     = $flash['date']       ?? '';
  $fInfo     = $flash['shipping_info'] ?? [];
?>
<!-- ── ORDER CONFIRMATION MODAL ── -->
<div id="orderModal" style="
  position:fixed;inset:0;z-index:10000;
  display:flex;align-items:center;justify-content:center;
  background:rgba(26,46,26,.55);backdrop-filter:blur(4px);
  padding:16px;animation:fadeInBg .3s ease;">

  <div style="
    background:#fff;border-radius:24px;width:100%;max-width:560px;
    max-height:90vh;overflow-y:auto;
    box-shadow:0 24px 64px rgba(0,0,0,.25);
    animation:slideUp .4s cubic-bezier(.34,1.56,.64,1);">

    <!-- Hero -->
    <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);border-radius:24px 24px 0 0;
                padding:36px 28px 28px;text-align:center;">
      <div style="width:76px;height:76px;border-radius:50%;background:rgba(255,255,255,.15);
                  border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;
                  justify-content:center;margin:0 auto 16px;animation:popIn .5s .2s both cubic-bezier(.34,1.56,.64,1);">
        <i class="fas fa-check" style="color:#fff;font-size:2rem;"></i>
      </div>
      <h4 style="font-family:'Playfair Display',serif;color:#fff;margin-bottom:6px;font-size:1.6rem;">Order Placed!</h4>
      <p style="color:rgba(255,255,255,.72);font-size:.88rem;margin-bottom:16px;">
        Salamat, <strong style="color:#fff;"><?= htmlspecialchars($user['name'] ?? '') ?></strong>! Confirmed and being processed.
      </p>
      <div style="display:inline-flex;align-items:center;gap:8px;
                  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.28);
                  border-radius:50px;padding:8px 20px;">
        <i class="fas fa-tag" style="color:rgba(255,255,255,.65);font-size:.8rem;"></i>
        <span style="font-weight:800;color:#fff;letter-spacing:2px;font-size:.92rem;"><?= htmlspecialchars($fId) ?></span>
      </div>
      <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-top:12px;">
        <span style="color:rgba(255,255,255,.6);font-size:.74rem;">
          <i class="fas fa-clock me-1"></i><?= date('M d, Y · h:i A', strtotime($fDate)) ?>
        </span>
        <span style="color:rgba(255,255,255,.6);font-size:.74rem;">
          <i class="fas fa-credit-card me-1"></i><?= htmlspecialchars($fPay) ?>
        </span>
      </div>
    </div>

    <!-- Body -->
    <div style="padding:24px 28px;">

      <!-- Items -->
      <p style="font-size:.65rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
                color:#2d5a2d;margin-bottom:12px;">Items Ordered</p>
      <div style="border:2px solid #d4e4d4;border-radius:14px;overflow:hidden;margin-bottom:18px;">
        <?php foreach ($fItems as $idx => $oi):
          $oiName  = htmlspecialchars($oi['name']  ?? '');
          $oiQty   = (int)($oi['qty']   ?? 1);
          $oiPrice = (float)($oi['price'] ?? 0);
          $oiImg   = $oi['image'] ?? '';
          $oiLine  = $oiPrice * $oiQty;
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;
                    <?= $idx > 0 ? 'border-top:1px solid #d4e4d4;' : '' ?>
                    background:<?= $idx % 2 === 0 ? '#fff' : '#fafdf8' ?>;">
          <img src="<?= htmlspecialchars($oiImg) ?>" alt=""
               style="width:48px;height:48px;object-fit:cover;border-radius:10px;
                      background:#d4e4d4;flex-shrink:0;"
               onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:.86rem;color:#1a2e1a;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= $oiName ?>
            </div>
            <div style="font-size:.74rem;color:#999;">
              ₱<?= number_format($oiPrice, 2) ?> × <?= $oiQty ?>
            </div>
          </div>
          <span style="font-weight:800;color:#2d5a2d;font-size:.9rem;white-space:nowrap;">
            ₱<?= number_format($oiLine, 2) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Totals -->
      <div style="background:#f5f2ec;border-radius:12px;padding:14px 16px;margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#888;padding:3px 0;">
          <span>Subtotal</span><span>₱<?= number_format($fSub, 2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#888;padding:3px 0;">
          <span><i class="fas fa-truck me-1"></i>Shipping</span><span>₱<?= number_format($fShip, 2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-weight:800;color:#2d5a2d;
                    font-size:.95rem;border-top:2px solid #d4e4d4;padding-top:10px;margin-top:6px;">
          <span>Total Paid</span><span>₱<?= number_format($fTotal, 2) ?></span>
        </div>
      </div>

      <!-- Delivery -->
      <p style="font-size:.65rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;
                color:#2d5a2d;margin-bottom:10px;">Delivery To</p>
      <div style="background:#f5f2ec;border-radius:12px;padding:12px 16px;font-size:.84rem;
                  color:#1a2e1a;margin-bottom:22px;">
        <strong><?= htmlspecialchars($fInfo['full_name'] ?? '') ?></strong>
        &nbsp;·&nbsp; <?= htmlspecialchars($fInfo['phone'] ?? '') ?><br>
        <span style="color:#777;">
          <?= htmlspecialchars($fInfo['address'] ?? '') ?>,
          <?= htmlspecialchars($fInfo['city']    ?? '') ?>,
          <?= htmlspecialchars($fInfo['province'] ?? '') ?>
          <?= htmlspecialchars($fInfo['zip'] ?? '') ?>
        </span>
        <?php if (!empty($fInfo['notes'])): ?>
        <br><span style="color:#aaa;font-size:.78rem;"><i class="fas fa-sticky-note me-1"></i><?= htmlspecialchars($fInfo['notes']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Close button -->
      <button onclick="closeOrderModal()"
        style="width:100%;padding:14px;background:#2d5a2d;color:#fff;border:none;
               border-radius:50px;font-weight:700;font-size:.95rem;cursor:pointer;
               transition:.2s;letter-spacing:.5px;">
        <i class="fas fa-check me-2"></i>Got it — View My Orders
      </button>
    </div>
  </div>
</div>

<style>
@keyframes fadeInBg  { from { opacity:0; } to { opacity:1; } }
@keyframes slideUp   { from { transform:translateY(60px);opacity:0; } to { transform:translateY(0);opacity:1; } }
@keyframes popIn     { from { transform:scale(.3);opacity:0; } to { transform:scale(1);opacity:1; } }
</style>

<script>
function closeOrderModal() {
  const modal = document.getElementById('orderModal');
  modal.style.animation = 'fadeInBg .25s ease reverse forwards';
  setTimeout(() => modal.remove(), 280);
  // Scroll to order history section
  const hist = document.querySelector('.section-card:last-of-type');
  if (hist) setTimeout(() => hist.scrollIntoView({behavior:'smooth', block:'start'}), 300);
}
</script>
<?php endif; ?>

</body>
</html>