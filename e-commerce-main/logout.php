<?php
// ── logout.php ───────────────────────────────────────────────
// Clears login session data AND deletes all zythera cookies.
// Cart is cleared from session on logout so it won't persist
// to the next login.
// ─────────────────────────────────────────────────────────────
require_once 'config.php';

// Clear cart from session on logout
if (!empty($_SESSION['logged_in_user'])) {
    $logoutEmail = $_SESSION['logged_in_user'];
    $_SESSION['cart'][$logoutEmail] = [];
}

// Clear ONLY login-related session keys
$keysToRemove = ['logged_in_user', 'role', 'login_time', 'session_start'];
foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// ── Delete all zythera cookies by setting expiry in the past ──
$cookiesToClear = ['zythera_user', 'zythera_role', 'zythera_name', 'zythera_login'];
foreach ($cookiesToClear as $name) {
    setcookie($name, '', time() - 60, '/', '', false, true);
    unset($_COOKIE[$name]);
}

// Redirect to login page
header('Location: logsign.php');
exit;