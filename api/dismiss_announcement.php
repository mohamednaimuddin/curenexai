<?php
/**
 * API - Dismiss Announcement
 */

require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

// Check maintenance mode for write operations
apiMaintenanceResponse();

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$doctorId = getLoggedInDoctorId();
$announcementId = intval($_POST['announcement_id'] ?? 0);

if ($announcementId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
    exit;
}

try {
    // Check if read record exists
    $existing = DB::queryOne("SELECT id FROM announcement_reads WHERE announcement_id = ? AND doctor_id = ?", [$announcementId, $doctorId]);
    
    if ($existing) {
        // Update existing record
        DB::update('announcement_reads', [
            'dismissed' => 1,
            'read_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$existing['id']]);
    } else {
        // Insert new record
        DB::insert('announcement_reads', [
            'announcement_id' => $announcementId,
            'doctor_id' => $doctorId,
            'dismissed' => 1
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Table might not exist
    error_log("Dismiss announcement error: " . $e->getMessage());
    echo json_encode(['success' => true]); // Return success anyway to close popup
}
