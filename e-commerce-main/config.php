<?php

date_default_timezone_set('Asia/Manila');

ini_set('session.gc_maxlifetime', 43200);
ini_set('session.cookie_lifetime', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── DATABASE CONFIG ───────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'system_db');

// ── CONNECT DATABASE ──────────────────────────────────────────
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            die("DATABASE ERROR: " . $e->getMessage());
        }
    }
    return $pdo;
}

// ── LOAD INVENTORY ────────────────────────────────────────────
function loadInventory(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT * FROM inventory
            ORDER BY inv_id ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("loadInventory ERROR: " . $e->getMessage());
    }
}

// ── SAVE INVENTORY ────────────────────────────────────────────
function saveInventory(array $inventory): void {
    try {
        $db = getDBConnection();
        foreach ($inventory as $item) {
            $obj = is_array($item) ? (object)$item : $item;

            $check = $db->prepare("
                SELECT inv_id FROM inventory
                WHERE inv_id = ?
            ");
            $check->execute([(int)$obj->inv_id]);

            if ($check->fetch()) {
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
                    $obj->name,
                    $obj->size,
                    $obj->color,
                    (float)$obj->price,
                    $obj->description,
                    (int)$obj->stock,
                    $obj->category,
                    $obj->image,
                    (int)$obj->inv_id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO inventory
                    (inv_id, name, size, color, price, description, stock, category, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    (int)$obj->inv_id,
                    $obj->name,
                    $obj->size,
                    $obj->color,
                    (float)$obj->price,
                    $obj->description,
                    (int)$obj->stock,
                    $obj->category,
                    $obj->image
                ]);
            }
        }
    } catch (PDOException $e) {
        die("saveInventory ERROR: " . $e->getMessage());
    }
}

// ── LOAD USERS ────────────────────────────────────────────────
function loadUsers(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT *
            FROM users
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("loadUsers ERROR: " . $e->getMessage());
    }
}

// ── LOAD CARTS ────────────────────────────────────────────────
function loadCarts(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT *
            FROM carts
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("loadCarts ERROR: " . $e->getMessage());
    }
}

// ── LOAD USER CART ────────────────────────────────────────────
function loadCartForUser(string $email): array {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT
                c.inv_id AS inv_id,
                inv.name AS name,
                inv.price AS price,
                c.qty AS qty,
                inv.image AS image
            FROM carts c
            LEFT JOIN inventory inv ON inv.inv_id = c.inv_id
            WHERE c.email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("loadCartForUser ERROR: " . $e->getMessage());
    }
}

function loadCart(string $email): array {
    return loadCartForUser($email);
}

function saveCartForUser(string $email, array $cart): void {
    saveCart($email, $cart);
}

// ── SAVE USER CART ────────────────────────────────────────────
function saveCart(string $email, array $cart): void {
    try {
        $db = getDBConnection();
        $db->prepare("DELETE FROM carts WHERE email = ?")->execute([$email]);

        foreach ($cart as $item) {
            $insert = $db->prepare("
                INSERT INTO carts (email, inv_id, qty)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE qty = VALUES(qty)
            ");
            $insert->execute([
                $email,
                (int)($item['inv_id'] ?? 0),
                (int)($item['qty'] ?? 1),
            ]);
        }
    } catch (PDOException $e) {
        die("saveCart ERROR: " . $e->getMessage());
    }
}

// ── CLEAR USER CART ───────────────────────────────────────────
function clearCartForUser(string $email): void {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            DELETE FROM carts
            WHERE email = ?
        ");
        $stmt->execute([$email]);
    } catch (PDOException $e) {
        die("clearCartForUser ERROR: " . $e->getMessage());
    }
}

