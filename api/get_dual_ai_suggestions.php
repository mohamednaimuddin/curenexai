<?php
/**
 * API Endpoint: Get Dual AI Suggestions
 * Returns both RAG (local database) and Gemini AI suggestions in separate columns
 * 
 * Now supports Vector Embeddings for semantic search when available
 * Falls back to keyword-based search if embeddings are not generated
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
    error_log("Dual AI API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
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
    
    // Try to load vector RAG if available
    $useVectorRAG = false;
    if (file_exists(__DIR__ . '/../includes/vector_rag.php')) {
        require_once __DIR__ . '/../includes/vector_rag.php';
        $useVectorRAG = true;
    }

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
    enforceRateLimit(RateLimiter::getClientKey('dual_ai'), 10, 60);

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
    // 1. RAG (Local Database) Suggestions
    // ============================================
    // Try Vector RAG first (semantic search), fall back to keyword search
    try {
        $vectorRagUsed = false;
        
        if ($useVectorRAG) {
            try {
                $vectorRag = new VectorRAG();
                
                // Check if embeddings are available
                if ($vectorRag->hasEmbeddings('remedy')) {
                    $ragSuggestions = $vectorRag->generateSuggestions($consultation);
                    $ragSuggestions['method'] = 'vector_embeddings';
                    $ragSuggestions['embedding_stats'] = $vectorRag->getEmbeddingStats();
                    $vectorRagUsed = true;
                }
            } catch (Exception $vectorError) {
                error_log('Vector RAG fallback: ' . $vectorError->getMessage());
                // Will fall back to keyword search below
            }
        }
        
        // Fall back to keyword-based RAG if vector search not available
        if (!$vectorRagUsed) {
            $ragSuggestions = generateRAGFromDatabase($consultation);
            $ragSuggestions['method'] = 'keyword_search';
        }
        
        $response['rag'] = $ragSuggestions;
    } catch (Exception $e) {
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
    
    // Log the combined suggestions (wrapped in try-catch to prevent failures)
    try {
        DB::query(
            "INSERT INTO ai_suggestions_log 
             (consultation_id, doctor_id, symptoms_sent, ai_response, suggested_remedies, api_used, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $consultation_id,
                $doctor_id,
                'Dual AI analysis (RAG + Gemini)',
                json_encode($response),
                json_encode(array_merge(
                    $response['rag']['remedies'] ?? [],
                    $response['gemini']['remedies'] ?? []
                )),
                'dual_rag_gemini'
            ]
        );
    } catch (Exception $logError) {
        // Logging failed, but continue anyway
        error_log('Failed to log AI suggestions: ' . $logError->getMessage());
    }
    
    echo json_encode($response);
    
} catch (Throwable $e) {
    error_log('Dual AI Suggestions API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine()]);
}

/**
 * Extract medical phrases from a text string
 * Identifies body parts, medical conditions, and symptoms
 * Also expands to related clinical terms for better matching
 */
function extractMedicalPhrases($text) {
    $medicalTerms = [];
    $text = strtolower($text);
    
    // Clinical condition mappings - expand chief complaint to related clinical terms
    $clinicalExpansions = [
        // Locomotor/walking issues -> expand to paralysis, weakness, stiffness terms
        'walk' => ['paralysis', 'paresis', 'weakness', 'locomotor', 'limbs', 'extremities', 'gait', 'ataxia', 
                   'rheumatic', 'rheumatism', 'arthritis', 'stiffness', 'restless', 'restlessness', 'motion'],
        'step' => ['paralysis', 'paresis', 'weakness', 'locomotor', 'limbs', 'lower limbs', 'legs', 'stiffness'],
        'unable' => ['paralysis', 'paresis', 'weakness', 'loss', 'paralytic'],
        'cannot' => ['paralysis', 'paresis', 'weakness', 'loss', 'paralytic'],
        'leg' => ['limbs', 'lower limbs', 'extremities', 'locomotor', 'paralysis', 'rheumatism'],
        'knee' => ['joint', 'joints', 'arthritis', 'rheumatism', 'stiffness', 'swelling'],
        'hip' => ['joint', 'joints', 'arthritis', 'rheumatism', 'sciatica', 'locomotor'],
        // Pain conditions
        'headache' => ['cephalalgia', 'migraine', 'head pain', 'cranial'],
        'backache' => ['lumbago', 'lumbar pain', 'spine', 'vertebral', 'sciatica'],
        'pain' => ['neuralgia', 'ache', 'soreness', 'rheumatic'],
        // Digestive
        'stomach' => ['gastric', 'dyspepsia', 'indigestion', 'epigastric'],
        'constipation' => ['bowels', 'intestinal', 'rectum', 'stool'],
        // Respiratory
        'cough' => ['bronchial', 'respiratory', 'chest', 'expectoration'],
        'breathless' => ['dyspnea', 'respiratory', 'asthma', 'suffocation'],
        // Skin
        'rash' => ['eruption', 'dermal', 'eczema', 'urticaria', 'skin'],
        'itch' => ['pruritus', 'urticaria', 'skin', 'eruption'],
    ];
    
    // Check for clinical expansions first
    foreach ($clinicalExpansions as $trigger => $expansions) {
        if (strpos($text, $trigger) !== false) {
            $medicalTerms = array_merge($medicalTerms, $expansions);
        }
    }
    
    // Body parts and systems
    $bodyParts = [
        'head', 'headache', 'skull', 'scalp', 'brain', 'temple', 'temples', 'occiput', 'occipital', 'vertex', 'forehead', 'frontal',
        'eye', 'eyes', 'vision', 'sight', 'blind', 'eyelid', 'pupil',
        'ear', 'ears', 'hearing', 'deaf', 'tinnitus',
        'nose', 'nasal', 'nostril', 'sinus', 'smell',
        'mouth', 'tongue', 'teeth', 'tooth', 'gums', 'palate', 'taste',
        'throat', 'tonsil', 'pharynx', 'larynx', 'voice', 'hoarse',
        'neck', 'cervical', 'thyroid',
        'chest', 'thorax', 'breast', 'nipple',
        'heart', 'cardiac', 'pulse', 'palpitation', 'circulation',
        'lung', 'lungs', 'respiratory', 'breath', 'breathing', 'cough', 'wheeze',
        'stomach', 'gastric', 'abdomen', 'abdominal', 'belly', 'navel', 'umbilicus',
        'liver', 'hepatic', 'gallbladder', 'bile',
        'spleen', 'pancreas',
        'intestine', 'bowel', 'colon', 'rectum', 'anus', 'hemorrhoid', 'piles',
        'kidney', 'renal', 'urinary', 'bladder', 'urine', 'urination',
        'genitals', 'genital', 'prostate', 'uterus', 'ovary', 'vagina', 'penis', 'testicle',
        'back', 'spine', 'spinal', 'lumbar', 'sacrum', 'coccyx', 'vertebra',
        'shoulder', 'arm', 'arms', 'elbow', 'forearm', 'wrist', 'hand', 'hands', 'finger', 'fingers', 'thumb',
        'hip', 'leg', 'legs', 'thigh', 'knee', 'knees', 'calf', 'ankle', 'foot', 'feet', 'toe', 'toes', 'heel',
        'skin', 'dermal', 'nail', 'nails', 'hair',
        'bone', 'bones', 'joint', 'joints', 'muscle', 'muscles', 'tendon', 'ligament',
        'nerve', 'nerves', 'neural', 'nervous',
        'blood', 'vein', 'veins', 'artery', 'arteries', 'lymph',
        'extremity', 'extremities', 'limb', 'limbs'
    ];
    
    // Symptoms and conditions
    $symptomTerms = [
        'pain', 'painful', 'ache', 'aching', 'sore', 'soreness',
        'burning', 'stinging', 'stabbing', 'shooting', 'throbbing', 'pulsating',
        'pulling', 'drawing', 'tearing', 'pressing', 'bursting', 'constricting',
        'cramping', 'cramp', 'spasm', 'spasmodic', 'twitching', 'trembling', 'tremor',
        'numbness', 'numb', 'tingling', 'prickling', 'pricking',
        'weakness', 'weak', 'fatigue', 'tired', 'exhaustion', 'lassitude',
        'swelling', 'swollen', 'edema', 'inflammation', 'inflamed',
        'stiffness', 'stiff', 'rigid', 'rigidity',
        'itching', 'itch', 'itchy', 'rash', 'eruption', 'hives', 'urticaria',
        'fever', 'febrile', 'chills', 'shivering', 'cold', 'hot', 'heat', 'warmth',
        'sweating', 'sweat', 'perspiration',
        'nausea', 'vomiting', 'vomit', 'retching',
        'diarrhea', 'constipation', 'loose', 'hard', 'stool',
        'bleeding', 'blood', 'hemorrhage',
        'discharge', 'secretion', 'mucus', 'pus',
        'dryness', 'dry', 'moisture', 'wet',
        'vertigo', 'dizziness', 'dizzy', 'giddiness', 'faint', 'fainting',
        'paralysis', 'paralyzed', 'paresis', 'palsy',
        'walk', 'walking', 'gait', 'locomotion', 'movement', 'motion', 'step', 'steps',
        'stand', 'standing', 'sit', 'sitting', 'lie', 'lying',
        'sleep', 'sleepless', 'insomnia', 'drowsy', 'restless',
        'appetite', 'hunger', 'thirst', 'eating', 'drink', 'drinking',
        'anxiety', 'anxious', 'fear', 'fearful', 'restlessness',
        'depression', 'depressed', 'sadness', 'melancholy', 'grief',
        'irritability', 'irritable', 'anger', 'angry', 'rage',
        'confusion', 'confused', 'delirium', 'delirious',
        'colic', 'griping', 'distension', 'bloating', 'flatulence', 'gas',
        'neuralgic', 'neuralgia', 'migraine', 'cephalalgia'
    ];
    
    // Modalities - time, conditions
    $modalityTerms = [
        'morning', 'afternoon', 'evening', 'night', 'midnight', 'noon',
        'worse', 'better', 'aggravation', 'amelioration',
        'cold', 'warm', 'hot', 'heat', 'open', 'air', 'draft',
        'motion', 'rest', 'touch', 'pressure',
        'eating', 'fasting', 'drinking',
        'lying', 'sitting', 'standing', 'walking', 'stooping', 'bending',
        'right', 'left', 'bilateral'
    ];
    
    // Check for body parts
    foreach ($bodyParts as $term) {
        if (strpos($text, $term) !== false) {
            $medicalTerms[] = $term;
        }
    }
    
    // Check for symptoms
    foreach ($symptomTerms as $term) {
        if (strpos($text, $term) !== false) {
            $medicalTerms[] = $term;
        }
    }
    
    // Check for modalities
    foreach ($modalityTerms as $term) {
        if (strpos($text, $term) !== false) {
            $medicalTerms[] = $term;
        }
    }
    
    return array_unique($medicalTerms);
}

/**
 * Generate remedy suggestions from local database using RAG approach
 */
