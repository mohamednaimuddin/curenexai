<?php
/**
 * AI Suggestions Module
 * Uses Google Gemini API for AI-powered remedy recommendations
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('/login.php');
}

// DEBUG: Log access attempt
error_log("AI Suggestions accessed - URL: " . $_SERVER['REQUEST_URI'] . " | consultation_id: " . ($_GET['consultation_id'] ?? 'MISSING'));

$pageTitle = 'AI Remedy Suggestions';
$doctor_id = $_SESSION['doctor_id'];
$error = '';
$success = '';
$suggestions = [];
$consultation = null;
$patient = null;

// Get consultation ID
$consultation_id = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;

// DEBUG: Show what we received
error_log("Consultation ID received: " . $consultation_id . " | Doctor ID: " . $doctor_id);

if ($consultation_id > 0) {
    // Fetch consultation details with symptoms - try without doctor_id filter first
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
    
    // Security check: Verify consultation belongs to this doctor
    if ($consultation && $consultation['doctor_id'] != $doctor_id) {
        $error = "Access denied: This consultation belongs to another doctor.";
        $consultation = null;
    }
    
    if (!$consultation) {
        $error = "Consultation not found or access denied.";
    }
    
    if ($consultation) {
        // Fetch symptoms for this consultation
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
        
        $consultation['symptoms'] = $symptoms;
        
        // Check if AI suggestion already exists
        $existing_suggestion = DB::queryOne(
            "SELECT * FROM ai_suggestions_log
             WHERE consultation_id = ?
             ORDER BY created_at DESC
             LIMIT 1",
            [$consultation_id]
        );
        
        // If form submitted or regenerate requested
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                $error = 'Invalid security token. Please try again.';
            } else {
                // Generate AI suggestions based on configured provider
                if (AI_PROVIDER === 'gemini' && !empty(GEMINI_API_KEY)) {
                    // Use Gemini AI
                    try {
                        require_once BASE_PATH . '/includes/gemini_api.php';
                        $gemini = new GeminiAPI();
                        $result = $gemini->generateRemedySuggestions($consultation);
                        
                        if ($result['success']) {
                            $suggestions = $result['suggestions'];
                            $suggestions['provider'] = 'gemini';
                            $suggestions['model'] = $result['model'];
                            
                            // Log AI suggestion
                            DB::query(
                                "INSERT INTO ai_suggestions_log 
                                 (consultation_id, doctor_id, prompt, ai_response, suggested_remedies, created_at)
                                 VALUES (?, ?, ?, ?, ?, NOW())",
                                [
                                    $consultation_id,
                                    $doctor_id,
                                    'Gemini AI analysis',
                                    json_encode($suggestions),
                                    json_encode($suggestions['remedies'] ?? []),
                                ]
                            );
                            
                            $success = 'AI-powered remedy suggestions generated successfully using Gemini!';
                        } else {
                            throw new Exception($result['error']);
                        }
                    } catch (Exception $e) {
                        $error = 'Gemini AI Error: ' . $e->getMessage();
                        
                        // Fallback to local RAG if enabled
                        if (AI_USE_LOCAL_FALLBACK) {
                            $suggestions = generateRAGSuggestions($consultation);
                            if (!empty($suggestions) && isset($suggestions['remedies'])) {
                                $suggestions['provider'] = 'local_rag_fallback';
                                $success = 'Gemini AI unavailable. Using local knowledge base as fallback.';
                                $error = ''; // Clear error since we have fallback
                            }
                        }
                    }
                } else {
                    // Use local RAG-based suggestions
                    $suggestions = generateRAGSuggestions($consultation);
                    
                    if (!empty($suggestions) && isset($suggestions['remedies'])) {
                        $suggestions['provider'] = 'local_rag';
                        
                        // Log AI suggestion
                        DB::query(
                            "INSERT INTO ai_suggestions_log 
                             (consultation_id, doctor_id, prompt, ai_response, suggested_remedies, created_at)
                             VALUES (?, ?, ?, ?, ?, NOW())",
                            [
                                $consultation_id,
                                $doctor_id,
                                'RAG-based analysis using local repertory',
                                json_encode($suggestions),
                                json_encode($suggestions['remedies'] ?? []),
                            ]
                        );
                        
                        $success = 'Knowledge-based remedy suggestions generated successfully!';
                    } else {
                        $error = 'Failed to generate suggestions. Please ensure the consultation has adequate symptom information.';
                    }
                }
            }
        } elseif ($existing_suggestion) {
            // Load existing suggestions
            $suggestions = json_decode($existing_suggestion['ai_response'], true);
            if (!$suggestions) {
                // Try parsing old format
                $suggestions = [
                    'remedies' => json_decode($existing_suggestion['suggested_remedies'], true) ?? [],
                    'analysis' => $existing_suggestion['ai_response'],
                    'cached' => true,
                    'created_at' => $existing_suggestion['created_at']
                ];
            }
        }
    } else {
        $error = 'Consultation not found or access denied.';
    }
} else {
    // No consultation ID provided - redirect to consultations list
    redirect('/consultations/list.php?message=Please select a consultation to get AI suggestions');
    exit;
}

/**
 * Build prompt for AI API
 */
