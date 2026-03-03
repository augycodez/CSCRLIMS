<?php
$pageTitle = 'Alerts';
require_once __DIR__ . '/../../includes/header.php';

$db = Database::getInstance();

// Handle dismiss action
if ($_GET['dismiss'] ?? false) {
    $alertId = sanitizeInt($_GET['dismiss']);
    $db->query("UPDATE alerts SET is_dismissed = 1 WHERE id = ?", [$alertId]);
    header('Location: ' . APP_URL . '/views/dashboard/alerts.php');
    exit;
}
if ($_GET['dismiss_all'] ?? false) {
    $db->query("UPDATE alerts SET is_dismissed = 1");
    header('Location: ' . APP_URL . '/views/dashboard/alerts.php');
    exit;
}
if ($_GET['mark_read'] ?? false) {
    $db->query("UPDATE alerts SET is_read = 1 WHERE is_dismissed = 0");
    header('Location: ' . APP_URL . '/views/dashboard/alerts.php');
    exit;
}

$alerts = $db->fetchAll(
    "SELECT a.*, ii.name AS item_name, ii.item_code, ii.id AS item_id
     FROM alerts a
     JOIN inventory_items ii ON a.item_id = ii.id
     WHERE a.is_dismissed = 0
     ORDER BY a.is_read ASC, a.created_at DESC"
);

// Mark all as read
$db->query("UPDATE alerts SET is_read = 1 WHERE is_dismissed = 0");
?>

<div class="page-header">
    <div>
        <h1>Alerts</h1>
        <p><?= count($alerts) ?> active alert(s) requiring attention</p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="?dismiss_all=1" class="btn btn-secondary" <?= empty($alerts) ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
            <i class="fas fa-check-double"></i> Dismiss All
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:1rem">
    <?php if (empty($alerts)): ?>
    <div class="empty-state">
        <i class="fas fa-circle-check text-success" style="opacity:1;font-size:3rem;color:var(--color-success)"></i>
        <h3>All Clear!</h3>
        <p>No active alerts. Your inventory is in good shape.</p>
    </div>
    <?php else: ?>
    <?php foreach ($alerts as $a): ?>
    <div class="alert-item <?= !$a['is_read'] ? 'unread' : '' ?>" style="position:relative">
        <div class="alert-icon <?= $a['type'] ?>">
            <i class="fas fa-<?= $a['type'] === 'low_stock' ? 'triangle-exclamation' : ($a['type'] === 'expiry' ? 'calendar-xmark' : 'wrench') ?>"></i>
        </div>
        <div class="alert-content">
            <div class="alert-message"><?= htmlspecialchars($a['message']) ?></div>
            <div class="alert-time">
                <span class="item-code"><?= $a['item_code'] ?></span>
                &nbsp;·&nbsp;<?= date('M j, Y H:i', strtotime($a['created_at'])) ?>
                &nbsp;·&nbsp;<span class="badge badge-secondary"><?= str_replace('_', ' ', strtoupper($a['type'])) ?></span>
            </div>
        </div>
        <div style="display:flex;gap:.4rem;flex-shrink:0">
            <a href="<?= APP_URL ?>/views/inventory/view.php?id=<?= $a['item_id'] ?>" class="btn btn-sm btn-secondary" title="View item">
                <i class="fas fa-eye"></i>
            </a>
            <a href="?dismiss=<?= $a['id'] ?>" class="btn btn-sm btn-secondary" title="Dismiss">
                <i class="fas fa-xmark"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
