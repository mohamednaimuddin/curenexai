<?php
require_once 'includes/init.php';
require_once 'includes/email_otp.php';

// Check if verification was successful - show popup
if (isset($_GET['success']) && isset($_SESSION['verification_success'])) {
    $verifiedName = $_SESSION['verified_name'] ?? '';
    unset($_SESSION['verification_success']);
    unset($_SESSION['verified_name']);
    
    $pageTitle = 'Verification Successful';
    $bodyClass = 'auth-page';
    $htmlClass = 'auth-page-html';
    require_once 'includes/header.php';
    ?>
    
    <div class="success-popup-overlay">
        <div class="success-popup">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Email Verified Successfully!</h2>
            <p>Welcome, Dr. <?php echo htmlspecialchars($verifiedName); ?>!</p>
            <p class="redirect-text">Redirecting to dashboard in <span id="countdown">3</span> seconds...</p>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    </div>
    
    <style>
    .success-popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, var(--primary-100) 0%, var(--gray-100) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
    }
    
    .success-popup {
        background: white;
        border-radius: 20px;
        padding: 3rem 2.5rem;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        max-width: 400px;
        width: 100%;
        animation: popupIn 0.5s ease;
    }
    
    @keyframes popupIn {
        0% { transform: scale(0.8); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .success-icon {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, #22c55e, #16a34a);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        animation: iconPop 0.6s ease 0.2s both;
    }
    
    @keyframes iconPop {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }
    
    .success-icon i {
        font-size: 3rem;
        color: white;
    }
    
    .success-popup h2 {
        color: #22c55e;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .success-popup p {
        color: var(--text-secondary);
        margin: 0.5rem 0;
    }
    
    .redirect-text {
        font-size: 0.9rem;
        margin-top: 1.5rem !important;
    }
    
    .redirect-text span {
        font-weight: bold;
        color: var(--primary-600);
    }
    
    .progress-bar {
        width: 100%;
        height: 6px;
        background: var(--gray-200);
        border-radius: 3px;
        margin-top: 1rem;
        overflow: hidden;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary-500), var(--secondary-500));
        border-radius: 3px;
        animation: progressFill 3s linear forwards;
    }
    
    @keyframes progressFill {
        0% { width: 0%; }
        100% { width: 100%; }
    }
    </style>
    
    <script>
    let seconds = 3;
    const countdownEl = document.getElementById('countdown');
    
    const timer = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = '<?php echo APP_URL; ?>/dashboard.php';
        }
    }, 1000);
    </script>
    
    <?php
    require_once 'includes/footer.php';
    exit;
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

// Check if email is in session
if (!isset($_SESSION['verify_email'])) {
    redirect('/register.php');
}

$email = $_SESSION['verify_email'];
$error = '';
$success = '';
$resendCooldown = 0;

