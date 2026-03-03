<?php
/**
 * Global utility functions
 */
defined('LAB_APP') or die('Direct access not permitted.');

/**
 * Sanitize input string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect with optional flash message
 */
function redirect(string $url, string $type = '', string $msg = ''): void {
    if ($type && $msg) {
        SessionManager::setFlash($type, $msg);
    }
    header("Location: $url");
    exit;
}

/**
 * Generate a unique item code based on category prefix
 */
function generateItemCode(PDO $pdo, string $categoryName): string {
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $categoryName), 0, 4));
    $stmt = $pdo->prepare("SELECT item_code FROM inventory_items WHERE item_code LIKE ? ORDER BY item_code DESC LIMIT 1");
    $stmt->execute([$prefix . '-%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int)substr(strrchr($last, '-'), 1) + 1 : 1;
    return $prefix . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
}

/**
 * Log audit entry
 */
function auditLog(PDO $pdo, ?int $userId, string $action, ?string $table = null, ?int $recordId = null, ?array $oldVals = null, ?array $newVals = null): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $table,
            $recordId,
            $oldVals ? json_encode($oldVals) : null,
            $newVals ? json_encode($newVals) : null,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (PDOException $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

/**
 * Update item status based on quantity and expiry
 */
function updateItemStatus(PDO $pdo, int $itemId): void {
    $stmt = $pdo->prepare("SELECT quantity, min_quantity, expiry_date, status FROM inventory_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) return;

    $newStatus = $item['status'];
    if ($item['expiry_date'] && strtotime($item['expiry_date']) < time()) {
        $newStatus = 'expired';
    } elseif ($item['quantity'] <= $item['min_quantity']) {
        $newStatus = 'low_stock';
    } elseif ($item['status'] === 'low_stock' || $item['status'] === 'expired') {
        $newStatus = 'active';
    }

    if ($newStatus !== $item['status']) {
        $pdo->prepare("UPDATE inventory_items SET status = ? WHERE id = ?")->execute([$newStatus, $itemId]);
    }
}

/**
 * Generate/refresh alerts for all items
 */
function refreshAlerts(PDO $pdo): void {
    // Clear old unread alerts and regenerate
    $items = $pdo->query("SELECT id, item_name, quantity, min_quantity, expiry_date, status FROM inventory_items WHERE status != 'discontinued'")->fetchAll();
    $today = new DateTime();

    foreach ($items as $item) {
        // Low stock alert
        if ($item['quantity'] <= $item['min_quantity']) {
            $exists = $pdo->prepare("SELECT id FROM alerts WHERE item_id=? AND alert_type='low_stock' AND is_read=0");
            $exists->execute([$item['id']]);
            if (!$exists->fetchColumn()) {
                $pdo->prepare("INSERT INTO alerts (item_id, alert_type, message) VALUES (?, 'low_stock', ?)")
                    ->execute([$item['id'], "{$item['item_name']}: Low stock ({$item['quantity']} remaining, min: {$item['min_quantity']})"]);
            }
        }
        // Expiry alerts
        if ($item['expiry_date']) {
            $expiry = new DateTime($item['expiry_date']);
            $diff = $today->diff($expiry)->days * ($expiry > $today ? 1 : -1);
            if ($diff < 0) {
                $exists = $pdo->prepare("SELECT id FROM alerts WHERE item_id=? AND alert_type='expired' AND is_read=0");
                $exists->execute([$item['id']]);
                if (!$exists->fetchColumn()) {
                    $pdo->prepare("INSERT INTO alerts (item_id, alert_type, message) VALUES (?, 'expired', ?)")
                        ->execute([$item['id'], "{$item['item_name']} has EXPIRED on {$item['expiry_date']}"]);
                }
            } elseif ($diff <= EXPIRY_ALERT_DAYS) {
                $exists = $pdo->prepare("SELECT id FROM alerts WHERE item_id=? AND alert_type='expiry' AND is_read=0");
                $exists->execute([$item['id']]);
                if (!$exists->fetchColumn()) {
                    $pdo->prepare("INSERT INTO alerts (item_id, alert_type, message) VALUES (?, 'expiry', ?)")
                        ->execute([$item['id'], "{$item['item_name']} expires in {$diff} days ({$item['expiry_date']})"]);
                }
            }
        }
    }
}

/**
 * Format file size
 */
function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

/**
 * Status badge HTML
 */
function statusBadge(string $status): string {
    $map = [
        'active'            => ['Active', 'badge-success'],
        'expired'           => ['Expired', 'badge-danger'],
        'low_stock'         => ['Low Stock', 'badge-warning'],
        'discontinued'      => ['Discontinued', 'badge-secondary'],
        'under_maintenance' => ['Maintenance', 'badge-info'],
    ];
    [$label, $cls] = $map[$status] ?? ['Unknown', 'badge-secondary'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

/**
 * Hazard icon
 */
function hazardIcon(string $hazard): string {
    $icons = [
        'Flammable'   => '🔥',
        'Corrosive'   => '⚗️',
        'Toxic'       => '☠️',
        'Oxidizer'    => '🔆',
        'Biohazard'   => '☣️',
        'Radioactive' => '☢️',
        'Explosive'   => '💥',
        'Irritant'    => '⚠️',
        'None'        => '',
    ];
    return $icons[$hazard] ?? '';
}

/**
 * Paginator helper
 */
function paginate(int $total, int $page, int $perPage): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => ($page - 1) * $perPage,
    ];
}
