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

// ── CATEGORY HELPERS ──────────────────────────────────────────
/**
 * Get (or create) a category_id for a given category name.
 */
function getCategoryId(string $categoryName): int {
    $categoryName = $categoryName !== '' ? $categoryName : 'Sofa';
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT category_id FROM category WHERE category_name = ? LIMIT 1");
    $stmt->execute([$categoryName]);
    $row = $stmt->fetch();

    if ($row) {
        return (int)$row->category_id;
    }

    $ins = $db->prepare("INSERT INTO category (category_name) VALUES (?)");
    $ins->execute([$categoryName]);
    return (int)$db->lastInsertId();
}

// ── LOAD INVENTORY ────────────────────────────────────────────
// Aliased to the legacy column names (inv_id, name, size, color, price,
// description, stock, category, image) so existing pages keep working.
function loadInventory(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT
                p.prod_id     AS inv_id,
                p.prod_name   AS name,
                p.prod_size   AS size,
                p.prod_color  AS color,
                p.unit_price  AS price,
                p.prod_desc   AS description,
                p.prod_stock  AS stock,
                c.category_name AS category,
                p.img_url     AS image,
                p.category_id AS category_id
            FROM product_inv p
            LEFT JOIN category c ON c.category_id = p.category_id
            ORDER BY p.prod_id ASC
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
            $categoryId = getCategoryId($obj->category ?? 'Sofa');

            $check = $db->prepare("
                SELECT prod_id FROM product_inv
                WHERE prod_id = ?
            ");
            $check->execute([(int)$obj->inv_id]);

            if ($check->fetch()) {
                $stmt = $db->prepare("
                    UPDATE product_inv SET
                        prod_name   = ?,
                        prod_size   = ?,
                        prod_color  = ?,
                        unit_price  = ?,
                        prod_desc   = ?,
                        prod_stock  = ?,
                        category_id = ?,
                        img_url     = ?
                    WHERE prod_id = ?
                ");
                $stmt->execute([
                    $obj->name,
                    $obj->size,
                    $obj->color,
                    (float)$obj->price,
                    $obj->description,
                    (int)$obj->stock,
                    $categoryId,
                    $obj->image,
                    (int)$obj->inv_id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO product_inv
                    (prod_id, category_id, prod_name, prod_desc, prod_size, prod_color, prod_stock, unit_price, img_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    (int)$obj->inv_id,
                    $categoryId,
                    $obj->name,
                    $obj->description,
                    $obj->size,
                    $obj->color,
                    (int)$obj->stock,
                    (float)$obj->price,
                    $obj->image
                ]);
            }
        }
    } catch (PDOException $e) {
        die("saveInventory ERROR: " . $e->getMessage());
    }
}

// ── USER HELPERS ──────────────────────────────────────────────
/**
 * Build the combined display name from fname/mname/lname.
 */
function combineName(?string $fname, ?string $mname, ?string $lname): string {
    $parts = array_filter([$fname, $mname, $lname], fn($p) => trim((string)$p) !== '');
    return trim(implode(' ', $parts));
}

/**
 * Split a single "full name" string into fname/mname/lname.
 * - 2 words  -> fname, lname
 * - 3+ words -> fname, middle word(s) as mname, last word as lname
 * - 1 word   -> fname only, lname = same word (lname is NOT NULL)
 */
function splitName(string $fullName): array {
    $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) === 0) {
        return ['fname' => '', 'mname' => null, 'lname' => ''];
    }
    if (count($parts) === 1) {
        return ['fname' => $parts[0], 'mname' => null, 'lname' => $parts[0]];
    }
    $fname = $parts[0];
    $lname = $parts[count($parts) - 1];
    $mname = count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null;
    return ['fname' => $fname, 'mname' => $mname, 'lname' => $lname];
}

/**
 * Look up a user by email and return an object shaped like the
 * legacy `users` row: email, name, password, role, profile_pic, created_at,
 * plus the new user_id.
 */
function findUserByEmail(string $email): ?object {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT
                user_id,
                fname, mname, lname,
                email,
                password,
                user_pfp     AS profile_pic,
                date_created AS created_at
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row->name = combineName($row->fname, $row->mname, $row->lname);
        $row->role = isAdminEmail($row->email) ? 'admin' : 'user';
        return $row;
    } catch (PDOException $e) {
        die("findUserByEmail ERROR: " . $e->getMessage());
    }
}

