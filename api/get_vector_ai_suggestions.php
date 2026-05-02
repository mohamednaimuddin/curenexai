<?php
/**
 * API Endpoint: Get Vector-based AI Suggestions
 * 
 * This is the new API that uses vector embeddings for RAG
 * Returns both Vector RAG and Gemini AI suggestions
 */

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header early
header('Content-Type: application/json');

// Global exception handler
set_exception_handler(function($e) {
    error_log("Vector AI API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

// Custom error handler to return JSON
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/rate_limiter.php';
    require_once __DIR__ . '/../includes/vector_rag.php';

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in']);
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
    enforceRateLimit(RateLimiter::getClientKey('vector_ai'), 10, 60);

    $doctor_id = $_SESSION['doctor_id'];
    $consultation_id = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;

    if ($consultation_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid consultation ID']);
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
        echo json_encode(['success' => false, 'error' => 'Consultation not found or access denied']);
        exit;
    }
    
    // Fetch symptoms
    $symptoms = DB::query(
        "SELECT symptom_text as symptom, intensity as severity, duration, location, sensation, modality,
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
    
    // Initialize response
    $response = [
        'success' => true,
        'patient' => [
            'name' => $consultation['patient_name'],
            'age' => $consultation['age'],
            'gender' => $consultation['gender'],
            'chief_complaint' => $consultation['chief_complaint']
        ],
        'rag' => null,
        'gemini' => null
    ];
    
    // ============================================
    // 1. Vector RAG (Semantic Search) Suggestions
    // ============================================
    try {
        $vectorRag = new VectorRAG();
        
        // Check if embeddings are available
        if (!$vectorRag->hasEmbeddings('remedy')) {
            // Fall back to traditional RAG if no embeddings
            $response['rag'] = [
                'error' => 'Vector embeddings not generated. Please run: php generate_embeddings.php',
                'remedies' => [],
                'fallback' => true
            ];
            
            // Try traditional RAG as fallback
            require_once __DIR__ . '/get_dual_ai_suggestions.php';
            // Note: The traditional RAG function is in get_dual_ai_suggestions.php
            // We'll include it if vector search isn't available
        } else {
            // Use vector-based RAG
            $ragSuggestions = $vectorRag->generateSuggestions($consultation);
            $response['rag'] = $ragSuggestions;
            $response['rag']['method'] = 'vector_embeddings';
            $response['rag']['embedding_stats'] = $vectorRag->getEmbeddingStats();
        }
    } catch (Exception $e) {
        error_log('Vector RAG Error: ' . $e->getMessage());
        $response['rag'] = ['error' => $e->getMessage()];
    }
    
    // ============================================
    // 2. Gemini AI Suggestions
    // ============================================
    if (AI_PROVIDER === 'gemini' && !empty(GEMINI_API_KEY)) {
        try {
            require_once BASE_PATH . '/includes/gemini_api.php';
            $gemini = new GeminiAPI();
            $result = $gemini->generateRemedySuggestions($consultation);
            
            if ($result['success']) {
                $response['gemini'] = [
                    'remedies' => $result['suggestions']['remedies'] ?? [],
                    'case_analysis' => $result['suggestions']['case_analysis'] ?? '',
                    'cautions' => $result['suggestions']['cautions'] ?? '',
                    'model' => $result['model'] ?? 'gemini'
                ];
            } else {
                $response['gemini'] = ['error' => $result['error']];
            }
        } catch (Exception $e) {
            $response['gemini'] = ['error' => $e->getMessage()];
        }
    } else {
        $response['gemini'] = ['error' => 'Gemini API not configured'];
    }
    
    // Log the combined suggestions
    try {
        DB::query(
            "INSERT INTO ai_suggestions_log 
             (consultation_id, doctor_id, symptoms_sent, ai_response, suggested_remedies, api_used, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $consultation_id,
                $doctor_id,
                'Vector AI analysis (Embeddings + Gemini)',
                json_encode($response),
                json_encode(array_merge(
                    $response['rag']['remedies'] ?? [],
                    $response['gemini']['remedies'] ?? []
                )),
                'vector_rag_gemini'
            ]
        );
    } catch (Exception $logError) {
        error_log('Failed to log AI suggestions: ' . $logError->getMessage());
    }
    
    echo json_encode($response);
    
} catch (Throwable $e) {
    error_log('Vector AI Suggestions API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}
