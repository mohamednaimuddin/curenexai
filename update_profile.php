<?php
require_once __DIR__ . '/includes/init.php';
requireLogin();

$doctor = getLoggedInDoctor();
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block updates in maintenance mode
    if (blockIfMaintenance()) {
        $error = 'System is in maintenance mode. Changes cannot be saved at this time.';
    } else {
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $qualification = sanitize($_POST['qualification'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $registrationNumber = sanitize($_POST['registration_number'] ?? '');
        
        if (empty($fullName) || empty($email)) {
            $error = 'Name and email are required.';
        } else {
            // Check if email is taken by another user
            $existingDoctor = DB::queryOne("SELECT id FROM doctors WHERE email = ? AND id != ?", [$email, $doctor['id']]);
            if ($existingDoctor) {
                $error = 'This email is already registered to another account.';
            } else {
                try {
                    DB::update('doctors', [
                        'full_name' => $fullName,
                        'email' => $email,
                        'phone' => $phone,
                        'qualification' => $qualification,
                        'specialization' => $specialization,
                        'registration_number' => $registrationNumber
                    ], 'id = ?', [$doctor['id']]);
                    
                    // Update session
                    $_SESSION['doctor_name'] = $fullName;
                    $_SESSION['doctor_email'] = $email;
                    
                    $success = 'Profile updated successfully!';
                    $doctor = getLoggedInDoctor(); // Refresh data
                    logActivity('profile_update', 'Profile updated', $doctor['id']);
                } catch (Exception $e) {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif (!verifyPassword($currentPassword, $doctor['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            try {
                DB::update('doctors', [
                    'password' => hashPassword($newPassword)
                ], 'id = ?', [$doctor['id']]);
                
                $success = 'Password changed successfully!';
                logActivity('password_change', 'Password changed', $doctor['id']);
            } catch (Exception $e) {
                $error = 'Failed to change password. Please try again.';
            }
        }
    }
    } // End of maintenance mode check
}

$pageTitle = 'Update Profile';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Information -->
                <div class="col-md-6 mb-4">
                    <div class="dashboard-card h-100">
                        <div class="card-header-custom">
                            <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                        </div>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="form-group">
                                <label for="full_name"><i class="fas fa-user"></i> Full Name *</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                                <input type="email" name="email" id="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                                <input type="text" name="phone" id="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="qualification"><i class="fas fa-graduation-cap"></i> Qualification</label>
                                <input type="text" name="qualification" id="qualification" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['qualification'] ?? ''); ?>"
                                       placeholder="e.g., BHMS, MD (Hom)">
                            </div>
                            
                            <div class="form-group">
                                <label for="specialization"><i class="fas fa-star"></i> Specialization</label>
                                <input type="text" name="specialization" id="specialization" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['specialization'] ?? ''); ?>"
                                       placeholder="e.g., Pediatrics, Skin Disorders">
                            </div>
                            
                            <div class="form-group">
                                <label for="registration_number"><i class="fas fa-id-card"></i> Registration Number</label>
                                <input type="text" name="registration_number" id="registration_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($doctor['registration_number'] ?? ''); ?>"
                                       placeholder="State Council Registration No.">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password -->
                <div class="col-md-6 mb-4">
                    <div class="dashboard-card h-100">
                        <div class="card-header-custom">
                            <h3><i class="fas fa-key"></i> Change Password</h3>
                        </div>
                        <form method="post" action="">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="form-group">
                                <label for="current_password"><i class="fas fa-lock"></i> Current Password *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" name="current_password" id="current_password" 
                                           class="form-control" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('current_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password"><i class="fas fa-key"></i> New Password *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" name="new_password" id="new_password" 
                                           class="form-control" minlength="8" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password"><i class="fas fa-check-double"></i> Confirm New Password *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" name="confirm_password" id="confirm_password" 
                                           class="form-control" minlength="8" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-sync-alt"></i> Change Password
                            </button>
                            
                            <div class="mt-3 pt-3 border-top">
                                <p class="text-muted mb-2"><small>Can't remember your current password?</small></p>
                                <a href="<?php echo APP_URL; ?>/forgot_password.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-question-circle"></i> Reset via Email
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Account Info -->
            <div class="dashboard-card">
                <div class="card-header-custom">
                    <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Account Created:</strong> <?php echo date('d M Y, h:i A', strtotime($doctor['created_at'])); ?></p>
                        <p><strong>Last Updated:</strong> <?php echo date('d M Y, h:i A', strtotime($doctor['updated_at'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Account Status:</strong> 
                            <span class="badge bg-<?php echo $doctor['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($doctor['status']); ?>
                            </span>
                        </p>
                        <p><strong>Current Session:</strong> 
                            <span class="badge bg-info">
                                <i class="fas fa-clock"></i> Since <?php echo date('h:i A', $_SESSION['login_time'] ?? time()); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<style>
.card-header-custom {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: white;
    padding: 15px 20px;
    margin: -20px -20px 20px -20px;
    border-radius: 12px 12px 0 0;
}
.card-header-custom h3 {
    margin: 0;
    font-size: 1.2rem;
}
.password-input-wrapper {
    position: relative;
}
.password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
}
.password-toggle:hover {
    color: #4f46e5;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
