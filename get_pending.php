<?php
require 'config.php';
header('Content-Type: application/json');

$adminRole = $_SESSION['role'] ?? '';
if ($adminRole !== 'admin') {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $db    = getDBConnection();
    $stmt  = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending'");
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['count' => $count]);
} catch (PDOException $e) {
    echo json_encode(['count' => 0]);
}