/**
 * Check whether the given email exists in the admins table.
 */
function isAdminEmail(string $email): bool {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT admin_id FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        die("isAdminEmail ERROR: " . $e->getMessage());
    }
}

/**
 * Look up an admin by email and return an object shaped like the
 * legacy `users` row (email, name, password, role, profile_pic, created_at).
 */
function findAdminByEmail(string $email): ?object {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT
                admin_id,
                admin_fname AS name,
                email,
                password,
                admin_pfp   AS profile_pic
            FROM admins
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row->role       = 'admin';
        $row->created_at = null;
        return $row;
    } catch (PDOException $e) {
        die("findAdminByEmail ERROR: " . $e->getMessage());
    }
}

/**
 * Look up either a user or an admin by email (admins take priority,
 * matching the historical behaviour where the seeded admin accounts
 * also existed as 'admin'-role rows in `users`).
 * Returns an object shaped like the legacy `users` row.
 */
function findAccountByEmail(string $email): ?object {
    $admin = findAdminByEmail($email);
    if ($admin) {
        return $admin;
    }
    return findUserByEmail($email);
}

// ── LOAD USERS ────────────────────────────────────────────────
// Returns regular users only (admins are managed separately),
// shaped like the legacy `users` rows.
function loadUsers(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT
                user_id,
                fname, mname, lname,
                email,
                password,
                user_pfp     AS profile_pic,
                date_created AS created_at
            FROM users
            ORDER BY date_created DESC
        ");
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $row->name = combineName($row->fname, $row->mname, $row->lname);
            $row->role = isAdminEmail($row->email) ? 'admin' : 'user';
        }
        return $rows;
    } catch (PDOException $e) {
        die("loadUsers ERROR: " . $e->getMessage());
    }
}

// ── SESSION-BASED CART FUNCTIONS ──────────────────────────────
/**
 * Load user's cart from session.
 * Session cart is indexed by inv_id for quick lookups.
 */
function loadCartForUser(string $email): array {
    if (!isset($_SESSION['cart'][$email])) {
        $_SESSION['cart'][$email] = [];
    }
    return $_SESSION['cart'][$email];
}

function loadCart(string $email): array {
    return loadCartForUser($email);
}

/**
 * Save user's cart to session.
 */
function saveCartForUser(string $email, array $cart): void {
    saveCart($email, $cart);
}

function saveCart(string $email, array $cart): void {
    $_SESSION['cart'][$email] = $cart;
}

/**
 * Clear user's cart from session.
 */
function clearCartForUser(string $email): void {
    if (isset($_SESSION['cart'][$email])) {
        unset($_SESSION['cart'][$email]);
    }
}

/**
 * Get all carts from session (for admin stats).
 * Returns array indexed by email with cart items.
 */
function loadCarts(): array {
    $allCarts = [];
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $email => $cart) {
            foreach ($cart as $item) {
                $allCarts[] = [
                    'email' => $email,
                    'inv_id' => $item['inv_id'] ?? 0,
                    'qty' => $item['qty'] ?? 0,
                    'name' => $item['name'] ?? '',
                    'price' => $item['price'] ?? 0,
                ];
            }
        }
    }
    return $allCarts;
}

// ── ADDRESS / PAYMENT HELPERS ─────────────────────────────────
/**
 * Find an existing address for this user matching the given details,
 * or insert a new one. Returns the address_id.
 */
function findOrCreateAddress(int $userId, string $phone, string $address, string $city, string $province, string $zip): int {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT address_id FROM user_address
        WHERE user_id = ? AND phone_num = ? AND st_address = ? AND city_municipality = ? AND province = ? AND zip_code = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $phone, $address, $city, $province, $zip]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row->address_id;
    }

    $ins = $db->prepare("
        INSERT INTO user_address (user_id, phone_num, st_address, city_municipality, province, zip_code)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$userId, $phone, $address, $city, $province, $zip]);
    return (int)$db->lastInsertId();
}

/**
 * Create a new payment record and return the payment_id.
 */
