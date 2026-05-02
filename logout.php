<?php
require_once 'includes/init.php';

// Log activity and invalidate session before logout
if (isLoggedIn()) {
    $doctorId = $_SESSION['doctor_id'];
    $sessionToken = $_SESSION['session_token'] ?? null;
    
    // Invalidate the session in database
    if ($sessionToken) {
        try {
            DB::query("UPDATE doctor_sessions SET is_active = 0 WHERE session_token = ?", [$sessionToken]);
            DB::update('doctors', ['session_token' => null, 'session_expires_at' => null], 'id = ?', [$doctorId]);
        } catch (Exception $e) {
            // Columns might not exist, continue
        }
    }
    
    logActivity('logout', 'Doctor logged out', $doctorId);
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy session
session_unset();
session_destroy();

// Start new session for CSRF token on login page
session_start();
session_regenerate_id(true);

// Redirect to login
header('Location: ' . APP_URL . '/login.php');
exit;
