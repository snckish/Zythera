<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$db = getDBConnection();

$uStmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$uStmt->execute([$userEmail]);
$dbUser = $uStmt->fetch();

if (!$dbUser) {
    foreach (['logged_in_user', 'role', 'login_time', 'session_start'] as $_k) unset($_SESSION[$_k]);
    header('Location: logsign.php');
    exit;
}

$userRole = $_SESSION['role'] ?? $dbUser->role ?? 'user';
if ($userRole === 'admin') {
    header('Location: profile.php');
    exit;
}

$userMessages = loadUserMessagesForEmail($userEmail);
$pic = $dbUser->profile_pic ?? null;
if (empty($pic)) {
    $email_l = strtolower($dbUser->email ?? '');
    if ($email_l === 'zythera@gmail.com') {
        $pic = 'pci/pfp/beti.jpg';
    } elseif ($email_l === 'admin@gmail.com') {
        $pic = 'pci/pfp/admin.jpg';
    } elseif ($email_l === 'mei@gmail.com') {
        $pic = 'pci/pfp/mei.jpg';
    }
}
$avatarSrc = getAvatarURL($pic, $dbUser->email ?? null, $dbUser->name ?? null, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZYTHERA | Receipts & Messages</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Lora',serif}
        body{font-family:var(--ui-font);background:#f7f8f6;}
        h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
        p,small,.caption,.text-muted{font-family:var(--text-font);}
        .page-wrapper{padding:80px 0 40px;}
        .profile-card,.section-card{background:#fff;border-radius:24px;box-shadow:0 28px 65px rgba(62,67,62,.12);margin-bottom:24px;}
        .profile-header{padding:32px 32px 24px;text-align:center;position:relative;}
        .avatar-ring{width:110px;height:110px;border-radius:50%;padding:6px;background:linear-gradient(180deg,#2b5728,#aac68a);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;}
        .avatar-ring img{width:100%;height:100%;object-fit:cover;border-radius:50%;border:4px solid #fff;}
        .avatar-overlay{position:absolute;bottom:26px;right:34px;width:38px;height:38px;border-radius:50%;background:rgba(45,90,45,.9);display:flex;align-items:center;justify-content:center;}
        .section-title{font-size:1rem;font-weight:700;color:#1f4d1f;margin-bottom:18px;display:flex;align-items:center;gap:.75rem;}
        .order-list{display:grid;gap:16px;}
        .order-box{border:1px solid #ecf0ea;border-radius:18px;background:#fff;padding:18px;}
        .order-summary{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;}
        .order-summary-title{font-weight:700;color:#1b3a1b;margin-bottom:6px;}
        .order-summary-meta{color:#6f7b6f;font-size:.9rem;}
        .empty-state{padding:52px 0;text-align:center;color:#7a8a7a;}
        .empty-state i{font-size:3rem;margin-bottom:18px;display:block;}
        .btn-green{background:#2d5a2d;color:#fff;border:none;}
        .navbar{background:#fff;border-bottom:1px solid rgba(0,0,0,.08);}
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-light px-4 py-2 fixed-top">
        <a class="navbar-brand fw-bold" href="website.php">ZYTHERA</a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <a href="profile.php" class="btn btn-sm btn-outline-success rounded-pill">Profile</a>
            <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Shop</a>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill">Logout</a>
        </div>
    </nav>

    <div style="height:56px;"></div>
    <div class="page-wrapper">
        <div class="container py-4" style="max-width:780px;">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="avatar-ring">
                        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Profile Photo">
                    </div>
                    <h4 class="mb-1 mt-3 fw-bold"><?= htmlspecialchars($dbUser->name ?? '') ?></h4>
                    <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
                    <span class="badge rounded-pill" style="background:#2d5a2d;color:#fff;padding:.45rem .85rem;font-size:.8rem;">
                        RECEIPTS & MESSAGES
                    </span>
                </div>
                <div class="p-4">
                    <div class="section-title">
                        <i class="fas fa-envelope" style="color:var(--green);"></i>
                        Your Receipts & Messages
                    </div>
                    <?php if (empty($userMessages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No receipts or admin messages yet.</p>
                            <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill mt-3">Continue Shopping</a>
                        </div>
                    <?php else: ?>
                        <div class="order-list">
                            <?php foreach ($userMessages as $msg): ?>
                                <div class="order-box">
                                    <div class="order-summary">
                                        <div>
                                            <div class="order-summary-title"><?= htmlspecialchars($msg->subject ?? 'Admin Message') ?></div>
                                            <div class="order-summary-meta">
                                                <?= htmlspecialchars($msg->created_at ?? '') ?>
                                                <?php if (!empty($msg->order_id)): ?>
                                                    · <a href="order.php?order_id=<?= urlencode($msg->order_id) ?>&return=messages" style="color:var(--green);text-decoration:none;">Order #<?= htmlspecialchars($msg->order_id) ?></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-top:12px;color:#333;white-space:pre-wrap;word-break:break-word;line-height:1.5;">
                                        <?php
                                            $body = $msg->body ?? '';
                                            $body = preg_replace('/^[\s\x{00A0}]+/u', '', $body);
                                            echo nl2br(htmlspecialchars($body));
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer style="padding:24px 0;text-align:center;">
        <img src="pci/Group_15.png" style="width:28px;" alt="Zythera logo">
        <span class="footer-brand" style="font-family:var(--logo-font);font-size:1rem;vertical-align:middle;margin-left:8px;">ZYTHERA</span>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
