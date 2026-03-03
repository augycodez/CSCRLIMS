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
$canEdit = in_array($_SESSION['user_role'], ['admin', 'lab_manager']);

// ─── Handle Add ────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!SessionManager::verifyCsrf($_POST['csrf_token'] ?? ''))
        redirect(APP_URL . '/inventory.php', 'error', 'Invalid CSRF token.');

    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $catStmt->execute([$_POST['category_id']]);
    $catName = $catStmt->fetchColumn();
    $itemCode = generateItemCode($pdo, $catName ?: 'ITEM');

    // Handle MSDS upload
    $msdsFile = null;
    if (!empty($_FILES['msds_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['msds_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS) || $_FILES['msds_file']['size'] > MAX_FILE_SIZE) {
            redirect(APP_URL . '/inventory.php', 'error', 'Invalid file. Use PDF/DOC/DOCX under 10MB.');
        }
        $msdsFile = $itemCode . '_' . time() . '.' . $ext;
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        move_uploaded_file($_FILES['msds_file']['tmp_name'], UPLOAD_DIR . $msdsFile);
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO inventory_items
            (item_code,item_name,category_id,quantity,min_quantity,unit,storage_location_id,supplier_id,lot_number,expiry_date,date_received,cost_per_unit,hazard_class,msds_file,status,notes,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $itemCode,
            trim($_POST['item_name']),
            (int)$_POST['category_id'],
            (float)$_POST['quantity'],
            (float)$_POST['min_quantity'],
            $_POST['unit'],
            (int)$_POST['storage_location_id'],
            !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
            trim($_POST['lot_number'] ?? '') ?: null,
            !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
            !empty($_POST['date_received']) ? $_POST['date_received'] : null,
            !empty($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : null,
            $_POST['hazard_class'],
            $msdsFile,
            $_POST['status'],
            trim($_POST['notes'] ?? '') ?: null,
            $_SESSION['user_id'],
        ]);
        $newId = $pdo->lastInsertId();
        // Record stock movement
        if ((float)$_POST['quantity'] > 0) {
            $pdo->prepare("INSERT INTO stock_movements (item_id,movement_type,quantity_change,quantity_before,quantity_after,reason,performed_by) VALUES (?,?,?,0,?,?,?)")
                ->execute([$newId, 'in', (float)$_POST['quantity'], (float)$_POST['quantity'], 'Initial stock entry', $_SESSION['user_id']]);
        }
        updateItemStatus($pdo, $newId);
        auditLog($pdo, $_SESSION['user_id'], 'CREATE', 'inventory_items', $newId, null, ['item_code'=>$itemCode]);
        redirect(APP_URL . '/inventory.php', 'success', "Item '{$_POST['item_name']}' added successfully.");
    } catch (PDOException $e) {
        redirect(APP_URL . '/inventory.php', 'error', 'Failed to save item: ' . $e->getMessage());
    }
}

// ─── Handle Update ─────────────────────────────────────────
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    if (!SessionManager::verifyCsrf($_POST['csrf_token'] ?? ''))
        redirect(APP_URL . '/inventory.php', 'error', 'Invalid CSRF token.');

    $id = (int)($_POST['edit_id'] ?? 0);
    if (!$id) redirect(APP_URL . '/inventory.php', 'error', 'Invalid item.');

    // Fetch old for audit
    $old = $pdo->prepare("SELECT * FROM inventory_items WHERE id=?")->execute([$id]) ? null : null;

    $msdsFile = null;
    if (!empty($_FILES['msds_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['msds_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS) || $_FILES['msds_file']['size'] > MAX_FILE_SIZE)
            redirect(APP_URL . '/inventory.php', 'error', 'Invalid file.');
        $stmt2 = $pdo->prepare("SELECT item_code FROM inventory_items WHERE id=?");
        $stmt2->execute([$id]);
        $code = $stmt2->fetchColumn();
        $msdsFile = $code . '_' . time() . '.' . $ext;
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        move_uploaded_file($_FILES['msds_file']['tmp_name'], UPLOAD_DIR . $msdsFile);
    }

    try {
        $msdsUpdate = $msdsFile ? ", msds_file=?" : "";
        $params = [
            trim($_POST['item_name']),
            (int)$_POST['category_id'],
            (float)$_POST['quantity'],
            (float)$_POST['min_quantity'],
            $_POST['unit'],
            (int)$_POST['storage_location_id'],
            !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
            trim($_POST['lot_number'] ?? '') ?: null,
            !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
            !empty($_POST['date_received']) ? $_POST['date_received'] : null,
            !empty($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : null,
            $_POST['hazard_class'],
            $_POST['status'],
            trim($_POST['notes'] ?? '') ?: null,
            $_SESSION['user_id'],
        ];
        if ($msdsFile) $params[] = $msdsFile;
        $params[] = $id;
        $pdo->prepare("UPDATE inventory_items SET item_name=?,category_id=?,quantity=?,min_quantity=?,unit=?,storage_location_id=?,supplier_id=?,lot_number=?,expiry_date=?,date_received=?,cost_per_unit=?,hazard_class=?,status=?,notes=?,updated_by=?{$msdsUpdate} WHERE id=?")->execute($params);
        updateItemStatus($pdo, $id);
        auditLog($pdo, $_SESSION['user_id'], 'UPDATE', 'inventory_items', $id);
        redirect(APP_URL . '/inventory.php', 'success', 'Item updated successfully.');
    } catch (PDOException $e) {
        redirect(APP_URL . '/inventory.php', 'error', 'Update failed: ' . $e->getMessage());
    }
}