function generateRAGFromDatabase($consultation) {
    $symptoms = $consultation['symptoms'] ?? [];
    $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
    
    if (empty($symptoms) && empty($chiefComplaint)) {
        return ['error' => 'No symptoms to analyze', 'remedies' => []];
    }
    
    // Common stop words to exclude from search
    $stopWords = [
        'not', 'able', 'unable', 'cannot', 'can', 'the', 'a', 'an', 'is', 'are', 'was', 'were',
        'to', 'of', 'in', 'on', 'at', 'for', 'with', 'from', 'by', 'and', 'or', 'but', 'if',
        'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'shall', 'very', 'much', 'more', 'less',
        'also', 'just', 'only', 'even', 'still', 'already', 'yet', 'too', 'so', 'such',
        'no', 'yes', 'any', 'some', 'all', 'each', 'every', 'both', 'few', 'many',
        'this', 'that', 'these', 'those', 'it', 'its', 'my', 'your', 'his', 'her', 'their',
        'me', 'him', 'her', 'us', 'them', 'who', 'what', 'which', 'when', 'where', 'why', 'how',
        'take', 'taking', 'taken', 'get', 'getting', 'got', 'make', 'making', 'made',
        'feel', 'feeling', 'felt', 'since', 'ago', 'day', 'days', 'week', 'weeks', 'month', 'months',
        'patient', 'complaint', 'complains', 'problem', 'problems', 'symptom', 'symptoms'
    ];
    
    // Build search terms from symptoms
    $searchTerms = [];
    foreach ($symptoms as $symptom) {
        // Extract meaningful words from symptom text
        $symptomWords = preg_split('/[\s,\-\.\/]+/', strtolower($symptom['symptom'] ?? ''));
        foreach ($symptomWords as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $searchTerms[] = $word;
            }
        }
        if (!empty($symptom['location'])) $searchTerms[] = strtolower($symptom['location']);
        if (!empty($symptom['sensation'])) $searchTerms[] = strtolower($symptom['sensation']);
        if (!empty($symptom['modality'])) $searchTerms[] = strtolower($symptom['modality']);
    }
    
    // Add chief complaint words (filtered)
    $complaintWords = preg_split('/[\s,\-\.\/]+/', $chiefComplaint);
    foreach ($complaintWords as $word) {
        $word = trim($word);
        if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
            $searchTerms[] = $word;
        }
    }
    
    // Also extract medical phrases from chief complaint
    $medicalPhrases = extractMedicalPhrases($chiefComplaint);
    $searchTerms = array_merge($searchTerms, $medicalPhrases);
    
    $searchTerms = array_unique(array_filter($searchTerms));
    
    // Define priority remedies for common conditions (well-known clinical associations)
    // Uses lowercase keys matching database remedy_name (lowercased for comparison)
    $priorityRemedies = [
        // General conditions
        'fever' => ['belladonna' => 15, 'aconitum napellus' => 12, 'aconite napellus' => 12, 'ferrum phosphoricum' => 10, 'gelsemium sempervirens' => 10, 'gelsemium' => 10, 'bryonia' => 8, 'bryonia alba' => 8, 'arsenicum album' => 6, 'baptisia tinctoria' => 6],
        'headache' => ['belladonna' => 12, 'bryonia' => 12, 'bryonia alba' => 12, 'gelsemium sempervirens' => 12, 'gelsemium' => 12, 'nux vomica' => 10, 'natrum muriaticum' => 10, 'sanguinaria canadensis' => 10, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'glonoine' => 8, 'glonoinum' => 8],
        'cough' => ['bryonia' => 12, 'bryonia alba' => 12, 'phosphorus' => 12, 'drosera' => 10, 'spongia' => 10, 'spongia tosta' => 10, 'antimonium tartaricum' => 8, 'hepar sulph' => 8, 'hepar sulphuris calcareum' => 8, 'rumex crispus' => 6],
        'cold' => ['aconitum napellus' => 12, 'aconite napellus' => 12, 'allium cepa' => 10, 'arsenicum album' => 8, 'euphrasia' => 8, 'euphrasia officinalis' => 8, 'natrum muriaticum' => 6],
        'diarrhea' => ['arsenicum album' => 12, 'podophyllum' => 10, 'podophyllum peltatum' => 10, 'veratrum album' => 10, 'china' => 8, 'china officinalis' => 8, 'phosphorus' => 6, 'aloe socotrina' => 6],
        'constipation' => ['nux vomica' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'alumina' => 10, 'opium' => 8, 'plumbum' => 8, 'plumbum metallicum' => 8, 'silicea' => 6, 'silicea terra' => 6],
        'anxiety' => ['arsenicum album' => 12, 'aconitum napellus' => 10, 'aconite napellus' => 10, 'argentum nitricum' => 10, 'gelsemium sempervirens' => 8, 'gelsemium' => 8, 'phosphorus' => 8],
        'pain' => ['bryonia' => 10, 'bryonia alba' => 10, 'rhus toxicodendron' => 10, 'rhus tox' => 10, 'arnica' => 10, 'arnica montana' => 10, 'belladonna' => 8, 'hypericum' => 8, 'hypericum perforatum' => 8, 'mag phos' => 6, 'magnesia phosphorica' => 6],
        'inflammation' => ['belladonna' => 12, 'apis' => 10, 'apis mellifica' => 10, 'bryonia' => 10, 'bryonia alba' => 10, 'hepar sulph' => 8, 'hepar sulphuris calcareum' => 8, 'mercurius' => 8],
        'insomnia' => ['coffea' => 12, 'coffea cruda' => 12, 'nux vomica' => 10, 'passiflora' => 10, 'passiflora incarnata' => 10, 'ignatia' => 8, 'ignatia amara' => 8],
        'vomiting' => ['ipecacuanha' => 12, 'ipecac' => 12, 'nux vomica' => 10, 'arsenicum album' => 10, 'phosphorus' => 8, 'veratrum album' => 8],
        'skin' => ['sulphur' => 12, 'graphites' => 10, 'arsenicum album' => 8, 'rhus toxicodendron' => 8, 'rhus tox' => 8, 'mezereum' => 6],
        'throat' => ['belladonna' => 12, 'mercurius' => 10, 'mercurius solubilis' => 10, 'phytolacca' => 10, 'phytolacca decandra' => 10, 'lachesis' => 8, 'hepar sulph' => 8],
        'joint' => ['rhus toxicodendron' => 12, 'rhus tox' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'ledum' => 10, 'ledum palustre' => 10, 'ruta' => 8, 'ruta graveolens' => 8, 'calc carb' => 6, 'calcarea carbonica' => 6],
        'back' => ['rhus toxicodendron' => 10, 'rhus tox' => 10, 'bryonia' => 10, 'bryonia alba' => 10, 'kali carb' => 8, 'kali carbonicum' => 8, 'nux vomica' => 8, 'calc fluor' => 6, 'calcarea fluorica' => 6],
        
        // Headache locations - specific
        'temple' => ['spigelia anthelmia' => 15, 'spigelia' => 15, 'belladonna' => 12, 'gelsemium sempervirens' => 10, 'gelsemium' => 10, 'sanguinaria canadensis' => 10, 'iris versicolor' => 8, 'lac caninum' => 6],
        'temples' => ['spigelia anthelmia' => 15, 'spigelia' => 15, 'belladonna' => 12, 'gelsemium sempervirens' => 10, 'gelsemium' => 10, 'sanguinaria canadensis' => 10, 'iris versicolor' => 8, 'lac caninum' => 6],
        'occiput' => ['gelsemium sempervirens' => 15, 'gelsemium' => 15, 'silicea' => 12, 'silicea terra' => 12, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'carbo veg' => 8, 'carbo vegetabilis' => 8, 'petroleum' => 8, 'nux vomica' => 6],
        'occipital' => ['gelsemium sempervirens' => 15, 'gelsemium' => 15, 'silicea' => 12, 'silicea terra' => 12, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'carbo veg' => 8, 'carbo vegetabilis' => 8, 'petroleum' => 8, 'nux vomica' => 6],
        'forehead' => ['belladonna' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'nux vomica' => 10, 'natrum muriaticum' => 8, 'pulsatilla' => 8, 'pulsatilla nigricans' => 8],
        'vertex' => ['silicea' => 12, 'silicea terra' => 12, 'sulphur' => 10, 'calc carb' => 10, 'calcarea carbonica' => 10, 'lac caninum' => 8, 'glonoine' => 8, 'glonoinum' => 8],
        
        // Pain types
        'pulling' => ['gelsemium sempervirens' => 12, 'gelsemium' => 12, 'rhus toxicodendron' => 10, 'rhus tox' => 10, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'bryonia' => 8, 'bryonia alba' => 8, 'china' => 6, 'china officinalis' => 6],
        'drawing' => ['gelsemium sempervirens' => 12, 'gelsemium' => 12, 'rhus toxicodendron' => 10, 'rhus tox' => 10, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'china' => 8, 'china officinalis' => 8, 'pulsatilla' => 6, 'pulsatilla nigricans' => 6],
        'throbbing' => ['belladonna' => 15, 'glonoine' => 12, 'glonoinum' => 12, 'natrum muriaticum' => 10, 'china' => 8, 'china officinalis' => 8, 'ferrum' => 8, 'ferrum metallicum' => 8],
        'pressing' => ['bryonia' => 12, 'bryonia alba' => 12, 'natrum muriaticum' => 10, 'nux vomica' => 10, 'pulsatilla' => 8, 'pulsatilla nigricans' => 8],
        'stitching' => ['bryonia' => 15, 'bryonia alba' => 15, 'kali carb' => 10, 'kali carbonicum' => 10, 'spigelia anthelmia' => 10, 'spigelia' => 10, 'apis' => 8, 'apis mellifica' => 8],
        'burning' => ['arsenicum album' => 12, 'sulphur' => 10, 'phosphorus' => 10, 'cantharis' => 8],
        'neuralgic' => ['spigelia anthelmia' => 15, 'spigelia' => 15, 'mag phos' => 12, 'magnesia phosphorica' => 12, 'colocynthis' => 10, 'hypericum' => 10, 'hypericum perforatum' => 10, 'gelsemium sempervirens' => 8, 'gelsemium' => 8],
        'neuralgia' => ['spigelia anthelmia' => 15, 'spigelia' => 15, 'mag phos' => 12, 'magnesia phosphorica' => 12, 'colocynthis' => 10, 'hypericum' => 10, 'hypericum perforatum' => 10, 'gelsemium sempervirens' => 8, 'gelsemium' => 8],
        
        // Time modalities
        'evening' => ['pulsatilla' => 12, 'pulsatilla nigricans' => 12, 'phosphorus' => 10, 'lycopodium' => 10, 'lycopodium clavatum' => 10, 'sepia' => 8, 'belladonna' => 6],
        'morning' => ['nux vomica' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'natrum muriaticum' => 10, 'sulphur' => 8, 'lachesis' => 8],
        'night' => ['arsenicum album' => 12, 'mercurius' => 10, 'mercurius solubilis' => 10, 'rhus toxicodendron' => 10, 'rhus tox' => 10, 'aconitum napellus' => 8, 'aconite napellus' => 8],
        'afternoon' => ['belladonna' => 10, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'pulsatilla' => 8, 'pulsatilla nigricans' => 8],
        
        // GASTRIC/DIGESTIVE CONDITIONS - Critical for gastritis, hyperacidity cases
        'gastritis' => ['nux vomica' => 18, 'arsenicum album' => 15, 'phosphorus' => 12, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'carbo vegetabilis' => 10, 'sulphur' => 10, 'robinia' => 8, 'robinia pseudoacacia' => 8],
        'hyperacidity' => ['nux vomica' => 18, 'robinia' => 15, 'robinia pseudoacacia' => 15, 'arsenicum album' => 12, 'iris versicolor' => 10, 'phosphorus' => 10, 'sulphur' => 8],
        'acidity' => ['nux vomica' => 15, 'robinia' => 12, 'robinia pseudoacacia' => 12, 'arsenicum album' => 10, 'carbo vegetabilis' => 10, 'phosphorus' => 8],
        'eructation' => ['carbo vegetabilis' => 15, 'nux vomica' => 12, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'china' => 10, 'china officinalis' => 10, 'argentum nitricum' => 8],
        'eructations' => ['carbo vegetabilis' => 15, 'nux vomica' => 12, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'china' => 10, 'china officinalis' => 10, 'argentum nitricum' => 8],
        'sour' => ['nux vomica' => 15, 'robinia' => 12, 'robinia pseudoacacia' => 12, 'sulphur' => 10, 'lycopodium' => 10, 'lycopodium clavatum' => 10, 'calc carb' => 8, 'calcarea carbonica' => 8],
        'waterbrash' => ['nux vomica' => 15, 'robinia' => 15, 'robinia pseudoacacia' => 15, 'arsenicum album' => 12, 'phosphorus' => 10, 'pulsatilla' => 8],
        'bloating' => ['lycopodium' => 15, 'lycopodium clavatum' => 15, 'carbo vegetabilis' => 12, 'china' => 10, 'china officinalis' => 10, 'nux vomica' => 10, 'argentum nitricum' => 8],
        'flatulence' => ['lycopodium' => 15, 'lycopodium clavatum' => 15, 'carbo vegetabilis' => 15, 'china' => 12, 'china officinalis' => 12, 'argentum nitricum' => 10, 'raphanus' => 8],
        'epigastric' => ['nux vomica' => 12, 'arsenicum album' => 12, 'phosphorus' => 10, 'carbo vegetabilis' => 10, 'lycopodium' => 8, 'lycopodium clavatum' => 8],
        'epigastrium' => ['nux vomica' => 12, 'arsenicum album' => 12, 'phosphorus' => 10, 'carbo vegetabilis' => 10, 'lycopodium' => 8, 'lycopodium clavatum' => 8],
        'indigestion' => ['nux vomica' => 15, 'carbo vegetabilis' => 12, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'pulsatilla' => 10, 'china' => 8, 'china officinalis' => 8],
        'dyspepsia' => ['nux vomica' => 15, 'carbo vegetabilis' => 12, 'lycopodium' => 12, 'lycopodium clavatum' => 12, 'arsenicum album' => 10, 'phosphorus' => 8],
        
        // 11 AM aggravation - SULPHUR keynote!
        '11' => ['sulphur' => 20, 'natrum muriaticum' => 12, 'phosphorus' => 8],
        'eleven' => ['sulphur' => 20, 'natrum muriaticum' => 12, 'phosphorus' => 8],
        '11am' => ['sulphur' => 20, 'natrum muriaticum' => 12, 'phosphorus' => 8],
        
        // Mental symptoms related to digestion
        'irritable' => ['nux vomica' => 15, 'chamomilla' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'lycopodium' => 8, 'lycopodium clavatum' => 8],
        'irritability' => ['nux vomica' => 15, 'chamomilla' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'lycopodium' => 8, 'lycopodium clavatum' => 8],
        'impatient' => ['nux vomica' => 15, 'chamomilla' => 12, 'arsenicum album' => 10, 'sulphur' => 8],
        'impatience' => ['nux vomica' => 15, 'chamomilla' => 12, 'arsenicum album' => 10, 'sulphur' => 8],
        'hungry' => ['nux vomica' => 12, 'sulphur' => 12, 'phosphorus' => 10, 'lycopodium' => 10, 'lycopodium clavatum' => 10],
        
        // Hot patient constitution
        'hot' => ['sulphur' => 12, 'pulsatilla' => 12, 'pulsatilla nigricans' => 12, 'apis' => 10, 'apis mellifica' => 10, 'lachesis' => 8],
        
        // CARDIAC/CARDIOVASCULAR CONDITIONS - Critical for heart cases
        'heart' => ['digitalis' => 20, 'digitalis purpurea' => 20, 'crataegus' => 18, 'crataegus oxyacantha' => 18, 'cactus' => 15, 'cactus grandiflorus' => 15, 'lachesis' => 12, 'spigelia' => 12, 'spigelia anthelmia' => 12, 'naja' => 10, 'naja tripudians' => 10, 'arsenicum album' => 10],
        'cardiac' => ['digitalis' => 20, 'digitalis purpurea' => 20, 'crataegus' => 18, 'crataegus oxyacantha' => 18, 'cactus' => 15, 'cactus grandiflorus' => 15, 'strophanthus' => 12, 'lachesis' => 12, 'arsenicum album' => 10],
        'chest' => ['cactus' => 12, 'cactus grandiflorus' => 12, 'spigelia' => 12, 'spigelia anthelmia' => 12, 'bryonia' => 10, 'bryonia alba' => 10, 'phosphorus' => 10, 'lachesis' => 8, 'kali carb' => 8, 'kali carbonicum' => 8],
        'heaviness' => ['cactus' => 12, 'cactus grandiflorus' => 12, 'digitalis' => 10, 'digitalis purpurea' => 10, 'lachesis' => 10, 'phosphorus' => 8],
        'palpitation' => ['digitalis' => 15, 'digitalis purpurea' => 15, 'spigelia' => 15, 'spigelia anthelmia' => 15, 'lachesis' => 12, 'cactus' => 12, 'cactus grandiflorus' => 12, 'naja' => 10, 'naja tripudians' => 10, 'arsenicum album' => 8],
        'palpitations' => ['digitalis' => 15, 'digitalis purpurea' => 15, 'spigelia' => 15, 'spigelia anthelmia' => 15, 'lachesis' => 12, 'cactus' => 12, 'cactus grandiflorus' => 12, 'naja' => 10, 'naja tripudians' => 10, 'arsenicum album' => 8],
        
        // Dyspnea/breathing issues
        'dyspnea' => ['digitalis' => 18, 'digitalis purpurea' => 18, 'arsenicum album' => 15, 'lachesis' => 12, 'carbo vegetabilis' => 12, 'antimonium tartaricum' => 10, 'lycopus' => 10, 'lycopus virginicus' => 10],
        'breath' => ['arsenicum album' => 12, 'carbo vegetabilis' => 12, 'digitalis' => 10, 'digitalis purpurea' => 10, 'lachesis' => 10, 'phosphorus' => 8],
        'breathlessness' => ['arsenicum album' => 15, 'digitalis' => 15, 'digitalis purpurea' => 15, 'carbo vegetabilis' => 12, 'lachesis' => 12, 'antimonium tartaricum' => 10],
        'exertion' => ['digitalis' => 15, 'digitalis purpurea' => 15, 'arsenicum album' => 12, 'crataegus' => 12, 'crataegus oxyacantha' => 12, 'lachesis' => 10, 'coca' => 8],
        
        // Edema/swelling
        'edema' => ['apis' => 18, 'apis mellifica' => 18, 'digitalis' => 15, 'digitalis purpurea' => 15, 'arsenicum album' => 12, 'lycopus' => 10, 'lycopus virginicus' => 10, 'apocynum' => 10, 'apocynum cannabinum' => 10],
        'swelling' => ['apis' => 15, 'apis mellifica' => 15, 'digitalis' => 12, 'digitalis purpurea' => 12, 'arsenicum album' => 10, 'rhus toxicodendron' => 8, 'bryonia' => 8],
        'feet' => ['apis' => 12, 'apis mellifica' => 12, 'digitalis' => 12, 'digitalis purpurea' => 12, 'lycopus' => 10, 'lycopus virginicus' => 10, 'ledum' => 8, 'ledum palustre' => 8],
        'pedal' => ['apis' => 15, 'apis mellifica' => 15, 'digitalis' => 15, 'digitalis purpurea' => 15, 'lycopus' => 12, 'lycopus virginicus' => 12, 'apocynum' => 10],
        
        // Hypertension
        'hypertension' => ['lachesis' => 15, 'glonoine' => 15, 'glonoinum' => 15, 'crataegus' => 12, 'crataegus oxyacantha' => 12, 'rauwolfia' => 10, 'baryta muriatica' => 8, 'viscum album' => 8],
        'blood' => ['lachesis' => 10, 'phosphorus' => 10, 'crotalus' => 8, 'crotalus horridus' => 8, 'hamamelis' => 8],
        'pressure' => ['lachesis' => 10, 'glonoine' => 10, 'glonoinum' => 10, 'belladonna' => 8],
        
        // Cardiac weakness/failure
        'weakness' => ['arsenicum album' => 12, 'carbo vegetabilis' => 12, 'china' => 10, 'china officinalis' => 10, 'digitalis' => 10, 'digitalis purpurea' => 10, 'phosphoric acid' => 8],
        'tired' => ['arsenicum album' => 10, 'phosphoric acid' => 10, 'china' => 8, 'china officinalis' => 8, 'kali phos' => 8, 'kali phosphoricum' => 8],
        'fatigue' => ['arsenicum album' => 12, 'china' => 10, 'china officinalis' => 10, 'phosphoric acid' => 10, 'kali phos' => 8, 'kali phosphoricum' => 8],
        
        // Fear/anxiety about heart
        'fear' => ['aconitum napellus' => 15, 'aconite napellus' => 15, 'arsenicum album' => 12, 'phosphorus' => 10, 'argentum nitricum' => 10, 'digitalis' => 8, 'digitalis purpurea' => 8],
        'disease' => ['arsenicum album' => 10, 'phosphorus' => 8, 'nitric acid' => 6],
        
        // Constriction/tightness
        'constriction' => ['cactus' => 18, 'cactus grandiflorus' => 18, 'lachesis' => 12, 'spigelia' => 10, 'spigelia anthelmia' => 10, 'arsenicum album' => 8],
        'tight' => ['cactus' => 15, 'cactus grandiflorus' => 15, 'lachesis' => 12, 'phosphorus' => 8],
        'tightness' => ['cactus' => 15, 'cactus grandiflorus' => 15, 'lachesis' => 12, 'phosphorus' => 8],
        
        // Cough at night (cardiac cough)
        'cough' => ['bryonia' => 12, 'bryonia alba' => 12, 'phosphorus' => 12, 'drosera' => 10, 'spongia' => 10, 'antimonium tartaricum' => 8],
        
        // DIABETES AND METABOLIC CONDITIONS
        'diabetes' => ['syzygium' => 18, 'syzygium jambolanum' => 18, 'phosphoric acid' => 15, 'uranium nitricum' => 15, 'arsenicum album' => 12, 'lycopodium' => 10, 'lycopodium clavatum' => 10],
        'diabetic' => ['syzygium' => 18, 'syzygium jambolanum' => 18, 'phosphoric acid' => 15, 'uranium nitricum' => 15, 'arsenicum album' => 12],
        'urination' => ['phosphoric acid' => 12, 'syzygium' => 12, 'syzygium jambolanum' => 12, 'lycopodium' => 10, 'lycopodium clavatum' => 10, 'cantharis' => 8, 'apis' => 6],
        'frequent' => ['phosphoric acid' => 10, 'apis' => 8, 'cantharis' => 8, 'lycopodium' => 6],
        'polyuria' => ['phosphoric acid' => 15, 'syzygium' => 15, 'syzygium jambolanum' => 15, 'uranium nitricum' => 12],
        'nocturia' => ['lycopodium' => 15, 'lycopodium clavatum' => 15, 'phosphoric acid' => 12, 'syzygium' => 10],
        'thirst' => ['phosphorus' => 12, 'arsenicum album' => 10, 'bryonia' => 10, 'bryonia alba' => 10, 'natrum muriaticum' => 8, 'sulphur' => 6],
        // Vision symptoms - NOTE: Gelsemium is for ACUTE visual disturbance (flu), not chronic diabetic vision issues
        'vision' => ['phosphorus' => 12, 'physostigma' => 10, 'ruta' => 8, 'ruta graveolens' => 8, 'gelsemium' => 6, 'gelsemium sempervirens' => 6],
        'blurred' => ['phosphorus' => 12, 'physostigma' => 10, 'ruta' => 8, 'gelsemium' => 6, 'gelsemium sempervirens' => 6],
        
        // Burning sensations (diabetic neuropathy)
        'burning' => ['arsenicum album' => 15, 'sulphur' => 12, 'phosphorus' => 12, 'cantharis' => 10, 'secale' => 8, 'secale cornutum' => 8],
        
        // Fatigue/weakness in metabolic disease context
        'fatigue' => ['phosphoric acid' => 12, 'arsenicum album' => 10, 'china' => 10, 'china officinalis' => 10, 'kali phos' => 8, 'kali phosphoricum' => 8],
        
        // Obesity - but should NOT dominate cardiac cases
        'obesity' => ['calcarea carbonica' => 10, 'graphites' => 8, 'phytolacca' => 6, 'fucus' => 6, 'fucus vesiculosus' => 6],
        'obese' => ['calcarea carbonica' => 10, 'graphites' => 8, 'phytolacca' => 6],
        'overweight' => ['calcarea carbonica' => 8, 'graphites' => 6, 'fucus' => 6],
    ];
    
    // Search remedies database
    $remedyScores = [];
    $remedyMatches = [];
    
    foreach ($searchTerms as $term) {
        if (strlen($term) < 3) continue;
        
        // Search in remedy fields - GROUP BY remedy_name to avoid duplicates
        $sql = "SELECT MIN(id) as id, remedy_name, 
                       MAX(common_name) as common_name,
                       GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms, 
                       GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications, 
                       GROUP_CONCAT(DISTINCT mind_symptoms SEPARATOR ' | ') as mind_symptoms, 
                       GROUP_CONCAT(DISTINCT modalities SEPARATOR ' | ') as modalities, 
                       GROUP_CONCAT(DISTINCT book_reference SEPARATOR '; ') as book_reference
                FROM remedies 
                WHERE LOWER(keynote_symptoms) LIKE ? 
                   OR LOWER(clinical_indications) LIKE ?
                   OR LOWER(mind_symptoms) LIKE ?
                   OR LOWER(modalities) LIKE ?
                GROUP BY remedy_name
                LIMIT 50";
        
        $termPattern = '%' . $term . '%';
        $matches = DB::query($sql, [$termPattern, $termPattern, $termPattern, $termPattern]);
        
        foreach ($matches as $remedy) {
            // Use remedy_name as key to prevent duplicates
            $key = strtolower($remedy['remedy_name']);
            if (!isset($remedyScores[$key])) {
                $remedyScores[$key] = 0;
                $remedyMatches[$key] = [
                    'remedy' => $remedy,
                    'matched_terms' => [],
                    'matched_fields' => []
                ];
            }
            
            // Score based on match location (handle null values)
            $score = 1;
            $keynotes = $remedy['keynote_symptoms'] ?? '';
            $clinical = $remedy['clinical_indications'] ?? '';
            $mind = $remedy['mind_symptoms'] ?? '';
            $modalities = $remedy['modalities'] ?? '';
            
            // Higher scores for clinical indications and keynotes (actual therapeutic use)
            if ($keynotes && stripos($keynotes, $term) !== false) {
                $score += 5;  // Increased from 3 - keynotes are most important
                $remedyMatches[$key]['matched_fields'][] = 'keynotes';
            }
            if ($clinical && stripos($clinical, $term) !== false) {
                $score += 4;  // Increased from 2 - clinical indications show therapeutic use
                $remedyMatches[$key]['matched_fields'][] = 'clinical';
            }
            if ($mind && stripos($mind, $term) !== false) {
                $score += 3;  // Mind symptoms important for constitutional
                $remedyMatches[$key]['matched_fields'][] = 'mind';
            }
            // Modalities get lower score - just mentions walking as a modality
            // doesn't mean the remedy TREATS walking problems
            if ($modalities && stripos($modalities, $term) !== false) {
                // Only add small score if matched in other fields too
                if (!empty($remedyMatches[$key]['matched_fields'])) {
                    $score += 1;
                }
                $remedyMatches[$key]['matched_fields'][] = 'modalities';
            }
            
            $remedyScores[$key] += $score;
            $remedyMatches[$key]['matched_terms'][] = $term;
        }
    }
    
    // Also search repertory for symptom-remedy mappings
    foreach ($symptoms as $symptom) {
        $symptomText = strtolower($symptom['symptom']);
        
        $repertoryMatches = DB::query(
            "SELECT r.id, r.rubric, rr.remedy_id, rr.grade, rem.remedy_name, rem.common_name, rem.book_reference
             FROM repertory r
             INNER JOIN repertory_remedies rr ON r.id = rr.repertory_id
             INNER JOIN remedies rem ON rr.remedy_id = rem.id
             WHERE LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?
             ORDER BY rr.grade DESC
             LIMIT 30",
            ['%' . $symptomText . '%', '%' . $symptomText . '%']
        );
        
        foreach ($repertoryMatches as $match) {
            // Use remedy_name as key to prevent duplicates
            $key = strtolower($match['remedy_name']);
            if (!isset($remedyScores[$key])) {
                $remedyScores[$key] = 0;
                $remedyMatches[$key] = [
                    'remedy' => [
                        'id' => $match['remedy_id'],
                        'remedy_name' => $match['remedy_name'],
                        'common_name' => $match['common_name'],
                        'book_reference' => $match['book_reference']
                    ],
                    'matched_terms' => [],
                    'matched_fields' => [],
                    'repertory_rubrics' => []
                ];
            }
            
            // Score based on grade (1-3)
            $remedyScores[$key] += $match['grade'] * 2;
            if (!isset($remedyMatches[$key]['repertory_rubrics'])) {
                $remedyMatches[$key]['repertory_rubrics'] = [];
            }
            $remedyMatches[$key]['repertory_rubrics'][] = [
                'rubric' => $match['rubric'],
                'grade' => $match['grade']
            ];
        }
    }
    
    // Apply priority bonus for well-known remedy-condition associations
    foreach ($searchTerms as $term) {
        $termLower = strtolower($term);
        if (isset($priorityRemedies[$termLower])) {
            foreach ($priorityRemedies[$termLower] as $remedyName => $bonus) {
                $key = strtolower($remedyName);
                if (isset($remedyScores[$key])) {
                    $remedyScores[$key] += $bonus;
                }
            }
        }
    }
    
    // Sort by score
    arsort($remedyScores);
    
    // AGE-SPECIFIC PENALTY for the keyword fallback
    // Apply penalties to remedies that are specifically for certain age groups
    // This prevents Baryta (elderly/children remedy) from appearing for middle-aged adults
    $patientAge = $consultation['age'] ?? null;
    if ($patientAge !== null) {
        $ageSpecificRemedies = [
            // Baryta - specifically for ELDERLY (>60) or CHILDREN (<12)
            'baryta' => ['min_age' => 60, 'max_age' => 12, 'penalty' => 0.15],  // Very heavy penalty
            // Cina - mainly for children
            'cina' => ['max_age' => 14, 'penalty' => 0.4],
            // Chamomilla - mainly for infants/children
            'chamomilla' => ['max_age' => 10, 'penalty' => 0.5],
        ];
        
        foreach ($remedyScores as $key => &$score) {
            foreach ($ageSpecificRemedies as $remedyPrefix => $ageData) {
                if (strpos($key, $remedyPrefix) === 0) {
                    $minAge = $ageData['min_age'] ?? 0;
                    $maxAge = $ageData['max_age'] ?? 150;
                    $penalty = $ageData['penalty'] ?? 0.5;
                    
                    // Baryta-type: indicated for elderly (>60) OR children (<12)
                    // Middle-aged adults (12-60) should be heavily penalized
                    if (isset($ageData['min_age']) && isset($ageData['max_age'])) {
                        if ($patientAge > $maxAge && $patientAge < $minAge) {
                            $score *= $penalty;  // Heavy penalty for wrong age
                        }
                    } elseif (isset($ageData['max_age'])) {
                        if ($patientAge > $maxAge) {
                            $score *= $penalty;
                        }
                    } elseif (isset($ageData['min_age'])) {
                        if ($patientAge < $minAge) {
                            $score *= $penalty;
                        }
                    }
                    break;
                }
            }
        }
        unset($score);
        
        // Re-sort after applying penalties
        arsort($remedyScores);
    }
    
    // CARDIAC CASE DETECTION
    // When clear cardiac symptoms are present, deprioritize less-proven remedies
    // that matched on secondary keywords (like 'obesity') but are not cardiac remedies
    $cardiacKeywords = ['heart', 'cardiac', 'chest', 'palpitation', 'dyspnea', 'breathlessness', 
                        'edema', 'swelling', 'feet', 'pedal', 'hypertension'];
    $isCardiacCase = false;
    $cardiacMatches = 0;
    
    foreach ($searchTerms as $term) {
        if (in_array(strtolower($term), $cardiacKeywords)) {
            $cardiacMatches++;
        }
    }
    
    // Also check chief complaint directly
    $chiefComplaintLower = strtolower($consultation['chief_complaint'] ?? '');
    if (strpos($chiefComplaintLower, 'chest') !== false || 
        strpos($chiefComplaintLower, 'heart') !== false ||
        strpos($chiefComplaintLower, 'breath') !== false ||
        strpos($chiefComplaintLower, 'swelling') !== false ||
        strpos($chiefComplaintLower, 'edema') !== false) {
        $cardiacMatches += 2;
    }
    
    $isCardiacCase = ($cardiacMatches >= 2);
    
    if ($isCardiacCase) {
        // INJECT must-have cardiac remedies that may not have keyword-matched
        $mustHaveCardiacRemedies = [
            'digitalis' => ['name' => 'Digitalis', 'baseScore' => 88,
                'indication' => 'Cardiac dropsy, weak heart, dyspnea on exertion, edema'],
            'arsenicum album' => ['name' => 'Arsenicum Album', 'baseScore' => 85,
                'indication' => 'Anxiety about health, fear of death, restlessness, edema'],
            'crataegus' => ['name' => 'Crataegus', 'baseScore' => 82,
                'indication' => 'Heart tonic, cardiac weakness, dyspnea, hypertension'],
            'apis mellifica' => ['name' => 'Apis Mellifica', 'baseScore' => 78,
                'indication' => 'Edema worse evening, puffiness, tight feeling in chest'],
            'lachesis' => ['name' => 'Lachesis', 'baseScore' => 80,
                'indication' => 'Circulatory disturbances, cannot bear tight clothing, hot patient']
        ];
        
        foreach ($mustHaveCardiacRemedies as $remedyKey => $remedyInfo) {
            $alreadyExists = false;
            foreach (array_keys($remedyScores) as $existingKey) {
                if (stripos($existingKey, explode(' ', $remedyKey)[0]) !== false) {
                    // Boost existing cardiac remedy
                    $remedyScores[$existingKey] *= 1.3;
                    $alreadyExists = true;
                    break;
                }
            }
            
            if (!$alreadyExists) {
                $remedyScores[$remedyKey] = $remedyInfo['baseScore'];
                $remedyMatches[$remedyKey] = [
                    'remedy' => [
                        'remedy_name' => $remedyInfo['name'],
                        'common_name' => '',
                        'keynote_symptoms' => $remedyInfo['indication'],
                        'source' => 'Boericke Materia Medica; Kent\'s Repertory'
                    ],
                    'matched_terms' => ['heart', 'chest', 'dyspnea', 'edema'],
                    'matched_fields' => ['cardiac_specific'],
                    'repertory_rubrics' => [
                        ['rubric' => 'HEART - cardiac conditions', 'grade' => 3],
                        ['rubric' => $remedyInfo['indication'], 'grade' => 2]
                    ]
                ];
            }
        }
        
        // First-line cardiac remedies that should NOT be penalized
        $cardiacRemedies = ['digitalis', 'crataegus', 'cactus', 'lachesis', 'spigelia', 
                          'naja', 'arsenicum', 'apis', 'lycopus', 'strophanthus', 
                          'apocynum', 'glonoine', 'glonoinum', 'phosphorus', 'carbo'];
        
        // Remedies that should be deprioritized in cardiac cases (matched on secondary symptoms)
        $nonCardiacRemedies = ['ammonium', 'baryta', 'calcarea', 'graphites', 'phytolacca', 
                              'fucus', 'antimonium', 'cina', 'chamomilla'];
        
        foreach ($remedyScores as $key => &$score) {
            $isCardiacRemedy = false;
            foreach ($cardiacRemedies as $cardiacRem) {
                if (strpos($key, $cardiacRem) !== false) {
                    $isCardiacRemedy = true;
                    break;
                }
            }
            
            if (!$isCardiacRemedy) {
                // Check if it's a known non-cardiac remedy that likely matched on obesity/catarrh
                foreach ($nonCardiacRemedies as $nonCardiac) {
                    if (strpos($key, $nonCardiac) === 0) {
                        $score *= 0.3;  // Heavy 70% penalty for wrong remedy type
                        break;
                    }
                }
            }
        }
        unset($score);
        
        // Re-sort after cardiac penalties
        arsort($remedyScores);
    }
    
    // DIABETES CASE DETECTION
    // When diabetes is mentioned or typical diabetic symptoms are present,
    // prioritize specific diabetic remedies and deprioritize acute remedies
    $diabeticKeywords = ['diabetes', 'diabetic', 'urination', 'frequent', 'polyuria', 'nocturia', 
                         'thirst', 'glucose', 'sugar', 'feet', 'burning'];
    $isDiabeticCase = false;
    $diabeticMatches = 0;
    
    foreach ($searchTerms as $term) {
        if (in_array(strtolower($term), $diabeticKeywords)) {
            $diabeticMatches++;
        }
    }
    
    // Check diagnosis and past history for diabetes
    $diagnosisLower = strtolower($consultation['diagnosis'] ?? '');
    $pastHistoryLower = strtolower($consultation['past_medical_history'] ?? '');
    $chiefComplaintCheck = strtolower($consultation['chief_complaint'] ?? '');
    
    if (strpos($diagnosisLower, 'diabet') !== false || 
        strpos($pastHistoryLower, 'diabet') !== false ||
        (strpos($chiefComplaintCheck, 'urination') !== false && strpos($chiefComplaintCheck, 'thirst') !== false)) {
        $diabeticMatches += 3;  // Strong indicator
    }
    
    $isDiabeticCase = ($diabeticMatches >= 3);
    
    if ($isDiabeticCase) {
        // INJECT must-have diabetic remedies that may not have keyword-matched
        // These are essential remedies for diabetes that must appear in results
        $mustHaveDiabeticRemedies = [
            'syzygium jambolanum' => ['name' => 'Syzygium Jambolanum', 'baseScore' => 85, 
                'indication' => 'Specific for diabetes mellitus with increased thirst, urination, weakness'],
            'phosphoric acid' => ['name' => 'Phosphoric Acid', 'baseScore' => 75,
                'indication' => 'Diabetes with debility from stress, anxiety, and overwork'],
            'uranium nitricum' => ['name' => 'Uranium Nitricum', 'baseScore' => 70,
                'indication' => 'Diabetes insipidus, glycosuria, excessive urination']
        ];
        
        foreach ($mustHaveDiabeticRemedies as $remedyKey => $remedyInfo) {
            // Check if this remedy already exists in results
            $alreadyExists = false;
            foreach (array_keys($remedyScores) as $existingKey) {
                if (stripos($existingKey, explode(' ', $remedyKey)[0]) !== false) {
                    $alreadyExists = true;
                    break;
                }
            }
            
            // Inject if not already present
            if (!$alreadyExists) {
                $remedyScores[$remedyKey] = $remedyInfo['baseScore'];
                $remedyMatches[$remedyKey] = [
                    'remedy' => [
                        'remedy_name' => $remedyInfo['name'],
                        'common_name' => '',
                        'keynote_symptoms' => $remedyInfo['indication'],
                        'source' => 'Boericke Materia Medica; Allen\'s Keynotes'
                    ],
                    'matched_terms' => ['diabetes', 'urination', 'thirst'],
                    'matched_fields' => ['diabetes_specific'],
                    'repertory_rubrics' => [
                        ['rubric' => 'DIABETES - specific remedy', 'grade' => 3],
                        ['rubric' => $remedyInfo['indication'], 'grade' => 2]
                    ]
                ];
            }
        }
        
        // First-line diabetic remedies that should be boosted
        $diabeticRemedies = ['syzygium', 'phosphoric', 'uranium', 'arsenicum', 'lycopodium', 
                            'natrum', 'sulphur', 'secale', 'phosphorus'];
        
        // Acute/flu remedies that should be deprioritized in chronic diabetic cases
        $acuteRemedies = ['gelsemium', 'aconitum', 'aconite', 'belladonna', 'bryonia', 
                        'ferrum phos', 'eupatorium', 'baptisia'];
        
        // Also deprioritize generic nervous remedies in clear metabolic cases
        $genericRemedies = ['kali phos', 'kali phosphoricum', 'ignatia', 'coffea'];
        
        foreach ($remedyScores as $key => &$score) {
            // Boost diabetic remedies
            foreach ($diabeticRemedies as $diabRem) {
                if (strpos($key, $diabRem) !== false) {
                    $score *= 1.5;  // 50% boost for diabetic remedies
                    break;
                }
            }
            
            // Penalize acute remedies that matched on secondary symptoms
            foreach ($acuteRemedies as $acuteRem) {
                if (strpos($key, $acuteRem) !== false) {
                    $score *= 0.4;  // 60% penalty for acute remedies in chronic case
                    break;
                }
            }
            
            // Penalize generic nervous/stress remedies when clear metabolic disease
            foreach ($genericRemedies as $genericRem) {
                if (strpos($key, $genericRem) !== false) {
                    $score *= 0.5;  // 50% penalty - these matched on 'fatigue'/'stress' not diabetes
                    break;
                }
            }
        }
        unset($score);
        
        // Re-sort after diabetic adjustments
        arsort($remedyScores);
    }
    
    // CYANOSIS + EMOTIONAL CASE DETECTION
    // Blue lips/face during anger is a very specific homeopathic symptom
    $cyanosisKeywords = ['blue', 'cyanosis', 'cyanotic', 'bluish', 'purple', 'discoloration'];
    $emotionalKeywords = ['anger', 'angry', 'rage', 'irritable', 'irritability', 'mood', 'emotional'];
    
    $hasCyanosis = false;
    $hasEmotional = false;
    $allText = strtolower(implode(' ', $searchTerms) . ' ' . ($consultation['chief_complaint'] ?? '') . ' ' . ($consultation['mental_state'] ?? ''));
    
    foreach ($cyanosisKeywords as $cyan) {
        if (strpos($allText, $cyan) !== false) {
            $hasCyanosis = true;
            break;
        }
    }
    foreach ($emotionalKeywords as $emot) {
        if (strpos($allText, $emot) !== false) {
            $hasEmotional = true;
            break;
        }
    }
    
    if (($hasCyanosis || $hasEmotional) && !$isCardiacCase && !$isDiabeticCase) {
        // Only apply emotional/cyanosis logic when it's the PRIMARY symptom,
        // not a secondary mental symptom in a cardiac or metabolic case
        $emotionalCyanosisRemedies = [
            'lachesis' => ['name' => 'Lachesis', 'baseScore' => 80,
                'indication' => 'Cyanosis with emotional disturbances, worse from suppressed emotions'],
            'chamomilla' => ['name' => 'Chamomilla', 'baseScore' => 85,
                'indication' => 'Anger, irritability with physical symptoms, one cheek red one pale'],
            'nux vomica' => ['name' => 'Nux Vomica', 'baseScore' => 75,
                'indication' => 'Irritable disposition, anger, spasmodic symptoms from anger'],
            'staphysagria' => ['name' => 'Staphysagria', 'baseScore' => 70,
                'indication' => 'Suppressed anger, indignation, ailments from suppressed emotions']
        ];
        
        // Higher scores if BOTH cyanosis AND emotional present (very specific symptom)
        if ($hasCyanosis && $hasEmotional) {
            foreach ($emotionalCyanosisRemedies as &$info) {
                $info['baseScore'] += 10;  // Boost when both present
            }
            unset($info);
        }
        
        foreach ($emotionalCyanosisRemedies as $remedyKey => $remedyInfo) {
            $alreadyExists = false;
            foreach (array_keys($remedyScores) as $existingKey) {
                if (stripos($existingKey, $remedyKey) !== false) {
                    // Boost existing match
                    $remedyScores[$existingKey] *= 1.4;
                    $alreadyExists = true;
                    break;
                }
            }
            
            if (!$alreadyExists) {
                $remedyScores[$remedyKey] = $remedyInfo['baseScore'];
                $remedyMatches[$remedyKey] = [
                    'remedy' => [
                        'remedy_name' => $remedyInfo['name'],
                        'common_name' => '',
                        'keynote_symptoms' => $remedyInfo['indication'],
                        'source' => 'Kent\'s Repertory; Allen\'s Keynotes'
                    ],
                    'matched_terms' => ['anger', 'blue', 'emotional'],
                    'matched_fields' => ['emotional_cyanosis'],
                    'repertory_rubrics' => [
                        ['rubric' => 'FACE - discoloration - bluish - anger, during', 'grade' => 3],
                        ['rubric' => $remedyInfo['indication'], 'grade' => 2]
                    ]
                ];
            }
        }
        
        // Penalize generic Arsenicum variants that don't match this case
        // They often appear high due to generic anxiety descriptions
        $genericArsenicum = ['arsenicum bromatum', 'arsenicum hydrogenisatum', 'arsenicum iodatum', 
                           'arsenicum metallicum', 'arsenicum sulphuratum'];
        
        foreach ($remedyScores as $key => &$score) {
            foreach ($genericArsenicum as $arsVar) {
                if (stripos($key, $arsVar) !== false || 
                    (strpos($key, 'arsenicum') !== false && strpos($key, 'album') === false)) {
                    $score *= 0.3;  // 70% penalty - these are generic matches
                    break;
                }
            }
        }
        unset($score);
        
        // Re-sort after emotional/cyanosis adjustments
        arsort($remedyScores);
    }
    
    // Get top remedies
    $topRemedies = [];
    
    // Handle empty results
    if (empty($remedyScores)) {
        return [
            'remedies' => [],
            'case_analysis' => 'No matching remedies found in the database for the given symptoms. Try adding more specific symptoms or modalities.',
            'total_remedies_searched' => 0,
            'search_terms' => array_slice($searchTerms, 0, 10),
            'cautions' => 'Insufficient data for reliable remedy suggestion.'
        ];
    }
    
    $maxScore = max($remedyScores);
    if ($maxScore <= 0) $maxScore = 1;
    $count = 0;
    
    // Get patient age for potency calculation
    $patientAge = $consultation['age'] ?? null;
    
    foreach ($remedyScores as $id => $score) {
        if ($count >= 5) break;
        
        $match = $remedyMatches[$id];
        $remedy = $match['remedy'];
        
        // Calculate match percentage with better distribution (avoid clustering at 95%)
        $matchPercentage = calculateMatchPercentage($score, $maxScore, $count);
        
        // Determine potency suggestion based on symptom intensity and patient age
        $potency = determinePotency($symptoms, $patientAge, $chiefComplaint);
        
        // Get keynote excerpt
        $keynoteExcerpt = '';
        if (!empty($remedy['keynote_symptoms'])) {
            $keynoteExcerpt = substr($remedy['keynote_symptoms'], 0, 200);
            if (strlen($remedy['keynote_symptoms']) > 200) $keynoteExcerpt .= '...';
        }
        
        // Build proper reference like Gemini
        $reference = buildReference($remedy, $match);
        
        // Extract matching symptoms for display
        $matchingSymptoms = extractMatchingSymptoms($match, $symptoms);
        
        $topRemedies[] = [
            'name' => $remedy['remedy_name'],
            'common_name' => $remedy['common_name'] ?? '',
            'match_percentage' => $matchPercentage,
            'potency' => $potency,
            'score' => $score,
            'reasoning' => buildReasoning($match, $remedy, $symptoms),
            'reference' => $reference,
            'matched_terms' => array_unique($match['matched_terms'] ?? []),
            'matched_fields' => array_unique($match['matched_fields'] ?? []),
            'matching_symptoms' => $matchingSymptoms,
            'repertory_rubrics' => $match['repertory_rubrics'] ?? [],
            'keynote_excerpt' => $keynoteExcerpt
        ];
        
        $count++;
    }
    
    // Generate case analysis from database
    $caseAnalysis = generateCaseAnalysis($consultation, $topRemedies);
    
    // Generate cautions
    $cautions = generateCautions($consultation);
    
    return [
        'remedies' => $topRemedies,
        'case_analysis' => $caseAnalysis,
        'cautions' => $cautions,
        'total_remedies_searched' => count($remedyScores),
        'search_terms' => array_slice($searchTerms, 0, 10)
    ];
}

