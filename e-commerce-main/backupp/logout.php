<?php
// ── logout.php ───────────────────────────────────────────────
// Clears only the logged-in user's session data.
// Inventory is stored in a JSON file and is NOT touched here —
// so products added by admin persist across all logouts/logins.
// ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear only login-related keys; leave inventory alone
$keysToRemove = ['logged_in_user', 'role', 'login_time'];
foreach ($keysToRemove as $key) {
    unset($_SESSION[$key]);
}

// Redirect to login page
header('Location: logsign.php');
exit;