// ─── Handle Delete ─────────────────────────────────────────
if ($action === 'delete' && $canEdit) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $pdo->prepare("DELETE FROM inventory_items WHERE id=?")->execute([$id]);
        auditLog($pdo, $_SESSION['user_id'], 'DELETE', 'inventory_items', $id);
        redirect(APP_URL . '/inventory.php', 'success', 'Item deleted.');
    }
}

// ─── Build Query ───────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$location = (int)($_GET['location'] ?? 0);
$status   = $_GET['status'] ?? '';
$expiring = isset($_GET['expiring']);
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(ii.item_name LIKE ? OR ii.item_code LIKE ? OR ii.lot_number LIKE ?)";
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($category) { $where[] = "ii.category_id = ?"; $params[] = $category; }
if ($location)  { $where[] = "ii.storage_location_id = ?"; $params[] = $location; }
if ($status)    { $where[] = "ii.status = ?"; $params[] = $status; }
if ($expiring)  { $where[] = "ii.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)"; }

$whereSQL = implode(' AND ', $where);

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_items ii WHERE $whereSQL");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();

$pg = paginate($total, $page, ITEMS_PER_PAGE);

$itemsStmt = $pdo->prepare("
    SELECT ii.*, c.name as category_name, c.color_code, sl.name as location_name, s.name as supplier_name
    FROM inventory_items ii
    JOIN categories c ON ii.category_id = c.id
    JOIN storage_locations sl ON ii.storage_location_id = sl.id
    LEFT JOIN suppliers s ON ii.supplier_id = s.id
    WHERE $whereSQL
    ORDER BY ii.updated_at DESC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}
");
$itemsStmt->execute($params);
$items = $itemsStmt->fetchAll();

$allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$allLocations  = $pdo->query("SELECT id, name FROM storage_locations ORDER BY name")->fetchAll();