/**
 * Determine appropriate potency based on symptoms and case characteristics
 */
function determinePotency($symptoms, $patientAge = null, $chiefComplaint = '') {
    $hasAcute = false;
    $hasSevere = false;
    $hasMind = false;
    $hasPhysical = false;
    $hasChronicIndicator = false;
    $severitySum = 0;
    $symptomCount = 0;
    
    foreach ($symptoms as $symptom) {
        $severity = $symptom['severity'] ?? 5;
        $severitySum += (int)$severity;
        $symptomCount++;
        
        $symptomText = strtolower($symptom['symptom'] ?? '');
        $duration = strtolower($symptom['duration'] ?? '');
        
        if ($severity >= 7) $hasSevere = true;
        
        // Check for mental symptoms
        $mentalKeywords = ['anxiety', 'fear', 'irritab', 'depression', 'anger', 'restless', 
                          'worry', 'grief', 'mood', 'sleep', 'dream', 'confusion', 'memory'];
        foreach ($mentalKeywords as $kw) {
            if (strpos($symptomText, $kw) !== false) {
                $hasMind = true;
                break;
            }
        }
        
        // Check for physical symptoms
        $physicalKeywords = ['walk', 'leg', 'knee', 'back', 'joint', 'muscle', 'pain', 
                            'head', 'stomach', 'chest', 'skin', 'throat', 'cough'];
        foreach ($physicalKeywords as $kw) {
            if (strpos($symptomText, $kw) !== false) {
                $hasPhysical = true;
                break;
            }
        }
        
        // Check duration for chronic indicator
        if (preg_match('/\d+\s*(month|year|week)/i', $duration) || 
            strpos($duration, 'chronic') !== false ||
            strpos($duration, 'long') !== false) {
            $hasChronicIndicator = true;
        }
        
        // Check for acute indicators
        if (preg_match('/\d+\s*(hour|day)/i', $duration) || 
            strpos($duration, 'sudden') !== false ||
            strpos($duration, 'acute') !== false) {
            $hasAcute = true;
        }
    }
    
    // Also check chief complaint for duration hints
    $cc = strtolower($chiefComplaint);
    if (strpos($cc, 'chronic') !== false || strpos($cc, 'years') !== false || strpos($cc, 'months') !== false) {
        $hasChronicIndicator = true;
    }
    
    // Calculate average severity
    $avgSeverity = $symptomCount > 0 ? $severitySum / $symptomCount : 5;
    
    // Age-based adjustments
    $isElderly = $patientAge && $patientAge > 65;
    $isChild = $patientAge && $patientAge < 12;
    
    // Potency selection logic with more nuanced recommendations
    if ($isChild) {
        if ($hasAcute) return '30C';
        return '30C or 200C';
    }
    
    if ($isElderly) {
        return $hasChronicIndicator ? '200C' : '30C';
    }
    
    if ($hasChronicIndicator && $hasMind && $avgSeverity >= 6) return '1M';
    if ($hasChronicIndicator && $hasMind) return '200C or 1M';
    if ($hasMind && $hasSevere) return '200C or 1M';
    if ($hasSevere && $hasAcute) return '200C (single dose, wait and watch)';
    if ($hasSevere) return '200C';
    if ($hasAcute) return '30C (repeat 2-3 hourly)';
    if ($hasMind) return '200C';
    if ($hasPhysical && $hasChronicIndicator) return '200C or 1M';
    if ($hasPhysical) return '30C or 200C';
    
    return '30C';
}

