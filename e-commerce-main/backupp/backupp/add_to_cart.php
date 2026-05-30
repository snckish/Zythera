<?php
session_start();
?>
<?php
session_start();

// Example product data (normally from DB)
$productId = $_POST['product_id'];
$productName = $_POST['product_name'];
$productPrice = $_POST['product_price'];

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add product (simple array structure)
$_SESSION['cart'][] = [
    'id' => $productId,
    'name' => $productName,
    'price' => $productPrice
];

// Redirect back to cart page
header("Location: cart.php");
exit;
?>
