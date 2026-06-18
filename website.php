<?php
require 'config.php';
$userEmail  = $_SESSION['logged_in_user'] ?? null;
$userName = null;
$uObj = null;
if ($userEmail) {
  $uObj = findAccountByEmail($userEmail);
  if ($uObj) {
    $userName = $uObj->name;
  }
}
$userRole   = $_SESSION['role'] ?? 'user';
$loginTime  = $_SESSION['login_time'] ?? null;

// ── Handle "Get in Touch" contact form ────────────────────────
$contactSuccess = false;
$contactError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
  $cName    = trim($_POST['c_name']    ?? '');
  $cEmail   = trim($_POST['c_email']   ?? '');
  $cSubject = trim($_POST['c_subject'] ?? '');
  $cMsg     = trim($_POST['c_message'] ?? '');

  if ($cName && $cEmail && $cMsg) {
    try {
      $db2 = getDBConnection();
      $msgUserId = null;
      if ($userEmail) {
        $msgUser = findUserByEmail($userEmail);
        $msgUserId = $msgUser ? (string)$msgUser->user_id : null;
      }
      $newMsgId = generateCustomId('MSG');
      $ins = $db2->prepare("
                INSERT INTO messages (msg_id, user_id, full_name, email, subject, msg_content)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
      $ins->execute([$newMsgId, $msgUserId, $cName, $cEmail, $cSubject, $cMsg]);
      // If request is AJAX, return JSON so client can clear only the message field
      $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
      }
      // Non-AJAX fallback: PRG so the form clears and the message isn't re-submitted
      header('Location: website.php?contact_sent=1#contact');
      exit;
    } catch (PDOException $e) {
      $contactError = 'Could not send message. Please try again.';
      $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
      if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $contactError]);
        exit;
      }
    }
  } else {
    $contactError = 'Please fill in your name, email, and message.';
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'error' => $contactError]);
      exit;
    }
  }
}

// If redirected after successful send, show success message
if (!empty($_GET['contact_sent'])) {
  $contactSuccess = true;
}

$cartCount = 0;
if ($userEmail && !empty($_SESSION['cart'][$userEmail]) && is_array($_SESSION['cart'][$userEmail])) {
  $cartCount = count($_SESSION['cart'][$userEmail]);
}

// FIX: Fetch the inventory array directly from the database using your loadInventory() function
$rawInventory = loadInventory();
$inventory = [];

foreach ($rawInventory as $item) {
  $inventory[] = $item;
}

// Sort inventory by ID accurately
usort($inventory, function ($a, $b) {
  $ia = is_object($a) ? (string)$a->inv_id : (string)($a['inv_id'] ?? '');
  $ib = is_object($b) ? (string)$b->inv_id : (string)($b['inv_id'] ?? '');
  return $ia <=> $ib;
});

$reviews = loadReviews();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZYTHERA | FURNITURE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --logo-font: 'Playfair Display', serif;
      --ui-font: 'Roboto', sans-serif;
      --text-font: 'Merriweather', serif
    }

    body {
      font-family: var(--ui-font);
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    .navbar-brand,
    .brand-name,
    .section-title,
    .page-header h2,
    footer .footer-brand {
      font-family: var(--logo-font);
    }

    p,
    small,
    .caption,
    .text-muted {
      font-family: var(--text-font);
    }
  </style>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dark-mode.css">
  <script src="dark-mode.js"></script>
  <style>
    :root {
      --green: #2d5a2d;
      --sage: #d4e4d4;
      --cream: #f5f2ec;
      --terra: #bc8a7b;
      --deep: #1a2e1a;
      --radius-card: 18px;
      --shadow-card: 0 2px 16px rgba(0,0,0,.07);
      --shadow-hover: 0 12px 36px rgba(0,0,0,.13);
      --transition: .22s ease;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: var(--ui-font);
      background: var(--cream);
      padding-top: 68px;
    }

    /* ── NAVBAR ── */
    .navbar {
      background: rgba(255,255,255,.96) !important;
      box-shadow: 0 1px 0 rgba(0,0,0,.06), 0 4px 20px rgba(0,0,0,.04);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      min-height: 68px;
    }

    .navbar-brand {
      font-family: 'Playfair Display', serif;
      color: var(--deep) !important;
      font-size: 1.5rem;
      letter-spacing: 2.5px;
      font-weight: 700;
      padding: 0;
    }
    .navbar-brand span { font-family: 'Playfair Display', serif; }

    .nav-link {
      font-weight: 500;
      color: #555 !important;
      font-size: .875rem;
      letter-spacing: .2px;
      padding: 6px 4px !important;
      position: relative;
      transition: color var(--transition);
    }
    .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0; right: 0;
      height: 2px;
      background: var(--green);
      border-radius: 2px;
      transform: scaleX(0);
      transition: transform var(--transition);
    }
    .nav-link:hover { color: var(--green) !important; }
    .nav-link:hover::after { transform: scaleX(1); }

    /* User capsule in nav */
   .nav-user-capsule {
  display: flex;
  align-items: center;
  background: #ffffff;
  border-radius: 50px;
  padding: 6px 12px 6px 6px;
  gap: 8px;
  border: 1px solid rgba(45,90,45,.12);
  transition: all var(--transition);
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}

.nav-user-capsule:hover {
  border-color: rgba(45,90,45,.25);
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
}

.nav-user-capsule img {
  border: 2.5px solid rgba(45,90,45,.15);
  transition: border-color var(--transition);
}

.nav-user-capsule:hover img {
  border-color: rgba(45,90,45,.3);
}

/* Dark Mode Support */
body.dark .nav-user-capsule {
  background: #1f2937;
  border-color: rgba(168,212,168,.15);
}

