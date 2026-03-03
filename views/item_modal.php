<?php
/**
 * Add/Edit Item Modal — reused across pages
 */
defined('LAB_APP') or die('Direct access not permitted.');

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$locations  = $pdo->query("SELECT id, name FROM storage_locations ORDER BY name")->fetchAll();
$suppliers  = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
?>

<div class="modal-overlay" id="itemModal">
  <div class="modal" style="max-width:820px">
    <div class="modal-header">
      <span class="modal-title" id="modal-title">Add New Item</span>
      <button class="modal-close" onclick="closeModal('itemModal')">×</button>
    </div>
    <form method="POST" action="inventory.php?action=add" enctype="multipart/form-data" id="itemForm">
      <input type="hidden" name="csrf_token" value="<?= SessionManager::generateCsrf() ?>">
      <input type="hidden" name="edit_id" id="edit_id_field">

      <div class="modal-body">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Item Name <span class="req">*</span></label>
            <input type="text" name="item_name" class="form-control" placeholder="e.g. Ethanol 96%" required maxlength="200">
          </div>
          <div class="form-group">
            <label class="form-label">Category <span class="req">*</span></label>
            <select name="category_id" class="form-control" required>
              <option value="">— Select Category —</option>
              <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-3">
          <div class="form-group">
            <label class="form-label">Quantity <span class="req">*</span></label>
            <input type="number" name="quantity" class="form-control" placeholder="0" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min. Quantity <span class="req">*</span></label>
            <input type="number" name="min_quantity" class="form-control" placeholder="0" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Unit <span class="req">*</span></label>
            <select name="unit" class="form-control" required>
              <?php foreach (['mL','L','mg','g','kg','units','boxes','vials','plates','rolls'] as $u): ?>
              <option value="<?= $u ?>"><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">Storage Location <span class="req">*</span></label>
            <select name="storage_location_id" class="form-control" required>
              <option value="">— Select Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= sanitize($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-control">
              <option value="">— Select Supplier —</option>
              <?php foreach ($suppliers as $sup): ?>
              <option value="<?= $sup['id'] ?>"><?= sanitize($sup['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid-3">
          <div class="form-group">
            <label class="form-label">Lot Number</label>
            <input type="text" name="lot_number" class="form-control" placeholder="LOT-2024-001" maxlength="100">
          </div>
          <div class="form-group">
            <label class="form-label">Expiry Date</label>
            <input type="date" name="expiry_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Date Received</label>
            <input type="date" name="date_received" class="form-control">
          </div>
        </div>

        <div class="form-grid-3">
          <div class="form-group">
            <label class="form-label">Cost Per Unit ($)</label>
            <input type="number" name="cost_per_unit" class="form-control" placeholder="0.00" min="0" step="0.01">
          </div>
          <div class="form-group">
            <label class="form-label">Hazard Class</label>
            <select name="hazard_class" class="form-control">
              <?php foreach (['None','Flammable','Corrosive','Toxic','Oxidizer','Biohazard','Radioactive','Explosive','Irritant'] as $h): ?>
              <option value="<?= $h ?>"><?= $h ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="low_stock">Low Stock</option>
              <option value="expired">Expired</option>
              <option value="under_maintenance">Under Maintenance</option>
              <option value="discontinued">Discontinued</option>
            </select>
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label">MSDS / Safety Data Sheet</label>
            <input type="file" name="msds_file" class="form-control" accept=".pdf,.doc,.docx">
            <div class="form-hint">PDF, DOC or DOCX (max 10MB)</div>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Additional information..."></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('itemModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
          Save Item
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Sync hidden field id
document.getElementById('itemForm').addEventListener('submit', function() {
  const hiddenId = document.getElementById('edit_id_field');
  const namedId = document.querySelector('[name="edit_id"]');
  if (hiddenId && namedId) hiddenId.value = namedId.value;
});
// Make sure hidden edit_id field has name
document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('edit_id_field');
  if (f) f.name = 'edit_id';
});
</script>
