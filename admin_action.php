<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Changed to 0 to prevent output in API responses

include 'config.php';

// ── Edit review (customer editing their own) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_review'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $reviewId = trim($_POST['review_id'] ?? '');
        $rating   = (int)($_POST['rating'] ?? 5);
        $comment  = trim($_POST['comment'] ?? '');
        $userEmail = $_SESSION['logged_in_user'] ?? '';

        if ($reviewId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid review ID.']);
            exit;
        }

        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
            exit;
        }

        if ($comment === '' || strlen($comment) > 500) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Comment must be between 1 and 500 characters.']);
            exit;
        }

        // Verify the review belongs to the current user
        $db = getDBConnection();
        $verifyStmt = $db->prepare("
            SELECT u.email
            FROM reviews r
            JOIN users u ON u.user_id = r.user_id
            WHERE r.review_id = ?
            LIMIT 1
        ");
        $verifyStmt->execute([$reviewId]);
        $reviewRecord = $verifyStmt->fetch();

        if (!$reviewRecord || $reviewRecord->email !== $userEmail) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You can only edit your own reviews.']);
            exit;
        }

        // Update the review
        $updateStmt = $db->prepare("UPDATE reviews SET user_rating = ?, user_review = ? WHERE review_id = ?");
        $updateStmt->execute([$rating, $comment, $reviewId]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Review updated successfully.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Reply to review (POST) — must be checked BEFORE the generic POST block ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_review'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $currentRole = $_SESSION['role'] ?? 'user';
        if ($currentRole !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required.']);
            exit;
        }

        $reviewId = trim($_POST['review_id'] ?? '');
        $reply    = trim($_POST['reply'] ?? '');

        if ($reviewId === '' || $reply === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Review ID and reply text are required.']);
            exit;
        }

        replyToReview($reviewId, $reply);
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Reply sent to customer.']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // message-sending feature removed

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

    $categoryId = getCategoryId($category);

    try {
        $db = getDBConnection();

        if ($inv_id !== '') {
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
                sanitize($_POST['name'] ?? ''),
                sanitize($_POST['size'] ?? ''),
                sanitize($_POST['color'] ?? ''),
                (float)$cleanPrice,
                sanitize($description),
                (int)($_POST['stock'] ?? 0),
                $categoryId,
                $imagePath,
                $inv_id
            ]);
        } else {
            $newProdId = generateCustomId('PRD');
            $stmt = $db->prepare("
                INSERT INTO product_inv (prod_id, prod_name, prod_size, prod_color, unit_price, prod_desc, prod_stock, category_id, img_url)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newProdId,
                sanitize($_POST['name'] ?? ''),
                sanitize($_POST['size'] ?? ''),
                sanitize($_POST['color'] ?? ''),
                (float)$cleanPrice,
                sanitize($description),
                (int)($_POST['stock'] ?? 0),
                $categoryId,
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
        $stmt = $db->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
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

if (isset($_GET['delete_review'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Review deletion is not allowed.']);
    exit;
}

if (isset($_GET['restock_id'], $_GET['amount'])) {
    $inv_id = trim($_GET['restock_id'] ?? '');
    $amount = max(0, (int)$_GET['amount']);

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE product_inv SET prod_stock = prod_stock + ? WHERE prod_id = ?");
        $stmt->execute([$amount, $inv_id]);
    } catch (PDOException $e) {
        die("Restock Error: " . $e->getMessage());
    }

    header('Location: admin.php');
    exit;
}

if (isset($_GET['delete'])) {
    $inv_id = trim($_GET['delete'] ?? '');

    try {
        $db = getDBConnection();
        $stmt = $db->prepare("DELETE FROM product_inv WHERE prod_id = ?");
        $stmt->execute([$inv_id]);
    } catch (PDOException $e) {
        die("Delete Error: " . $e->getMessage());
    }

    header('Location: admin.php');
    exit;
}

// ── Update Payment Status + Reference No ─────────────────────
if (isset($_POST['update_payment'])) {
    header('Content-Type: application/json');
    $orderId    = trim($_POST['order_id']     ?? '');
    $payStatus  = trim($_POST['pay_status']   ?? '');
    $refNo      = trim($_POST['reference_no'] ?? '');

    $allowed = ['pending', 'verified', 'rejected'];
    if ($orderId === '' || !in_array($payStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    try {
        $db = getDBConnection();
        // Get the payment_id for this order
        $row = $db->prepare("SELECT payment_id FROM orders WHERE order_id = ? LIMIT 1");
        $row->execute([$orderId]);
        $pRow = $row->fetch();
        if (!$pRow) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        $stmt = $db->prepare("
            UPDATE payment
            SET payment_status = ?,
                reference_no   = ?,
                payment_date   = CASE WHEN payment_date IS NULL THEN NOW() ELSE payment_date END
            WHERE payment_id = ?
        ");
        $stmt->execute([$payStatus, $refNo !== '' ? $refNo : null, $pRow->payment_id]);
        echo json_encode(['success' => true, 'pay_status' => $payStatus, 'reference_no' => $refNo]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}