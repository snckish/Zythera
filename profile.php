<?php
require 'config.php';
require __DIR__ . '/includes/location_data.php';

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
    'phone_num'   => $dbUser->phone_num ?? '',
    'birthday'    => $dbUser->birthday ?? '',
    'created_at'  => $dbUser->created_at ?? '',
];
$userRole = $_SESSION['role'] ?? $user['role'];
$isAdminAccount = ($userRole === 'admin');
$uObj = $dbUser;
$userName = $user['name'] ?? '';
$loginTime = $_SESSION['login_time'] ?? null;
$cartCount = 0;
if ($userRole !== 'admin' && !empty($_SESSION['cart'][$userEmail]) && is_array($_SESSION['cart'][$userEmail])) {
    $cartCount = count($_SESSION['cart'][$userEmail]);
}
$profileError = '';
$activeSettingsTab = $_GET['tab'] ?? 'account';
$allowedSettingsTabs = ['account', 'addresses', 'orders', 'security'];
if (!in_array($activeSettingsTab, $allowedSettingsTabs, true)) $activeSettingsTab = 'account';

if ($userRole !== 'admin') {
    if (!isset($_SESSION['orders'][$userEmail])) $_SESSION['orders'][$userEmail] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['update_account'])) {
        $newName = trim($_POST['name'] ?? '');
        $newPhone = preg_replace('/\D+/', '', $_POST['phone_num'] ?? '');
        $newBirthday = trim($_POST['birthday'] ?? '');
        if ($newName === '') $newName = $user['name'];
        if ($newPhone !== '' && !preg_match('/^[0-9]{10,11}$/', $newPhone)) {
            $profileError = 'Phone number must be 10 or 11 digits.';
        }
        if ($newBirthday !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newBirthday)) {
            $profileError = 'Please enter a valid birthday.';
        }

        if ($profileError === '') {
            if ($isAdminAccount) {
                if (tableExists('admins')) {
                    $db->prepare("UPDATE admins SET admin_fname=? WHERE email=?")->execute([$newName, $userEmail]);
                }
            } else {
                $nameParts = splitName($newName);
                $db->prepare("UPDATE users SET fname=?, mname=?, lname=?, phone_num=?, birthday=? WHERE email=?")
                   ->execute([$nameParts['fname'], $nameParts['mname'], $nameParts['lname'], $newPhone ?: null, $newBirthday ?: null, $userEmail]);
            }
            header('Location: profile.php?updated=1&tab=account');
            exit;
        }
        $activeSettingsTab = 'account';
    }

    if (isset($_POST['change_password']) || isset($_POST['update_profile'])) {
        $currentPass = trim($_POST['current_password'] ?? '');
        $newPass = trim($_POST['password'] ?? '');

        if (isset($_POST['update_profile']) && $isAdminAccount) {
            $newName = trim($_POST['name'] ?? $user['name']);
            if ($newPass !== '' && $currentPass === '') {
                $profileError = 'Please enter your old password.';
            } elseif ($newPass !== '' && !password_verify($currentPass, (string)($dbUser->password ?? ''))) {
                $profileError = 'Old password is incorrect.';
            } elseif ($newPass !== '' && strlen($newPass) < 6) {
                $profileError = 'Password must be at least 6 characters.';
            } elseif ($newPass !== '') {
                if (tableExists('admins')) {
                    $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                    $db->prepare("UPDATE admins SET admin_fname=?, password=? WHERE email=?")->execute([$newName, $hashed, $userEmail]);
                }
                header('Location: profile.php?updated=1');
                exit;
            } else {
                if (tableExists('admins')) {
                    $db->prepare("UPDATE admins SET admin_fname=? WHERE email=?")->execute([$newName, $userEmail]);
                }
                header('Location: profile.php?updated=1');
                exit;
            }
        } elseif ($newPass === '') {
            $profileError = 'Please enter a new password.';
        } elseif ($currentPass === '') {
            $profileError = 'Please enter your old password.';
        } elseif (!password_verify($currentPass, (string)($dbUser->password ?? ''))) {
            $profileError = 'Old password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $profileError = 'Password must be at least 6 characters.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            if ($isAdminAccount) {
                if (tableExists('admins')) {
                    $db->prepare("UPDATE admins SET password=? WHERE email=?")->execute([$hashed, $userEmail]);
                }
            } else {
                $db->prepare("UPDATE users SET password=? WHERE email=?")->execute([$hashed, $userEmail]);
            }
            header('Location: profile.php?updated=1&tab=security');
            exit;
        }
        $activeSettingsTab = 'security';
    }

    if (!$isAdminAccount && isset($_POST['save_address'])) {
        try {
            $currentUserId = (string)$dbUser->user_id;
            $addressId = trim($_POST['address_id'] ?? '');
            saveUserAddress($currentUserId, [
                'address_label' => $_POST['address_label'] ?? 'Home',
                'phone_num' => $_POST['phone_num'] ?? '',
                'st_address' => $_POST['st_address'] ?? '',
                'barangay' => $_POST['barangay'] ?? '',
                'city_municipality' => $_POST['city_municipality'] ?? '',
                'province' => $_POST['province'] ?? '',
                'zip_code' => $_POST['zip_code'] ?? '',
                'is_default' => isset($_POST['is_default']),
            ], $addressId !== '' ? $addressId : null);
            header('Location: profile.php?updated=1&tab=addresses');
            exit;
        } catch (Throwable $e) {
            $profileError = $e->getMessage();
            $activeSettingsTab = 'addresses';
        }
    }

    if (!$isAdminAccount && isset($_POST['set_default_address'])) {
        try {
            setDefaultAddress((string)$dbUser->user_id, trim($_POST['address_id'] ?? ''));
            header('Location: profile.php?updated=1&tab=addresses');
            exit;
        } catch (Throwable $e) {
            $profileError = $e->getMessage();
            $activeSettingsTab = 'addresses';
        }
    }

    if (!$isAdminAccount && isset($_POST['delete_address'])) {
        try {
            deleteUserAddress((string)$dbUser->user_id, trim($_POST['address_id'] ?? ''));
            header('Location: profile.php?updated=1&tab=addresses');
            exit;
        } catch (Throwable $e) {
            $profileError = 'This address is linked to an order and cannot be deleted.';
            $activeSettingsTab = 'addresses';
        }
    }

    if (isset($_POST['upload_pic']) && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        if ($file['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $uploadsDir = __DIR__ . '/uploads';
                if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0777, true); }
                @chmod($uploadsDir, 0777);

                $picDir = $uploadsDir . '/profile_pics';
                if (!is_dir($picDir)) {
                    @mkdir($picDir, 0777, true);
                }
                if (!is_writable($picDir)) {
                    @chmod($picDir, 0777);
                }
                if (!is_writable($picDir)) {
                    $profileError = 'Profile picture upload folder is not writable. Please run: chmod -R 777 uploads/ on your server.';
                } else {
                    $newName = uniqid('profile_', true) . '.' . $ext;
                    $target  = $picDir . '/' . $newName;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $target = 'uploads/profile_pics/' . $newName;
                        if ($isAdminAccount) {
                            if (tableExists('admins')) {
                                $db->prepare("UPDATE admins SET admin_pfp=? WHERE email=?")->execute([$target, $userEmail]);
                            }
                        } else {
                            $db->prepare("UPDATE users SET user_pfp=? WHERE email=?")->execute([$target, $userEmail]);
                        }
                        header('Location: profile.php?updated=1');
                        exit;
                    }
                } // end is_writable check
            } // end in_array allowed
        } // end file['error'] === 0
    } // end upload_pic

    if (isset($_POST['remove_pic'])) {
        if (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) {
            unlink($user['profile_pic']);
        }
        if ($isAdminAccount) {
            if (tableExists('admins')) {
                $db->prepare("UPDATE admins SET admin_pfp=NULL WHERE email=?")->execute([$userEmail]);
            }
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
$addresses = [];
if ($userRole !== 'admin') {
    $orders = loadUserOrders($userEmail);
    $addresses = loadUserAddresses((string)$dbUser->user_id);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/profile.css">
</head>

<body>

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

<?php if (isset($_GET['updated'])): ?>
        
    <?php endif; ?>
    <div style="height:56px;"></div>

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
                            <div class="section-title"><i class="fas fa-sliders" style="color:var(--green);font-size:.9rem;"></i>Settings</div>
                            <div class="settings-nav" role="tablist" aria-label="Profile settings">
                                <button type="button" class="settings-tab-btn <?= ($activeSettingsTab === 'account' || $activeSettingsTab === 'security') ? 'active' : '' ?>" data-settings-tab="account">
                                    <i class="fas fa-user-shield"></i>Account & Security
                                </button>
                                <button type="button" class="settings-tab-btn <?= $activeSettingsTab === 'addresses' ? 'active' : '' ?>" data-settings-tab="addresses">
                                    <i class="fas fa-location-dot"></i>Addresses
                                </button>
                                <button type="button" class="settings-tab-btn <?= $activeSettingsTab === 'orders' ? 'active' : '' ?>" data-settings-tab="orders">
                                    <i class="fas fa-bag-shopping"></i>My Orders
                                </button>
                            </div>
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
                        <?php if ($profileError): ?>
                            <div role="alert" style="display:flex;align-items:flex-start;gap:10px;background:#fff0f0;border:1.5px solid #f5c2c2;border-radius:14px;padding:14px 16px;margin-bottom:18px;color:#b91c1c;font-size:.85rem;line-height:1.5;">
                                <i class="fas fa-circle-exclamation" style="flex-shrink:0;margin-top:2px;font-size:1rem;"></i>
                                <span><?= htmlspecialchars($profileError) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="settings-pane <?= ($activeSettingsTab === 'account' || $activeSettingsTab === 'security') ? 'active' : '' ?>" data-settings-pane="account">
                            <div class="section-title">
                                <i class="fas fa-user-shield" style="color:var(--green);"></i>
                                Account & Security
                            </div>
                            <form method="POST" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">Full Name</label>
                                    <input class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">Email Address</label>
                                    <input class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">Phone Number</label>
                                    <input class="form-control" name="phone_num" value="<?= htmlspecialchars($user['phone_num']) ?>" inputmode="numeric" maxlength="11">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">Birthday</label>
                                    <input class="form-control" type="date" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>">
                                </div>
                                <div class="col-12">
                                    <button name="update_account" class="btn-green btn">Save Account</button>
                                </div>
                            </form>

                            <!-- Security sub-section merged here -->
                            <hr style="border-color:var(--sage);margin:28px 0 24px;">
                            <div class="section-title" style="margin-bottom:16px;">
                                <i class="fas fa-lock" style="color:var(--green);"></i>
                                Change Password
                            </div>
                            <form method="POST" style="max-width:520px;">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">Old Password</label>
                                    <div class="position-relative">
                                        <input class="form-control" name="current_password" type="password" id="oldPwField"
                                            placeholder="Enter old password" autocomplete="current-password" required>
                                        <button type="button" onclick="togglePw('oldPwField','oldPwEye')" tabindex="-1"
                                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                            <i class="fas fa-eye" id="oldPwEye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold" style="color:var(--green);">New Password</label>
                                    <div class="position-relative">
                                        <input class="form-control" name="password" type="password" id="newPwField"
                                            placeholder="Min 6 characters" autocomplete="new-password" required>
                                        <button type="button" onclick="togglePw('newPwField','newPwEye')" tabindex="-1"
                                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                            <i class="fas fa-eye" id="newPwEye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button name="change_password" class="btn-green btn">Update Password</button>
                            </form>
                        </div>

                        <div class="settings-pane <?= $activeSettingsTab === 'addresses' ? 'active' : '' ?>" data-settings-pane="addresses">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                                <div class="section-title mb-0">
                                    <i class="fas fa-location-dot" style="color:var(--green);"></i>
                                    Addresses
                                </div>
                                <button type="button" class="btn-green btn btn-sm" onclick="openAddressForm()">
                                    <i class="fas fa-plus me-1"></i>Add Address
                                </button>
                            </div>

                            <div id="addressFormWrap" style="display:<?= (isset($_POST['save_address']) && $profileError) ? 'block' : 'none' ?>;">
                                <div class="address-card">
                                    <form method="POST" class="row g-3" id="addressForm">
                                        <input type="hidden" name="address_id" id="address_id" value="<?= htmlspecialchars($_POST['address_id'] ?? '') ?>">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">Label</label>
                                            <select class="form-select" name="address_label" id="address_label">
                                                <?php foreach (['Home','Work','Office','Other'] as $label): ?>
                                                    <option value="<?= $label ?>"><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">Phone Number</label>
                                            <input class="form-control" name="phone_num" id="addr_phone" inputmode="numeric" maxlength="11" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">Province</label>
                                            <select class="form-select" name="province" id="addr_province" required onchange="filterAddressCities()">
                                                <option value="">Select Province</option>
                                                <?php foreach ($provinces as $_p): ?>
                                                    <option value="<?= htmlspecialchars($_p) ?>"><?= htmlspecialchars($_p) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">City / Municipality</label>
                                            <select class="form-select" name="city_municipality" id="addr_city" required onchange="updateAddressZip(); filterAddressBarangays();">
                                                <option value="">Select City / Municipality</option>
                                                <?php foreach ($cities as $_c): ?>
                                                    <option value="<?= htmlspecialchars($_c) ?>"><?= htmlspecialchars($_c) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">Barangay</label>
                                            <select class="form-select" name="barangay" id="addr_barangay" required>
                                                <option value="">Select Barangay</option>
                                            </select>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">House / Street Address</label>
                                            <input class="form-control" name="st_address" id="addr_street" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold" style="color:var(--green);">ZIP Code</label>
                                            <input class="form-control" name="zip_code" id="addr_zip" inputmode="numeric" maxlength="4" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="d-inline-flex align-items-center gap-2 small fw-semibold" style="color:var(--green);">
                                                <input type="checkbox" name="is_default" id="addr_default" value="1"> Set as default address
                                            </label>
                                        </div>
                                        <div class="col-12 d-flex gap-2">
                                            <button class="btn-green btn" name="save_address">Save Address</button>
                                            <button class="btn btn-outline-secondary rounded-pill px-4" type="button" onclick="closeAddressForm()">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <?php if (empty($addresses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-map-location-dot"></i>
                                    <p>No saved addresses yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($addresses as $addr): ?>
                                    <?php $addrJson = htmlspecialchars(json_encode([
                                        'address_id' => $addr->address_id,
                                        'address_label' => $addr->address_label ?? 'Home',
                                        'phone_num' => $addr->phone_num ?? '',
                                        'province' => $addr->province ?? '',
                                        'city_municipality' => $addr->city_municipality ?? '',
                                        'barangay' => $addr->barangay ?? '',
                                        'st_address' => $addr->st_address ?? '',
                                        'zip_code' => $addr->zip_code ?? '',
                                        'is_default' => (int)($addr->is_default ?? 0),
                                    ]), ENT_QUOTES, 'UTF-8'); ?>
                                    <div class="address-card <?= (int)($addr->is_default ?? 0) === 1 ? 'default' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div>
                                                <span class="label-pill"><i class="fas fa-tag"></i><?= htmlspecialchars($addr->address_label ?? 'Home') ?></span>
                                                <?php if ((int)($addr->is_default ?? 0) === 1): ?>
                                                    <span class="badge rounded-pill text-bg-success ms-1">Default</span>
                                                <?php endif; ?>
                                                <div class="fw-bold mt-2" style="color:var(--deep);">
                                                    <?= htmlspecialchars($addr->st_address) ?>, <?= htmlspecialchars($addr->barangay) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars($addr->city_municipality) ?>, <?= htmlspecialchars($addr->province) ?> <?= htmlspecialchars($addr->zip_code) ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($addr->phone_num) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill" onclick="editAddress(this)" data-address="<?= $addrJson ?>">Edit</button>
                                                <?php if ((int)($addr->is_default ?? 0) !== 1): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="address_id" value="<?= htmlspecialchars($addr->address_id) ?>">
                                                        <button class="btn btn-sm btn-outline-success rounded-pill" name="set_default_address">Set Default</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return confirm('Delete this address?');">
                                                    <input type="hidden" name="address_id" value="<?= htmlspecialchars($addr->address_id) ?>">
                                                    <button class="btn btn-sm btn-outline-danger rounded-pill" name="delete_address">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="settings-pane <?= $activeSettingsTab === 'orders' ? 'active' : '' ?>" data-settings-pane="orders">
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
                                <label class="form-label small fw-semibold" style="color:var(--green);">Old Password</label>
                                <div class="position-relative">
                                    <input class="form-control" name="current_password" type="password" id="adminOldPwField"
                                        placeholder="Required when changing password" autocomplete="current-password">
                                    <button type="button" onclick="togglePw('adminOldPwField','adminOldPwEye')" tabindex="-1"
                                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                        <i class="fas fa-eye" id="adminOldPwEye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold" style="color:var(--green);">New Password</label>
                                <div class="position-relative">
                                    <input class="form-control" name="password" type="password" id="adminNewPwField"
                                        placeholder="Min 6 characters" autocomplete="new-password">
                                    <button type="button" onclick="togglePw('adminNewPwField','adminNewPwEye')" tabindex="-1"
                                        style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--green);cursor:pointer;">
                                        <i class="fas fa-eye" id="adminNewPwEye"></i>
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
            
        <?php endif; ?>

        <!-- ── CART SLIDE-OUT PANEL — hidden for admin ── -->
        <?php if ($userRole !== 'admin'):
            $initCartCount = 0;
            $initCartDistinct = 0;
            $initCartSubtotal = 0;
            if (!empty($_SESSION['cart'][$userEmail])) {
                $initCartDistinct = count($_SESSION['cart'][$userEmail]);
                foreach ($_SESSION['cart'][$userEmail] as $ci) {
                    $initCartCount += (int)($ci['qty'] ?? 1);
                    $initCartSubtotal += (float)($ci['price'] ?? 0) * (int)($ci['qty'] ?? 1);
                }
            }
        ?>
            <div id="cartPanel" style="
          position:fixed;top:0;right:-110vw;width:min(400px,100vw);height:100vh;
          background:#fff;z-index:10000;box-shadow:-8px 0 40px rgba(0,0,0,.18);
          display:flex;flex-direction:column;transition:right .35s cubic-bezier(.4,0,.2,1);
          border-radius:20px 0 0 20px;overflow:hidden;">

                <!-- Header -->
                <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);color:#fff;padding:20px 22px 16px;flex-shrink:0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <h5 style="margin:0;font-family:'Playfair Display',serif;font-weight:700;letter-spacing:1px;display:flex;align-items:center;gap:8px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1" />
                                    <circle cx="20" cy="21" r="1" />
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                                </svg>
                                My Cart
                            </h5>
                            <small id="cartItemCount" style="opacity:.75;font-size:.75rem;">
                                <?= $initCartCount === 0 ? 'Your cart is empty' : $initCartCount . ' item' . ($initCartCount === 1 ? '' : 's') . ' in cart' ?>
                            </small>
                        </div>
                        <button onclick="closeCart()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:36px;height:36px;font-size:1.1rem;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Select-All bar — only appears once there are 2+ distinct products in the cart -->
                <div id="cartSelectAllBar" style="display:<?= $initCartDistinct >= 2 ? 'flex' : 'none' ?>;align-items:center;justify-content:space-between;
            padding:10px 18px;background:#f0f7f0;border-bottom:1px solid rgba(45,90,45,.16);flex-shrink:0;">
                    <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:.82rem;font-weight:700;color:var(--green);margin:0;">
                        <input type="checkbox" id="cartSelectAllCheckbox" onchange="toggleSelectAll(this.checked)"
                            style="width:17px;height:17px;accent-color:var(--green);cursor:pointer;">
                        Select All
                    </label>
                    <span id="cartSelectAllCount" style="font-size:.74rem;color:#5a8a5a;font-weight:600;"></span>
                </div>

                <!-- Items list -->
                <div id="cartItems" style="flex:1;overflow-y:auto;padding:16px;background:#f9f9f6;">
                    <?php if (!empty($_SESSION['cart'][$userEmail])):
                        foreach ($_SESSION['cart'][$userEmail] as $ci):
                            $ciPrice  = (float)($ci['price'] ?? 0);
                            $ciQty    = (int)($ci['qty'] ?? 1);
                            $ciId     = (string)($ci['inv_id'] ?? '');
                            $ciTotal  = $ciPrice * $ciQty;
                            $ciStock  = $stockMap[$ciId] ?? 99;
                            $stockLabel = $ciStock === 0 ? 'Out of Stock' : ($ciStock <= 5 ? 'Low stock: ' . $ciStock . ' left' : 'In stock: ' . $ciStock);
                            $stockColor = $ciStock === 0 ? '#dc2626' : ($ciStock <= 5 ? '#f59e0b' : '#16a34a');
                    ?>
                        <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
                box-shadow:0 2px 10px rgba(0,0,0,.06);">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                <input type="checkbox" class="cart-select-checkbox" value="<?= htmlspecialchars($ciId) ?>"
                                    onchange="toggleCartSelection('<?= htmlspecialchars($ciId) ?>', this.checked)"
                                    style="width:18px;height:18px;accent-color:var(--green);flex-shrink:0;cursor:pointer;"
                                    aria-label="Select <?= htmlspecialchars($ci['name'] ?? 'item') ?> for checkout">
                                <img src="<?= htmlspecialchars($ci['image'] ?? '') ?>" alt=""
                                    style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                                    onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars($ci['name'] ?? '') ?>
                                    </div>
                                    <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱<?= number_format($ciPrice, 2) ?> each</div>
                                    <div style="font-size:.68rem;color:<?= $stockColor ?>;font-weight:600;margin-top:2px;">
                                        <?= $stockLabel ?>
                                    </div>
                                </div>
                                <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                                    ₱<?= number_format($ciTotal, 2) ?>
                                </div>
                            </div>
                            <!-- Qty stepper + remove row -->
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
                                <div style="display:flex;align-items:center;gap:0;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                                    <button onclick="cartQty('<?= $ciId ?>', 'minus')"
                                        style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                                    <span id="panel-qty-<?= $ciId ?>" style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;"><?= $ciQty ?></span>
                                    <button onclick="cartQty('<?= $ciId ?>', 'plus')"
                                        style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
                                </div>
                                <button onclick="cartQty('<?= $ciId ?>', 'remove')"
                                    style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                                    <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
                                </button>
                            </div>
                        </div>
                    <?php
                        endforeach;
                    else: ?>
                        <div style="text-align:center;padding:60px 20px;color:#bbb;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round" style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
                                <circle cx="9" cy="21" r="1" />
                                <circle cx="20" cy="21" r="1" />
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                            </svg>
                            <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer with subtotal + checkout -->
                <div id="cartFooter" style="padding:16px 20px;background:#fff;border-top:2px solid #f0f0eb;flex-shrink:0;<?= ($initCartSubtotal > 0) ? '' : 'display:none;' ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                        <span style="font-weight:600;color:#666;font-size:.85rem;">SUBTOTAL</span>
                        <span id="cartSubtotal" style="font-weight:800;color:#2d5a2d;font-size:1.15rem;">₱<?= number_format($initCartSubtotal) ?></span>
                    </div>
                    <div id="cartSelectionError" style="display:none;color:#b91c1c;background:#fee2e2;border-radius:10px;padding:8px 10px;font-size:.78rem;font-weight:700;margin-bottom:10px;text-align:center;">
                        Please select products first.
                    </div>
                    <a href="checkout.php" id="checkoutSelectedBtn" onclick="return goToSelectedCheckout(event)" style="display:block;background:var(--green);color:#fff;text-align:center;padding:14px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.95rem;transition:.2s;">
                        Checkout Now
                    </a>
                </div>
            </div>
            <div id="cartBackdrop" onclick="closeCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;backdrop-filter:blur(2px);"></div>
        <?php endif; /* end admin cart hide */ ?>

        <div id="toast-msg" class="toast-fixed"></div>

        

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

        

  <script>
    /* PHP-seeded globals for profile.js */
    const PROFILE_PROVINCE_CITIES = <?= json_encode($provinceCities, JSON_UNESCAPED_UNICODE) ?>;
    const PROFILE_CITY_ZIP_CODES  = <?= json_encode($cityZipCodes,   JSON_UNESCAPED_UNICODE) ?>;
    const PROFILE_CITY_BARANGAYS  = <?= json_encode($cityBarangays,  JSON_UNESCAPED_UNICODE) ?>;
    const PROFILE_ALL_CITIES      = <?= json_encode($cities,         JSON_UNESCAPED_UNICODE) ?>;
    let cartItemsJS = <?= json_encode(array_values(array_map(function ($i) {
      return ['inv_id' => (string)($i['inv_id'] ?? ''), 'name' => $i['name'] ?? '', 'price' => (float)($i['price'] ?? 0), 'qty' => (int)($i['qty'] ?? 1), 'image' => $i['image'] ?? ''];
    }, $_SESSION['cart'][$userEmail] ?? []))) ?>;
    const stockMapJS = <?= json_encode($stockMap) ?>;
    const PROFILE_USER_ROLE = <?= json_encode($userRole ?? 'user') ?>;
    const PROFILE_IS_LOGGED_IN = <?= json_encode((bool)$userEmail) ?>;
  </script>
  <script src="assets/js/profile.js"></script>
</body>

</html>