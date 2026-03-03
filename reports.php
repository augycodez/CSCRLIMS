<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();
SessionManager::requireAuth();

$pdo    = Database::getInstance();
$action = $_GET['action'] ?? '';

// ─── CSV Export ────────────────────────────────────────────
if ($action === 'export_csv') {
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to'] ?? '';
    $category = (int)($_GET['category'] ?? 0);

    $where  = ['1=1'];
    $params = [];
    if ($dateFrom) { $where[] = "ii.date_received >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $where[] = "ii.date_received <= ?"; $params[] = $dateTo; }
    if ($category) { $where[] = "ii.category_id = ?"; $params[] = $category; }
    $whereSQL = implode(' AND ', $where);

    $items = $pdo->prepare("SELECT ii.item_code, ii.item_name, c.name as category, ii.quantity, ii.unit, sl.name as location, s.name as supplier, ii.lot_number, ii.expiry_date, ii.date_received, ii.cost_per_unit, ii.hazard_class, ii.status FROM inventory_items ii JOIN categories c ON ii.category_id=c.id JOIN storage_locations sl ON ii.storage_location_id=sl.id LEFT JOIN suppliers s ON ii.supplier_id=s.id WHERE $whereSQL ORDER BY c.name, ii.item_name");
    $items->execute($params);
    $rows = $items->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lab_inventory_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Item Code','Item Name','Category','Quantity','Unit','Storage Location','Supplier','Lot Number','Expiry Date','Date Received','Cost/Unit','Hazard Class','Status']);
    foreach ($rows as $row) fputcsv($out, array_values($row));
    fclose($out);
    exit;
}

// ─── Summary Stats ─────────────────────────────────────────
$summary = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status='low_stock' THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status='under_maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(COALESCE(quantity * cost_per_unit, 0)) as total_value
    FROM inventory_items
")->fetch();

$catBreakdown = $pdo->query("
    SELECT c.name, COUNT(ii.id) as count, SUM(ii.quantity * COALESCE(ii.cost_per_unit,0)) as value
    FROM categories c LEFT JOIN inventory_items ii ON ii.category_id=c.id
    GROUP BY c.id ORDER BY count DESC
")->fetchAll();

$allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div><h1>Reports &amp; Analytics</h1><p>Inventory summaries and data exports</p></div>
</div>

<!-- Summary Stats -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon teal"><svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.86.18-1a3 3 0 0 0-6 0c0 .14.11.56.18 1H10c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h10c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2z"/></svg></div><div><div class="stat-value"><?= $summary['total'] ?></div><div class="stat-label">Total Items</div></div></div>
  <div class="stat-card"><div class="stat-icon success"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div><div class="stat-value"><?= $summary['active'] ?></div><div class="stat-label">Active</div></div></div>
  <div class="stat-card"><div class="stat-icon warning"><svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg></div><div><div class="stat-value"><?= $summary['low_stock'] ?></div><div class="stat-label">Low Stock</div></div></div>
  <div class="stat-card"><div class="stat-icon danger"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg></div><div><div class="stat-value"><?= $summary['expired'] ?></div><div class="stat-label">Expired</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></div><div><div class="stat-value">$<?= number_format((float)$summary['total_value'], 0) ?></div><div class="stat-label">Total Value</div></div></div>
</div>

<div class="grid-2">
  <!-- Export Panel -->
  <div class="card">
    <div class="card-header"><span class="card-title">Export Data</span></div>
    <div class="card-body">
      <form method="GET" action="">
        <input type="hidden" name="action" value="export_csv">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Date From</label>
            <input type="date" name="date_from" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Date To</label>
            <input type="date" name="date_to" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($allCategories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="btn-group">
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            Export CSV
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Category Breakdown -->
  <div class="card">
    <div class="card-header"><span class="card-title">Category Breakdown</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th>Items</th><th>Est. Value</th></tr></thead>
        <tbody>
        <?php foreach ($catBreakdown as $cat): ?>
          <tr>
            <td><?= sanitize($cat['name']) ?></td>
            <td><?= $cat['count'] ?></td>
            <td>$<?= number_format((float)$cat['value'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
