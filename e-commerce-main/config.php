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

// ── REVIEW TABLE ────────────────────────────────────────────
function createReviewsTableIfNotExists(): void {
    try {
        $db = getDBConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS reviews (
            review_id INT NOT NULL AUTO_INCREMENT,
            ord_no INT NOT NULL,
            order_id VARCHAR(50) NOT NULL,
            email VARCHAR(191) NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            comment TEXT NOT NULL,
            reply TEXT DEFAULT NULL,
            reply_created_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (review_id),
            UNIQUE KEY unique_order_review (order_id),
            KEY email (email),
            CONSTRAINT reviews_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $replyColumn = $db->query("SHOW COLUMNS FROM reviews LIKE 'reply'")->fetch();
        $replyCreatedColumn = $db->query("SHOW COLUMNS FROM reviews LIKE 'reply_created_at'")->fetch();

        if (!$replyColumn) {
            $db->exec("ALTER TABLE reviews ADD COLUMN reply TEXT DEFAULT NULL");
        }
        if (!$replyCreatedColumn) {
            $db->exec("ALTER TABLE reviews ADD COLUMN reply_created_at DATETIME DEFAULT NULL");
        }
    } catch (PDOException $e) {
        die("createReviewsTableIfNotExists ERROR: " . $e->getMessage());
    }
}

function loadReviewForOrder(string $orderId): ?object {
    try {
        createReviewsTableIfNotExists();
        $db = getDBConnection();
            $stmt = $db->prepare("SELECT r.*, u.name AS author_name, u.profile_pic AS author_pic, u.email AS author_email
            FROM reviews r
            LEFT JOIN users u ON u.email = r.email
            WHERE r.order_id = ?
            LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        die("loadReviewForOrder ERROR: " . $e->getMessage());
    }
}

function saveReviewForOrder(string $email, string $orderId, int $rating, string $comment): void {
    try {
        createReviewsTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT ord_no FROM orders WHERE order_id = ? AND email = ? LIMIT 1");
        $stmt->execute([$orderId, $email]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new PDOException('Order not found.');
        }

        $insert = $db->prepare("INSERT INTO reviews (ord_no, order_id, email, rating, comment)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)");
        $insert->execute([
            (int)$order->ord_no,
            $orderId,
            $email,
            max(1, min(5, $rating)),
            $comment,
        ]);
    } catch (PDOException $e) {
        die("saveReviewForOrder ERROR: " . $e->getMessage());
    }
}

function loadReviews(int $limit = 8, bool $all = false): array {
    try {
        createReviewsTableIfNotExists();
        $db = getDBConnection();
        if ($all) {
            $stmt = $db->prepare("SELECT r.*, u.name AS author_name, u.profile_pic AS author_pic, u.email AS author_email
            FROM reviews r
            LEFT JOIN users u ON u.email = r.email
            ORDER BY r.created_at DESC");
        } else {
            $stmt = $db->prepare("SELECT r.*, u.name AS author_name, u.profile_pic AS author_pic, u.email AS author_email
            FROM reviews r
            LEFT JOIN users u ON u.email = r.email
            ORDER BY r.created_at DESC
            LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("loadReviews ERROR: " . $e->getMessage());
    }
}

function deleteReview(int $reviewId): void {
    try {
        createReviewsTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$reviewId]);
    } catch (PDOException $e) {
        die("deleteReview ERROR: " . $e->getMessage());
    }
}

function replyToReview(int $reviewId, string $reply): void {
    try {
        createReviewsTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE reviews SET reply = ?, reply_created_at = NOW() WHERE review_id = ?");
        $stmt->execute([$reply, $reviewId]);
    } catch (PDOException $e) {
        die("replyToReview ERROR: " . $e->getMessage());
    }
}

// ── USER MESSAGE / RECEIPT TABLE ─────────────────────────────────
function createUserMessagesTableIfNotExists(): void {
    try {
        $db = getDBConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS user_messages (
            message_id INT NOT NULL AUTO_INCREMENT,
            recipient_email VARCHAR(191) NOT NULL,
            subject VARCHAR(191) NOT NULL,
            body TEXT NOT NULL,
            order_id VARCHAR(50) DEFAULT NULL,
            sender VARCHAR(50) NOT NULL DEFAULT 'admin',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id),
            KEY recipient_email (recipient_email),
            KEY order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        die("createUserMessagesTableIfNotExists ERROR: " . $e->getMessage());
    }
}

function loadUserMessagesForEmail(string $email): array {
    try {
        createUserMessagesTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM user_messages WHERE recipient_email = ? ORDER BY created_at DESC");
        $stmt->execute([$email]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function loadAllUserMessages(): array {
    try {
        createUserMessagesTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->query("SELECT * FROM user_messages ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function saveAdminUserMessage(string $recipientEmail, string $subject, string $body, ?string $orderId = null): void {
    try {
        createUserMessagesTableIfNotExists();
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO user_messages (recipient_email, subject, body, order_id, sender)
            VALUES (?, ?, ?, ?, 'admin')");
        $stmt->execute([
            $recipientEmail,
            $subject,
            $body,
            $orderId !== '' ? $orderId : null,
        ]);
    } catch (PDOException $e) {
        die("saveAdminUserMessage ERROR: " . $e->getMessage());
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
                SELECT oi.*, inv.image AS image
                FROM order_items oi
                LEFT JOIN inventory inv ON inv.inv_id = oi.inv_id
                WHERE oi.ord_no = ?
            ");
            $itemStmt->execute([$order->ord_no]);
            $order->items = $itemStmt->fetchAll();
            $order->review = loadReviewForOrder($order->order_id);
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
                SELECT oi.*, inv.image AS image
                FROM order_items oi
                LEFT JOIN inventory inv ON inv.inv_id = oi.inv_id
                WHERE oi.ord_no = ?
            ");
            $itemStmt->execute([$order->ord_no]);
            $order->items = $itemStmt->fetchAll();
            $order->review = loadReviewForOrder($order->order_id);
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

// ── AVATAR HELPERS ───────────────────────────────────────────
/**
 * Resolve the best avatar URL for a user.
 * Priority: provided `profile_pic` (absolute URL or existing local file) -> known-email fallbacks -> ui-avatars
 */
function getAvatarURL($profilePic, $email = null, $name = null, $size = 80): string {
    $pic = trim((string)($profilePic ?? ''));
    if ($pic !== '') {
        if (stripos($pic, 'http') === 0) return $pic;
        $candidate = __DIR__ . '/' . ltrim($pic, '/');
        if (file_exists($candidate)) return $pic;
    }

    $e = strtolower(trim((string)($email ?? '')));
    if ($e === 'zythera@gmail.com') return 'pci/pfp/beti.jpg';
    if ($e === 'admin@gmail.com')   return 'pci/pfp/admin.jpg';
    if ($e === 'mei@gmail.com')     return 'pci/pfp/mei.jpg';

    $display = trim((string)($name ?? $email ?? '')) ?: 'User';
    return 'https://ui-avatars.com/api/?name=' . urlencode($display) . '&background=2d5a2d&color=fff&size=' . intval($size);
}