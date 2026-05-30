<?php
require 'config.php';

if (empty($_SESSION['logged_in_user'])) {
    header('Location: logsign.php');
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
if (!isset($_SESSION['users'][$userEmail])) {
    session_destroy();
    header('Location: logsign.php');
    exit;
}

$user     = &$_SESSION['users'][$userEmail];
$userRole = $_SESSION['role'] ?? 'user';

if (!isset($_SESSION['cart'][$userEmail]))        $_SESSION['cart'][$userEmail]        = [];
if (!isset($_SESSION['orders'][$userEmail]))      $_SESSION['orders'][$userEmail]      = [];
if (!isset($_SESSION['profile_pic'][$userEmail])) $_SESSION['profile_pic'][$userEmail] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update profile
    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        if ($newName) $user['name'] = htmlspecialchars($newName);
        if ($newPass && strlen($newPass) >= 6)
            $user['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        header('Location: profile.php?updated=1');
        exit;
    }

    // Checkout
    if (isset($_POST['checkout']) && !empty($_SESSION['cart'][$userEmail])) {
        $_SESSION['orders'][$userEmail][] = [
            'items' => $_SESSION['cart'][$userEmail],
            'date'  => date('Y-m-d H:i:s'),
        ];
        $_SESSION['cart'][$userEmail] = [];
        header('Location: profile.php?ordered=1');
        exit;
    }

    // Upload profile picture
    if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic'])) {
        $f = $_FILES['profile_pic'];
        if ($f['error'] === 0 && $f['size'] <= 5 * 1024 * 1024) {
            $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                $dest = 'uploads/' . md5($userEmail) . '.' . $ext;
                if (move_uploaded_file($f['tmp_name'], $dest))
                    $_SESSION['profile_pic'][$userEmail] = $dest;
            }
        }
        header('Location: profile.php');
        exit;
    }
}

