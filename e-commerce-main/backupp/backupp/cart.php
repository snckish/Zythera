<?php
session_start();
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html>
<head>
  <title>My Cart</title>
</head>
<body>
  <!-- Cart icon -->
  <a href="cart.php" class="position-relative text-decoration-none fs-5" title="Cart">
    🛒
    <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success"
          style="font-size:.55rem;">
          <?= $cartCount ?>
    </span>
  </a>

  <h2>Your Cart</h2>
  <?php if ($cartCount > 0): ?>
    <ul>
      <?php foreach ($_SESSION['cart'] as $index => $item): ?>
        <li>
          <?= htmlspecialchars($item['name']) ?> - $<?= htmlspecialchars($item['price']) ?>
          <a href="remove_from_cart.php?index=<?= $index ?>">Remove</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>Your cart is empty.</p>
  <?php endif; ?>
</body>
</html>
