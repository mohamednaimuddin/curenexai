<?php
require_once 'includes/init.php';
require_once 'includes/rate_limiter.php';

// Check for remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $rememberToken = $_COOKIE['remember_token'];
    $session = DB::queryOne(
        "SELECT ds.*, d.status FROM doctor_sessions ds 
         JOIN doctors d ON ds.doctor_id = d.id 
         WHERE ds.remember_token = ? AND ds.expires_at > NOW() AND ds.is_active = 1 AND d.status = 'active'",
        [$rememberToken]
    );
    
    if ($session) {
        $doctor = DB::queryOne("SELECT * FROM doctors WHERE id = ?", [$session['doctor_id']]);
        if ($doctor) {
            // Auto-login from remember token
            session_regenerate_id(true);
            
            // Generate new session token and invalidate old sessions
            $newSessionToken = bin2hex(random_bytes(32));
            
            // Invalidate all other sessions for this doctor (single device login)
            DB::query("UPDATE doctor_sessions SET is_active = 0 WHERE doctor_id = ? AND id != ?", 
                      [$doctor['id'], $session['id']]);
            
            // Update current session
            DB::update('doctor_sessions', [
                'session_token' => $newSessionToken,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'last_activity' => date('Y-m-d H:i:s')
            ], 'id = ?', [$session['id']]);
            
            // Update doctor's session info
            DB::update('doctors', [
                'session_token' => $newSessionToken,
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ], 'id = ?', [$doctor['id']]);
            
            $_SESSION['doctor_id'] = $doctor['id'];
            $_SESSION['doctor_name'] = $doctor['full_name'];
            $_SESSION['doctor_email'] = $doctor['email'];
            $_SESSION['login_time'] = time();
            $_SESSION['session_token'] = $newSessionToken;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            
            logActivity('auto_login', 'Auto-login via remember token', $doctor['id']);
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirectUrl);
        }
    } else {
        // Invalid or expired remember token, clear cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
    unset($_SESSION['redirect_after_login']);
    redirect($redirectUrl);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
    
    // Get security settings from database
    $maxAttempts = getMaxLoginAttempts();
    $lockoutMinutes = getLockoutDurationMinutes();
    $lockoutSeconds = $lockoutMinutes * 60;
    
    // Rate limiting for login attempts - per email (user) to avoid locking out other users
    // Using email in the key so each user has their own rate limit
    $rateLimitKey = 'login:' . strtolower($email) . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateLimit = RateLimiter::check($rateLimitKey, $maxAttempts, $lockoutSeconds);
    
    if (!$rateLimit['allowed']) {
        $retryMinutes = ceil($rateLimit['retry_after'] / 60);
        $error = 'Too many login attempts for this account. Please wait ' . ($retryMinutes > 1 ? $retryMinutes . ' minutes' : $rateLimit['retry_after'] . ' seconds') . ' before trying again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Fetch doctor by email
        $doctor = DB::queryOne("SELECT * FROM doctors WHERE email = ? AND status = 'active'", [$email]);
        
        if ($doctor && verifyPassword($password, $doctor['password'])) {
            // Login successful - Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Generate unique session token
            $sessionToken = bin2hex(random_bytes(32));
            
            // Invalidate all previous sessions for this doctor (single device login)
            DB::query("UPDATE doctor_sessions SET is_active = 0 WHERE doctor_id = ?", [$doctor['id']]);
            
            // Calculate session expiry
            $sessionExpiry = $remember ? date('Y-m-d H:i:s', strtotime('+30 days')) : date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Create remember token if "Remember Me" is checked
            $rememberToken = null;
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                // Set cookie for 30 days
                setcookie('remember_token', $rememberToken, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            // Store session in database
            try {
                DB::insert('doctor_sessions', [
                    'doctor_id' => $doctor['id'],
                    'session_token' => $sessionToken,
                    'remember_token' => $rememberToken,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'expires_at' => $sessionExpiry,
                    'is_active' => 1
                ]);
            } catch (Exception $e) {
                // Table might not exist yet, continue without session tracking
            }
            
            // Update doctor's session token
            try {
                DB::update('doctors', [
                    'session_token' => $sessionToken,
                    'session_expires_at' => $sessionExpiry,
                    'last_login_at' => date('Y-m-d H:i:s'),
                    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
                ], 'id = ?', [$doctor['id']]);
            } catch (Exception $e) {
                // Columns might not exist yet, continue without
            }
            
            $_SESSION['doctor_id'] = $doctor['id'];
            $_SESSION['doctor_name'] = $doctor['full_name'];
            $_SESSION['doctor_email'] = $doctor['email'];
            $_SESSION['login_time'] = time();
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['show_announcements'] = true; // Flag to show announcement popup
            
            // Log activity
            logActivity('login', 'Doctor logged in successfully' . ($remember ? ' (Remember Me)' : ''), $doctor['id']);
            
            // Redirect
            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            redirect($redirectUrl);
        } else {
            $error = 'Invalid email or password';
            logActivity('login_failed', 'Failed login attempt for: ' . $email);
        }
    }
}

// SEO Variables - Login page should NOT appear in main search results
// Users find login through homepage, not by searching "CurenexAI login"
$pageTitle = 'Doctor Login';
$pageDescription = 'Login to your CurenexAI doctor dashboard. Access AI-powered diagnosis tools, patient management, and homeopathic practice features.';
$pageKeywords = 'doctor login, CurenexAI dashboard, homeopathy portal';
$pageRobots = 'noindex, follow'; // Keep crawling but don't show in search
$pageCanonical = 'https://curenexai.com/login.php';
$bodyClass = 'auth-page';
$htmlClass = 'auth-page-html';
?>
<?php require_once 'includes/header.php'; ?>

<style>
    .auth-container::before {
        content: '';
        position: fixed;
        right: -15%;
        bottom: -10%;
        width: 600px;
        height: 600px;
        background: url('assets/image/xrunbg.png') center/contain no-repeat;
        opacity: 0.04;
        pointer-events: none;
        z-index: 0;
    }
</style>

<div class="auth-container">
    <div class="auth-box">
        <div class="auth-back-btn">
            <a href="<?php echo APP_URL; ?>/" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
        <div class="auth-header">
            <div class="auth-logo">
                <img src="assets/image/CURENEXAI PNG.png" alt="Curenex AI" class="auth-logo-img">
            </div>
            <p>Clinical Decision Support System</p>
        </div>
        
        <div class="auth-body">
            <h2>Doctor Login</h2>
            <p class="text-muted">Sign in to access your clinical dashboard</p>
            
            <?php if (isset($_SESSION['session_invalidated']) && $_SESSION['session_invalidated']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['invalidated_message'] ?? 'Your session has expired. Please login again.'; ?>
                </div>
                <?php unset($_SESSION['session_invalidated'], $_SESSION['invalidated_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['session_expired']) && $_SESSION['session_expired']): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i> Your session has expired due to inactivity. Please login again.
                </div>
                <?php unset($_SESSION['session_expired']); ?>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="doctor@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required 
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-row-flex" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div class="form-group form-check" style="margin: 0;">
                        <input type="checkbox" id="remember" name="remember" class="form-check-input">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-password-link" style="color: #6366f1; font-size: 14px; text-decoration: none;">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>
        
        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
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

<?php require_once 'includes/footer.php'; ?>
