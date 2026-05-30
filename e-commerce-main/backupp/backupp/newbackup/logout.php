<?php
// ── logout.php ───────────────────────────────────────────────
// Clears login session data AND deletes all zafirah cookies.
// Inventory stays in JSON file — products persist across logins.
// Cart is persisted to carts.json BEFORE clearing session so
// items are restored on next login.
// ─────────────────────────────────────────────────────────────
require_once 'config.php'; // starts session + loads persistence helpers

// Clear cart from file on logout so it won't be restored on next login
if (!empty($_SESSION['logged_in_user'])) {
    $logoutEmail = $_SESSION['logged_in_user'];
    $allCarts = loadCarts();
    $allCarts[$logoutEmail] = [];
    $dir = dirname(CARTS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(CARTS_FILE, json_encode($allCarts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Clear ONLY login-related session keys — leave cart/orders/inventory intact
// so config.php reloads them from JSON on next login.
$keysToRemove = ['logged_in_user', 'role', 'login_time', 'session_start'];
foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// ── Delete all zafirah cookies by setting expiry in the past ──
$cookiesToClear = ['zafirah_user', 'zafirah_role', 'zafirah_name', 'zafirah_login'];
foreach ($cookiesToClear as $name) {
    setcookie($name, '', time() - 60, '/', '', false, true);
    unset($_COOKIE[$name]);
}

// Redirect to login page
header('Location: logsign.php');
exit;