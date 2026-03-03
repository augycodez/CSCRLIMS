<?php
/**
 * View Item Details
 */
$pageTitle = 'Item Details';
require_once __DIR__ . '/../../includes/header.php';

$db   = Database::getInstance();
$id   = sanitizeInt($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/views/inventory/list.php'); exit; }

$item = $db->fetchOne(
    "SELECT ii.*, c.name AS category_name, c.color_code,
            sl.name AS location_name, sl.type AS location_type, sl.temperature,
            s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone,
            cu.full_name AS created_by_name, uu.full_name AS updated_by_name
     FROM inventory_items ii
     JOIN categories c ON ii.category_id = c.id
     LEFT JOIN storage_locations sl ON ii.location_id = sl.id
     LEFT JOIN suppliers s ON ii.supplier_id = s.id
     LEFT JOIN users cu ON ii.created_by = cu.id
     LEFT JOIN users uu ON ii.updated_by = uu.id
     WHERE ii.id = ?",
    [$id]
);
if (!$item) { setFlash('error', 'Item not found.'); header('Location: ' . APP_URL . '/views/inventory/list.php'); exit; }

$pageTitle = htmlspecialchars($item['name']);

// Recent movements for this item
$movements = $db->fetchAll(
    "SELECT sm.*, u.full_name AS user_name FROM stock_movements sm
     LEFT JOIN users u ON sm.user_id = u.id
     WHERE sm.item_id = ? ORDER BY sm.movement_date DESC LIMIT 10",
    [$id]
);

$pct = $item['min_quantity'] > 0 ? min(100, ($item['quantity'] / $item['min_quantity']) * 100) : 100;
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($item['name']) ?></h1>
        <p>
            <span class="item-code"><?= $item['item_code'] ?></span>
            &nbsp;<?= statusBadge($item['status']) ?>
            &nbsp;<?= hazardBadge($item['hazard_class']) ?>
        </p>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-secondary" data-qr data-qr-code="<?= $item['item_code'] ?>" data-qr-name="<?= htmlspecialchars($item['name']) ?>">
            <i class="fas fa-qrcode"></i> QR Code
        </button>
        <?php if (isLabManager()): ?>
        <button class="btn btn-secondary" onclick="showAdjustModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $item['quantity'] ?>)">
            <i class="fas fa-sliders"></i> Adjust Stock
        </button>
        <a href="<?= APP_URL ?>/views/inventory/edit.php?id=<?= $item['id'] ?>" class="btn btn-primary">
            <i class="fas fa-pen"></i> Edit
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/views/inventory/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;align-items:start" class="view-grid">

    <!-- Main Details -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

        <!-- Quantities -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-cubes"></i> Stock Information</h3></div>
            <div class="card-body">
                <div style="display:flex;gap:2rem;flex-wrap:wrap">
                    <div style="text-align:center">
                        <div style="font-size:2.5rem;font-weight:800;color:var(--color-primary)"><?= number_format($item['quantity'], 2) ?></div>
                        <div style="font-size:.9rem;color:var(--text-secondary)"><?= $item['unit'] ?> in stock</div>
                    </div>
                    <div style="text-align:center">
                        <div style="font-size:2.5rem;font-weight:800;color:var(--text-secondary)"><?= number_format($item['min_quantity'], 2) ?></div>
                        <div style="font-size:.9rem;color:var(--text-secondary)">Minimum threshold</div>
                    </div>
                    <?php if ($item['cost']): ?>
                    <div style="text-align:center">
                        <div style="font-size:2.5rem;font-weight:800;color:var(--color-success)">$<?= number_format($item['quantity'] * $item['cost'], 2) ?></div>
                        <div style="font-size:.9rem;color:var(--text-secondary)">Total value ($<?= number_format($item['cost'],2) ?>/<?= $item['unit'] ?>)</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($item['min_quantity'] > 0): ?>
                <div style="margin-top:1.25rem">
                    <div style="display:flex;justify-content:space-between;font-size:.8rem;color:var(--text-muted);margin-bottom:.4rem">
                        <span>Stock Level</span>
                        <span><?= round($pct) ?>% of minimum</span>
                    </div>
                    <div class="stock-bar" style="height:10px">
                        <div class="stock-bar-fill <?= $pct <= 20 ? 'danger' : ($pct <= 50 ? 'warn' : '') ?>"
                            style="width:<?= min(100,$pct) ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Properties -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-list-ul"></i> Item Properties</h3></div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Category</div>
                        <div class="detail-value">
                            <span class="badge" style="background:<?= $item['color_code'] ?>22;color:<?= $item['color_code'] ?>;border:1px solid <?= $item['color_code'] ?>44">
                                <?= htmlspecialchars($item['category_name']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Hazard Class</div>
                        <div class="detail-value"><?= hazardBadge($item['hazard_class']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Lot Number</div>
                        <div class="detail-value mono"><?= htmlspecialchars($item['lot_number'] ?: '—') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Catalog Number</div>
                        <div class="detail-value mono"><?= htmlspecialchars($item['catalog_number'] ?: '—') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Expiry Date</div>
                        <div class="detail-value <?= ($item['expiry_date'] && $item['expiry_date'] < date('Y-m-d')) ? 'text-danger' : '' ?>">
                            <?= $item['expiry_date'] ? date('M j, Y', strtotime($item['expiry_date'])) : '—' ?>
                        </div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date Received</div>
                        <div class="detail-value"><?= $item['date_received'] ? date('M j, Y', strtotime($item['date_received'])) : '—' ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Cost per Unit</div>
                        <div class="detail-value"><?= $item['cost'] ? '$' . number_format($item['cost'], 2) : '—' ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Item Code</div>
                        <div class="detail-value mono"><?= $item['item_code'] ?></div>
                    </div>
                </div>
                <?php if ($item['notes']): ?>
                <div style="margin-top:1rem;padding:.85rem;background:var(--bg-app);border-radius:var(--radius-sm)">
                    <div class="detail-label" style="margin-bottom:.4rem">Notes</div>
                    <p style="font-size:.875rem;color:var(--text-primary);line-height:1.6"><?= nl2br(htmlspecialchars($item['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stock Movements -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-arrows-rotate"></i> Movement History</h3>
                <a href="<?= APP_URL ?>/views/inventory/movements.php?item_id=<?= $id ?>" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="table-wrapper">
            <table>
                <thead><tr><th>Type</th><th>Qty</th><th>Before</th><th>After</th><th>Reference</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (empty($movements)): ?>
                <tr><td colspan="7"><div class="empty-state" style="padding:1.5rem"><i class="fas fa-inbox"></i><p>No movements yet</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td><span class="movement-badge movement-<?= $m['type'] ?>"><?= $m['type'] ?></span></td>
                    <td style="font-weight:600"><?= number_format($m['quantity'],2) ?></td>
                    <td class="text-muted"><?= number_format($m['quantity_before'],2) ?></td>
                    <td style="color:var(--color-primary);font-weight:600"><?= number_format($m['quantity_after'],2) ?></td>
                    <td class="text-mono text-small"><?= htmlspecialchars($m['reference'] ?? '—') ?></td>
                    <td class="text-small"><?= htmlspecialchars($m['user_name'] ?? '—') ?></td>
                    <td class="text-muted text-small"><?= date('M j, Y H:i', strtotime($m['movement_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Sidebar info -->
    <div style="display:flex;flex-direction:column;gap:1rem">

        <!-- Storage Location -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-location-dot"></i> Storage</h3></div>
            <div class="card-body">
                <?php if ($item['location_name']): ?>
                <div style="font-weight:700;font-size:1rem;margin-bottom:.5rem"><?= htmlspecialchars($item['location_name']) ?></div>
                <?php if ($item['location_type']): ?>
                <span class="badge badge-info"><?= $item['location_type'] ?></span>
                <?php endif; ?>
                <?php if ($item['temperature']): ?>
                <div style="margin-top:.75rem;font-size:.9rem;color:var(--text-secondary)">
                    <i class="fas fa-thermometer-half"></i> <?= htmlspecialchars($item['temperature']) ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-muted">No location assigned</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Supplier -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-truck"></i> Supplier</h3></div>
            <div class="card-body">
                <?php if ($item['supplier_name']): ?>
                <div style="font-weight:700;margin-bottom:.5rem"><?= htmlspecialchars($item['supplier_name']) ?></div>
                <?php if ($item['supplier_email']): ?>
                <div class="text-small"><i class="fas fa-envelope" style="width:14px;color:var(--text-muted)"></i> <?= htmlspecialchars($item['supplier_email']) ?></div>
                <?php endif; ?>
                <?php if ($item['supplier_phone']): ?>
                <div class="text-small mt-1"><i class="fas fa-phone" style="width:14px;color:var(--text-muted)"></i> <?= htmlspecialchars($item['supplier_phone']) ?></div>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-muted">No supplier assigned</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- MSDS -->
        <?php if ($item['msds_file']): ?>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-file-shield"></i> MSDS</h3></div>
            <div class="card-body">
                <a href="<?= UPLOADS_URL ?>msds/<?= htmlspecialchars($item['msds_file']) ?>" target="_blank" class="btn btn-outline-primary" style="width:100%;justify-content:center">
                    <i class="fas fa-file-arrow-down"></i> Download MSDS
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Metadata -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-circle-info"></i> Record Info</h3></div>
            <div class="card-body">
                <div class="detail-grid" style="grid-template-columns:1fr">
                    <div class="detail-item">
                        <div class="detail-label">Created by</div>
                        <div class="detail-value text-small"><?= htmlspecialchars($item['created_by_name'] ?? '—') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Created at</div>
                        <div class="detail-value text-small"><?= date('M j, Y H:i', strtotime($item['created_at'])) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Last updated</div>
                        <div class="detail-value text-small"><?= date('M j, Y H:i', strtotime($item['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code Preview -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-qrcode"></i> QR Code</h3></div>
            <div class="card-body" style="text-align:center">
                <div id="itemQR" style="display:inline-block"></div>
                <p style="font-family:var(--font-mono);font-size:.75rem;color:var(--color-primary);margin-top:.5rem"><?= $item['item_code'] ?></p>
                <button class="btn btn-sm btn-secondary" onclick="downloadQR()" style="width:100%;justify-content:center;margin-top:.5rem">
                    <i class="fas fa-download"></i> Download QR
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media(max-width:900px){.view-grid{grid-template-columns:1fr!important}}
</style>
<script>
window.APP_URL = '<?= APP_URL ?>';
document.addEventListener('DOMContentLoaded', () => {
    if (typeof QRCode !== 'undefined') {
        new QRCode(document.getElementById('itemQR'), {
            text: 'LABINVENT:<?= $item['item_code'] ?>:<?= htmlspecialchars(addslashes($item['name'])) ?>',
            width: 140, height: 140,
            colorDark: '#0f172a', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});
// Override downloadQR to use inline QR
function downloadQR() {
    const canvas = document.querySelector('#itemQR canvas');
    if (!canvas) return;
    const a = document.createElement('a');
    a.download = '<?= $item['item_code'] ?>_qr.png';
    a.href = canvas.toDataURL('image/png');
    a.click();
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
