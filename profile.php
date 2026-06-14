<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$db        = getDBConnection();

$uStmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$uStmt->execute([$userEmail]);
$dbUser = $uStmt->fetch();

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

if ($userRole !== 'admin') {
    if (!isset($_SESSION['orders'][$userEmail])) $_SESSION['orders'][$userEmail] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        if ($newName === '') $newName = $user['name'];
        if (!empty($newPass)) {
            if (strlen($newPass) < 6) die('Password must be at least 6 characters.');
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET name=?, password=? WHERE email=?")->execute([$newName, $hashed, $userEmail]);
        } else {
            $db->prepare("UPDATE users SET name=? WHERE email=?")->execute([$newName, $userEmail]);
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
                    $db->prepare("UPDATE users SET profile_pic=? WHERE email=?")->execute([$target, $userEmail]);
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
        $db->prepare("UPDATE users SET profile_pic=NULL WHERE email=?")->execute([$userEmail]);
        header('Location: profile.php');
        exit;
    }

    // ── Handle cart quantity updates from navbar ───────────────────
    // When user adds/removes items from cart in navigation bar (website.php)
    if (isset($_POST['update_qty'])) {
        header('Content-Type: application/json');
        
        $itemId = (int)($_POST['item_id'] ?? 0);
        $action = trim($_POST['qty_action'] ?? '');
        
        if (!$itemId || !in_array($action, ['plus', 'minus', 'remove'], true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
            exit;
        }
        
        // Initialize cart in session if not exists
        if (!isset($_SESSION['cart'][$userEmail])) {
            $_SESSION['cart'][$userEmail] = [];
        }
        
        $cart = &$_SESSION['cart'][$userEmail];
        $found = false;
        
        // Find the item in the cart
        foreach ($cart as $key => $item) {
            if ((int)$item['inv_id'] === $itemId) {
                $found = true;
                
                if ($action === 'remove') {
                    // Remove item from session cart
                    unset($cart[$key]);
                    
                    // Also remove from database if cart table exists
                    // Assuming you have a user_cart table: email, inv_id, qty, etc.
                    try {
                        $delStmt = $db->prepare("DELETE FROM user_cart WHERE email = ? AND inv_id = ?");
                        $delStmt->execute([$userEmail, $itemId]);
                    } catch (Exception $e) {
                        // If table doesn't exist or error, just continue
                        // The session update is sufficient
                    }
                } elseif ($action === 'plus') {
                    $item['qty'] = min((int)$item['qty'] + 1, 9999);
                } elseif ($action === 'minus') {
                    $item['qty'] = max(1, (int)$item['qty'] - 1);
                }
                
                break;
            }
        }
        
        // Re-index array to avoid gaps
        $_SESSION['cart'][$userEmail] = array_values($cart);
        
        echo json_encode(['success' => $found, 'cart_count' => count($_SESSION['cart'][$userEmail])]);
        exit;
    }
}

// ── Data for rendering ─────────────────────────────────────────
// FIX: Load orders with their items properly joined
$orders = [];
if ($userRole !== 'admin') {
    $oStmt = $db->prepare("SELECT * FROM orders WHERE email=? ORDER BY date DESC");
    $oStmt->execute([$userEmail]);
    $rawOrders = $oStmt->fetchAll();

    foreach ($rawOrders as $ord) {
        $iStmt = $db->prepare("SELECT * FROM order_items WHERE ord_no=?");
        $iStmt->execute([$ord->ord_no ?? $ord->id ?? 0]);
        $ord->items = $iStmt->fetchAll();
        $orders[] = $ord;
    }
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
    $stockMap[(int)$inv->inv_id] = (int)$inv->stock;
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
        }

        body {
            background: var(--cream);
            color: var(--deep);
            transition: background .15s, color .15s;
        }

        body.dark {
            background: #111e11;
            color: #e8e8e0;
        }

        .navbar {
            background: linear-gradient(to right, var(--deep), var(--green));
            box-shadow: 0 2px 8px rgba(26, 46, 26, 0.1);
            z-index: 999;
        }

        body.dark .navbar {
            background: linear-gradient(to right, #0a150a, #1a3a1a);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
        }

        .navbar-brand {
            color: #fff !important;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .page-wrapper {
            min-height: 100vh;
            background: var(--cream);
            margin-top: 56px;
            padding: 32px 16px;
        }

        body.dark .page-wrapper {
            background: #111e11;
        }

        .profile-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            margin-bottom: 32px;
            transition: box-shadow .3s;
        }

        body.dark .profile-card {
            background: #1a2e1a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .profile-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        body.dark .profile-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--green) 0%, var(--mid) 100%);
            color: #fff;
            padding: 48px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .profile-header > * {
            position: relative;
            z-index: 1;
        }

        .avatar-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            margin: 0 auto 16px;
            cursor: pointer;
            position: relative;
            transition: transform .3s, border-color .3s;
        }

        .avatar-ring:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.6);
        }

        .avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .2s;
        }

        .avatar-ring:hover .avatar-overlay {
            opacity: 1;
        }

        .profile-header h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .profile-header p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 8px 0;
        }

        .badge-role {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 12px;
        }

        .badge-admin {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .badge-user {
            background: rgba(212, 228, 212, 0.3);
            color: #fff;
            border: 1px solid rgba(212, 228, 212, 0.5);
        }

        .section-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            margin-bottom: 32px;
            transition: box-shadow .3s;
        }

        body.dark .section-card {
            background: #1a2e1a;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .section-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }

        body.dark .section-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
        }

        .section-card > .section-title {
            background: #f9f6f1;
            padding: 20px 28px;
            border-bottom: 1px solid #e8e0d5;
        }

        body.dark .section-card > .section-title {
            background: #0f230f;
            border-bottom: 1px solid #2a3a2a;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--deep);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        body.dark .section-title {
            color: #e8e8e0;
        }

        .section-card > div:not(.section-title):not(style) {
            padding: 28px;
        }

        .form-control {
            border: 1.5px solid #e0e0e0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: border-color .3s, box-shadow .3s;
        }

        body.dark .form-control {
            background: #0f230f;
            border-color: #3a4a3a;
            color: #e8e8e0;
        }

        .form-control:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(45, 90, 45, 0.1);
        }

        body.dark .form-control:focus {
            box-shadow: 0 0 0 3px rgba(45, 90, 45, 0.3);
        }

        .form-label {
            color: var(--green) !important;
            font-weight: 600;
            margin-bottom: 8px;
        }

        body.dark .form-label {
            color: #7aab7a !important;
        }

        .btn-green {
            background: linear-gradient(135deg, var(--green) 0%, var(--mid) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 28px;
            font-weight: 700;
            transition: all .3s;
        }

        .btn-green:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(45, 90, 45, 0.3);
        }

        .order-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all .2s;
        }

        .order-link:hover {
            transform: translateY(-2px);
        }

        .order-box {
            border: 1.5px solid #e8e0d5;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all .2s;
        }

        body.dark .order-box {
            border-color: #3a4a3a;
            background: #0f230f;
        }

        .order-link:hover .order-box {
            border-color: var(--green);
            box-shadow: 0 4px 12px rgba(45, 90, 45, 0.15);
        }

        body.dark .order-link:hover .order-box {
            box-shadow: 0 4px 12px rgba(45, 90, 45, 0.3);
        }

        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .order-summary-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--deep);
        }

        body.dark .order-summary-title {
            color: #e8e8e0;
        }

        .order-summary-meta {
            font-size: 0.85rem;
            color: #999;
            margin-top: 4px;
        }

        body.dark .order-summary-meta {
            color: #aaa;
        }

        .order-total {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--green);
        }

        .order-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .st-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ff9800;
        }

        .st-processing {
            background: rgba(33, 150, 243, 0.15);
            color: #2196f3;
        }

        .st-shipped {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
        }

        .st-delivered {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
        }

        .st-cancelled {
            background: rgba(244, 67, 54, 0.15);
            color: #f44336;
        }

        .empty-state {
            text-align: center;
            padding: 48px 28px;
            color: #999;
        }

        body.dark .empty-state {
            color: #aaa;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d4e4d4;
            margin-bottom: 16px;
        }

        body.dark .empty-state i {
            color: #3a4a3a;
        }

        footer {
            background: var(--deep);
            color: #fff;
            text-align: center;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }

        body.dark footer {
            background: #0a150a;
        }

        .footer-brand {
            color: #fff;
            font-weight: 700;
        }

        .alert-banner {
            position: fixed;
            top: 56px;
            left: 0;
            right: 0;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: #fff;
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            z-index: 998;
            animation: slideDown .3s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ────────────────────────────────────────── */
        /* LOGOUT MODAL STYLES                        */
        /* ────────────────────────────────────────── */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeInBg 0.25s ease-out;
        }

        .modal-content-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
            text-align: center;
        }

        body.dark .modal-content-box {
            background: #1a2e1a;
            color: #e8e8e0;
        }

        .modal-content-box i {
            font-size: 2.5rem;
            color: #ff9800;
            margin-bottom: 16px;
        }

        .modal-content-box h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--deep);
            margin-bottom: 12px;
        }

        body.dark .modal-content-box h3 {
            color: #e8e8e0;
        }

        .modal-content-box p {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 24px;
        }

        body.dark .modal-content-box p {
            color: #aaa;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-modal {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-modal-cancel {
            background: #e8e8e8;
            color: #333;
        }

        body.dark .btn-modal-cancel {
            background: #3a4a3a;
            color: #e8e8e0;
        }

        .btn-modal-cancel:hover {
            background: #d0d0d0;
        }

        body.dark .btn-modal-cancel:hover {
            background: #4a5a4a;
        }

        .btn-modal-logout {
            background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
            color: #fff;
        }

        .btn-modal-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 67, 54, 0.3);
        }

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
    </style>
