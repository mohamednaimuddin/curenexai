<?php
/**
 * Email OTP Functions
 * Handles OTP generation, sending, and verification
 * Uses PHPMailer for reliable email delivery
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// PHPMailer is autoloaded via Composer

/**
 * Generate a 6-digit OTP
 */
function generateOTP($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Store OTP in database
 */
function storeOTP($email, $otp, $purpose = 'registration', $expiryMinutes = 10) {
    // Delete any existing OTPs for this email and purpose
    DB::query("DELETE FROM email_otps WHERE email = ? AND purpose = ?", [$email, $purpose]);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
    
    $otpId = DB::insert('email_otps', [
        'email' => $email,
        'otp' => $otp,
        'purpose' => $purpose,
        'expires_at' => $expiresAt,
        'verified' => 0,
        'attempts' => 0
    ]);
    
    return $otpId;
}

/**
 * Verify OTP
 */
function verifyOTP($email, $otp, $purpose = 'registration') {
    $record = DB::queryOne(
        "SELECT * FROM email_otps WHERE email = ? AND purpose = ? AND verified = 0 ORDER BY id DESC LIMIT 1",
        [$email, $purpose]
    );
    
    if (!$record) {
        return ['success' => false, 'message' => 'No OTP found. Please request a new one.'];
    }
    
    // Check if expired
    if (strtotime($record['expires_at']) < time()) {
        DB::query("DELETE FROM email_otps WHERE id = ?", [$record['id']]);
        return ['success' => false, 'message' => 'OTP has expired. Please request a new one.'];
    }
    
    // Check attempts
    if ($record['attempts'] >= 5) {
        DB::query("DELETE FROM email_otps WHERE id = ?", [$record['id']]);
        return ['success' => false, 'message' => 'Too many attempts. Please request a new OTP.'];
    }
    
    // Increment attempts
    DB::query("UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?", [$record['id']]);
    
    // Verify OTP
    if ($record['otp'] !== $otp) {
        $remaining = 5 - ($record['attempts'] + 1);
        return ['success' => false, 'message' => "Invalid OTP. {$remaining} attempts remaining."];
    }
    
    // Mark as verified
    DB::query("UPDATE email_otps SET verified = 1 WHERE id = ?", [$record['id']]);
    
    return ['success' => true, 'message' => 'OTP verified successfully.', 'otp_id' => $record['id']];
}

/**
 * Send OTP via email using PHPMailer
 */
function sendOTPEmail($email, $otp, $name = '') {
    $mail = new PHPMailer(true);
    
    // Create a log file for debugging email issues
    $logFile = __DIR__ . '/../logs/email_debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    try {
        // Server settings
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
        
        // Timeout settings
        $mail->Timeout    = 15;
        $mail->SMTPKeepAlive = false;
        
        // Disable debug in production (set to 2 for debugging)
        $mail->SMTPDebug  = 0;
        $mail->Debugoutput = function($str, $level) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[$timestamp] $str\n", FILE_APPEND);
        };
        
        // Log attempt
        @file_put_contents($logFile, "\n\n[" . date('Y-m-d H:i:s') . "] ========== NEW EMAIL ATTEMPT ==========\n", FILE_APPEND);
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] To: $email, Host: " . $mail->Host . ", Port: " . $mail->Port . "\n", FILE_APPEND);
        
        // Recipients
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'noreply@example.com');
        $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name);
        $mail->addReplyTo($fromEmail, $fromName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - Email Verification OTP';
        $mail->Body    = getOTPEmailTemplate($otp, $name);
        $mail->AltBody = "Your OTP for " . APP_NAME . " is: {$otp}. This code expires in 10 minutes.";
        
        $mail->send();
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SUCCESS: Email sent to $email\n", FILE_APPEND);
        return true;
        
    } catch (Exception $e) {
        $errorMsg = "PHPMailer Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage();
        error_log($errorMsg);
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] FAILED: $errorMsg\n", FILE_APPEND);
        return false;
    }
}

/**
 * Get HTML email template for OTP
 */