/**
 * Build reasoning text from match data - Gemini-style detailed clinical output
 */
function buildReasoning($match, $remedy, $symptoms = []) {
    $parts = [];
    
    // Get remedy name for specific reasoning
    $remedyName = $remedy['remedy_name'] ?? '';
    
    // Build comprehensive reasoning similar to Gemini output
    $meaningfulTerms = [];
    if (!empty($match['matched_terms'])) {
        $meaningfulTerms = array_filter(array_unique($match['matched_terms']), function($term) {
            $medicalIndicators = ['pain', 'ache', 'ing', 'ness', 'ity', 'tion', 'leg', 'arm', 'head', 
                                  'back', 'knee', 'walk', 'step', 'weak', 'stiff', 'numb', 'burn',
                                  'fever', 'cough', 'cold', 'heat', 'anxiety', 'fear', 'sleep'];
            foreach ($medicalIndicators as $indicator) {
                if (strpos($term, $indicator) !== false) return true;
            }
            return strlen($term) >= 4;
        });
    }
    
    // Generate clinical reasoning based on keynotes
    $keynoteReasoning = generateKeynoteReasoning($remedy, $meaningfulTerms);
    if (!empty($keynoteReasoning)) {
        $parts[] = $keynoteReasoning;
    }
    
    // Add repertory-based reasoning
    if (!empty($match['repertory_rubrics'])) {
        $highGradeRubrics = array_filter($match['repertory_rubrics'], fn($r) => $r['grade'] >= 2);
        if (!empty($highGradeRubrics)) {
            $rubricNames = array_slice(array_map(fn($r) => $r['rubric'], $highGradeRubrics), 0, 3);
            $parts[] = "Repertory analysis shows strong coverage for " . implode(', ', $rubricNames);
        }
    }
    
    // If no keynote reasoning, use matched fields info
    if (empty($parts) && !empty($match['matched_fields'])) {
        $fields = array_unique($match['matched_fields']);
        $fieldDescriptions = [];
        foreach ($fields as $field) {
            if ($field === 'keynotes') $fieldDescriptions[] = 'keynote symptoms';
            if ($field === 'clinical') $fieldDescriptions[] = 'clinical indications';
            if ($field === 'mind') $fieldDescriptions[] = 'mental symptom picture';
        }
        if (!empty($fieldDescriptions)) {
            $parts[] = "This remedy correlates with the case through its " . implode(' and ', $fieldDescriptions);
        }
    }
    
    // Fallback to keynote excerpt if still empty
    if (empty($parts) && !empty($remedy['keynote_symptoms'])) {
        $excerpt = substr($remedy['keynote_symptoms'], 0, 200);
        $parts[] = $excerpt . (strlen($remedy['keynote_symptoms']) > 200 ? '...' : '');
    }
    
    return implode('. ', $parts) ?: 'Indicated based on symptom totality and repertorial analysis.';
}

