<?php
/**
 * Inventory List — Search, Filter, Paginate
 */
$pageTitle = 'Inventory';
require_once __DIR__ . '/../../includes/header.php';

$db = Database::getInstance();

// ─── Filters ─────────────────────────────────────────────────────
$search        = sanitize($_GET['search']        ?? '');
$filterCat     = sanitizeInt($_GET['category']   ?? 0);
$filterLoc     = sanitizeInt($_GET['location']   ?? 0);
$filterStatus  = sanitize($_GET['status']        ?? '');
$filterHazard  = sanitize($_GET['hazard']        ?? '');
$expiryFilter  = sanitize($_GET['expiry_filter'] ?? '');
$page          = max(1, sanitizeInt($_GET['page'] ?? 1));
$perPage       = ITEMS_PER_PAGE;
$offset        = ($page - 1) * $perPage;

// ─── CSV Export ───────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventory_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Item Code','Name','Category','Quantity','Unit','Location','Supplier','Lot #','Expiry Date','Cost','Status','Hazard Class']);
    $rows = $db->fetchAll(
        "SELECT ii.item_code,ii.name,c.name AS cat,ii.quantity,ii.unit,sl.name AS loc,
                s.name AS sup,ii.lot_number,ii.expiry_date,ii.cost,ii.status,ii.hazard_class
         FROM inventory_items ii
         JOIN categories c ON ii.category_id = c.id
         LEFT JOIN storage_locations sl ON ii.location_id = sl.id
         LEFT JOIN suppliers s ON ii.supplier_id = s.id
         ORDER BY ii.name"
    );
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

// ─── Build query ──────────────────────────────────────────────────
$where  = [];
$params = [];

