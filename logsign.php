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
<style>
  :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Merriweather',serif}
  body{font-family:var(--ui-font);}
  h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.form-title,.visual-brand,footer .footer-brand{font-family:var(--logo-font)}
  p,small,.tagline,.auth-switch{font-family:var(--text-font)}
</style>
<style>
/* ── Reset ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --cream:#f5f2ec;--sage:#d4e4d4;--mid:#7aab7a;
  --green:#2d5a2d;--moss:#6f8f6b;--mist:#edf3e8;
  --deep:#1a2e1a;--white:#fff;
  /* ✦ Olive-green accent — warm but on-theme */
  --gold:#7a9e4e;--gold-light:#ddeec4;--gold-pale:#f3f8ec;
  --red:#dc2626;--radius:16px;
  --radius-card:18px;
  --shadow-card:0 2px 16px rgba(0,0,0,.07);
  --shadow-hover:0 12px 36px rgba(0,0,0,.13);
  /* ✦ Field transition timing */
  --field-transition:.22s cubic-bezier(.4,0,.2,1);
}
html,body{
  height:100%;
  overflow:hidden;
}
body{
  min-height:100dvh;
  background:#fff;
  font-family:var(--ui-font);
  display:block;
  padding:0;
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
  padding:7px 14px;border-radius:30px;transition:.2s;
}
.top-btn:hover{background:var(--sage);}

/* ── Layout ── */
.auth-shell{
  height:100dvh;
  display:grid;
  grid-template-columns:minmax(0,1fr) minmax(0,1fr);
  background:#fff;
  padding:0;gap:0;
}

/* ── Left visual panel ── */
.auth-visual{
  position:relative;height:100dvh;overflow:hidden;
  background:var(--deep);border-radius:0;
}
.auth-visual img{width:100%;height:100%;object-fit:cover;display:block;}
.auth-visual::after{
  content:'';position:absolute;inset:0;
  background:
    linear-gradient(180deg,rgba(26,46,26,.08),rgba(26,46,26,.54)),
    linear-gradient(90deg,rgba(26,46,26,.12),rgba(237,243,232,.08));
}
.visual-brand{
  position:absolute;top:26px;left:30px;z-index:1;
  display:flex;align-items:center;gap:10px;
  color:var(--deep);font-family:var(--logo-font);
  font-size:1.45rem;font-weight:700;letter-spacing:3px;text-shadow:none;
  background:rgba(255,255,255,.72);backdrop-filter:blur(8px);
  padding:8px 16px 8px 10px;border-radius:50px;
  box-shadow:0 2px 12px rgba(0,0,0,.10);
}
.visual-brand img{width:30px;height:30px;object-fit:contain;filter:none;}
.visual-copy{
  position:absolute;
  left:clamp(28px,8vw,116px);right:32px;bottom:54px;z-index:1;color:#fbfff8;
}
.visual-copy h2{
  font-family:var(--logo-font);
  font-size:clamp(1.45rem,2.5vw,2.25rem);
  line-height:1.15;margin-bottom:22px;
  text-shadow:0 2px 18px rgba(0,0,0,.28);
}

/* ── Right auth panel ── */
.auth-panel{
  height:100dvh;display:flex;align-items:center;justify-content:center;
  padding:36px 44px;
  background:linear-gradient(160deg,#ffffff 60%,var(--gold-pale) 100%);
  border-radius:0;
  overflow-y:auto;
}

/* ── Session timer bar ── */
.session-bar{position:fixed;top:0;left:0;right:0;height:3px;background:var(--sage);z-index:99;}
.session-bar-fill{height:100%;background:var(--green);transition:width 1s linear;}

/* ── Card — now elevated with shadow & border ── */
.card{
  width:100%;max-width:520px;
  display:flex;flex-direction:column;justify-content:center;
  background:#fff;
  border:1px solid rgba(122,158,78,.18);
  border-radius:24px;
  box-shadow:0 12px 52px rgba(45,90,45,.13), 0 2px 10px rgba(122,158,78,.10);
  padding:48px 52px 44px;
  position:relative;
  overflow:hidden;
}
/* subtle gold top-border accent */
.card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--green),var(--gold),var(--green));
}

.card-brand{
  font-family:var(--logo-font);color:var(--deep);
  font-size:1.7rem;font-weight:700;letter-spacing:3px;
  text-align:center;margin-bottom:6px;
}
.card-tagline{
  font-family:var(--text-font);text-align:center;
  color:#9a8f80;font-size:.78rem;margin-bottom:28px;
  font-style:italic;letter-spacing:.3px;
}

