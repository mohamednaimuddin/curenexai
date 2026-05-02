<?php
/**
 * Helper Functions
 */

/**
 * Get a system setting from the database
 * @param string $key The setting key
 * @param mixed $default Default value if setting not found
 * @return mixed The setting value or default
 */
function getSystemSetting($key, $default = null) {
    static $settingsCache = [];
    
    // Check cache first
    if (isset($settingsCache[$key])) {
        return $settingsCache[$key];
    }
    
    try {
        $setting = DB::queryOne("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?", [$key]);
        if ($setting) {
            $value = $setting['setting_value'];
            // Convert based on type
            if ($setting['setting_type'] === 'boolean') {
                $value = ($value === 'true' || $value === '1');
            } elseif ($setting['setting_type'] === 'number') {
                $value = is_numeric($value) ? (int)$value : $default;
            }
            $settingsCache[$key] = $value;
            return $value;
        }
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
    $settingsCache[$key] = $default;
    return $default;
}

/**
 * Check if AI suggestions are enabled
 */
function isAiEnabled() {
    return getSystemSetting('enable_ai_suggestions', true);
}

/**
 * Check if Gemini API is enabled
 */
function isGeminiEnabled() {
    return getSystemSetting('gemini_api_enabled', true);
}

/**
 * Get session timeout in minutes
 */
function getSessionTimeoutMinutes() {
    return getSystemSetting('session_timeout_minutes', 60);
}

/**
 * Get max login attempts
 */
function getMaxLoginAttempts() {
    return getSystemSetting('max_login_attempts', 5);
}

/**
 * Get lockout duration in minutes
 */
function getLockoutDurationMinutes() {
    return getSystemSetting('lockout_duration_minutes', 15);
}

/**
 * Check if maintenance mode is enabled
 */
function isMaintenanceMode() {
    return getSystemSetting('maintenance_mode', false);
}

/**
 * Check if current user can bypass maintenance mode (super admins only)
 */
function canBypassMaintenance() {
    return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
}

/**
 * Block write operations in maintenance mode
 * Returns true if the operation should be blocked
 */
function shouldBlockInMaintenance() {
    // Super admins can always write
    if (canBypassMaintenance()) {
        return false;
    }
    
    // Block if maintenance mode is on and it's a write operation
    if (isMaintenanceMode() && $_SERVER['REQUEST_METHOD'] === 'POST') {
        return true;
    }
    
    return false;
}

/**
 * Block write operations if in maintenance mode
 * Call this at the beginning of POST handlers
 * Returns true if blocked (and sets flash message), false if allowed
 */
function blockIfMaintenance() {
    if (shouldBlockInMaintenance()) {
        if (function_exists('setFlash')) {
            setFlash('error', 'System is in maintenance mode. Changes cannot be saved at this time.');
        }
        return true;
    }
    return false;
}

/**
 * API response for maintenance mode
 * Use this for API endpoints
 */
function apiMaintenanceResponse() {
    if (shouldBlockInMaintenance()) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'System is in maintenance mode. Changes cannot be saved at this time.',
            'maintenance' => true
        ]);
        exit;
    }
}

/**
 * Sanitize input data
 */
function sanitize($data, $maxLength = 10000) {
    if (is_array($data)) {
        return array_map(function($item) use ($maxLength) {
            return sanitize($item, $maxLength);
        }, $data);
    }
    $data = htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    // Enforce maximum length to prevent oversized inputs
    if (strlen($data) > $maxLength) {
        $data = substr($data, 0, $maxLength);
    }
    return $data;
}

/**
 * Sanitize with specific max length
 */
function sanitizeWithLength($data, $maxLength) {
    return sanitize($data, $maxLength);
}

/**
 * Validate string length
 */
function validateLength($value, $min = 0, $max = null) {
    $length = strlen($value);
    if ($length < $min) return false;
    if ($max !== null && $length > $max) return false;
    return true;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Redirect to URL
 */
function redirect($url) {
    // Clean the URL - remove any duplicate base paths
    $url = ltrim($url, '/');
    
    // If URL already contains the full APP_URL or starts with http, use it directly
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        header("Location: " . $url);
        exit;
    }
    
    // Build the full URL
    $fullUrl = rtrim(APP_URL, '/') . '/' . $url;
    
    header("Location: " . $fullUrl);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['doctor_id']) && !empty($_SESSION['doctor_id']);
}

/**
 * Get logged in doctor ID
 */
function getLoggedInDoctorId() {
    return $_SESSION['doctor_id'] ?? null;
}

/**
 * Get logged in doctor info
 */
