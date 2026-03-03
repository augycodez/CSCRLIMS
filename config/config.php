<?php
/**
 * Lab Inventory System - Application Configuration
 */

// Prevent direct access
defined('LAB_APP') or die('Direct access not permitted.');

// ─── Database ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'lab_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ─── App Settings ────────────────────────────────────────────
define('APP_NAME', 'LabTrack Pro');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/labinventory');
define('APP_ROOT', dirname(__DIR__));

// ─── File Upload ─────────────────────────────────────────────
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/msds/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/msds/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx']);

// ─── Session ─────────────────────────────────────────────────
define('SESSION_NAME', 'LABTRACK_SESS');
define('SESSION_LIFETIME', 3600); // 1 hour

// ─── Pagination ──────────────────────────────────────────────
define('ITEMS_PER_PAGE', 20);

// ─── Alert Thresholds ────────────────────────────────────────
define('EXPIRY_ALERT_DAYS', 30); // Warn 30 days before expiry

// ─── Timezone ────────────────────────────────────────────────
date_default_timezone_set('UTC');

// ─── Error Reporting (set to 0 in production) ────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);