// ── SAVE ORDER ────────────────────────────────────────────────
function saveOrderToDB(string $email, array $order): void {
    try {
        $db = getDBConnection();
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO orders
            (order_id, email, subtotal, shipping, total, date, status, pay_method,
             full_name, phone, address, city, province, zip, notes)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $order['order_id'],
            $email,
            $order['subtotal'],
            $order['shipping'],
            $order['total'],
            $order['status'] ?? 'Pending',
            $order['pay_method'],
            $order['shipping_info']['full_name'] ?? '',
            $order['shipping_info']['phone']     ?? '',
            $order['shipping_info']['address']   ?? '',
            $order['shipping_info']['city']      ?? '',
            $order['shipping_info']['province']  ?? '',
            $order['shipping_info']['zip']       ?? '',
            $order['shipping_info']['notes']     ?? '',
        ]);

        $ordNo = $db->lastInsertId();

        foreach ($order['items'] as $item) {
            $itemStmt = $db->prepare("
                INSERT INTO order_items
                (ord_no, inv_id, product_name, price, qty)
                VALUES (?, ?, ?, ?, ?)
            ");
            $itemStmt->execute([
                $ordNo,
                (int)($item['inv_id'] ?? 0),
                $item['name'] ?? '',
                (float)($item['price'] ?? 0),
                (int)($item['qty'] ?? 1)
            ]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        die("saveOrderToDB ERROR: " . $e->getMessage());
    }
}

// ── LOAD ORDERS ───────────────────────────────────────────────
function loadOrders(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT * FROM orders
            ORDER BY ord_no DESC
        ");
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $itemStmt = $db->prepare("
                SELECT *
                FROM order_items
                WHERE ord_no = ?
            ");
            $itemStmt->execute([$order->ord_no]);
            $order->items = $itemStmt->fetchAll();
        }
        return $orders;
    } catch (PDOException $e) {
        die("loadOrders ERROR: " . $e->getMessage());
    }
}

// ── LOAD USER ORDERS ──────────────────────────────────────────
function loadUserOrders(string $email): array {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT * FROM orders
            WHERE email = ?
            ORDER BY ord_no DESC
        ");
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $itemStmt = $db->prepare("
                SELECT *
                FROM order_items
                WHERE ord_no = ?
            ");
            $itemStmt->execute([$order->ord_no]);
            $order->items = $itemStmt->fetchAll();
        }
        return $orders;
    } catch (PDOException $e) {
        die("loadUserOrders ERROR: " . $e->getMessage());
    }
}

// ── SESSION DEFAULTS ──────────────────────────────────────────
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['orders'])) {
    $_SESSION['orders'] = [];
}
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = [];
}

// ── AUTO LOAD INVENTORY TO SESSION ───────────────────────────
$_SESSION['inventory'] = [];
try {
    $products = loadInventory();
    foreach ($products as $item) {
        $_SESSION['inventory'][(int)$item->inv_id] = $item;
    }
} catch (Exception $e) {
    // Fail silently
}

// ── COOKIE AUTO LOGIN ─────────────────────────────────────────
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');

if (
    !in_array($currentScript, ['logsign.php', 'logout.php']) &&
    !empty($_SESSION['logged_in_user'])
) {
    if (
        empty($_COOKIE['zythera_user']) ||
        $_COOKIE['zythera_user'] !== $_SESSION['logged_in_user']
    ) {
        unset($_SESSION['logged_in_user']);
        unset($_SESSION['role']);

        header('Location: logsign.php?expired=1');
        exit;
    }

    $exp = time() + 43200;
    setcookie(
        'zythera_user',
        $_SESSION['logged_in_user'],
        $exp,
        '/'
    );
}

// ── HELPERS ───────────────────────────────────────────────────
define('TAX_RATE', 0.12);

function sanitize(string $v): string {
    return trim(htmlspecialchars($v));
}

function isBlank(string $v): bool {
    return empty(trim($v));
}

function formatPrice(float $p): string {
    return '₱' . number_format($p, 2);
}

function getStockLabel(int $s): string {
    if ($s <= 0) {
        return 'Out of Stock';
    }
    if ($s <= 5) {
        return 'Low Stock';
    }
    return 'In Stock';
}

function getStockBadge(int $s): string {
    switch (getStockLabel($s)) {
        case 'Out of Stock':
            return 'bg-danger';
        case 'Low Stock':
            return 'bg-warning text-dark';
        default:
            return 'bg-success';
    }
}

function nowFormatted(): string {
    return date('M d, Y h:i A');
}

// ── PRODUCT CLASS ─────────────────────────────────────────────
class Product {
    public int $inv_id;
    public string $name;
    public string $size;
    public string $color;
    public float $price;
    public string $description;
    public int $stock;
    public string $category;
    public string $image;

    public function __construct(
        int $inv_id,
        string $name,
        string $size,
        string $color,
        float $price,
        string $description,
        int $stock,
        string $category,
        string $image
    ) {
        $this->inv_id = $inv_id;
        $this->name = $name;
        $this->size = $size;
        $this->color = $color;
        $this->price = $price;
        $this->description = $description;
        $this->stock = $stock;
        $this->category = $category;
        $this->image = $image;
    }

    public function getFormattedPrice(): string {
        return formatPrice($this->price);
    }

    public function isAvailable(): bool {
        return $this->stock > 0;
    }
}