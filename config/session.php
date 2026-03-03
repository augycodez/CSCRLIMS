<?php
/**
 * Session Management
 */

defined('LAB_APP') or die('Direct access not permitted.');

class SessionManager {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false, // set true in production with HTTPS
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }

    public static function requireAuth(): void {
        if (!self::isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public static function requireRole(array $roles): void {
        self::requireAuth();
        if (!in_array($_SESSION['user_role'], $roles)) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Access denied. Insufficient permissions.'];
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        }
    }

    public static function generateCsrf(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function setFlash(string $type, string $msg): void {
        $_SESSION['toast'] = ['type' => $type, 'msg' => $msg];
    }

    public static function getFlash(): ?array {
        $flash = $_SESSION['toast'] ?? null;
        unset($_SESSION['toast']);
        return $flash;
    }

    public static function destroy(): void {
        session_unset();
        session_destroy();
    }
}