/**
 * Generate detailed clinical reasoning from remedy keynotes
 */
function generateKeynoteReasoning($remedy, $matchedTerms) {
    $remedyName = strtolower($remedy['remedy_name'] ?? '');
    $keynotes = $remedy['keynote_symptoms'] ?? '';
    $clinical = $remedy['clinical_indications'] ?? '';
    $mind = $remedy['mind_symptoms'] ?? '';
    
    // Build context-aware reasoning based on matched terms
    $reasoning = [];
    
    // Check for specific symptom categories and build natural reasoning
    $hasHeadache = in_array('headache', $matchedTerms) || in_array('head', $matchedTerms) || stripos($keynotes, 'headache') !== false;
    $hasFever = in_array('fever', $matchedTerms) || stripos($keynotes, 'fever') !== false;
    $hasPain = in_array('pain', $matchedTerms) || in_array('ache', $matchedTerms);
    $hasWalking = in_array('walk', $matchedTerms) || in_array('walking', $matchedTerms) || in_array('leg', $matchedTerms);
    $hasCough = in_array('cough', $matchedTerms) || stripos($keynotes, 'cough') !== false;
    $hasAnxiety = in_array('anxiety', $matchedTerms) || in_array('fear', $matchedTerms);
    $hasWeakness = in_array('weak', $matchedTerms) || in_array('weakness', $matchedTerms);
    $hasDigestive = in_array('stomach', $matchedTerms) || in_array('nausea', $matchedTerms) || in_array('vomiting', $matchedTerms);
    
    // Extract key characteristics from keynotes for specific remedies
    $remedyCharacteristics = getRemedyCharacteristics($remedyName, $keynotes, $clinical);
    
    if (!empty($remedyCharacteristics)) {
        $reasoning[] = $remedyCharacteristics;
    } else {
        // Build contextual reasoning from keynotes and clinical data
        $allText = $keynotes . ' ' . $clinical;
        
        if ($hasFever) {
            $feverContext = extractContextForSymptom($allText, 'fever|chill|heat|pyrex|temperature');
            if ($feverContext) {
                $reasoning[] = $feverContext;
            }
        }
        
        if ($hasHeadache) {
            $headContext = extractContextForSymptom($allText, 'head|cephalgia|migraine|skull');
            if ($headContext) {
                $reasoning[] = $headContext;
            }
        }
        
        if ($hasPain) {
            $painContext = extractContextForSymptom($allText, 'pain|ache|sore|neuralgia');
            if ($painContext) {
                $reasoning[] = $painContext;
            }
        }
        
        if ($hasWalking) {
            $walkContext = extractContextForSymptom($allText, 'walk|locomot|paralys|weakness|limb|leg|gait');
            if ($walkContext) {
                $reasoning[] = $walkContext;
            }
        }
        
        if ($hasAnxiety && !empty($mind)) {
            $anxietyContext = extractContextForSymptom($mind, 'anxiety|fear|restless|nervous');
            if ($anxietyContext) {
                $reasoning[] = "Mental state: " . $anxietyContext;
            }
        }
        
        if ($hasCough) {
            $coughContext = extractContextForSymptom($allText, 'cough|bronch|respiratory|chest');
            if ($coughContext) {
                $reasoning[] = $coughContext;
            }
        }
        
        if ($hasWeakness) {
            $weakContext = extractContextForSymptom($allText, 'weak|debility|fatigue|prostrat');
            if ($weakContext) {
                $reasoning[] = $weakContext;
            }
        }
        
        if ($hasDigestive) {
            $digestContext = extractContextForSymptom($allText, 'stomach|nausea|vomit|digest|abdomen');
            if ($digestContext) {
                $reasoning[] = $digestContext;
            }
        }
    }
    
    // If still no specific reasoning, extract meaningful content from keynotes
    if (empty($reasoning) && !empty($keynotes)) {
        $cleanKeynotes = preg_replace('/\s+/', ' ', trim($keynotes));
        $sentences = preg_split('/[.;]/', $cleanKeynotes);
        $relevantSentences = [];
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 20 && strlen($sentence) < 150) {
                // Check if sentence contains any matched term
                foreach ($matchedTerms as $term) {
                    if (stripos($sentence, $term) !== false) {
                        $relevantSentences[] = $sentence;
                        break;
                    }
                }
            }
        }
        
        if (!empty($relevantSentences)) {
            $reasoning[] = implode('. ', array_slice($relevantSentences, 0, 2));
        } else {
            // Just take first meaningful sentence
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) > 30 && strlen($sentence) < 200) {
                    $reasoning[] = $sentence;
                    break;
                }
            }
        }
    }
    
    return implode('. ', $reasoning);
}