body.dark .nav-user-capsule:hover {
  background: #2d3748;
  border-color: rgba(168,212,168,.3);
}

    /* ── DARK MODE TOGGLE ── */
    .dark-toggle-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px 6px 10px;
      border: 1.5px solid rgba(45,90,45,.25);
      border-radius: 50px;
      background: transparent;
      color: var(--green);
      font-size: .8rem;
      font-weight: 600;
      cursor: pointer;
      transition: background var(--transition), border-color var(--transition), color var(--transition);
      line-height: 1;
      white-space: nowrap;
    }
    .dark-toggle-btn:hover {
      background: var(--green);
      color: #fff;
      border-color: var(--green);
    }
    .dark-toggle-icon { display: flex; align-items: center; }
    body.dark .dark-toggle-btn {
      border-color: rgba(168,212,168,.4) !important;
      color: #a8d4a8 !important;
      background: transparent !important;
    }
    body.dark .dark-toggle-btn:hover {
      background: rgba(168,212,168,.15) !important;
      color: #fff !important;
      border-color: rgba(168,212,168,.7) !important;
    }

    /* ── HERO ── */
    .hero {
      position: relative;
      height: 90vh;
      min-height: 520px;
      overflow: hidden;
      background: var(--deep);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .hero-img {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      opacity: 0.5;
      z-index: 0;
    }

    .hero-text {
      position: relative;
      z-index: 1;
      max-width: 680px;
      text-align: center;
      color: #fff;
      font-family: var(--logo-font);
      font-size: clamp(1.15rem, 2.5vw, 1.75rem);
      line-height: 1.8;
      padding: 44px 48px;
      border: 1px solid rgba(212,228,212,.18);
      border-radius: 12px;
      background: rgba(0,0,0,.22);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }

    .hero-cta {
      margin-top: 28px;
      display: inline-block;
      padding: 13px 36px;
      background: rgba(255,255,255,.12);
      border: 2px solid rgba(255,255,255,.45);
      border-radius: 50px;
      color: #fff;
      text-decoration: none;
      font-family: var(--ui-font);
      font-size: .9rem;
      font-weight: 600;
      backdrop-filter: blur(4px);
      transition: background var(--transition), border-color var(--transition);
      letter-spacing: .3px;
    }
    .hero-cta:hover {
      background: rgba(255,255,255,.22);
      border-color: rgba(255,255,255,.75);
      color: #fff;
    }

    /* ── SECTIONS ── */
    .section {
      padding: 72px 0;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      color: var(--green);
      font-size: clamp(1.5rem, 3vw, 1.9rem);
      margin-bottom: 40px;
    }

    /* ── PRODUCT CARD ── */
    .product-card {
      border: none;
      border-radius: var(--radius-card);
      overflow: hidden;
      background: #fff;
      box-shadow: var(--shadow-card);
      transition: transform var(--transition), box-shadow var(--transition);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-hover);
    }

    .product-card img {
      height: 210px;
      object-fit: cover;
      width: 100%;
      flex-shrink: 0;
    }

    .product-card .p-4 {
      display: flex;
      flex-direction: column;
      flex: 1;
    }

    .product-name {
      font-family: 'Playfair Display', serif;
      color: var(--green);
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.35;
    }

    .product-price {
      color: var(--terra);
      font-size: 1.15rem;
      font-weight: 700;
    }

    .stock-badge {
      background: var(--green);
      color: #fff;
      font-size: .68rem;
      border-radius: 6px;
      padding: 3px 9px;
      font-weight: 600;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .btn-cart {
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: .7rem 1rem;
      font-weight: 600;
      width: 100%;
      font-family: var(--ui-font);
      font-size: .88rem;
      transition: background var(--transition), transform .1s;
      margin-top: auto;
      letter-spacing: .2px;
    }
    .btn-cart:hover { background: var(--deep); color: #fff; }
    .btn-cart:active { transform: scale(.98); }
    .btn-cart:disabled { opacity: .45; cursor: not-allowed; transform: none; }

    /* Qty stepper */
    .qty-stepper-row {
      display: flex;
      align-items: center;
      border: 2px solid var(--sage);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 12px;
    }
    .qty-stepper-row .input-group-text {
      border: none;
      background: #fff;
      color: #888;
      font-size: .8rem;
      padding: 8px 10px;
    }
    .qty-stepper-row input {
      border: none;
      text-align: center;
      font-weight: 600;
    }

    /* REVIEWS */
    .reviews-section {
      padding: 72px 0;
      overflow: hidden;
      background: var(--cream);
    }

    .review-scroll-wrapper {
      position: relative;
    }

    .review-scroll-track {
      display: flex;
      gap: 16px;
      overflow-x: auto;
      scroll-snap-type: x mandatory;
      -webkit-overflow-scrolling: touch;
      padding: 8px 4px 28px;
      scrollbar-width: none;
      cursor: grab;
      user-select: none;
      align-items: stretch;
    }

    .review-scroll-track::-webkit-scrollbar {
      display: none;
    }

    .review-scroll-track.dragging {
      cursor: grabbing;
      scroll-snap-type: none;
    }

    /* Review card — fixed width, flexible HEIGHT */
    .review-item {
      background: var(--green);
      border-radius: 20px;
      padding: 22px 20px 22px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .07);
      min-width: 280px;
      max-width: 280px;
      scroll-snap-align: start;
      flex-shrink: 0;
      transition: transform .2s, box-shadow .2s;
      position: relative;
      display: flex;
      flex-direction: column;
      height: auto;
      overflow: visible;
    }

    .review-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 32px rgba(0, 0, 0, .12);
    }

    .review-item.own-review {
      outline: 2px solid rgba(255,255,255,.3);
      outline-offset: 2px;
    }
    .review-item.own-review:hover {
      outline-color: rgba(255,255,255,.6);
    }

    /* ── Three-dot menu ── */
    .review-menu-wrapper {
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 20;
    }

    .review-menu-btn {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: rgba(255,255,255,.15);
      border: none;
      color: rgba(255,255,255,.8);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      line-height: 1;
      letter-spacing: 0;
      transition: background .15s, color .15s;
      padding: 0;
      flex-shrink: 0;
    }
    .review-menu-btn:hover {
      background: rgba(255,255,255,.28);
      color: #fff;
    }

    .review-dropdown {
      position: absolute;
      top: calc(100% + 6px);
      right: 0;
      min-width: 152px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 28px rgba(0,0,0,.18);
      overflow: hidden;
      display: none;
      z-index: 100;
    }
    .review-dropdown.open { display: block; }

    .review-dropdown button {
      display: flex;
      align-items: center;
      gap: 9px;
      width: 100%;
      padding: 10px 14px;
      background: none;
      border: none;
      font-size: .82rem;
      font-weight: 500;
      color: #333;
      cursor: pointer;
      text-align: left;
      transition: background .12s;
      white-space: nowrap;
    }
    .review-dropdown button:hover { background: #f5f2ec; }
    .review-dropdown button.danger { color: #dc2626; }
    .review-dropdown button.danger:hover { background: #fee2e2; }
    .review-dropdown button i { width: 14px; text-align: center; flex-shrink: 0; }

    /* Review text — wraps properly, no overflow/truncation */
    .review-body {
      font-size: .88rem;
      color: var(--sage);
      line-height: 1.65;
      margin: 0;
      word-break: break-word;
      overflow-wrap: anywhere;
      white-space: pre-line;
      flex: 1;
      min-width: 0;
    }

    /* Long review: clamp to ~5 lines */
    .review-body.clamped {
      display: -webkit-box;
      -webkit-line-clamp: 5;
      -webkit-box-orient: vertical;
      overflow: hidden;
      white-space: normal;
    }

    .review-expand-btn {
      display: inline-block;
      margin-top: 6px;
      font-size: .75rem;
      color: rgba(255,255,255,.65);
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      text-decoration: underline;
      text-underline-offset: 2px;
      align-self: flex-start;
      transition: color .15s;
    }
    .review-expand-btn:hover { color: #fff; }

    /* Edit review modal */
/* ── Review Edit Modal ── */
.review-edit-modal-bg {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 5000;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(2px);
}

.review-edit-modal-bg.active {
  display: flex;
}

.review-edit-modal {
  background: white;
  border-radius: 16px;
  padding: 28px;
  width: min(480px, calc(100vw - 32px));
  box-shadow: 0 20px 60px rgba(0,0,0,.3);
  animation: slideDown .35s cubic-bezier(.34,1.56,.64,1);
}

.review-edit-modal h5 {
  color: var(--deep);
  font-weight: 700;
  margin-bottom: 18px;
  font-size: 1.1rem;
}

.review-edit-stars {
  display: flex;
  gap: 8px;
  margin-bottom: 18px;
}

.review-edit-stars span {
  font-size: 1.8rem;
  color: #ddd;
  cursor: pointer;
  transition: all .2s ease;
}

.review-edit-stars span:hover,
.review-edit-stars span.selected {
  color: #fbbf24;
  transform: scale(1.15);
}

.review-edit-modal textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  font-family: var(--text-font);
  font-size: .9rem;
  resize: vertical;
  margin-bottom: 6px;
}

.review-edit-modal textarea:focus {
  outline: none;
  border-color: var(--green);
  box-shadow: 0 0 0 3px rgba(45,90,45,.1);
}

.review-char-count {
  font-size: .8rem;
  color: #999;
  margin-bottom: 16px;
  text-align: right;
}

.review-edit-modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.review-edit-modal-actions button {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  font-size: .9rem;
  cursor: pointer;
  transition: all .2s ease;
}

.btn-cancel-review {
  background: #f0ece4;
  color: #555;
}

.btn-cancel-review:hover {
  background: #e8ddd4;
}

.btn-save-review {
  background: var(--green);
  color: white;
}

.btn-save-review:hover {
  background: #1e4d1e;
}

/* Dark Mode */
body.dark .review-edit-modal {
  background: #1f2937;
}

body.dark .review-edit-modal h5 {
  color: #ffffff;
}

body.dark .review-edit-modal textarea {
  background: #374151;
  border-color: #4b5563;
  color: #e5e7eb;
}

body.dark .review-edit-modal textarea:focus {
  border-color: var(--green);
}
    /* ── LOGOUT CONFIRMATION MODAL ── */
    .logout-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.6);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px);
    }
    .logout-modal-overlay.active { display: flex; }

    .logout-modal {
      background: #fff;
      border-radius: 20px;
      padding: 32px 28px;
      width: min(420px, calc(100vw - 32px));
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      text-align: center;
      animation: slideDown .3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logout-modal h2 {
      font-family: 'Playfair Display', serif;
      color: var(--deep);
      font-size: 1.3rem;
      margin: 0 0 12px 0;
      font-weight: 700;
    }

    .logout-modal p {
      color: #666;
      font-size: .95rem;
      margin: 0 0 24px 0;
      line-height: 1.5;
    }

    body.dark .logout-modal {
      background: #1f2937;
    }
    body.dark .logout-modal h2 {
      color: #a8d4a8;
    }
    body.dark .logout-modal p {
      color: #cbd5e1;
    }
    body.dark .logout-cancel-btn {
      background: #2d3748;
      color: #cbd5e1;
    }
    body.dark .logout-cancel-btn:hover {
      background: #374151;
    }

    .logout-modal-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .logout-modal-buttons button {
      padding: 12px 28px;
      border-radius: 50px;
      border: none;
      font-weight: 600;
      font-size: .9rem;
      cursor: pointer;
      transition: .2s ease;
      font-family: var(--ui-font);
    }

    .logout-cancel-btn {
      background: #f0ece4;
      color: #555;
    }
    .logout-cancel-btn:hover {
      background: #e2ddd4;
    }

    .logout-confirm-btn {
      background: var(--green);
      color: #fff;
      min-width: 120px;
    }
    .logout-confirm-btn:hover {
      background: var(--deep);
    }
    .logout-confirm-btn:active {
      transform: scale(0.98);
    }

    .review-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      flex-shrink: 0;
      padding-right: 36px; /* space for the three-dot menu */
    }

    .avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
      border: 2px solid rgba(255,255,255,.25);
    }

    .stars {
      color: #f5c842;
      font-size: .85rem;
      letter-spacing: 1px;
      flex-shrink: 0;
      margin-bottom: 10px;
    }

    /* Arrow nav buttons */
    .scroll-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-60%);
      width: 40px;
      height: 40px;
      border-radius: 50%;
      border: none;
      background: #fff;
      color: var(--green);
      box-shadow: 0 3px 16px rgba(0, 0, 0, .13);
      cursor: pointer;
      font-size: .9rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background var(--transition), color var(--transition), box-shadow var(--transition);
      z-index: 2;
    }

    .scroll-btn:hover {
      background: var(--green);
      color: #fff;
      box-shadow: 0 4px 20px rgba(45,90,45,.25);
    }

    .scroll-btn.left { left: -18px; }
    .scroll-btn.right { right: -18px; }

    @media (max-width: 576px) {
      .scroll-btn { display: none; }
      .review-item { min-width: 248px; max-width: 248px; }
    }

    /* CONTACT */
    .contact-card {
      background: #fff;
      border-radius: var(--radius-card);
      padding: 48px;
      box-shadow: var(--shadow-card);
    }

    .input-box {
      position: relative;
      margin-bottom: 24px;
    }

    .input-box input,
    .input-box textarea {
      width: 100%;
      padding: 16px 18px;
      border: 2px solid var(--sage);
      border-radius: 12px;
      font-size: .95rem;
      background: var(--cream);
      outline: none;
      font-family: var(--ui-font);
      transition: border-color var(--transition), background var(--transition);
      color: #333;
      letter-spacing: 0.3px;
    }

    .input-box input:focus,
    .input-box textarea:focus {
      border-color: var(--green);
      background: #fff;
    }

    .input-box label {
      position: absolute;
      top: 17px;
      left: 18px;
      font-size: .88rem;
      color: #999;
      pointer-events: none;
      transition: .2s;
      background: transparent;
      font-weight: 500;
    }

    .input-box input:focus ~ label,
    .input-box input:not(:placeholder-shown) ~ label,
    .input-box textarea:focus ~ label,
    .input-box textarea:not(:placeholder-shown) ~ label {
      top: 0;
      font-size: .7rem;
      background: #fff;
      padding: 0 4px;
      color: var(--green);
      border-radius: 4px;
    }

    .input-box textarea {
      min-height: 140px;
      resize: none;
      line-height: 1.6;
    }

    .social-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--sage);
      color: var(--green);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: background var(--transition), color var(--transition), transform var(--transition);
      text-decoration: none;
    }

    .social-icon:hover {
      background: var(--green);
      color: #fff;
      transform: translateY(-2px);
    }

    /* ── FOOTER ── */
    .site-footer {
      background: var(--deep);
      color: rgba(212,228,212,.75);
      padding: 56px 0 0;
      margin-top: 0;
      font-family: var(--ui-font);
    }
    .site-footer .footer-brand-name {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      color: #fff;
      letter-spacing: 2px;
      font-weight: 700;
    }
    .site-footer .footer-tagline {
      font-size: .82rem;
      color: rgba(212,228,212,.6);
      line-height: 1.7;
      margin-top: 8px;
      max-width: 220px;
    }
    .site-footer .footer-logo-img {
      width: 28px;
      opacity: .85;
    }
    .site-footer .footer-col-title {
      font-size: .7rem;
      letter-spacing: 2.5px;
      text-transform: uppercase;
      color: rgba(212,228,212,.45);
      margin-bottom: 16px;
      font-weight: 600;
    }
    .site-footer .footer-link {
      display: block;
      color: rgba(212,228,212,.72);
      text-decoration: none;
      font-size: .85rem;
      line-height: 1;
      padding: 5px 0;
      transition: color .15s;
    }
    .site-footer .footer-link:hover { color: #fff; }
    .site-footer .footer-link i {
      width: 16px;
      font-size: .78rem;
      margin-right: 7px;
      opacity: .65;
    }
    .site-footer .footer-divider {
      border-color: rgba(255,255,255,.08);
      margin: 40px 0 0;
    }
    .site-footer .footer-bottom {
      padding: 18px 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 10px;
    }
    .site-footer .footer-bottom-text {
      font-size: .77rem;
      color: rgba(212,228,212,.38);
    }
    .site-footer .footer-social {
      display: flex;
      gap: 10px;
    }
    .site-footer .footer-social a {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(255,255,255,.07);
      color: rgba(212,228,212,.7);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .82rem;
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .site-footer .footer-social a:hover {
      background: var(--green);
      color: #fff;
    }
    @media (max-width: 767px) {
      .site-footer { padding: 44px 0 0; }
      .site-footer .footer-col { margin-bottom: 32px; }
      .site-footer .footer-bottom { justify-content: center; text-align: center; }
    }

    .toast-fixed {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--green);
      color: #fff;
      padding: 14px 22px;
      border-radius: 12px;
      font-size: .86rem;
      z-index: 9999;
      opacity: 0;
      transform: translateY(10px);
      transition: .3s;
      pointer-events: none;
      max-width: 300px;
      box-shadow: 0 6px 24px rgba(0, 0, 0, .2);
    }

    .toast-fixed.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast-fixed.error {
      background: #dc2626;
    }
  </style>
  <script>
    /* ZYTHERA dark mode — apply before paint to prevent flash */
    (function() {
      if (localStorage.getItem('zythera_dark') === '1') {
        document.documentElement.style.background = '#111e11';
        document.addEventListener('DOMContentLoaded', function() {
          document.body.classList.add('dark');
          document.documentElement.style.background = '';
        });
      }
    })();
  </script>
  <link rel="stylesheet" href="responsive.css">
</head>

<body>

  <!-- NAVBAR -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold" href="website.php"><span style="font-family: 'Playfair Display', serif; color: var(--deep); font-weight: 700;"> ZYTHERA </span></a>

      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navMenu">
        <div class="ms-auto d-flex align-items-center gap-3 flex-wrap">
          <a href="#products" class="nav-link fw-semibold" style="color:var(--green)!important;">Products</a>
          <a href="about.php" class="nav-link fw-semibold" style="color:var(--green)!important;">About</a>
          <a href="website.php#contact" class="nav-link fw-semibold" style="color:var(--green)!important;">Contact Us</a>
          <?php if ($userEmail && $userRole !== 'admin'): ?>
            <a href="profile.php?tab=orders" class="nav-link fw-semibold" style="color:var(--green)!important;">My Orders</a>
          <?php endif; ?>
          <?php if ($userEmail): ?>
            <div class="nav-user-capsule">
              <div class="text-end d-none d-md-block">
                <p class="mb-0 fw-bold" style="font-size:.78rem;color:var(--green);"><?= htmlspecialchars($userName) ?></p>
                <?php if ($loginTime): ?>
                  <small class="text-muted" style="font-size:.6rem;"><span id="liveTime"></span></small>
                <?php endif; ?>
              </div>
              <div class="dropdown">
                <?php
                    $navPic = getAvatarURL($uObj->profile_pic ?? null, $uObj->email ?? null, $userName, 34);
                ?>
                <img src="<?= htmlspecialchars($navPic) ?>" class="rounded-circle" width="32" height="32" style="cursor:pointer;border:2px solid rgba(45,90,45,.2);object-fit:cover;" data-bs-toggle="dropdown" alt="<?= htmlspecialchars($userName) ?>">
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius:14px;min-width:190px;">
                  <?php if ($userRole !== 'admin'): ?>
                  <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-muted" style="font-size:.85rem;"></i>My Profile</a></li>
                  <?php endif; ?>
                  <?php if ($userRole === 'admin'): ?>
                    <li><a class="dropdown-item py-2" href="admin.php"><i class="fas fa-user-shield me-2 text-muted" style="font-size:.85rem;"></i>Admin Panel</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider my-1"></li>
                  <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt me-2" style="font-size:.85rem;"></i>Logout</a></li>
                </ul>
              </div>
            </div>

            <!-- Cart icon — hidden for admin -->
            <?php if ($userRole !== 'admin'): ?>
              <a href="profile.php" class="text-decoration-none d-flex align-items-center justify-content-center" title="Settings" aria-label="Profile settings"
                style="width:36px;height:36px;border-radius:50%;background:#f0f7f0;border:1px solid rgba(45,90,45,.16);color:var(--green);transition:.2s;">
                <i class="fas fa-gear" style="font-size:1rem;"></i>
              </a>
              <a href="javascript:void(0)" onclick="openCart()" class="position-relative text-decoration-none d-flex align-items-center" title="Cart" style="color:var(--green);">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="9" cy="21" r="1" />
                  <circle cx="20" cy="21" r="1" />
                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>
                <span id="cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                  style="font-size:.55rem;background:var(--green);color:#fff;<?= $cartCount == 0 ? 'display:none;' : '' ?>">
                  <?= $cartCount ?>
                </span>
              </a>
            <?php endif; ?>

          <?php else: ?>
            <a href="logsign.php" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold">Log In</a>
            <a href="logsign.php" class="position-relative text-decoration-none d-flex align-items-center" title="Cart" style="color:var(--green);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <div class="hero">
    <img src="pci/image_8.png" class="hero-img">
    <div class="hero-text">
      "A quiet collection of furniture crafted for lives that deserve stillness, beauty, and meaning."
      <br>
      <a href="#products" class="hero-cta mt-3 d-inline-block">Explore Collection ↓</a>
    </div>
  </div>

  <!-- PRODUCTS -->
  <section class="section" id="products">
    <div class="container">
      <h2 class="section-title text-center">Our Collection</h2>

      <?php if (empty($inventory)): ?>
        <div class="text-center py-5 text-muted">
          <i class="fas fa-couch fa-3x mb-3 opacity-25"></i>
          <p>No products yet. <?php if ($userRole === 'admin'): ?><a href="admin.php">Add products →</a><?php endif; ?></p>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($inventory as $item):
            $item       = (object)$item;
            $cleanPrice = (float)str_replace([',', '₱', ' '], '', $item->price);
            $outOfStock = (int)$item->stock === 0;
          ?>
            <div class="col-sm-6 col-lg-4">
              <div class="product-card">
                <img src="<?= htmlspecialchars($item->image ?? '') ?>"
                  alt="<?= htmlspecialchars($item->name) ?>"
                  onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400&h=220&fit=crop'">
                <div class="p-4">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <h6 class="product-name mb-0"><?= htmlspecialchars($item->name) ?></h6>
                    <span class="stock-badge <?= $outOfStock ? 'bg-danger' : '' ?>">
                      <?= $outOfStock ? 'Out of Stock' : 'Stock: ' . $item->stock ?>
                    </span>
                  </div>
                  <div class="product-price mb-2">₱<?= number_format($cleanPrice) ?></div>
                  <div class="small text-muted mb-2">
                    <?php if (!empty($item->size)):  ?><span><b>Size:</b> <?= htmlspecialchars($item->size) ?></span><?php endif; ?>
                    <?php if (!empty($item->color)): ?> &nbsp;·&nbsp; <span><b>Color:</b> <?= htmlspecialchars($item->color) ?></span><?php endif; ?>
                  </div>
                  <p class="small text-dark mb-3" style="line-height:1.5;">
                    <span class="desc-short"><?= htmlspecialchars(mb_substr($item->description ?? '', 0, 70)) ?>...</span>
                    <span class="desc-full d-none"><?= htmlspecialchars($item->description ?? '') ?></span>
                    <a href="javascript:void(0)" class="text-success see-more" onclick="toggleDesc(this)" style="font-size:.75rem;">See More</a>
                  </p>

                  <!-- Qty + Cart -->
                  <div class="input-group mb-3" style="border:2px solid var(--sage);border-radius:10px;overflow:hidden;">
                    <span class="input-group-text border-0 bg-white text-muted small">Qty</span>
                    <input type="number" id="qty-<?= $item->inv_id ?>" class="form-control border-0 text-center"
                      value="1" min="1" max="<?= $item->stock ?>" <?= $outOfStock ? 'disabled' : '' ?>>
                  </div>

                  <button class="btn-cart" id="btn-<?= $item->inv_id ?>"
                    onclick='addToCart(<?= json_encode((string)$item->inv_id) ?>, <?= json_encode($item->name) ?>, <?= $cleanPrice ?>, <?= json_encode($item->image ?? '') ?>)'
                    <?= ($outOfStock || $userRole === 'admin') ? 'disabled' : '' ?>>
                    <?php if ($userRole === 'admin'): ?>
                      Admin View Only
                    <?php elseif ($outOfStock): ?>
                      Out of Stock
                    <?php else: ?>
                      Add to Cart
                    <?php endif; ?>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>


  <!-- REVIEW SECTION -->
  <section class="reviews-section" id="reviews">
    <div class="container">
      <h2 class="section-title text-center">Customer Reviews</h2>
      <p class="text-center text-muted mb-4" style="font-size:.9rem;margin-top:-20px;">What our customers say about our furniture</p>

      <div class="review-scroll-wrapper px-2">
        <!-- Left arrow -->
        <button class="scroll-btn left" onclick="document.getElementById('reviewTrack').scrollBy({left:-290,behavior:'smooth'})">
          <i class="fas fa-chevron-left"></i>
        </button>

        <div class="review-scroll-track" id="reviewTrack">
          <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
              <?php
                $stars = str_repeat('★', max(1, min(5, (int)($review->rating ?? 5))));
                $isOwn = ($userEmail && strtolower($userEmail) === strtolower($review->author_email ?? ''));
                $reviewOrderId = htmlspecialchars($review->order_id ?? '');
                $reviewIdInt   = (int)($review->review_id ?? 0);
                $cardId        = 'rv-' . $reviewIdInt;

                // Character limit: truncate at 300 chars for display
                $CHAR_LIMIT  = 300;
                $commentText = $review->comment;
                $isLong      = mb_strlen($commentText) > $CHAR_LIMIT || substr_count($commentText, "\n") >= 4;
              ?>
              <div class="review-item<?= $isOwn ? ' own-review' : '' ?>"
                   id="card-<?= $cardId ?>"
                   <?php if ($isOwn && $reviewOrderId): ?>
                     style="cursor:pointer;"
                     onclick="window.location.href='order.php?order_id=<?= $reviewOrderId ?>'"
                     title="Click to view your order"
                   <?php endif; ?>>

                <?php if ($isOwn): ?>
                <!-- ⋮ Three-dot menu (own reviews only) -->
                <div class="review-menu-wrapper" onclick="event.stopPropagation()">
                  <button class="review-menu-btn"
                          title="Review options"
                          onclick="toggleReviewMenu('<?= $cardId ?>')">&#8942;</button>
                  <div class="review-dropdown" id="menu-<?= $cardId ?>">
                  <!-- <button onclick="openEditReview(<?= $reviewIdInt ?>, <?= (int)($review->rating ?? 5) ?>, <?= json_encode($commentText) ?>)">
                      <i class="fas fa-pencil-alt"></i> Edit Review
                    </button>--->
                    <button class="danger" onclick="deleteMyReview(<?= $reviewIdInt ?>)">
                      <i class="fas fa-trash"></i> Delete Review
                    </button>
                  </div>
                </div>
                <?php endif; ?>

                <div class="review-header">
                  <?php $authorPic = getAvatarURL($review->author_pic ?? null, $review->author_email ?? null, $review->author_name ?? null, 80); ?>
                  <img src="<?= htmlspecialchars($authorPic) ?>" class="avatar" alt="<?= htmlspecialchars($review->author_name ?: 'Verified Buyer') ?>">
                  <div style="min-width:0;">
                    <div style="font-weight:700;color:#fff;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                      <?= htmlspecialchars($review->author_name ?: 'Verified Buyer') ?>
                      <?php if ($isOwn): ?><span style="font-size:.65rem;background:rgba(255,255,255,.18);border-radius:50px;padding:1px 7px;margin-left:5px;font-weight:500;white-space:nowrap;">You</span><?php endif; ?>
                    </div>
                    <div style="font-size:.72rem;color:rgba(212,228,212,.8);">Verified Buyer</div>
                  </div>
                </div>
                <div class="stars"><?= $stars ?></div>
                <p class="review-body<?= $isLong ? ' clamped' : '' ?>" id="body-<?= $cardId ?>">
                  <?= nl2br(htmlspecialchars($commentText)) ?>
                </p>
                <?php if ($isLong): ?>
                <button class="review-expand-btn" id="btn-<?= $cardId ?>"
                  onclick="event.stopPropagation();toggleReview('<?= $cardId ?>')">Read more</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="review-item">
              <div class="review-header">
                <img src="https://i.pravatar.cc/80?img=13" class="avatar" alt="Verified Buyer">
                <div>
                  <div style="font-weight:700;color:#fff;font-size:.9rem;">Verified Buyer</div>
                  <div style="font-size:.72rem;color:var(--sage);opacity:.85;">Verified Buyer</div>
                </div>
              </div>
              <div class="stars mb-2" style="color:#f5c842;">★★★★★</div>
              <p style="font-size:.88rem;color:var(--sage);line-height:1.6;margin:0;">
                The sofa exceeded my expectations. Very comfortable, elegant, and perfect for our living room.
              </p>
            </div>
            <div class="review-item">
              <div class="review-header">
                <img src="https://i.pravatar.cc/80?img=25" class="avatar" alt="Verified Buyer">
                <div>
                  <div style="font-weight:700;color:#fff;font-size:.9rem;">Verified Buyer</div>
                  <div style="font-size:.72rem;color:var(--sage);opacity:.85;">Verified Buyer</div>
                </div>
              </div>
              <div class="stars mb-2" style="color:#f5c842;">★★★★★</div>
              <p style="font-size:.88rem;color:var(--sage);line-height:1.6;margin:0;">
                Excellent quality and very fast delivery. The furniture looks premium and modern.
              </p>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right arrow -->
        <button class="scroll-btn right" onclick="document.getElementById('reviewTrack').scrollBy({left:290,behavior:'smooth'})">
          <i class="fas fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </section>

  <script>
    // Drag-to-scroll for review track
    (function() {
      const track = document.getElementById('reviewTrack');
      if (!track) return;
      let isDown = false,
        startX, scrollLeft;
      track.addEventListener('mousedown', e => {
        isDown = true;
        track.classList.add('dragging');
        startX = e.pageX - track.offsetLeft;
        scrollLeft = track.scrollLeft;
      });
      track.addEventListener('mouseleave', () => {
        isDown = false;
        track.classList.remove('dragging');
      });
      track.addEventListener('mouseup', () => {
        isDown = false;
        track.classList.remove('dragging');
      });
      track.addEventListener('mousemove', e => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - track.offsetLeft;
        track.scrollLeft = scrollLeft - (x - startX) * 1.4;
      });
    })();
  </script>

  <script>
    // Contact form functionality that needs the DOM to be ready first
    window.addEventListener('DOMContentLoaded', function() {
      // If server-rendered success alert exists (PRG path), fade and remove it after a short delay
      (function() {
        const alert = document.getElementById('contactSuccessAlert');
        if (!alert) return;
        // start fading after 1 second, then remove after the transition
        setTimeout(() => {
          alert.style.opacity = '0';
          setTimeout(() => {
            if (alert.parentNode) alert.parentNode.removeChild(alert);
          }, 400);
        }, 1000);
      })();

      // AJAX submit for contact form: clears only the message textarea and shows a toast
      (function() {
        const form = document.getElementById('contactForm');
        const toast = document.getElementById('contactToast');
        if (!form || !toast) return;

        function showToast(msg, isError) {
          toast.textContent = msg;
          toast.classList.toggle('error', !!isError);
          toast.classList.add('show');
          setTimeout(() => toast.classList.remove('show'), 3500);
        }

        form.addEventListener('submit', async function(e) {
          e.preventDefault();
          const btn = this.querySelector('button[type=submit]');
          if (btn) {
            btn.disabled = true;
          }

          const data = new FormData(this);
          if (!data.has('send_message')) data.append('send_message', '1');

          try {
            const res = await fetch('website.php', {
              method: 'POST',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
              },
              body: data
            });
            const json = await res.json();
            if (json && json.success) {
              const ta = form.querySelector('textarea[name=c_message]');
              const subject = form.querySelector('input[name=c_subject]');
              if (ta) ta.value = '';
              if (subject) subject.value = '';
              showToast("Message sent! We'll get back to you soon.", false);
            } else {
              showToast(json.error || 'Could not send message. Please try again.', true);
            }
          } catch (err) {
            showToast('Network error. Please try again later.', true);
          } finally {
            if (btn) {
              btn.disabled = false;
            }
          }
        });
      })();
    });
  </script>


  <!-- CONTACT -->
  <section class="section" id="contact">
    <div class="container">
      <h2 class="section-title text-center">Get in Touch</h2>
      <div class="row g-4 justify-content-center">
        <div class="col-lg-8">
          <div class="contact-card">
            <h5 class="fw-bold mb-5" style="font-family:'Playfair Display',serif;color:var(--green);font-size:1.5rem;">Contact Us</h5>
            <?php if ($contactSuccess): ?>
            <?php elseif ($contactError): ?>
              <div style="background:#fee2e2;color:#b91c1c;border-radius:12px;padding:14px 18px;margin-bottom:18px;font-weight:600;font-size:.9rem;">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($contactError) ?>
              </div>
            <?php endif; ?>
            <form id="contactForm" method="POST" action="website.php#contact">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="input-box"><input type="text" name="c_name" placeholder=" " value="<?= htmlspecialchars($_POST['c_name'] ?? ($userName ?? '')) ?>" required><label>Full Name</label></div>
                </div>
                <div class="col-md-6">
                  <div class="input-box"><input type="email" name="c_email" placeholder=" " value="<?= htmlspecialchars($_POST['c_email'] ?? ($userEmail ?? '')) ?>" required><label>Email Address</label></div>
                </div>
              </div>
              <div class="input-box mt-3"><input type="text" name="c_subject" placeholder=" " value="<?= htmlspecialchars($_POST['c_subject'] ?? '') ?>"><label>Subject</label></div>
              <div class="input-box mt-3"><textarea name="c_message" placeholder=" " required style="min-height:180px;"><?= htmlspecialchars($_POST['c_message'] ?? '') ?></textarea><label>Your Message</label></div>
              <button type="submit" name="send_message" class="btn w-100 fw-bold text-white rounded-pill py-3 mt-4" style="background:var(--green);font-size:1rem;letter-spacing:0.5px;">Send Message</button>
            </form>
            <div id="contactToast" class="toast-fixed" aria-live="polite" aria-atomic="true"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="container">
      <div class="row gy-4">
        <!-- Brand col -->
        <div class="col-12 col-md-4 footer-col">
          <div class="d-flex align-items-center gap-2 mb-2">
            <img src="pci/Group_15.png" class="footer-logo-img" alt="Zythera logo">
            <span class="footer-brand-name">ZYTHERA</span>
          </div>
          <p class="footer-tagline">Furniture crafted for lives that deserve stillness, beauty, and meaning.</p>
          <div class="footer-social mt-3">
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          </div>
        </div>
        <!-- Navigate col -->
        <div class="col-6 col-md-2 footer-col">
          <p class="footer-col-title">Navigate</p>
          <a href="website.php" class="footer-link">Home</a>
          <a href="website.php#products" class="footer-link">Products</a>
          <a href="about.php" class="footer-link">About</a>
          <a href="website.php#contact" class="footer-link">Contact</a>
          <?php if ($userEmail): ?>
          <a href="profile.php" class="footer-link">My Profile</a>
          <?php else: ?>
          <a href="logsign.php" class="footer-link">Log In</a>
          <?php endif; ?>
        </div>
        <!-- Contact col -->
        <div class="col-6 col-md-3 footer-col">
          <p class="footer-col-title">Contact Us</p>
          <a href="tel:+639123456789" class="footer-link"><i class="fas fa-phone"></i>+63 912 345 6789</a>
          <a href="mailto:zythera@gmail.com" class="footer-link"><i class="fas fa-envelope"></i>zythera@gmail.com</a>
          <span class="footer-link" style="cursor:default;"><i class="fas fa-map-marker-alt"></i>123 Furniture St, Philippines</span>
          <span class="footer-link" style="cursor:default;"><i class="fas fa-clock"></i>Mon–Sat, 9 AM – 6 PM</span>
        </div>
        <!-- Reviews anchor col -->
        <div class="col-6 col-md-3 footer-col">
          <p class="footer-col-title">More</p>
          <a href="website.php#reviews" class="footer-link">Customer Reviews</a>
          <a href="website.php#contact" class="footer-link">Send a Message</a>
          <?php if ($userEmail && $userRole !== 'admin'): ?>
          <a href="orders.php" class="footer-link">My Orders</a>
          <?php endif; ?>
          <?php if ($userRole === 'admin'): ?>
          <a href="admin.php" class="footer-link">Admin Panel</a>
          <?php endif; ?>
        </div>
      </div>
      <hr class="footer-divider">
      <div class="footer-bottom">
        <span class="footer-bottom-text">&copy; <?= date('Y') ?> ZYTHERA. All rights reserved.</span>
        <span class="footer-bottom-text">Crafted with care in the Philippines.</span>
      </div>
    </div>
  </footer>
  <!-- ── CART SLIDE-OUT PANEL — hidden for admin ── -->
  <?php if ($userRole !== 'admin'): ?>
    <div id="cartPanel" style="
  position:fixed;top:0;right:-110vw;width:min(400px,100vw);height:100vh;
  background:#fff;z-index:10000;box-shadow:-8px 0 40px rgba(0,0,0,.18);
  display:flex;flex-direction:column;transition:right .35s cubic-bezier(.4,0,.2,1);
  border-radius:20px 0 0 20px;overflow:hidden;">

      <!-- Header -->
      <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);color:#fff;padding:20px 22px 16px;flex-shrink:0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <h5 style="margin:0;font-family:'Playfair Display',serif;font-weight:700;letter-spacing:1px;display:flex;align-items:center;gap:8px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
              My Cart
            </h5>
            <small id="cartItemCount" style="opacity:.75;font-size:.75rem;">
              <?php
              $initCount = 0;
              $initDistinctCount = 0;
              if ($userEmail && !empty($_SESSION['cart'][$userEmail])) {
                $initDistinctCount = count($_SESSION['cart'][$userEmail]);
                foreach ($_SESSION['cart'][$userEmail] as $ci) $initCount += (int)($ci['qty'] ?? 1);
              }
              echo $initCount === 0 ? 'Your cart is empty' : $initCount . ' item' . ($initCount === 1 ? '' : 's') . ' in cart';
              ?>
            </small>
          </div>
          <button onclick="closeCart()" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:36px;height:36px;font-size:1.1rem;cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Select-All bar — only appears once there are 2+ distinct products in the cart.
           Styled to match the nav's utility-icon look (soft green pill, same border/colors
           as the Settings button) so it reads as a toolbar action, not just another row. -->
      <div id="cartSelectAllBar" style="display:<?= $initDistinctCount >= 2 ? 'flex' : 'none' ?>;align-items:center;justify-content:space-between;
        padding:10px 18px;background:#f0f7f0;border-bottom:1px solid rgba(45,90,45,.16);flex-shrink:0;">
        <label style="display:flex;align-items:center;gap:9px;cursor:pointer;font-size:.82rem;font-weight:700;color:var(--green);margin:0;">
          <input type="checkbox" id="cartSelectAllCheckbox" onchange="toggleSelectAll(this.checked)"
            style="width:17px;height:17px;accent-color:var(--green);cursor:pointer;">
          Select All
        </label>
        <span id="cartSelectAllCount" style="font-size:.74rem;color:#5a8a5a;font-weight:600;"></span>
      </div>

      <!-- Items list -->
      <div id="cartItems" style="flex:1;overflow-y:auto;padding:16px;background:#f9f9f6;">
        <?php
        $initSubtotal = 0;
        // Build a stock lookup from session inventory
        $invStock = [];
        foreach ($_SESSION['inventory'] ?? [] as $invId => $inv) {
          $invStock[$invId] = (int)$inv->stock;
        }
        if ($userEmail && !empty($_SESSION['cart'][$userEmail])):
          foreach ($_SESSION['cart'][$userEmail] as $ci):
            $ciPrice  = (float)($ci['price'] ?? 0);
            $ciQty    = (int)($ci['qty'] ?? 1);
            $ciId     = (string)($ci['inv_id'] ?? '');
            $ciTotal  = $ciPrice * $ciQty;
            $ciStock  = $invStock[$ciId] ?? 99;
            $initSubtotal += $ciTotal;
            $stockLabel = $ciStock === 0 ? 'Out of Stock' : ($ciStock <= 5 ? 'Low stock: ' . $ciStock . ' left' : 'In stock: ' . $ciStock);
            $stockColor = $ciStock === 0 ? '#dc2626' : ($ciStock <= 5 ? '#f59e0b' : '#16a34a');
        ?>
            <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
              <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <input type="checkbox" class="cart-select-checkbox" value="<?= htmlspecialchars($ciId) ?>"
                  onchange="toggleCartSelection('<?= htmlspecialchars($ciId) ?>', this.checked)"
                  style="width:18px;height:18px;accent-color:var(--green);flex-shrink:0;cursor:pointer;"
                  aria-label="Select <?= htmlspecialchars($ci['name'] ?? 'item') ?> for checkout">
                <img src="<?= htmlspecialchars($ci['image'] ?? '') ?>" alt=""
                  style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                  onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
                <div style="flex:1;min-width:0;">
                  <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($ci['name'] ?? '') ?>
                  </div>
                  <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱<?= number_format($ciPrice, 2) ?> each</div>
                  <div style="font-size:.68rem;color:<?= $stockColor ?>;font-weight:600;margin-top:2px;">
                    <?= $stockLabel ?>
                  </div>
                </div>
                <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                  ₱<?= number_format($ciTotal, 2) ?>
                </div>
              </div>
              <!-- Qty stepper + remove row -->
              <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
                <div style="display:flex;align-items:center;gap:0;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                  <button onclick="cartQty('<?= $ciId ?>', 'minus')"
                    style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                  <span id="panel-qty-<?= $ciId ?>" style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;"><?= $ciQty ?></span>
                  <button onclick="cartQty('<?= $ciId ?>', 'plus')"
                    style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
                </div>
                <button onclick="cartQty('<?= $ciId ?>', 'remove')"
                  style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                  onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                  <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
                </button>
              </div>
            </div>
          <?php
          endforeach;
        else: ?>
          <div style="text-align:center;padding:60px 20px;color:#bbb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round" style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
              <circle cx="9" cy="21" r="1" />
              <circle cx="20" cy="21" r="1" />
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
            </svg>
            <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Footer with subtotal + checkout -->
      <div id="cartFooter" style="padding:16px 20px;background:#fff;border-top:2px solid #f0f0eb;flex-shrink:0;<?= ($initSubtotal > 0) ? '' : 'display:none;' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <span style="font-weight:600;color:#666;font-size:.85rem;">SUBTOTAL</span>
          <span id="cartSubtotal" style="font-weight:800;color:#2d5a2d;font-size:1.15rem;">₱<?= number_format($initSubtotal) ?></span>
        </div>
        <div id="cartSelectionError" style="display:none;color:#b91c1c;background:#fee2e2;border-radius:10px;padding:8px 10px;font-size:.78rem;font-weight:700;margin-bottom:10px;text-align:center;">
          Please select products first.
        </div>
        <a href="checkout.php" id="checkoutSelectedBtn" onclick="return goToSelectedCheckout(event)" style="display:block;background:var(--green);color:#fff;text-align:center;padding:14px;border-radius:50px;text-decoration:none;font-weight:700;font-size:.95rem;transition:.2s;">
          Checkout Now
        </a>
      </div>
    </div>
    <div id="cartBackdrop" onclick="closeCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;backdrop-filter:blur(2px);"></div>
  <?php endif; /* end admin cart hide */ ?>

  <div id="toast-msg" class="toast-fixed"></div>
  <?php if (!empty($contactSuccess)): $toastMessage = "Message sent! We'll get back to you soon.";
    $toastType = 'success';
    include __DIR__ . '/includes/_toast.php';
  endif; ?>

  <!-- Logout Confirmation Modal -->
