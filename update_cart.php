<?php
/**
 * update_cart.php
 * ──────────────────────────────────────────────────────────────
 * Handles cart mutations (plus / minus / remove) triggered by the
 * cart sidebar stepper buttons.  Responds with JSON so the client
 * can keep cartItemsJS perfectly in sync with the session.
 *
 * POST params:
 *   item_id    – product ID string (e.g. PRD-ZY001)
 *   qty_action – "plus" | "minus" | "remove"
 */

require 'config.php';
header('Content-Type: application/json');

/* ── Auth guard ─────────────────────────────────────────────── */
if (empty($_SESSION['logged_in_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$itemId    = trim($_POST['item_id']    ?? '');
$action    = trim($_POST['qty_action'] ?? '');

if ($itemId === '' || !in_array($action, ['plus', 'minus', 'remove'], true)) {
    echo json_encode(['success' => false, 'message' => 'Bad parameters.']);
    exit;
}

/* ── Always reload cart from session (single source of truth) ── */
$cart = loadCartForUser($userEmail);

if ($action === 'remove') {
    /* ── Remove item ─────────────────────────────────────────── */
    $cart = array_values(array_filter($cart, fn($i) => (string)($i['inv_id'] ?? '') !== $itemId));
} else {
    /* ── Plus / Minus ────────────────────────────────────────── */
    $found = false;
    foreach ($cart as &$item) {
        if ((string)($item['inv_id'] ?? '') !== $itemId) continue;
        $found = true;

        if ($action === 'plus') {
            /* Verify against live stock before incrementing */
            $db   = getDBConnection();
            $stmt = $db->prepare("SELECT prod_stock FROM product_inv WHERE prod_id = ? LIMIT 1");
            $stmt->execute([$itemId]);
            $row  = $stmt->fetch();
            $max  = $row ? (int)$row->prod_stock : 9999;
            $item['qty'] = min((int)$item['qty'] + 1, $max);
        } elseif ($action === 'minus') {
            $item['qty'] = max(1, (int)$item['qty'] - 1);
        }
        break;
    }
    unset($item);

    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
        exit;
    }
}

/* ── Persist to session ──────────────────────────────────────── */
saveCart($userEmail, $cart);
$_SESSION['cart'][$userEmail] = $cart;

/* ── Recompute totals ────────────────────────────────────────── */
$subtotal = 0;
$totalQty = 0;
foreach ($cart as $ci) {
    $q        = (int)($ci['qty']   ?? 1);
    $p        = (float)($ci['price'] ?? 0);
    $subtotal += $p * $q;
    $totalQty += $q;
}
$shipping = $subtotal > 0 ? 150 : 0;
$total    = $subtotal + $shipping;

echo json_encode([
    'success'      => true,
    'cart'         => array_values($cart),
    'total_items'  => count($cart),       // distinct product count
    'total_qty'    => $totalQty,          // sum of all quantities
    'subtotal'     => $subtotal,
    'shipping'     => $shipping,
    'total'        => $total,
]);
