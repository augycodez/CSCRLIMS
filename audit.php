<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();
SessionManager::requireRole(['admin']);

$pdo  = Database::getInstance();
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$pg   = paginate($total, $page, 30);

$logs = $pdo->query("
    SELECT al.*, u.full_name, u.username
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
")->fetchAll();

$pageTitle = 'Audit Log';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div><h1>Audit Log</h1><p>Complete system activity history</p></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>IP Address</th></tr></thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--c-text-3)">No audit records found.</td></tr>
      <?php else: foreach ($logs as $log):
        $actionClass = str_contains($log['action'],'DELETE') ? 'style="color:var(--c-danger)"' :
                      (str_contains($log['action'],'CREATE') ? 'style="color:var(--c-success)"' :
                      (str_contains($log['action'],'LOGIN') ? 'style="color:var(--c-info)"' : ''));
      ?>
        <tr>
          <td style="font-size:.78rem;white-space:nowrap"><?= date('M j Y, H:i:s', strtotime($log['created_at'])) ?></td>
          <td style="font-size:.82rem"><?= sanitize($log['full_name'] ?? 'System') ?> <span class="td-code" style="font-size:.68rem"><?= sanitize($log['username'] ?? '') ?></span></td>
          <td <?= $actionClass ?>><strong style="font-size:.82rem"><?= sanitize($log['action']) ?></strong></td>
          <td style="font-size:.78rem;color:var(--c-text-2)"><?= sanitize($log['table_name'] ?? '—') ?></td>
          <td style="font-family:var(--font-mono);font-size:.78rem"><?= $log['record_id'] ?: '—' ?></td>
          <td style="font-size:.78rem;color:var(--c-text-3)"><?= sanitize($log['ip_address'] ?? '—') ?></td>
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