<div id="logoutModalOverlay" class="logout-modal-overlay">
    <div class="logout-modal">
        <h2>Log Out Confirmation</h2>
        <p>Are you sure you want to log out of your account?</p>
        <div class="logout-modal-buttons">
            <button type="button" class="logout-cancel-btn" onclick="closeLogoutModal(event)">
                Stay
            </button>
            <button type="button" class="logout-confirm-btn" onclick="performLogout()">
                Logout
            </button>
        </div>
    </div>
</div>

  <!-- Edit Review Modal -->
  <div class="review-edit-modal-bg" id="editReviewModalBg" onclick="closeEditReview()">
    <div class="review-edit-modal" onclick="event.stopPropagation()">
      <h5><i class="fas fa-pencil-alt me-2" style="font-size:.9rem;"></i>Edit Your Review</h5>
      <div class="review-edit-stars" id="editStars">
        <span data-val="1">★</span>
        <span data-val="2">★</span>
        <span data-val="3">★</span>
        <span data-val="4">★</span>
        <span data-val="5">★</span>
      </div>
      <textarea id="editReviewText" maxlength="500"
        placeholder="Share your experience with this furniture..." rows="4"
        oninput="updateEditCharCount()"></textarea>
      <div class="review-char-count" id="editCharCount">0 / 500</div>
      <div class="review-edit-modal-actions">
        <button class="btn-cancel-review" onclick="closeEditReview()">Cancel</button>
        <button class="btn-save-review" id="saveReviewBtn" onclick="saveEditReview()">Save Changes</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ── Cart state seeded from PHP session + live DB via fetch ──
    let cartItemsJS = <?= json_encode(array_values(array_map(function ($i) {
                        return ['inv_id' => (string)($i['inv_id'] ?? ''), 'name' => $i['name'] ?? '', 'price' => (float)($i['price'] ?? 0), 'qty' => (int)($i['qty'] ?? 1), 'image' => $i['image'] ?? ''];
                      }, $_SESSION['cart'][$userEmail] ?? []))) ?>;
    let selectedCartIds = new Set(JSON.parse(localStorage.getItem('zythera_selected_cart') || '[]').map(String));
    // Stock map from PHP inventory (inv_id => stock)
    const stockMap = <?= json_encode(array_combine(
                        array_keys($_SESSION['inventory'] ?? []),
                        array_map(fn($i) => (int)$i->stock, $_SESSION['inventory'] ?? [])
                      )) ?>;

    // On page load, always sync cart from DB so badge is accurate
    (function syncCartOnLoad() {
      <?php if ($userEmail && $userRole !== 'admin'): ?>
        fetch('getcart.php', {
            credentials: 'same-origin'
          })
          .then(r => r.json())
          .then(data => {
            if (data.cart) {
              cartItemsJS = data.cart;
              const badge = document.getElementById('cart-badge');
              if (badge) {
                badge.textContent = data.total_items;
                badge.style.display = data.total_items > 0 ? '' : 'none';
              }
              renderCart();
            }
          }).catch(() => {});
      <?php endif; ?>
    })();

    // ── Open / Close cart panel ───────────────────────────────────
    function openCart() {
      document.getElementById('cartPanel').style.right = '0';
      document.getElementById('cartBackdrop').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeCart() {
      document.getElementById('cartPanel').style.right = '-110vw';
      document.getElementById('cartBackdrop').style.display = 'none';
      document.body.style.overflow = '';
    }

    function syncSelectedCartIds() {
      const currentIds = new Set(cartItemsJS.map(item => String(item.inv_id)));
      selectedCartIds = new Set([...selectedCartIds].filter(id => currentIds.has(id)));
      localStorage.setItem('zythera_selected_cart', JSON.stringify([...selectedCartIds]));
    }

    function toggleCartSelection(itemId, checked) {
      itemId = String(itemId);
      if (checked) selectedCartIds.add(itemId);
      else selectedCartIds.delete(itemId);
      syncSelectedCartIds();
      updateSelectAllUI();
      const err = document.getElementById('cartSelectionError');
      if (err && selectedCartIds.size > 0) err.style.display = 'none';
    }

    // ── Select-All toolbar: check/uncheck every item at once ──────
    function toggleSelectAll(checked) {
      document.querySelectorAll('.cart-select-checkbox').forEach(cb => {
        cb.checked = checked;
      });
      cartItemsJS.forEach(item => {
        const id = String(item.inv_id);
        if (checked) selectedCartIds.add(id);
        else selectedCartIds.delete(id);
      });
      syncSelectedCartIds();
      updateSelectAllUI();
      const err = document.getElementById('cartSelectionError');
      if (err && selectedCartIds.size > 0) err.style.display = 'none';
    }

    // Keeps the Select-All checkbox (checked/indeterminate), its visibility,
    // and the "x of y selected" label in sync with the current selection.
    function updateSelectAllUI() {
      const bar = document.getElementById('cartSelectAllBar');
      const cb = document.getElementById('cartSelectAllCheckbox');
      const countEl = document.getElementById('cartSelectAllCount');
      if (!bar || !cb) return;

      const total = cartItemsJS.length;
      if (total < 2) {
        bar.style.display = 'none';
        return;
      }
      bar.style.display = 'flex';

      const selectedCount = cartItemsJS.filter(i => selectedCartIds.has(String(i.inv_id))).length;
      cb.checked = total > 0 && selectedCount === total;
      cb.indeterminate = selectedCount > 0 && selectedCount < total;
      if (countEl) countEl.textContent = selectedCount + ' of ' + total + ' selected';
    }

    function goToSelectedCheckout(event) {
      syncSelectedCartIds();
      if (selectedCartIds.size === 0) {
        if (event) event.preventDefault();
        const err = document.getElementById('cartSelectionError');
        if (err) {
          err.textContent = 'Please select products first.';
          err.style.display = 'block';
        }
        openCart();
        return false;
      }
      const selected = encodeURIComponent([...selectedCartIds].join(','));
      window.location.href = 'checkout.php?selected=' + selected;
      if (event) event.preventDefault();
      return false;
    }

    document.addEventListener('DOMContentLoaded', function () {
      if (new URLSearchParams(window.location.search).get('cart_error') === 'select_items') {
        const err = document.getElementById('cartSelectionError');
        if (err) {
          err.textContent = 'Please select products first.';
          err.style.display = 'block';
        }
        openCart();
      }
    });

    // ── Re-render cart panel items + subtotal + header count ──────
    function renderCart() {
      const container = document.getElementById('cartItems');
      const footer = document.getElementById('cartFooter');
      const subEl = document.getElementById('cartSubtotal');
      const countEl = document.getElementById('cartItemCount');

      let subtotal = 0,
        totalQty = 0,
        distinctCount = cartItemsJS.length;

      syncSelectedCartIds();

      if (cartItemsJS.length === 0) {
        container.innerHTML = `
          <div style="text-align:center;padding:60px 20px;color:#bbb;">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
              stroke="#d4e4d4" stroke-width="1.5" stroke-linecap="round"
              style="margin-bottom:14px;display:block;margin-left:auto;margin-right:auto;">
              <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <p style="font-size:.9rem;line-height:1.6;">Your cart is empty.<br>Add some furniture!</p>
          </div>`;
        if (footer) footer.style.display = 'none';
        if (countEl) countEl.textContent = 'Your cart is empty';
        const badge = document.getElementById('cart-badge');
        if (badge) {
          badge.textContent = '0';
          badge.style.display = 'none';
        }
        updateSelectAllUI();
        return;
      }

      let html = '';
      cartItemsJS.forEach(item => {
        const price = Number(item.price) || 0;
        const qty = Number(item.qty) || 1;
        const lineTotal = price * qty;
        const stock = stockMap[item.inv_id] ?? 99;
        subtotal += lineTotal;
        totalQty += qty;
        const imgSrc = item.image || 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop';
        const itemId = String(item.inv_id);
        const checked = selectedCartIds.has(itemId) ? 'checked' : '';

        const stockLabel = stock === 0 ? 'Out of Stock' :
          stock <= 5 ? 'Low stock: ' + stock + ' left' :
          'In stock: ' + stock;
        const stockColor = stock === 0 ? '#dc2626' : stock <= 5 ? '#f59e0b' : '#16a34a';

        html += `
          <div style="background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:10px;
            box-shadow:0 2px 10px rgba(0,0,0,.06);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
              <input type="checkbox" class="cart-select-checkbox" value="${escHtml(itemId)}" ${checked}
                onchange="toggleCartSelection('${escHtml(itemId)}', this.checked)"
                style="width:18px;height:18px;accent-color:var(--green);flex-shrink:0;cursor:pointer;"
                aria-label="Select ${escHtml(String(item.name || 'item'))} for checkout">
              <img src="${escHtml(imgSrc)}" alt=""
                style="width:54px;height:54px;object-fit:cover;border-radius:10px;flex-shrink:0;background:#d4e4d4;"
                onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=60&fit=crop'">
              <div style="flex:1;min-width:0;">
                <div style="font-weight:600;color:#1a2e1a;font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  ${escHtml(String(item.name))}
                </div>
                <div style="color:#7aab7a;font-size:.76rem;margin-top:1px;">₱${price.toLocaleString('en-PH')} each</div>
                <div style="font-size:.68rem;color:${stockColor};font-weight:600;margin-top:2px;">${stockLabel}</div>
              </div>
              <div style="font-weight:700;color:#2d5a2d;white-space:nowrap;font-size:.92rem;">
                ₱${lineTotal.toLocaleString('en-PH')}
              </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
              <div style="display:flex;align-items:center;border:1.5px solid #d4e4d4;border-radius:8px;overflow:hidden;">
                <button onclick="cartQty('${item.inv_id}','minus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">−</button>
                <span id="panel-qty-${item.inv_id}"
                  style="width:34px;text-align:center;font-weight:700;font-size:.88rem;color:#1a2e1a;">${qty}</span>
                <button onclick="cartQty('${item.inv_id}','plus')"
                  style="width:30px;height:30px;border:none;background:#d4e4d4;color:#2d5a2d;font-weight:700;font-size:1rem;cursor:pointer;line-height:1;">+</button>
              </div>
              <button onclick="cartQty('${item.inv_id}','remove')"
                style="background:none;border:none;color:#dc2626;font-size:.78rem;font-weight:600;cursor:pointer;padding:4px 8px;border-radius:6px;transition:.15s;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='none'">
                <i class="fas fa-trash-alt" style="margin-right:4px;"></i>Remove
              </button>
            </div>
          </div>`;
      });

      container.innerHTML = html;
      if (subEl) subEl.textContent = '₱' + subtotal.toLocaleString('en-PH');
      if (footer) footer.style.display = 'block';
      if (countEl) {
        countEl.textContent = distinctCount === 1 ? '1 item in cart' : distinctCount + ' items in cart';
      }
      const badge = document.getElementById('cart-badge');
      if (badge) {
        badge.textContent = distinctCount;
        badge.style.display = distinctCount > 0 ? '' : 'none';
      }
      updateSelectAllUI();
    }

    // ── Qty stepper in cart sidebar → update_cart.php ────────────
    function cartQty(itemId, action) {
      // Client-side stock cap before even hitting server
      if (action === 'plus') {
        const item = cartItemsJS.find(i => String(i.inv_id) === String(itemId));
        const max = stockMap[itemId] ?? 9999;
        if (item && Number(item.qty) >= max) {
          showToast('Maximum stock (' + max + ') already in cart.', 'error');
          return;
        }
      }

      fetch('update_cart.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'item_id=' + encodeURIComponent(itemId) + '&qty_action=' + encodeURIComponent(action)
      })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          showToast(data.message || 'Could not update cart.', 'error');
          return;
        }
        // Replace local cart state with authoritative server response
        cartItemsJS = data.cart || [];
        renderCart();
        // If checkout is open in another tab/frame, notify it via BroadcastChannel
        try {
          const bc = new BroadcastChannel('zythera_cart');
          bc.postMessage({ type: 'cart_updated', cart: cartItemsJS });
          bc.close();
        } catch (e) { /* BroadcastChannel not supported — no-op */ }
      })
      .catch(() => showToast('Could not update cart. Try again.', 'error'));
    }

    // ── Add item to local JS state then re-render ─────────────────
    function updateCartPanel(newItem) {
      const existing = cartItemsJS.find(i => String(i.inv_id) === String(newItem.inv_id));
      if (existing) {
        existing.qty = Number(existing.qty) + Number(newItem.qty);
        if (!existing.image && newItem.image) existing.image = newItem.image;
      } else {
        cartItemsJS.push({
          inv_id: newItem.inv_id,
          name: newItem.name,
          price: Number(newItem.price),
          qty: Number(newItem.qty),
          image: newItem.image || ''
        });
      }
      renderCart();
    }

    function escHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Time display ──────────────────────────────────────────────
    function updateTime() {
      const el = document.getElementById('liveTime');
      if (el) el.textContent = new Date().toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }
    setInterval(updateTime, 1000);
    updateTime();

    function toggleDesc(btn) {
      const p = btn.parentElement;
      const s = p.querySelector('.desc-short');
      const f = p.querySelector('.desc-full');
      const hidden = f.classList.contains('d-none');
      f.classList.toggle('d-none', !hidden);
      s.classList.toggle('d-none', hidden);
      btn.textContent = hidden ? 'See Less' : 'See More';
    }

    function showToast(msg, type = 'success') {
      const t = document.getElementById('toast-msg');
      t.textContent = msg;
      t.className = 'toast-fixed show' + (type === 'error' ? ' error' : '');
      setTimeout(() => t.classList.remove('show'), 3500);
    }

    function addToCart(id, name, price, image) {
      <?php if (!$userEmail): ?>
        window.location.href = 'logsign.php';
        return;
      <?php endif; ?>

      const qtyEl = document.getElementById('qty-' + id);
      const qty = qtyEl ? (parseInt(qtyEl.value) || 1) : 1;

      const btn = document.getElementById('btn-' + id);
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adding...';
      }

      fetch('addcart.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'inv_id=' + encodeURIComponent(id) +
            '&name=' + encodeURIComponent(name) +
            '&price=' + encodeURIComponent(price) +
            '&qty=' + encodeURIComponent(qty) +
            '&image=' + encodeURIComponent(image || '')
        })
        .then(r => r.text())
        .then(raw => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          let data;
          try {
            data = JSON.parse(raw);
          } catch (e) {
            showToast('Server error — check PHP logs.', 'error');
            console.error('Non-JSON response:', raw);
            return;
          }

          if (data.success) {
            // Update full cart state from server response
            if (data.cart) {
              cartItemsJS = data.cart;
            }
            const badge = document.getElementById('cart-badge');
            if (badge) {
              badge.textContent = data.total_items;
              badge.style.display = data.total_items > 0 ? '' : 'none';
            }
            renderCart();
            showToast('✓ ' + name + ' added to cart!');
          } else if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            showToast(data.message || 'Error adding to cart.', 'error');
          }
        })
        .catch(err => {
          if (btn) {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
          }
          showToast('Connection error. Try again.', 'error');
          console.error(err);
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) {
          e.preventDefault();
          t.scrollIntoView({
            behavior: 'smooth'
          });
        }
      });
    });

  // ── Three-dot menu toggle ──
  function toggleReviewMenu(cardId) {
    var menu = document.getElementById('menu-' + cardId);
    if (!menu) return;
    var isOpen = menu.classList.contains('open');
    // Close all open menus first
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) {
      m.classList.remove('open');
    });
    if (!isOpen) menu.classList.add('open');
  }

  // Close menus when clicking outside
  document.addEventListener('click', function() {
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) {
      m.classList.remove('open');
    });
  });

  // ── Delete own review ──
  function deleteMyReview(reviewId) {
    if (!reviewId) return;
    // Close any open dropdown
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) { m.classList.remove('open'); });
    if (!confirm('Delete your review? This cannot be undone.')) return;
    fetch('admin_action.php?delete_review=' + encodeURIComponent(reviewId))
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Review deleted.');
          setTimeout(() => location.reload(), 900);
        } else {
          alert(data.message || 'Could not delete review.');
        }
      }).catch(() => alert('Network error. Please try again.'));
  }

  // ── Edit review modal ──
  var _editReviewId   = 0;
  var _editRating     = 5;

  function openEditReview(reviewId, currentRating, currentComment) {
    document.querySelectorAll('.review-dropdown.open').forEach(function(m) { m.classList.remove('open'); });
    _editReviewId = reviewId;
    _editRating   = currentRating || 5;

    var ta = document.getElementById('editReviewText');
    if (ta) {
      ta.value = currentComment || '';
      updateEditCharCount();
    }
    setEditStars(_editRating);
    document.getElementById('editReviewModalBg').classList.add('open');
    if (ta) setTimeout(function() { ta.focus(); }, 80);
  }

  function closeEditReview() {
    document.getElementById('editReviewModalBg').classList.remove('open');
  }

  function setEditStars(val) {
    _editRating = val;
    document.querySelectorAll('#editStars span').forEach(function(s) {
      s.classList.toggle('lit', parseInt(s.dataset.val) <= val);
    });
  }

  document.querySelectorAll('#editStars span').forEach(function(s) {
    s.addEventListener('click', function() { setEditStars(parseInt(this.dataset.val)); });
    s.addEventListener('mouseover', function() {
      document.querySelectorAll('#editStars span').forEach(function(x) {
        x.classList.toggle('lit', parseInt(x.dataset.val) <= parseInt(s.dataset.val));
      });
    });
  });
  var editStarsEl = document.getElementById('editStars');
  if (editStarsEl) {
    editStarsEl.addEventListener('mouseleave', function() { setEditStars(_editRating); });
  }

  function updateEditCharCount() {
    var ta    = document.getElementById('editReviewText');
    var cc    = document.getElementById('editCharCount');
    if (!ta || !cc) return;
    var len   = ta.value.length;
    cc.textContent = len + ' / 500';
    cc.classList.toggle('over', len > 500);
  }

  function saveEditReview() {
    var ta  = document.getElementById('editReviewText');
    var btn = document.getElementById('saveReviewBtn');
    if (!ta) return;
    var comment = ta.value.trim();
    if (!comment) { showToast('Review text cannot be empty.', 'error'); return; }
    if (comment.length > 500) { showToast('Review must be 500 characters or fewer.', 'error'); return; }

    btn.disabled = true;
    btn.textContent = 'Saving…';

    var body = 'edit_review=1&review_id=' + encodeURIComponent(_editReviewId) +
               '&rating='    + encodeURIComponent(_editRating) +
               '&comment='   + encodeURIComponent(comment);

    fetch('admin_action.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        if (data.success) {
          showToast('Review updated!');
          closeEditReview();
          setTimeout(() => location.reload(), 900);
        } else {
          showToast(data.message || 'Could not save review.', 'error');
        }
      }).catch(() => {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
        showToast('Network error. Try again.', 'error');
      });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeEditReview();
  });

  // ── Expand / collapse long review text ──
  function toggleReview(cardId) {
    var body = document.getElementById('body-' + cardId);
    var btn  = document.getElementById('btn-'  + cardId);
    if (!body || !btn) return;
    var isCollapsed = body.classList.contains('clamped');
    body.classList.toggle('clamped', !isCollapsed);
    btn.textContent = isCollapsed ? 'Show less' : 'Read more';
  }

  // ── Logout modal functions ──
 function openLogoutModal() {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
}