</head>

<body>

    <nav class="navbar navbar-light px-4 py-2 fixed-top">
        <a class="navbar-brand fw-bold" href="website.php"><span style="font-family:'Playfair Display',serif;color:#fff;font-weight:700;"> ZYTHERA </span></a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <?php if ($userRole !== 'admin'): ?>
                <a href="website.php" class="btn btn-sm btn-outline-light rounded-pill">Shop</a>
            <?php else: ?>
                <a href="admin.php" class="btn btn-sm btn-light rounded-pill">Admin Panel</a>
            <?php endif; ?>
            <a href="javascript:void(0)" onclick="openLogoutModal()" class="btn btn-sm btn-danger rounded-pill">Logout</a>
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
        <div class="container py-4" style="max-width:780px;">

            <div class="profile-card">
                <div class="profile-header">
                    <div class="avatar-ring" onclick="document.getElementById('picInput').click();" title="Click to change photo">
                        <?php $avatarSrc = getAvatarURL($user['profile_pic'] ?? null, $user['email'] ?? null, $user['name'] ?? null, 100); ?>
                        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Profile Photo">
                        <div class="avatar-overlay"><i class="fas fa-camera" style="color:#fff;font-size:1.3rem;"></i></div>
                    </div>

                    <h4 class="mb-1 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
                    <span class="badge-role <?= $userRole === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                        <?= strtoupper($userRole) ?>
                    </span>

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

                <?php if ($userRole !== 'admin'): ?>
                <!-- ── ORDER HISTORY ── -->
                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-receipt" style="color:var(--green);"></i>
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
                        <div class="order-list">
                            <?php foreach ($orders as $o): ?>
                                <?php
                                $oStatus  = $o->status ?? 'Pending';
                                $stCls    = match (strtolower($oStatus)) {
                                    'delivered', 'completed' => 'st-delivered',
                                    'cancelled'              => 'st-cancelled',
                                    'shipped'                => 'st-shipped',
                                    'processing'             => 'st-processing',
                                    default                  => 'st-pending'
                                };
                                $oSub      = (float)($o->subtotal ?? 0);
                                $oShip     = is_numeric($o->shipping ?? null) ? (float)$o->shipping : 150;
                                $oTotal    = (float)($o->total ?? ($oSub + $oShip));
                                $oOrderId  = $o->order_id  ?? '—';
                                $oDate     = $o->date      ?? '';
                                $itemCount = count($o->items ?? []);
                                ?>
                                <a href="order.php?order_id=<?= urlencode($oOrderId) ?>&return=profile" class="order-link" aria-label="View order <?= htmlspecialchars($oOrderId) ?>">
                                    <div class="order-box">
                                        <div class="order-summary">
                                            <div class="order-summary-left">
                                                <div class="order-summary-title">Order #<?= htmlspecialchars($oOrderId) ?></div>
                                                <div class="order-summary-meta">
                                                    <?= $oDate ? date('M d, Y · h:i A', strtotime($oDate)) : 'No date' ?> · <?= $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?>
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
                    <?php endif; ?>
                </div>

                <?php endif; ?>
        </div>

        <footer>
            <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
            <span class="footer-brand"><span style="font-family:'Playfair Display',serif;color:#fff;font-weight:700;"> ZYTHERA </span></span>
        </footer>

        <!-- FIX: Bootstrap JS was missing -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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

            /**
             * FIX: Added missing openLogoutModal() function
             * This function displays a confirmation modal before logging out
             */
            function openLogoutModal() {
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop';
                backdrop.id = 'logoutModalBackdrop';

                backdrop.innerHTML = `
                    <div class="modal-content-box">
                        <i class="fas fa-sign-out-alt"></i>
                        <h3>Confirm Logout</h3>
                        <p>Are you sure you want to log out? You'll need to log in again to access your account.</p>
                        <div class="modal-buttons">
                            <button class="btn-modal btn-modal-cancel" onclick="closeLogoutModal()">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                            <button class="btn-modal btn-modal-logout" onclick="performLogout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(backdrop);
                backdrop.addEventListener('click', function(e) {
                    if (e.target === this) closeLogoutModal();
                });
            }

            /**
             * Close the logout modal without logging out
             */
            function closeLogoutModal() {
                const backdrop = document.getElementById('logoutModalBackdrop');
                if (backdrop) {
                    backdrop.style.animation = 'fadeInBg 0.25s ease reverse forwards';
                    setTimeout(() => backdrop.remove(), 280);
                }
            }

            /**
             * Perform the actual logout by redirecting to logout.php
             */
            function performLogout() {
                window.location.href = 'logout.php';
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
    </div>

</body>

</html>