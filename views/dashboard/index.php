<?php
/**
 * Dashboard — Main Overview
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../includes/header.php';

$db = Database::getInstance();

// Summary stats
$totalItems    = $db->fetchColumn("SELECT COUNT(*) FROM inventory_items");
$lowStockItems = $db->fetchColumn("SELECT COUNT(*) FROM inventory_items WHERE status = 'Low Stock'");
$expiredItems  = $db->fetchColumn("SELECT COUNT(*) FROM inventory_items WHERE status = 'Expired'");
$maintenance   = $db->fetchColumn("SELECT COUNT(*) FROM inventory_items WHERE status = 'Under Maintenance'");
$totalValue    = $db->fetchColumn("SELECT SUM(quantity * cost) FROM inventory_items WHERE status != 'Depleted'");

// Recent activity (stock movements)
$recentMovements = $db->fetchAll(
    "SELECT sm.*, ii.name AS item_name, ii.item_code, u.full_name AS user_name
     FROM stock_movements sm
     JOIN inventory_items ii ON sm.item_id = ii.id
     LEFT JOIN users u ON sm.user_id = u.id
     ORDER BY sm.movement_date DESC LIMIT 8"
);

// Unread alerts
$recentAlerts = $db->fetchAll(
    "SELECT a.*, ii.name AS item_name, ii.item_code
     FROM alerts a JOIN inventory_items ii ON a.item_id = ii.id
     WHERE a.is_dismissed = 0
     ORDER BY a.created_at DESC LIMIT 6"
);

// Category distribution
$categoryData = $db->fetchAll(
    "SELECT c.name, COUNT(ii.id) AS cnt
     FROM categories c
     LEFT JOIN inventory_items ii ON ii.category_id = c.id
     GROUP BY c.id, c.name"
);

// Monthly stock movements (last 6 months)
$monthlyData = $db->fetchAll(
    "SELECT DATE_FORMAT(movement_date, '%b %Y') AS month,
            MONTH(movement_date) AS m, YEAR(movement_date) AS y,
            SUM(CASE WHEN type='IN'  THEN quantity ELSE 0 END) AS stock_in,
            SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END) AS stock_out
     FROM stock_movements
     WHERE movement_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY y, m, month
     ORDER BY y, m"
);

// Top low stock items
$lowStockList = $db->fetchAll(
    "SELECT ii.*, c.name AS category_name, sl.name AS location_name
     FROM inventory_items ii
     JOIN categories c ON ii.category_id = c.id
     LEFT JOIN storage_locations sl ON ii.location_id = sl.id
     WHERE ii.quantity <= ii.min_quantity AND ii.min_quantity > 0
     ORDER BY (ii.quantity / NULLIF(ii.min_quantity,0)) ASC LIMIT 5"
);

// Expiring soon
$expiringSoon = $db->fetchAll(
    "SELECT * FROM inventory_items
     WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY expiry_date ASC LIMIT 5"
);
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card" style="--stat-color:#3b82f6;--stat-bg:#eff6ff">
        <div class="stat-icon"><i class="fas fa-boxes-stacked"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($totalItems) ?></div>
            <div class="stat-label">Total Items</div>
            <div class="stat-change"><i class="fas fa-database"></i> In inventory</div>
        </div>
    </div>
    <div class="stat-card" style="--stat-color:#f59e0b;--stat-bg:#fffbeb">
        <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($lowStockItems) ?></div>
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-change"><i class="fas fa-arrow-down"></i> Below threshold</div>
        </div>
    </div>
    <div class="stat-card" style="--stat-color:#ef4444;--stat-bg:#fef2f2">
        <div class="stat-icon"><i class="fas fa-calendar-xmark"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($expiredItems) ?></div>
            <div class="stat-label">Expired Items</div>
            <div class="stat-change"><i class="fas fa-clock"></i> Need disposal</div>
        </div>
    </div>
    <div class="stat-card" style="--stat-color:#6366f1;--stat-bg:#eef2ff">
        <div class="stat-icon"><i class="fas fa-wrench"></i></div>
        <div class="stat-content">
            <div class="stat-value"><?= number_format($maintenance) ?></div>
            <div class="stat-label">Under Maintenance</div>
            <div class="stat-change"><i class="fas fa-tools"></i> Equipment</div>
        </div>
    </div>
    <div class="stat-card" style="--stat-color:#22c55e;--stat-bg:#f0fdf4">
        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-content">
            <div class="stat-value">$<?= number_format($totalValue ?? 0, 0) ?></div>
            <div class="stat-label">Inventory Value</div>
            <div class="stat-change"><i class="fas fa-chart-line"></i> Total cost value</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="charts-grid">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Items by Category</h3>
        </div>
        <div class="card-body" style="height:280px;display:flex;align-items:center;justify-content:center">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Monthly Stock Movement</h3>
        </div>
        <div class="card-body" style="height:280px">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
</div>

<!-- Alerts + Low Stock -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.75rem" class="two-col-grid">

    <!-- Alerts -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Active Alerts</h3>
            <a href="<?= APP_URL ?>/views/dashboard/alerts.php" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding:1rem">
            <?php if (empty($recentAlerts)): ?>
            <div class="empty-state" style="padding:1.5rem">
                <i class="fas fa-circle-check text-success" style="opacity:1;font-size:2rem"></i>
                <p>No active alerts</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentAlerts as $alert): ?>
            <div class="alert-item <?= !$alert['is_read'] ? 'unread' : '' ?>">
                <div class="alert-icon <?= $alert['type'] ?>">
                    <i class="fas fa-<?= $alert['type'] === 'low_stock' ? 'triangle-exclamation' : ($alert['type'] === 'expiry' ? 'calendar-xmark' : 'wrench') ?>"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-message"><?= htmlspecialchars($alert['message']) ?></div>
                    <div class="alert-time"><?= $alert['item_code'] ?> · <?= date('M j, Y', strtotime($alert['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Expiring Soon -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-days"></i> Expiring in 30 Days</h3>
            <a href="<?= APP_URL ?>/views/inventory/list.php?status=Active&expiry_filter=30days" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding:0">
            <?php if (empty($expiringSoon)): ?>
            <div class="empty-state" style="padding:1.5rem">
                <i class="fas fa-circle-check text-success" style="opacity:1;font-size:2rem"></i>
                <p>No items expiring soon</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
            <table>
                <thead><tr><th>Item</th><th>Expiry</th><th>Days Left</th></tr></thead>
                <tbody>
                <?php foreach ($expiringSoon as $item):
                    $daysLeft = (int)((strtotime($item['expiry_date']) - time()) / 86400);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($item['name']) ?></div>
                        <span class="item-code"><?= $item['item_code'] ?></span>
                    </td>
                    <td><?= date('M j, Y', strtotime($item['expiry_date'])) ?></td>
                    <td>
                        <span class="badge <?= $daysLeft <= 7 ? 'badge-danger' : 'badge-warning' ?>">
                            <?= $daysLeft ?> days
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clock-rotate-left"></i> Recent Stock Activity</h3>
        <a href="<?= APP_URL ?>/views/inventory/movements.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="table-wrapper">
    <table id="recentActivityTable">
        <thead>
            <tr>
                <th>Item</th><th>Type</th><th>Quantity</th><th>Before</th><th>After</th><th>User</th><th>Reference</th><th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($recentMovements)): ?>
        <tr><td colspan="8"><div class="empty-state"><i class="fas fa-inbox"></i><p>No activity yet</p></div></td></tr>
        <?php else: ?>
        <?php foreach ($recentMovements as $m): ?>
        <tr>
            <td>
                <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($m['item_name']) ?></div>
                <span class="item-code"><?= $m['item_code'] ?></span>
            </td>
            <td><span class="movement-badge movement-<?= $m['type'] ?>"><?= $m['type'] ?></span></td>
            <td style="font-weight:600"><?= number_format($m['quantity'], 2) ?></td>
            <td class="text-muted"><?= number_format($m['quantity_before'], 2) ?></td>
            <td style="font-weight:600;color:var(--color-primary)"><?= number_format($m['quantity_after'], 2) ?></td>
            <td><?= htmlspecialchars($m['user_name'] ?? '—') ?></td>
            <td class="text-mono text-small"><?= htmlspecialchars($m['reference'] ?? '—') ?></td>
            <td class="text-muted text-small"><?= date('M j, Y H:i', strtotime($m['movement_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Chart Data -->
<script>
window.APP_URL = '<?= APP_URL ?>';
window.chartData = {
    categories: {
        labels: <?= json_encode(array_column($categoryData, 'name')) ?>,
        values: <?= json_encode(array_column($categoryData, 'cnt')) ?>
    },
    monthly: {
        labels:     <?= json_encode(array_column($monthlyData, 'month')) ?>,
        in_values:  <?= json_encode(array_column($monthlyData, 'stock_in')) ?>,
        out_values: <?= json_encode(array_column($monthlyData, 'stock_out')) ?>
    }
};
</script>
<style>
@media(max-width:768px){.two-col-grid{grid-template-columns:1fr!important}}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