function closeLogoutModal(event) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
}

function performLogout() {
  const confirmBtn = document.querySelector('.logout-confirm-btn');
  if (confirmBtn) {
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Logging out...';
  }
  window.location.href = 'logout.php';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutModal();
  }
});

// Close modal on outside click
document.addEventListener('click', function(e) {
  const overlay = document.getElementById('logoutModalOverlay');
  if (overlay && e.target === overlay) {
    closeLogoutModal(e);
  }
});

// ── Review Edit Functions ──
let currentEditingReviewId = null;
let currentEditingRating = 0;

function openEditReview(reviewId, currentRating, currentComment) {
  currentEditingReviewId = reviewId;
  currentEditingRating = parseInt(currentRating) || 5;
  
  const modal = document.getElementById('editReviewModalBg');
  const textarea = document.getElementById('editReviewText');
  
  if (modal && textarea) {
    textarea.value = currentComment || '';
    updateEditStars(currentEditingRating);
    updateEditCharCount();
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    setTimeout(() => {
      textarea.focus();
      textarea.select();
    }, 100);
  }
}

function closeEditReview() {
  const modal = document.getElementById('editReviewModalBg');
  if (modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentEditingReviewId = null;
    currentEditingRating = 0;
  }
}

function updateEditStars(rating) {
  currentEditingRating = rating;
  const stars = document.querySelectorAll('.review-edit-stars span');
  stars.forEach((star, index) => {
    if (index < rating) {
      star.classList.add('selected');
    } else {
      star.classList.remove('selected');
    }
  });
}

