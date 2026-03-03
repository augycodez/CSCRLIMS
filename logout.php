<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
SessionManager::start();
if (SessionManager::isLoggedIn()) {
    $pdo = Database::getInstance();
    auditLog($pdo, $_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
}
SessionManager::destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
