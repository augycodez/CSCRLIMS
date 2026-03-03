<?php
/**
 * Edit Inventory Item
 */
$pageTitle = 'Edit Item';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin', 'lab_manager');

$db = Database::getInstance();
$id = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/views/inventory/list.php'); exit; }

$item = $db->fetchOne("SELECT * FROM inventory_items WHERE id = ?", [$id]);
if (!$item) { setFlash('error', 'Item not found.'); header('Location: ' . APP_URL . '/views/inventory/list.php'); exit; }

$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
$locations  = $db->fetchAll("SELECT id, name, type, temperature FROM storage_locations ORDER BY name");
$suppliers  = $db->fetchAll("SELECT id, name FROM suppliers ORDER BY name");

$errors = [];
$data   = $item; // Pre-fill with existing data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $oldData = $item;
        $data = [
            'name'           => sanitize($_POST['name']           ?? ''),
            'category_id'    => sanitizeInt($_POST['category_id'] ?? 0),
            'quantity'       => sanitizeFloat($_POST['quantity']  ?? 0),
            'min_quantity'   => sanitizeFloat($_POST['min_quantity'] ?? 0),
            'unit'           => sanitize($_POST['unit']           ?? ''),
            'location_id'    => sanitizeInt($_POST['location_id'] ?? 0) ?: null,
            'supplier_id'    => sanitizeInt($_POST['supplier_id'] ?? 0) ?: null,
            'lot_number'     => sanitize($_POST['lot_number']     ?? ''),
            'catalog_number' => sanitize($_POST['catalog_number'] ?? ''),
            'expiry_date'    => sanitize($_POST['expiry_date']    ?? '') ?: null,
            'date_received'  => sanitize($_POST['date_received']  ?? '') ?: null,
            'cost'           => sanitizeFloat($_POST['cost']      ?? 0) ?: null,
            'hazard_class'   => sanitize($_POST['hazard_class']   ?? '') ?: null,
            'status'         => sanitize($_POST['status']         ?? 'Active'),
            'notes'          => sanitize($_POST['notes']          ?? ''),
        ];

        if (empty($data['name']))   $errors[] = 'Item name is required.';
        if (!$data['category_id'])  $errors[] = 'Category is required.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $msdsFile = $item['msds_file'];

                if (!empty($_FILES['msds_file']['name'])) {
                    $newFile = handleMsdsUpload($_FILES['msds_file'], $item['item_code']);
                    if ($newFile) {
                        // Delete old file
                        if ($msdsFile && file_exists(APP_ROOT . '/assets/uploads/msds/' . $msdsFile)) {
                            unlink(APP_ROOT . '/assets/uploads/msds/' . $msdsFile);
                        }
                        $msdsFile = $newFile;
                    } else {
                        $errors[] = 'Invalid MSDS file.';
                    }
                }

                if (empty($errors)) {
                    $user = getCurrentUser();
                    $db->query(
                        "UPDATE inventory_items SET name=?,category_id=?,quantity=?,min_quantity=?,unit=?,location_id=?,supplier_id=?,lot_number=?,catalog_number=?,expiry_date=?,date_received=?,cost=?,hazard_class=?,msds_file=?,status=?,notes=?,updated_by=? WHERE id=?",
                        [$data['name'],$data['category_id'],$data['quantity'],$data['min_quantity'],$data['unit'],$data['location_id'],$data['supplier_id'],$data['lot_number'],$data['catalog_number'],$data['expiry_date'],$data['date_received'],$data['cost'],$data['hazard_class'],$msdsFile,$data['status'],$data['notes'],$user['id'],$id]
                    );

                    // Log quantity change as movement
                    if ((float)$data['quantity'] !== (float)$oldData['quantity']) {
                        $diff = (float)$data['quantity'] - (float)$oldData['quantity'];
                        logStockMovement($id, 'ADJUSTMENT', abs($diff), (float)$oldData['quantity'], (float)$data['quantity'], 'EDIT', 'Quantity adjusted via edit');
                    }

                    auditLog('UPDATE_ITEM', 'inventory_items', $id, $oldData, $data);
                    $db->commit();
                    setFlash('success', "Item '{$data['name']}' updated successfully.");
                    header('Location: ' . APP_URL . '/views/inventory/view.php?id=' . $id);
                    exit;
                }
                $db->rollBack();
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1>Edit Item</h1>
        <p><span class="item-code"><?= $item['item_code'] ?></span> — <?= htmlspecialchars($item['name']) ?></p>
    </div>
    <div style="display:flex;gap:.5rem">
        <a href="<?= APP_URL ?>/views/inventory/view.php?id=<?= $id ?>" class="btn btn-secondary">
            <i class="fas fa-eye"></i> View
        </a>
        <a href="<?= APP_URL ?>/views/inventory/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($errors): ?>
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-md);padding:1rem 1.25rem;margin-bottom:1.5rem;color:#dc2626">
    <strong><i class="fas fa-circle-xmark"></i> Errors:</strong>
    <ul style="margin:.5rem 0 0 1.25rem;font-size:.875rem">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><i class="fas fa-pen"></i> Edit Item Details</h2></div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?= csrfField() ?>
            <div class="form-grid">
                <div class="form-group full-width">
                    <label>Item Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($data['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="category_id" class="form-control" required>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $data['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach (['Active','Low Stock','Expired','Depleted','Under Maintenance','Discontinued'] as $s): ?>
                        <option value="<?= $s ?>" <?= $data['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" value="<?= $data['quantity'] ?>" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Min Quantity</label>
                    <input type="number" id="min_quantity" name="min_quantity" class="form-control" value="<?= $data['min_quantity'] ?>" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" class="form-control">
                        <?php foreach (['mL','L','mg','g','kg','units','vials','boxes','pieces','μL','μg','nmol','mmol','mol'] as $u): ?>
                        <option value="<?= $u ?>" <?= $data['unit'] === $u ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <select name="location_id" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $data['location_id'] == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $data['supplier_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lot Number</label>
                    <input type="text" name="lot_number" class="form-control" value="<?= htmlspecialchars($data['lot_number']) ?>">
                </div>
                <div class="form-group">
                    <label>Catalog Number</label>
                    <input type="text" name="catalog_number" class="form-control" value="<?= htmlspecialchars($data['catalog_number']) ?>">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($data['expiry_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Date Received</label>
                    <input type="date" name="date_received" class="form-control" value="<?= htmlspecialchars($data['date_received'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Cost per Unit ($)</label>
                    <input type="number" name="cost" class="form-control" value="<?= $data['cost'] ?>" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Hazard Class</label>
                    <select name="hazard_class" class="form-control">
                        <?php foreach (['None','Flammable','Corrosive','Toxic','Oxidizing','Explosive','Radioactive','Biological','Carcinogenic','Environmental'] as $h): ?>
                        <option value="<?= $h ?>" <?= ($data['hazard_class'] ?? 'None') === $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>MSDS File <?= $data['msds_file'] ? '(currently uploaded)' : '' ?></label>
                    <input type="file" name="msds_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <?php if ($data['msds_file']): ?>
                    <a href="<?= UPLOADS_URL ?>msds/<?= htmlspecialchars($data['msds_file']) ?>" target="_blank" class="text-small" style="color:var(--color-primary)">
                        <i class="fas fa-file"></i> View current file
                    </a>
                    <?php endif; ?>
                </div>
                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="divider"></div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <a href="<?= APP_URL ?>/views/inventory/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>
<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
