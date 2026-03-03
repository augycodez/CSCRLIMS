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

$filter = $_GET['filter'] ?? 'unread';
$where  = $filter === 'all' ? '' : 'WHERE a.is_read = 0';

$alerts = $pdo->query("
    SELECT a.*, ii.item_name, ii.item_code
    FROM alerts a
    JOIN inventory_items ii ON a.item_id = ii.id
    $where
    ORDER BY a.created_at DESC
")->fetchAll();

$pageTitle = 'Alerts';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Alerts &amp; Notifications</h1>
    <p><?= count($alerts) ?> <?= $filter === 'all' ? 'total' : 'unread' ?> alerts</p>
  </div>
  <div class="btn-group">
    <a href="?filter=unread" class="btn <?= $filter!=='all'?'btn-primary':'btn-outline' ?>">Unread</a>
    <a href="?filter=all" class="btn <?= $filter==='all'?'btn-primary':'btn-outline' ?>">All</a>
    <button class="btn btn-outline" onclick="markAllRead()">Mark All Read</button>
  </div>
</div>

<div class="card">
  <?php if (empty($alerts)): ?>
    <div class="empty-state">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      <h3>No alerts</h3>
      <p>All systems normal. No active alerts.</p>
    </div>
  <?php else: foreach ($alerts as $a):
    $dotClass = in_array($a['alert_type'], ['expired','low_stock']) ? 'danger' : ($a['alert_type'] === 'expiry' ? 'warning' : 'info');
    $typeLabel = ['low_stock'=>'Low Stock','expiry'=>'Expiring Soon','expired'=>'Expired','maintenance'=>'Maintenance'][$a['alert_type']] ?? $a['alert_type'];
  ?>
    <div class="alert-item" style="<?= $a['is_read'] ? 'opacity:.55' : '' ?>">
      <div class="alert-dot <?= $dotClass ?>"></div>
      <div class="alert-msg">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
          <span class="badge badge-<?= $dotClass === 'danger' ? 'danger' : ($dotClass === 'warning' ? 'warning' : 'info') ?>" style="font-size:.68rem"><?= $typeLabel ?></span>
          <span class="td-code" style="font-size:.72rem"><?= sanitize($a['item_code']) ?></span>
        </div>
        <?= sanitize($a['message']) ?>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
        <span class="alert-time"><?= date('M j, H:i', strtotime($a['created_at'])) ?></span>
        <?php if (!$a['is_read']): ?>
        <button class="btn btn-outline btn-sm" onclick="markAlertRead(<?= $a['id'] ?>, this)">✓ Read</button>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/inventory.php?search=<?= urlencode($a['item_code']) ?>" class="btn btn-outline btn-sm">View Item</a>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
