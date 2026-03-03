<?php
/**
 * Session Management & Authentication Helpers
 */

require_once __DIR__ . '/../config/config.php';

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false, // Set true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Check if user is logged in, redirect if not
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/views/auth/login.php');
        exit;
    }
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function getCurrentUser(): array {
    return [
        'id'        => $_SESSION['user_id']   ?? null,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'staff',
        'email'     => $_SESSION['email']     ?? '',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function isLabManager(): bool {
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'lab_manager']);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
    }
}

// ─── CSRF Protection ───────────────────────────────────────────

function generateCsrfToken(): string {
    $token = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    $_SESSION['csrf_token'] = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

function getCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        return generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRY) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}

// ─── Flash Messages ────────────────────────────────────────────

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Input Sanitization ────────────────────────────────────────

function sanitize(mixed $input): string {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function sanitizeInt(mixed $input): int {
    return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

function sanitizeFloat(mixed $input): float {
    return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}
