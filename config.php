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
define('DB_NAME', 'zythera_db');

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

// ── CUSTOM ID GENERATOR ───────────────────────────────────────
/**
 * Calls the MySQL stored procedure generate_custom_id() and returns
 * the next sequential ID string for the given prefix.
 *
 * Prefix → format examples:
 *   OR  → OR-ZY001   (Orders)
 *   PAY → PAY-ZY001  (Payments)
 *   U   → U-ZY001    (Users)
 *   AD  → AD-ZY001   (Admins)
 *   MSG → MSG-ZY001  (Messages)
 *   REV → REV-ZY001  (Reviews)
 *   ODR → ODR-ZY001  (Order Items)
 *   ADR → ADR-ZY001  (User Addresses)
 *   CAT → CAT-ZY001  (Categories)
 *   PRD → PRD-ZY001  (Product Inventory)
 */
function generateCustomId(string $prefix): string {
    $db   = getDBConnection();
    $stmt = $db->prepare("CALL generate_custom_id(?, @out_id)");
    $stmt->execute([$prefix]);
    $stmt->closeCursor();
    $row = $db->query("SELECT @out_id AS id")->fetch();
    if (!$row || $row->id === null) {
        throw new RuntimeException("generateCustomId failed for prefix '$prefix' — counter row missing.");
    }
    return (string)$row->id;
}

// ── CATEGORY HELPERS ──────────────────────────────────────────
/**
 * Get (or create) a category_id string for a given category name.
 * Returns e.g. 'CAT-ZY001'.
 */
function getCategoryId(string $categoryName): string {
    $categoryName = ($categoryName !== '') ? $categoryName : 'Sofa';
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT category_id FROM category WHERE category_name = ? LIMIT 1");
    $stmt->execute([$categoryName]);
    $row = $stmt->fetch();
    if ($row) {
        return (string)$row->category_id;
    }

    $newId = generateCustomId('CAT');
    $ins   = $db->prepare("INSERT INTO category (category_id, category_name) VALUES (?, ?)");
    $ins->execute([$newId, $categoryName]);
    return $newId;
}

// ── LOAD INVENTORY ────────────────────────────────────────────
// inv_id is now the custom VARCHAR string (e.g. 'PRD-ZY001').
function loadInventory(): array {
    try {
        $db   = getDBConnection();
        $stmt = $db->query("
            SELECT
                p.prod_id       AS inv_id,
                p.prod_name     AS name,
                p.prod_size     AS size,
                p.prod_color    AS color,
                p.unit_price    AS price,
                p.prod_desc     AS description,
                p.prod_stock    AS stock,
                c.category_name AS category,
                p.img_url       AS image,
                p.category_id   AS category_id
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
            $obj        = is_array($item) ? (object)$item : $item;
            $categoryId = getCategoryId($obj->category ?? 'Sofa');
            $invId      = (string)($obj->inv_id ?? '');

            $check = $db->prepare("SELECT prod_id FROM product_inv WHERE prod_id = ?");
            $check->execute([$invId]);

            if ($check->fetch()) {
                // UPDATE existing product
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
                    $invId,
                ]);
            } else {
                // INSERT new product — always generate a fresh PRD- id
                $newId = generateCustomId('PRD');
                $stmt  = $db->prepare("
                    INSERT INTO product_inv
                        (prod_id, category_id, prod_name, prod_desc, prod_size, prod_color, prod_stock, unit_price, img_url)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $newId,
                    $categoryId,
                    $obj->name,
                    $obj->description,
                    $obj->size,
                    $obj->color,
                    (int)$obj->stock,
                    (float)$obj->price,
                    $obj->image,
                ]);
            }
        }
    } catch (PDOException $e) {
        die("saveInventory ERROR: " . $e->getMessage());
    }
}

// ── USER HELPERS ──────────────────────────────────────────────
/**
 * Build the combined display name from fname / mname / lname.
 */
function combineName(?string $fname, ?string $mname, ?string $lname): string {
    $parts = array_filter([$fname, $mname, $lname], fn($p) => trim((string)$p) !== '');
    return trim(implode(' ', $parts));
}

/**
 * Split a single "full name" string into fname / mname / lname.
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
 * Look up a user by email. Returns an object with user_id (VARCHAR),
 * name, role, profile_pic, created_at, etc.
 */
