<?php
require 'config.php';

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── SIGN UP ──────────────────────────────────────────────
    if (isset($_POST['signup'])) {
        $name = trim($_POST['name'] ?? '');
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        if (!$email || !$password || !$name) {
            $message = 'Please complete all fields.';
            $msgType  = 'error';
        } elseif (isset($_SESSION['users'][$email])) {
            $message = 'Email already registered!';
            $msgType  = 'error';
        } else {
            $_SESSION['users'][$email] = [
                'name'     => $name,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role'     => $role,
            ];
            // init per-user data
            $_SESSION['cart'][$email]        = [];
            $_SESSION['orders'][$email]      = [];
            $_SESSION['profile_pic'][$email] = null;

            $message = 'Account created as ' . strtoupper($role) . '! You can now log in.';
            $msgType  = 'success';
        }
    }

    // ── LOGIN ────────────────────────────────────────────────
    if (isset($_POST['login'])) {
        if (
            isset($_SESSION['users'][$email]) &&
            password_verify($password, $_SESSION['users'][$email]['password'])
        ) {
            $_SESSION['logged_in_user'] = $email;
            $_SESSION['role']           = $_SESSION['users'][$email]['role'];
            $_SESSION['login_time']     = date('h:i A');

            // ensure cart/orders exist
            if (!isset($_SESSION['cart'][$email]))        $_SESSION['cart'][$email]        = [];
            if (!isset($_SESSION['orders'][$email]))      $_SESSION['orders'][$email]      = [];
            if (!isset($_SESSION['profile_pic'][$email])) $_SESSION['profile_pic'][$email] = null;

            header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'website.php'));
            exit;
        } else {
            $message = 'Invalid email or password.';
            $msgType  = 'error';
        }
    }
}

