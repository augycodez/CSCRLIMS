<?php
/**
 * Stock Movement Controller
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
requireRole('admin', 'lab_manager');

$action = sanitize($_POST['action'] ?? '');
$db     = Database::getInstance();

if ($action === 'adjust') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        header('Location: ' . APP_URL . '/views/inventory/list.php');
        exit;
    }

    $itemId   = sanitizeInt($_POST['item_id'] ?? 0);
    $type     = sanitize($_POST['type']     ?? '');
    $quantity = sanitizeFloat($_POST['quantity'] ?? 0);
    $ref      = sanitize($_POST['reference'] ?? '');
    $notes    = sanitize($_POST['notes']    ?? '');

    if (!$itemId || $quantity <= 0) {
        setFlash('error', 'Invalid adjustment data.');
        header('Location: ' . APP_URL . '/views/inventory/list.php');
        exit;
    }

    $item = $db->fetchOne("SELECT id, name, quantity, status FROM inventory_items WHERE id = ?", [$itemId]);
    if (!$item) {
        setFlash('error', 'Item not found.');
        header('Location: ' . APP_URL . '/views/inventory/list.php');
        exit;
    }

    $before = (float) $item['quantity'];
    $after  = $before;

    switch ($type) {
        case 'IN':
            $after = $before + $quantity;
            break;
        case 'OUT':
        case 'DISPOSED':
            $after = max(0, $before - $quantity);
            break;
        case 'ADJUSTMENT':
            $after = $quantity; // Set absolute value
            break;
        case 'RETURNED':
            $after = $before + $quantity;
            break;
    }

    try {
        $db->beginTransaction();

        // Update item quantity
        $newStatus = $item['status'];
        $minQty = (float) $db->fetchColumn("SELECT min_quantity FROM inventory_items WHERE id = ?", [$itemId]);
        if ($after === 0.0) $newStatus = 'Depleted';
        elseif ($minQty > 0 && $after <= $minQty) $newStatus = 'Low Stock';
        elseif ($newStatus === 'Depleted' || $newStatus === 'Low Stock') $newStatus = 'Active';

        $db->query("UPDATE inventory_items SET quantity = ?, status = ? WHERE id = ?", [$after, $newStatus, $itemId]);
        logStockMovement($itemId, $type, $quantity, $before, $after, $ref, $notes);
        auditLog('STOCK_MOVEMENT', 'stock_movements', $itemId, ['quantity' => $before], ['quantity' => $after, 'type' => $type]);

        $db->commit();
        setFlash('success', "Stock adjusted for '{$item['name']}'. New quantity: " . number_format($after, 2));
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Adjustment failed: ' . $e->getMessage());
    }
}

header('Location: ' . APP_URL . '/views/inventory/list.php');
exit;