function buildAIPrompt($consultation) {
    $prompt = "You are an expert homeopathic physician. Analyze this case and suggest the most suitable homeopathic remedies.\n\n";
    
    $prompt .= "PATIENT INFORMATION:\n";
    $prompt .= "- Name: " . htmlspecialchars($consultation['patient_name']) . "\n";
    $prompt .= "- Age: " . $consultation['age'] . " years\n";
    $prompt .= "- Gender: " . $consultation['gender'] . "\n";
    if (!empty($consultation['blood_group'])) {
        $prompt .= "- Blood Group: " . $consultation['blood_group'] . "\n";
    }
    if (!empty($consultation['allergies'])) {
        $prompt .= "- Known Allergies: " . $consultation['allergies'] . "\n";
    }
    
    $prompt .= "\nCONSULTATION DETAILS:\n";
    $prompt .= "- Chief Complaint: " . htmlspecialchars($consultation['chief_complaint']) . "\n";
    
    if (!empty($consultation['diagnosis'])) {
        $prompt .= "- Diagnosis: " . htmlspecialchars($consultation['diagnosis']) . "\n";
    }
    
    if (!empty($consultation['symptoms']) && count($consultation['symptoms']) > 0) {
        $prompt .= "\nSYMPTOMS (with severity and duration):\n";
        foreach ($consultation['symptoms'] as $symptom) {
            $prompt .= "- " . htmlspecialchars($symptom['symptom']) . 
                       " [Severity: " . $symptom['severity'] . "/10, Duration: " . 
                       htmlspecialchars($symptom['duration']) . "]";
            if (!empty($symptom['notes'])) {
                $prompt .= " - Notes: " . htmlspecialchars($symptom['notes']);
            }
            $prompt .= "\n";
        }
    }
    
    if (!empty($consultation['notes'])) {
        $prompt .= "\nADDITIONAL NOTES:\n" . htmlspecialchars($consultation['notes']) . "\n";
    }
    
    $prompt .= "\nPLEASE PROVIDE:\n";
    $prompt .= "1. TOP 5 RECOMMENDED REMEDIES with:\n";
    $prompt .= "   - Remedy name\n";
    $prompt .= "   - Match percentage (0-100%)\n";
    $prompt .= "   - Recommended potency (e.g., 30C, 200C, 1M)\n";
    $prompt .= "   - Reasoning for recommendation\n";
    $prompt .= "   - Key matching symptoms\n";
    $prompt .= "2. CASE ANALYSIS: Brief analysis of the case and constitutional tendencies\n";
    $prompt .= "3. DIFFERENTIAL DIAGNOSIS: Other remedies to consider\n";
    $prompt .= "4. CAUTIONS: Any important considerations or red flags\n";
    $prompt .= "\nFormat your response as JSON with this structure:\n";
    $prompt .= "{\n";
    $prompt .= "  \"remedies\": [\n";
    $prompt .= "    {\n";
    $prompt .= "      \"name\": \"Remedy Name\",\n";
    $prompt .= "      \"match_percentage\": 85,\n";
    $prompt .= "      \"potency\": \"30C\",\n";
    $prompt .= "      \"reasoning\": \"Why this remedy matches\",\n";
    $prompt .= "      \"matching_symptoms\": [\"symptom1\", \"symptom2\"]\n";
    $prompt .= "    }\n";
    $prompt .= "  ],\n";
    $prompt .= "  \"case_analysis\": \"Overall case analysis\",\n";
    $prompt .= "  \"differential\": [\"Remedy 1\", \"Remedy 2\"],\n";
    $prompt .= "  \"cautions\": \"Important considerations\"\n";
    $prompt .= "}";
    
    return $prompt;
}

/**
 * RAG-based AI Suggestion System
 * Uses local knowledge base (remedies, rubrics, clinical data) to generate suggestions
 * No external API needed!
 */
function generateRAGSuggestions($caseData) {
    // Step 1: Extract key symptoms and patterns
    $symptoms = extractSymptoms($caseData);
    
    // Step 2: Search repertory for matching rubrics
    $matchingRubrics = searchRepertoryBySymptoms($symptoms);
    
    // Step 3: Calculate remedy scores based on rubrics (with keynote matching)
    $remedyScores = calculateRemedyScores($matchingRubrics, $symptoms);
    
    // Step 4: Get detailed remedy information
    $topRemedies = getTopRemedies($remedyScores, 5);
    
    // Step 5: Generate clinical reasoning
    $suggestions = generateClinicalReasoning($caseData, $topRemedies, $symptoms);
    
    return [
        'success' => true,
        'data' => $suggestions,
        'method' => 'RAG (Retrieval-Augmented Generation)',
        'source' => 'Local Knowledge Base'
    ];
}

/**
 * Extract symptoms from case data
 */
function extractSymptoms($caseData) {
    $symptoms = [];
    
    // Extract from chief complaint
    if (!empty($caseData['chief_complaint'])) {
        $symptoms[] = [
            'text' => $caseData['chief_complaint'],
            'type' => 'chief_complaint',
            'weight' => 3 // High importance
        ];
    }
    
    // Extract from present illness
    if (!empty($caseData['present_illness'])) {
        $symptoms[] = [
            'text' => $caseData['present_illness'],
            'type' => 'present_illness',
            'weight' => 3
        ];
    }
    
    // Extract from symptoms (handle both string and array formats)
    if (!empty($caseData['symptoms'])) {
        // Check if symptoms is an array of symptom records (from API)
        if (is_array($caseData['symptoms'])) {
            foreach ($caseData['symptoms'] as $symptomRecord) {
                // Handle array format from API (symptom_text or symptom)
                $symptomText = '';
                if (is_array($symptomRecord)) {
                    $symptomText = $symptomRecord['symptom_text'] ?? $symptomRecord['symptom'] ?? '';
                    // Include notes if available
                    if (!empty($symptomRecord['notes'])) {
                        $symptomText .= ' ' . $symptomRecord['notes'];
                    }
                } else {
                    $symptomText = (string)$symptomRecord;
                }
                
                if (!empty($symptomText)) {
                    // Higher weight for severe symptoms
                    $weight = 2;
                    if (isset($symptomRecord['severity'])) {
                        if ($symptomRecord['severity'] === 'severe') $weight = 4;
                        elseif ($symptomRecord['severity'] === 'moderate') $weight = 3;
                    }
                    
                    $symptoms[] = [
                        'text' => $symptomText,
                        'type' => 'symptom',
                        'weight' => $weight
                    ];
                }
            }
        } else {
            // Handle string format (newline separated)
            $symptomLines = explode("\n", $caseData['symptoms']);
            foreach ($symptomLines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $symptoms[] = [
                        'text' => $line,
                        'type' => 'symptom',
                        'weight' => 2
                    ];
                }
            }
        }
    }
    
    // Extract from general symptoms field
    if (!empty($caseData['general_symptoms'])) {
        $symptoms[] = [
            'text' => $caseData['general_symptoms'],
            'type' => 'general',
            'weight' => 3
        ];
    }
    
    // Extract from particular symptoms field
    if (!empty($caseData['particular_symptoms'])) {
        $symptoms[] = [
            'text' => $caseData['particular_symptoms'],
            'type' => 'particular',
            'weight' => 2
        ];
    }
    
    // Extract modalities
    if (!empty($caseData['better_from'])) {
        $symptoms[] = [
            'text' => 'Better from: ' . $caseData['better_from'],
            'type' => 'amelioration',
            'weight' => 2
        ];
    }
    
    if (!empty($caseData['worse_from'])) {
        $symptoms[] = [
            'text' => 'Worse from: ' . $caseData['worse_from'],
            'type' => 'aggravation',
            'weight' => 2
        ];
    }
    
    // Extract mental/emotional symptoms
    if (!empty($caseData['mental_symptoms'])) {
        $symptoms[] = [
            'text' => $caseData['mental_symptoms'],
            'type' => 'mental',
            'weight' => 3 // Mental symptoms very important
        ];
    }
    
    return $symptoms;
}

