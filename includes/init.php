<?php
/**
 * Application Initialization
 */

// Enable error logging before anything else
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Set a global exception handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "An error occurred. Please try again or contact support.";
    exit;
});

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

// Define app access
define('APP_ACCESS', true);

// Include Composer autoloader (for PHPMailer and other packages)
require_once __DIR__ . '/../vendor/autoload.php';

// Include configuration FIRST (before using any constants)
require_once __DIR__ . '/../config/config.php';

// Include database
require_once __DIR__ . '/database.php';

// Start session (after config is loaded)
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    $secure = IS_PRODUCTION && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $httponly = true;
    $samesite = 'Strict';
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    } else {
        session_set_cookie_params(SESSION_LIFETIME, '/; samesite=' . $samesite, '', $secure, $httponly);
    }
    
    session_name(SESSION_NAME);
    session_start();
    
    // Session fingerprint validation (anti-hijacking) - DISABLED
    // This was causing logout issues when switching between desktop/mobile
    // The User-Agent changes too frequently on mobile browsers and PWAs
    // Keeping the code commented for reference if needed for high-security scenarios
    /*
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sessionFingerprint = md5($userAgent . 'homeo_salt_2025');
    
    if (isset($_SESSION['fingerprint'])) {
        if ($_SESSION['fingerprint'] !== $sessionFingerprint) {
            error_log("Session fingerprint mismatch - old: " . $_SESSION['fingerprint'] . " new: " . $sessionFingerprint);
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['fingerprint'] = $sessionFingerprint;
        }
    } else {
        $_SESSION['fingerprint'] = $sessionFingerprint;
    }
    */
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Set session timeout - use database setting if available
    $sessionTimeout = SESSION_LIFETIME; // Default from config
    try {
        $dbTimeout = DB::queryOne("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout_minutes'");
        if ($dbTimeout && is_numeric($dbTimeout['setting_value'])) {
            $sessionTimeout = (int)$dbTimeout['setting_value'] * 60; // Convert minutes to seconds
        }
    } catch (Exception $e) {
        // Table might not exist, use default
    }
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['session_expired'] = true;
    }
    $_SESSION['last_activity'] = time();
    
    // Validate session token for single device login enforcement
    // If user logged in from another device, this session becomes invalid
    // DISABLED IN DEVELOPMENT - enable in production for security
    $enforceSingleDevice = IS_PRODUCTION; // Only enforce in production
    
    if ($enforceSingleDevice && isset($_SESSION['doctor_id']) && isset($_SESSION['session_token'])) {
        try {
            $doctor = DB::queryOne("SELECT session_token FROM doctors WHERE id = ?", [$_SESSION['doctor_id']]);
            if ($doctor && isset($doctor['session_token']) && $doctor['session_token'] !== $_SESSION['session_token']) {
                // Session was invalidated (user logged in from another device)
                $oldDoctorId = $_SESSION['doctor_id'];
                session_unset();
                session_destroy();
                session_start();
                $_SESSION['session_invalidated'] = true;
                $_SESSION['invalidated_message'] = 'You have been logged out because your account was accessed from another device.';
                error_log("Session invalidated for doctor $oldDoctorId - logged in from another device");
            }
        } catch (Exception $e) {
            // Column might not exist yet, skip validation
        }
    }
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (IS_PRODUCTION) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy for production (strengthened)
if (IS_PRODUCTION) {
    header("Content-Security-Policy: default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.google-analytics.com https://www.googletagmanager.com https://www.googleadservices.com https://googleads.g.doubleclick.net https://ad.doubleclick.net; " .
           "script-src-elem 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.google-analytics.com https://www.googletagmanager.com https://www.googleadservices.com https://googleads.g.doubleclick.net https://ad.doubleclick.net; " .
           "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
           "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "img-src 'self' data: blob: https:; " .
           "connect-src 'self' https://www.google-analytics.com https://analytics.google.com https://region1.google-analytics.com https://region1.analytics.google.com https://www.googletagmanager.com https://stats.g.doubleclick.net https://www.googleadservices.com https://googleads.g.doubleclick.net https://ad.doubleclick.net https://www.google.com https://www.google.co.in https://www.google.com.sa https://generativelanguage.googleapis.com https://cdn.jsdelivr.net; " .
           "frame-src 'self' https://www.googletagmanager.com https://googleads.g.doubleclick.net https://ad.doubleclick.net; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self';");
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    
    if ($csrfToken !== $sessionToken) {
        $currentScript = basename($_SERVER['PHP_SELF']);
        $currentDir = basename(dirname($_SERVER['PHP_SELF']));
        
        // API endpoints: Require X-CSRF-Token header for state-changing operations
        if ($currentDir === 'api') {
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            // Security: API endpoints must have valid CSRF token in header for authenticated sessions
            if (isset($_SESSION['doctor_id']) || isset($_SESSION['admin_id'])) {
                // Authenticated sessions MUST have valid CSRF token
                if (empty($headerToken) || $headerToken !== $sessionToken) {
                    // List of read-only APIs that don't need CSRF (GET-like behavior)
                    $readOnlyApis = ['get_messages.php', 'get_notifications.php', 'get_announcements.php'];
                    if (!in_array($currentScript, $readOnlyApis)) {
                        error_log("CSRF validation failed for API: " . $currentScript);
                        http_response_code(403);
                        die(json_encode(['success' => false, 'error' => 'CSRF token validation failed']));
                    }
                }
            } else {
                // Unauthenticated API calls - allow specific public endpoints only
                $publicApis = ['chatbot.php']; // Public chatbot doesn't need session
                if (!in_array($currentScript, $publicApis)) {
                    error_log("Unauthenticated API access attempt: " . $currentScript);
                }
            }
        }
        // Login/register pages - log but allow (they have their own validation)
        elseif (in_array($currentScript, ['login.php', 'register.php', 'forgot_password.php'])) {
            // These pages handle their own validation - allow but log
            error_log("CSRF token mismatch on auth page: " . $currentScript . " (may be new session)");
        }
        // All other pages require valid CSRF token
        else {
            error_log("CSRF validation failed for: " . $currentScript . " | Posted token: " . ($csrfToken ?: 'none') . " | Session token: " . ($sessionToken ?: 'none'));
            http_response_code(403);
            die('CSRF token validation failed. Please refresh the page and try again.');
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateToken();
}

// Maintenance Mode Check - Block write operations for non-admins
// Must be after functions.php is loaded (via require in individual pages)
// This is handled in the header.php for UI and individual pages for API