if ($search) {
    $where[]  = "(ii.name LIKE ? OR ii.item_code LIKE ? OR ii.lot_number LIKE ? OR ii.catalog_number LIKE ?)";
    $like     = "%{$search}%";
    $params   = array_merge($params, [$like,$like,$like,$like]);
}
if ($filterCat)    { $where[] = "ii.category_id = ?";  $params[] = $filterCat; }
if ($filterLoc)    { $where[] = "ii.location_id = ?";  $params[] = $filterLoc; }
if ($filterStatus) { $where[] = "ii.status = ?";       $params[] = $filterStatus; }
if ($filterHazard) { $where[] = "ii.hazard_class = ?"; $params[] = $filterHazard; }
if ($expiryFilter === '30days')   { $where[] = "ii.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"; }
elseif ($expiryFilter === 'expired') { $where[] = "ii.expiry_date < CURDATE()"; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total    = (int) $db->fetchColumn("SELECT COUNT(*) FROM inventory_items ii $whereSQL", $params);
$pages    = (int) ceil($total / $perPage);

$items    = $db->fetchAll(
    "SELECT ii.*, c.name AS category_name, c.color_code,
            sl.name AS location_name, s.name AS supplier_name
     FROM inventory_items ii
     JOIN categories c ON ii.category_id = c.id
     LEFT JOIN storage_locations sl ON ii.location_id = sl.id
     LEFT JOIN suppliers s ON ii.supplier_id = s.id
     $whereSQL
     ORDER BY ii.updated_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Filter options
$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
$locations  = $db->fetchAll("SELECT id, name FROM storage_locations ORDER BY name");
$statuses   = ['Active','Low Stock','Expired','Depleted','Under Maintenance','Discontinued'];
$hazards    = ['None','Flammable','Corrosive','Toxic','Oxidizing','Explosive','Radioactive','Biological','Carcinogenic','Environmental'];
?>

<div class="page-header">
    <div>
        <h1>Inventory</h1>
        <p>Manage laboratory chemicals, reagents, equipment and samples</p>
    </div>
    <div class="page-header-actions">
        <?php if (isLabManager()): ?>
        <a href="<?= APP_URL ?>/views/inventory/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Item
        </a>
        <?php endif; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-secondary">
            <i class="fas fa-download"></i> CSV
        </a>
        <button class="btn btn-secondary" onclick="exportTableToPdf('inventoryTable','inventory')">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search items…" value="<?= htmlspecialchars($search) ?>"
            data-search-url="<?= APP_URL ?>/views/inventory/list.php"
            oninput="debounceSearch(this)">
    </div>
    <select onchange="applyFilter('category', this.value)" title="Filter by category">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filterCat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select onchange="applyFilter('location', this.value)" title="Filter by location">
        <option value="">All Locations</option>
        <?php foreach ($locations as $l): ?>
        <option value="<?= $l['id'] ?>" <?= $filterLoc == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select onchange="applyFilter('status', this.value)" title="Filter by status">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
    </select>
    <select onchange="applyFilter('hazard', this.value)" title="Filter by hazard">
        <option value="">All Hazards</option>
        <?php foreach ($hazards as $h): ?>
        <option value="<?= $h ?>" <?= $filterHazard === $h ? 'selected' : '' ?>><?= $h ?></option>
        <?php endforeach; ?>
    </select>
    <select onchange="applyFilter('expiry_filter', this.value)" title="Filter by expiry">
        <option value="">Any Expiry</option>
        <option value="30days" <?= $expiryFilter==='30days'?'selected':'' ?>>Expiring in 30 Days</option>
        <option value="expired" <?= $expiryFilter==='expired'?'selected':'' ?>>Already Expired</option>
    </select>
    <?php if ($search || $filterCat || $filterLoc || $filterStatus || $filterHazard || $expiryFilter): ?>
    <a href="<?= APP_URL ?>/views/inventory/list.php" class="btn btn-sm btn-secondary">
        <i class="fas fa-xmark"></i> Clear
    </a>
    <?php endif; ?>
    <span class="text-muted text-small" style="margin-left:auto"><?= number_format($total) ?> items</span>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="table-wrapper">
    <table id="inventoryTable">
        <thead>
            <tr>
                <th>Code</th><th>Item Name</th><th>Category</th><th>Quantity</th>
                <th>Location</th><th>Expiry</th><th>Status</th><th>Hazard</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
        <tr><td colspan="9">
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No items found</h3>
                <p>Try adjusting your filters or add new inventory items.</p>
                <?php if (isLabManager()): ?>
                <a href="<?= APP_URL ?>/views/inventory/add.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add First Item
                </a>
                <?php endif; ?>
            </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($items as $item):
            $pct = $item['min_quantity'] > 0 ? min(100, ($item['quantity'] / $item['min_quantity']) * 100) : 100;
            $isExpired  = $item['expiry_date'] && $item['expiry_date'] < date('Y-m-d');
            $isExpiring = $item['expiry_date'] && !$isExpired && strtotime($item['expiry_date']) <= strtotime('+30 days');
        ?>
        <tr>
            <td><span class="item-code"><?= htmlspecialchars($item['item_code']) ?></span></td>
            <td>
                <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($item['name']) ?></div>
                <?php if ($item['lot_number']): ?>
                <div class="text-small text-muted">Lot: <?= htmlspecialchars($item['lot_number']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge-secondary" style="border-color:<?= htmlspecialchars($item['color_code']) ?>;color:<?= htmlspecialchars($item['color_code']) ?>">
                    <?= htmlspecialchars($item['category_name']) ?>
                </span>
            </td>
            <td>
                <div style="font-weight:600"><?= number_format($item['quantity'], 2) ?> <span class="text-muted text-small"><?= $item['unit'] ?></span></div>
                <?php if ($item['min_quantity'] > 0): ?>
                <div class="stock-bar-wrap" style="margin-top:4px">
                    <div class="stock-bar">
                        <div class="stock-bar-fill <?= $pct <= 20 ? 'danger' : ($pct <= 50 ? 'warn' : '') ?>"
                            style="width:<?= min(100,$pct) ?>%" data-pct="<?= $pct ?>"></div>
                    </div>
                </div>
                <?php endif; ?>
            </td>
            <td class="text-small"><?= htmlspecialchars($item['location_name'] ?? '—') ?></td>
            <td>
                <?php if ($item['expiry_date']): ?>
                <span class="<?= $isExpired ? 'text-danger fw-bold' : ($isExpiring ? 'text-warning fw-bold' : '') ?> text-small">
                    <?= date('M j, Y', strtotime($item['expiry_date'])) ?>
                    <?= $isExpired ? '⚠' : ($isExpiring ? '⏰' : '') ?>
                </span>
                <?php else: ?>
                <span class="text-muted">N/A</span>
                <?php endif; ?>
            </td>
            <td><?= statusBadge($item['status']) ?></td>
            <td><?= hazardBadge($item['hazard_class']) ?></td>
            <td>
                <div class="table-actions">
                    <a href="<?= APP_URL ?>/views/inventory/view.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <?php if (isLabManager()): ?>
                    <a href="<?= APP_URL ?>/views/inventory/edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Edit">
                        <i class="fas fa-pen"></i>
                    </a>
                    <button class="btn btn-sm btn-secondary btn-icon" title="Adjust Stock"
                        onclick="showAdjustModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['quantity'] ?>)">
                        <i class="fas fa-sliders"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-secondary btn-icon" title="QR Code"
                        data-qr data-qr-code="<?= $item['item_code'] ?>" data-qr-name="<?= htmlspecialchars($item['name']) ?>">
                        <i class="fas fa-qrcode"></i>
                    </button>
                    <?php if (isAdmin()): ?>
                    <a href="<?= APP_URL ?>/controllers/InventoryController.php?action=delete&id=<?= $item['id'] ?>&csrf=<?= getCsrfToken() ?>"
                        class="btn btn-sm btn-danger btn-icon"
                        data-confirm="Delete '<?= htmlspecialchars(addslashes($item['name'])) ?>'? This cannot be undone."
                        title="Delete">
                        <i class="fas fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1])) ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php for ($i = max(1,$page-2); $i <= min($pages,$page+2); $i++): ?>
            <<?= $i === $page ? 'span class="current"' : 'a href="?'.http_build_query(array_merge($_GET,['page'=>$i])).'"' ?>><?= $i ?></<?= $i === $page ? 'span' : 'a' ?>>
            <?php endfor; ?>

            <?php if ($page < $pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1])) ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
        <div style="text-align:center;font-size:.8rem;color:var(--text-muted);margin-top:.25rem">
            Page <?= $page ?> of <?= $pages ?> (<?= number_format($total) ?> total)
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
window.APP_URL = '<?= APP_URL ?>';
// CSRF token for JS form submissions
document.addEventListener('DOMContentLoaded', () => {
    // inject a hidden csrf field available to JS
    window._csrfToken = '<?= getCsrfToken() ?>';
});
function applyFilter(key, value) {
    const url = new URL(window.location.href);
    if (value) url.searchParams.set(key, value);
    else url.searchParams.delete(key);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
let searchTimer;
function debounceSearch(input) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('search', input.value);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }, 500);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
