<?php
/**
 * Super Admin - Mark Notifications as Read API
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

// For now, just return success - could implement read tracking later
echo json_encode(['success' => true]);
