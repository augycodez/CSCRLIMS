<?php
define('LAB_APP', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session.php';
require_once dirname(__DIR__) . '/includes/functions.php';
SessionManager::start();
header('Content-Type: application/json');
if (!SessionManager::isLoggedIn()) { echo json_encode(['error'=>'Unauthorized']); exit; }
$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['error'=>'Invalid ID']); exit; }
$pdo = Database::getInstance();
$stmt = $pdo->prepare("SELECT ii.*, c.name as category_name, sl.name as location_name, s.name as supplier_name FROM inventory_items ii JOIN categories c ON ii.category_id = c.id JOIN storage_locations sl ON ii.storage_location_id = sl.id LEFT JOIN suppliers s ON ii.supplier_id = s.id WHERE ii.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { echo json_encode(['error'=>'Item not found']); exit; }
echo json_encode($item);
