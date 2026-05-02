<?php
/**
 * API endpoint to get remedies for a specific rubric
 */
require_once __DIR__ . '/../includes/init.php';

header('Content-Type: application/json');

// Check login
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$rubricId = isset($_GET['rubric_id']) ? intval($_GET['rubric_id']) : 0;

if (!$rubricId) {
    echo json_encode(['success' => false, 'error' => 'Invalid rubric ID']);
    exit;
}

try {
    // Get remedies for this rubric with their grades
    $sql = "SELECT r.id, r.remedy_name, r.common_name, r.remedy_short_name, rr.grade
            FROM remedies r
            INNER JOIN repertory_remedies rr ON r.id = rr.remedy_id
            WHERE rr.repertory_id = ?
            ORDER BY rr.grade DESC, r.remedy_name ASC";
    
    $remedies = DB::query($sql, [$rubricId]);
    
    // Also get rubric info
    $rubric = DB::queryOne("SELECT * FROM repertory WHERE id = ?", [$rubricId]);
    
    echo json_encode([
        'success' => true,
        'rubric' => $rubric,
        'remedies' => $remedies,
        'count' => count($remedies)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