// Check resend cooldown
if (isset($_SESSION['otp_resend_time'])) {
    $timePassed = time() - $_SESSION['otp_resend_time'];
    if ($timePassed < 60) {
        $resendCooldown = 60 - $timePassed;
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Resend OTP
        if ($_POST['action'] === 'resend') {
            if ($resendCooldown > 0) {
                $error = "Please wait {$resendCooldown} seconds before requesting a new OTP.";
            } else {
                $pending = getPendingRegistration($email);
                if ($pending) {
                    $otp = generateOTP();
                    $otpId = storeOTP($email, $otp, 'registration');
                    $_SESSION['otp_resend_time'] = time();
                    
                    // Send OTP email via PHPMailer
                    if (sendOTPEmail($email, $otp, $pending['full_name'])) {
                        $success = 'A new OTP has been sent to your email.';
                    } else {
                        $error = 'Failed to send OTP. Please try again.';
                    }
                } else {
                    $error = 'Session expired. Please register again.';
                    unset($_SESSION['verify_email']);
                    redirect('/register.php');
                }
            }
        }
        
        // Verify OTP
        elseif ($_POST['action'] === 'verify') {
            $otp = sanitize($_POST['otp'] ?? '');
            
            if (empty($otp) || strlen($otp) !== 6) {
                $error = 'Please enter a valid 6-digit OTP.';
            } else {
                $result = verifyOTP($email, $otp, 'registration');
                
                if ($result['success']) {
                    // Complete registration
                    $regResult = completePendingRegistration($email);
                    
                    if ($regResult['success']) {
                        // Auto-login
                        $_SESSION['doctor_id'] = $regResult['doctor_id'];
                        $_SESSION['doctor_name'] = $regResult['full_name'];
                        $_SESSION['doctor_email'] = $regResult['email'];
                        
                        // Clear verification session
                        unset($_SESSION['verify_email']);
                        unset($_SESSION['otp_resend_time']);
                        
                        logActivity('register', 'New doctor registered (email verified): ' . $email, $regResult['doctor_id']);
                        
                        // Set flag to show success popup
                        $_SESSION['verification_success'] = true;
                        $_SESSION['verified_name'] = $regResult['full_name'];
                        header('Location: ' . APP_URL . '/verify_email.php?success=1');
                        exit;
                    } else {
                        $error = $regResult['message'];
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}

$pageTitle = 'Verify Email';
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
            <h1>Verify Your Email</h1>
            <p>Enter the OTP sent to your email</p>
        </div>
        
        <div class="auth-body">
            <div class="verify-email-info">
                <i class="fas fa-paper-plane"></i>
                <p>We've sent a 6-digit OTP to:</p>
                <strong><?php echo htmlspecialchars($email); ?></strong>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            

            
            <form method="POST" action="verify_email.php" class="auth-form otp-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="verify">
                
                <div class="form-group">
                    <label for="otp">
                        <i class="fas fa-key"></i> Enter OTP
                    </label>
                    <div class="otp-input-wrapper">
                        <input 
                            type="text" 
                            id="otp" 
                            name="otp" 
                            class="form-control otp-input" 
                            placeholder="000000"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            required 
                            autofocus
                        >
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-check-circle"></i> Verify & Create Account
                </button>
            </form>
            
            <div class="otp-resend">
                <p>Didn't receive the OTP?</p>
                <form method="POST" action="verify_email.php" class="resend-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" class="btn btn-link" <?php echo $resendCooldown > 0 ? 'disabled' : ''; ?>>
                        <i class="fas fa-redo"></i> 
                        <?php if ($resendCooldown > 0): ?>
                            Resend in <?php echo $resendCooldown; ?>s
                        <?php else: ?>
                            Resend OTP
                        <?php endif; ?>
                    </button>
                </form>
            </div>
            
            <div class="otp-timer">
                <i class="fas fa-clock"></i> OTP expires in <span id="otpTimer">10:00</span>
            </div>
        </div>
        
        <div class="auth-footer">
            <p>Wrong email? <a href="register.php">Go back to registration</a></p>
        </div>
    </div>
</div>

<style>
.verify-email-info {
    text-align: center;
    padding: 1rem;
    background: var(--primary-50);
    border-radius: var(--border-radius);
    margin-bottom: 1.25rem;
}

.verify-email-info i {
    font-size: 2rem;
    color: var(--primary-500);
    margin-bottom: 0.5rem;
}

.verify-email-info p {
    margin: 0.5rem 0 0.25rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.verify-email-info strong {
    color: var(--primary-600);
    font-size: 1rem;
}

.otp-input-wrapper {
    position: relative;
}

.otp-input {
    text-align: center;
    font-size: 1.75rem !important;
    font-weight: 600;
    letter-spacing: 0.75rem;
    padding: 0.875rem 1rem !important;
    font-family: 'Courier New', monospace;
}

.otp-input::placeholder {
    letter-spacing: 0.5rem;
    color: var(--gray-300);
}

.otp-resend {
    text-align: center;
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.otp-resend p {
    margin: 0 0 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.resend-form {
    display: inline;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary-600);
    cursor: pointer;
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}

.btn-link:hover:not(:disabled) {
    color: var(--primary-700);
    text-decoration: underline;
}

.btn-link:disabled {
    color: var(--gray-400);
    cursor: not-allowed;
}

.otp-timer {
    text-align: center;
    margin-top: 1rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.otp-timer i {
    color: var(--warning-500);
}

.otp-timer span {
    font-weight: 600;
    color: var(--warning-600);
}

.alert-info {
    background: var(--info-50);
    border: 1px solid var(--info-500);
    color: var(--info-600);
}

.otp-display-box {
    background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
    border-radius: var(--border-radius);
    padding: 1.25rem;
    text-align: center;
    margin-bottom: 1.25rem;
    color: white;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

.otp-display-label {
    font-size: 0.8rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.otp-display-label i {
    margin-right: 0.375rem;
}

.otp-display-code {
    font-size: 2.25rem;
    font-weight: 700;
    letter-spacing: 0.5rem;
    font-family: 'Courier New', monospace;
    background: rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    display: inline-block;
    margin: 0.5rem 0;
}

.otp-display-hint {
    font-size: 0.75rem;
    opacity: 0.85;
    margin-top: 0.25rem;
}

@media (max-height: 600px) {
    .verify-email-info {
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .verify-email-info i {
        font-size: 1.5rem;
    }
    
    .otp-input {
        font-size: 1.5rem !important;
        padding: 0.625rem 0.75rem !important;
    }
    
    .otp-resend {
        margin-top: 1rem;
        padding-top: 0.75rem;
    }
}
</style>

<script>
// Auto-focus and format OTP input
document.getElementById('otp').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
});

// Countdown timer (10 minutes)
let timeLeft = 600; // 10 minutes in seconds
const timerElement = document.getElementById('otpTimer');

function updateTimer() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    
    if (timeLeft > 0) {
        timeLeft--;
        setTimeout(updateTimer, 1000);
    } else {
        timerElement.textContent = 'Expired';
        timerElement.style.color = 'var(--danger-500)';
    }
}
updateTimer();

// Resend cooldown timer
<?php if ($resendCooldown > 0): ?>
let resendTimeLeft = <?php echo $resendCooldown; ?>;
const resendBtn = document.querySelector('.resend-form button');

function updateResendTimer() {
    if (resendTimeLeft > 0) {
        resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend in ' + resendTimeLeft + 's';
        resendTimeLeft--;
        setTimeout(updateResendTimer, 1000);
    } else {
        resendBtn.disabled = false;
        resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend OTP';
    }
}
updateResendTimer();
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