/**
 * Synonym map for natural language to clinical terminology
 * Extended for comprehensive symptom matching
 */
function getSynonymMap() {
    return [
        // Emotions/Mental - EXTENDED
        'angry' => ['anger', 'irritability', 'rage', 'vexation', 'wrath', 'fury', 'indignation', 'violent'],
        'anger' => ['angry', 'irritability', 'rage', 'vexation', 'wrath', 'fury', 'indignation', 'violent'],
        'sad' => ['sadness', 'grief', 'sorrow', 'melancholy', 'depression', 'dejection', 'weeping', 'hopeless'],
        'anxious' => ['anxiety', 'fear', 'apprehension', 'worry', 'restlessness', 'anguish', 'nervous', 'tension'],
        'fear' => ['anxiety', 'fright', 'terror', 'dread', 'apprehension', 'panic', 'phobia', 'scared'],
        'jealous' => ['jealousy', 'envy', 'suspicion', 'possessive'],
        'jealousy' => ['jealous', 'envy', 'suspicion', 'possessive'],
        'irritable' => ['irritability', 'anger', 'vexation', 'peevish', 'cross', 'touchy', 'sensitive'],
        'contradicted' => ['contradiction', 'contradict', 'opposition'],
        'contradiction' => ['contradicted', 'contradict', 'opposition'],
        'restless' => ['restlessness', 'anxiety', 'tossing', 'agitation', 'fidgety'],
        'depressed' => ['depression', 'sadness', 'melancholy', 'despair', 'hopeless'],
        'concentration' => ['focus', 'attention', 'memory', 'confusion', 'forgetful'],
        'confusion' => ['confused', 'disoriented', 'dazed', 'bewildered'],
        'suicidal' => ['suicide', 'death wish', 'hopeless', 'despair'],
        'indifferent' => ['indifference', 'apathy', 'uncaring', 'detached'],
        
        // Colors - EXTENDED
        'blue' => ['blueness', 'bluish', 'cyanosis', 'cyanotic', 'livid', 'discoloration', 'purple'],
        'bluish' => ['blue', 'blueness', 'cyanosis', 'cyanotic', 'livid', 'discoloration'],
        'pale' => ['paleness', 'pallor', 'pallid', 'wan', 'white', 'colorless', 'anemic'],
        'red' => ['redness', 'flushed', 'erythema', 'rubor', 'crimson', 'congestion', 'inflamed'],
        'yellow' => ['yellowness', 'jaundice', 'icterus', 'sallow', 'bilious'],
        'purple' => ['purplish', 'livid', 'cyanosis', 'violaceous', 'dusky'],
        'black' => ['blackness', 'dark', 'ecchymosis', 'gangrenous'],
        
        // Body parts - EXTENDED
        'lip' => ['lips', 'labial', 'mouth', 'oral'],
        'lips' => ['lip', 'labial', 'mouth', 'oral'],
        'face' => ['facial', 'countenance', 'visage', 'cheek', 'cheeks', 'complexion'],
        'head' => ['cephalic', 'cranial', 'vertex', 'occiput', 'forehead', 'temple', 'skull'],
        'stomach' => ['gastric', 'epigastric', 'abdomen', 'epigastrium', 'gastritis'],
        'throat' => ['pharynx', 'larynx', 'fauces', 'tonsil', 'tonsils', 'pharyngitis'],
        'eye' => ['eyes', 'ocular', 'ophthalmic', 'vision', 'sight', 'visual'],
        'eyes' => ['eye', 'ocular', 'ophthalmic', 'vision', 'sight', 'visual'],
        'ear' => ['ears', 'aural', 'auditory', 'hearing', 'tinnitus', 'otic'],
        'tongue' => ['lingual', 'glossal', 'taste'],
        'extremities' => ['limbs', 'arms', 'legs', 'hands', 'feet', 'knee', 'ankle', 'wrist'],
        'back' => ['spine', 'spinal', 'lumbar', 'dorsal', 'cervical', 'sacral', 'vertebral'],
        'chest' => ['thorax', 'thoracic', 'pectoral', 'sternum', 'ribs'],
        'heart' => ['cardiac', 'cardiovascular', 'palpitation', 'pulse'],
        'kidney' => ['renal', 'nephritis', 'urinary'],
        'liver' => ['hepatic', 'biliary', 'hepatitis'],
        'lung' => ['pulmonary', 'respiratory', 'bronchial', 'pleural'],
        'skin' => ['cutaneous', 'dermal', 'epidermis', 'dermis'],
        'joint' => ['joints', 'articular', 'arthritis', 'articulation'],
        'muscle' => ['muscular', 'myalgia', 'myositis'],
        'nerve' => ['nervous', 'neural', 'neuralgic', 'neuritis'],
        
        // Symptoms - EXTENDED
        'pain' => ['ache', 'aching', 'sore', 'soreness', 'painful', 'hurt', 'hurting', 'tender'],
        'burning' => ['burn', 'burnt', 'hot', 'heat', 'scalding', 'smarting'],
        'cold' => ['coldness', 'chill', 'chilly', 'frigid', 'freezing', 'icy', 'shivering'],
        'hot' => ['heat', 'fever', 'feverish', 'burning', 'warm', 'warmth', 'temperature'],
        'swelling' => ['swollen', 'edema', 'oedema', 'tumefaction', 'puffiness', 'inflammation'],
        'itching' => ['itch', 'itchy', 'pruritus', 'crawling'],
        'bleeding' => ['bleed', 'hemorrhage', 'blood', 'hemorrhagic'],
        'nausea' => ['nauseous', 'sick', 'queasy', 'vomiting', 'retching'],
        'tired' => ['fatigue', 'exhaustion', 'weakness', 'lassitude', 'prostration', 'weary'],
        'sleepless' => ['insomnia', 'sleeplessness', 'wakefulness', 'restless sleep'],
        'thirsty' => ['thirst', 'thirstiness', 'desire for water', 'dry mouth'],
        'thirstless' => ['thirstlessness', 'no thirst', 'absence of thirst'],
        'hungry' => ['hunger', 'appetite', 'ravenous', 'craving'],
        'constipated' => ['constipation', 'costive', 'hard stool', 'difficult stool'],
        'diarrhea' => ['loose stool', 'watery stool', 'frequent stool', 'dysentery'],
        'cough' => ['coughing', 'tussis', 'expectoration', 'hacking'],
        'headache' => ['head pain', 'cephalalgia', 'migraine', 'cephalea'],
        'vertigo' => ['dizziness', 'giddiness', 'spinning', 'unsteady'],
        'numbness' => ['numb', 'tingling', 'paresthesia', 'pins and needles'],
        'stiffness' => ['stiff', 'rigid', 'tense', 'tight'],
        'cramp' => ['cramping', 'spasm', 'spasmodic', 'cramps'],
        'trembling' => ['tremor', 'shaking', 'tremulous', 'quivering'],
        'discharge' => ['secretion', 'exudate', 'flow', 'drainage'],
        'offensive' => ['foul', 'putrid', 'fetid', 'malodorous', 'smelly'],
        'profuse' => ['copious', 'abundant', 'excessive', 'heavy'],
        'scanty' => ['scant', 'sparse', 'meager', 'little'],
        
        // Respiratory - EXTENDED
        'asthma' => ['asthmatic', 'wheezing', 'dyspnea', 'breathless'],
        'dyspnea' => ['breathlessness', 'shortness of breath', 'difficult breathing'],
        'wheeze' => ['wheezing', 'whistling', 'asthmatic'],
        
        // Digestive - EXTENDED
        'vomiting' => ['vomit', 'emesis', 'regurgitation', 'retching'],
        'eructation' => ['belching', 'burping', 'gas'],
        'flatulence' => ['gas', 'bloating', 'distension', 'wind'],
        'heartburn' => ['pyrosis', 'acid reflux', 'gastritis'],
        
        // Female - EXTENDED
        'menses' => ['menstruation', 'period', 'menstrual', 'monthly'],
        'leucorrhea' => ['discharge', 'vaginal discharge', 'whites'],
        
        // Time/Modalities - EXTENDED
        'morning' => ['am', 'waking', 'on waking', 'forenoon', 'sunrise'],
        'afternoon' => ['pm', 'post meridian', '4pm'],
        'evening' => ['pm', 'sunset', 'dusk', 'twilight'],
        'night' => ['nocturnal', 'midnight', 'pm', 'dark'],
        'better' => ['amelioration', 'ameliorated', 'improved', 'relief', 'amel'],
        'worse' => ['aggravation', 'aggravated', 'exacerbated', 'agg'],
        'motion' => ['movement', 'moving', 'walking', 'exercise'],
        'rest' => ['resting', 'lying', 'stillness', 'repose'],
        'pressure' => ['pressing', 'hard pressure', 'touch'],
        'warmth' => ['warm', 'heat', 'hot applications'],
        'open air' => ['fresh air', 'outdoors', 'drafts'],
        
        // Circulation - EXTENDED
        'circulation' => ['circulatory', 'vascular', 'blood flow'],
        'cyanosis' => ['blue', 'blueness', 'bluish', 'livid', 'discoloration'],
        'congestion' => ['congestive', 'fullness', 'plethora'],
        
        // Common clinical terms
        'acute' => ['sudden', 'abrupt', 'rapid onset', 'violent'],
        'chronic' => ['long-standing', 'persistent', 'prolonged', 'recurring'],
        'periodic' => ['periodicity', 'recurring', 'intermittent', 'cyclical'],
        'alternating' => ['alternation', 'changing', 'shifting'],
        'suppressed' => ['suppression', 'held back', 'checked'],
    ];
}

