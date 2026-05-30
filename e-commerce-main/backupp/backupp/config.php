<?php

date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('INVENTORY_FILE', __DIR__ . '/data/inventory.json');

function loadInventory(): array {
    if (!file_exists(INVENTORY_FILE)) return [];
    $json = file_get_contents(INVENTORY_FILE);
    if ($json === false || trim($json) === '') return [];
    $arr = json_decode($json, false);
    return is_array($arr) ? $arr : [];
}

function saveInventory(array $inventory): void {
    $dir = dirname(INVENTORY_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Always store as a flat list of objects (strip any int keys)
    $list = array_values($inventory);

    $result = file_put_contents(
        INVENTORY_FILE,
        json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($result === false) {
        error_log('[] saveInventory() FAILED — check write permissions on ' . INVENTORY_FILE);
    }

    // Keep session in sync immediately after every save
    $_SESSION['inventory'] = [];
    foreach ($list as $item) {
        $obj = is_array($item) ? (object)$item : $item;
        $_SESSION['inventory'][(int)$obj->id] = $obj;
    }
}

// Bootstrap session arrays only once
if (!isset($_SESSION['users']))   $_SESSION['users']   = [];
if (!isset($_SESSION['cart']))    $_SESSION['cart']    = [];
if (!isset($_SESSION['orders']))  $_SESSION['orders']  = [];

// Always derive last_id from the JSON file — survives logout/login.
// Never rely solely on session for the max ID counter.
$_zExisting = loadInventory();
$_zMaxId    = 0;
foreach ($_zExisting as $_zItem) {
    if (isset($_zItem->id) && (int)$_zItem->id > $_zMaxId) {
        $_zMaxId = (int)$_zItem->id;
    }
}
// Only move the counter forward, never backwards
if (!isset($_SESSION['last_id']) || $_zMaxId > (int)$_SESSION['last_id']) {
    $_SESSION['last_id'] = $_zMaxId;
}

// Always reload inventory FROM FILE so every page request reflects
// the current saved state — changes by admin are instantly visible
// to all users, and nothing is lost after logout.
$_SESSION['inventory'] = [];
foreach ($_zExisting as $_zItem) {
    $_SESSION['inventory'][(int)$_zItem->id] = $_zItem;
}
unset($_zExisting, $_zMaxId, $_zItem);

define('STORE_NAME', 'ZAFIRAH');
define('TAX_RATE',   0.12);

// ---- Helpers ----
function sanitize(string $v): string { return trim(htmlspecialchars($v)); }
function isBlank(string $v): bool    { return empty(trim($v)); }
function formatPrice(float $p): string { return '₱' . number_format($p, 2); }

function getStockLabel(int $s): string {
    if ($s === 0) return 'Out of Stock';
    if ($s <= 5)  return 'Low Stock';
    return 'In Stock';
}

function getStockBadge(int $s): string {
    switch (getStockLabel($s)) {
        case 'Out of Stock': return 'bg-danger';
        case 'Low Stock':    return 'bg-warning text-dark';
        default:             return 'bg-success';
    }
}

function nowFormatted(): string { return date('M d, Y h:i A'); }

class Product {

    private int    $id;
    public string $name;
    public string $size;
    public string $color;
    public string $description;
    public string $category;
    private string $image;

    protected float $price;

    private int $stock;

    public function __construct(
        int    $id,
        string $name,
        string $size,
        string $color,
        float  $price,
        string $description,
        int    $stock,
        string $category,
        string $image
    ) {
        $this->id          = $id;
        $this->name        = $name;
        $this->size        = $size;
        $this->color       = $color;
        $this->price       = $price;
        $this->description = $description;
        $this->stock       = $stock;
        $this->category    = $category;
        $this->image       = $image;
    }

     public function __get(string $prop): mixed {
        if ($prop === 'price') return $this->price;
        if ($prop === 'stock') return $this->stock;
        return null;
    }

      public function __set(string $prop, mixed $value): void {
        if ($prop === 'price') {
            $clean = (float) str_replace([',', '₱', ' '], '', (string)$value);
            $this->price = max(0, $clean);
        }
        if ($prop === 'stock') {
            $this->stock = max(0, (int)$value);
        }
    }

  public function __toString(): string {
        return "[Product #{$this->id}] {$this->name} — ₱" . number_format($this->price, 2)
             . " | Stock: {$this->stock} | Category: {$this->category}";
    }

     public function customized_toString(): string {
        $stockLabel = getStockLabel($this->stock);
        return "ZAFIRAH Product Card\n"
             . "-------------------\n"
             . "ID       : {$this->id}\n"
             . "Name     : {$this->name}\n"
             . "Size     : {$this->size}\n"
             . "Color    : {$this->color}\n"
             . "Price    : ₱" . number_format($this->price, 2) . "\n"
             . "Stock    : {$this->stock} ({$stockLabel})\n"
             . "Category : {$this->category}\n"
             . "Desc     : {$this->description}";
    }

    public function reststock(int $amount): void {
        $this->stock += $amount;
    }

    public function getFormattedPrice(): string { return formatPrice($this->price); }
    public function isAvailable(): bool         { return $this->stock > 0; }

     public function toStdClass(): object {
        return (object)[
            'id'          => $this->id,
            'name'        => $this->name,
            'size'        => $this->size,
            'color'       => $this->color,
            'price'       => $this->price,
            'description' => $this->description,
            'stock'       => $this->stock,
            'category'    => $this->category,
            'image'       => $this->image,
        ];
    }

     public static function fromStdClass(object $obj): self {
        return new self(
            (int)($obj->id ?? 0),
            (string)($obj->name ?? ''),
            (string)($obj->size ?? ''),
            (string)($obj->color ?? ''),
            (float)($obj->price ?? 0),
            (string)($obj->description ?? ''),
            (int)($obj->stock ?? 0),
            (string)($obj->category ?? ''),
            (string)($obj->image ?? '')
        );
    }
}