<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();
SessionManager::requireAuth();

$pdo = Database::getInstance();
refreshAlerts($pdo);

// ─── Dashboard Stats ──────────────────────────────────────
$stats = [];
$stats['total']       = $pdo->query("SELECT COUNT(*) FROM inventory_items")->fetchColumn();
$stats['low_stock']   = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='low_stock'")->fetchColumn();
$stats['expired']     = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='expired'")->fetchColumn();
$stats['maintenance'] = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='under_maintenance'")->fetchColumn();
$stats['active']      = $pdo->query("SELECT COUNT(*) FROM inventory_items WHERE status='active'")->fetchColumn();
$alertCount           = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read=0")->fetchColumn();

// ─── Recent Activity ──────────────────────────────────────
$recentActivity = $pdo->query("
    SELECT sm.*, ii.item_name, ii.item_code, ii.unit, u.full_name
    FROM stock_movements sm
    JOIN inventory_items ii ON sm.item_id = ii.id
    JOIN users u ON sm.performed_by = u.id
    ORDER BY sm.created_at DESC LIMIT 10
")->fetchAll();

// ─── Category Distribution ────────────────────────────────
$catData = $pdo->query("
    SELECT c.name, COUNT(ii.id) as cnt, c.color_code
    FROM categories c
    LEFT JOIN inventory_items ii ON ii.category_id = c.id
    GROUP BY c.id ORDER BY cnt DESC
")->fetchAll();

// ─── Low Stock Items ──────────────────────────────────────
$lowStockItems = $pdo->query("
    SELECT ii.item_code, ii.item_name, ii.quantity, ii.min_quantity, ii.unit, c.name as category
    FROM inventory_items ii
    JOIN categories c ON ii.category_id = c.id
    WHERE ii.quantity <= ii.min_quantity AND ii.status != 'discontinued'
    ORDER BY (ii.quantity / GREATEST(ii.min_quantity, 0.01)) ASC LIMIT 8
")->fetchAll();

// ─── Monthly Stock Movement ───────────────────────────────
$monthlyData = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') as month,
        SUM(CASE WHEN movement_type='in' THEN quantity_change ELSE 0 END) as stock_in,
        SUM(CASE WHEN movement_type='out' THEN quantity_change ELSE 0 END) as stock_out
    FROM stock_movements
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY MIN(created_at)
")->fetchAll();

// ─── Upcoming Expiries ────────────────────────────────────
$expiring = $pdo->query("
    SELECT item_code, item_name, expiry_date, quantity, unit,
           DATEDIFF(expiry_date, CURDATE()) as days_left
    FROM inventory_items
    WHERE expiry_date IS NOT NULL
      AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
      AND status != 'discontinued'
    ORDER BY expiry_date ASC LIMIT 5
")->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Dashboard</h1>
    <p>Welcome back, <?= sanitize($_SESSION['full_name']) ?>. Here's your lab inventory overview.</p>
  </div>
  <?php if (in_array($_SESSION['user_role'], ['admin','lab_manager'])): ?>
  <button class="btn btn-primary" onclick="openAddModal()">
    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
    Add Item
  </button>
  <?php endif; ?>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon teal">
      <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.86.18-1a3 3 0 0 0-6 0c0 .14.11.56.18 1H10c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h10c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($stats['total']) ?></div>
      <div class="stat-label">Total Items</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon success">
      <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($stats['active']) ?></div>
      <div class="stat-label">Active Items</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon warning">
      <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($stats['low_stock']) ?></div>
      <div class="stat-label">Low Stock Items</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon danger">
      <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($stats['expired']) ?></div>
      <div class="stat-label">Expired Items</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon info">
      <svg viewBox="0 0 24 24"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($stats['maintenance']) ?></div>
      <div class="stat-label">Under Maintenance</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= number_format($alertCount) ?></div>
      <div class="stat-label">Unread Alerts</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header"><span class="card-title">Stock Movement (6 months)</span></div>
    <div class="card-body"><div class="chart-wrap"><canvas id="movementChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><span class="card-title">Items by Category</span></div>
    <div class="card-body"><div class="chart-wrap"><canvas id="categoryChart"></canvas></div></div>
  </div>
</div>

<!-- Low Stock + Expiring -->
<div class="grid-2" style="margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <span class="card-title">⚠️ Low Stock Items</span>
      <a href="<?= APP_URL ?>/inventory.php?status=low_stock" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Qty</th><th>Min</th><th>Stock Level</th></tr></thead>
        <tbody>
        <?php if (empty($lowStockItems)): ?>
          <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--c-text-3)">✅ All items are sufficiently stocked</td></tr>
        <?php else: foreach ($lowStockItems as $item):
          $pct = $item['min_quantity'] > 0 ? min(100, ($item['quantity'] / $item['min_quantity']) * 100) : 0;
          $cls = $pct < 30 ? 'danger' : ($pct < 70 ? 'warning' : '');
        ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.855rem"><?= sanitize($item['item_name']) ?></div>
              <div class="td-code"><?= sanitize($item['item_code']) ?></div>
            </td>
            <td style="font-family:var(--font-mono);font-size:.82rem"><?= $item['quantity'] ?> <?= $item['unit'] ?></td>
            <td style="font-size:.78rem;color:var(--c-text-2)"><?= $item['min_quantity'] ?> <?= $item['unit'] ?></td>
            <td style="min-width:100px">
              <div class="progress-bar">
                <div class="progress-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <div style="font-size:.68rem;color:var(--c-text-3);margin-top:2px"><?= round($pct) ?>%</div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">📅 Expiring Soon (60 days)</span>
      <a href="<?= APP_URL ?>/inventory.php?expiring=1" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Expiry</th><th>Days Left</th></tr></thead>
        <tbody>
        <?php if (empty($expiring)): ?>
          <tr><td colspan="3" style="text-align:center;padding:24px;color:var(--c-text-3)">✅ No items expiring within 60 days</td></tr>
        <?php else: foreach ($expiring as $item):
          $cls = $item['days_left'] <= 7 ? 'badge-danger' : ($item['days_left'] <= 30 ? 'badge-warning' : 'badge-info');
        ?>
          <tr>
            <td>
              <div style="font-weight:600;font-size:.855rem"><?= sanitize($item['item_name']) ?></div>
              <div class="td-code"><?= sanitize($item['item_code']) ?></div>
            </td>
            <td style="font-size:.82rem"><?= $item['expiry_date'] ?></td>
            <td><span class="badge <?= $cls ?>"><?= $item['days_left'] ?>d</span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent Activity -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Stock Activity</span>
    <a href="<?= APP_URL ?>/movements.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Item</th><th>Type</th><th>Change</th><th>Before → After</th><th>Reason</th><th>By</th><th>Time</th></tr></thead>
      <tbody>
      <?php if (empty($recentActivity)): ?>
        <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--c-text-3)">No stock movements recorded yet.</td></tr>
      <?php else: foreach ($recentActivity as $m):
        $typeClass = ['in'=>'mov-in','out'=>'mov-out','adjustment'=>'mov-adj','disposal'=>'mov-dis'][$m['movement_type']] ?? '';
        $sign = $m['movement_type'] === 'in' ? '+' : ($m['movement_type'] === 'out' ? '-' : '±');
      ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.855rem"><?= sanitize($m['item_name']) ?></div>
            <div class="td-code"><?= sanitize($m['item_code']) ?></div>
          </td>
          <td><span class="<?= $typeClass ?>" style="text-transform:capitalize"><?= $m['movement_type'] ?></span></td>
          <td style="font-family:var(--font-mono);font-size:.82rem"><span class="<?= $typeClass ?>"><?= $sign ?><?= abs($m['quantity_change']) ?> <?= $m['unit'] ?></span></td>
          <td style="font-size:.78rem;color:var(--c-text-2)"><?= $m['quantity_before'] ?> → <?= $m['quantity_after'] ?></td>
          <td style="font-size:.78rem;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= sanitize($m['reason'] ?? '—') ?></td>
          <td style="font-size:.78rem"><?= sanitize($m['full_name']) ?></td>
          <td style="font-size:.72rem;color:var(--c-text-3)"><?= date('M j, H:i', strtotime($m['created_at'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
// Include the add/edit item modal
require_once __DIR__ . '/views/item_modal.php';
// Include confirm modal
?>
<div class="modal-overlay" id="confirmModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><span class="modal-title">Confirm Action</span><button class="modal-close" onclick="closeModal('confirmModal')">×</button></div>
    <div class="modal-body"><p id="confirmMsg"></p></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('confirmModal')">Cancel</button>
      <button class="btn btn-danger" id="confirmBtn">Delete</button>
    </div>
  </div>
</div>
<div class="modal-overlay" id="viewModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Item Details</span><button class="modal-close" onclick="closeModal('viewModal')">×</button></div>
    <div class="modal-body" id="viewModalBody"></div>
  </div>
</div>

<?php
$catLabels = json_encode(array_column($catData, 'name'));
$catCounts = json_encode(array_column($catData, 'cnt'));
$catColors = json_encode(array_column($catData, 'color_code'));
$monthLabels = json_encode(array_column($monthlyData, 'month'));
$monthIn  = json_encode(array_column($monthlyData, 'stock_in'));
$monthOut = json_encode(array_column($monthlyData, 'stock_out'));
$extraJs = "
document.addEventListener('DOMContentLoaded', () => {
  LabCharts.createDoughnut('categoryChart', {$catLabels}, {$catCounts}, {$catColors});
  LabCharts.createLineChart('movementChart', {$monthLabels}, [
    { label: 'Stock In',  data: {$monthIn},  borderColor: '#0FAB73', backgroundColor: '#0FAB7322', fill: true },
    { label: 'Stock Out', data: {$monthOut}, borderColor: '#EF4444', backgroundColor: '#EF444422', fill: true }
  ]);
});";
require_once __DIR__ . '/includes/footer.php';
