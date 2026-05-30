<?php
session_start();

if (isset($_GET['index'])) {
    $index = $_GET['index'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // reindex
    }
}

header("Location: cart.php");
exit;
?>