function createPayment(string $method, string $status = 'pending'): int {
    $db = getDBConnection();
    $ins = $db->prepare("
        INSERT INTO payment (payment_method, payment_status, payment_date)
        VALUES (?, ?, NOW())
    ");
    $ins->execute([$method, $status]);
    return (int)$db->lastInsertId();
}

// ── SAVE ORDER ────────────────────────────────────────────────
function saveOrderToDB(string $email, array $order): void {
    try {
        $db = getDBConnection();
        $db->beginTransaction();

        $user = findUserByEmail($email);
        if (!$user) {
            throw new Exception('User not found for order.');
        }
        $userId = (int)$user->user_id;

        $shippingInfo = $order['shipping_info'] ?? [];
        $addressId = findOrCreateAddress(
            $userId,
            $shippingInfo['phone']    ?? '',
            $shippingInfo['address']  ?? '',
            $shippingInfo['city']     ?? '',
            $shippingInfo['province'] ?? '',
            $shippingInfo['zip']      ?? ''
        );

        $paymentId = createPayment($order['pay_method'] ?? '', 'pending');

        $orderRef = $order['order_id'] ?? ('ORD-' . strtoupper(substr(md5(uniqid($email, true)), 0, 8)));

        $stmt = $db->prepare("
            INSERT INTO orders
            (order_ref, user_id, address_id, payment_id, total_ammount, shipping_fee, user_note, order_date, order_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $orderRef,
            $userId,
            $addressId,
            $paymentId,
            $order['total']    ?? 0,
            $order['shipping'] ?? 0,
            $shippingInfo['notes'] ?? '',
            $order['status'] ?? 'Pending',
        ]);

        $orderDbId = $db->lastInsertId();

        foreach ($order['items'] as $item) {
            $itemStmt = $db->prepare("
                INSERT INTO order_items
                (order_id, prod_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");
            $itemStmt->execute([
                $orderDbId,
                (int)($item['inv_id'] ?? 0),
                (int)($item['qty'] ?? 1),
                (float)($item['price'] ?? 0),
            ]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        die("saveOrderToDB ERROR: " . $e->getMessage());
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        die("saveOrderToDB ERROR: " . $e->getMessage());
    }
}

// ── REVIEW HELPERS ────────────────────────────────────────────
/**
 * Resolve an order_ref (e.g. ORD-XXXXXXXX) to its internal numeric
 * order_id, scoped to the given user's email.
 */
function resolveOrderDbId(string $orderRef, string $email): ?int {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT o.order_id
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE o.order_ref = ? AND u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$orderRef, $email]);
    $row = $stmt->fetch();
    return $row ? (int)$row->order_id : null;
}

/**
 * Get the "anchor" order_items row used for a review (the first
 * item belonging to the order), since reviews are linked to a
 * single order_items row in the new schema.
 */
function firstOrderItemId(int $orderDbId): ?int {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT orderitem_id FROM order_items WHERE order_id = ? ORDER BY orderitem_id ASC LIMIT 1");
    $stmt->execute([$orderDbId]);
    $row = $stmt->fetch();
    return $row ? (int)$row->orderitem_id : null;
}

function loadReviewForOrder(string $orderId): ?object {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT
                r.review_id,
                r.orderitem_id,
                r.user_id,
                r.user_rating AS rating,
                r.user_review AS comment,
                r.admin_reply AS reply,
                r.reply_date  AS reply_created_at,
                r.review_date AS created_at,
                o.order_ref   AS order_id,
                u.email       AS author_email,
                u.user_pfp    AS author_pic,
                CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS author_name
            FROM reviews r
            JOIN order_items oi ON oi.orderitem_id = r.orderitem_id
            JOIN orders o ON o.order_id = oi.order_id
            JOIN users u ON u.user_id = r.user_id
            WHERE o.order_ref = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        die("loadReviewForOrder ERROR: " . $e->getMessage());
    }
}

function saveReviewForOrder(string $email, string $orderId, int $rating, string $comment): void {
    try {
        $db = getDBConnection();

        $user = findUserByEmail($email);
        if (!$user) {
            throw new PDOException('User not found.');
        }

        $orderDbId = resolveOrderDbId($orderId, $email);
        if (!$orderDbId) {
            throw new PDOException('Order not found.');
        }

        $orderItemId = firstOrderItemId($orderDbId);
        if (!$orderItemId) {
            throw new PDOException('Order has no items to review.');
        }

        $insert = $db->prepare("
            INSERT INTO reviews (orderitem_id, order_id, user_id, user_rating, user_review)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE user_rating = VALUES(user_rating), user_review = VALUES(user_review)
        ");
        $insert->execute([
            $orderItemId,
            $orderDbId,
            (int)$user->user_id,
            max(1, min(5, $rating)),
            $comment,
        ]);
    } catch (PDOException $e) {
        die("saveReviewForOrder ERROR: " . $e->getMessage());
    }
}

function loadReviews(int $limit = 8, bool $all = false): array {
    try {
        $db = getDBConnection();
        $sql = "
            SELECT
                r.review_id,
                r.orderitem_id,
                r.user_id,
                r.user_rating AS rating,
                r.user_review AS comment,
                r.admin_reply AS reply,
                r.reply_date  AS reply_created_at,
                r.review_date AS created_at,
                o.order_ref   AS order_id,
                u.email       AS author_email,
                u.user_pfp    AS author_pic,
                CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS author_name
            FROM reviews r
            JOIN order_items oi ON oi.orderitem_id = r.orderitem_id
            JOIN orders o ON o.order_id = oi.order_id
            JOIN users u ON u.user_id = r.user_id
            ORDER BY r.review_date DESC
        ";
        if (!$all) {
            $sql .= " LIMIT ?";
        }
        $stmt = $db->prepare($sql);
        if (!$all) {
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
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$reviewId]);
    } catch (PDOException $e) {
        die("deleteReview ERROR: " . $e->getMessage());
    }
}

function replyToReview(int $reviewId, string $reply): void {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE reviews SET admin_reply = ?, reply_date = NOW() WHERE review_id = ?");
        $stmt->execute([$reply, $reviewId]);
    } catch (PDOException $e) {
        die("replyToReview ERROR: " . $e->getMessage());
    }
}

// ── LOAD ORDERS ───────────────────────────────────────────────
// Aliased to the legacy column names (ord_no, order_id, email, subtotal,
// shipping, total, date, status, pay_method, full_name, phone, address,
// city, province, zip, notes) so existing pages keep working.
const ORDER_SELECT_SQL = "
    SELECT
        o.order_id      AS ord_no,
        o.order_ref     AS order_id,
        u.email         AS email,
        (o.total_ammount - o.shipping_fee) AS subtotal,
        o.shipping_fee  AS shipping,
        o.total_ammount AS total,
        o.order_date    AS date,
        o.order_status  AS status,
        pay.payment_method AS pay_method,
        CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS full_name,
        ua.phone_num         AS phone,
        ua.st_address        AS address,
        ua.city_municipality AS city,
        ua.province          AS province,
        ua.zip_code          AS zip,
        o.user_note     AS notes
    FROM orders o
    JOIN users u           ON u.user_id = o.user_id
    JOIN user_address ua   ON ua.address_id = o.address_id
    JOIN payment pay       ON pay.payment_id = o.payment_id
";

const ORDER_ITEMS_SELECT_SQL = "
    SELECT
        oi.orderitem_id AS orderitem_id,
        oi.order_id     AS ord_no,
        oi.prod_id      AS inv_id,
        p.prod_name     AS product_name,
        oi.unit_price   AS price,
        oi.quantity     AS qty,
        p.img_url       AS image
    FROM order_items oi
    LEFT JOIN product_inv p ON p.prod_id = oi.prod_id
    WHERE oi.order_id = ?
";

function loadOrders(): array {
    try {
        $db = getDBConnection();
        $stmt = $db->query(ORDER_SELECT_SQL . " ORDER BY o.order_id DESC");
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $itemStmt = $db->prepare(ORDER_ITEMS_SELECT_SQL);
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
        $stmt = $db->prepare(ORDER_SELECT_SQL . " WHERE u.email = ? ORDER BY o.order_id DESC");
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $itemStmt = $db->prepare(ORDER_ITEMS_SELECT_SQL);
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
    if ($e === 'zythera@gmail.com') return 'pci/beti.jpg';
    if ($e === 'admin@gmail.com')   return 'pci/admin.jpg';
    if ($e === 'mei@gmail.com')     return 'pci/mei.jpg';

    $display = trim((string)($name ?? $email ?? '')) ?: 'User';
    return 'https://ui-avatars.com/api/?name=' . urlencode($display) . '&background=2d5a2d&color=fff&size=' . intval($size);
}