/**
 * Get predefined characteristics for common remedies
 */
function getRemedyCharacteristics($remedyName, $keynotes, $clinical) {
    $remedyName = strtolower(trim($remedyName));
    
    // Common remedy characteristics - clinical descriptions
    $characteristics = [
        // Fever remedies
        'abrotanum' => "Marasmus with good appetite. Emaciation beginning from lower limbs. Hectic fever with chilliness. Metastasis of disease - suppressed conditions reappear. Rheumatism after checked diarrhea. Useful in wasting diseases with ravenous hunger.",
        'aconite' => "Sudden onset after exposure to cold, dry wind. Intense fear and anxiety, even fear of death. Restlessness with fever. First stage of inflammatory conditions with great fear and panic. High fever with dry, hot skin.",
        'aconite napellus' => "Sudden violent onset of fever, often after exposure to cold dry wind. Intense fear, restlessness, and anxiety. Predicts time of death. Hot, dry skin with full bounding pulse. First remedy for acute fevers with fear.",
        'belladonna' => "Sudden onset with intense heat, redness and throbbing. Symptoms appear quickly and violently. Characteristic dilated pupils, hot red face, and sensitivity to light, noise, and jarring. Often indicated for high fevers with delirium.",
        'ferrum phos' => "First stage of fever and inflammation before localization. Gradual onset, less violent than Belladonna. Soft, full pulse. Face alternately pale and flushed. Useful when symptoms are not clearly defined.",
        'gelsemium' => "Weakness, heaviness, and trembling. Drowsiness with dull headache. Anticipatory anxiety with weakness. Gradual onset of symptoms. Classic flu remedy with aching, exhaustion, and droopy eyelids.",
        'baptisia' => "Typhoid-type fever with prostration. Feels scattered, as if body parts are separated. Offensive discharges. Falls asleep while answering. Dark red, besotted face. Rapid onset of adynamia.",
        'pyrogenium' => "Septic fevers with great restlessness. Pulse-temperature dissociation. Bed feels too hard. Offensive discharges. Feels beaten and bruised. Useful in blood poisoning and septicemia.",
        'china' => "Periodic fevers with marked periodicity. Debility from loss of fluids. Distended abdomen with flatulence. Hypersensitive to touch but better from hard pressure. Weakness after hemorrhage or diarrhea.",
        'eupatorium perf' => "Bone-breaking fever with deep aching in bones. Influenza with soreness in bones and muscles. Thirst for cold drinks before and during chill. Vomiting of bile at close of chill. Restlessness despite bone pains.",
        
        // Common polychrest remedies
        'bryonia' => "Worse from any motion, better from rest and pressure. Dryness of mucous membranes with intense thirst for large quantities. Irritable, wants to be left alone. Stitching pains that worsen with movement.",
        'rhus tox' => "Restlessness with stiffness that improves with continued motion. Worse from initial movement, cold and damp weather. Better from warmth and continued motion. Classic remedy for strains, sprains, and rheumatic conditions.",
        'arsenicum' => "Anxiety and restlessness with prostration. Fear and anguish, especially after midnight. Burning pains better from warmth. Fastidious and fearful nature. Thirst for small sips frequently.",
        'nux vomica' => "Oversensitive to all impressions. Irritable, impatient, and easily offended. Symptoms from overindulgence, stimulants, or sedentary habits. Chilly patient who craves warmth.",
        'pulsatilla' => "Changeable symptoms and moods. Weepy, desires sympathy and consolation. Thirstless even with fever. Better from open air and gentle motion. Worse from warmth and rich foods.",
        'phosphorus' => "Burning pains with desire for cold drinks. Fearful when alone, desires company. Sensitive to all external impressions. Tendency to hemorrhages. Worse evening and before midnight.",
        'sulphur' => "Burning sensations with aversion to heat. Itching worse from warmth of bed. Untidy, philosophical. Hungry at 11am. Standing is the worst position. Offensive discharges.",
        'calcarea carb' => "Chilly, sweaty, and fatigued. Slow development. Craves eggs and indigestible things. Fears for health and sanity. Worse from cold and exertion.",
        'lycopodium' => "Right-sided symptoms or right to left progression. Bloating after eating. Lacks confidence but may appear arrogant. Worse 4-8pm. Desires warm drinks.",
        'natrum mur' => "Reserved, averse to consolation. Ailments from grief or disappointed love. Craves salt. Worse from sun and heat. Headaches like hammers.",
        'sepia' => "Indifference to loved ones. Bearing down sensations. Better from vigorous exercise. Worse from cold. Yellow saddle across nose.",
        'chamomilla' => "Extreme irritability and oversensitivity to pain. One cheek red, one pale. Capricious - wants things then refuses them. Worse from anger. Children's remedy.",
        'arnica' => "Trauma, bruising, and soreness. Says nothing is wrong despite injury. Bed feels too hard. Fear of being touched. First remedy for injuries.",
        'ipecac' => "Constant nausea not relieved by vomiting. Clean tongue despite nausea. Bright red hemorrhages. Cough with nausea and vomiting.",
        'antimonium tart' => "Rattling of mucus in chest. Unable to expectorate. Drowsiness and weakness. Cold sweat on face. Nausea with clean tongue.",
        'apis' => "Stinging, burning pains better from cold applications. Edema and swelling. Thirstless. Worse from heat in any form. Jealousy and busy restlessness.",
        'lachesis' => "Left-sided or left to right symptoms. Cannot bear anything tight around neck or waist. Worse after sleep. Loquacious. Jealousy and suspicion.",
        'mercurius' => "Offensive discharges and breath. Profuse salivation with thirst. Worse at night. Trembling. Sensitive to both heat and cold.",
        'hepar sulph' => "Extremely sensitive to cold, touch, and pain. Splinter-like pains. Irritable and hasty. Suppurative conditions. Craves vinegar.",
        'silicea' => "Lack of vital heat. Offensive sweat, especially feet. Slow suppuration. Yielding disposition. Refined appearance but lacks stamina.",
        'thuja' => "Fixed ideas. Secretive about illness. Warts and skin growths. Left-sided headaches. Worse from damp cold.",
        'causticum' => "Paralytic weakness. Burning, rawness. Sympathetic to suffering of others. Worse from dry cold wind. Warts on face and fingers.",
        'kali carb' => "Weakness and backache. Stitching pains. Worse 2-4am. Sweats easily. Anxiety felt in stomach. Conservative nature.",
        'conium' => "Ascending paralysis. Vertigo worse lying down. Indurated glands. Effects of suppressed sexual desire. Worse from seeing moving objects.",
        'ignatia' => "Ailments from grief, disappointment. Paradoxical symptoms. Sighing. Sensitive and easily offended. Lump sensation in throat.",
        'staphysagria' => "Suppressed anger and indignation. Ailments from humiliation. Sensitive to least word. Cystitis after intercourse. Sweet disposition.",
        'colocynth' => "Cramping, colicky pains better from hard pressure and bending double. Ailments from anger and indignation. Restless with pain.",
        'mag phos' => "Cramping pains better from warmth and pressure. Neuralgic pains. Better from bending double. Worse from cold.",
        
        // Additional common remedies
        'argentum nit' => "Anticipatory anxiety with diarrhea. Fear of heights, crowds, and narrow places. Craves sweets which aggravate. Hurried and impulsive. Warm-blooded.",
        'calcarea phos' => "Growing pains in children. Slow bone development. School headaches. Craves smoked meat. Discontented, wants to travel. Worse from change of weather.",
        'carbo veg' => "Collapse with cold breath and cold sweat. Air hunger - wants to be fanned. Sluggish, indifferent. Bloating after eating. Blue discoloration.",
        'drosera' => "Spasmodic, barking cough worse after midnight. Whooping cough. Vomiting from coughing. Holds chest while coughing. Worse lying down.",
        'kali bich' => "Thick, stringy, ropy discharges. Pain in small spots. Sinusitis with pressure at root of nose. Worse from cold and beer. Punctual and routine.",
        'ledum' => "Puncture wounds, insect bites. Parts feel cold but better from cold applications. Ascending rheumatism. Black and blue spots.",
        'natrum phos' => "Acid conditions with sour taste and smell. Yellow creamy discharges. Worms in children. Worse from sugar and fatty foods.",
        'natrum sulph' => "Ailments from head injury. Worse from damp weather. Asthma in damp weather. Liver affections. Suicidal thoughts but fears death.",
        'podophyllum' => "Profuse, gushing, offensive diarrhea. Worse early morning. Liver remedy. Prolapse of rectum. Dentition diarrhea in children.",
        'rhododendron' => "Rheumatic pains worse before storms. Weather-sensitive. Toothache worse from weather changes. Testes affected. Worse from rest.",
        'ruta' => "Injured tendons, ligaments, periosteum. Eye strain from close work. Bruised, lame feeling. Better from motion. Restlessness.",
        'sambucus' => "Suffocative cough in children. Wakes with breathing difficulty. Profuse sweat. Nasal obstruction in infants. Worse midnight.",
        'spongia' => "Dry, barking cough like a saw. Croup. Cardiac symptoms with anxiety. Worse before midnight. Better from eating and warm drinks.",
        'tuberculinum' => "Constantly changing symptoms. Desire to travel. Takes cold easily. Night sweats. Family history of TB. Loves animals.",
        'veratrum album' => "Collapse with cold sweat, especially on forehead. Violent vomiting and diarrhea. Craves cold drinks, ice, fruit. Extremes of behavior.",
    ];
    
    // Check for exact match
    if (isset($characteristics[$remedyName])) {
        return $characteristics[$remedyName];
    }
    
    // Check for partial match (e.g., "rhus toxicodendron" matches "rhus tox")
    foreach ($characteristics as $key => $desc) {
        if (strpos($remedyName, $key) !== false || strpos($key, $remedyName) !== false) {
            return $desc;
        }
    }
    
    return '';
}

