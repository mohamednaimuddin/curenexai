<?php
/**
 * API: Get Remedy Information
 */
require_once '../includes/init.php';
require_once '../includes/rate_limiter.php';

header('Content-Type: application/json');

// Check if logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Apply rate limiting (100 requests per minute)
enforceRateLimit(RateLimiter::getClientKey('remedy_api'), 100, 60);

// If 'id' is provided, return single remedy
if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $id = (int)$_GET['id'];
    $remedy = DB::queryOne("SELECT * FROM remedies WHERE id = ?", [$id]);
    if (!$remedy) {
        echo json_encode(['success' => false, 'error' => 'Remedy not found']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'remedy' => $remedy
    ]);
    exit;
}

// Otherwise, handle live search (search, family, sort)
$search = $_GET['search'] ?? '';
$family = $_GET['family'] ?? '';
$sort = $_GET['sort'] ?? 'name';

$query = "SELECT * FROM remedies WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (LOWER(remedy_name) LIKE ? OR LOWER(common_name) LIKE ? OR LOWER(remedy_short_name) LIKE ? OR LOWER(keynote_symptoms) LIKE ?)";
    $searchTerm = "%" . mb_strtolower(trim($search)) . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if (!empty($family)) {
    $query .= " AND family = ?";
    $params[] = $family;
}
$validSorts = ['name' => 'remedy_name', 'family' => 'family', 'recent' => 'created_at DESC'];
$orderBy = $validSorts[$sort] ?? 'remedy_name';
$query .= " ORDER BY $orderBy";

$remedies = DB::query($query . " COLLATE utf8mb4_unicode_ci", $params);
if (!is_array($remedies)) {
    $remedies = [];
}

echo json_encode([
    'success' => true,
    'remedies' => $remedies
]);
