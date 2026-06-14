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
    $stmt = $db->prepare("
        SELECT o.order_id AS order_id, o.order_status AS status
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE u.email = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$userEmail]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders'  => $rows,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'orders' => []]);
}
