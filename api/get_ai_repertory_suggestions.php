<?php
// API: Get AI-powered remedy suggestions for selected rubrics (repertory)
// Enhanced with local knowledge base for better accuracy

// Suppress HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Global exception handler
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

// Custom error handler
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/gemini_api.php';

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $doctor_id = $_SESSION['doctor_id'] ?? 0;
    $rubric_ids = $_POST['rubric_ids'] ?? [];

    // Handle JSON string input (from JavaScript fetch)
    if (is_string($rubric_ids)) {
        $rubric_ids = json_decode($rubric_ids, true) ?? [];
    }

    // Sanitize and validate rubric IDs
    $rubric_ids = array_filter(array_map('intval', (array)$rubric_ids));

    if (empty($rubric_ids)) {
        echo json_encode(['success' => false, 'error' => 'No rubrics selected']);
        exit;
    }

    // Fetch rubric details
    $placeholders = implode(',', array_fill(0, count($rubric_ids), '?'));
    $rubrics = DB::query("SELECT * FROM repertory WHERE id IN ($placeholders)", $rubric_ids);

    if (empty($rubrics)) {
        echo json_encode(['success' => false, 'error' => 'Rubrics not found']);
        exit;
    }

    // STEP 1: Get remedies from local database with repertorization
    $localRemedies = getRepertorizationResults($rubric_ids);
    
    // STEP 2: Enhance with AI if enabled
    $suggestions = null;
    
    if (isAiEnabled() && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        // Build AI prompt with local knowledge
        $prompt = buildEnhancedPrompt($rubrics, $localRemedies);
        
        // Call Gemini
        $gemini = new GeminiAPI();
        $result = $gemini->generateContent($prompt, ['temperature' => 0.3, 'maxTokens' => 2048]);
        
        if (!empty($result['text'])) {
            // Parse JSON
            $jsonPattern = '/\{.*\}/s';
            if (preg_match($jsonPattern, $result['text'], $matches)) {
                $jsonText = $matches[0];
                $aiSuggestions = json_decode($jsonText, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $suggestions = $aiSuggestions;
                    $suggestions['source'] = 'AI enhanced with local repertorization';
                }
            }
        }
    }
    
    // STEP 3: If no AI response, use local repertorization
    if (!$suggestions) {
        $suggestions = buildLocalSuggestions($rubrics, $localRemedies);
    }
    
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
exit;

/**
 * Get repertorization results from local database
 */
function getRepertorizationResults($rubric_ids) {
    $placeholders = implode(',', array_fill(0, count($rubric_ids), '?'));
    
    $sql = "SELECT rem.id, rem.remedy_name as name, rem.remedy_short_name, rem.common_name,
                   rem.keynote_symptoms, rem.clinical_indications, rem.modalities,
                   SUM(CASE WHEN rr.grade = 3 THEN 4 WHEN rr.grade = 2 THEN 2 ELSE 1 END) as total_score,
                   COUNT(DISTINCT rr.repertory_id) as rubric_count,
                   GROUP_CONCAT(CONCAT(rr.repertory_id, ':', rr.grade) ORDER BY rr.grade DESC SEPARATOR ',') as rubric_grades
            FROM remedies rem
            INNER JOIN repertory_remedies rr ON rem.id = rr.remedy_id
            WHERE rr.repertory_id IN ($placeholders)
            GROUP BY rem.id
            ORDER BY total_score DESC, rubric_count DESC
            LIMIT 15";
    
    return DB::query($sql, $rubric_ids) ?: [];
}

/**
 * Build enhanced prompt with local knowledge for Gemini
 */
