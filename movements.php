<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();
SessionManager::requireAuth();

$pdo = Database::getInstance();
$action = $_GET['action'] ?? '';

// ─── Handle Record Movement ────────────────────────────────
if ($action === 'record' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionManager::verifyCsrf($_POST['csrf_token'] ?? ''))
        redirect(APP_URL . '/movements.php', 'error', 'Invalid CSRF token.');

    $itemId = (int)($_POST['item_id'] ?? 0);
    $type   = $_POST['movement_type'] ?? '';
    $qty    = (float)($_POST['quantity_change'] ?? 0);

    if (!$itemId || !in_array($type, ['in','out','adjustment','disposal']) || $qty <= 0)
        redirect(APP_URL . '/inventory.php', 'error', 'Invalid movement data.');

    $stmt = $pdo->prepare("SELECT quantity, unit FROM inventory_items WHERE id=?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) redirect(APP_URL . '/inventory.php', 'error', 'Item not found.');

    $before = (float)$item['quantity'];
    $after  = $type === 'in' ? $before + $qty : max(0, $before - $qty);
    if ($type === 'adjustment') $after = $qty; // Set absolute value
    if ($type === 'disposal') $after = max(0, $before - $qty);

    $pdo->prepare("INSERT INTO stock_movements (item_id,movement_type,quantity_change,quantity_before,quantity_after,reason,reference_number,performed_by) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$itemId, $type, $qty, $before, $after, trim($_POST['reason'] ?? ''), trim($_POST['reference_number'] ?? '') ?: null, $_SESSION['user_id']]);

    $pdo->prepare("UPDATE inventory_items SET quantity=?, updated_by=? WHERE id=?")->execute([$after, $_SESSION['user_id'], $itemId]);
    updateItemStatus($pdo, $itemId);
    auditLog($pdo, $_SESSION['user_id'], 'STOCK_' . strtoupper($type), 'stock_movements', $itemId);

    redirect(APP_URL . '/movements.php', 'success', "Movement recorded: {$type} {$qty} {$item['unit']}");
}

// ─── Fetch Movements ───────────────────────────────────────
$page  = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$pg    = paginate($total, $page, 30);

$movements = $pdo->query("
    SELECT sm.*, ii.item_name, ii.item_code, ii.unit, u.full_name
    FROM stock_movements sm
    JOIN inventory_items ii ON sm.item_id = ii.id
    JOIN users u ON sm.performed_by = u.id
    ORDER BY sm.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
")->fetchAll();

$pageTitle = 'Stock Movements';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Stock Movements</h1>
    <p>History of all inventory transactions</p>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date/Time</th><th>Item</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>Reason</th><th>Reference</th><th>By</th></tr>
      </thead>
      <tbody>
      <?php if (empty($movements)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--c-text-3)">No movements recorded yet.</td></tr>
      <?php else: foreach ($movements as $m):
        $typeCls = ['in'=>'mov-in','out'=>'mov-out','adjustment'=>'mov-adj','disposal'=>'mov-dis'][$m['movement_type']] ?? '';
        $sign = $m['movement_type'] === 'in' ? '+' : ($m['movement_type'] === 'out' || $m['movement_type'] === 'disposal' ? '-' : '±');
      ?>
        <tr>
          <td style="font-size:.78rem;color:var(--c-text-2);white-space:nowrap"><?= date('M j Y, H:i', strtotime($m['created_at'])) ?></td>
          <td>
            <div style="font-weight:600;font-size:.855rem"><?= sanitize($m['item_name']) ?></div>
            <div class="td-code"><?= sanitize($m['item_code']) ?></div>
          </td>
          <td><span class="<?= $typeCls ?>" style="text-transform:capitalize;font-size:.82rem"><?= $m['movement_type'] ?></span></td>
          <td style="font-family:var(--font-mono);font-size:.82rem"><span class="<?= $typeCls ?>"><?= $sign ?><?= abs($m['quantity_change']) ?> <?= $m['unit'] ?></span></td>
          <td style="font-family:var(--font-mono);font-size:.78rem;color:var(--c-text-2)"><?= $m['quantity_before'] ?></td>
          <td style="font-family:var(--font-mono);font-size:.78rem"><?= $m['quantity_after'] ?></td>
          <td style="font-size:.78rem;max-width:200px"><?= sanitize($m['reason'] ?? '—') ?></td>
          <td style="font-size:.72rem;color:var(--c-text-3)"><?= sanitize($m['reference_number'] ?? '—') ?></td>
          <td style="font-size:.78rem"><?= sanitize($m['full_name']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'], $total) ?> of <?= $total ?></span>
    <div class="page-links">
      <?php for ($i=1; $i<=$pg['total_pages']; $i++): ?>
      <a href="?page=<?= $i ?>" class="page-link <?= $i==$pg['current']?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
