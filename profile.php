<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$db        = getDBConnection();

$dbUser = findAccountByEmail($userEmail);

if (!$dbUser) {
    foreach (['logged_in_user', 'role', 'login_time', 'session_start'] as $_k) unset($_SESSION[$_k]);
    header('Location: logsign.php');
    exit;
}

$user = [
    'name'        => $dbUser->name ?? '',
    'email'       => $dbUser->email ?? '',
    'role'        => $dbUser->role ?? 'user',
    'profile_pic' => $dbUser->profile_pic ?? null,
    'created_at'  => $dbUser->created_at ?? '',
];
$userRole = $_SESSION['role'] ?? $user['role'];
$isAdminAccount = ($userRole === 'admin');

if ($userRole !== 'admin') {
    if (!isset($_SESSION['orders'][$userEmail])) $_SESSION['orders'][$userEmail] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        if ($newName === '') $newName = $user['name'];

        if ($isAdminAccount) {
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) die('Password must be at least 6 characters.');
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $db->prepare("UPDATE admins SET admin_fname=?, password=? WHERE email=?")->execute([$newName, $hashed, $userEmail]);
            } else {
                $db->prepare("UPDATE admins SET admin_fname=? WHERE email=?")->execute([$newName, $userEmail]);
            }
        } else {
            $nameParts = splitName($newName);
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) die('Password must be at least 6 characters.');
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET fname=?, mname=?, lname=?, password=? WHERE email=?")
                   ->execute([$nameParts['fname'], $nameParts['mname'], $nameParts['lname'], $hashed, $userEmail]);
            } else {
                $db->prepare("UPDATE users SET fname=?, mname=?, lname=? WHERE email=?")
                   ->execute([$nameParts['fname'], $nameParts['mname'], $nameParts['lname'], $userEmail]);
            }
        }
        header('Location: profile.php?updated=1');
        exit;
    }

    if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        if ($file['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                if (!is_dir('uploads/profile_pics')) mkdir('uploads/profile_pics', 0777, true);
                $newName = uniqid('profile_', true) . '.' . $ext;
                $target  = 'uploads/profile_pics/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    if ($isAdminAccount) {
                        $db->prepare("UPDATE admins SET admin_pfp=? WHERE email=?")->execute([$target, $userEmail]);
                    } else {
                        $db->prepare("UPDATE users SET user_pfp=? WHERE email=?")->execute([$target, $userEmail]);
                    }
                    header('Location: profile.php?updated=1');
                    exit;
                }
            }
        }
    }

    if (isset($_POST['remove_pic'])) {
        if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
            unlink($user['profile_pic']);
        }
        if ($isAdminAccount) {
            $db->prepare("UPDATE admins SET admin_pfp=NULL WHERE email=?")->execute([$userEmail]);
        } else {
            $db->prepare("UPDATE users SET user_pfp=NULL WHERE email=?")->execute([$userEmail]);
        }
        header('Location: profile.php');
        exit;
    }
}

// ── Data for rendering ─────────────────────────────────────────
// FIX: Load orders with their items properly joined
$orders = [];
if ($userRole !== 'admin') {
    $orders = loadUserOrders($userEmail);
}

$pic = $dbUser->profile_pic ?? null;
// Use DB-stored picture when present; otherwise fall back for admin users only
if (empty($pic) && (($dbUser->role ?? '') === 'admin')) {
    $email_l = strtolower($dbUser->email ?? '');
    if ($email_l === 'zythera@gmail.com') {
        $pic = 'pci/beti.jpg';
    } elseif ($email_l === 'admin@gmail.com') {
        $pic = 'pci/admin.jpg';
    } elseif ($email_l === 'mei@gmail.com') {
        $pic = 'pci/mei.jpg';
    } else {
        // fallback to name-based heuristics
        $lname = strtolower($dbUser->name ?? '');
        if (strpos($lname, 'mei') !== false) $pic = 'pci/mei.jpg';
        elseif (strpos($lname, 'beti') !== false) $pic = 'pci/beti.jpg';
        else $pic = null;
    }
}
$_SESSION['profile_pic'][$userEmail] = $pic;

