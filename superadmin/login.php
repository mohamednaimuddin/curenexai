<?php
/**
 * Super Admin Login Page
 */

require_once __DIR__ . '/../includes/init.php';

// Generate CSRF token if not exists
generateCsrfToken();

// Already logged in?
if (isSuperAdminLoggedIn()) {
    header("Location: " . rtrim(APP_URL, '/') . "/superadmin/dashboard.php");
    exit;
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Get security settings from database
        $maxAttempts = getMaxLoginAttempts();
        $lockoutMinutes = getLockoutDurationMinutes();
        
        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            // Check for admin
            $admin = DB::queryOne("SELECT * FROM super_admins WHERE (username = ? OR email = ?) AND status = 'active'", [$username, $username]);
            
            if ($admin) {
                // Check if locked
                if ($admin['locked_until'] && strtotime($admin['locked_until']) > time()) {
                    $remaining = ceil((strtotime($admin['locked_until']) - time()) / 60);
                    $error = "Account is locked. Try again in {$remaining} minutes.";
                } elseif (password_verify($password, $admin['password'])) {
                    // Successful login
                    session_regenerate_id(true);
                    
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['is_super_admin'] = true;
                    $_SESSION['admin_permissions'] = json_decode($admin['permissions'] ?? '{}', true);
                    
                    // Reset login attempts and update last login
                    DB::update('super_admins', [
                        'login_attempts' => 0,
                        'locked_until' => null,
                        'last_login' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$admin['id']]);
                    
                    // Log activity
                    logAdminActivity($admin['id'], 'login', 'Admin logged in successfully');
                    
                    // Redirect
                    $redirect = $_SESSION['redirect_after_login'] ?? rtrim(APP_URL, '/') . '/superadmin/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: " . $redirect);
                    exit;
                } else {
                    // Failed login
                    $attempts = ($admin['login_attempts'] ?? 0) + 1;
                    $updateData = ['login_attempts' => $attempts];
                    
                    if ($attempts >= $maxAttempts) {
                        $updateData['locked_until'] = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
                        $error = "Too many failed attempts. Account locked for {$lockoutMinutes} minutes.";
                    } else {
                        $error = 'Invalid credentials. ' . ($maxAttempts - $attempts) . ' attempts remaining.';
                    }
                    
                    DB::update('super_admins', $updateData, 'id = ?', [$admin['id']]);
                }
            } else {
                $error = 'Invalid credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            padding: 35px 30px;
            text-align: center;
        }
        
        .login-icon {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .login-icon i {
            font-size: 32px;
            color: white;
        }
        
        .login-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 5px;
        }
        
        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .login-body {
            padding: 35px 30px;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-control {
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group-text {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-right: none;
            border-radius: 10px 0 0 10px;
            padding: 12px 14px;
            color: #6b7280;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }
        
        .input-group:focus-within .form-control {
            border-color: #667eea;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            padding: 5px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px 24px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f3f4f6;
            color: #9ca3af;
            font-size: 13px;
        }
        
        .security-badge i {
            color: #10b981;
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            opacity: 0.9;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.2s;
        }
        
        .back-link a:hover {
            opacity: 1;
            color: white;
        }
        
        @media (max-width: 480px) {
            body {
                padding: 15px;
            }
            
            .login-header {
                padding: 30px 25px;
            }
            
            .login-body {
                padding: 30px 25px;
            }
            
            .login-icon {
                width: 60px;
                height: 60px;
            }
            
            .login-icon i {
                font-size: 28px;
            }
            
            .login-header h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <h2>Admin Portal</h2>
                <p>Secure access to control panel</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="username" 
                                   placeholder="Enter your username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <div class="input-group position-relative">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password" id="password" 
                                   placeholder="Enter your password" required style="padding-right: 45px;">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                    
                    <div class="security-badge">
                        <i class="bi bi-shield-check"></i>
                        <span>Secure connection • All activity logged</span>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="back-link">
            <a href="<?php echo APP_URL; ?>">
                <i class="bi bi-arrow-left"></i> Back to main site
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }
    </script>
</body>
</html>
