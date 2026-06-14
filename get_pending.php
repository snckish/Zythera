<?php
require 'config.php';
header('Content-Type: application/json');

$adminRole = $_SESSION['role'] ?? '';
if ($adminRole !== 'admin') {
    echo json_encode(['count' => 0, 'payment_count' => 0]);
    exit;
}

try {
    $db = getDBConnection();

    // Count orders with Pending order_status
    $stmt1  = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'");
    $count  = (int)$stmt1->fetchColumn();

    // Count orders whose payment is still pending verification
    $stmt2        = $db->query("SELECT COUNT(*) FROM payment WHERE payment_status = 'pending'");
    $paymentCount = (int)$stmt2->fetchColumn();

    echo json_encode(['count' => $count, 'payment_count' => $paymentCount]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0, 'payment_count' => 0]);
}
