<?php
/**
 * Forgot Password - Link-based password reset
 * Step 1: Enter email → Send reset link if email exists
 * Step 2: Click link → Set new password
 */
require_once 'includes/init.php';
require_once 'includes/rate_limiter.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Generate CSRF token
generateCsrfToken();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
$success = '';
$step = 'email';
$token = $_GET['token'] ?? '';

// If token is provided, show reset form
if (!empty($token)) {
    $step = 'reset';
}

/**
 * Generate a secure reset token
 */
function generateResetToken() {
    return bin2hex(random_bytes(32)); // 64 character token
}

/**
 * Store reset token in database
 */
function storeResetToken($email, $token, $expiryMinutes = 30) {
    // Delete any existing tokens for this email
    DB::query("DELETE FROM email_otps WHERE email = ? AND purpose = 'password_reset'", [$email]);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
    
    // Store token in otp field (reusing existing table)
    DB::insert('email_otps', [
        'email' => $email,
        'otp' => $token,
        'purpose' => 'password_reset',
        'expires_at' => $expiresAt,
        'verified' => 0,
        'attempts' => 0
    ]);
    
    return true;
}

/**
 * Verify reset token
 */
function verifyResetToken($token) {
    $record = DB::queryOne(
        "SELECT * FROM email_otps WHERE otp = ? AND purpose = 'password_reset' AND verified = 0",
        [$token]
    );
    
    if (!$record) {
        return ['success' => false, 'message' => 'Invalid or expired reset link. Please request a new one.'];
    }
    
    // Check if expired
    if (strtotime($record['expires_at']) < time()) {
        DB::query("DELETE FROM email_otps WHERE id = ?", [$record['id']]);
        return ['success' => false, 'message' => 'Reset link has expired. Please request a new one.'];
    }
    
    return ['success' => true, 'email' => $record['email'], 'id' => $record['id']];
}

/**
 * Send Password Reset Link Email
 */
