<?php
/**
 * Super Admin - Get Notifications API
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'notifications' => [], 'count' => 0]);
    exit;
}

$notifications = [];
$count = 0;

try {
    // Get recent doctor registrations (last 24 hours)
    $newDoctors = DB::query("
        SELECT id, full_name, created_at 
        FROM doctors 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    foreach ($newDoctors as $doctor) {
        $notifications[] = [
            'title' => 'New Doctor: ' . $doctor['full_name'],
            'time' => timeAgo($doctor['created_at']),
            'icon' => 'bi-person-plus',
            'type' => 'success',
            'link' => 'doctor_details.php?id=' . $doctor['id']
        ];
    }
    
    // Get recent activity logs
    $recentLogs = DB::query("
        SELECT al.*, sa.full_name as admin_name 
        FROM admin_activity_logs al 
        JOIN super_admins sa ON al.admin_id = sa.id 
        WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND al.admin_id != ?
        ORDER BY al.created_at DESC 
        LIMIT 5
    ", [$_SESSION['admin_id']]);
    
    foreach ($recentLogs as $log) {
        $notifications[] = [
            'title' => $log['admin_name'] . ': ' . $log['action'],
            'time' => timeAgo($log['created_at']),
            'icon' => 'bi-activity',
            'type' => 'info',
            'link' => 'activity_logs.php'
        ];
    }
    
    // Get doctors pending approval (if any have status = 'pending')
    $pendingDoctors = DB::query("
        SELECT COUNT(*) as count 
        FROM doctors 
        WHERE status = 'inactive' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    if ($pendingDoctors && $pendingDoctors[0]['count'] > 0) {
        $notifications[] = [
            'title' => $pendingDoctors[0]['count'] . ' inactive doctor(s) need attention',
            'time' => 'Action needed',
            'icon' => 'bi-exclamation-circle',
            'type' => 'warning',
            'link' => 'doctors.php?status=inactive'
        ];
    }
    
    $count = count($notifications);
    
} catch (Exception $e) {
    // Tables might not exist yet
}

echo json_encode([
    'success' => true,
    'notifications' => array_slice($notifications, 0, 10),
    'count' => $count
]);