function getOTPEmailTemplate($otp, $name = '') {
    $appName = APP_NAME;
    $greeting = $name ? "Hello Dr. {$name}," : "Hello,";
    
    return <<<HTML
    <html>
    <head>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f3f4f6; }
            .wrapper { padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 40px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
            .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 40px 30px; }
            .content p { margin: 0 0 20px; color: #4b5563; }
            .otp-box { background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 30px; text-align: center; border-radius: 12px; margin: 25px 0; }
            .otp-code { font-size: 42px; font-weight: bold; color: #ffffff; letter-spacing: 12px; font-family: 'Courier New', monospace; }
            .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px 20px; margin: 25px 0; border-radius: 0 8px 8px 0; }
            .info-box p { margin: 0; color: #92400e; font-size: 14px; }
            .warning { color: #dc2626; font-size: 13px; margin-top: 20px; padding: 15px; background: #fef2f2; border-radius: 8px; }
            .warning i { margin-right: 5px; }
            .footer { background: #1f2937; color: #9ca3af; padding: 25px 30px; text-align: center; font-size: 12px; }
            .footer p { margin: 5px 0; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>✚ {$appName}</h1>
                    <p>Email Verification</p>
                </div>
                <div class="content">
                    <p>{$greeting}</p>
                    <p>Your One-Time Password (OTP) for email verification is:</p>
                    <div class="otp-box">
                        <div class="otp-code">{$otp}</div>
                    </div>
                    <div class="info-box">
                        <p>⏱️ This OTP is valid for <strong>10 minutes</strong> only.</p>
                    </div>
                    <div class="warning">
                        ⚠️ <strong>Security Notice:</strong> Do not share this OTP with anyone. Our team will never ask for your OTP.
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

/**
 * Store pending registration data
 */
function storePendingRegistration($data, $otpId) {
    // Delete any existing pending registration for this email
    DB::query("DELETE FROM pending_registrations WHERE email = ?", [$data['email']]);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime("+30 minutes"));
    
    return DB::insert('pending_registrations', [
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'password' => $data['password'],
        'registration_number' => $data['registration_number'] ?? '',
        'state_council' => $data['state_council'] ?? '',
        'qualification' => $data['qualification'] ?? '',
        'phone' => $data['phone'] ?? '',
        'otp_id' => $otpId,
        'expires_at' => $expiresAt
    ]);
}

/**
 * Get pending registration by email
 */
function getPendingRegistration($email) {
    return DB::queryOne(
        "SELECT * FROM pending_registrations WHERE email = ? AND expires_at > NOW()",
        [$email]
    );
}

/**
 * Complete registration from pending data
 */
function completePendingRegistration($email) {
    $pending = getPendingRegistration($email);
    
    if (!$pending) {
        return ['success' => false, 'message' => 'No pending registration found or it has expired.'];
    }
    
    // Create the doctor account
    $doctorData = [
        'full_name' => $pending['full_name'],
        'email' => $pending['email'],
        'password' => $pending['password'],
        'registration_number' => $pending['registration_number'],
        'state_council' => $pending['state_council'] ?? '',
        'qualification' => $pending['qualification'],
        'phone' => $pending['phone'],
        'status' => 'active'
    ];
    
    $doctorId = DB::insert('doctors', $doctorData);
    
    if ($doctorId) {
        // Clean up
        DB::query("DELETE FROM pending_registrations WHERE email = ?", [$email]);
        DB::query("DELETE FROM email_otps WHERE email = ? AND purpose = 'registration'", [$email]);
        
        return [
            'success' => true, 
            'doctor_id' => $doctorId, 
            'full_name' => $pending['full_name'],
            'email' => $pending['email']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create account. Please try again.'];
}

/**
 * Clean up expired OTPs and pending registrations
 */
function cleanupExpiredOTPs() {
    DB::query("DELETE FROM email_otps WHERE expires_at < NOW()");
    DB::query("DELETE FROM pending_registrations WHERE expires_at < NOW()");
}
