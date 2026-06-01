<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inv_id = $_POST['inv_id'] ?? ($_POST['id'] ?? '');

    $rawPrice = $_POST['price'] ?? '0';
    $cleanPrice = str_replace([',', '₱', ' '], '', $rawPrice);

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $cleanPrice)) {
        $cleanPrice = preg_replace('/[^\d.]/', '', $cleanPrice);
    }

    $category = $_POST['category'] ?? 'Sofa';
    if (strcasecmp($category, 'sofa') === 0) {
        $category = 'Sofa';
    } elseif (strcasecmp($category, 'chair') === 0) {
        $category = 'Chair';
    } elseif (strcasecmp($category, 'set') === 0) {
        $category = 'Set';
    }

    $description = $_POST['description'] ?? '';
    if (str_word_count($description) < 3) {
        $description .= ' (short description)';
    }

    $imagePath = $_POST['image'] ?? '';
    if (strpos($imagePath, 'pci/') === false && strpos($imagePath, 'http') === false) {
        $imagePath = 'pci/' . ltrim($imagePath, '/');
    }

    try {
        $db = getDBConnection();

        if ($inv_id !== '') {
            $stmt = $db->prepare("
                UPDATE inventory SET
                    name = ?,
                    size = ?,
                    color = ?,
                    price = ?,
                    description = ?,
                    stock = ?,
                    category = ?,
                    image = ?
                WHERE inv_id = ?
            ");
            $stmt->execute([
                sanitize($_POST['name'] ?? ''),
                sanitize($_POST['size'] ?? ''),
                sanitize($_POST['color'] ?? ''),
                (float)$cleanPrice,
                sanitize($description),
                (int)($_POST['stock'] ?? 0),
                $category,
                $imagePath,
                (int)$inv_id
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO inventory (name, size, color, price, description, stock, category, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($_POST['name'] ?? ''),
                sanitize($_POST['size'] ?? ''),
                sanitize($_POST['color'] ?? ''),
                (float)$cleanPrice,
                sanitize($description),
                (int)($_POST['stock'] ?? 0),
                $category,
                $imagePath
            ]);
        }
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }

    header('Location: admin.php');
    exit;
}

if (isset($_GET['update_status'], $_GET['order_id'], $_GET['status'])) {
    header('Content-Type: application/json');
    $orderId = trim($_GET['order_id']);
    $status  = trim($_GET['status']);

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$status, $orderId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['delete_user'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['delete_user']);
    $currentAdmin = $_SESSION['logged_in_user'] ?? '';

    if ($email === $currentAdmin) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete yourself.']);
        exit;
    }

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['restock_id'], $_GET['amount'])) {
    $inv_id = (int)$_GET['restock_id'];
    $amount = max(0, (int)$_GET['amount']);

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE inventory SET stock = stock + ? WHERE inv_id = ?");
        $stmt->execute([$amount, $inv_id]);
    } catch (PDOException $e) {
        die("Restock Error: " . $e->getMessage());
    }

    header('Location: admin.php');
    exit;
}

if (isset($_GET['delete'])) {
    $inv_id = (int)$_GET['delete'];

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM inventory WHERE inv_id = ?");
        $stmt->execute([$inv_id]);
    } catch (PDOException $e) {
        die("Delete Error: " . $e->getMessage());
    }

    header('Location: admin.php');
    exit;
}
?>