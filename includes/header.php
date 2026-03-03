<?php
/**
 * Global HTML Header & Navigation
 */
defined('LAB_APP') or die('Direct access not permitted.');
$flash = SessionManager::getFlash();
$pdo = Database::getInstance();

// Get unread alert count
$alertCount = $pdo->query("SELECT COUNT(*) FROM alerts WHERE is_read = 0")->fetchColumn();

// Page title
$pageTitle = ($pageTitle ?? 'Dashboard') . ' — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" href="<?= APP_URL ?>/assets/images/favicon.svg" type="image/svg+xml">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <svg class="logo-icon" viewBox="0 0 40 40" fill="none">
      <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2"/>
      <path d="M14 28V20L10 12h20l-4 8v8H14z" fill="currentColor" opacity="0.3"/>
      <path d="M14 28V20L10 12h20l-4 8v8H14z" stroke="currentColor" stroke-width="1.5"/>
      <circle cx="20" cy="24" r="3" fill="currentColor"/>
    </svg>
    <div>
      <span class="logo-text"><?= APP_NAME ?></span>
      <span class="logo-sub">Laboratory System</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="<?= APP_URL ?>/dashboard.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='dashboard.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      Dashboard
    </a>
    <a href="<?= APP_URL ?>/inventory.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='inventory.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.86.18-1a3 3 0 0 0-6 0c0 .14.11.56.18 1H10c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h10c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-7-1a1 1 0 0 1 1 1c0 .14-.11.56-.18 1h-1.64C12.11 6.56 12 6.14 12 6a1 1 0 0 1 1-1zm-3 3h10v11H10V8z"/></svg>
      Inventory
    </a>
    <a href="<?= APP_URL ?>/alerts.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='alerts.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
      Alerts
      <?php if ($alertCount > 0): ?>
        <span class="badge-count"><?= $alertCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/movements.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='movements.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 15h12v-2H3v2zm0-8v2h18V7H3zm0 12h6v-2H3v2zM21 12l-4-4v3H11v2h6v3l4-4z"/></svg>
      Stock Movements
    </a>
    <div class="nav-section-label">Management</div>
    <a href="<?= APP_URL ?>/reports.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='reports.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      Reports
    </a>
    <?php if (in_array($_SESSION['user_role'] ?? '', ['admin'])): ?>
    <a href="<?= APP_URL ?>/users.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='users.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      Users
    </a>
    <a href="<?= APP_URL ?>/audit.php" class="nav-link <?= (basename($_SERVER['PHP_SELF'])=='audit.php')?'active':'' ?>">
      <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
      Audit Log
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)) ?></div>
    <div class="user-info">
      <span class="user-name"><?= sanitize($_SESSION['full_name'] ?? 'User') ?></span>
      <span class="user-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'] ?? '')) ?></span>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">
      <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
    </a>
  </div>
</aside>

<!-- Main Content Wrapper -->
<div class="main-wrapper">
  <!-- Top Bar -->
  <header class="topbar">
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
      <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
    <div class="topbar-title"><?= $pageTitle ?></div>
    <div class="topbar-actions">
      <button class="icon-btn" onclick="toggleDark()" title="Toggle dark mode" id="darkToggle">
        <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36-.98 1.37-2.58 2.26-4.4 2.26-2.98 0-5.4-2.42-5.4-5.4 0-1.81.89-3.42 2.26-4.4-.44-.06-.9-.1-1.36-.1z"/></svg>
      </button>
      <a href="<?= APP_URL ?>/alerts.php" class="icon-btn" title="Alerts">
        <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
        <?php if ($alertCount > 0): ?><span class="badge-count sm"><?= $alertCount ?></span><?php endif; ?>
      </a>
    </div>
  </header>

  <!-- Flash Toast -->
  <?php if ($flash): ?>
  <div class="toast toast-<?= $flash['type'] ?>" id="flashToast">
    <span><?= sanitize($flash['msg']) ?></span>
    <button onclick="this.parentElement.remove()">×</button>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <main class="main-content">