function findUserByEmail(string $email): ?object {
    try {
        $db   = getDBConnection();
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
        if (!$row) return null;
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
        $db   = getDBConnection();
        $stmt = $db->prepare("SELECT admin_id FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        die("isAdminEmail ERROR: " . $e->getMessage());
    }
}

/**
 * Look up an admin by email.
 */
function findAdminByEmail(string $email): ?object {
    try {
        $db   = getDBConnection();
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
        if (!$row) return null;
        $row->role       = 'admin';
        $row->created_at = null;
        return $row;
    } catch (PDOException $e) {
        die("findAdminByEmail ERROR: " . $e->getMessage());
    }
}

/**
 * Look up either a user or an admin by email (admins take priority).
 */
function findAccountByEmail(string $email): ?object {
    $admin = findAdminByEmail($email);
    if ($admin) return $admin;
    return findUserByEmail($email);
}

// ── LOAD USERS ────────────────────────────────────────────────
function loadUsers(): array {
    try {
        $db   = getDBConnection();
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
function loadCartForUser(string $email): array {
    if (!isset($_SESSION['cart'][$email])) {
        $_SESSION['cart'][$email] = [];
    }
    return $_SESSION['cart'][$email];
}

function loadCart(string $email): array {
    return loadCartForUser($email);
}

function saveCartForUser(string $email, array $cart): void {
    saveCart($email, $cart);
}

function saveCart(string $email, array $cart): void {
    $_SESSION['cart'][$email] = $cart;
}

function clearCartForUser(string $email): void {
    if (isset($_SESSION['cart'][$email])) {
        unset($_SESSION['cart'][$email]);
    }
}

function loadCarts(): array {
    $allCarts = [];
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $email => $cart) {
            foreach ($cart as $item) {
                $allCarts[] = [
                    'email'  => $email,
                    'inv_id' => $item['inv_id'] ?? '',
                    'qty'    => $item['qty']    ?? 0,
                    'name'   => $item['name']   ?? '',
                    'price'  => $item['price']  ?? 0,
                ];
            }
        }
    }
    return $allCarts;
}

// ── ADDRESS / PAYMENT HELPERS ─────────────────────────────────
/**
 * Find an existing address for this user or insert a new one.
 * Returns the custom address_id string, e.g. 'ADR-ZY001'.
 */
function findOrCreateAddress(string $userId, string $phone, string $address, string $barangay, string $city, string $province, string $zip): string {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT address_id FROM user_address
        WHERE user_id = ? AND phone_num = ? AND st_address = ?
          AND COALESCE(barangay,'') = ? AND city_municipality = ? AND province = ? AND zip_code = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $phone, $address, $barangay, $city, $province, $zip]);
    $row = $stmt->fetch();
    if ($row) return (string)$row->address_id;

    $newId = generateCustomId('ADR');
    $ins   = $db->prepare("
        INSERT INTO user_address (address_id, user_id, phone_num, st_address, barangay, city_municipality, province, zip_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$newId, $userId, $phone, $address, $barangay !== '' ? $barangay : null, $city, $province, $zip]);
    return $newId;
}

/**
 * Create a new payment record and return the custom payment_id string.
 */