function getLoggedInDoctor() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $doctorId = getLoggedInDoctorId();
    return DB::queryOne("SELECT * FROM doctors WHERE id = ?", [$doctorId]);
}

/**
 * Require login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('/login.php');
    }
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount, $symbol = '₹') {
    return $symbol . number_format($amount, 2);
}

/**
 * Upload file
 */
function uploadFile($file, $uploadDir, $allowedTypes = ALLOWED_DOC_TYPES) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum limit'];
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
    $destination = $uploadDir . '/' . $newFileName;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
    
    return [
        'success' => true,
        'filename' => $newFileName,
        'path' => $destination,
        'size' => $file['size'],
        'type' => $fileExt
    ];
}

/**
 * Delete file
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Get file icon based on extension
 */
function getFileIcon($extension) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'gif' => 'fa-file-image'
    ];
    
    return $icons[$extension] ?? 'fa-file';
}

/**
 * Generate pagination HTML with beautiful modern design
 */
function pagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $currentPage = (int)$currentPage;
    $totalPages = (int)$totalPages;
    
    // Parse URL for query string handling
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    
    $html = '<nav class="pagination-nav" aria-label="Page navigation">';
    $html .= '<ul class="pagination">';
    
    // First & Previous buttons
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1" title="First"><i class="fas fa-angle-double-left"></i></a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '" title="Previous"><i class="fas fa-angle-left"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-left"></i></span></li>';
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-left"></i></span></li>';
    }
    
    // Calculate page range to show
    $range = 2; // Show 2 pages on each side of current
    $startPage = max(1, $currentPage - $range);
    $endPage = min($totalPages, $currentPage + $range);
    
    // Adjust range if at edges
    if ($currentPage <= $range) {
        $endPage = min($totalPages, $range * 2 + 1);
    }
    if ($currentPage > $totalPages - $range) {
        $startPage = max(1, $totalPages - $range * 2);
    }
    
    // Show first page with ellipsis if needed
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>';
        }
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Show last page with ellipsis if needed
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link page-ellipsis">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next & Last buttons
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '" title="Next"><i class="fas fa-angle-right"></i></a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '" title="Last"><i class="fas fa-angle-double-right"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-right"></i></span></li>';
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-angle-double-right"></i></span></li>';
    }
    
    $html .= '</ul>';
    
    // Page info text
    $html .= '<div class="pagination-info">Page ' . $currentPage . ' of ' . $totalPages . '</div>';
    
    $html .= '</nav>';
    return $html;
}

/**
 * Log activity
 */
function logActivity($action, $details = '', $doctorId = null) {
    if ($doctorId === null) {
        $doctorId = getLoggedInDoctorId();
    }
    
    // Log to file
    $logFile = BASE_PATH . '/logs/activity_' . date('Y-m-d') . '.log';
    $logMessage = sprintf(
        "[%s] Doctor ID: %s | Action: %s | Details: %s | IP: %s\n",
        date('Y-m-d H:i:s'),
        $doctorId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also log to database for superadmin tracking
    if ($doctorId) {
        logDoctorActivity($doctorId, $action, $details);
    }
}

/**
 * Log doctor activity to database
 */
function logDoctorActivity($doctorId, $action, $details = null, $targetType = null, $targetId = null) {
    try {
        DB::insert('doctor_activity_logs', [
            'doctor_id' => $doctorId,
            'action' => $action,
            'details' => $details,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // Table might not exist, silently fail
        error_log("Failed to log doctor activity: " . $e->getMessage());
    }
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get client IP address
 */
function getClientIp() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('d M Y', $time);
    }
}

/**
 * Get symptom category badge color
 */
function getCategoryColor($category) {
    $colors = [
        'mind' => 'primary',
        'head' => 'danger',
        'eye' => 'info',
        'ear' => 'warning',
        'respiratory' => 'success',
        'stomach' => 'secondary',
        'skin' => 'dark',
        'general' => 'light'
    ];
    
    return $colors[$category] ?? 'secondary';
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Regenerate CSRF token (for security after sensitive actions)
 */
function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Check if super admin is logged in
 */
function isSuperAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']) && isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
}

/**
 * Get logged in admin ID
 */
function getLoggedInAdminId() {
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Require super admin login
 */
function requireSuperAdmin() {
    if (!isSuperAdminLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: " . rtrim(APP_URL, '/') . "/superadmin/login.php");
        exit;
    }
}

/**
 * Log admin activity
 */
function logAdminActivity($adminId, $action, $details = null, $targetType = null, $targetId = null) {
    try {
        DB::insert('admin_activity_logs', [
            'admin_id' => $adminId,
            'action' => $action,
            'details' => $details,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}
