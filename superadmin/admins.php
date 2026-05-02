sd<?php
/**
 * Super Admin - Admin Users Management
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Admin Users';
generateCsrfToken();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $username = sanitize($_POST['username'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $fullName = sanitize($_POST['full_name'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize($_POST['role'] ?? 'admin');
            
            if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
                $error = 'All fields are required.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } else {
                $existing = DB::queryOne("SELECT id FROM super_admins WHERE username = ? OR email = ?", [$username, $email]);
                if ($existing) {
                    $error = 'Username or email already exists.';
                } else {
                    DB::insert('super_admins', [
                        'username' => $username,
                        'email' => $email,
                        'full_name' => $fullName,
                        'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                        'role' => $role,
                        'status' => 'active'
                    ]);
                    
                    logAdminActivity($_SESSION['admin_id'], 'create_admin', "Created admin: $fullName ($username)");
                    $success = 'Admin user created successfully.';
                }
            }
            break;
            
        case 'update_status':
            $adminId = intval($_POST['admin_id'] ?? 0);
            $status = sanitize($_POST['status'] ?? '');
            
            if ($adminId === intval($_SESSION['admin_id'])) {
                $error = 'You cannot change your own status.';
            } elseif ($adminId > 0 && in_array($status, ['active', 'inactive', 'suspended'])) {
                DB::update('super_admins', ['status' => $status], 'id = ?', [$adminId]);
                logAdminActivity($_SESSION['admin_id'], 'update_admin_status', "Changed admin #$adminId status to $status", 'admin', $adminId);
                $success = 'Admin status updated.';
            }
            break;
            
        case 'delete':
            $adminId = intval($_POST['admin_id'] ?? 0);
            
            if ($adminId === intval($_SESSION['admin_id'])) {
                $error = 'You cannot delete your own account.';
            } elseif ($adminId > 0) {
                $admin = DB::queryOne("SELECT full_name, username FROM super_admins WHERE id = ?", [$adminId]);
                if ($admin) {
                    DB::delete('super_admins', 'id = ?', [$adminId]);
                    logAdminActivity($_SESSION['admin_id'], 'delete_admin', "Deleted admin: {$admin['full_name']} ({$admin['username']})");
                    $success = 'Admin user deleted.';
                }
            }
            break;
            
        case 'reset_password':
            $adminId = intval($_POST['admin_id'] ?? 0);
            if ($adminId > 0) {
                $newPassword = bin2hex(random_bytes(4));
                DB::update('super_admins', [
                    'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                    'login_attempts' => 0,
                    'locked_until' => null
                ], 'id = ?', [$adminId]);
                logAdminActivity($_SESSION['admin_id'], 'reset_admin_password', "Reset password for admin #$adminId", 'admin', $adminId);
                $success = "Password reset successfully. New password: <strong>$newPassword</strong>";
            }
            break;
    }
}

// Get all admins
$admins = [];
try {
    $admins = DB::query("SELECT * FROM super_admins ORDER BY created_at DESC");
} catch (Exception $e) {
    $error = 'Admin table not found. Please run the database migration.';
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Admin Users</h4>
        <p class="text-muted mb-0">Manage super admin accounts</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
        <i class="bi bi-plus-lg me-2"></i>Add Admin
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Admins Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-person-gear me-2"></i>Admin Users (<?php echo count($admins); ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($admins)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No admin users found</td></tr>
                <?php else: ?>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><strong>#<?php echo $admin['id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="admin-avatar me-2" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($admin['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td>
                                <?php
                                $roleColors = ['super_admin' => 'danger', 'admin' => 'primary', 'moderator' => 'info'];
                                ?>
                                <span class="badge bg-<?php echo $roleColors[$admin['role']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $admin['status']; ?>">
                                    <?php echo ucfirst($admin['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                            <td>
                                <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($admin['status'] !== 'active'): ?>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <button type="submit" class="dropdown-item text-success">
                                                            <i class="bi bi-check-circle me-2"></i>Activate
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($admin['status'] !== 'suspended'): ?>
                                                <li>
                                                    <form method="POST" onsubmit="return confirm('Suspend this admin?')">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <input type="hidden" name="status" value="suspended">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-slash-circle me-2"></i>Suspend
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            
                                            <li>
                                                <form method="POST" onsubmit="return confirm('Reset password?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        <i class="bi bi-key me-2"></i>Reset Password
                                                    </button>
                                                </form>
                                            </li>
                                            
                                            <li><hr class="dropdown-divider"></li>
                                            
                                            <li>
                                                <form method="POST" onsubmit="return confirm('DELETE this admin permanently?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add Admin User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" pattern="[a-zA-Z0-9_]+" required>
                        <small class="text-muted">Letters, numbers, and underscores only</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" minlength="8" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="admin">Admin</option>
                            <option value="moderator">Moderator</option>
                            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                                <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