$stockMap = [];
foreach ($_SESSION['inventory'] ?? [] as $inv) {
    $stockMap[$inv->inv_id] = (int)$inv->stock;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZYTHERA | MY PROFILE</title>
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

        * {
            font-family: var(--ui-font);
            box-sizing: border-box;
        }

        body {
            background: var(--cream);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        .page-wrapper {
            flex: 1;
        }

        .navbar {
            background: #fff;
            box-shadow: 0 1px 12px rgba(0, 0, 0, .07);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            color: var(--green) !important;
            letter-spacing: 4px;
            font-size: 1.5rem;
.navbar-brand span { font-family: 'Playfair Display', serif; }
    }

        .profile-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 6px 28px rgba(0, 0, 0, .08);
            margin-bottom: 22px;
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--deep), var(--green));
            color: #fff;
            padding: 36px;
            text-align: center;
        }

        .section-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            color: var(--deep);
            font-size: 1.05rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .avatar-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .15);
            border: 3px solid rgba(255, 255, 255, .5);
            margin: 0 auto 14px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            position: relative;
        }

        .avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .42);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: .2s;
            border-radius: 50%;
        }

        .avatar-ring:hover .avatar-overlay {
            opacity: 1;
        }

        .badge-role {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 6px;
        }

        .badge-user {
            background: rgba(255, 255, 255, .2);
            color: #fff;
        }

        .badge-admin {
            background: #fee2e2;
            color: #b91c1c;
        }

        .form-control,
        .form-select {
            background: var(--sage);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: .75rem 1rem;
            color: var(--deep);
            transition: .2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--green);
            background: #fff;
            box-shadow: none;
            color: var(--deep);
        }

        .btn-green {
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: .65rem 1.8rem;
            font-weight: 600;
            transition: .2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-green:hover {
            background: var(--deep);
            color: #fff;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            background: var(--cream);
            border-radius: 12px;
            margin-bottom: 8px;
            transition: .15s;
        }

        .cart-item:hover {
            background: #ede9e0;
        }

        .cart-thumb {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: 10px;
            background: var(--sage);
        }

        .qty-stepper {
            display: inline-flex;
            align-items: center;
            border: 2px solid var(--sage);
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .qty-stepper button {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--sage);
            color: var(--green);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: .15s;
            line-height: 1;
        }

        .qty-stepper button:hover {
            background: var(--mid);
            color: #fff;
        }

        .qty-stepper button:disabled {
            opacity: .3;
            cursor: not-allowed;
        }

        .qty-stepper .qty-val {
            width: 34px;
            text-align: center;
            font-weight: 700;
            font-size: .9rem;
            color: var(--deep);
            background: #fff;
        }

        .stock-chip {
            display: inline-block;
            font-size: .65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            letter-spacing: .5px;
        }

        .sc-ok {
            background: #dcfce7;
            color: #16a34a;
        }

        .sc-low {
            background: #fef9c3;
            color: #b45309;
        }

        .sc-out {
            background: #fee2e2;
            color: #b91c1c;
        }

        .totals-box {
            background: var(--cream);
            border-radius: 14px;
            padding: 14px 18px;
            margin-top: 14px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            font-size: .85rem;
            color: #777;
            padding: 3px 0;
        }

        .totals-row.grand {
            font-size: 1rem;
            font-weight: 800;
            color: var(--green);
            border-top: 2px solid var(--sage);
            padding-top: 10px;
            margin-top: 6px;
        }

        .order-box {
            border: 2px solid var(--sage);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
            transition: .2s ease, transform .2s ease;
        }

        .order-box:hover {
            border-color: var(--mid);
        }

        .order-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }

        .order-link:hover .order-box {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, .08);
        }

        .order-list {
            display: grid;
            gap: 14px;
        }

        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }

        .order-summary-left {
            min-width: 0;
        }

        .order-summary-title {
            font-size: .95rem;
            font-weight: 700;
            color: var(--deep);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .order-summary-meta {
            font-size: .82rem;
            color: #777;
            margin-top: 6px;
        }

        .order-summary-right {
            text-align: right;
            min-width: 120px;
        }

        .order-total {
            font-size: .95rem;
            font-weight: 700;
            color: var(--green);
        }

        .order-link .order-box {
            border-color: rgba(212, 212, 212, .7);
        }

        .order-link:hover .order-box {
            border-color: var(--mid);
        }

        .order-status {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .st-pending {
            background: #fef9c3;
            color: #b45309;
        }

        .st-processing {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .st-shipped {
            background: #e0f2fe;
            color: #0369a1;
        }

        .st-completed,
        .st-delivered {
            background: #dcfce7;
            color: #16a34a;
        }

        .st-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty-state {
            text-align: center;
            padding: 36px 20px;
            color: #bbb;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 12px;
            display: block;
        }

        .empty-state p {
            font-size: .88rem;
            margin: 0;
        }

        .p-toast {
            position: fixed;
            bottom: 28px;
            right: 28px;
            background: var(--deep);
            color: #fff;
            padding: 12px 22px;
            border-radius: 50px;
            font-size: .85rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(12px);
            transition: .3s;
            pointer-events: none;
            z-index: 9999;
        }

        .p-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .p-toast.err {
            background: #b91c1c;
        }

        .alert-banner {
            padding: 12px 20px;
            font-size: .88rem;
            font-weight: 600;
            text-align: center;
        }

        .alert-banner.success {
            background: #dcfce7;
            color: #166534;
        }

        footer {
            background: #f5f2ec;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            border-top: 1px solid #e8e4dc;
        }

        footer .footer-brand {
            font-family: 'Playfair Display', serif;
            color: var(--green);
            font-size: 1rem;
            letter-spacing: 4px;
        }

        /* ── TWO-COLUMN LAYOUT ── */
        .two-col-layout {
            display: grid;
            grid-template-columns: 1fr 1.8fr;
            gap: 24px;
            align-items: start;
        }

        .profile-col {
            position: sticky;
            top: 72px;
        }

        .orders-col .section-card {
            min-height: 500px;
        }

        /* ── ORDER STATUS TABS ── */
        .order-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--sage);
        }

        .order-tab {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 50px;
            border: 2px solid var(--sage);
            background: #fff;
            color: #888;
            font-size: .78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
        }

        .order-tab:hover {
            border-color: var(--mid);
            color: var(--green);
            background: var(--sage);
        }

        .order-tab.active {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.25);
            color: inherit;
            border-radius: 50px;
            font-size: .66rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
        }

        .order-tab:not(.active) .tab-count {
            background: var(--sage);
            color: var(--green);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .two-col-layout {
                grid-template-columns: 1fr;
            }
            .profile-col {
                position: static;
            }
        }
    </style>
