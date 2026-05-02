<?php
/**
 * Super Admin - Get Messages API
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'messages' => [], 'count' => 0]);
    exit;
}

$messages = [];
$count = 0;

try {
    // Get active announcements
    $announcements = DB::query("
        SELECT a.*, sa.full_name as admin_name 
        FROM announcements a 
        JOIN super_admins sa ON a.admin_id = sa.id 
        WHERE a.is_active = 1
        AND (a.start_date IS NULL OR a.start_date <= NOW())
        AND (a.end_date IS NULL OR a.end_date >= NOW())
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    
    foreach ($announcements as $ann) {
        $messages[] = [
            'title' => $ann['title'],
            'preview' => substr($ann['content'], 0, 50) . '...',
            'avatar' => strtoupper(substr($ann['admin_name'], 0, 1)),
            'type' => $ann['type'],
            'link' => 'announcements.php'
        ];
    }
    
    $count = count($messages);
    
} catch (Exception $e) {
    // Table might not exist yet
}

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'count' => $count
]);
