<?php
define('LAB_APP', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session.php';
SessionManager::start();
header('Content-Type: application/json');
if (!SessionManager::isLoggedIn()) { echo json_encode(['error'=>'Unauthorized']); exit; }
$pdo = Database::getInstance();
if (isset($_GET['all'])) {
    $pdo->exec("UPDATE alerts SET is_read=1");
    echo json_encode(['success'=>true]);
} elseif (isset($_GET['id'])) {
    $pdo->prepare("UPDATE alerts SET is_read=1 WHERE id=?")->execute([(int)$_GET['id']]);
    echo json_encode(['success'=>true]);
} else { echo json_encode(['error'=>'No ID']); }
