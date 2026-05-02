<?php
/**
 * API Endpoint: Get Gemini AI Disease Diagnosis
 * 
 * Uses Google Gemini AI for advanced diagnosis suggestions
 * Provides AI-enhanced analysis complementing the RAG-based local database
 */

// Prevent any output before headers
ob_start();

// Enable error logging, disable display
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header FIRST
header('Content-Type: application/json');

// Global error handler to catch all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Gemini Diagnosis Error [$errno]: $errstr in $errfile:$errline");
    return false;
});

// Global exception handler
set_exception_handler(function($e) {
    ob_clean();
    error_log("Gemini Diagnosis API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

// Shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        error_log("Gemini Diagnosis Fatal Error: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message']
        ]);
    }
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Rate limiter is optional - don't fail if it's missing
    $rateLimiterPath = __DIR__ . '/../includes/rate_limiter.php';
    if (file_exists($rateLimiterPath)) {
        require_once $rateLimiterPath;
    }

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    // Check authentication
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in']);
        exit;
    }

    // Check if AI is enabled
    if (function_exists('isAiEnabled') && !isAiEnabled()) {
        echo json_encode([
            'success' => false,
            'error' => 'AI suggestions are currently disabled by the administrator.'
        ]);
        exit;
    }

    // Check if Gemini is configured
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        echo json_encode([
            'success' => false,
            'error' => 'Gemini API is not configured. Please add your API key in the admin settings.'
        ]);
        exit;
    }

    // Apply rate limiting (5 Gemini diagnosis requests per minute) - if available
    if (function_exists('enforceRateLimit') && class_exists('RateLimiter')) {
        enforceRateLimit(RateLimiter::getClientKey('gemini_diagnosis'), 5, 60);
    }

    $doctor_id = $_SESSION['doctor_id'];

    // Get input data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (empty($input)) {
        $input = $_POST;
    }

    // Extract input fields
    $symptomsText = trim($input['symptoms'] ?? '');
    $chiefComplaint = trim($input['chief_complaint'] ?? '');
    $labTests = trim($input['lab_tests'] ?? '');
    $physicalExam = trim($input['physical_exam'] ?? '');
    $patientAge = $input['age'] ?? null;
    $patientGender = $input['gender'] ?? null;

    // Combine all text
    $allText = trim($symptomsText . ' ' . $chiefComplaint);

    if (empty($allText)) {
        echo json_encode([
            'success' => false,
            'error' => 'Please provide symptoms or chief complaint for diagnosis.'
        ]);
        exit;
    }

    // Load Gemini API
    require_once __DIR__ . '/../includes/gemini_api.php';

    // Build diagnosis prompt
    $prompt = buildDiagnosisPrompt(
        $symptomsText,
        $chiefComplaint,
        $labTests,
        $physicalExam,
        $patientAge,
        $patientGender
    );

    // Call Gemini API - generateContent throws exception on failure
    $gemini = new GeminiAPI();
    $result = $gemini->generateContent($prompt, [
        'temperature' => 0.3,
        'maxTokens' => 3000
    ]);

    // Result contains: text, finish_reason, safety_ratings
    if (empty($result['text'])) {
        throw new Exception('Empty response from Gemini AI');
    }

    // Parse Gemini response
    $diagnoses = parseGeminiDiagnosisResponse($result['text']);

    // Log the diagnosis (non-blocking - don't fail if table structure differs)
    try {
        // Check if api_used column exists
        $tableInfo = DB::query("SHOW COLUMNS FROM diagnosis_logs LIKE 'api_used'");
        if (!empty($tableInfo)) {
            DB::query(
                "INSERT INTO diagnosis_logs 
                 (doctor_id, input_symptoms, input_clinical_findings, input_lab_tests, 
                  suggested_diagnoses, confidence_scores, ai_analysis, api_used, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'gemini', NOW())",
                [
                    $doctor_id,
                    $symptomsText,
                    $chiefComplaint . ' ' . $physicalExam,
                    $labTests,
                    json_encode($diagnoses),
                    json_encode(array_column($diagnoses, 'confidence')),
                    $result['text']
                ]
            );
        } else {
            // Fallback without api_used column
            DB::query(
                "INSERT INTO diagnosis_logs 
                 (doctor_id, input_symptoms, input_clinical_findings, input_lab_tests, 
                  suggested_diagnoses, confidence_scores, ai_analysis, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $doctor_id,
                    $symptomsText,
                    $chiefComplaint . ' ' . $physicalExam,
                    $labTests,
                    json_encode($diagnoses),
                    json_encode(array_column($diagnoses, 'confidence')),
                    $result['text']
                ]
            );
        }
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log('Failed to log Gemini diagnosis: ' . $e->getMessage());
    }

    // Clean output buffer before sending response
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'diagnoses' => $diagnoses,
        'provider' => 'gemini',
        'model' => GEMINI_MODEL ?? 'gemini-2.0-flash',
        'disclaimer' => 'AI-generated suggestions for educational purposes only. Always confirm with proper clinical examination.'
    ]);

} catch (Throwable $e) {
    ob_clean();
    error_log('Gemini Diagnosis API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Build comprehensive diagnosis prompt for Gemini
 */
function buildDiagnosisPrompt($symptoms, $chiefComplaint, $labTests, $physicalExam, $age, $gender) {
    $prompt = <<<PROMPT
You are an expert medical diagnostician and homeopathic physician. Analyze the following patient case and provide differential diagnoses with homeopathic remedy suggestions.

PATIENT INFORMATION:
PROMPT;

    if ($age) $prompt .= "\n- Age: {$age} years";
    if ($gender) $prompt .= "\n- Gender: " . ucfirst($gender);

    $prompt .= "\n\nSYMPTOMS:\n{$symptoms}";
    
    if (!empty($chiefComplaint)) {
        $prompt .= "\n\nCHIEF COMPLAINT:\n{$chiefComplaint}";
    }
    
    if (!empty($physicalExam)) {
        $prompt .= "\n\nPHYSICAL EXAMINATION:\n{$physicalExam}";
    }
    
    if (!empty($labTests)) {
        $prompt .= "\n\nLABORATORY FINDINGS:\n{$labTests}";
    }

    $prompt .= <<<PROMPT


Based on the above information, provide your analysis in the following JSON format:
{
    "diagnoses": [
        {
            "diagnosis": "Disease Name",
            "confidence": "High/Medium/Low",
            "matching_symptoms": ["symptom1", "symptom2"],
            "supporting_findings": "Explanation of why this diagnosis fits",
            "notes_for_doctor": "Important clinical considerations",
            "homeopathic_remedies": [
                {
                    "remedy_name": "Remedy Name (Abbreviation)",
                    "common_name": "Common name if applicable",
                    "indication_strength": "primary/secondary/supportive",
                    "specific_indication": "Why this remedy is indicated",
                    "potency": "Suggested potency (e.g., 30C, 200C)"
                }
            ]
        }
    ],
    "differential_notes": "Brief note on how to differentiate between diagnoses",
    "red_flags": "Any urgent symptoms that need immediate attention"
}

Provide 2-5 most likely diagnoses ranked by probability. For each diagnosis, suggest 2-4 homeopathic remedies with their specific indications based on classical homeopathic literature (Boericke, Kent, Allen).

IMPORTANT: 
1. Return ONLY valid JSON, no additional text
2. Be specific about symptom matching
3. Include both conventional disease names and homeopathic remedy suggestions
4. Mention potency and repetition guidelines where applicable
PROMPT;

    return $prompt;
}

/**
 * Parse Gemini's diagnosis response into structured format
 */
function parseGeminiDiagnosisResponse($responseText) {
    // Try to extract JSON from response
    $jsonStart = strpos($responseText, '{');
    $jsonEnd = strrpos($responseText, '}');
    
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonStr = substr($responseText, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonStr, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['diagnoses'])) {
            // Format diagnoses to match frontend expectations
            $formattedDiagnoses = [];
            foreach ($data['diagnoses'] as $diag) {
                $formattedDiagnoses[] = [
                    'diagnosis' => $diag['diagnosis'] ?? 'Unknown',
                    'confidence' => $diag['confidence'] ?? 'Low',
                    'matching_symptoms' => array_values($diag['matching_symptoms'] ?? []),
                    'supporting_findings' => $diag['supporting_findings'] ?? '',
                    'notes_for_doctor' => $diag['notes_for_doctor'] ?? '',
                    'homeopathic_remedies' => $diag['homeopathic_remedies'] ?? [],
                    'source' => 'gemini'
                ];
            }
            return $formattedDiagnoses;
        }
    }
    
    // Fallback: Try to parse as best effort if JSON parsing failed
    error_log("Gemini Diagnosis: Failed to parse JSON, raw response: " . substr($responseText, 0, 500));
    
    return [[
        'diagnosis' => 'AI Analysis Available',
        'confidence' => 'Medium',
        'matching_symptoms' => [],
        'supporting_findings' => $responseText,
        'notes_for_doctor' => 'Please review the AI analysis above for detailed suggestions.',
        'homeopathic_remedies' => [],
        'source' => 'gemini'
    ]];
}
