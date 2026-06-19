<?php
require 'config.php';

$message = '';
$msgType = '';
$activeTab = 'login';

// ── Cookie-based auto-restore ─────────────────────────────────
if (empty($_SESSION['logged_in_user']) && !empty($_COOKIE['zythera_user'])) {

    $cEmail = $_COOKIE['zythera_user'];
    $cRole  = $_COOKIE['zythera_role'] ?? '';

    $checkUser = findAccountByEmail($cEmail);

    if ($checkUser && $checkUser->role === $cRole) {

        $_SESSION['logged_in_user'] = $cEmail;
        $_SESSION['role']           = $cRole;
        $_SESSION['login_time']     = $_COOKIE['zythera_login'] ?? date('h:i A');

        if (!isset($_SESSION['cart'][$cEmail])) {
            $_SESSION['cart'][$cEmail] = [];
        }

        if (!isset($_SESSION['orders'][$cEmail])) {
            $_SESSION['orders'][$cEmail] = [];
        }

        if (!isset($_SESSION['profile_pic'][$cEmail])) {
            $_SESSION['profile_pic'][$cEmail] = null;
        }
    }
}

// ── Session expired message ───────────────────────────────────
if (isset($_GET['expired'])) {

    $message = "We couldn't find your account session — please log in again.";
    $msgType = 'error';
}

