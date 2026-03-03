<?php
/**
 * Add Inventory Item
 */
$pageTitle = 'Add Item';
require_once __DIR__ . '/../../includes/header.php';
requireRole('admin', 'lab_manager');

$db = Database::getInstance();
$categories = $db->fetchAll("SELECT id, name FROM categories ORDER BY name");
$locations  = $db->fetchAll("SELECT id, name, type, temperature FROM storage_locations ORDER BY name");
$suppliers  = $db->fetchAll("SELECT id, name FROM suppliers ORDER BY name");

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        // Collect & sanitize
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

        if (empty($data['name']))        $errors[] = 'Item name is required.';
        if (!$data['category_id'])       $errors[] = 'Category is required.';
        if (empty($data['unit']))        $errors[] = 'Unit is required.';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $itemCode = generateItemCode();
                $msdsFile = null;

                // Handle MSDS upload
                if (!empty($_FILES['msds_file']['name'])) {
                    $msdsFile = handleMsdsUpload($_FILES['msds_file'], $itemCode);
                    if (!$msdsFile) $errors[] = 'Invalid MSDS file. Please upload PDF, JPG, or PNG (max 10MB).';
                }

                if (empty($errors)) {
                    $user = getCurrentUser();
                    $db->query(
                        "INSERT INTO inventory_items 
                         (item_code,name,category_id,quantity,min_quantity,unit,location_id,supplier_id,lot_number,catalog_number,expiry_date,date_received,cost,hazard_class,msds_file,status,notes,created_by,updated_by)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [$itemCode,$data['name'],$data['category_id'],$data['quantity'],$data['min_quantity'],$data['unit'],$data['location_id'],$data['supplier_id'],$data['lot_number'],$data['catalog_number'],$data['expiry_date'],$data['date_received'],$data['cost'],$data['hazard_class'],$msdsFile,$data['status'],$data['notes'],$user['id'],$user['id']]
                    );
                    $newId = $db->lastInsertId();

                    // Log initial stock movement
                    if ($data['quantity'] > 0) {
                        logStockMovement((int)$newId, 'IN', $data['quantity'], 0, $data['quantity'], 'INITIAL', 'Initial stock entry');
                    }

                    auditLog('CREATE_ITEM', 'inventory_items', (int)$newId, null, $data);
                    $db->commit();
                    setFlash('success', "Item '{$data['name']}' created successfully with code {$itemCode}.");
                    header('Location: ' . APP_URL . '/views/inventory/list.php');
                    exit;
                }
                $db->rollBack();
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="page-header">
    <div>
        <h1>Add New Item</h1>
        <p>Register a new item in the laboratory inventory</p>
    </div>
    <a href="<?= APP_URL ?>/views/inventory/list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<?php if ($errors): ?>
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:var(--radius-md);padding:1rem 1.25rem;margin-bottom:1.5rem;color:#dc2626">
    <strong><i class="fas fa-circle-xmark"></i> Please fix the following errors:</strong>
    <ul style="margin:.5rem 0 0 1.25rem;font-size:.875rem">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-plus-circle"></i> Item Details</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <?= csrfField() ?>

            <div class="form-grid" style="gap:1.25rem">

                <!-- Basic Info -->
                <div class="form-group full-width">
                    <label for="name">Item Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control"
                        value="<?= htmlspecialchars($data['name'] ?? '') ?>"
                        placeholder="e.g. Ethanol 96%, Centrifuge Eppendorf…" required maxlength="200">
                </div>

                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select category…</option>
                        <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($data['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach (['Active','Low Stock','Expired','Depleted','Under Maintenance','Discontinued'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($data['status'] ?? 'Active') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" class="form-control"
                        value="<?= $data['quantity'] ?? '' ?>"
                        step="0.01" min="0" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="min_quantity">Minimum Quantity (Low-Stock Alert)</label>
                    <input type="number" id="min_quantity" name="min_quantity" class="form-control"
                        value="<?= $data['min_quantity'] ?? '' ?>"
                        step="0.01" min="0" placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="unit">Unit <span class="required">*</span></label>
                    <select id="unit" name="unit" class="form-control" required>
                        <option value="">Select unit…</option>
                        <?php foreach (['mL','L','mg','g','kg','units','vials','boxes','pieces','μL','μg','nmol','mmol','mol'] as $u): ?>
                        <option value="<?= $u ?>" <?= ($data['unit'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="location_id">Storage Location</label>
                    <select id="location_id" name="location_id" class="form-control">
                        <option value="">Select location…</option>
                        <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($data['location_id'] ?? 0) == $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['name']) ?> (<?= $l['temperature'] ?? $l['type'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="supplier_id">Supplier</label>
                    <select id="supplier_id" name="supplier_id" class="form-control">
                        <option value="">Select supplier…</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= ($data['supplier_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="lot_number">Lot Number</label>
                    <input type="text" id="lot_number" name="lot_number" class="form-control"
                        value="<?= htmlspecialchars($data['lot_number'] ?? '') ?>" placeholder="Lot #">
                </div>

                <div class="form-group">
                    <label for="catalog_number">Catalog Number</label>
                    <input type="text" id="catalog_number" name="catalog_number" class="form-control"
                        value="<?= htmlspecialchars($data['catalog_number'] ?? '') ?>" placeholder="Cat #">
                </div>

                <div class="form-group">
                    <label for="expiry_date">Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                        value="<?= htmlspecialchars($data['expiry_date'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="date_received">Date Received</label>
                    <input type="date" id="date_received" name="date_received" class="form-control"
                        value="<?= htmlspecialchars($data['date_received'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="form-group">
                    <label for="cost">Cost per Unit ($)</label>
                    <input type="number" id="cost" name="cost" class="form-control"
                        value="<?= $data['cost'] ?? '' ?>"
                        step="0.01" min="0" placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="hazard_class">Hazard Classification</label>
                    <select id="hazard_class" name="hazard_class" class="form-control">
                        <?php foreach (['None','Flammable','Corrosive','Toxic','Oxidizing','Explosive','Radioactive','Biological','Carcinogenic','Environmental'] as $h): ?>
                        <option value="<?= $h ?>" <?= ($data['hazard_class'] ?? 'None') === $h ? 'selected' : '' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="msds_file">MSDS / Safety Data Sheet</label>
                    <input type="file" id="msds_file" name="msds_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <span class="text-small text-muted">PDF, JPG, or PNG — max 10MB</span>
                </div>

                <div class="form-group full-width">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"
                        placeholder="Any additional notes about this item…"><?= htmlspecialchars($data['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="divider"></div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <a href="<?= APP_URL ?>/views/inventory/list.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