function createPayment(string $method, string $status = 'pending', ?string $refNo = null, ?string $proofPath = null): string {
    $db    = getDBConnection();
    $newId = generateCustomId('PAY');
    $ins   = $db->prepare("
        INSERT INTO payment (payment_id, payment_method, payment_status, payment_date, reference_no, pay_proof)
        VALUES (?, ?, ?, NOW(), ?, ?)
    ");
    $ins->execute([$newId, $method, $status, $refNo, $proofPath]);
    return $newId;
}

// ── SAVE ORDER ────────────────────────────────────────────────
/**
 * Persist a completed checkout order to the database.
 * $order['order_id'] must already hold a valid OR-ZY### string
 * (generated in checkout.php before the transaction begins).
 */
function saveOrderToDB(string $email, array $order): void {
    $db = getDBConnection();
    try {
        $db->beginTransaction();

        $user = findUserByEmail($email);
        if (!$user) throw new Exception('User not found for order.');
        $userId = (string)$user->user_id;

        $shippingInfo = $order['shipping_info'] ?? [];
        $addressId    = findOrCreateAddress(
            $userId,
            $shippingInfo['phone']    ?? '',
            $shippingInfo['address']  ?? '',
            $shippingInfo['barangay'] ?? '',
            $shippingInfo['city']     ?? '',
            $shippingInfo['province'] ?? '',
            $shippingInfo['zip']      ?? ''
        );
        $paymentId = createPayment($order['pay_method'] ?? '', 'pending');

        // Use the pre-generated OR-ZY### id passed from the caller
        $orderId = (string)($order['order_id'] ?? generateCustomId('OR'));

        $stmt = $db->prepare("
            INSERT INTO orders
                (order_id, user_id, address_id, payment_id, total_ammount, shipping_fee, user_note, order_date, order_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $orderId,
            $userId,
            $addressId,
            $paymentId,
            $order['total']    ?? 0,
            $order['shipping'] ?? 0,
            $shippingInfo['notes'] ?? '',
            $order['status']   ?? 'Pending',
        ]);

        foreach ($order['items'] as $item) {
            $orderItemId = generateCustomId('ODR');
            $oiStmt      = $db->prepare("
                INSERT INTO order_items (orderitem_id, order_id, prod_id, prod_name, quantity, unit_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $oiStmt->execute([
                $orderItemId,
                $orderId,
                (string)($item['inv_id'] ?? ''),
                trim($item['name']        ?? ''),
                (int)($item['qty']        ?? 1),
                (float)($item['price']    ?? 0),
            ]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        die("saveOrderToDB ERROR: " . $e->getMessage());
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        die("saveOrderToDB ERROR: " . $e->getMessage());
    }
}

// ── REVIEW HELPERS ────────────────────────────────────────────
/**
 * Verify that order_id (e.g. 'OR-ZY001') belongs to the given user.
 * Returns the order_id string on success, null on failure.
 */
function resolveOrderDbId(string $orderId, string $email): ?string {
    $db   = getDBConnection();
    $stmt = $db->prepare("
        SELECT o.order_id
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE o.order_id = ? AND u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId, $email]);
    $row = $stmt->fetch();
    return $row ? (string)$row->order_id : null;
}

/**
 * Return the first order_items.orderitem_id (ODR-ZY###) for an order.
 */
function firstOrderItemId(string $orderId): ?string {
    $db   = getDBConnection();
    $stmt = $db->prepare("SELECT orderitem_id FROM order_items WHERE order_id = ? ORDER BY orderitem_id ASC LIMIT 1");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ? (string)$row->orderitem_id : null;
}

function loadReviewForOrder(string $orderId): ?object {
    try {
        $db   = getDBConnection();
        $stmt = $db->prepare("
            SELECT
                r.review_id,
                r.orderitem_id,
                r.user_id,
                r.user_rating AS rating,
                r.user_review AS comment,
                r.review_date AS created_at,
                o.order_id    AS order_id,
                u.email       AS author_email,
                u.user_pfp    AS author_pic,
                CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS author_name
            FROM reviews r
            JOIN order_items oi ON oi.orderitem_id = r.orderitem_id
            JOIN orders o       ON o.order_id       = oi.order_id
            JOIN users u        ON u.user_id         = r.user_id
            WHERE o.order_id = ?
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
        if (!$user) throw new PDOException('User not found.');

        $verifiedOrderId = resolveOrderDbId($orderId, $email);
        if (!$verifiedOrderId) throw new PDOException('Order not found.');

        $orderItemId = firstOrderItemId($verifiedOrderId);
        if (!$orderItemId) throw new PDOException('Order has no items to review.');

        // Check whether a review already exists for this order
        $existing = $db->prepare("SELECT review_id FROM reviews WHERE order_id = ? LIMIT 1");
        $existing->execute([$verifiedOrderId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            // UPDATE existing review
            $upd = $db->prepare("UPDATE reviews SET user_rating = ?, user_review = ? WHERE review_id = ?");
            $upd->execute([max(1, min(5, $rating)), $comment, (string)$existingRow->review_id]);
        } else {
            // INSERT new review with a fresh REV- id
            $newId = generateCustomId('REV');
            $ins   = $db->prepare("
                INSERT INTO reviews (review_id, orderitem_id, order_id, user_id, user_rating, user_review)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $newId,
                $orderItemId,
                $verifiedOrderId,
                (string)$user->user_id,
                max(1, min(5, $rating)),
                $comment,
            ]);
        }
    } catch (PDOException $e) {
        die("saveReviewForOrder ERROR: " . $e->getMessage());
    }
}

function loadReviews(int $limit = 8, bool $all = false): array {
    try {
        $db  = getDBConnection();
        $sql = "
            SELECT
                r.review_id,
                r.orderitem_id,
                r.user_id,
                r.user_rating AS rating,
                r.user_review AS comment,
                r.review_date AS created_at,
                o.order_id    AS order_id,
                u.email       AS author_email,
                u.user_pfp    AS author_pic,
                CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS author_name
            FROM reviews r
            JOIN order_items oi ON oi.orderitem_id = r.orderitem_id
            JOIN orders o       ON o.order_id       = oi.order_id
            JOIN users u        ON u.user_id         = r.user_id
            ORDER BY r.review_date DESC
        ";
        if (!$all) $sql .= " LIMIT ?";
        $stmt = $db->prepare($sql);
        if (!$all) $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        die("loadReviews ERROR: " . $e->getMessage());
    }
}

function deleteReview(string $reviewId): void {
    try {
        $db   = getDBConnection();
        $stmt = $db->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->execute([$reviewId]);
    } catch (PDOException $e) {
        die("deleteReview ERROR: " . $e->getMessage());
    }
}


// ── LOAD ORDERS ───────────────────────────────────────────────
// The legacy alias 'ord_no' is kept for compatibility; it now returns
// the same custom ID string as 'order_id' (OR-ZY###).
// All downstream code that used the old numeric ord_no for ORDER_ITEMS_SELECT_SQL
// now correctly passes the string primary key.
const ORDER_SELECT_SQL = "
    SELECT
        o.order_id                             AS ord_no,
        o.order_id                             AS order_id,
        u.email                                AS email,
        (o.total_ammount - o.shipping_fee)     AS subtotal,
        o.shipping_fee                         AS shipping,
        o.total_ammount                        AS total,
        o.order_date                           AS date,
        o.order_status                         AS status,
        pay.payment_id                         AS payment_id,
        pay.payment_method                     AS pay_method,
        pay.payment_status                     AS pay_status,
        pay.reference_no                       AS pay_reference,
        pay.pay_proof                          AS pay_proof,
        CONCAT_WS(' ', u.fname, NULLIF(u.mname,''), u.lname) AS full_name,
        ua.phone_num                           AS phone,
        ua.st_address                          AS address,
        ua.barangay                            AS barangay,
        ua.city_municipality                   AS city,
        ua.province                            AS province,
        ua.zip_code                            AS zip,
        o.user_note                            AS notes
    FROM orders o
    JOIN users u         ON u.user_id    = o.user_id
    JOIN user_address ua ON ua.address_id = o.address_id
    JOIN payment pay     ON pay.payment_id = o.payment_id
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
        $db     = getDBConnection();
        $stmt   = $db->query(ORDER_SELECT_SQL . " ORDER BY o.order_date DESC");
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $itemStmt = $db->prepare(ORDER_ITEMS_SELECT_SQL);
            $itemStmt->execute([$order->order_id]);
            $order->items  = $itemStmt->fetchAll();
            $order->review = loadReviewForOrder($order->order_id);
        }
        return $orders;
    } catch (PDOException $e) {
        die("loadOrders ERROR: " . $e->getMessage());
    }
}

function loadUserOrders(string $email): array {
    try {
        $db     = getDBConnection();
        $stmt   = $db->prepare(ORDER_SELECT_SQL . " WHERE u.email = ? ORDER BY o.order_date DESC");
        $stmt->execute([$email]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $itemStmt = $db->prepare(ORDER_ITEMS_SELECT_SQL);
            $itemStmt->execute([$order->order_id]);
            $order->items  = $itemStmt->fetchAll();
            $order->review = loadReviewForOrder($order->order_id);
        }
        return $orders;
    } catch (PDOException $e) {
        die("loadUserOrders ERROR: " . $e->getMessage());
    }
}

// ── SESSION DEFAULTS ──────────────────────────────────────────
if (!isset($_SESSION['cart']))   $_SESSION['cart']   = [];
if (!isset($_SESSION['orders'])) $_SESSION['orders'] = [];
if (!isset($_SESSION['users']))  $_SESSION['users']  = [];

// ── AUTO LOAD INVENTORY TO SESSION ───────────────────────────
$_SESSION['inventory'] = [];
try {
    $products = loadInventory();
    foreach ($products as $item) {
        // Key by the custom string ID (e.g. 'PRD-ZY001')
        $_SESSION['inventory'][$item->inv_id] = $item;
    }
} catch (Exception $e) {
    // Fail silently — inventory loaded on demand
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
    setcookie('zythera_user', $_SESSION['logged_in_user'], $exp, '/');
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
    if ($s <= 0) return 'Out of Stock';
    if ($s <= 5) return 'Low Stock';
    return 'In Stock';
}

function getStockBadge(int $s): string {
    switch (getStockLabel($s)) {
        case 'Out of Stock': return 'bg-danger';
        case 'Low Stock':    return 'bg-warning text-dark';
        default:             return 'bg-success';
    }
}

function nowFormatted(): string {
    return date('M d, Y h:i A');
}

// ── PRODUCT CLASS ─────────────────────────────────────────────
class Product {
    public string $inv_id;   // custom VARCHAR, e.g. 'PRD-ZY001'
    public string $name;
    public string $size;
    public string $color;
    public float  $price;
    public string $description;
    public int    $stock;
    public string $category;
    public string $image;

    public function __construct(
        string $inv_id,
        string $name,
        string $size,
        string $color,
        float  $price,
        string $description,
        int    $stock,
        string $category,
        string $image
    ) {
        $this->inv_id      = $inv_id;
        $this->name        = $name;
        $this->size        = $size;
        $this->color       = $color;
        $this->price       = $price;
        $this->description = $description;
        $this->stock       = $stock;
        $this->category    = $category;
        $this->image       = $image;
    }

    public function getFormattedPrice(): string { return formatPrice($this->price); }
    public function isAvailable(): bool          { return $this->stock > 0; }
}

// ── AVATAR HELPERS ───────────────────────────────────────────
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