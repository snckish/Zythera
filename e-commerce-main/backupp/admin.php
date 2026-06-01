<?php
require_once 'config.php';

// ── Admin-only access control ─────────────────────────────────
// Only users with role = 'admin' in the database can enter.
$loggedIn = $_SESSION['logged_in_user'] ?? null;

// Not logged in at all → go to login page
if (!$loggedIn) {
    header('Location: logsign.php');
    exit;
}

// Check role from session (set during login) or from DB
$adminRole = $_SESSION['role'] ?? '';
if ($adminRole !== 'admin') {
    // Double-check from DB in case session was tampered
    $dbCheck = getDBConnection()->prepare("SELECT role FROM users WHERE email = ? LIMIT 1");
    $dbCheck->execute([$loggedIn]);
    $dbRow = $dbCheck->fetch();
    if (!$dbRow || $dbRow->role !== 'admin') {
        header('Location: website.php');
        exit;
    }
    $_SESSION['role'] = 'admin'; // fix session
}
// ─────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZYTHERA | ADMIN</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,700&family=Roboto:wght@300;400;500;700&family=Lora:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
    :root{--logo-font:'Playfair Display',serif;--ui-font:'Roboto',sans-serif;--text-font:'Lora',serif}
    body{font-family:var(--ui-font);}
    h1,h2,h3,h4,h5,.navbar-brand,.brand-name,.section-title,.page-header h2,footer .footer-brand{font-family:var(--logo-font);}
    p,small,.caption,.text-muted{font-family:var(--text-font);}
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --cream: #f5f2ec;
            --sage-light: #d4e4d4;
            --sage-dark: #7aab7a;
            --deep-green: #2d5a2d;
            --white: #ffffff;
        }

        body { 
            background-color: var(--cream); 
            font-family: 'DM Sans', sans-serif;
            color: var(--deep-green);
        }

        .brand-admin { 
            font-family: 'Playfair Display', serif;
            font-weight: 700; 
            font-size: 1.6rem;
            letter-spacing: 1px;
            color: var(--deep-green);
            text-decoration: none;
        }

        .navbar { 
            background: var(--white); 
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 0.8rem 2rem;
        }

        .card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            background-color: var(--white);
        }

        .form-control, .form-select {
            background-color: var(--sage-light);
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: var(--deep-green);
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background-color: var(--white);
            border-color: var(--deep-green);
            box-shadow: 0 0 0 3px rgba(45,90,45,.15);
            color: var(--deep-green);
        }

        .form-control::placeholder {
            color: rgba(45,90,45,.5);
        }

        .form-select:focus {
            border-color: var(--deep-green);
            box-shadow: 0 0 0 3px rgba(45,90,45,.15);
            color: var(--deep-green);
        }

        .form-label {
            color: var(--deep-green);
            font-family: 'DM Sans', sans-serif;
        }

        .btn-zythera {
            background-color: var(--deep-green);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 2rem;
            font-weight: 500;
            border: none;
            transition: 0.3s;
        }

        .btn-zythera:hover {
            background-color: var(--sage-dark);
            color: white;
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: var(--sage-light);
            color: var(--deep-green);
            border-radius: 10px;
        }

        .table thead { background-color: var(--sage-light); }

        .table th {
            border: none;
            padding: 1rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .user-capsule {
            background: var(--white);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50px;
            padding: 5px 5px 5px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-info-text { text-align: right; line-height: 1.2; }

        .user-name {
            font-weight: 600;
            color: var(--deep-green);
            display: block;
            font-size: 0.95rem;
        }

        #datetime { font-size: 0.75rem; color: var(--deep-green); opacity: .65; display: block; }

        .user-avatar {
            background-color: var(--deep-green);
            color: var(--white);
            width: 40px; height: 40px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold; font-size: 0.85rem; letter-spacing: 0.5px;
        }

        /* ── Search Bar ── */
        .search-wrap { position: relative; max-width: 340px; }

        .search-wrap input {
            width: 100%;
            height: 42px;
            padding: 0 2.4rem 0 2.6rem;
            border-radius: 50px;
            border: 2px solid var(--sage-light);
            background: var(--sage-light);
            font-size: .88rem;
            color: var(--deep-green);
            outline: none;
            transition: .2s;
            font-family: 'DM Sans', sans-serif;
        }

        .search-wrap input:focus {
            border-color: var(--sage-dark);
            background: #fff;
        }

        .search-icon {
            position: absolute;
            left: 13px; top: 50%;
            transform: translateY(-50%);
            color: var(--sage-dark);
            font-size: .85rem;
            pointer-events: none;
        }

        .clear-btn {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--deep-green); opacity: .5; font-size: .85rem;
            cursor: pointer; display: none; line-height: 1;
        }

        /* Highlight matched text */
        mark {
            background: #c8ecc8;
            color: var(--deep-green);
            border-radius: 3px;
            padding: 0 2px;
        }

        /* No results row */
        #noResults td {
            color: var(--deep-green);
            opacity: .5;
            font-size: .9rem;
            padding: 2rem 0;
        }

        #result-count {
            font-size: .78rem;
            color: var(--deep-green);
            opacity: .7;
            font-weight: 400;
            margin-left: 6px;
        }

        /* ── Sidebar Layout ── */
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            width: 240px;
            background: linear-gradient(180deg, #1a2e1a 0%, #2d5a2d 100%);
            z-index: 200;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,.15);
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 24px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-brand .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #fff;
            letter-spacing: 3px;
        }
        .sidebar-brand .brand-sub {
            font-size: .72rem;
            color: rgba(255,255,255,.5);
            letter-spacing: 1px;
        }
        .sidebar-nav {
            padding: 16px 0;
            flex: 1;
        }
        .sidebar-label {
            font-size: .65rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            padding: 14px 20px 6px;
            font-weight: 600;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            color: rgba(255,255,255,.75);
            text-decoration: none;
            font-size: .88rem;
            font-weight: 500;
            transition: .2s;
            border-left: 3px solid transparent;
            cursor: pointer;
            background: none;
            border-top: none;
            border-right: none;
            border-bottom: none;
            width: 100%;
            text-align: left;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background: rgba(255,255,255,.1);
            color: #fff;
            border-left-color: #d4e4d4;
        }
        .sidebar-link i { width: 18px; text-align: center; font-size: .9rem; }
        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.1);
        }
        .sidebar-footer a {
            color: rgba(255,255,255,.55);
            font-size: .8rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: .2s;
        }
        .sidebar-footer a:hover { color: #fff; }

        /* Main content offset */
        .main-content {
            margin-left: 240px;
        }
        .top-navbar {
            background: var(--white);
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 0.8rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .order-card {
            background: #fff;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            border-left: 4px solid var(--sage-dark);
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }
        .order-user-tag {
            display: inline-block;
            background: var(--sage-light);
            color: var(--deep-green);
            border-radius: 20px;
            font-size: .72rem;
            font-weight: 600;
            padding: 2px 10px;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-name">ZYTHERA</div>
        <div class="brand-sub">Admin Panel</div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-label">Main</div>
        <button class="sidebar-link active" onclick="showSection('inventory')" id="nav-inventory">
            <i class="fas fa-boxes"></i> Product Inventory
        </button>
        <button class="sidebar-link" onclick="showSection('addproduct')" id="nav-addproduct">
            <i class="fas fa-plus-circle"></i> Add Product
        </button>
        <button class="sidebar-link" onclick="showSection('analytics')" id="nav-analytics">
            <i class="fas fa-chart-bar"></i> Analytics
        </button>

        <div class="sidebar-label" style="margin-top:8px;">Orders</div>
        <button class="sidebar-link" onclick="showSection('orders')" id="nav-orders" style="display:flex;align-items:center;justify-content:space-between;">
            <span><i class="fas fa-receipt me-2"></i>Order History</span>
            <?php
            $pendingCount = 0;
            try {
                $pStmt = getDBConnection()->query("SELECT COUNT(*) FROM orders WHERE status='Pending'");
                $pendingCount = (int)$pStmt->fetchColumn();
            } catch(Exception $e) {}
            if ($pendingCount > 0): ?>
            <span id="pending-badge" style="background:#dc2626;color:#fff;border-radius:50px;font-size:.6rem;font-weight:700;padding:2px 7px;"><?= $pendingCount ?></span>
            <?php endif; ?>
        </button>
        <button class="sidebar-link" onclick="showSection('users')" id="nav-users">
            <i class="fas fa-users"></i> User Summary
        </button>
        <button class="sidebar-link" onclick="showSection('messages')" id="nav-messages">
            <i class="fas fa-envelope"></i> Messages
        </button>

        <div class="sidebar-label" style="margin-top:8px;">Store</div>
        <a href="website.php" class="sidebar-link">
            <i class="fas fa-store"></i> View Store
        </a>
        <a href="profile.php" class="sidebar-link">
            <i class="fas fa-user-circle"></i> My Profile
        </a>
    </nav>

    <div class="sidebar-footer">
        <?php
        $adminEmail = $_SESSION['logged_in_user'] ?? 'Admin';
        $db = getDBConnection();

$stmt = $db->prepare("SELECT name FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);

$adminData = $stmt->fetch();

$adminName = $adminData ? $adminData->name : 'Admin';
        ?>
        <div style="color:rgba(255,255,255,.7);font-size:.8rem;margin-bottom:10px;">
            <i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($adminName) ?>
        </div>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- ── MAIN CONTENT ── -->
<div class="main-content">

<nav class="top-navbar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <h6 class="mb-0 fw-bold" style="color:var(--deep-green);font-family:'Playfair Display',serif;font-size:1.1rem;letter-spacing:.5px;">
            <span id="sectionTitle">Product Inventory</span>
        </h6>
    </div>
    <div class="user-capsule shadow-sm">
        <div class="user-info-text">
            <span class="user-name">Welcome back, <?= htmlspecialchars($adminName) ?></span>
            <span id="datetime" style="font-size:.7rem;color:var(--deep-green);opacity:.65;display:block;"></span>
        </div>
        <div class="user-avatar"><?= strtoupper(substr($adminName, 0, 2)) ?></div>
    </div>
</nav>

<div class="container py-4">

<!-- ── SECTION: Product Inventory ── -->
<div id="section-inventory">
<div class="card p-3 mt-3">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <span id="result-count"></span>
        <div class="search-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text"
                   id="searchInput"
                   placeholder="Search products…"
                   oninput="searchProducts(this.value)"
                   autocomplete="off">
            <button class="clear-btn" id="clearBtn" onclick="clearSearch()" title="Clear">✕</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle text-center">
            <thead class="table-success">
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Color</th>
                    <th>Price</th>
                    <th>Description</th>
                    <th>Stock</th>
                    <th>Category</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="inventoryBody">

<?php
$inventory   = $_SESSION['inventory'] ?? [];
$searchQuery = trim($_GET['search'] ?? '');
uasort($inventory, fn($a,$b) => $a->inv_id <=> $b->inv_id);
if ($searchQuery !== '') {
    $needle = strtolower($searchQuery);
    $inventory = array_filter($inventory, function($item) use ($needle) {
        return strpos(strtolower((string)($item->name??'')), $needle) !== false
            || strpos(strtolower((string)($item->color??'')), $needle) !== false
            || strpos(strtolower((string)($item->category??'')), $needle) !== false
            || strpos(strtolower((string)($item->description??'')), $needle) !== false
            || strpos(strtolower((string)($item->size??'')), $needle) !== false;
    });
}
?>

<?php foreach($inventory as $item): ?>
<tr class="product-row"
    data-name="<?=        htmlspecialchars(strtolower((string)($item->name        ?? ''))) ?>"
    data-color="<?=       htmlspecialchars(strtolower((string)($item->color       ?? ''))) ?>"
    data-category="<?=    htmlspecialchars(strtolower((string)($item->category    ?? ''))) ?>"
    data-description="<?= htmlspecialchars(strtolower((string)($item->description ?? ''))) ?>"
    data-size="<?=        htmlspecialchars(strtolower((string)($item->size        ?? ''))) ?>">

    <td><?= $item->inv_id ?></td>
    <td>
        <img src="<?= htmlspecialchars($item->image) ?>" width="50" style="border-radius:8px;"
             onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=50&fit=crop'">
    </td>
    <td class="s-name"><?=        htmlspecialchars($item->name) ?></td>
    <td class="s-size"><?=        htmlspecialchars($item->size) ?></td>
    <td class="s-color"><?=       htmlspecialchars($item->color) ?></td>
    <td>₱<?= number_format((float)$item->price, 2) ?></td>
    <td class="s-desc" style="font-size:.82rem;max-width:180px;"><?= htmlspecialchars($item->description) ?></td>
    <td>
        <?php $s=(int)$item->stock; ?>
        <span class="badge <?= $s===0?'bg-danger':($s<=5?'bg-warning text-dark':'bg-success') ?>">
            <?= $s===0?'Out of Stock':($s<=5?'Low: '.$s:$s) ?>
        </span>
    </td>
    <td class="s-category"><?= htmlspecialchars($item->category) ?></td>
    <td>
        <div class="d-flex gap-1 justify-content-center flex-wrap">
            <button class="btn btn-edit btn-sm"
                onclick="editProduct('<?= $item->inv_id ?>','<?= addslashes($item->name) ?>','<?= addslashes($item->size) ?>','<?= addslashes($item->color) ?>','<?= $item->price ?>','<?= addslashes($item->description) ?>','<?= $item->stock ?>','<?= addslashes($item->category) ?>','<?= addslashes($item->image) ?>')">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button class="btn btn-outline-success btn-sm"
                onclick="let qty=prompt('Units to add?');if(qty)window.location.href='admin_action.php?restock_id=<?= $item->inv_id ?>&amount='+qty;">
                <i class="fas fa-plus-circle"></i> Restock
            </button>
            <a href="admin_action.php?delete=<?= $item->inv_id ?>"
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this product?')">
               <i class="fas fa-trash"></i> Delete
            </a>
        </div>
    </td>
</tr>
<?php endforeach; ?>

<tr id="noResults" style="display:none;">
    <td colspan="10" style="color:#bbb;padding:2rem 0;font-size:.9rem;">
        <i class="fas fa-search me-2 opacity-50"></i>
        No products found for "<span id="noResultsQuery" style="color:#888;"></span>"
    </td>
</tr>

            </tbody>
        </table>
    </div>

</div><!-- /card -->
</div><!-- /section-inventory -->

<!-- ── SECTION: Add Product ── -->
<div id="section-addproduct" style="display:none;">
<div class="card p-4 mt-3" style="max-width:680px;margin:0 auto;">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-plus-circle" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Add / Edit Product</h5>
              </div>
    </div>
    <form method="POST" action="admin_action.php" id="formCard">
        <input type="hidden" name="id" id="pid">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Product Name</label>
                <input class="form-control" name="name" id="pname" placeholder="e.g. Nordic Lounge Chair" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Size</label>
                <input class="form-control" name="size" id="psize" placeholder="e.g. L120 x W80 x H90 cm">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Color(s)</label>
                <input class="form-control" name="color" id="pcolor" placeholder="e.g. Beige, Walnut">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Price (₱)</label>
                <input class="form-control" name="price" id="pprice" placeholder="e.g. 12500">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold small">Description</label>
                <textarea class="form-control" name="description" id="pdesc" rows="3" placeholder="Short description of the product…"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Stock</label>
                <input class="form-control" name="stock" id="pstock" type="number" min="0" placeholder="0">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Category</label>
                <select class="form-select" name="category" id="pcat">
                    <option value="Sofa">Sofa</option>
                    <option value="Chair">Chair</option>
                    <option value="Set">Set</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small">Image Path</label>
                <input class="form-control" name="image" id="pimage" placeholder="pci/image.png">
            </div>  
          
            <div class="col-12 d-flex gap-2 mt-2">
<br><br>
                <button type="submit" class="btn btn-zythera flex-fill">
                    <i class="fas fa-save me-2"></i>Save Product
                </button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                    onclick="resetForm(); showSection('inventory');">Cancel</button>
            </div>
        </div>
    </form>
</div>
</div><!-- /section-addproduct -->

<!-- ── SECTION: Analytics ── -->
<div id="section-analytics" style="display:none;">
<div class="row g-4 mt-2">
    <?php
    $inv = $_SESSION['inventory'] ?? [];
    $totalProducts = count($inv);
    $outOfStock    = count(array_filter($inv, fn($i)=>((int)($i->stock??0))===0));
    $lowStock      = count(array_filter($inv, fn($i)=>((int)($i->stock??0))>0 && ((int)($i->stock??0))<=5));

    // Load users and orders fresh from DB
    $dbUsers       = loadUsers();
    $totalUsers    = count($dbUsers);
    $dbAllOrders   = loadOrders();
    $totalOrders   = count($dbAllOrders);
    $revenue       = 0;
    foreach ($dbAllOrders as $o) {
        if (isset($o->total) && (float)$o->total > 0) {
            $revenue += (float)$o->total;
        } else {
            foreach ($o->items as $oi)
                $revenue += (float)($oi->price ?? 0) * (int)($oi->qty ?? 1);
        }
    }
    $cards = [
        ['icon'=>'fa-boxes','label'=>'Total Products','value'=>$totalProducts,'color'=>'#2d5a2d'],
        ['icon'=>'fa-exclamation-triangle','label'=>'Out of Stock','value'=>$outOfStock,'color'=>'#2d5a2d'],
        ['icon'=>'fa-battery-quarter','label'=>'Low Stock','value'=>$lowStock,'color'=>'#2d5a2d'],
        ['icon'=>'fa-users','label'=>'Registered Users','value'=>$totalUsers,'color'=>'#2d5a2d'],
        ['icon'=>'fa-receipt','label'=>'Total Orders','value'=>$totalOrders,'color'=>'#2d5a2d'],
        ['icon'=>'fa-peso-sign','label'=>'Total Revenue','value'=>'₱'.number_format($revenue),'color'=>'#2d5a2d'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-sm-6 col-lg-4">
        <div class="card p-4" style="border-left:5px solid <?= $c['color'] ?>;">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px;height:48px;border-radius:14px;background:<?= $c['color'] ?>18;display:flex;align-items:center;justify-content:center;">
                    <i class="fas <?= $c['icon'] ?>" style="color:<?= $c['color'] ?>;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size:.75rem;color:var(--deep-green);text-transform:uppercase;letter-spacing:1px;"><?= $c['label'] ?></div>
                    <div style="font-size:1.6rem;font-weight:700;color:<?= $c['color'] ?>;line-height:1.2;"><?= $c['value'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div><!-- /section-analytics -->

<!-- ── SECTION: Order History ── -->
<div id="section-orders" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-receipt" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Order History</h5>
        </div>
    </div>

    <?php
    // Always load orders fresh from DB for admin
    $allOrders2    = loadOrders();
    $totalOrdCount = 0;
    $grandTotal2   = 0;
    foreach ($allOrders2 as $order):
        $oEmail = $order->email ?? '';
        $totalOrdCount++;
        $orderStoredTotal = (float)($order->total ?? 0);
        $orderStatus = $order->status ?? 'Pending';
        $orderId     = $order->order_id ?? '';
        $orderItems  = $order->items ?? [];
        $orderShipping = (float)($order->shipping ?? 0);
        $orderDate   = $order->date ?? '';
        $orderPayMethod = $order->pay_method ?? '';
        // Use flat columns directly from schema
        $shippingInfo = [
            'full_name' => $order->full_name ?? '',
            'phone'     => $order->phone     ?? '',
            'address'   => $order->address   ?? '',
            'city'      => $order->city       ?? '',
            'province'  => $order->province   ?? '',
            'zip'       => $order->zip        ?? '',
        ];
        $shippingAddr = implode(', ', array_filter([
            $shippingInfo['full_name'] ?? '',
            $shippingInfo['address']   ?? '',
            $shippingInfo['city']      ?? '',
            $shippingInfo['province']  ?? '',
        ]));
        $statusColors = [
            'Pending'    => ['bg'=>'#fff7ed','color'=>'#c2410c','border'=>'#fed7aa'],
            'Processing' => ['bg'=>'#eff6ff','color'=>'#1d4ed8','border'=>'#bfdbfe'],
            'Shipped'    => ['bg'=>'#f0f9ff','color'=>'#0369a1','border'=>'#bae6fd'],
            'Delivered'  => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0'],
            'Cancelled'  => ['bg'=>'#fef2f2','color'=>'#b91c1c','border'=>'#fecaca'],
        ];
        $sc = $statusColors[$orderStatus] ?? $statusColors['Pending'];
    ?>
    <div class="order-card mb-3" id="order-card-<?= htmlspecialchars($orderId) ?>">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <?php if ($orderId !== ''): ?>
            <span style="background:#f0f7f0;color:#2d5a2d;border-radius:50px;padding:2px 10px;font-size:.72rem;font-weight:700;">
                #<?= htmlspecialchars($orderId) ?>
            </span>
            <?php endif; ?>
            <span class="order-user-tag"><i class="fas fa-user me-1"></i><?= htmlspecialchars($oEmail) ?></span>

            <?php if ($orderPayMethod !== ''): ?>
            <span style="background:#f5f2f0;color:#666;border-radius:50px;padding:2px 10px;font-size:.72rem;">
                <i class="fas fa-credit-card me-1"></i><?= htmlspecialchars($orderPayMethod) ?>
            </span>
            <?php endif; ?>

            <span id="status-badge-<?= htmlspecialchars($orderId) ?>"
                style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;
                border-radius:50px;padding:2px 10px;font-size:.72rem;font-weight:700;">
                <?= htmlspecialchars($orderStatus) ?>
            </span>
            <div class="ms-auto d-flex align-items-center gap-2">
                <small class="text-muted"><i class="fas fa-calendar me-1"></i><?= htmlspecialchars($orderDate) ?></small>
                <button onclick="toggleOrderDetail('<?= htmlspecialchars($orderId, ENT_QUOTES) ?>')"
                  style="background:#f0f7f0;color:#2d5a2d;border:1px solid #d4e4d4;border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:600;cursor:pointer;white-space:nowrap;">
                  <i class="fas fa-expand-alt me-1"></i>Details
                </button>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <select style="min-width:220px;padding:8px 12px;font-size:.82rem;border-radius:10px;border:2px solid #d4e4d4;background:#f9f9f6;color:#2d5a2d;font-family:inherit;cursor:pointer;outline:none;"
                id="status-sel-<?= htmlspecialchars($orderId) ?>"
                onchange="updateOrderStatus('<?= htmlspecialchars($oEmail, ENT_QUOTES) ?>','<?= htmlspecialchars($orderId, ENT_QUOTES) ?>',this.value)">
                <option value=""> Update Status </option>
                <option value="Pending">Pending</option>
                <option value="Processing">Processing</option>
                <option value="Shipped">Shipped</option>
                <option value="Delivered">Delivered</option>
                <option value="Cancelled">Cancelled</option>
            </select>

        </div>
        <?php
        $orderSubtotal2 = 0;
        foreach ($orderItems as $oi):
            $oiPrice = (float)($oi->price ?? 0);
            $oiQty   = (int)($oi->qty ?? 1);
            $oiName  = $oi->product_name ?? 'Item';
            $oiImage = trim((string)($oi->image ?? ''));
            $oiLine  = $oiPrice * $oiQty;
            $orderSubtotal2 += $oiLine;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:.85rem;padding:6px 0;border-bottom:1px dashed #f0f0eb;">
            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                <?php if ($oiImage): ?>
                <img src="<?= htmlspecialchars($oiImage) ?>" alt="<?= htmlspecialchars($oiName) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:12px;border:1px solid #e5e5e5;background:#fff;">
                <?php endif; ?>
                <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($oiName) ?> <b style="color:#7aab7a;">×<?= $oiQty ?></b>
                </span>
            </div>
            <?php if ($oiPrice > 0): ?>
            <span style="color:#2d5a2d;font-weight:600;">₱<?= number_format($oiLine) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($orderShipping > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;padding:4px 0;color:#888;">
            <span><i class="fas fa-truck me-1"></i>Shipping Fee</span>
            <span>₱<?= number_format($orderShipping) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($orderStoredTotal > 0): ?>
        <div class="text-end fw-bold mt-2" style="color:#2d5a2d;">
            Order Total: ₱<?= number_format($orderStoredTotal) ?>
        </div>
        <?php $grandTotal2 += $orderStoredTotal; ?>
        <?php elseif ($orderSubtotal2 > 0): ?>
        <div class="text-end fw-bold mt-2" style="color:#2d5a2d;">
            Order Total: ₱<?= number_format($orderSubtotal2) ?>
        </div>
        <?php $grandTotal2 += $orderSubtotal2; ?>
        <?php endif; ?>

        <!-- ── Collapsible Order Detail Panel ─────────── -->
        <div id="detail-<?= htmlspecialchars($orderId) ?>" style="display:none;margin-top:14px;border-top:2px dashed #d4e4d4;padding-top:14px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Recipient</div>
                <div style="font-weight:600;font-size:.84rem;color:#1a2e1a;"><?= htmlspecialchars($shippingInfo['full_name'] ?? '—') ?></div>
                <div style="font-size:.78rem;color:#666;"><?= htmlspecialchars($shippingInfo['phone'] ?? '') ?></div>
              </div>
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Payment</div>
                <div style="font-weight:600;font-size:.84rem;color:#1a2e1a;"><?= htmlspecialchars($orderPayMethod ?: '—') ?></div>
              </div>
              <div style="background:#f9f9f6;border-radius:10px;padding:10px;grid-column:1/-1;">
                <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:4px;">Delivery Address</div>
                <div style="font-size:.82rem;color:#444;"><?= htmlspecialchars(implode(', ', array_filter([
                    $shippingInfo['address']  ?? '',
                    $shippingInfo['city']     ?? '',
                    $shippingInfo['province'] ?? '',
                    $shippingInfo['zip']      ?? '',
                ]))) ?: '—' ?></div>
              </div>
            </div>
        </div>

    </div>
    <?php endforeach;
    if ($totalOrdCount === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-receipt fa-3x mb-3 opacity-25"></i>
        <p>No orders placed yet.</p>
    </div>
    <?php endif; ?>

    <?php if ($grandTotal2 > 0): ?>
    <div style="background:linear-gradient(135deg,#1a2e1a,#2d5a2d);color:#fff;border-radius:16px;padding:20px 24px;margin-top:8px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="font-size:.72rem;opacity:.7;letter-spacing:1.5px;text-transform:uppercase;color:#fff;">Grand Total Revenue</div>
            <div style="font-size:1.6rem;font-weight:800;font-family:'Playfair Display',serif;">₱<?= number_format($grandTotal2) ?></div>
        </div>
        <div style="text-align:right;opacity:.8;">
            <div style="font-size:1.3rem;font-weight:700;"><?= $totalOrdCount ?></div>
            <div style="font-size:.72rem;">total order(s)</div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-orders -->


<!-- ── SECTION: User Summary ── -->
<div id="section-users" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-users" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">User Summary</h5>
        
        </div>
    </div>

    <?php
    $allUsers2 = loadUsers();
    if (empty($allUsers2)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
        <p>No users registered yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
    <?php
    // Pre-load orders + carts for user stats
    $dbAllOrders3 = loadOrders();
    $dbAllCarts3  = loadCarts();

    // Index orders by email
    $ordersByUser = [];
    foreach ($dbAllOrders3 as $o) {
        $ordersByUser[$o->email][] = $o;
    }
    // Index carts by email
    $cartByUser = [];
    foreach ($dbAllCarts3 as $c) {
        $cartByUser[$c['email']][] = $c;
    }

    foreach ($allUsers2 as $uObj):
        $uEmail = $uObj->email ?? '';
        $uData  = ['name' => $uObj->name ?? '', 'role' => $uObj->role ?? 'user'];

        $uOrders2    = count($ordersByUser[$uEmail] ?? []);
        $uCartItems  = $cartByUser[$uEmail] ?? [];
        $uCartCount2 = array_sum(array_column($uCartItems, 'qty'));
        $uSpend      = 0;
        foreach ($ordersByUser[$uEmail] ?? [] as $uo)
            $uSpend += (float)($uo->total ?? 0);
        $isAdmin = ($uData['role'] === 'admin');
    ?>
    <div class="col-md-6">
        <div class="order-card h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:46px;height:46px;border-radius:50%;background:<?= $isAdmin?'#1a2e1a':'#2d5a2d' ?>;color:#fff;
                    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1rem;flex-shrink:0;">
                    <?= strtoupper(substr($uData['name'] ?? '?', 0, 1)) ?>
                </div>
                <div style="flex:1;min-width:0;">
                    <div class="fw-bold text-truncate" style="color:#1a2e1a;"><?= htmlspecialchars($uData['name'] ?? '') ?></div>
                    <div style="font-size:.75rem;color:var(--deep-green);opacity:.65;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;" class="user-email-tag" data-email="<?= htmlspecialchars($uEmail) ?>"><?= htmlspecialchars($uEmail) ?></div>
                </div>
                <span style="background:<?= $isAdmin?'#fee2e2':'#d4e4d4' ?>;color:<?= $isAdmin?'#b91c1c':'#2d5a2d' ?>;
                    border-radius:20px;font-size:.68rem;font-weight:700;padding:3px 10px;letter-spacing:1px;white-space:nowrap;">
                    <?= $isAdmin ? 'ADMIN' : 'USER' ?>
                </span>
            </div>
            <div class="d-flex gap-2 flex-wrap mb-3">
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:1.1rem;font-weight:800;color:#2d5a2d;"><?= $uOrders2 ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">Orders</div>
                </div>
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:1.1rem;font-weight:800;color:#2d5a2d;"><?= $uCartCount2 ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">In Cart</div>
                </div>
                <div style="flex:1;background:#f9f9f6;border-radius:10px;padding:10px;text-align:center;min-width:70px;">
                    <div style="font-size:.9rem;font-weight:800;color:#2d5a2d;">₱<?= number_format($uSpend) ?></div>
                    <div style="font-size:.68rem;color:var(--deep-green);opacity:.65;">Spent</div>
                </div>
            </div>
            <?php
            $currentAdmin = $_SESSION['logged_in_user'] ?? '';
            if ($uEmail !== $currentAdmin): ?>
            <button class="btn btn-danger btn-sm w-100 rounded-pill fw-semibold"
                onclick="deleteUser('<?= htmlspecialchars($uEmail, ENT_QUOTES) ?>', '<?= htmlspecialchars($uData['name'] ?? '', ENT_QUOTES) ?>')">
                <i class="fas fa-user-times me-1"></i> Delete User
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-users -->

<!-- ── SECTION: Messages ── -->
<div id="section-messages" style="display:none;">
<div class="card p-4 mt-3">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width:44px;height:44px;background:var(--sage-light);border-radius:12px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-envelope" style="color:var(--deep-green);font-size:1.1rem;"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-0" style="color:var(--deep-green);">Customer Messages</h5>
        </div>
    </div>
    <?php
    $msgs = [];
    try {
        $db2 = getDBConnection();
        $msgStmt = $db2->query("SELECT * FROM messages ORDER BY created_at DESC");
        $msgs = $msgStmt->fetchAll();
    } catch (Exception $e) { /* table may not exist yet */ }
    if (empty($msgs)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-envelope-open fa-3x mb-3 opacity-25"></i>
        <p>No messages received yet.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
    <table class="table align-middle" style="font-size:.88rem;">
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Subject</th><th>Message</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($msgs as $m): ?>
        <tr>
            <td class="fw-semibold"><?= htmlspecialchars($m->full_name ?? '') ?></td>
            <td><?= htmlspecialchars($m->email ?? '') ?></td>
            <td><?= htmlspecialchars($m->subject ?? '') ?></td>
            <td style="max-width:260px;white-space:pre-wrap;word-break:break-word;"><?= htmlspecialchars($m->message ?? '') ?></td>
            <td style="white-space:nowrap;color:#999;"><?= htmlspecialchars($m->created_at ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
</div><!-- /section-messages -->

</div><!-- /container -->
</div><!-- /main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Date / Time ──────────────────────────────────────────────
function updateDateTime() {
    const now = new Date();
    const d = now.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
    const t = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    document.getElementById('datetime').textContent = d + ', ' + t;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ── Section switching (all sidebar nav items) ─────────────────
const sectionTitles = {
    inventory:  'Product Inventory',
    addproduct: 'Add Product',
    analytics:  'Analytics Dashboard',
    orders:     'Order History',
    users:      'User Summary',
    messages:   'Customer Messages',
};

function showSection(name) {
    ['inventory','addproduct','analytics','orders','users','messages'].forEach(s => {
        document.getElementById('section-' + s).style.display = s === name ? '' : 'none';
    });
    document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('active'));
    const nav = document.getElementById('nav-' + name);
    if (nav) nav.classList.add('active');
    const titleEl = document.getElementById('sectionTitle');
    if (titleEl) titleEl.textContent = sectionTitles[name] || '';
    if (name === 'addproduct') resetForm();
}

// ── Edit product: switch to Add Product section then fill form ─
function editProduct(id, name, size, color, price, desc, stock, category, image) {
    showSection('addproduct');
    document.getElementById('pid').value    = id;
    document.getElementById('pname').value  = name;
    document.getElementById('psize').value  = size;
    document.getElementById('pcolor').value = color;
    document.getElementById('pprice').value = price;
    document.getElementById('pdesc').value  = desc;
    document.getElementById('pstock').value = stock;
    document.getElementById('pcat').value   = category;
    document.getElementById('pimage').value = image;
    document.getElementById('sectionTitle').textContent = 'Edit Product';
    window.scrollTo({top:0, behavior:'smooth'});
}

// ── Reset form to blank (new product) ────────────────────────
function resetForm() {
    ['pid','pname','psize','pcolor','pprice','pdesc','pstock','pimage'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const cat = document.getElementById('pcat');
    if (cat) cat.value = 'Sofa';
}

// ── Search ────────────────────────────────────────────────────
function searchProducts(query) {
    const q         = query.trim().toLowerCase();
    const rows      = document.querySelectorAll('.product-row');
    const clearBtn  = document.getElementById('clearBtn');
    const noResults = document.getElementById('noResults');
    const countEl   = document.getElementById('result-count');

    clearBtn.style.display = q ? 'block' : 'none';
    let visible = 0;

    rows.forEach(row => {
        const haystack = [row.dataset.name, row.dataset.color, row.dataset.category,
                          row.dataset.description, row.dataset.size].join(' ');
        const match = q === '' || haystack.indexOf(q) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) { visible++; q ? highlightRow(row, q) : clearHighlight(row); }
        else clearHighlight(row);
    });

    noResults.style.display = (visible === 0 && q) ? '' : 'none';
    document.getElementById('noResultsQuery').textContent = query;
    const total = rows.length;
    countEl.textContent = q ? `(${visible} of ${total} shown)` : `(${total} total)`;
}

function highlightRow(row, q) {
    const targets = row.querySelectorAll('.s-name,.s-size,.s-color,.s-desc,.s-category');
    const regex   = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
    targets.forEach(cell => {
        if (!cell.dataset.original) cell.dataset.original = cell.textContent;
        cell.innerHTML = cell.dataset.original.replace(regex, '<mark>$1</mark>');
    });
}

function clearHighlight(row) {
    row.querySelectorAll('[data-original]').forEach(cell => {
        cell.textContent = cell.dataset.original;
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchProducts('');
}

// ── Update Order Status ───────────────────────────────────────
function updateOrderStatus(email, orderId, newStatus) {
    if (!newStatus) return;
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus))
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            showToast('Order #' + orderId + ' → ' + newStatus);
        } else {
            alert(data.message || 'Could not update status.');
        }
    })
    .catch(() => alert('Request failed.'));
}

// ── Delete User ───────────────────────────────────────────────
function deleteUser(email, name) {
    if (!confirm('Delete user "' + name + '" (' + email + ')?\nThis will also remove their cart and orders.')) return;
    fetch('admin_action.php?delete_user=' + encodeURIComponent(email))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('User ' + name + ' deleted.');
                // Remove the card from DOM
                document.querySelectorAll('.user-email-tag').forEach(el => {
                    if (el.dataset.email === email) {
                        el.closest('.col-md-6').remove();
                    }
                });
                // Reload section after short delay
                setTimeout(() => window.location.reload(), 800);
            } else {
                alert(data.message || 'Could not delete user.');
            }
        })
        .catch(() => alert('Request failed.'));
}

function showToast(msg, isError = false) {
    let t = document.getElementById('admin-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'admin-toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;color:#fff;padding:14px 22px;border-radius:12px;font-size:.86rem;z-index:9999;box-shadow:0 6px 24px rgba(0,0,0,.2);transition:.3s;';
        document.body.appendChild(t);
    }
    t.style.background = isError ? '#dc2626' : '#2d5a2d';
    t.textContent = msg;
    t.style.opacity = '1';
    setTimeout(() => t.style.opacity = '0', 3500);
}

// ── Toggle order detail panel ─────────────────────────────────
function toggleOrderDetail(orderId) {
    const panel = document.getElementById('detail-' + orderId);
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' ? '' : 'none';
}

// ── Quick status button (in detail panel) ─────────────────────
function quickStatus(email, orderId, newStatus) {
    fetch('admin_action.php?update_status=1&email=' + encodeURIComponent(email)
        + '&order_id=' + encodeURIComponent(orderId)
        + '&status='   + encodeURIComponent(newStatus))
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('status-badge-' + orderId);
            const statusColors = {
                Pending:    { bg:'#fff7ed', color:'#c2410c', border:'#fed7aa' },
                Processing: { bg:'#eff6ff', color:'#1d4ed8', border:'#bfdbfe' },
                Shipped:    { bg:'#f0f9ff', color:'#0369a1', border:'#bae6fd' },
                Delivered:  { bg:'#f0fdf4', color:'#15803d', border:'#bbf7d0' },
                Cancelled:  { bg:'#fef2f2', color:'#b91c1c', border:'#fecaca' },
            };
            const sc = statusColors[newStatus] || statusColors['Pending'];
            if (badge) {
                badge.textContent = newStatus;
                badge.style.background = sc.bg;
                badge.style.color      = sc.color;
                badge.style.border     = '1px solid ' + sc.border;
            }
            const sel = document.getElementById('status-sel-' + orderId);
            if (sel) sel.value = '';
            // Update pending badge in sidebar
            updatePendingBadge();
            showToast('✓ Order #' + orderId + ' → ' + newStatus);
        } else {
            showToast(data.message || 'Could not update status.', true);
        }
    }).catch(() => showToast('Request failed.', true));
}

// ── Refresh pending count on sidebar ─────────────────────────
function updatePendingBadge() {
    fetch('get_pending.php', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(d => {
        let badge = document.getElementById('pending-badge');
        if (d.count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'pending-badge';
                badge.style.cssText = 'background:#dc2626;color:#fff;border-radius:50px;font-size:.6rem;font-weight:700;padding:2px 7px;';
                document.getElementById('nav-orders').appendChild(badge);
            }
            badge.textContent = d.count;
        } else if (badge) {
            badge.remove();
        }
    }).catch(() => {});
}

window.addEventListener('DOMContentLoaded', () => {
    const total = document.querySelectorAll('.product-row').length;
    const countEl = document.getElementById('result-count');
    if (countEl) countEl.textContent = `(${total} total)`;
});
</script>

</body>
</html>