// ── FORM SUBMIT ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── SIGNUP ────────────────────────────────────────────────
    if (isset($_POST['signup'])) {

        $fname = trim($_POST['fname'] ?? '');
        $mname = trim($_POST['mname'] ?? '');
        $lname = trim($_POST['lname'] ?? '');

        if (!$fname || !$lname || !$email || !$password) {

            $message = 'Please complete all required fields.';
            $msgType = 'error';
            $activeTab = 'signup';

        } else {

            $db = getDBConnection();

            $check = $db->prepare("
                SELECT email
                FROM users
                WHERE email = ?
            ");

            $check->execute([$email]);

            $adminExists = isAdminEmail($email);
            if (!$adminExists && tableExists('admins')) {
                $adminCheck = $db->prepare("SELECT email FROM admins WHERE email = ?");
                $adminCheck->execute([$email]);
                $adminExists = (bool)$adminCheck->fetch();
            }

            if ($check->fetch() || $adminExists) {

                $message = 'Email already registered!';
                $msgType = 'error';
                $activeTab = 'signup';

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $newUserId = generateCustomId('U');
                $stmt = $db->prepare("
                    INSERT INTO users
                    (
                        user_id,
                        fname,
                        mname,
                        lname,
                        email,
                        password
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $newUserId,
                    $fname,
                    $mname !== '' ? $mname : null,
                    $lname,
                    $email,
                    $hashedPassword
                ]);

                // Auto-login: set session and cookies identical to the login flow
                $fullName = trim("$fname $lname");
                $exp = time() + 43200;

                $_SESSION['logged_in_user'] = $email;
                $_SESSION['role']           = 'user';
                $_SESSION['login_time']     = date('h:i A');
                $_SESSION['session_start']  = time();

                setcookie('zythera_user',  $email,         $exp, '/');
                setcookie('zythera_role',  'user',         $exp, '/');
                setcookie('zythera_name',  $fullName,      $exp, '/');
                setcookie('zythera_login', date('h:i A'),  $exp, '/');

                if (!isset($_SESSION['cart'][$email]))        $_SESSION['cart'][$email]        = [];
                if (!isset($_SESSION['orders'][$email]))      $_SESSION['orders'][$email]      = [];
                if (!isset($_SESSION['profile_pic'][$email])) $_SESSION['profile_pic'][$email] = null;

                header('Location: website.php');
                exit;
            }
        }
    }

    // ── LOGIN ────────────────────────────────────────────────
    if (isset($_POST['login'])) {

        try {

            $user = findAccountByEmail($email);

            if (!$user) {

                $message = 'Email not found.';
                $msgType = 'error';

            } else {

                if (password_verify($password, $user->password)) {

                    $_SESSION['logged_in_user'] = $user->email;
                    $_SESSION['role']           = $user->role;
                    $_SESSION['login_time']     = date('h:i A');
                    $_SESSION['session_start']  = time();

                    $exp = time() + 43200;

                    setcookie(
                        'zythera_user',
                        $user->email,
                        $exp,
                        '/'
                    );

                    setcookie(
                        'zythera_role',
                        $user->role,
                        $exp,
                        '/'
                    );

                    setcookie(
                        'zythera_name',
                        $user->name,
                        $exp,
                        '/'
                    );

                    setcookie(
                        'zythera_login',
                        date('h:i A'),
                        $exp,
                        '/'
                    );

                    if (!isset($_SESSION['cart'][$user->email])) {
                        $_SESSION['cart'][$user->email] = [];
                    }

                    if (!isset($_SESSION['orders'][$user->email])) {
                        $_SESSION['orders'][$user->email] = [];
                    }

                    if (!isset($_SESSION['profile_pic'][$user->email])) {
                        $_SESSION['profile_pic'][$user->email] = null;
                    }

                    header(
                        'Location: ' .
                        (
                            $user->role === 'admin'
                            ? 'admin.php'
                            : 'website.php'
                        )
                    );

                    exit;

                } else {

                    $message = 'Invalid password.';
                    $msgType = 'error';
                }
            }

        } catch (PDOException $e) {

            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

// ── AUTO REDIRECT IF LOGGED IN ───────────────────────────────
if (!empty($_SESSION['logged_in_user'])) {

    $loggedInRole = $_SESSION['role'] ?? (isAdminEmail($_SESSION['logged_in_user']) ? 'admin' : 'user');

    header(
        'Location: ' .
        (
            $loggedInRole === 'admin'
            ? 'admin.php'
            : 'website.php'
        )
    );

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title> ZYTHERA </title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/logsign.css">
</head>
<body>

<!-- Session timer progress bar -->
<div class="session-bar"><div class="session-bar-fill" id="sessionBarFill" style="width:100%;"></div></div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<?php if ($message): ?>

<?php endif; ?>

<main class="auth-shell">
<section class="auth-visual" aria-label="Zythera furniture showcase">
  <img src="pci/scandinavian-interior-mockup-wall-decal-background 1.png" alt="Styled living room furniture">
  <div class="visual-brand">
    <img src="pci/Group_15.png" alt="">
    <span>ZYTHERA</span>
  </div>
  <div class="visual-copy">
    <h2>Curated Comfort,<br>Quietly Beautiful</h2>
  </div>
</section>

<section class="auth-panel">
<div class="card">
  <div class="card-brand">ZYTHERA</div>
  <p class="card-tagline">Furniture crafted for lives that deserve beauty.</p>

  <!-- LOGIN FORM -->
  <form id="loginForm" class="form<?= $activeTab === 'login' ? ' active' : '' ?>" method="POST" novalidate>
    <h2 class="form-title">Welcome back</h2>

    <div class="field">
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter your mail" required autocomplete="email" value="<?= $activeTab === 'login' ? htmlspecialchars($email ?? '') : '' ?>">
    </div>

    <div class="field pw-wrap">
      <label>Password</label>
      <input type="password" name="password" id="loginPw" placeholder="Enter your password" required autocomplete="current-password">
      <button type="button" class="pw-eye" onclick="togglePw('loginPw',this)" tabindex="-1" aria-label="Toggle password visibility">
        <i class="fas fa-eye"></i>
      </button>
    </div>

    <button type="submit" name="login" class="btn-submit user" id="loginBtn">Log in</button>
    <div class="form-footer">
      <p class="auth-switch">Don't have an account? <button type="button" onclick="switchTab('signup')">Sign up</button></p>
    </div>
  </form>

  <!-- SIGNUP FORM -->
  <form id="signupForm" class="form<?= $activeTab === 'signup' ? ' active' : '' ?>" method="POST" novalidate>
    <h2 class="form-title">Create an account</h2>

    <div class="name-grid">
      <div class="field">
        <label>First Name</label>
        <input type="text" name="fname" placeholder="Enter first name" required autocomplete="given-name" value="<?= $activeTab === 'signup' ? htmlspecialchars($_POST['fname'] ?? '') : '' ?>">
      </div>

      <div class="field">
        <label>Middle Name (optional)</label>
        <input type="text" name="mname" placeholder="Enter middle name" autocomplete="additional-name" value="<?= $activeTab === 'signup' ? htmlspecialchars($_POST['mname'] ?? '') : '' ?>">
      </div>

      <div class="field">
        <label>Last Name</label>
        <input type="text" name="lname" placeholder="Enter last name" required autocomplete="family-name" value="<?= $activeTab === 'signup' ? htmlspecialchars($_POST['lname'] ?? '') : '' ?>">
      </div>
    </div>

    <div class="field">
      <label>Email</label>
      <input type="email" name="email" placeholder="Enter your mail" required autocomplete="email" value="<?= $activeTab === 'signup' ? htmlspecialchars($email ?? '') : '' ?>">
    </div>

    <div class="field pw-wrap">
      <label>Password</label>
      <input type="password" name="password" id="signupPw" placeholder="Enter your password" required autocomplete="new-password" minlength="6">
      <button type="button" class="pw-eye" onclick="togglePw('signupPw',this)" tabindex="-1" aria-label="Toggle password visibility">
        <i class="fas fa-eye"></i>
      </button>
    </div>

    <label class="terms-row">
      <input type="checkbox" name="terms" required>
      <span>I agree to all the <a href="#">Terms &amp; Conditions</a></span>
    </label>

    <button type="submit" name="signup" class="btn-submit user" id="signupBtn">
      Sign up
    </button>
    <div class="form-footer">
      <p class="auth-switch">Already have an account? <button type="button" onclick="switchTab('login')">Log in</button></p>
    </div>
  </form>

<div class="footer-brand">
  <img src="pci/Group_15.png" class="footer-logo">
  <span class="brand-name"><span style="font-family:'Playfair Display',serif;color:#1a2e1a;font-weight:700;"> ZYTHERA </span></span>
</div>
</div>
</section>
</main>

  <script>
    /* PHP-seeded globals for logsign.js */
    const LOGSIGN_TOAST_MSG  = <?= json_encode($message  ?? '') ?>;
    const LOGSIGN_TOAST_TYPE = <?= json_encode($msgType  ?? '') ?>;
  </script>
  <script src="assets/js/logsign.js"></script>
</body>
</html>