/* ── Tabs ── */
.tabs{
  display:flex;background:var(--sage);
  border-radius:50px;padding:4px;margin-bottom:28px;
}
.tabs button{
  flex:1;padding:11px;border:none;border-radius:50px;
  background:transparent;font-family:var(--ui-font);
  font-size:.9rem;font-weight:600;color:var(--green);
  cursor:pointer;transition:.25s;
}
.tabs button.active{
  background:var(--green);color:#fff;
  box-shadow:0 3px 12px rgba(45,90,45,.3);
}
.tabs button:not(.active):hover{background:rgba(45,90,45,.08);}

/* ── Form panels ── */
.form{display:none;}
.form.active{
  display:flex;flex-direction:column;
  animation:fadeForm .22s ease;
}
@keyframes fadeForm{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

.form-title{
  font-family:var(--logo-font);color:var(--green);
  font-size:1.72rem;line-height:1.15;font-weight:700;
  text-align:left;margin-bottom:22px;letter-spacing:0;
}

/* ── Inline error messages ── */
.field-error{
  display:none;font-size:.73rem;color:var(--red);
  margin-top:5px;padding-left:4px;
  font-family:var(--ui-font);
  animation:errSlide .18s ease;
}
.field-error.show{display:block;}
.field-error i{margin-right:4px;}
@keyframes errSlide{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}

/* ── Input fields ── */
.field{position:relative;margin-bottom:15px;}
.name-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px;}
.name-grid .field:last-child{grid-column:1 / -1;}

