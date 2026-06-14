<?php
require 'config.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in_user'])) {
    echo json_encode(['success' => false, 'redirect' => 'logsign.php']);
    exit;
}

$userEmail = $_SESSION['logged_in_user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$productId = trim($_POST['inv_id'] ?? ($_POST['id'] ?? ''));
$name      = trim($_POST['name']     ?? '');
$price     = (float)($_POST['price'] ?? 0);
$qty       = max(1, (int)($_POST['qty'] ?? 1));
$image     = trim($_POST['image']    ?? '');

if ($productId === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid product parameters.']);
    exit;
}

$db   = getDBConnection();
$stmt = $db->prepare("SELECT prod_stock AS stock, prod_name AS name, unit_price AS price, img_url AS image FROM product_inv WHERE prod_id = ? LIMIT 1");
$stmt->execute([$productId]);
$invItem = $stmt->fetch();

if (!$invItem) {
    echo json_encode(['success' => false, 'message' => 'Product not found in inventory.']);
    exit;
}

$availableStock = (int)$invItem->stock;
if ($availableStock <= 0) {
    echo json_encode(['success' => false, 'message' => 'This item is currently out of stock.']);
    exit;
}

$name  = $name  ?: $invItem->name;
$price = $price ?: (float)$invItem->price;
$image = $image ?: $invItem->image;

$cart = loadCartForUser($userEmail);

$found = false;
foreach ($cart as &$item) {
    if ((string)$item['inv_id'] === $productId) {
        $newQty      = (int)$item['qty'] + $qty;
        $item['qty'] = min($newQty, $availableStock);
        $found       = true;
        break;
    }
}
unset($item);

if (!$found) {
    $cart[] = [
        'inv_id' => $productId,
        'name'   => $name,
        'price'  => $price,
        'qty'    => min($qty, $availableStock),
        'image'  => $image,
    ];
}

saveCart($userEmail, $cart);
$_SESSION['cart'][$userEmail] = $cart;

$totalItemsCount = count($cart);

echo json_encode([
    'success'     => true,
    'total_items' => $totalItemsCount,
    'cart'        => array_values($cart),
]);
exit;