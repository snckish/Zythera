<?php
require 'config.php';
header('Content-Type: application/json');

if (empty($_SESSION['logged_in_user'])) {
    echo json_encode(['success' => false, 'orders' => []]);
    exit;
}

$userEmail = $_SESSION['logged_in_user'];

try {
    $db   = getDBConnection();
    $stmt = $db->prepare("SELECT order_id, status FROM orders WHERE email = ? ORDER BY ord_no DESC");
    $stmt->execute([$userEmail]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders'  => $rows,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'orders' => []]);
}