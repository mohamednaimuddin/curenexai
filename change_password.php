<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block updates in maintenance mode
    if (blockIfMaintenance()) {
        $error = 'System is in maintenance mode. Changes cannot be saved at this time.';
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Validate CSRF token
        $error = 'Invalid request. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All fields are required.';
        } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            // Verify current password
            $doctor = getLoggedInDoctor();
            
            if (!$doctor || !verifyPassword($currentPassword, $doctor['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                // Update password
                $hashedPassword = hashPassword($newPassword);
                $updated = DB::update('doctors', 
                    ['password' => $hashedPassword], 
                    'id = ?', 
                    [getLoggedInDoctorId()]
                );
                
                if ($updated) {
                    // Regenerate session for security
                    session_regenerate_id(true);
                    logActivity('password_changed', 'Password changed successfully');
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Change Password';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<div class="dashboard-card" style="max-width:500px;margin:40px auto;">
    <h2><i class="fas fa-key"></i> Change Password</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" name="current_password" id="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" name="new_password" id="new_password" class="form-control" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
            <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Password</button>
        <a href="<?php echo APP_URL; ?>/settings.php" class="btn btn-outline">Cancel</a>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
