<?php
// ── addcart.php ───────────────────────────────────────────────
// AJAX endpoint called by website.php when a user clicks "Add to Cart".
// Always responds with clean JSON payloads.
// ─────────────────────────────────────────────────────────────
require 'config.php';
header('Content-Type: application/json');

// Must be logged in
if (empty($_SESSION['logged_in_user'])) {
    echo json_encode(['success' => false, 'redirect' => 'logsign.php']);
    exit;
}

$userEmail = $_SESSION['logged_in_user'];

// Only POST accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request strategy.']);
    exit;
}

$productId = (int)($_POST['id']    ?? 0);
$name      = trim($_POST['name']   ?? '');
$price     = (float)($_POST['price'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? 1));
$image     = trim($_POST['image']  ?? '');

if ($productId <= 0 || $name === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid product parameters.']);
    exit;
}

// FIX: Query the live database directly instead of counting on broken $_SESSION['inventory'] array state
$db = getDBConnection();
$stmt = $db->prepare("SELECT stock FROM inventory WHERE id = ? LIMIT 1");
$stmt->execute([$productId]);
$invItem = $stmt->fetch();

if (!$invItem) {
    echo json_encode(['success' => false, 'message' => 'Requested item was not found inside inventory.']);
    exit;
}

$availableStock = (int)$invItem->stock;
if ($availableStock <= 0) {
    echo json_encode(['success' => false, 'message' => 'This spectacular item is currently out of stock.']);
    exit;
}

// Load current cart from database fallback configuration
if (!isset($_SESSION['cart'][$userEmail])) {
    $_SESSION['cart'][$userEmail] = loadCartForUser($userEmail);
}
$cart = &$_SESSION['cart'][$userEmail];

// Find if product already exists inside cart session context array
$found = false;
foreach ($cart as &$item) {
    if ((int)$item['id'] === $productId) {
        $newQty = (int)$item['qty'] + $qty;
        $item['qty'] = min($newQty, $availableStock);
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $cart[] = [
        'id'    => $productId,
        'name'  => $name,
        'price' => $price,
        'qty'   => min($qty, $availableStock),
        'color' => '',
        'image' => $image,
    ];
}

// Persist live back to db using configuration schema
saveCartForUser($userEmail, $cart);

// Count total individual parts
$totalItemsCount = 0;
foreach ($cart as $item) {
    $totalItemsCount += (int)($item['qty'] ?? 1);
}

echo json_encode([
    'success' => true,
    'total_items' => $totalItemsCount
]);
exit;