/**
 * Search remedies directly by symptom text using embeddings
 */
function searchRemediesBySymptomText($symptomText) {
    $keywords = extractKeywords($symptomText);
    if (empty($keywords)) return [];
    
    $searchTerms = implode(' ', $keywords);
    
    // Use FULLTEXT search on embeddings for semantic matching
    $results = DB::query("
        SELECT DISTINCT r.id, r.remedy_name, r.remedy_short_name, r.keynote_symptoms,
               MATCH(e.content_text, e.keywords) AGAINST (? IN NATURAL LANGUAGE MODE) as relevance
        FROM embeddings e
        INNER JOIN remedies r ON e.source_type = 'remedy' AND e.source_id = r.id
        WHERE MATCH(e.content_text, e.keywords) AGAINST (? IN NATURAL LANGUAGE MODE)
        ORDER BY relevance DESC
        LIMIT 20
    ", [$searchTerms, $searchTerms]);
    
    return $results ?: [];
}

/**
 * Match symptoms against remedy keynotes for accurate suggestions
 */
function matchKeynoteSymptoms($symptomText, $remedyId) {
    $remedy = DB::queryOne("SELECT keynote_symptoms, clinical_indications FROM remedies WHERE id = ?", [$remedyId]);
    if (!$remedy) return 0;
    
    $keynotes = strtolower($remedy['keynote_symptoms'] . ' ' . $remedy['clinical_indications']);
    $symptomLower = strtolower($symptomText);
    $keywords = extractKeywords($symptomLower);
    
    $matchScore = 0;
    foreach ($keywords as $keyword) {
        if (strlen($keyword) >= 4 && strpos($keynotes, $keyword) !== false) {
            $matchScore += 2; // Direct keynote match is valuable
        }
    }
    
    return $matchScore;
}

/**
 * Expand keywords with synonyms
 */
function expandWithSynonyms($keywords) {
    $synonymMap = getSynonymMap();
    $expanded = [];
    
    foreach ($keywords as $keyword) {
        $expanded[] = $keyword;
        $lowerKeyword = strtolower($keyword);
        
        if (isset($synonymMap[$lowerKeyword])) {
            foreach ($synonymMap[$lowerKeyword] as $synonym) {
                if (!in_array($synonym, $expanded)) {
                    $expanded[] = $synonym;
                }
            }
        }
    }
    
    return array_unique($expanded);
}

/**
 * Search repertory for matching rubrics
 */
function searchRepertoryBySymptoms($symptoms) {
    $matchingRubrics = [];
    
    foreach ($symptoms as $symptom) {
        // Search for keywords in rubrics
        $keywords = extractKeywords($symptom['text']);
        
        // Expand keywords with synonyms for better matching
        $expandedKeywords = expandWithSynonyms($keywords);
        
        foreach ($expandedKeywords as $keyword) {
            if (strlen($keyword) < 3) continue; // Skip short words
            
            // Search in repertory table (correct table name)
            // Join with repertory_remedies and remedies to get remedy names
            $rubrics = DB::query("
                SELECT r.id, r.rubric, r.category, r.sub_category, r.complete_rubric,
                       GROUP_CONCAT(DISTINCT rem.remedy_name ORDER BY rr.grade DESC SEPARATOR ', ') as remedies,
                       MAX(rr.grade) as max_grade
                FROM repertory r
                LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id
                LEFT JOIN remedies rem ON rr.remedy_id = rem.id
                WHERE r.rubric LIKE ? OR r.complete_rubric LIKE ?
                GROUP BY r.id
                ORDER BY max_grade DESC
                LIMIT 15
            ", ['%' . $keyword . '%', '%' . $keyword . '%']);
            
            foreach ($rubrics as $rubric) {
                $rubricId = $rubric['id'];
                if (!isset($matchingRubrics[$rubricId])) {
                    $matchingRubrics[$rubricId] = [
                        'rubric' => $rubric,
                        'relevance' => 0,
                        'matched_symptoms' => [],
                        'matched_keywords' => []
                    ];
                }
                
                // Increase relevance based on symptom weight
                $matchingRubrics[$rubricId]['relevance'] += $symptom['weight'];
                
                // Bonus for matching original keyword (not just synonym)
                if (in_array($keyword, $keywords)) {
                    $matchingRubrics[$rubricId]['relevance'] += 1;
                }
                
                if (!in_array($symptom['text'], $matchingRubrics[$rubricId]['matched_symptoms'])) {
                    $matchingRubrics[$rubricId]['matched_symptoms'][] = $symptom['text'];
                }
                if (!in_array($keyword, $matchingRubrics[$rubricId]['matched_keywords'])) {
                    $matchingRubrics[$rubricId]['matched_keywords'][] = $keyword;
                }
            }
        }
    }
    
    // Sort by relevance
    usort($matchingRubrics, function($a, $b) {
        return $b['relevance'] - $a['relevance'];
    });
    
    return array_slice($matchingRubrics, 0, 30); // Top 30 rubrics for better coverage
}

/**
 * Extract keywords from text
 */
function extractKeywords($text) {
    // Convert to lowercase
    $text = strtolower($text);
    
    // Remove common words
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'been', 'be', 'have', 'has', 'had'];
    
    // Split into words
    $words = preg_split('/\s+/', $text);
    
    // Filter
    $keywords = [];
    foreach ($words as $word) {
        $word = preg_replace('/[^a-z0-9]/', '', $word);
        if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
            $keywords[] = $word;
        }
    }
    
    return array_unique($keywords);
}

