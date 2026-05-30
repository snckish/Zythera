<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAFIRAH | ADMIN</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
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
            font-family: 'Inter', sans-serif;
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
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background-color: var(--white);
            border-color: var(--sage-dark);
            box-shadow: none;
        }

        .btn-zafirah {
            background-color: var(--deep-green);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 2rem;
            font-weight: 500;
            border: none;
            transition: 0.3s;
        }

        .btn-zafirah:hover {
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

        #datetime { font-size: 0.75rem; color: #666; display: block; }

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
            font-family: 'Inter', sans-serif;
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
            color: #aaa; font-size: .85rem;
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
            color: #bbb;
            font-size: .9rem;
            padding: 2rem 0;
        }

        #result-count {
            font-size: .78rem;
            color: #999;
            font-weight: 400;
            margin-left: 6px;
        }

        /* Sidebar Styles */
.offcanvas {
    background-color: var(--cream);
    border-left: 1px solid rgba(0,0,0,0.1);
}

.order-history-card {
    background: var(--white);
    border-radius: 15px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 5px solid var(--sage-dark);
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

.order-id { font-weight: 600; color: var(--deep-green); font-size: 0.9rem; }
.order-date { font-size: 0.75rem; color: #888; }
.order-total { font-weight: 700; color: var(--deep-green); }
    </style>
</head>
<body>

<nav class="navbar sticky-top">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a href="#" class="brand-admin">ZAFIRAH <span style="font-weight:400;font-style:italic;font-size:1.2rem;">Admin</span></a>
        
        <div class="d-flex align-items-center gap-3">
            <a href="website.php" class="btn btn-link text-decoration-none text-muted fw-bold">← View Store</a>
            <button class="btn btn-zafirah shadow-sm" onclick="toggleForm()">+ New Product</button>
            
            <div class="user-capsule shadow-sm">
                <div class="user-info-text">
                    <span class="user-name">Zafirah</span>
                    <span id="datetime"></span>
                </div>
                <div class="user-avatar">ZY</div>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
<div class="card p-3 mt-3">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h5 class="fw-bold mb-0">
            Product Inventory
            <span id="result-count"></span>
        </h5>


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

uasort($inventory, fn($a,$b) => $a->id <=> $b->id);


if ($searchQuery !== '') {
    $needle = strtolower($searchQuery);

    $inventory = array_filter($inventory, function($item) use ($needle) {
        $inName     = strpos(strtolower((string)($item->name        ?? '')), $needle) !== false;
        $inColor    = strpos(strtolower((string)($item->color       ?? '')), $needle) !== false;
        $inCategory = strpos(strtolower((string)($item->category    ?? '')), $needle) !== false;
        $inDesc     = strpos(strtolower((string)($item->description ?? '')), $needle) !== false;
        $inSize     = strpos(strtolower((string)($item->size        ?? '')), $needle) !== false;

        return $inName || $inColor || $inCategory || $inDesc || $inSize;
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

    <td><?= $item->id ?></td>

    <td>
        <img src="<?= htmlspecialchars($item->image) ?>" width="50" style="border-radius:8px;"
             onerror="this.src='https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=60&h=50&fit=crop'">
    </td>

    <td class="s-name"><?=        htmlspecialchars($item->name) ?></td>
    <td class="s-size"><?=        htmlspecialchars($item->size) ?></td>
    <td class="s-color"><?=       htmlspecialchars($item->color) ?></td>
    <td>₱<?= number_format((float)$item->price, 2) ?></td>
    <td class="s-desc" style="font-size:.82rem;max-width:180px;"><?= htmlspecialchars($item->description) ?></td>
    <td><?= $item->stock ?></td>
    <td class="s-category"><?=    htmlspecialchars($item->category) ?></td>

    <td class="d-flex gap-2 justify-content-center">
        <button class="btn btn-edit btn-sm"
            onclick="editProduct(
                '<?= $item->id ?>',
                '<?= addslashes($item->name) ?>',
                '<?= addslashes($item->size) ?>',
                '<?= addslashes($item->color) ?>',
                '<?= $item->price ?>',
                '<?= addslashes($item->description) ?>',
                '<?= $item->stock ?>',
                '<?= addslashes($item->category) ?>',
                '<?= addslashes($item->image) ?>'
            )">
            <i class="fas fa-edit"></i> Edit
        </button>

        <button class="btn btn-outline-success btn-sm" 
            onclick="let qty = prompt('How many units to add?'); if(qty) window.location.href='admin_action.php?restock_id=<?= $item->id ?>&amount='+qty;">
        <i class="fas fa-plus-circle"></i> Restock
    </button>


        <a href="admin_action.php?delete=<?= $item->id ?>"
           class="btn btn-danger btn-sm"
           onclick="return confirm('Are you sure you want to delete this?')">
           <i class="fas fa-trash"></i> Delete
        </a>
    </td>
</tr>
<?php endforeach; ?>

<!-- Shown by JS when nothing matches -->
<tr id="noResults" style="display:none;">
    <td colspan="10" style="color:#bbb;padding:2rem 0;font-size:.9rem;">
        <i class="fas fa-search me-2 opacity-50"></i>
        No products found for "<span id="noResultsQuery" style="color:#888;"></span>"
    </td>
</tr>

            </tbody>
        </table>
    </div>

<!-- ADD / EDIT FORM -->
<div class="card p-4 mt-4" id="formCard" style="display:none;">
    <h5>Add / Edit Product</h5>
    <form method="POST" action="admin_action.php">
        <input type="hidden"    name="id"          id="pid">
        <input class="form-control mb-2" name="name"        id="pname"  placeholder="Name">
        <input class="form-control mb-2" name="size"        id="psize"  placeholder="Size">
        <input class="form-control mb-2" name="color"       id="pcolor" placeholder="Color">
        <input class="form-control mb-2" name="price"       id="pprice" placeholder="Price">
        <input class="form-control mb-2" name="description" id="pdesc"  placeholder="Description">
        <input class="form-control mb-2" name="stock"       id="pstock" placeholder="Stock">
        <select class="form-select mb-2" name="category"    id="pcat">
            <option value="Sofa">Sofa</option>
            <option value="Chair">Chair</option>
            <option value="Set">Set</option>
        </select>
        <input class="form-control mb-3" name="image" id="pimage" placeholder="Image Path (pci/image.png)">
        <button class="btn btn-success w-100">Save Product</button>
    </form>
</div>

</div><!-- /card -->
</div><!-- /container -->

<script>
// ── Date / Time ──────────────────────────────────────────────
function updateDateTime() {
    const now = new Date();
    const d = now.toLocaleDateString('en-US', { month:'short', day:'2-digit', year:'numeric' });
    const t = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    document.getElementById('datetime').innerHTML = d + ', ' + t;
}
setInterval(updateDateTime, 1000);
updateDateTime();

// ── Toggle form ───────────────────────────────────────────────
function toggleForm() {
    const f = document.getElementById('formCard');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

// ── Fill edit form ────────────────────────────────────────────
function editProduct(id, name, size, color, price, desc, stock, category, image) {
    document.getElementById('formCard').style.display = 'block';
    document.getElementById('pid').value    = id;
    document.getElementById('pname').value  = name;
    document.getElementById('psize').value  = size;
    document.getElementById('pcolor').value = color;
    document.getElementById('pprice').value = price;
    document.getElementById('pdesc').value  = desc;
    document.getElementById('pstock').value = stock;
    document.getElementById('pcat').value   = category;
    document.getElementById('pimage').value = image;
    document.getElementById('formCard').scrollIntoView({ behavior:'smooth' });
}

function searchProducts(query) {
    const q         = query.trim().toLowerCase();
    const rows      = document.querySelectorAll('.product-row');
    const clearBtn  = document.getElementById('clearBtn');
    const noResults = document.getElementById('noResults');
    const countEl   = document.getElementById('result-count');

    clearBtn.style.display = q ? 'block' : 'none';

    let visible = 0;

    rows.forEach(row => {
         const haystack = [
            row.dataset.name,
            row.dataset.color,
            row.dataset.category,
            row.dataset.description,
            row.dataset.size
        ].join(' ');

         const match = q === '' || haystack.indexOf(q) !== -1;

        row.style.display = match ? '' : 'none';

        if (match) {
            visible++;
            q ? highlightRow(row, q) : clearHighlight(row);
        } else {
            clearHighlight(row);
        }
    });

    // No-results message
    noResults.style.display = (visible === 0 && q) ? '' : 'none';
    document.getElementById('noResultsQuery').textContent = query;

    // Counter badge
    const total = rows.length;
    countEl.textContent = q
        ? `(${visible} of ${total} shown)`
        : `(${total} total)`;
}

function highlightRow(row, q) {
    const targets = row.querySelectorAll('.s-name,.s-size,.s-color,.s-desc,.s-category');
    const regex   = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');

    targets.forEach(cell => {
        // Save original text once
        if (!cell.dataset.original) cell.dataset.original = cell.textContent;
        cell.innerHTML = cell.dataset.original.replace(regex, '<mark>$1</mark>');
    });
}

function clearHighlight(row) {
    row.querySelectorAll('[data-original]').forEach(cell => {
        cell.textContent = cell.dataset.original;
    });
}

// Clear search input
function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchProducts('');
}

// Show total count on page load
window.addEventListener('DOMContentLoaded', () => {
    const total = document.querySelectorAll('.product-row').length;
    document.getElementById('result-count').textContent = `(${total} total)`;
});
</script>

</body>
</html>