<script>
/* ZYTHERA dark mode — apply before paint to prevent flash */
(function(){
  if(localStorage.getItem('zythera_dark')==='1'){
    document.documentElement.classList.add('zd');
    if (document.body) document.body.classList.add('dark');
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

    <nav class="navbar navbar-light px-4 py-2 fixed-top">
        <a class="navbar-brand fw-bold" href="website.php"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <?php if ($userRole !== 'admin'): ?>
                <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Shop</a>
            <?php else: ?>
                <a href="admin.php" class="btn btn-sm btn-dark rounded-pill">Admin Panel</a>
            <?php endif; ?>

        </div>
    </nav>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert-banner success" style="margin-top:56px;">
            <i class="fas fa-check-circle me-2"></i>Profile updated successfully.
        </div>
    <?php else: ?>
        <div style="height:56px;"></div>
    <?php endif; ?>

    <div class="page-wrapper">
        <div class="container py-4" style="max-width:1200px;">

            <?php if ($userRole !== 'admin'): ?>
            <!-- ── TWO-COLUMN LAYOUT ── -->
            <div class="two-col-layout">

                <!-- ── LEFT: PROFILE CARD ── -->
                <div class="profile-col">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="avatar-ring" onclick="document.getElementById('picInput').click();" title="Click to change photo">
                                <?php $avatarSrc = getAvatarURL($user['profile_pic'] ?? null, $user['email'] ?? null, $user['name'] ?? null, 100); ?>
                                <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Profile Photo">
                                <div class="avatar-overlay"><i class="fas fa-camera" style="color:#fff;font-size:1.3rem;"></i></div>
                            </div>

                            <h4 class="mb-1 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                            <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
                            <span class="badge-role badge-user"><?= strtoupper($userRole) ?></span>

                            <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                                <input type="file" name="profile_pic" id="picInput" accept="image/*"
                                    onchange="document.getElementById('avatarForm').submit();" required>
                                <input type="hidden" name="upload_pic" value="1">
                            </form>

                            <?php if (!empty($user['profile_pic'])): ?>
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

                        <div class="p-4">
                            <div class="section-title"><i class="fas fa-pen" style="color:var(--green);font-size:.9rem;"></i>Edit Profile</div>
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
                                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                            <i class="fas fa-eye" id="pwEye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button name="update_profile" class="btn-green btn w-100">Save Changes</button>
                            </form>

                            <!-- Member since -->
                            <?php if (!empty($user['created_at'])): ?>
                            <div class="mt-4 pt-3" style="border-top:2px solid var(--sage);">
                                <div style="font-size:.75rem;color:#aaa;text-align:center;">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Member since <?= date('M Y', strtotime($user['created_at'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- /profile-col -->

                <!-- ── RIGHT: MY ORDERS ── -->
                <div class="orders-col">
                    <div class="section-card" style="height:100%;">
                        <div class="section-title" style="margin-bottom:16px;">
                            <i class="fas fa-shopping-bag" style="color:var(--green);"></i>
                            My Orders
                            <span class="badge rounded-pill ms-1"
                                style="background:var(--mid);color:#fff;font-size:.7rem;padding:4px 9px;">
                                <?= count($orders) ?>
                            </span>
                        </div>

                        <!-- ── STATUS TABS ── -->
                        <?php
                        $tabStatuses = ['All', 'Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                        $tabCounts   = ['All' => count($orders)];
                        foreach (['Pending','Processing','Shipped','Delivered','Cancelled'] as $ts) {
                            $tabCounts[$ts] = count(array_filter($orders, fn($o) => strtolower($o->status ?? '') === strtolower($ts)));
                        }
                        $tabIcons = [
                            'All'        => 'fa-list',
                            'Pending'    => 'fa-clock',
                            'Processing' => 'fa-gear',
                            'Shipped'    => 'fa-truck',
                            'Delivered'  => 'fa-check-circle',
                            'Cancelled'  => 'fa-times-circle',
                        ];
                        ?>
                        <div class="order-tabs" id="orderTabs">
                            <?php foreach ($tabStatuses as $tab): ?>
                            <button class="order-tab <?= $tab === 'All' ? 'active' : '' ?>"
                                    onclick="filterOrders('<?= $tab ?>')"
                                    data-tab="<?= $tab ?>">
                                <i class="fas <?= $tabIcons[$tab] ?> me-1"></i>
                                <?= $tab ?>
                                <?php if ($tabCounts[$tab] > 0): ?>
                                <span class="tab-count"><?= $tabCounts[$tab] ?></span>
                                <?php endif; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <!-- ── ORDER LIST ── -->
                        <?php if (empty($orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <p>No orders placed yet.</p>
                                <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill mt-2">Browse Products</a>
                            </div>
                        <?php else: ?>
                            <div class="order-list" id="orderList">
                                <?php foreach ($orders as $o): ?>
                                    <?php
                                    $oStatus  = $o->status ?? 'Pending';
                                    $stCls    = match (strtolower($oStatus)) {
                                        'delivered' => 'st-delivered',
                                        'cancelled' => 'st-cancelled',
                                        'shipped'   => 'st-shipped',
                                        'processing'=> 'st-processing',
                                        default     => 'st-pending'
                                    };
                                    $oSub      = (float)($o->subtotal ?? 0);
                                    $oShip     = is_numeric($o->shipping ?? null) ? (float)$o->shipping : 150;
                                    $oTotal    = (float)($o->total ?? ($oSub + $oShip));
                                    $oOrderId  = $o->order_id ?? '—';
                                    $oDate     = $o->date ?? '';
                                    $itemCount = count($o->items ?? []);
                                    ?>
                                    <a href="order.php?order_id=<?= urlencode($oOrderId) ?>&return=profile"
                                       class="order-link"
                                       data-status="<?= htmlspecialchars($oStatus) ?>"
                                       aria-label="View order <?= htmlspecialchars($oOrderId) ?>">
                                        <div class="order-box">
                                            <div class="order-summary">
                                                <div class="order-summary-left">
                                                    <div class="order-summary-title">Order #<?= htmlspecialchars($oOrderId) ?></div>
                                                    <div class="order-summary-meta">
                                                        <?= $oDate ? date('M d, Y · h:i A', strtotime($oDate)) : 'No date' ?>
                                                        · <?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>
                                                    </div>
                                                </div>
                                                <div class="order-summary-right">
                                                    <div class="order-total">₱<?= number_format($oTotal, 2) ?></div>
                                                    <span class="order-status <?= $stCls ?>" style="margin-top:6px;display:inline-block;">
                                                        <?= htmlspecialchars($oStatus) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <!-- empty-tab message (shown by JS when filter yields 0 results) -->
                            <div class="empty-state" id="emptyTabMsg" style="display:none;">
                                <i class="fas fa-inbox"></i>
                                <p id="emptyTabText">No orders in this category.</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div><!-- /orders-col -->

            </div><!-- /two-col-layout -->

            <?php else: ?>
            <!-- ── ADMIN SINGLE COLUMN (no orders section) ── -->
            <div style="max-width:480px;margin:0 auto;">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="avatar-ring" onclick="document.getElementById('picInput').click();" title="Click to change photo">
                            <?php $avatarSrc = getAvatarURL($user['profile_pic'] ?? null, $user['email'] ?? null, $user['name'] ?? null, 100); ?>
                            <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Profile Photo">
                            <div class="avatar-overlay"><i class="fas fa-camera" style="color:#fff;font-size:1.3rem;"></i></div>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                        <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
                        <span class="badge-role badge-admin">ADMIN</span>
                        <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none;">
                            <input type="file" name="profile_pic" id="picInput" accept="image/*"
                                onchange="document.getElementById('avatarForm').submit();" required>
                            <input type="hidden" name="upload_pic" value="1">
                        </form>
                        <?php if (!empty($user['profile_pic'])): ?>
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
                    <div class="p-4">
                        <div class="section-title"><i class="fas fa-pen" style="color:var(--green);font-size:.9rem;"></i>Edit Profile</div>
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
                                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                        <i class="fas fa-eye" id="pwEye"></i>
                                    </button>
                                </div>
                            </div>
                            <button name="update_profile" class="btn-green btn w-100">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <footer>
            <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
            <span class="footer-brand"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></span>
        </footer>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script>
        function filterOrders(tab) {
            // Update active tab
            document.querySelectorAll('.order-tab').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });

            const items   = document.querySelectorAll('#orderList .order-link');
            const emptyMsg = document.getElementById('emptyTabMsg');
            const emptyTxt = document.getElementById('emptyTabText');
            const orderList = document.getElementById('orderList');

            if (!items.length) return;

            let visible = 0;
            items.forEach(function(link) {
                const status = (link.dataset.status || '').trim();
                const show   = tab === 'All' || status.toLowerCase() === tab.toLowerCase();
                link.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (emptyMsg && orderList) {
                if (visible === 0) {
                    orderList.style.display = 'none';
                    emptyMsg.style.display  = '';
                    if (emptyTxt) emptyTxt.textContent = 'No ' + tab + ' orders yet.';
                } else {
                    orderList.style.display = '';
                    emptyMsg.style.display  = 'none';
                }
            }
        }
        </script>

        <?php
        $flash = null;
        if (isset($_GET['order_placed']) && !empty($_SESSION['order_flash'])) {
            $flash = $_SESSION['order_flash'];
            unset($_SESSION['order_flash']);
        }
        ?>

        <?php if ($flash): ?>
            <?php
            $fItems = $flash['items']    ?? [];
            $fSub   = (float)($flash['subtotal']  ?? 0);
            $fShip  = (float)($flash['shipping']  ?? 0);
            $fTotal = (float)($flash['total']     ?? 0);
            $fPay   = $flash['pay_method'] ?? '';
            $fId    = $flash['order_id']   ?? '';
            $fDate  = $flash['date']       ?? '';
            $fInfo  = $flash['shipping_info'] ?? [];
            ?>
            <div id="orderModal" style="position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;background:rgba(26,46,26,.55);backdrop-filter:blur(4px);padding:16px;animation:fadeInBg .3s ease;">
                <div style="background:#fff;border-radius:24px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.25);animation:slideUp .4s cubic-bezier(.34,1.56,.64,1);">
                    <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);border-radius:24px 24px 0 0;padding:36px 28px 28px;text-align:center;">
                        <div style="width:76px;height:76px;border-radius:50%;background:rgba(255,255,255,.15);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;animation:popIn .5s .2s both cubic-bezier(.34,1.56,.64,1);">
                            <i class="fas fa-check" style="color:#fff;font-size:2rem;"></i>
                        </div>
                        <h4 style="font-family:'Playfair Display',serif;color:#fff;margin-bottom:6px;font-size:1.6rem;">Order Placed!</h4>
                        <p style="color:rgba(255,255,255,.72);font-size:.88rem;margin-bottom:16px;">
                            Salamat, <strong style="color:#fff;"><?= htmlspecialchars($user['name'] ?? '') ?></strong>! Confirmed and being processed.
                        </p>
                        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.28);border-radius:50px;padding:8px 20px;">
                            <i class="fas fa-tag" style="color:rgba(255,255,255,.65);font-size:.8rem;"></i>
                            <span style="font-weight:800;color:#fff;letter-spacing:2px;font-size:.92rem;"><?= htmlspecialchars($fId) ?></span>
                        </div>
                        <div style="display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-top:12px;">
                            <span style="color:rgba(255,255,255,.6);font-size:.74rem;"><i class="fas fa-clock me-1"></i><?= date('M d, Y · h:i A', strtotime($fDate)) ?></span>
                            <span style="color:rgba(255,255,255,.6);font-size:.74rem;"><i class="fas fa-credit-card me-1"></i><?= htmlspecialchars($fPay) ?></span>
                        </div>
                    </div>
                    <div style="padding:24px 28px;">
                        <p style="font-size:.65rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#2d5a2d;margin-bottom:12px;">Items Ordered</p>
                        <div style="border:2px solid #d4e4d4;border-radius:14px;overflow:hidden;margin-bottom:18px;">
                            <?php foreach ($fItems as $idx => $oi):
                                $oiName  = htmlspecialchars($oi['name']  ?? '');
                                $oiQty   = (int)($oi['qty']   ?? 1);
                                $oiPrice = (float)($oi['price'] ?? 0);
                                $oiImg   = $oi['image'] ?? '';
                                $oiLine  = $oiPrice * $oiQty;
                            ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;<?= $idx > 0 ? 'border-top:1px solid #d4e4d4;' : '' ?>background:<?= $idx % 2 === 0 ? '#fff' : '#fafdf8' ?>;">
                                    <img src="<?= htmlspecialchars($oiImg) ?>" alt=""
                                        style="width:48px;height:48px;object-fit:cover;border-radius:10px;background:#d4e4d4;flex-shrink:0;"
                                        onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-weight:700;font-size:.86rem;color:#1a2e1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= $oiName ?></div>
                                        <div style="font-size:.74rem;color:#999;">₱<?= number_format($oiPrice, 2) ?> × <?= $oiQty ?></div>
                                    </div>
                                    <span style="font-weight:800;color:#2d5a2d;font-size:.9rem;white-space:nowrap;">₱<?= number_format($oiLine, 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="background:#f5f2ec;border-radius:12px;padding:14px 16px;margin-bottom:18px;">
                            <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#888;padding:3px 0;"><span>Subtotal</span><span>₱<?= number_format($fSub, 2) ?></span></div>
                            <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#888;padding:3px 0;"><span><i class="fas fa-truck me-1"></i>Shipping</span><span>₱<?= number_format($fShip, 2) ?></span></div>
                            <div style="display:flex;justify-content:space-between;font-weight:800;color:#2d5a2d;font-size:.95rem;border-top:2px solid #d4e4d4;padding-top:10px;margin-top:6px;"><span>Total Paid</span><span>₱<?= number_format($fTotal, 2) ?></span></div>
                        </div>
                        <p style="font-size:.65rem;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#2d5a2d;margin-bottom:10px;">Delivery To</p>
                        <div style="background:#f5f2ec;border-radius:12px;padding:12px 16px;font-size:.84rem;color:#1a2e1a;margin-bottom:22px;">
                            <strong><?= htmlspecialchars($fInfo['full_name'] ?? '') ?></strong> &nbsp;·&nbsp; <?= htmlspecialchars($fInfo['phone'] ?? '') ?><br>
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
                        <button onclick="closeOrderModal()"
                            style="width:100%;padding:14px;background:#2d5a2d;color:#fff;border:none;border-radius:50px;font-weight:700;font-size:.95rem;cursor:pointer;transition:.2s;letter-spacing:.5px;">
                            <i class="fas fa-check me-2"></i>Got it — View My Orders
                        </button>
                    </div>
                </div>
            </div>
            <style>
                @keyframes fadeInBg {
                    from {
                        opacity: 0;
                    }

                    to {
                        opacity: 1;
                    }
                }

                @keyframes slideUp {
                    from {
                        transform: translateY(60px);
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                @keyframes popIn {
                    from {
                        transform: scale(.3);
                        opacity: 0;
                    }

                    to {
                        transform: scale(1);
                        opacity: 1;
                    }
                }
            </style>
            <script>
                function closeOrderModal() {
                    const modal = document.getElementById('orderModal');
                    modal.style.animation = 'fadeInBg .25s ease reverse forwards';
                    setTimeout(() => modal.remove(), 280);
                    const hist = document.querySelector('.section-card:last-of-type');
                    if (hist) setTimeout(() => hist.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    }), 300);
                }

                function togglePw() {
                    const input = document.getElementById('pwField');
                    if (!input) return;
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    const icon = document.querySelector('.fa-eye, .fa-eye-slash');
                    if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
                }
            </script>
        <?php endif; ?>

</body>

</html>