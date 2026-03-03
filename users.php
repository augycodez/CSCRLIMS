<?php
define('LAB_APP', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

SessionManager::start();
SessionManager::requireRole(['admin']);

$pdo    = Database::getInstance();
$action = $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SessionManager::verifyCsrf($_POST['csrf_token'] ?? ''))
        redirect(APP_URL . '/users.php', 'error', 'CSRF check failed.');

    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] ?? 'staff';
    $password  = $_POST['password'] ?? '';

    if (!$username || !$email || !$fullName || !$password)
        redirect(APP_URL . '/users.php', 'error', 'All fields required.');

    if (strlen($password) < 8)
        redirect(APP_URL . '/users.php', 'error', 'Password must be at least 8 characters.');

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $pdo->prepare("INSERT INTO users (username,email,password_hash,full_name,role) VALUES (?,?,?,?,?)")
            ->execute([$username, $email, $hash, $fullName, $role]);
        auditLog($pdo, $_SESSION['user_id'], 'CREATE_USER', 'users', $pdo->lastInsertId());
        redirect(APP_URL . '/users.php', 'success', "User '$username' created.");
    } catch (PDOException $e) {
        redirect(APP_URL . '/users.php', 'error', 'Username or email already exists.');
    }
}

if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id !== (int)$_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        redirect(APP_URL . '/users.php', 'success', 'User status updated.');
    }
}

$users = $pdo->query("SELECT id, username, email, full_name, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$csrfToken = SessionManager::generateCsrf();
$pageTitle = 'Users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div><h1>User Management</h1><p>Manage system access and roles</p></div>
  <button class="btn btn-primary" onclick="openModal('userModal')">
    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> Add User
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="user-avatar" style="width:32px;height:32px;font-size:.68rem"><?= strtoupper(substr($u['full_name'],0,2)) ?></div>
              <div>
                <div style="font-weight:600;font-size:.875rem"><?= sanitize($u['full_name']) ?></div>
                <div class="td-code"><?= sanitize($u['username']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:.82rem"><?= sanitize($u['email']) ?></td>
          <td>
            <?php $roleColors = ['admin'=>'badge-danger','lab_manager'=>'badge-info','staff'=>'badge-secondary']; ?>
            <span class="badge <?= $roleColors[$u['role']] ?>"><?= ucfirst(str_replace('_',' ',$u['role'])) ?></span>
          </td>
          <td><?= $u['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
          <td style="font-size:.78rem;color:var(--c-text-2)"><?= $u['last_login'] ? date('M j Y, H:i', strtotime($u['last_login'])) : 'Never' ?></td>
          <td>
            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
            <a href="?action=toggle&id=<?= $u['id'] ?>" class="btn btn-outline btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="userModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header"><span class="modal-title">Add New User</span><button class="modal-close" onclick="closeModal('userModal')">×</button></div>
    <form method="POST" action="?action=add">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="modal-body">
        <div class="form-grid-2">
          <div class="form-group"><label class="form-label">Full Name <span class="req">*</span></label><input type="text" name="full_name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Username <span class="req">*</span></label><input type="text" name="username" class="form-control" required maxlength="50"></div>
        </div>
        <div class="form-group"><label class="form-label">Email <span class="req">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="form-grid-2">
          <div class="form-group"><label class="form-label">Role <span class="req">*</span></label><select name="role" class="form-control"><option value="staff">Staff</option><option value="lab_manager">Lab Manager</option><option value="admin">Admin</option></select></div>
          <div class="form-group"><label class="form-label">Password <span class="req">*</span></label><input type="password" name="password" class="form-control" required minlength="8" placeholder="Min. 8 characters"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
