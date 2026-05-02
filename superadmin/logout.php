<?php
/**
 * Super Admin Logout
 */

require_once __DIR__ . '/../includes/init.php';

if (isSuperAdminLoggedIn()) {
    // Log activity
    logAdminActivity($_SESSION['admin_id'], 'logout', 'Admin logged out');
}

// Clear admin session data
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);
unset($_SESSION['is_super_admin']);
unset($_SESSION['admin_permissions']);

// Redirect to login
header("Location: " . rtrim(APP_URL, '/') . "/superadmin/login.php");
exit;
