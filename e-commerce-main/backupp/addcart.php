<?php
require 'config.php'; // starts session and loads users/inventory

header('Content-Type: application/json');

// ── 1. Must be logged in ──────────────────────────────────────────────────────
$userEmail = $_SESSION['logged_in_user'] ?? null;
if (!$userEmail) {
    echo json_encode(['success' => false, 'redirect' => 'logsign.php']);
    exit;
}

// ── 2. Validate input ─────────────────────────────────────────────────────────
$id    = isset($_POST['id'])    ? (int)$_POST['id']        : 0;
$name  = isset($_POST['name'])  ? trim($_POST['name'])      : '';
$price = isset($_POST['price']) ? (float)$_POST['price']   : 0.0;
$qty   = isset($_POST['qty'])   ? max(1, (int)$_POST['qty']): 1;

if (!$id || !$name || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product data.']);
    exit;
}

// ── 3. Stock check ────────────────────────────────────────────────────────────
$inventory = $_SESSION['inventory'] ?? [];
$stockItem = null;
foreach ($inventory as $inv) {
    $inv = (object)$inv;
    if ((int)$inv->id === $id) { $stockItem = $inv; break; }
}

if ($stockItem && (int)$stockItem->stock === 0) {
    echo json_encode(['success' => false, 'message' => 'Sorry, this item is out of stock.']);
    exit;
}

// ── 4. Init cart for this user ────────────────────────────────────────────────
if (!isset($_SESSION['cart'][$userEmail])) {
    $_SESSION['cart'][$userEmail] = [];
}

$cart = &$_SESSION['cart'][$userEmail];

// ── 5. Add or increment quantity ──────────────────────────────────────────────
$found = false;
foreach ($cart as &$item) {
    if (is_array($item) && (int)$item['id'] === $id) {
        $item['qty'] = (int)$item['qty'] + $qty;
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $cart[] = [
        'id'    => $id,
        'name'  => htmlspecialchars($name),
        'price' => $price,
        'qty'   => $qty,
    ];
}

// ── 6. Total item count (for badge in website.php) ───────────────────────────
$totalItems = 0;
foreach ($cart as $cartItem) {
    $totalItems += is_array($cartItem) ? (int)($cartItem['qty'] ?? 1) : 1;
}

echo json_encode([
    'success'     => true,
    'total_items' => $totalItems,
    'message'     => htmlspecialchars($name) . ' added to cart!',
]);
exit;
?>