<?php
/**
 * API - Get Active Announcements for Doctor
 */

// Prevent any output before JSON
ob_start();

// Set error handling
ini_set('display_errors', 0);
error_reporting(0);

// Clean any accidental output
ob_end_clean();

header('Content-Type: application/json');

// Simple function to output JSON and exit
function outputJson($data) {
    echo json_encode($data);
    exit;
}

try {
    // Load configuration file first to get proper database credentials
    define('APP_ACCESS', true);
    
    // Load environment variables from .env if it exists
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (!empty($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
    
    // Start session with same name as the app
    session_name('HOMEO_SESSION');
    session_start();
    
    // Check if doctor is logged in
    if (!isset($_SESSION['doctor_id']) || empty($_SESSION['doctor_id'])) {
        outputJson(['success' => false, 'announcements' => [], 'error' => 'not_logged_in']);
    }
    
    $doctorId = $_SESSION['doctor_id'];
    
    // Get database credentials from environment or use defaults (matching config.php)
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'homeo_db';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';
    
    // Database connection
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Set timezone to match PHP
    $pdo->exec("SET time_zone = '+05:30'");
    
    // Query to get announcements not yet dismissed by this doctor
    $sql = "
        SELECT a.* 
        FROM announcements a
        LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.doctor_id = ?
        WHERE a.is_active = 1
        AND (a.target_audience = 'all' OR a.target_audience = 'doctors')
        AND (a.start_date IS NULL OR a.start_date <= NOW())
        AND (a.end_date IS NULL OR a.end_date >= NOW())
        AND ar.id IS NULL
        ORDER BY a.priority DESC, a.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctorId]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format announcements
    foreach ($announcements as &$ann) {
        $ann['formatted_date'] = date('M j, Y', strtotime($ann['created_at']));
        $ann['show_popup'] = isset($ann['show_popup']) ? (int)$ann['show_popup'] : 1;
    }
    
    outputJson([
        'success' => true,
        'announcements' => $announcements,
        'count' => count($announcements)
    ]);
    
} catch (Exception $e) {
    outputJson([
        'success' => false,
        'error' => $e->getMessage(),
        'announcements' => []
    ]);
}