$pageTitle = 'Inventory';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Inventory Management</h1>
    <p><?= number_format($total) ?> items found</p>
  </div>
  <div class="btn-group">
    <a href="<?= APP_URL ?>/reports.php?action=export_csv" class="btn btn-outline">
      <svg viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
      Export CSV
    </a>
    <?php if ($canEdit): ?>
    <button class="btn btn-primary" onclick="openAddModal()">
      <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Add Item
    </button>
    <?php endif; ?>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="padding:16px">
    <form method="GET" action="">
      <div class="filter-bar">
        <div class="search-wrap" style="flex:2">
          <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          <input type="text" name="search" class="form-control" placeholder="Search by name, code or lot..." value="<?= sanitize($search) ?>">
        </div>
        <select name="category" class="form-control" style="min-width:140px">
          <option value="">All Categories</option>
          <?php foreach ($allCategories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $category==$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="location" class="form-control" style="min-width:160px">
          <option value="">All Locations</option>
          <?php foreach ($allLocations as $l): ?>
          <option value="<?= $l['id'] ?>" <?= $location==$l['id']?'selected':'' ?>><?= sanitize($l['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-control" style="min-width:130px">
          <option value="">All Statuses</option>
          <option value="active" <?= $status==='active'?'selected':'' ?>>Active</option>
          <option value="low_stock" <?= $status==='low_stock'?'selected':'' ?>>Low Stock</option>
          <option value="expired" <?= $status==='expired'?'selected':'' ?>>Expired</option>
          <option value="under_maintenance" <?= $status==='under_maintenance'?'selected':'' ?>>Maintenance</option>
          <option value="discontinued" <?= $status==='discontinued'?'selected':'' ?>>Discontinued</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= APP_URL ?>/inventory.php" class="btn btn-outline">Clear</a>
      </div>
    </form>
  </div>
</div>

<!-- Inventory Table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Item Name</th>
          <th>Category</th>
          <th>Quantity</th>
          <th>Location</th>
          <th>Expiry</th>
          <th>Hazard</th>
          <th>Status</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($items)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.86.18-1a3 3 0 0 0-6 0c0 .14.11.56.18 1H10c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h10c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2z"/></svg>
            <h3>No items found</h3>
            <p>Try adjusting your filters or add a new item.</p>
          </div>
        </td></tr>
      <?php else: foreach ($items as $item):
        $expiryClass = '';
        if ($item['expiry_date']) {
            $daysLeft = (strtotime($item['expiry_date']) - time()) / 86400;
            $expiryClass = $daysLeft < 0 ? 'color:var(--c-danger);font-weight:600' : ($daysLeft <= 30 ? 'color:var(--c-warning);font-weight:600' : '');
        }
      ?>
        <tr>
          <td class="td-code"><?= sanitize($item['item_code']) ?></td>
          <td>
            <div style="font-weight:600;font-size:.875rem"><?= sanitize($item['item_name']) ?></div>
            <?php if ($item['lot_number']): ?>
            <div style="font-size:.72rem;color:var(--c-text-3)">Lot: <?= sanitize($item['lot_number']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span style="display:inline-flex;align-items:center;gap:5px;font-size:.82rem">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $item['color_code'] ?>;display:inline-block"></span>
              <?= sanitize($item['category_name']) ?>
            </span>
          </td>
          <td>
            <span style="font-family:var(--font-mono);font-size:.82rem"><?= $item['quantity'] ?> <?= $item['unit'] ?></span>
            <?php if ($item['quantity'] <= $item['min_quantity']): ?>
            <div class="progress-bar" style="margin-top:3px;width:70px">
              <div class="progress-fill danger" style="width:<?= min(100, ($item['quantity']/$item['min_quantity'])*100) ?>%"></div>
            </div>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem"><?= sanitize($item['location_name']) ?></td>
          <td style="font-size:.82rem;<?= $expiryClass ?>"><?= $item['expiry_date'] ?: '—' ?></td>
          <td><?= hazardIcon($item['hazard_class']) ?> <span style="font-size:.72rem"><?= $item['hazard_class'] !== 'None' ? $item['hazard_class'] : '' ?></span></td>
          <td><?= statusBadge($item['status']) ?></td>
          <td style="text-align:right">
            <div class="btn-group" style="justify-content:flex-end">
              <button class="btn btn-outline btn-sm btn-icon" onclick="viewItem(<?= $item['id'] ?>)" title="View details">
                <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
              </button>
              <button class="btn btn-outline btn-sm btn-icon" onclick="openMovementModal(<?= $item['id'] ?>, '<?= addslashes(sanitize($item['item_name'])) ?>')" title="Record movement">
                <svg viewBox="0 0 24 24"><path d="M21 12l-4-4v3H11v2h6v3l4-4zM3 6h12V4H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12v-2H3V6z"/></svg>
              </button>
              <?php if ($canEdit): ?>
              <button class="btn btn-outline btn-sm btn-icon" onclick="editItem(<?= $item['id'] ?>)" title="Edit">
                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              </button>
              <button class="btn btn-outline btn-sm btn-icon" style="color:var(--c-danger)"
                onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes(sanitize($item['item_name'])) ?>', '<?= APP_URL ?>/inventory.php?action=delete&id=<?= $item['id'] ?>')" title="Delete">
                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pg['total_pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">Showing <?= $pg['offset']+1 ?>–<?= min($pg['offset']+$pg['per_page'], $total) ?> of <?= $total ?> items</span>
    <div class="page-links">
      <?php
      $baseUrl = APP_URL . '/inventory.php?' . http_build_query(array_filter(['search'=>$search,'category'=>$category,'location'=>$location,'status'=>$status]));
      for ($i = 1; $i <= $pg['total_pages']; $i++):
        $disabled = $i == $pg['current'] ? 'active' : '';
      ?>
      <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="page-link <?= $disabled ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/views/item_modal.php'; ?>

<!-- Confirm delete modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><span class="modal-title">Confirm Delete</span><button class="modal-close" onclick="closeModal('confirmModal')">×</button></div>
    <div class="modal-body"><p id="confirmMsg"></p></div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeModal('confirmModal')">Cancel</button>
      <button class="btn btn-danger" id="confirmBtn">Delete</button>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal-overlay" id="viewModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Item Details</span><button class="modal-close" onclick="closeModal('viewModal')">×</button></div>
    <div class="modal-body" id="viewModalBody"></div>
  </div>
</div>

<!-- Stock Movement Modal -->
<div class="modal-overlay" id="movementModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <span class="modal-title">Record Stock Movement</span>
      <button class="modal-close" onclick="closeModal('movementModal')">×</button>
    </div>
    <form method="POST" action="movements.php?action=record">
      <input type="hidden" name="csrf_token" value="<?= SessionManager::generateCsrf() ?>">
      <input type="hidden" name="item_id" id="mov_item_id">
      <div class="modal-body">
        <p style="margin-bottom:16px;font-size:.875rem;color:var(--c-text-2)">Recording movement for: <strong id="mov-item-name"></strong></p>
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Type <span class="req">*</span></label>
            <select name="movement_type" class="form-control" required>
              <option value="in">Stock In</option>
              <option value="out">Stock Out</option>
              <option value="adjustment">Adjustment</option>
              <option value="disposal">Disposal</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Quantity <span class="req">*</span></label>
            <input type="number" name="quantity_change" class="form-control" min="0.01" step="0.01" required placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reason</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Used for experiment XYZ" maxlength="255">
        </div>
        <div class="form-group">
          <label class="form-label">Reference Number</label>
          <input type="text" name="reference_number" class="form-control" placeholder="PO#, Exp#, etc." maxlength="100">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('movementModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Record Movement</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
