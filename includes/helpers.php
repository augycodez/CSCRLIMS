<?php
/**
 * Audit Log & Alert Helpers
 */

require_once __DIR__ . '/../config/database.php';

function auditLog(string $action, ?string $table = null, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): void {
    try {
        $db = Database::getInstance();
        $user = getCurrentUser();
        $db->query(
            "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$user['id'] ?? null, $action, $table, $recordId, $oldValues ? json_encode($oldValues) : null, $newValues ? json_encode($newValues) : null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]
        );
    } catch (Exception $e) { error_log('Audit log error: ' . $e->getMessage()); }
}

function refreshAlerts(): void {
    try {
        $db = Database::getInstance();
        $lowStock = $db->fetchAll("SELECT id, name, quantity, min_quantity FROM inventory_items WHERE quantity <= min_quantity AND min_quantity > 0 AND status != 'Depleted'");
        foreach ($lowStock as $item) {
            $exists = $db->fetchOne("SELECT id FROM alerts WHERE item_id = ? AND type = 'low_stock' AND is_dismissed = 0", [$item['id']]);
            if (!$exists) {
                $db->query("INSERT INTO alerts (item_id, type, message) VALUES (?, 'low_stock', ?)", [$item['id'], "{$item['name']} is below minimum stock level ({$item['quantity']} remaining, minimum: {$item['min_quantity']})"]);
                $db->query("UPDATE inventory_items SET status = 'Low Stock' WHERE id = ?", [$item['id']]);
            }
        }
        $expiring = $db->fetchAll("SELECT id, name, expiry_date FROM inventory_items WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) AND status = 'Active'", [EXPIRY_WARNING_DAYS]);
        foreach ($expiring as $item) {
            $exists = $db->fetchOne("SELECT id FROM alerts WHERE item_id = ? AND type = 'expiry' AND is_dismissed = 0", [$item['id']]);
            if (!$exists) {
                $db->query("INSERT INTO alerts (item_id, type, message) VALUES (?, 'expiry', ?)", [$item['id'], "{$item['name']} will expire within 30 days (Expiry: {$item['expiry_date']})"]);
            }
        }
        $db->query("UPDATE inventory_items SET status = 'Expired' WHERE expiry_date < CURDATE() AND status IN ('Active','Low Stock')");
    } catch (Exception $e) { error_log('Alert refresh error: ' . $e->getMessage()); }
}

function getUnreadAlertCount(): int {
    try {
        $db = Database::getInstance();
        return (int) $db->fetchColumn("SELECT COUNT(*) FROM alerts WHERE is_read = 0 AND is_dismissed = 0");
    } catch (Exception $e) { return 0; }
}

function generateItemCode(): string {
    $db = Database::getInstance();
    $last = $db->fetchColumn("SELECT MAX(CAST(SUBSTRING(item_code, 5) AS UNSIGNED)) FROM inventory_items");
    $next = ($last ?? 0) + 1;
    return 'LAB-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function logStockMovement(int $itemId, string $type, float $quantity, float $before, float $after, ?string $reference = null, ?string $notes = null): void {
    $db = Database::getInstance();
    $user = getCurrentUser();
    $db->query("INSERT INTO stock_movements (item_id, user_id, type, quantity, quantity_before, quantity_after, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$itemId, $user['id'], $type, $quantity, $before, $after, $reference, $notes]);
}

function handleMsdsUpload(array $file, string $itemCode): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_MSDS_TYPES)) return false;
    $ext = match($mimeType) { 'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', default => false };
    if (!$ext) return false;
    $filename  = $itemCode . '_msds_' . time() . '.' . $ext;
    $uploadDir = APP_ROOT . '/assets/uploads/msds/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) return $filename;
    return false;
}

function statusBadge(string $status): string {
    $classes = ['Active' => 'badge-success', 'Expired' => 'badge-danger', 'Low Stock' => 'badge-warning', 'Depleted' => 'badge-dark', 'Under Maintenance' => 'badge-info', 'Discontinued' => 'badge-secondary'];
    $cls = $classes[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($status) . '</span>';
}

function hazardBadge(?string $hazard): string {
    if (!$hazard || $hazard === 'None') return '<span class="badge badge-secondary">None</span>';
    $colors = ['Flammable' => '#f97316', 'Corrosive' => '#ef4444', 'Toxic' => '#8b5cf6', 'Oxidizing' => '#eab308', 'Explosive' => '#dc2626', 'Radioactive' => '#84cc16', 'Biological' => '#10b981', 'Carcinogenic' => '#ec4899', 'Environmental' => '#06b6d4'];
    $color = $colors[$hazard] ?? '#6b7280';
    return '<span class="badge" style="background:' . $color . ';color:#fff">' . htmlspecialchars($hazard) . '</span>';
}