if (!empty($_SESSION['logged_in_user'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'website.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ZAFIRAH | Login / Sign Up</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#f5f2ec;--sage:#d4e4d4;--mid:#7aab7a;
  --green:#2d5a2d;--deep:#1a2e1a;--white:#fff;
  --red:#dc2626;--radius:16px;
}
body{
  min-height:100vh;
  background:linear-gradient(135deg,#c8dcc8 0%,#f5f2ec 60%,#e8d8c8 100%);
  font-family:'DM Sans',sans-serif;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:80px 16px 32px;
}

/* ── Top Bar ── */
.top-bar{
  position:fixed;top:0;left:0;right:0;
  display:flex;justify-content:space-between;align-items:center;
  padding:12px 24px;
  background:rgba(255,255,255,.75);
  backdrop-filter:blur(12px);
  border-bottom:1px solid rgba(0,0,0,.06);
  z-index:100;
}
.top-btn{
  display:flex;align-items:center;gap:7px;
  text-decoration:none;font-size:.85rem;font-weight:500;
  color:var(--green);cursor:pointer;border:none;background:none;
  padding:7px 14px;border-radius:30px;
  transition:.2s;
}
.top-btn:hover{background:var(--sage);}

/* ── Card ── */
.card{
  width:100%;max-width:460px;
  background:rgba(255,255,255,.92);
  backdrop-filter:blur(20px);
  border-radius:28px;
  box-shadow:0 16px 48px rgba(45,90,45,.15);
  padding:40px 40px 36px;
}
@media(max-width:500px){.card{padding:28px 20px 24px;}}

.brand{
  font-family:'Playfair Display',serif;
  color:var(--green);font-size:2.2rem;
  text-align:center;letter-spacing:3px;
  margin-bottom:4px;
}
.tagline{text-align:center;color:#888;font-size:.82rem;margin-bottom:28px;}

/* ── Tabs ── */
.tabs{
  display:flex;background:var(--sage);
  border-radius:50px;padding:4px;margin-bottom:28px;
}
.tabs button{
  flex:1;padding:11px;border:none;border-radius:50px;
  background:transparent;font-family:'DM Sans',sans-serif;
  font-size:.9rem;font-weight:600;color:var(--green);
  cursor:pointer;transition:.25s;
}
.tabs button.active{
  background:var(--green);color:#fff;
  box-shadow:0 3px 12px rgba(45,90,45,.3);
}

/* ── Form panels ── */
.form{display:none;}
.form.active{display:block;}

/* ── Floating-label input ── */
.field{position:relative;margin-bottom:18px;}
.field input,.field select{
  width:100%;padding:15px 14px 7px;
  background:var(--sage);border:2px solid transparent;
  border-radius:var(--radius);outline:none;
  font-family:'DM Sans',sans-serif;font-size:.95rem;
  color:var(--deep);transition:.2s;appearance:none;
}
.field input:focus,.field select:focus{
  border-color:var(--green);background:#fff;
}
.field label{
  position:absolute;left:14px;top:14px;
  font-size:.85rem;color:#999;pointer-events:none;
  transition:.2s;background:transparent;
}
.field input:focus~label,
.field input:not(:placeholder-shown)~label{
  top:4px;font-size:.68rem;color:var(--green);font-weight:600;
}
.field select~label{
  top:4px;font-size:.68rem;color:var(--green);font-weight:600;
}

/* ── Submit ── */
.btn-submit{
  width:100%;padding:14px;border:none;border-radius:50px;
  font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;
  cursor:pointer;transition:.25s;margin-top:4px;letter-spacing:.5px;
}
.btn-submit.user {background:var(--green);color:#fff;}
.btn-submit.admin{background:#111827;color:#fff;}
.btn-submit:hover{opacity:.88;transform:translateY(-1px);}

/* ── Toast ── */
.toast{
  position:fixed;top:72px;right:20px;
  padding:14px 20px;border-radius:14px;color:#fff;
  font-size:.86rem;font-weight:500;z-index:9999;
  opacity:0;transform:translateY(-10px);
  transition:.3s;pointer-events:none;max-width:300px;
}
.toast.show{opacity:1;transform:translateY(0);}
.toast.success{background:#16a34a;}
.toast.error  {background:var(--red);}

/* ── Footer logo ── */
.footer-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px; /* space between logo & text */
  margin-top: 24px;
  font-family: 'Playfair Display', serif;
  color: var(--green);
  font-size: 0.9rem;
  opacity: 0.6;
}

.footer-logo {
  width: 28px;   /* control size */
  height: 28px;
  object-fit: contain;
}

.brand-name {
  letter-spacing: 2px;
}

/* ── Dark mode ── */
body.dark{background:linear-gradient(135deg,#1a2e1a,#243324);}
body.dark .card{background:rgba(36,51,36,.95);}
body.dark .field input,body.dark .field select{background:#1a2e1a;color:#d4e4d4;}
body.dark .field input:focus,body.dark .field select:focus{background:#243324;border-color:var(--mid);}
body.dark .tagline,body.dark .field label{color:#7aab7a;}
body.dark .top-btn{color:#d4e4d4;}
body.dark .top-btn:hover{background:rgba(255,255,255,.1);}
body.dark .tabs{background:#1a2e1a;}
body.dark .tabs button{color:#d4e4d4;}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="top-bar">
  <a href="website.php" class="top-btn">Back to Home</a>
  <button class="top-btn" onclick="document.body.classList.toggle('dark')">🌓 Dark Mode</button>
</div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<?php if ($message): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($message) ?>,<?= json_encode($msgType) ?>));
</script>
<?php endif; ?>

<!-- CARD -->
<div class="card">
  <div class="brand">ZAFIRAH</div>
  <p class="tagline">Furniture crafted for lives that deserve beauty.</p>

  <!-- TABS -->
  <div class="tabs">
    <button id="loginTab"  class="active" onclick="switchTab('login')">Login</button>
    <button id="signupTab"               onclick="switchTab('signup')">Sign Up</button>
  </div>

  <!-- LOGIN FORM -->
  <form id="loginForm" class="form active" method="POST" novalidate>

    <div class="field">
      <select name="role" aria-label="Login role">
        <option value="user">Login as User</option>
        <option value="admin">Login as Admin</option>
      </select>
      <label>Role</label>
    </div>

    <div class="field">
      <input type="email" name="email" placeholder=" " required autocomplete="email">
      <label>Email Address</label>
    </div>

    <div class="field">
      <input type="password" name="password" placeholder=" " required autocomplete="current-password">
      <label>Password</label>
    </div>

    <button type="submit" name="login" class="btn-submit user" id="loginBtn">Login</button>
  </form>

  <!-- SIGNUP FORM -->
  <form id="signupForm" class="form" method="POST" novalidate>

    <div class="field">
      <select name="role" id="signupRole" onchange="updateSignupBtn()" aria-label="Account role">
        <option value="user">Sign up as User</option>
        <option value="admin">Sign up as Admin</option>
      </select>
      <label>Account Type</label>
    </div>

    <div class="field">
      <input type="text" name="name" placeholder=" " required autocomplete="name">
      <label>Full Name</label>
    </div>

    <div class="field">
      <input type="email" name="email" placeholder=" " required autocomplete="email">
      <label>Email Address</label>
    </div>

    <div class="field">
      <input type="password" name="password" placeholder=" " required autocomplete="new-password"
             minlength="6">
      <label>Password (min 6 chars)</label>
    </div>

    <button type="submit" name="signup" class="btn-submit user" id="signupBtn">
      Create Account
    </button>
  </form>

<div class="footer-brand">
  <img src="/php_work/e-commerce/pci/Group_15.svg" class="footer-logo">
  <span class="brand-name">ZAFIRAH</span>
</div>

<script>
function switchTab(tab) {
  document.getElementById('loginTab').classList.toggle('active',  tab==='login');
  document.getElementById('signupTab').classList.toggle('active', tab==='signup');
  document.getElementById('loginForm').classList.toggle('active', tab==='login');
  document.getElementById('signupForm').classList.toggle('active',tab==='signup');
}

function updateSignupBtn() {
  const role = document.getElementById('signupRole').value;
  const btn  = document.getElementById('signupBtn');
  if (role === 'admin') {
    btn.textContent = 'Create Admin Account';
    btn.className   = 'btn-submit admin';
  } else {
    btn.textContent = 'Create Account';
    btn.className   = 'btn-submit user';
  }
}

function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast ' + type + ' show';
  setTimeout(()=>t.classList.remove('show'), 4000);
}
</script>
</body>
</html>