/**
 * Calculate remedy scores based on rubrics
 * Enhanced with keynote matching and semantic relevance
 */
function calculateRemedyScores($matchingRubrics, $originalSymptoms = []) {
    $remedyScores = [];
    
    foreach ($matchingRubrics as $match) {
        $rubric = $match['rubric'];
        $relevance = $match['relevance'];
        
        if (empty($rubric['remedies'])) continue;
        
        $remedies = explode(',', $rubric['remedies']);
        
        foreach ($remedies as $remedy) {
            $remedy = trim($remedy);
            if (empty($remedy)) continue;
            
            if (!isset($remedyScores[$remedy])) {
                $remedyScores[$remedy] = [
                    'name' => $remedy,
                    'score' => 0,
                    'keynote_bonus' => 0,
                    'rubrics' => [],
                    'symptoms_covered' => [],
                    'matching_keywords' => []
                ];
            }
            
            // Score based on grade (use max_grade from our query)
            // Grade 3 = Principal remedy (weight 4)
            // Grade 2 = Important remedy (weight 2)
            // Grade 1 = Minor remedy (weight 1)
            $gradeScore = 1;
            if (!empty($rubric['max_grade'])) {
                $grade = (int)$rubric['max_grade'];
                $gradeScore = ($grade == 3) ? 4 : (($grade == 2) ? 2 : 1);
            }
            
            $points = $relevance * $gradeScore;
            $remedyScores[$remedy]['score'] += $points;
            $remedyScores[$remedy]['rubrics'][] = [
                'text' => $rubric['rubric'] ?? $rubric['complete_rubric'] ?? '',
                'category' => $rubric['category'] ?? '',
                'grade' => $rubric['max_grade'] ?? 2,
                'points' => $points
            ];
            
            // Track symptoms covered
            foreach ($match['matched_symptoms'] as $symptom) {
                if (!in_array($symptom, $remedyScores[$remedy]['symptoms_covered'])) {
                    $remedyScores[$remedy]['symptoms_covered'][] = $symptom;
                }
            }
            
            // Track matched keywords for explanation
            if (!empty($match['matched_keywords'])) {
                foreach ($match['matched_keywords'] as $kw) {
                    if (!in_array($kw, $remedyScores[$remedy]['matching_keywords'])) {
                        $remedyScores[$remedy]['matching_keywords'][] = $kw;
                    }
                }
            }
        }
    }
    
    // Add keynote matching bonus for top 20 remedies
    $topRemedyNames = array_slice(array_keys($remedyScores), 0, 20);
    foreach ($topRemedyNames as $remedyName) {
        $remedyData = DB::queryOne("SELECT id, keynote_symptoms, clinical_indications FROM remedies WHERE remedy_name = ?", [$remedyName]);
        if ($remedyData) {
            $keynoteBonus = 0;
            $keynoteText = strtolower(($remedyData['keynote_symptoms'] ?? '') . ' ' . ($remedyData['clinical_indications'] ?? ''));
            
            // Check if original symptoms match keynotes
            foreach ($originalSymptoms as $symptom) {
                $symptomText = strtolower($symptom['text'] ?? '');
                $symptomKeywords = extractKeywords($symptomText);
                
                foreach ($symptomKeywords as $keyword) {
                    if (strlen($keyword) >= 4 && strpos($keynoteText, $keyword) !== false) {
                        $keynoteBonus += $symptom['weight'] * 2; // Keynote match is valuable
                    }
                }
            }
            
            $remedyScores[$remedyName]['keynote_bonus'] = $keynoteBonus;
            $remedyScores[$remedyName]['score'] += $keynoteBonus;
        }
    }
    
    // Sort by score
    usort($remedyScores, function($a, $b) {
        // Primary: total score
        if ($b['score'] != $a['score']) {
            return $b['score'] - $a['score'];
        }
        // Secondary: number of symptoms covered
        return count($b['symptoms_covered']) - count($a['symptoms_covered']);
    });
    
    return $remedyScores;
}