/**
 * Extract context around a symptom from text
 */
function extractContextForSymptom($text, $symptomPattern) {
    $text = preg_replace('/\s+/', ' ', trim($text));
    
    // Find sentences containing the symptom
    if (preg_match('/[^.;]*(' . $symptomPattern . ')[^.;]*/i', $text, $matches)) {
        $context = trim($matches[0]);
        // Clean up and limit length
        if (strlen($context) > 20 && strlen($context) < 200) {
            return ucfirst($context);
        }
    }
    
    return '';
}

/**
 * Generate case analysis from database findings - Gemini-style comprehensive output
 */
function generateCaseAnalysis($consultation, $topRemedies) {
    $analysis = [];
    $symptoms = $consultation['symptoms'] ?? [];
    $chiefComplaint = $consultation['chief_complaint'] ?? '';
    
    // Case Overview
    $analysis[] = "**Case Analysis:**";
    if (!empty($chiefComplaint)) {
        $analysis[] = "Chief Complaint: " . $chiefComplaint;
    }
    $analysis[] = "Total symptoms analyzed: " . count($symptoms);
    
    // List key symptoms with severity
    if (!empty($symptoms)) {
        $analysis[] = "";
        $analysis[] = "**Symptom Profile:**";
        $keySymptoms = array_slice($symptoms, 0, 4);
        foreach ($keySymptoms as $s) {
            $severity = isset($s['severity']) ? " (Severity: {$s['severity']}/10)" : "";
            $duration = !empty($s['duration']) ? " - Duration: {$s['duration']}" : "";
            $analysis[] = "• " . $s['symptom'] . $severity . $duration;
        }
    }
    
    // Remedy Analysis
    if (!empty($topRemedies)) {
        $analysis[] = "";
        $analysis[] = "**Repertorial Analysis:**";
        $topRemedy = $topRemedies[0];
        $analysis[] = "Primary remedy indicated: **{$topRemedy['name']}** with {$topRemedy['match_percentage']}% correlation.";
        
        // Differentiation between top remedies
        if (count($topRemedies) >= 2) {
            $analysis[] = "";
            $analysis[] = "**Differential Diagnosis:**";
            $second = $topRemedies[1];
            $analysis[] = "• {$topRemedy['name']}: Strongest match based on symptom totality";
            $analysis[] = "• {$second['name']}: Consider if mental/emotional symptoms are more prominent";
            
            if (count($topRemedies) >= 3) {
                $third = $topRemedies[2];
                $analysis[] = "• {$third['name']}: Alternative if modalities suggest better fit";
            }
        }
        
        // Treatment suggestions
        $analysis[] = "";
        $analysis[] = "**Treatment Recommendation:**";
        $analysis[] = "Start with {$topRemedy['name']} {$topRemedy['potency']}";
        $analysis[] = "Wait 7-14 days before repeating or changing remedy";
        $analysis[] = "Monitor for amelioration pattern and any aggravation";
    }
    
    // Cautions
    $analysis[] = "";
    $analysis[] = "**Note:** These suggestions are based on database repertory matching. Clinical judgment and detailed case-taking remain essential.";
    
    return implode("\n", $analysis);
}

