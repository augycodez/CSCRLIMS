<?php
/**
 * Inventory Controller — Handles form submissions and actions
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');
$db     = Database::getInstance();

switch ($action) {
    case 'delete':
        requireRole('admin');
        $id   = sanitizeInt($_GET['id'] ?? 0);
        $csrf = $_GET['csrf'] ?? '';
        if (!verifyCsrfToken($csrf)) {
            setFlash('error', 'Invalid security token.');
            header('Location: ' . APP_URL . '/views/inventory/list.php');
            exit;
        }
        $item = $db->fetchOne("SELECT name FROM inventory_items WHERE id = ?", [$id]);
        if ($item) {
            auditLog('DELETE_ITEM', 'inventory_items', $id, $item, null);
            $db->query("DELETE FROM inventory_items WHERE id = ?", [$id]);
            setFlash('success', "Item '{$item['name']}' deleted successfully.");
        } else {
            setFlash('error', 'Item not found.');
        }
        header('Location: ' . APP_URL . '/views/inventory/list.php');
        break;

    default:
        header('Location: ' . APP_URL . '/views/inventory/list.php');
}
exit;