function sendPasswordResetLink($email, $resetLink, $name = '') {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 465;
        $mail->Port       = $smtpPort;
        // Use SSL for port 465, STARTTLS for port 587
        $mail->SMTPSecure = ($smtpPort == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        
        // SSL options to prevent certificate issues
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $mail->Timeout    = 15;
        
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'noreply@example.com');
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        
        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - Password Reset Request';
        $mail->Body    = getPasswordResetEmailTemplate($resetLink, $name);
        $mail->AltBody = "Reset your password by visiting: {$resetLink}\n\nThis link expires in 30 minutes.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log('Password reset email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get HTML email template for password reset link
 */
function getPasswordResetEmailTemplate($resetLink, $name = '') {
    $appName = APP_NAME;
    $greeting = $name ? "Hello Dr. {$name}," : "Hello,";
    
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f3f4f6; }
            .wrapper { padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
            .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 40px 30px; }
            .content p { margin: 0 0 20px; color: #4b5563; }
            .btn-container { text-align: center; margin: 30px 0; }
            .btn { display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: #ffffff !important; padding: 16px 40px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; }
            .link-text { word-break: break-all; background: #f3f4f6; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 12px; margin: 20px 0; }
            .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 25px 0; border-radius: 0 8px 8px 0; }
            .info-box p { margin: 0; color: #92400e; font-size: 14px; }
            .warning { color: #dc2626; font-size: 13px; margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 8px; }
            .footer { background: #1f2937; color: #9ca3af; padding: 25px 30px; text-align: center; font-size: 12px; }
            .footer p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>🔐 Password Reset</h1>
                    <p>{$appName}</p>
                </div>
                <div class="content">
                    <p>{$greeting}</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    
                    <div class="btn-container">
                        <a href="{$resetLink}" class="btn">Reset My Password</a>
                    </div>
                    
                    <p style="font-size: 13px; color: #6b7280;">If the button doesn't work, copy and paste this link in your browser:</p>
                    <div class="link-text">{$resetLink}</div>
                    
                    <div class="info-box">
                        <p>⏱️ This link is valid for <strong>30 minutes</strong> only.</p>
                    </div>
                    
                    <div class="warning">
                        ⚠️ <strong>Security Notice:</strong> If you did not request a password reset, please ignore this email. Your account will remain secure.
                    </div>
                </div>
                <div class="footer">
                    <p>This is an automated message from {$appName}</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
HTML;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Step 1: Email submission - Send reset link
        // Check for email field OR submit_email button (button name not always sent)
        if (isset($_POST['email']) && !isset($_POST['submit_password']) && !isset($_POST['token'])) {
            $email = sanitize($_POST['email'] ?? '');
            
            // DEBUG MODE - remove in production
            $debug = isset($_GET['debug']);
            
            // Rate limiting - 3 requests per 5 minutes per IP
            $rateLimitKey = 'forgot_pwd:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $rateLimit = RateLimiter::check($rateLimitKey, 3, 300);
            
            if (!$rateLimit['allowed']) {
                $error = 'Too many requests. Please wait ' . ceil($rateLimit['retry_after'] / 60) . ' minutes before trying again.';
            } elseif (empty($email)) {
                $error = 'Please enter your email address.';
            } elseif (!isValidEmail($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email exists
                $doctor = DB::queryOne("SELECT id, full_name, email FROM doctors WHERE email = ? AND status = 'active'", [$email]);
                
                if ($debug) {
                    error_log("Forgot Password Debug: Email=$email, Doctor Found=" . ($doctor ? 'YES' : 'NO'));
                }
                
                if ($doctor) {
                    // Generate reset token
                    $token = generateResetToken();
                    storeResetToken($email, $token, 30); // 30 minutes expiry
                    
                    // Build reset link
                    $resetLink = APP_URL . '/forgot_password.php?token=' . $token;
                    
                    if ($debug) {
                        error_log("Forgot Password Debug: Reset Link=$resetLink");
                    }
                    
                    // Send reset link email
                    if (sendPasswordResetLink($email, $resetLink, $doctor['full_name'])) {
                        $success = 'Password reset link has been sent to your email. Please check your inbox.';
                        if ($debug) {
                            $success .= " (DEBUG: Email sent to {$email})";
                        }
                    } else {
                        $error = 'Failed to send email. Please try again later.';
                        if ($debug) {
                            $error .= " (DEBUG: sendPasswordResetLink() returned false - check PHP error log)";
                        }
                    }
                } else {
                    // Email not found - show specific error in debug mode
                    if ($debug) {
                        $error = "DEBUG: Email '{$email}' not found in doctors table OR account status is not 'active'";
                    } else {
                        // Don't reveal if email exists or not for security
                        $success = 'If an account exists with this email, you will receive a password reset link.';
                    }
                }
            }
        }
        
        // Step 2: Password reset via token
        // Check for token field in POST (button name might not be sent)
        elseif (isset($_POST['token']) && isset($_POST['password'])) {
            $token = sanitize($_POST['token'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($token)) {
                $error = 'Invalid reset link. Please request a new one.';
                $step = 'email';
            } else {
                // Verify token
                $tokenResult = verifyResetToken($token);
                
                if (!$tokenResult['success']) {
                    $error = $tokenResult['message'];
                    $step = 'email';
                } elseif (empty($password)) {
                    $error = 'Please enter a new password.';
                    $step = 'reset';
                } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                    $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
                    $step = 'reset';
                } elseif ($password !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                    $step = 'reset';
                } else {
                    $email = $tokenResult['email'];
                    
                    // Update password
                    $hashedPassword = hashPassword($password);
                    $updated = DB::query(
                        "UPDATE doctors SET password = ?, updated_at = NOW() WHERE email = ?",
                        [$hashedPassword, $email]
                    );
                    
                    if ($updated !== false) {
                        // Delete the used token
                        DB::query("DELETE FROM email_otps WHERE id = ?", [$tokenResult['id']]);
                        
                        // Log activity
                        if (function_exists('logActivity')) {
                            logActivity('password_reset', 'Password reset successful for: ' . $email);
                        }
                        
                        setFlash('success', 'Password reset successful! Please login with your new password.');
                        redirect('/login.php');
                    } else {
                        $error = 'Failed to update password. Please try again.';
                        $step = 'reset';
                    }
                }
            }
        }
    }
}

// If token provided in URL, verify it
if ($step === 'reset' && !empty($token)) {
    $tokenResult = verifyResetToken($token);
    if (!$tokenResult['success']) {
        $error = $tokenResult['message'];
        $step = 'email';
        $token = '';
    }
}

// SEO Variables
$pageTitle = 'Password Reset | Secure Account Recovery';
$pageDescription = 'Reset your CurenexAI password securely. Recover access to your AI-powered homeopathic healthcare account.';
$pageKeywords = 'Curenex password reset, CurenexAI forgot password, account recovery, Curenex AI, curenex, curenexai';
$pageRobots = 'index, follow';
$pageCanonical = 'https://curenexai.com/forgot_password.php';
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
        <div class="auth-header">
            <div class="auth-logo">
                <img src="assets/image/CURENEXAI PNG.png" alt="Curenex AI" class="auth-logo-img">
            </div>
            <h1><?php echo APP_NAME; ?></h1>
            <p>Password Recovery</p>
        </div>
        
        <div class="auth-body">
            <?php if ($step === 'email'): ?>
                <!-- Step 1: Enter Email -->
                <h2>Forgot Password?</h2>
                <p class="text-muted">Enter your registered email address to receive a password reset link</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope me-2"></i>Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Enter your registered email" required autofocus>
                    </div>
                    
                    <button type="submit" name="submit_email" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p>Remember your password? <a href="login.php">Login here</a></p>
                </div>
                
            <?php elseif ($step === 'reset'): ?>
                <!-- Step 2: Set New Password -->
                <h2>Create New Password</h2>
                <p class="text-muted">Enter your new password below</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock me-2"></i>New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="Enter new password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        <div class="password-strength mt-2">
                            <div class="strength-bar" id="strength-bar"></div>
                        </div>
                        <small class="strength-text" id="strength-text"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_password" class="btn btn-primary btn-block">
                        <i class="fas fa-save me-2"></i>Reset Password
                    </button>
                </form>
                
                <div class="auth-footer">
                    <p><a href="forgot_password.php">Request new reset link</a></p>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .password-input-wrapper {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #6c757d;
    }
    .password-strength {
        height: 4px;
        background: #e9ecef;
        border-radius: 2px;
        overflow: hidden;
    }
    .strength-bar {
        height: 100%;
        width: 0;
        transition: all 0.3s ease;
    }
    .strength-text {
        font-size: 12px;
    }
</style>

<script>
function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength indicator
document.getElementById('password')?.addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const widths = ['20%', '40%', '60%', '80%', '100%'];
    
    if (password.length === 0) {
        strengthBar.style.width = '0';
        strengthText.textContent = '';
    } else {
        strengthBar.style.width = widths[strength - 1] || '20%';
        strengthBar.style.background = colors[strength - 1] || colors[0];
        strengthText.textContent = texts[strength - 1] || texts[0];
        strengthText.style.color = colors[strength - 1] || colors[0];
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