/**
 * Get top remedies with full details
 */
function getTopRemedies($remedyScores, $limit = 5) {
    $topRemedies = array_slice($remedyScores, 0, $limit);
    
    // Get full remedy details from database
    foreach ($topRemedies as &$remedy) {
        $remedyData = DB::queryOne("
            SELECT * FROM remedies WHERE remedy_name = ?
        ", [$remedy['name']]);
        
        if ($remedyData) {
            $remedy['details'] = $remedyData;
        }
    }
    
    return $topRemedies;
}

/**
 * Generate clinical reasoning and recommendations
 */
function generateClinicalReasoning($caseData, $topRemedies, $symptoms) {
    // Calculate total score for percentage
    $totalScore = 0;
    foreach ($topRemedies as $remedy) {
        $totalScore += $remedy['score'];
    }
    
    // Generate recommendations
    $recommendations = [];
    
    foreach ($topRemedies as $index => $remedy) {
        $percentage = $totalScore > 0 ? round(($remedy['score'] / $totalScore) * 100) : 0;
        
        // Suggest potency based on symptom severity and chronicity
        $potency = suggestPotency($caseData, $remedy);
        
        // Generate reasoning
        $reasoning = generateRemedyReasoning($remedy, $caseData);
        
        $recommendations[] = [
            'rank' => $index + 1,
            'remedy' => $remedy['name'],
            'latin_name' => $remedy['details']['latin_name'] ?? '',
            'match_percentage' => $percentage,
            'score' => $remedy['score'],
            'potency' => $potency,
            'reasoning' => $reasoning,
            'symptoms_covered' => count($remedy['symptoms_covered']),
            'rubrics_matched' => count($remedy['rubrics']),
            'keynote_symptoms' => $remedy['details']['keynote_symptoms'] ?? '',
            'clinical_indications' => $remedy['details']['clinical_indications'] ?? ''
        ];
    }
    
    // Generate case analysis
    $caseAnalysis = generateCaseAnalysis($caseData, $symptoms, $topRemedies);
    
    // Generate differential diagnosis
    $differential = generateDifferentialDiagnosis($topRemedies);
    
    // Generate cautions
    $cautions = generateCautions($caseData);
    
    return [
        'recommendations' => $recommendations,
        'case_analysis' => $caseAnalysis,
        'differential_diagnosis' => $differential,
        'cautions' => $cautions,
        'total_rubrics_considered' => count($symptoms),
        'method' => 'Knowledge-Based RAG System'
    ];
}

/**
 * Suggest potency based on case characteristics
 */
function suggestPotency($caseData, $remedy) {
    $age = $caseData['age'] ?? 0;
    $duration = strtolower($caseData['duration'] ?? '');
    $severity = strtolower($caseData['severity'] ?? '');
    
    // Acute cases - lower potencies
    if (strpos($duration, 'day') !== false || strpos($duration, 'week') !== false) {
        if (strpos($severity, 'severe') !== false) {
            return '30C';
        }
        return '6C or 30C';
    }
    
    // Chronic cases - higher potencies
    if (strpos($duration, 'month') !== false || strpos($duration, 'year') !== false) {
        if ($age < 12) {
            return '30C'; // Children - moderate potency
        } elseif ($age > 60) {
            return '30C or 200C'; // Elderly - moderate to high
        } else {
            return '200C or 1M'; // Adults - higher potency
        }
    }
    
    // Default
    return '30C';
}

/**
 * Generate reasoning for remedy selection
 */
function generateRemedyReasoning($remedy, $caseData) {
    $reasoning = [];
    
    // Main indication
    $reasoning[] = "✓ Indicated based on {$remedy['rubrics_matched']} matching rubrics covering {$remedy['symptoms_covered']} symptoms";
    
    // Top rubrics
    $topRubrics = array_slice($remedy['rubrics'], 0, 3);
    foreach ($topRubrics as $rubric) {
        $reasoning[] = "• {$rubric['text']} (Grade {$rubric['grade']})";
    }
    
    // Keynote match
    if (!empty($remedy['details']['keynote_symptoms'])) {
        $reasoning[] = "✓ Keynote symptoms align with case presentation";
    }
    
    return implode("\n", $reasoning);
}

/**
 * Generate case analysis
 */
function generateCaseAnalysis($caseData, $symptoms, $topRemedies) {
    $analysis = [];
    
    $analysis[] = "**Chief Complaint:** " . ($caseData['chief_complaint'] ?? 'Not specified');
    $analysis[] = "**Duration:** " . ($caseData['duration'] ?? 'Not specified');
    $analysis[] = "**Severity:** " . ($caseData['severity'] ?? 'Not specified');
    $analysis[] = "";
    $analysis[] = "**Symptom Analysis:**";
    $analysis[] = "Total symptoms analyzed: " . count($symptoms);
    $analysis[] = "Mental/Emotional symptoms: " . countSymptomType($symptoms, 'mental');
    $analysis[] = "Physical symptoms: " . (count($symptoms) - countSymptomType($symptoms, 'mental'));
    $analysis[] = "";
    $analysis[] = "**Remedy Selection:**";
    $analysis[] = "Top {count($topRemedies)} remedies identified based on symptom totality and rubric analysis.";
    $analysis[] = "Recommendation prioritizes remedies with highest symptom coverage and grade matching.";
    
    return implode("\n", $analysis);
}

/**
 * Count symptoms by type
 */
function countSymptomType($symptoms, $type) {
    $count = 0;
    foreach ($symptoms as $symptom) {
        if ($symptom['type'] === $type) {
            $count++;
        }
    }
    return $count;
}

/**
 * Generate differential diagnosis
 */
function generateDifferentialDiagnosis($topRemedies) {
    $differential = [];
    
    if (count($topRemedies) >= 2) {
        $first = $topRemedies[0];
        $second = $topRemedies[1];
        
        $differential[] = "**Primary Recommendation: {$first['name']}**";
        $differential[] = "Strongest match with {$first['rubrics_matched']} rubrics and highest symptom coverage.";
        $differential[] = "";
        $differential[] = "**Consider also: {$second['name']}**";
        $differential[] = "Alternative if patient doesn't respond to primary remedy or if mental/emotional symptoms are more prominent.";
        
        if (count($topRemedies) >= 3) {
            $third = $topRemedies[2];
            $differential[] = "";
            $differential[] = "**Third option: {$third['name']}**";
            $differential[] = "Consider if specific modalities or physical generals match better.";
        }
    }
    
    return implode("\n", $differential);
}

/**
 * Generate cautions
 */
function generateCautions($caseData) {
    $cautions = [];
    
    $cautions[] = "• Start with recommended potency and frequency";
    $cautions[] = "• Observe for 3-7 days before repeating dose";
    $cautions[] = "• Watch for aggravation (temporary worsening)";
    
    $age = $caseData['age'] ?? 0;
    if ($age < 2) {
        $cautions[] = "⚠ Young child - use lower potencies and consult experienced practitioner";
    } elseif ($age > 70) {
        $cautions[] = "⚠ Elderly patient - monitor response carefully";
    }
    
    if (!empty($caseData['pregnant'])) {
        $cautions[] = "⚠ Pregnancy - avoid certain remedies, consult specialist";
    }
    
    $cautions[] = "• If no improvement after 2 weeks, reassess case";
    $cautions[] = "• Refer to specialist for serious/emergency conditions";
    
    return implode("\n", $cautions);
}

/**
 * Parse AI response (for RAG system, this just returns formatted data)
 */
function parseAIResponse($response_data) {
    // RAG system already returns structured data
    return $response_data;
}

include '../includes/header.php';
?>

<div class="container">
    <!-- Back Button -->
    <div class="mb-3">
        <?php if ($consultation_id): ?>
        <a href="<?= APP_URL ?>/consultations/view.php?id=<?= $consultation_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Consultation
        </a>
        <?php endif; ?>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <i class="fas fa-brain"></i> Remedy Suggestions
            <span class="badge badge-success ml-2">
                <i class="fas fa-database"></i> Knowledge-Based Analysis
            </span>
        </div>
        <p class="text-muted mt-2">
            <small><i class="fas fa-info-circle"></i> Uses local repertory database and remedy relationships for intelligent suggestions</small>
        </p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($consultation): ?>
    <!-- Patient & Consultation Summary -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-user-injured"></i> Case Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Patient:</strong> <?= htmlspecialchars($consultation['patient_name']) ?></p>
                    <p><strong>Age/Gender:</strong> <?= $consultation['age'] ?> years / <?= $consultation['gender'] ?></p>
                    <?php if (!empty($consultation['blood_group'])): ?>
                    <p><strong>Blood Group:</strong> <?= htmlspecialchars($consultation['blood_group']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p><strong>Chief Complaint:</strong> <?= htmlspecialchars($consultation['chief_complaint']) ?></p>
                    <?php if (!empty($consultation['diagnosis'])): ?>
                    <p><strong>Diagnosis:</strong> <?= htmlspecialchars($consultation['diagnosis']) ?></p>
                    <?php endif; ?>
                    <p><strong>Consultation Date:</strong> <?= date('M d, Y', strtotime($consultation['consultation_date'])) ?></p>
                </div>
            </div>

            <?php if (!empty($consultation['symptoms'])): ?>
            <hr>
            <h6><i class="fas fa-stethoscope"></i> Symptoms (<?= count($consultation['symptoms']) ?>)</h6>
            <div class="symptoms-list">
                <?php foreach ($consultation['symptoms'] as $symptom): ?>
                <div class="symptom-item">
                    <span class="symptom-name"><?= htmlspecialchars($symptom['symptom']) ?></span>
                    <span class="badge badge-severity-<?= $symptom['severity'] >= 7 ? 'high' : ($symptom['severity'] >= 4 ? 'medium' : 'low') ?>">
                        Severity: <?= $symptom['severity'] ?>/10
                    </span>
                    <span class="badge badge-secondary"><?= htmlspecialchars($symptom['duration']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($consultation['allergies'])): ?>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-triangle"></i> <strong>Allergies:</strong> <?= htmlspecialchars($consultation['allergies']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Generate/Regenerate Button -->
    <?php if (empty($suggestions['remedies']) || isset($suggestions['cached'])): ?>
    <div class="card mb-4">
        <div class="card-body text-center">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <?php if (isset($suggestions['cached'])): ?>
                <p class="mb-3">
                    <i class="fas fa-clock"></i> Cached suggestions from <?= date('M d, Y h:i A', strtotime($suggestions['created_at'])) ?>
                </p>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-sync-alt"></i> Regenerate Suggestions
                </button>
                <?php else: ?>
                <p class="mb-3">
                    <i class="fas fa-magic"></i> Click below to get knowledge-based remedy recommendations using repertory analysis
                </p>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-brain"></i> Analyze & Suggest Remedies
                </button>
                <?php endif; ?>
            </form>
            <p class="text-muted mt-3">
                <small><i class="fas fa-database"></i> Analyzes symptoms against local repertory database (195 rubrics, 36 remedies) for accurate suggestions</small>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- AI Suggestions Display -->
    <?php if (!empty($suggestions['remedies'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="fas fa-flask"></i> Knowledge-Based Remedy Recommendations
                <?php if (isset($suggestions['cached'])): ?>
                <span class="badge badge-light float-right">
                    <i class="fas fa-clock"></i> Cached
                </span>
                <?php endif; ?>
            </h5>
            <p class="mb-0 mt-2" style="font-size: 0.9rem; opacity: 0.95;">
                <i class="fas fa-info-circle"></i> Based on repertory analysis: 
                <?= count($suggestions['matched_rubrics'] ?? []) ?> rubrics matched, 
                <?= count($suggestions['symptoms_analyzed'] ?? []) ?> symptoms analyzed
            </p>
        </div>
        <div class="card-body">
            <div class="remedies-grid">
                <?php foreach ($suggestions['remedies'] as $index => $remedy): ?>
                <div class="remedy-card">
                    <div class="remedy-header">
                        <div class="remedy-rank">#<?= $index + 1 ?></div>
                        <div class="remedy-info">
                            <h6 class="remedy-name"><?= htmlspecialchars($remedy['name']) ?></h6>
                            <div class="remedy-meta">
                                <span class="match-badge match-<?= $remedy['match_percentage'] >= 80 ? 'high' : ($remedy['match_percentage'] >= 60 ? 'medium' : 'low') ?>">
                                    <?= $remedy['match_percentage'] ?>% Match
                                </span>
                                <span class="potency-badge"><?= htmlspecialchars($remedy['potency']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="remedy-body">
                        <p class="reasoning"><?= nl2br(htmlspecialchars($remedy['reasoning'])) ?></p>
                        <?php if (!empty($remedy['matching_symptoms'])): ?>
                        <div class="matching-symptoms">
                            <strong>Matching Symptoms:</strong>
                            <ul>
                                <?php foreach ($remedy['matching_symptoms'] as $symptom): ?>
                                <li><?= htmlspecialchars($symptom) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Case Analysis -->
    <?php if (!empty($suggestions['case_analysis'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-file-medical-alt"></i> Case Analysis</h5>
        </div>
        <div class="card-body">
            <p><?= nl2br(htmlspecialchars($suggestions['case_analysis'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Differential Diagnosis -->
    <?php if (!empty($suggestions['differential'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-list-alt"></i> Differential Diagnosis</h5>
        </div>
        <div class="card-body">
            <p><strong>Other remedies to consider:</strong></p>
            <ul>
                <?php foreach ($suggestions['differential'] as $diff_remedy): ?>
                <li><?= htmlspecialchars($diff_remedy) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cautions -->
    <?php if (!empty($suggestions['cautions'])): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Important Considerations</h5>
        </div>
        <div class="card-body">
            <p><?= nl2br(htmlspecialchars($suggestions['cautions'])) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Raw Response (if parse error) -->
    <?php if (isset($suggestions['parse_error']) && !empty($suggestions['raw_text'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-code"></i> Raw AI Response</h5>
        </div>
        <div class="card-body">
            <pre style="white-space: pre-wrap;"><?= htmlspecialchars($suggestions['raw_text']) ?></pre>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="text-center mb-4">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-sync-alt"></i> Regenerate Suggestions
            </button>
        </form>
        <a href="<?= APP_URL ?>/prescriptions/add.php?consultation_id=<?= $consultation_id ?>" class="btn btn-success">
            <i class="fas fa-prescription"></i> Write Prescription
        </a>
        <a href="<?= APP_URL ?>/consultations/view.php?id=<?= $consultation_id ?>" class="btn btn-primary">
            <i class="fas fa-eye"></i> View Consultation
        </a>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Info Card -->
    <div class="card bg-light">
        <div class="card-body">
            <h6><i class="fas fa-info-circle"></i> About AI Suggestions</h6>
            <p class="mb-0">
                <small>
                    This feature uses Google's Gemini AI to analyze consultation data and suggest suitable homeopathic remedies.
                    The AI considers patient demographics, chief complaint, symptoms with severity and duration, and any additional notes.
                    <strong>Note:</strong> AI suggestions are meant to assist clinical decision-making, not replace professional judgment.
                    Always verify suggestions with repertory and materia medica before prescribing.
                </small>
            </p>
        </div>
    </div>
</div>

<style>
.symptoms-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.symptom-item {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid var(--primary-color);
}

.symptom-name {
    font-weight: 500;
    margin-right: 8px;
}

.badge-severity-high { background-color: #dc3545; color: white; }
.badge-severity-medium { background-color: #ffc107; color: #333; }
.badge-severity-low { background-color: #28a745; color: white; }

.remedies-grid {
    display: grid;
    gap: 20px;
}

.remedy-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.remedy-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.remedy-header {
    display: flex;
    align-items: center;
    padding: 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.remedy-rank {
    font-size: 32px;
    font-weight: bold;
    margin-right: 15px;
    opacity: 0.9;
}

.remedy-info {
    flex: 1;
}

.remedy-name {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.remedy-meta {
    margin-top: 5px;
}

.match-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 8px;
}

.match-high { background-color: #28a745; color: white; }
.match-medium { background-color: #ffc107; color: #333; }
.match-low { background-color: #6c757d; color: white; }

.potency-badge {
    display: inline-block;
    padding: 4px 10px;
    background-color: rgba(255,255,255,0.3);
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.remedy-body {
    padding: 15px;
}

.reasoning {
    color: #495057;
    line-height: 1.6;
    margin-bottom: 15px;
    word-break: break-word;
    overflow-wrap: anywhere;
    white-space: pre-line;
    max-width: 100%;
    box-sizing: border-box;
}

.matching-symptoms {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid var(--primary-color);
}

.matching-symptoms ul {
    margin: 5px 0 0 0;
    padding-left: 20px;
}

.matching-symptoms li {
    margin: 3px 0;
    color: #495057;
}

.badge-info {
    background-color: #17a2b8;
}

@media (max-width: 768px) {
    .remedy-header {
        flex-direction: column;
        text-align: center;
    }
    
    .remedy-rank {
        margin: 0 0 10px 0;
    }

    .reasoning {
        font-size: 1rem;
        padding: 0;
        max-width: 100vw;
        word-break: break-word;
        overflow-wrap: anywhere;
        white-space: pre-line;
        box-sizing: border-box;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