function buildEnhancedPrompt($rubrics, $localRemedies) {
    $prompt = "You are an expert homeopathic physician with deep knowledge of Materia Medica and Kent's Repertory.\n\n";
    $prompt .= "A case has the following selected rubrics:\n";
    
    foreach ($rubrics as $rubric) {
        $prompt .= "- [{$rubric['category']}] {$rubric['rubric']}\n";
    }
    
    // Add local repertorization context
    if (!empty($localRemedies)) {
        $prompt .= "\nOur local repertorization shows these top remedies (with coverage scores):\n";
        foreach (array_slice($localRemedies, 0, 8) as $idx => $remedy) {
            $prompt .= ($idx + 1) . ". {$remedy['name']} (Score: {$remedy['total_score']}, Rubrics: {$remedy['rubric_count']}/" . count($rubrics) . ")\n";
            if (!empty($remedy['keynote_symptoms'])) {
                $keynotes = substr($remedy['keynote_symptoms'], 0, 150);
                $prompt .= "   Keynotes: {$keynotes}...\n";
            }
        }
    }
    
    $prompt .= "\nBased on the selected rubrics AND the repertorization data above, provide your expert analysis.\n";
    $prompt .= "Validate or adjust the repertorization based on your homeopathic knowledge.\n\n";
    $prompt .= "For each of the top 5 remedies, provide:\n";
    $prompt .= "- name: Full remedy name\n";
    $prompt .= "- match_percentage: Your expert assessment (0-100)\n";
    $prompt .= "- potency: Recommended starting potency\n";
    $prompt .= "- reasoning: Why this remedy fits the case\n";
    $prompt .= "- matching_rubrics: Which rubrics this remedy covers\n";
    $prompt .= "- reference: Materia Medica source (e.g., Boericke, Kent, Allen)\n\n";
    $prompt .= "Respond in JSON format:\n";
    $prompt .= '{ "remedies": [ {name, match_percentage, potency, reasoning, matching_rubrics, reference} ], "case_analysis": "overall analysis", "cautions": "any warnings" }';
    
    return $prompt;
}

/**
 * Build local suggestions when AI is unavailable
 */
function buildLocalSuggestions($rubrics, $localRemedies) {
    $remedies = [];
    $totalRubrics = count($rubrics);
    
    foreach (array_slice($localRemedies, 0, 5) as $remedy) {
        $coverage = $totalRubrics > 0 ? round(($remedy['rubric_count'] / $totalRubrics) * 100) : 0;
        
        // Get matching rubric names
        $matchingRubrics = [];
        if (!empty($remedy['rubric_grades'])) {
            $grades = explode(',', $remedy['rubric_grades']);
            foreach ($grades as $rg) {
                list($rubricId, $grade) = explode(':', $rg);
                foreach ($rubrics as $rub) {
                    if ($rub['id'] == $rubricId) {
                        $matchingRubrics[] = $rub['rubric'] . " (Grade $grade)";
                    }
                }
            }
        }
        
        // Suggest potency based on coverage
        $potency = $coverage >= 80 ? '200C' : ($coverage >= 50 ? '30C' : '6C');
        
        $remedies[] = [
            'name' => $remedy['name'],
            'match_percentage' => $coverage,
            'potency' => $potency,
            'reasoning' => "Covers {$remedy['rubric_count']} of {$totalRubrics} selected rubrics with a score of {$remedy['total_score']}. " .
                          (!empty($remedy['keynote_symptoms']) ? "Keynotes: " . substr($remedy['keynote_symptoms'], 0, 200) : ''),
            'matching_rubrics' => $matchingRubrics,
            'reference' => 'Local Repertorization Database'
        ];
    }
    
    // Build case analysis
    $rubricCategories = array_unique(array_column($rubrics, 'category'));
    $caseAnalysis = "Case involves " . count($rubrics) . " rubrics across " . count($rubricCategories) . " categories: " . 
                    implode(', ', $rubricCategories) . ". ";
    
    if (!empty($remedies)) {
        $caseAnalysis .= "Top remedy {$remedies[0]['name']} shows strongest coverage.";
    }
    
    return [
        'remedies' => $remedies,
        'case_analysis' => $caseAnalysis,
        'cautions' => 'These suggestions are based on repertorization data. Always verify with full case taking and Materia Medica study.',
        'source' => 'Local Repertorization (AI unavailable)'
    ];
}
