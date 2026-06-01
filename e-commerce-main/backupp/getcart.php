<?php
require 'config.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in_user'])) {
    echo json_encode(['success' => false, 'cart' => [], 'total_items' => 0]);
    exit;
}

$userEmail = $_SESSION['logged_in_user'];
$cart      = loadCartForUser($userEmail);

// Sync session
$_SESSION['cart'][$userEmail] = $cart;

$totalItemsCount = count($cart);

echo json_encode([
    'success'     => true,
    'cart'        => array_values($cart),
    'total_items' => $totalItemsCount,
]);