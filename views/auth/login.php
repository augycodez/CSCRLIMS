<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/views/dashboard/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $db   = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
                [$username, $username]
            );
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['last_regeneration'] = time();

                // Update last login
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

                // Audit log
                $db->query(
                    "INSERT INTO audit_log (user_id, action, table_name, record_id, new_values, ip_address, user_agent) VALUES (?, 'LOGIN', 'users', ?, ?, ?, ?)",
                    [$user['id'], $user['id'], json_encode(['status' => 'success']), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
                );

                header('Location: ' . APP_URL . '/views/dashboard/index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'System error. Please try again.';
            error_log($e->getMessage());
        }
    } else {
        $error = 'Please enter your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-page">
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="brand-icon"><i class="fas fa-flask"></i></div>
            <div>
                <span class="brand-name"><?= APP_NAME ?></span>
                <span class="brand-sub" style="display:block;font-size:.68rem;color:#64748b">Laboratory System</span>
            </div>
        </div>
        <h1>Welcome back</h1>
        <p>Sign in to access the laboratory inventory system</p>

        <?php if ($error): ?>
        <div class="auth-error"><i class="fas fa-circle-xmark" style="margin-right:.4rem"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" style="display:flex;flex-direction:column;gap:1rem">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" class="form-control"
                    placeholder="admin" required autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                    placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:.75rem;margin-top:.5rem">
                <i class="fas fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,.08)">
            <p style="color:#475569;font-size:.78rem;text-align:center">
                <strong style="color:#64748b">Demo Credentials:</strong><br>
                admin / password &nbsp;|&nbsp; drjohnson / password &nbsp;|&nbsp; msmith / password
            </p>
        </div>
    </div>
</div>
<script>
// Apply stored theme
const t = localStorage.getItem('labinvent_theme');
if (t) document.documentElement.setAttribute('data-theme', t);
</script>
</body>
</html>