.field input,.field select{
  width:100%;padding:13px 16px;
  background:var(--mist);
  border:1.5px solid rgba(45,90,45,.10);
  border-radius:10px;outline:none;
  font-family:var(--ui-font);font-size:.88rem;
  color:var(--deep);
  transition:border-color var(--field-transition),
             background var(--field-transition),
             box-shadow var(--field-transition);
  appearance:none;
}
.field input::placeholder{color:#98a393;}

/* focus micro-interaction — green glow */
.field input:focus,.field select:focus{
  border-color:var(--green);background:#fff;
  box-shadow:0 0 0 3.5px rgba(45,90,45,.12);
}
/* error state */
.field input.has-error{
  border-color:var(--red);background:#fff8f8;
  box-shadow:0 0 0 3px rgba(220,38,38,.10);
}
.field label{
  position:static;display:block;margin-bottom:7px;
  font-size:.79rem;color:#4a5a4a;
  font-family:var(--ui-font);font-weight:500;
  pointer-events:none;transition:color .15s;
}

/* ── Submit button — enhanced hover ── */
.btn-submit{
  width:100%;padding:14px;border:none;border-radius:10px;
  font-family:var(--ui-font);font-size:.9rem;font-weight:700;
  cursor:pointer;margin-top:14px;letter-spacing:.2px;
  position:relative;overflow:hidden;
  transition:background .25s, box-shadow .25s, transform .18s;
}
.btn-submit.user{
  background:var(--green);color:#fff;
  box-shadow:0 4px 18px rgba(45,90,45,.25);
}
.btn-submit.user:hover{
  background:#235123;
  box-shadow:0 8px 28px rgba(45,90,45,.38);
  transform:translateY(-2px);
}
.btn-submit.user:active{transform:translateY(0);box-shadow:0 2px 10px rgba(45,90,45,.2);}
/* shimmer sweep on hover */
.btn-submit.user::after{
  content:'';position:absolute;top:0;left:-100%;
  width:60%;height:100%;
  background:linear-gradient(120deg,transparent,rgba(255,255,255,.18),transparent);
  transition:left .4s ease;
}
.btn-submit.user:hover::after{left:140%;}
.btn-submit.admin{background:#111827;color:#fff;}
.btn-submit.admin:hover{background:#0a1020;transform:translateY(-1px);}

/* ── "Sign up" / switch link — gold accent ── */
.auth-switch{
  font-family:var(--text-font);text-align:center;
  margin:22px 0 0;color:#6b7068;font-size:.79rem;
}
.auth-switch button{
  border:none;background:none;
  color:var(--gold);
  font-family:var(--ui-font);font-weight:700;
  cursor:pointer;padding:0 0 2px;
  border-bottom:1.5px solid rgba(181,132,58,.35);
  transition:color .2s,border-color .2s;
}
.auth-switch button:hover{
  color:#8c5e1e;
  border-bottom-color:#8c5e1e;
}

/* ── Password eye toggle ── */
.pw-wrap{position:relative;}
.pw-wrap input{padding-right:44px;}
.pw-eye{
  position:absolute;right:13px;bottom:12px;
  background:none;border:none;cursor:pointer;
  color:#b0b8b0;font-size:1rem;padding:4px;line-height:1;
  transition:color .2s;
}
.pw-eye:hover{color:var(--green);}

/* ── Remember me row ── */
.remember-row{
  display:flex;align-items:center;gap:8px;
  margin:4px 0 0;
}
.remember-row input[type=checkbox]{
  width:15px;height:15px;
  accent-color:var(--green);flex:0 0 auto;cursor:pointer;
}
.remember-row label{
  font-size:.78rem;color:#6b7068;
  font-family:var(--ui-font);cursor:pointer;
  user-select:none;margin:0;
}

/* ── Terms row ── */
.terms-row{
  display:flex;align-items:flex-start;gap:8px;
  margin-top:-2px;color:#5d6468;
  font-family:var(--text-font);font-size:.76rem;line-height:1.4;
}
.terms-row input{
  width:14px;height:14px;margin-top:2px;
  accent-color:var(--green);flex:0 0 auto;
}
.terms-row a{color:var(--gold);text-decoration:underline;text-underline-offset:2px;}
.terms-row a:hover{color:#8c5e1e;}

.form-footer{margin-top:20px;padding-top:0;}

/* ── Toast ── */
.toast{
  position:fixed;top:72px;right:20px;
  padding:13px 18px 13px 14px;border-radius:12px;color:#fff;
  font-size:.84rem;font-weight:500;z-index:9999;
  opacity:0;transform:translateY(-10px);
  transition:.3s;pointer-events:none;max-width:320px;
  display:flex;align-items:center;gap:10px;
  box-shadow:0 6px 24px rgba(0,0,0,.18);
}
.toast.show{opacity:1;transform:translateY(0);}
.toast.success{background:#15803d;}
.toast.error{background:var(--red);}
.toast i{font-size:1rem;flex-shrink:0;}

/* ── Divider between brand & form ── */
.brand-divider{
  width:40px;height:2px;
  background:linear-gradient(90deg,var(--green),var(--gold));
  border-radius:2px;margin:0 auto 22px;
}

/* ── Footer logo ── */
.footer-brand{
  display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:14px;font-family:'Playfair Display',serif;
  color:var(--deep);font-size:.78rem;font-weight:700;
  letter-spacing:2.5px;opacity:.55;transition:.3s;
}
.footer-logo{width:22px;height:22px;object-fit:contain;filter:none;transition:filter .3s;}
.brand-name{letter-spacing:2.5px;}

/* ── Responsive ── */
@media(max-width:800px){
  .auth-shell{grid-template-columns:1fr;padding:0;}
  .auth-visual{display:none;}
  .auth-panel{
    height:100dvh;padding:32px 22px;
    background:linear-gradient(160deg,#ffffff 50%,var(--dark--green) 100%);
  }
  .card{padding:28px 22px 24px;}
}
@media(max-width:500px){
  .card{max-width:100%;}
  .name-grid{grid-template-columns:1fr;gap:0;}
  .name-grid .field:last-child{grid-column:auto;}
}
@media(min-width:801px){.footer-brand{display:none;}}
.tagline{display:none;}

/* ── Dark mode ── */
body.dark{background:#1c3a1c;}
body.dark .auth-panel{background:linear-gradient(135deg,#172717 0%,#1c2a14 100%);}
body.dark .auth-visual::after{background:linear-gradient(180deg,rgba(12,24,12,.2),rgba(12,24,12,.62));}
body.dark .top-bar{background:rgba(15,28,15,.9);border-bottom:1px solid rgba(255,255,255,.07);}
body.dark .top-btn{color:#c8dfc8;}
body.dark .top-btn:hover{background:rgba(255,255,255,.1);}
body.dark .card{
  background:rgba(20,40,20,.82);
  border-color:rgba(122,158,78,.22);
  box-shadow:0 8px 40px rgba(0,0,0,.45),0 2px 8px rgba(122,158,78,.08);
  background:rgba(20,40,20,.72);
  backdrop-filter:blur(28px);
}
body.dark .card::before{background:linear-gradient(90deg,#2d5a2d,var(--gold),#2d5a2d);}
body.dark .card-brand{color:#d4edd4;}
body.dark .card-tagline{color:rgba(210,235,210,.55);}
body.dark .brand-divider{background:linear-gradient(90deg,#4a8a4a,var(--gold));}
body.dark .form-title{color:#a8d4a8;}
body.dark .auth-switch{color:rgba(220,240,220,.60);}
body.dark .auth-switch button{color:#c9a460;border-bottom-color:rgba(201,164,96,.35);}
body.dark .auth-switch button:hover{color:#e0c080;border-bottom-color:#e0c080;}
body.dark .tabs{background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.1);}
body.dark .tabs button{color:rgba(220,240,220,.75);}
body.dark .tabs button.active{background:#2d5a2d;color:#fff;box-shadow:0 3px 14px rgba(0,0,0,.4);}
body.dark .tabs button:not(.active):hover{background:rgba(255,255,255,.07);}
body.dark .field label{color:rgba(200,230,200,.60);}
body.dark .field input,
body.dark .field select{
  background:rgba(255,255,255,.08);color:#e8f5e8;
  border:1.5px solid rgba(255,255,255,.12);
}
body.dark .field input::placeholder{color:rgba(255,255,255,.28);}
body.dark .field input:focus,
body.dark .field select:focus{
  background:rgba(255,255,255,.14);
  border-color:rgba(180,220,180,.45);
  box-shadow:0 0 0 3.5px rgba(45,90,45,.22);
}
body.dark .field input.has-error{border-color:#f87171;background:rgba(220,38,38,.08);}
body.dark .footer-brand{color:rgba(200,230,200,.7);opacity:1;}
body.dark .footer-logo{filter:brightness(0) invert(1) opacity(.7);}
body.dark .btn-submit.user{background:#2d5a2d;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.35);}
body.dark .btn-submit.user:hover{background:#1e4a1e;box-shadow:0 8px 28px rgba(0,0,0,.5);}
body.dark .btn-submit.admin{background:#0f1f0f;color:#fff;}
body.dark .pw-eye{color:rgba(200,230,200,.5);}
body.dark .pw-eye:hover{color:#a8d4a8;}
body.dark .remember-row label{color:rgba(200,230,200,.55);}
body.dark .terms-row{color:rgba(200,230,200,.55);}
body.dark .terms-row a{color:#c9a460;}
body.dark .field-error{color:#f87171;}
</style>
<link rel="stylesheet" href="dark-mode.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="dark-mode.js"></script>
<script>
/* ZYTHERA dark mode — apply before paint to prevent flash */
(function(){
  var dark = localStorage.getItem('zythera_dark') === '1';
  if (dark) {
    document.documentElement.classList.add('zd');
    document.documentElement.style.background = '#111e11';
    if (document.body) document.body.classList.add('dark');
    document.addEventListener('DOMContentLoaded', function(){
      document.body.classList.add('dark');
      document.documentElement.style.background = '';
      var btn = document.getElementById('darkToggle');
      if (btn) btn.textContent = 'Light Mode';
    });
  } else {
    document.documentElement.style.background = '#ffffff';
  }
})();
function toggleDark(){
  var dark = !document.body.classList.contains('dark');
  document.documentElement.classList.toggle('zd', dark);
  document.body.classList.toggle('dark', dark);
  localStorage.setItem('zythera_dark', dark ? '1' : '0');
  var age = dark ? 60*60*24*365 : 0;
  document.cookie = 'zythera_dark=' + (dark ? '1' : '0') + ';path=/;max-age=' + age;
  document.documentElement.style.background = dark ? '#111e11' : '#ffffff';
  if (!dark) document.documentElement.style.background = '';
  var btn = document.getElementById('darkToggle');
  if(btn) btn.textContent = dark ? 'Light Mode' : 'Dark Mode';
}
</script>
<link rel="stylesheet" href="responsive.css">
</head>
<body>

<!-- Session timer progress bar -->
<div class="session-bar"><div class="session-bar-fill" id="sessionBarFill" style="width:100%;"></div></div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<?php if ($message): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>showToast(<?= json_encode($message) ?>,<?= json_encode($msgType) ?>));
</script>
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
function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon  = btn.querySelector('i');
  const show  = input.type === 'password';
  input.type  = show ? 'text' : 'password';
  icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Dark mode handled by inline script above
function switchTab(tab) {
  document.getElementById('loginForm').classList.toggle('active', tab==='login');
  document.getElementById('signupForm').classList.toggle('active',tab==='signup');
}

function showToast(msg, type='success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = 'toast ' + type + ' show';
  setTimeout(()=>t.classList.remove('show'), 5000);
}

// ── Session active indicator ─────────────────────────────────
// Cookie lasts 12 hours of inactivity — just show it as full/active.
(function() {
  const fill = document.getElementById('sessionBarFill');
  if (!fill) return;

  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  if (getCookie('zythera_user')) {
    fill.style.width = '100%';
    fill.style.background = '#2d5a2d';
  } else {
    fill.style.width = '0%';
  }
})();
</script>
</body>
</html>
