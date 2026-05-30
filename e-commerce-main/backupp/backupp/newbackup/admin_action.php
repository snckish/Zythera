<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php'; 

// ── ADD / EDIT product ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';

    $rawPrice   = $_POST['price'] ?? '0';
    $cleanPrice = str_replace([',', '₱', ' '], '', $rawPrice);

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $cleanPrice)) {
        $cleanPrice = preg_replace('/[^\d.]/', '', $cleanPrice);
    }

    $category = $_POST['category'] ?? 'Sofa';
    if      (strcasecmp($category, 'sofa')  === 0) $category = 'Sofa';
    elseif  (strcasecmp($category, 'chair') === 0) $category = 'Chair';
    elseif  (strcasecmp($category, 'set')   === 0) $category = 'Set';


    if (strcmp($category, 'Sofa') === 0) {  }

     $description = $_POST['description'] ?? '';
    if (str_word_count($description) < 3) {
        $description .= ' (short description — ' . str_word_count($description) . ' word/s)';
    }

       $imagePath = $_POST['image'] ?? '';
    if (strpos($imagePath, 'pci/') === false && strpos($imagePath, 'http') === false) {
        $imagePath = 'pci/' . ltrim($imagePath, '/');
    }

    $savedAt = date('M d, Y h:i A', strtotime('now'));

    $colorArray  = array_map('trim', str_getcsv($_POST['color'] ?? ''));
    $colorStored = implode(', ', $colorArray);

    $product = new Product(
        id:          $id !== '' ? (int)$id : 0,
        name:        sanitize($_POST['name'] ?? ''),
        size:        sanitize($_POST['size'] ?? ''),
        color:       $colorStored,
        price:       (float)$cleanPrice,
        description: sanitize($description),
        stock:       (int)($_POST['stock'] ?? 0),
        category:    $category,
        image:       $imagePath
    );

    $productStd           = $product->toStdClass();
    $productStd->saved_at = $savedAt;

    $inventory = $_SESSION['inventory'];

    if ($id !== '' && isset($inventory[(int)$id])) {
        // ── EDIT existing product ──
        $productStd->id      = (int)$id;
        $inventory[(int)$id] = $productStd;
    } else {
        // ── ADD new product: derive next ID from file to avoid collisions ──
        $fromFile = loadInventory();
        $maxId    = 0;
        foreach ($fromFile as $fi) {
            if (isset($fi->id) && (int)$fi->id > $maxId) $maxId = (int)$fi->id;
        }
        $newId               = $maxId + 1;
        $_SESSION['last_id'] = $newId;   // keep session in sync
        $productStd->id      = $newId;
        $inventory[$newId]   = $productStd;
    }

    saveInventory($inventory); // writes JSON + resyncs $_SESSION['inventory']

    header("Location: admin.php");
    exit;
}
// ── RESTOCK Product ──────────────────────────────────────────
if (isset($_GET['restock_id']) && isset($_GET['amount'])) {
    $id     = (int)$_GET['restock_id'];
    $amount = max(0, (int)$_GET['amount']);

    // Load from file — source of truth
    $fromFile = loadInventory();
    $inventory = [];
    foreach ($fromFile as $fi) {
        $inventory[(int)$fi->id] = $fi;
    }

    if (isset($inventory[$id])) {
        $productObj = Product::fromStdClass($inventory[$id]);
        $productObj->reststock($amount);
        $inventory[$id] = $productObj->toStdClass();
        saveInventory($inventory); // writes JSON + resyncs session
        header("Location: admin.php?success=restocked");
        exit;
    }
}

// ── UPDATE Order Status ───────────────────────────────────────
if (isset($_GET['update_status'])) {
    header('Content-Type: application/json');
    $email    = trim($_GET['email'] ?? '');
    $orderId  = trim($_GET['order_id'] ?? '');
    $newStatus = trim($_GET['status'] ?? '');

    $validStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }

    $allOrders = loadOrders();
    $found = false;
    if (isset($allOrders[$email])) {
        foreach ($allOrders[$email] as &$order) {
            if (($order['order_id'] ?? '') === $orderId) {
                $order['status'] = $newStatus;
                $found = true;
                break;
            }
        }
        unset($order);
    }

    if ($found) {
        saveOrders($allOrders);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
    }
    exit;
}

// ── DELETE User ───────────────────────────────────────────────
if (isset($_GET['delete_user'])) {
    header('Content-Type: application/json');
    $email = trim($_GET['delete_user']);
    $currentAdmin = $_SESSION['logged_in_user'] ?? '';

    if ($email === $currentAdmin) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete yourself.']);
        exit;
    }

    if (!isset($_SESSION['users'][$email])) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    unset($_SESSION['users'][$email]);
    unset($_SESSION['cart'][$email]);
    unset($_SESSION['orders'][$email]);
    if (isset($_SESSION['profile_pic'][$email])) unset($_SESSION['profile_pic'][$email]);

    // Persist user removal to file
    saveUsers($_SESSION['users']);

    // Persist order removal to file
    $allOrders = loadOrders();
    unset($allOrders[$email]);
    saveOrders($allOrders);

    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE Product ────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Load from file — source of truth
    $fromFile  = loadInventory();
    $inventory = [];
    foreach ($fromFile as $fi) {
        $inventory[(int)$fi->id] = $fi;
    }

    unset($inventory[$id]);
    saveInventory($inventory); // writes JSON + resyncs session
    header("Location: admin.php");
    exit;
}