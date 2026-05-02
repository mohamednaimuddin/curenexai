<?php
/**
 * API Endpoint: Get AI Suggestions
 * Returns AI-powered remedy suggestions for a consultation
 */

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Set JSON header early
header('Content-Type: application/json');

// Global error handler to ensure JSON response
set_exception_handler(function($e) {
    error_log("AI API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("AI API Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

try {
    // Use the standard init approach
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/rate_limiter.php';

    // Start session with proper configuration (same as init.php)
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Please log in'
        ]);
        exit;
    }

    // Check if AI suggestions are enabled in system settings
    if (!isAiEnabled()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'AI suggestions are currently disabled by the administrator.'
        ]);
        exit;
    }

    // Apply rate limiting (10 AI requests per minute per user)
    enforceRateLimit(RateLimiter::getClientKey('ai_suggestions'), 10, 60);

    $doctor_id = $_SESSION['doctor_id'];
    $consultation_id = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;

    if ($consultation_id <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid consultation ID'
        ]);
        exit;
    }

    // Fetch consultation details
    $consultation = DB::queryOne(
        "SELECT c.*, 
                COALESCE(p.patient_name, 'Unknown') as patient_name, 
                COALESCE(p.age, 0) as age, 
                COALESCE(p.gender, 'unknown') as gender, 
                COALESCE(p.blood_group, '') as blood_group, 
                COALESCE(p.allergies, '') as allergies
         FROM consultations c
         LEFT JOIN patients p ON c.patient_id = p.id
         WHERE c.id = ?",
        [$consultation_id]
    );
    
    // Security check
    if (!$consultation || $consultation['doctor_id'] != $doctor_id) {
        echo json_encode([
            'success' => false,
            'error' => 'Consultation not found or access denied'
        ]);
        exit;
    }
    
    // Fetch symptoms
    $symptoms = DB::query(
        "SELECT symptom_text as symptom, intensity as severity, duration, 
                CONCAT_WS(', ', location, sensation, modality) as notes
         FROM symptoms
         WHERE consultation_id = ?
         ORDER BY 
            CASE intensity 
                WHEN 'severe' THEN 3
                WHEN 'moderate' THEN 2
                WHEN 'mild' THEN 1
                ELSE 0
            END DESC,
            symptom_text",
        [$consultation_id]
    );
    
    $consultation['symptoms'] = $symptoms ?: [];
    
    // Check for existing cached suggestions
    $existing_suggestion = DB::queryOne(
        "SELECT * FROM ai_suggestions_log
         WHERE consultation_id = ?
         ORDER BY created_at DESC
         LIMIT 1",
        [$consultation_id]
    );
    
    $suggestions = null;
    $usedCache = false;
    
    // Use cached if exists and less than 1 hour old
    if ($existing_suggestion && (time() - strtotime($existing_suggestion['created_at'])) < 3600) {
        $suggestions = json_decode($existing_suggestion['ai_response'], true);
        $usedCache = true;
    }
    
    // Generate new suggestions if no cache
    if (!$suggestions) {
        if (defined('AI_PROVIDER') && AI_PROVIDER === 'gemini' && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
            // Use Gemini AI
            require_once BASE_PATH . '/includes/gemini_api.php';
            $gemini = new GeminiAPI();
            $result = $gemini->generateRemedySuggestions($consultation);
            
            if ($result['success']) {
                $suggestions = $result['suggestions'];
                $suggestions['provider'] = 'gemini';
                $suggestions['model'] = $result['model'];
                
                // Log to database
                DB::execute(
                    "INSERT INTO ai_suggestions_log 
                     (consultation_id, doctor_id, prompt, ai_response, suggested_remedies, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [
                        $consultation_id,
                        $doctor_id,
                        'Gemini AI inline analysis',
                        json_encode($suggestions),
                        json_encode($suggestions['remedies'] ?? []),
                    ]
                );
            } else {
                throw new Exception($result['error']);
            }
        } else {
            // Use local RAG
            require_once BASE_PATH . '/ai/suggestions.php';
            $ragResult = generateRAGSuggestions($consultation);
            
            if ($ragResult['success']) {
                $suggestions = $ragResult['data'];
                $suggestions['provider'] = 'local_rag';
                
                // Log to database
                DB::execute(
                    "INSERT INTO ai_suggestions_log 
                     (consultation_id, doctor_id, prompt, ai_response, suggested_remedies, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [
                        $consultation_id,
                        $doctor_id,
                        'RAG-based inline analysis',
                        json_encode($suggestions),
                        json_encode($suggestions['recommendations'] ?? []),
                    ]
                );
                
                // Convert RAG format to standard format
                if (isset($suggestions['recommendations'])) {
                    $suggestions['remedies'] = array_map(function($rec) {
                        return [
                            'name' => $rec['remedy'],
                            'match_percentage' => $rec['match_percentage'],
                            'potency' => $rec['potency'],
                            'reasoning' => $rec['reasoning'],
                            'matching_symptoms' => []
                        ];
                    }, $suggestions['recommendations']);
                }
            } else {
                throw new Exception('Failed to generate suggestions');
            }
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'cached' => $usedCache,
        'patient' => [
            'name' => $consultation['patient_name'],
            'age' => $consultation['age'],
            'gender' => $consultation['gender'],
            'chief_complaint' => $consultation['chief_complaint']
        ],
        'suggestions' => $suggestions
    ]);
    
} catch (Exception $e) {
    error_log('AI Suggestions API Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
