<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();

// Redirect if already logged in
if (SessionManager::isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
$csrfToken = SessionManager::generateCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionManager::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, role, is_active FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];

                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                auditLog($pdo, $user['id'], 'LOGIN', 'users', $user['id']);

                header('Location: ' . APP_URL . '/dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials or account disabled.';
                // Slight delay to prevent brute force
                sleep(1);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    body { background: linear-gradient(135deg, #0A1628 0%, #0D3D4A 50%, #0A2535 100%); }
    .login-page { min-height: 100vh; display: grid; place-items: center; padding: 20px; }
    .login-card { background: var(--c-surface); border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 30px 80px rgba(0,0,0,.3); }
    .demo-creds { background: var(--c-surface2); border: 1px solid var(--c-border); border-radius: var(--radius-sm); padding: 14px; margin-top: 20px; font-size:.78rem; }
    .demo-creds strong { display: block; margin-bottom: 6px; color: var(--c-text-2); }
    .demo-creds span { color: var(--c-text-3); }
    .error-msg { background: #FEE2E2; border: 1px solid #FECACA; border-radius: var(--radius-sm); padding: 10px 14px; color: #DC2626; font-size:.855rem; margin-bottom:16px; }
  </style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo" style="margin-bottom:28px">
      <svg viewBox="0 0 40 40" fill="none" style="width:44px;height:44px;color:var(--c-primary)">
        <circle cx="20" cy="20" r="18" stroke="currentColor" stroke-width="2"/>
        <path d="M14 28V20L10 12h20l-4 8v8H14z" fill="currentColor" opacity=".3"/>
        <path d="M14 28V20L10 12h20l-4 8v8H14z" stroke="currentColor" stroke-width="1.5"/>
        <circle cx="20" cy="24" r="3" fill="currentColor"/>
      </svg>
      <div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--c-primary);letter-spacing:-.03em"><?= APP_NAME ?></div>
        <div style="font-size:.75rem;color:var(--c-text-3)">Laboratory Inventory System v<?= APP_VERSION ?></div>
      </div>
    </div>

    <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:4px">Welcome back</h2>
    <p style="font-size:.855rem;color:var(--c-text-2);margin-bottom:24px">Sign in to your account to continue</p>

    <?php if ($error): ?>
    <div class="error-msg"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="form-group">
        <label class="form-label">Username or Email</label>
        <input type="text" name="username" class="form-control" placeholder="admin" value="<?= sanitize($_POST['username'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">Sign In</button>
    </form>

    <div class="demo-creds">
      <strong>Demo Accounts (password: <code>password</code>)</strong>
      <div style="display:grid;gap:4px">
        <span>👑 <strong>admin</strong> — Full access</span>
        <span>🔬 <strong>labmanager</strong> — Add/Edit items</span>
        <span>👤 <strong>staff1</strong> — View only</span>
      </div>
    </div>
  </div>
</div>
<script>
  const saved = localStorage.getItem('labtrack_theme');
  if (saved) document.documentElement.setAttribute('data-theme', saved);
</script>
</body>
</html>