function updateEditCharCount() {
  const textarea = document.getElementById('editReviewText');
  const charCount = document.getElementById('editCharCount');
  
  if (textarea && charCount) {
    const length = textarea.value.length;
    charCount.textContent = length + ' / 500';
    
    if (length > 450) {
      charCount.style.color = '#dc2626';
    } else if (length > 400) {
      charCount.style.color = '#f59e0b';
    } else {
      charCount.style.color = '#999';
    }
  }
}

function saveEditReview() {
  if (!currentEditingReviewId) {
    alert('Error: No review selected');
    return;
  }
  
  const textarea = document.getElementById('editReviewText');
  const comment = textarea.value.trim();
  
  if (!comment || comment.length < 1 || comment.length > 500) {
    alert('Comment must be between 1 and 500 characters');
    return;
  }
  
  if (currentEditingRating < 1 || currentEditingRating > 5) {
    alert('Please select a rating between 1 and 5 stars');
    return;
  }
  
  const saveBtn = document.getElementById('saveReviewBtn');
  const originalText = saveBtn.textContent;
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';
  
  fetch('admin_action.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
      edit_review: '1',
      review_id: currentEditingReviewId,
      rating: currentEditingRating,
      comment: comment
    }),
    credentials: 'same-origin'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Review updated successfully');
      closeEditReview();
      location.reload();
    } else {
      alert('Error: ' + (data.message || 'Could not update review'));
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Error updating review. Please try again.');
    saveBtn.disabled = false;
    saveBtn.textContent = originalText;
  });
}

// Setup edit stars click handlers
document.addEventListener('DOMContentLoaded', function() {
  const editStars = document.querySelectorAll('.review-edit-stars span');
  editStars.forEach(star => {
    star.addEventListener('click', function() {
      const rating = this.getAttribute('data-val');
      updateEditStars(rating);
    });
  });
  
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeEditReview();
    }
  });
  
  const editModalBg = document.getElementById('editReviewModalBg');
  if (editModalBg) {
    editModalBg.addEventListener('click', function(e) {
      if (e.target === editModalBg) {
        closeEditReview();
      }
    });
  }
});

</script>
</body>

</html>