$cart   = $_SESSION['cart'][$userEmail];
$orders = $_SESSION['orders'][$userEmail];
$pic    = $_SESSION['profile_pic'][$userEmail];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAFIRAH | My Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --green: #2d5a2d;
            --sage: #d4e4d4;
            --cream: #f5f2ec;
            --deep: #1a2e1a;
        }

        * {
            font-family: 'DM Sans', sans-serif;
        }

        body {
            background: var(--cream);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            color: var(--green) !important;
            letter-spacing: 4px;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 6px 28px rgba(0, 0, 0, .08);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--deep), var(--green));
            color: #fff;
            border-radius: 20px 20px 0 0;
            padding: 36px;
            text-align: center;
        }

        .avatar-ring {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            border: 3px solid rgba(255, 255, 255, .5);
            margin: 0 auto 16px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: #fff;
        }

        .avatar-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .badge-role {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: 1px;
            margin-top: 6px;
        }

        .badge-user {
            background: var(--sage);
            color: var(--green);
        }

        .badge-admin {
            background: #fee2e2;
            color: #b91c1c;
        }

        .section-card {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
        }

        .form-control,
        .form-select {
            background: var(--sage);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: .75rem 1rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--deep);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--green);
            background: #fff;
            box-shadow: none;
        }

        .btn-green {
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: .6rem 1.6rem;
            font-weight: 600;
        }

        .btn-green:hover {
            background: var(--deep);
            color: #fff;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: var(--cream);
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .order-box {
            border: 2px solid var(--sage);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-light bg-white shadow-sm px-4 py-2">
        <a class="navbar-brand fw-bold">ZAFIRAH</a>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <a href="website.php" class="btn btn-sm btn-outline-success rounded-pill">Home</a>
            <?php if ($userRole === 'admin'): ?>
                <a href="admin.php" class="btn btn-sm btn-dark rounded-pill">Admin</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill">Logout</a>
        </div>
    </nav>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success text-center rounded-0 mb-0">✓ Profile updated!</div>
    <?php elseif (isset($_GET['ordered'])): ?>
        <div class="alert alert-success text-center rounded-0 mb-0">✓ Order placed successfully!</div>
    <?php endif; ?>

    <div class="container py-4" style="max-width:740px;">

        <!-- PROFILE CARD -->
        <div class="card mb-4">
            <div class="profile-header">
                <div class="avatar-ring">
                    <?php if ($pic && file_exists($pic)): ?>
                        <img src="<?= htmlspecialchars($pic) ?>" alt="Profile photo">
                    <?php else: ?>
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <h4 class="mb-1 fw-bold"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="mb-1 opacity-75" style="font-size:.85rem;"><?= htmlspecialchars($userEmail) ?></p>
                <span class="badge-role <?= $userRole === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                    <?= strtoupper($userRole) ?>
                </span>

                <!-- Upload photo -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <input type="file" name="profile_pic" accept="image/*"
                            class="form-control form-control-sm"
                            style="max-width:230px;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);">
                        <button name="upload_pic" class="btn btn-light btn-sm rounded-pill fw-semibold">Upload Photo</button>
                    </div>
                </form>
            </div>

            <!-- Edit profile -->
            <div class="p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-user-edit me-2"></i>Edit Profile</h6>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Full Name</label>
                        <input class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">New Password <span class="text-muted fw-normal">(leave blank to keep)</span></label>
                        <input class="form-control" name="password" type="password" placeholder="Min 6 characters" autocomplete="new-password">
                    </div>
                    <button name="update_profile" class="btn-green btn w-100">Save Changes</button>
                </form>
            </div>
        </div>

        <!-- CART -->
        <div class="section-card">
            <h6 class="fw-bold mb-3"><i class="fas fa-shopping-cart me-2"></i>My Cart
                <span class="badge bg-success ms-1"><?= count($cart) ?></span>
            </h6>
            <?php if (empty($cart)): ?>
                <p class="text-muted small mb-2">Your cart is empty. <a href="website.php" class="text-success">Start shopping →</a></p>
            <?php else: ?>
                <?php
                $subtotal = 0;
                foreach ($cart as $item):
                    $price = (float)($item['price'] ?? 0);
                    $qty   = (int)($item['qty']   ?? 1);
                    $subtotal += $price * $qty;
                ?>
                    <div class="cart-item">
                        <div>
                            <span class="fw-semibold"><?= htmlspecialchars($item['name'] ?? $item) ?></span>
                            <?php if (isset($item['price'])): ?>
                                <br><small class="text-muted">₱<?= number_format($price) ?> × <?= $qty ?></small>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-success">×<?= $qty ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ($subtotal > 0): ?>
                    <div class="text-end fw-bold mt-2" style="color:var(--green);">
                        Total: ₱<?= number_format($subtotal) ?>
                    </div>
                <?php endif; ?>
                <form method="POST" class="mt-3">
                    <button name="checkout" class="btn btn-success w-100 rounded-pill fw-bold">
                        <i class="fas fa-check-circle me-2"></i>Checkout
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ORDERS -->
        <div class="section-card">
            <h6 class="fw-bold mb-3"><i class="fas fa-box me-2"></i>Order History
                <span class="badge bg-secondary ms-1"><?= count($orders) ?></span>
            </h6>
            <?php if (empty($orders)): ?>
                <p class="text-muted small">No orders yet.</p>
            <?php else: ?>
                <?php foreach (array_reverse($orders) as $o): ?>
                    <div class="order-box">
                        <p class="small text-muted mb-2"><i class="fas fa-calendar me-1"></i><?= $o['date'] ?></p>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($o['items'] as $name => $val): ?>
                                <li class="small">
                                    <?php
                                    if (is_array($val)) {
                                        echo htmlspecialchars($val['name'] ?? $name) . ' <b>×' . ($val['qty'] ?? 1) . '</b>';
                                    } else {
                                        echo htmlspecialchars($name) . ' <b>×' . $val . '</b>';
                                    }
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>