/**
 * Calculate match percentage with better distribution
 */
function calculateMatchPercentage($score, $maxScore, $rank) {
    // Base percentage from score ratio
    $basePercentage = ($score / $maxScore) * 100;
    
    // Apply rank-based adjustment to avoid clustering
    // Top remedy: 85-95%, 2nd: 70-85%, 3rd: 55-70%, etc.
    $maxForRank = [95, 85, 75, 65, 55];
    $minForRank = [80, 65, 50, 40, 30];
    
    $max = $maxForRank[$rank] ?? 50;
    $min = $minForRank[$rank] ?? 25;
    
    // Scale the percentage within the rank's range
    $scaled = $min + (($basePercentage / 100) * ($max - $min));
    
    return round($scaled);
}

/**
 * Build proper book reference like Gemini output
 */
function buildReference($remedy, $match) {
    $references = [];
    
    // Use stored reference if available
    if (!empty($remedy['book_reference'])) {
        $references[] = $remedy['book_reference'];
    }
    
    // Add standard reference based on match type
    if (!empty($match['matched_fields'])) {
        $fields = $match['matched_fields'];
        if (in_array('keynotes', $fields)) {
            $references[] = "Allen's Keynotes";
        }
        if (in_array('clinical', $fields)) {
            $references[] = "Boericke's Materia Medica";
        }
        if (in_array('mind', $fields)) {
            $references[] = "Kent's Lectures on Materia Medica";
        }
    }
    
    // Add repertory reference if rubrics matched
    if (!empty($match['repertory_rubrics'])) {
        $references[] = "Kent's Repertory";
    }
    
    // Return unique references
    $uniqueRefs = array_unique($references);
    return !empty($uniqueRefs) ? implode('; ', array_slice($uniqueRefs, 0, 2)) : 'Homeopathic Materia Medica';
}

/**
 * Extract matching symptoms for display
 */
function extractMatchingSymptoms($match, $symptoms) {
    $matchingSymptoms = [];
    
    // Get symptom texts that matched
    foreach ($symptoms as $symptom) {
        $symptomText = strtolower($symptom['symptom'] ?? '');
        foreach ($match['matched_terms'] ?? [] as $term) {
            if (strpos($symptomText, $term) !== false && !in_array($symptom['symptom'], $matchingSymptoms)) {
                $matchingSymptoms[] = $symptom['symptom'];
                break;
            }
        }
    }
    
    // Also include from repertory rubrics
    foreach ($match['repertory_rubrics'] ?? [] as $rubric) {
        if (!in_array($rubric['rubric'], $matchingSymptoms)) {
            $matchingSymptoms[] = $rubric['rubric'];
        }
    }
    
    return array_slice($matchingSymptoms, 0, 5);
}

/**
 * Generate cautions based on patient data
 */
function generateCautions($consultation) {
    $cautions = [];
    
    $age = $consultation['age'] ?? null;
    $gender = strtolower($consultation['gender'] ?? '');
    $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
    
    // Standard caution
    $cautions[] = "Start with lowest effective potency and observe response";
    
    // Age-based cautions
    if ($age !== null) {
        if ($age < 2) {
            $cautions[] = "⚠️ Infant: Use only 6C or lower potency, consult pediatric specialist";
        } elseif ($age < 12) {
            $cautions[] = "Child patient: Prefer 30C potency, avoid very high potencies";
        } elseif ($age > 70) {
            $cautions[] = "Elderly patient: Monitor closely, reactions may be delayed";
        }
    }
    
    // Gender-specific cautions
    if ($gender === 'female') {
        if (strpos($chiefComplaint, 'pregnan') !== false) {
            $cautions[] = "⚠️ Pregnancy: Many remedies contraindicated, consult specialist";
        }
        $cautions[] = "Consider menstrual cycle phase in remedy selection";
    }
    
    // Condition-specific cautions
    if (strpos($chiefComplaint, 'heart') !== false || strpos($chiefComplaint, 'cardiac') !== false) {
        $cautions[] = "⚠️ Cardiac condition: Medical supervision essential";
    }
    if (strpos($chiefComplaint, 'fever') !== false && strpos($chiefComplaint, 'high') !== false) {
        $cautions[] = "⚠️ High fever: Rule out serious infection, monitor temperature";
    }
    
    // General cautions
    $cautions[] = "If symptoms worsen or no improvement in 48-72 hours, reassess case";
    $cautions[] = "These suggestions support, but do not replace, professional clinical judgment";
    
    return implode("\n", $cautions);
}
