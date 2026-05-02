<?php
/**
 * API Endpoint: Get Disease Diagnosis Suggestions (RAG-based)
 * 
 * Uses ONLY local database for diagnosis - no external AI
 * Returns possible diagnoses based on symptoms, clinical findings, and test results
 * 
 * IMPORTANT: This is a diagnostic SUGGESTION tool, NOT a final medical decision
 */

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json');

// Global exception handler
set_exception_handler(function($e) {
    error_log("Diagnosis API Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/database.php';
    require_once __DIR__ . '/../includes/functions.php';

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

    $doctor_id = $_SESSION['doctor_id'];

    // Get input data
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    // Debug logging
    error_log("Diagnosis API - Raw input: " . $rawInput);
    error_log("Diagnosis API - Parsed input: " . print_r($input, true));
    
    // Also accept form data
    if (empty($input)) {
        $input = [
            'symptoms' => $_POST['symptoms'] ?? '',
            'issues' => $_POST['issues'] ?? '',
            'tests' => $_POST['tests'] ?? '',
            'duration' => $_POST['duration'] ?? '',
            'severity' => $_POST['severity'] ?? 'moderate',
            'age' => $_POST['age'] ?? null,
            'gender' => $_POST['gender'] ?? null
        ];
    }

    // Extract symptoms from input - accept multiple field names
    $symptomsText = $input['symptoms'] ?? '';
    $chiefComplaint = $input['chief_complaint'] ?? $input['issues'] ?? '';
    $physicalExam = $input['physical_exam'] ?? $input['physical_examination'] ?? '';
    $testsText = $input['lab_tests'] ?? $input['tests'] ?? '';
    $duration = $input['duration'] ?? '';
    $severity = $input['severity'] ?? 'moderate';
    $patientAge = $input['age'] ?? null;
    $patientGender = $input['gender'] ?? null;

    // Pre-process and normalize inputs
    $symptomsText = normalizeSymptomText($symptomsText);
    $chiefComplaint = normalizeSymptomText($chiefComplaint);
    $physicalExam = normalizeSymptomText($physicalExam);

    // Combine all text for analysis with weighting (chief complaint is important)
    $allText = strtolower(trim($chiefComplaint . ' ' . $chiefComplaint . ' ' . $symptomsText . ' ' . $physicalExam));
    
    error_log("Diagnosis API - All text combined: " . $allText);

    if (empty($allText)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No symptoms provided. Please enter symptoms to analyze.'
        ]);
        exit;
    }

    // Perform RAG-based diagnosis
    $diagnosisResult = performRAGDiagnosis(
        $allText, 
        $testsText, 
        $duration, 
        $severity,
        $patientAge,
        $patientGender
    );

    // Log the diagnosis
    try {
        DB::query(
            "INSERT INTO diagnosis_logs 
             (doctor_id, input_symptoms, input_clinical_findings, input_lab_tests, 
              suggested_diagnoses, confidence_scores, ai_analysis, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $doctor_id,
                $symptomsText,
                $chiefComplaint . ' ' . $physicalExam,
                $testsText,
                json_encode($diagnosisResult['diagnoses'] ?? []),
                json_encode(array_column($diagnosisResult['diagnoses'] ?? [], 'confidence')),
                $diagnosisResult['analysis'] ?? ''
            ]
        );
    } catch (Exception $e) {
        // Log error but don't fail
        error_log('Failed to log diagnosis: ' . $e->getMessage());
    }

    // Format output to match expected frontend format
    $formattedDiagnoses = [];
    foreach ($diagnosisResult['diagnoses'] ?? [] as $diag) {
        // Ensure matching_symptoms is a proper indexed array (not associative)
        $matchingSymptoms = isset($diag['matching_symptoms']) ? array_values(array_unique($diag['matching_symptoms'])) : [];
        
        $formattedDiagnoses[] = [
            'diagnosis' => $diag['diagnosis'],
            'confidence' => $diag['confidence_level'] ?? 'Low',
            'matching_symptoms' => $matchingSymptoms,
            'supporting_findings' => $diag['supporting_findings'] ?? '',
            'notes_for_doctor' => $diag['notes_for_doctor'] ?? '',
            'homeopathic_remedies' => $diag['homeopathic_remedies'] ?? []
        ];
    }

    error_log("Diagnosis API - Final formatted diagnoses count: " . count($formattedDiagnoses));
    if (!empty($formattedDiagnoses)) {
        error_log("Diagnosis API - First result: " . json_encode($formattedDiagnoses[0]));
    }

    $response = [
        'success' => true,
        'diagnoses' => $formattedDiagnoses,
        'analysis' => $diagnosisResult['analysis'] ?? '',
        'matched_keywords' => $diagnosisResult['matched_keywords'] ?? [],
        'disclaimer' => $diagnosisResult['disclaimer'] ?? ''
    ];
    
    error_log("Diagnosis API - Full response: " . substr(json_encode($response), 0, 500));
    
    echo json_encode($response);

} catch (Throwable $e) {
    error_log('Diagnosis API Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}

/**
 * Extract duration information from symptom text
 * Returns duration in days (approximate) and duration type (acute/subacute/chronic)
 */
function extractDuration($text) {
    $text = strtolower($text);
    
    $durationDays = 0;
    $durationType = 'unknown';
    $rawDuration = '';
    
    // Pattern for extracting duration mentions
    $patterns = [
        // Months
        '/(\d+)\s*month[s]?/' => function($m) { return intval($m[1]) * 30; },
        '/(?:for\s+)?(?:a|one)\s+month/' => function($m) { return 30; },
        '/several\s+months?/' => function($m) { return 90; },
        '/few\s+months?/' => function($m) { return 60; },
        
        // Weeks
        '/(\d+)\s*week[s]?/' => function($m) { return intval($m[1]) * 7; },
        '/(?:for\s+)?(?:a|one)\s+week/' => function($m) { return 7; },
        '/several\s+weeks?/' => function($m) { return 21; },
        '/few\s+weeks?/' => function($m) { return 14; },
        
        // Days
        '/(\d+)\s*day[s]?/' => function($m) { return intval($m[1]); },
        '/(?:for\s+)?(?:a|one)\s+day/' => function($m) { return 1; },
        '/few\s+days?/' => function($m) { return 3; },
        '/several\s+days?/' => function($m) { return 5; },
        
        // Hours (acute)
        '/(\d+)\s*hour[s]?/' => function($m) { return 0.04 * intval($m[1]); },
        '/few\s+hours?/' => function($m) { return 0.12; },
        
        // Qualitative duration terms
        '/(?:chronic|long[\s-]*standing|prolonged|persistent)/' => function($m) { return 90; },
        '/(?:recent|lately|recently)/' => function($m) { return 14; },
        '/(?:sudden|suddenly|abrupt|acute|today|just\s+started|just\s+now)/' => function($m) { return 0.5; },
        '/this\s+morning|since\s+morning/' => function($m) { return 0.5; },
        '/(?:yesterday|since\s+yesterday)/' => function($m) { return 1; },
        '/(?:last\s+night|tonight)/' => function($m) { return 0.5; },
    ];
    
    foreach ($patterns as $pattern => $calculator) {
        if (preg_match($pattern, $text, $matches)) {
            $days = $calculator($matches);
            if ($days > $durationDays) {
                $durationDays = $days;
                $rawDuration = $matches[0];
            }
        }
    }
    
    // Classify duration type based on days
    if ($durationDays <= 0) {
        $durationType = 'unknown';
    } elseif ($durationDays < 1) {
        $durationType = 'hyperacute'; // Hours - emergency presentations
    } elseif ($durationDays <= 7) {
        $durationType = 'acute'; // Days to 1 week
    } elseif ($durationDays <= 28) {
        $durationType = 'subacute'; // 1-4 weeks
    } else {
        $durationType = 'chronic'; // > 4 weeks
    }
    
    error_log("Duration extraction: '$rawDuration' => $durationDays days, type: $durationType");
    
    return [
        'days' => $durationDays,
        'type' => $durationType,
        'raw' => $rawDuration
    ];
}

/**
 * Check if symptoms suggest a chronic presentation pattern
 */
function hasChronicPresentationMarkers($text) {
    $text = strtolower($text);
    
    $chronicMarkers = [
        'for months', 'for weeks', 'chronic', 'long-standing', 'longstanding',
        'persistent', 'recurrent', 'ongoing', 'gradually', 'over time',
        'especially in the morning', 'every morning', 'daily', 'regularly',
        'getting worse over', 'increasing over', 'progressively'
    ];
    
    foreach ($chronicMarkers as $marker) {
        if (strpos($text, $marker) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * CRITICAL: Extract NEGATED symptoms from text
 * These are symptoms explicitly denied by the patient
 * "no vomiting", "without fever", "denies chest pain"
 */
function extractNegatedSymptoms($text) {
    $text = strtolower($text);
    $negated = [];
    
    // Common symptoms that may be negated
    $symptoms = [
        'fever', 'vomiting', 'diarrhea', 'cough', 'headache', 'chest pain',
        'shortness of breath', 'breathing difficulty', 'rash', 'bleeding',
        'weight loss', 'blood', 'pus', 'discharge', 'swelling', 'pain',
        'nausea', 'fatigue', 'weakness', 'dizziness', 'numbness', 'tingling',
        'paralysis', 'seizure', 'loss of consciousness', 'fainting',
        'appetite loss', 'sleep disturbance', 'sweating', 'chills', 'rigors',
        'body ache', 'joint pain', 'muscle pain', 'sore throat', 'runny nose'
    ];
    
    // Negation patterns
    $negationPatterns = [
        '/\bno\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i',           // "no vomiting"
        '/\bwithout\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i',      // "without fever"
        '/\bdenies?\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i',      // "denies chest pain"
        '/\bnot\s+(?:experiencing|having|had|any)\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i', // "not having any fever"
        '/\babsent\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i',       // "absent fever"
        '/\bnegative\s+for\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i', // "negative for fever"
        '/\bno\s+history\s+of\s+([a-z\s]+?)(?:\.|,|;|\band\b|$)/i', // "no history of bleeding"
    ];
    
    foreach ($negationPatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $match) {
                $match = trim($match);
                // Check if the matched phrase contains a known symptom
                foreach ($symptoms as $symptom) {
                    if (strpos($match, $symptom) !== false) {
                        $negated[] = $symptom;
                    }
                }
            }
        }
    }
    
    // Also check for specific patterns like "but no X"
    if (preg_match_all('/\bbut\s+no\s+([a-z\s]+?)(?:\.|,|;|$)/i', $text, $matches)) {
        foreach ($matches[1] as $match) {
            $match = trim($match);
            foreach ($symptoms as $symptom) {
                if (strpos($match, $symptom) !== false) {
                    $negated[] = $symptom;
                }
            }
        }
    }
    
    return array_unique($negated);
}

/**
 * CRITICAL: Extract modalities (aggravating/ameliorating factors)
 * These are symptom modifiers, NOT disease indicators!
 * "worse with alcohol" ≠ "alcoholic disease"
 * "worse with spicy food" ≠ "spicy food disease"
 */
function extractModalities($text) {
    $text = strtolower($text);
    
    $modalities = [
        'aggravating' => [],  // Makes symptoms worse
        'ameliorating' => [], // Makes symptoms better
        'timing' => [],       // Time-related patterns
        'location' => []      // Where symptoms are felt
    ];
    
    // Aggravating factor patterns - these are MODALITIES, not disease causes
    $aggravatingPatterns = [
        '/(?:worse|worsens?|aggravated?|exacerbated?|increases?)\s+(?:with|by|after|from|on)\s+([a-z\s]+?)(?:\.|,|and|$)/i',
        '/(?:after|with|from)\s+([a-z\s]+?)\s+(?:it\s+)?(?:gets?\s+)?(?:worse|worsens?|aggravates?)/i',
        '/([a-z\s]+?)\s+(?:makes?\s+(?:it\s+)?worse|aggravates?|worsens?)/i'
    ];
    
    foreach ($aggravatingPatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $factor) {
                $factor = trim($factor);
                if (strlen($factor) > 2 && strlen($factor) < 50) {
                    $modalities['aggravating'][] = $factor;
                }
            }
        }
    }
    
    // Common aggravating factors to detect
    $commonAggravators = [
        'alcohol' => '/(?:worse|worsens?|aggravat|discomfort)\s+(?:with|by|after|from)?\s*alcohol/i',
        'spicy food' => '/(?:worse|worsens?|aggravat)\s+(?:with|by|after|from)?\s*(?:spicy|hot|chili)/i',
        'nsaids' => '/(?:worse|worsens?|aggravat|discomfort)\s+(?:with|by|after|from)?\s*nsaids?/i',
        'empty stomach' => '/(?:worse|worsens?|aggravat|pain)\s+(?:on|with)?\s*(?:an?\s+)?empty\s+stomach/i',
        'eating' => '/(?:worse|worsens?|aggravat|pain)\s+(?:after|with|following)\s*(?:eating|meals?|food)/i',
        'fatty food' => '/(?:worse|worsens?|aggravat)\s+(?:with|by|after|from)?\s*(?:fatty|oily|greasy|fried)/i',
        'stress' => '/(?:worse|worsens?|aggravat)\s+(?:with|by|under|during)?\s*stress/i',
        'movement' => '/(?:worse|worsens?|aggravat)\s+(?:with|by|on|during)?\s*(?:movement|motion|walking|bending)/i',
        'lying down' => '/(?:worse|worsens?|aggravat)\s+(?:when|while|on)?\s*(?:lying|recumbent)/i',
    ];
    
    foreach ($commonAggravators as $factor => $pattern) {
        if (preg_match($pattern, $text)) {
            if (!in_array($factor, $modalities['aggravating'])) {
                $modalities['aggravating'][] = $factor;
            }
        }
    }
    
    // Ameliorating factors
    $amelioratingPatterns = [
        '/(?:better|improves?|relieved?|eased?)\s+(?:with|by|after|from)\s+([a-z\s]+?)(?:\.|,|and|$)/i',
        '/([a-z\s]+?)\s+(?:helps?|relieves?|improves?|eases?)/i'
    ];
    
    foreach ($amelioratingPatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[1] as $factor) {
                $factor = trim($factor);
                if (strlen($factor) > 2 && strlen($factor) < 50) {
                    $modalities['ameliorating'][] = $factor;
                }
            }
        }
    }
    
    // Timing patterns
    $timingPatterns = [
        'morning' => '/(?:in the|every|especially)\s+morning/i',
        'night' => '/(?:at|during|worse at)\s+night/i',
        'after meals' => '/after\s+(?:eating|meals?|food)/i',
        'before meals' => '/before\s+(?:eating|meals?|food)/i',
        'empty stomach' => '/(?:on|with)\s+(?:an?\s+)?empty\s+stomach/i'
    ];
    
    foreach ($timingPatterns as $timing => $pattern) {
        if (preg_match($pattern, $text)) {
            $modalities['timing'][] = $timing;
        }
    }
    
    return $modalities;
}

/**
 * CRITICAL: Detect organ system from symptom description
 * This helps filter out completely irrelevant diseases
 */
function detectOrganSystem($text) {
    $text = strtolower($text);
    
    $organSystems = [
        'gastrointestinal' => [
            'keywords' => [
                'epigastric', 'abdomen', 'abdominal', 'stomach', 'gastric', 'belly',
                'nausea', 'vomiting', 'bloating', 'belching', 'eructation',
                'heartburn', 'acid', 'reflux', 'indigestion', 'dyspepsia',
                'diarrhea', 'constipation', 'bowel', 'stool',
                'eating', 'meals', 'food', 'appetite', 'fullness after eating',
                'upper abdomen', 'lower abdomen'
            ],
            'locations' => ['epigastric', 'upper abdomen', 'lower abdomen', 'stomach', 'belly'],
            'weight' => 0,
            'is_primary' => false
        ],
        'urinary' => [
            'keywords' => [
                'urination', 'urinary', 'urine', 'bladder', 'kidney',
                'dysuria', 'frequency', 'urgency', 'burning urination',
                'flank pain', 'suprapubic', 'hematuria', 'pyuria'
            ],
            'locations' => ['flank', 'suprapubic', 'bladder', 'kidney'],
            'weight' => 0,
            'is_primary' => false
        ],
        'hepatobiliary' => [
            'keywords' => [
                'liver', 'hepatic', 'jaundice', 'yellow', 'gallbladder',
                'biliary', 'cholecyst', 'right upper quadrant', 'ruq',
                'ascites', 'hepatomegaly'
            ],
            'locations' => ['right upper quadrant', 'ruq', 'liver area'],
            'weight' => 0,
            'is_primary' => false
        ],
        'respiratory' => [
            'keywords' => [
                'cough', 'breathing', 'breath', 'lung', 'chest',
                'wheeze', 'sputum', 'pneumonia', 'bronchitis'
            ],
            'locations' => ['chest', 'lung'],
            'weight' => 0,
            'is_primary' => false
        ],
        'cardiovascular' => [
            'keywords' => [
                'heart', 'cardiac', 'palpitation', 'chest pain',
                'blood pressure', 'hypertension'
            ],
            'locations' => ['chest', 'heart'],
            'weight' => 0,
            'is_primary' => false
        ],
        'neurological' => [
            'keywords' => [
                'headache', 'migraine', 'dizziness', 'vertigo',
                'numbness', 'weakness', 'paralysis', 'seizure'
            ],
            'locations' => ['head', 'brain'],
            'weight' => 0,
            'is_primary' => false
        ]
    ];
    
    // Count keyword matches and location matches
    foreach ($organSystems as $system => &$data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $data['weight'] += 1;
            }
        }
        foreach ($data['locations'] as $location) {
            if (strpos($text, $location) !== false) {
                $data['weight'] += 3; // Location matches are more specific
            }
        }
    }
    
    // Find primary organ system
    $maxWeight = 0;
    $primarySystem = null;
    foreach ($organSystems as $system => $data) {
        if ($data['weight'] > $maxWeight) {
            $maxWeight = $data['weight'];
            $primarySystem = $system;
        }
    }
    
    if ($primarySystem) {
        $organSystems[$primarySystem]['is_primary'] = true;
    }
    
    return [
        'primary' => $primarySystem,
        'weights' => array_map(function($d) { return $d['weight']; }, $organSystems),
        'has_urinary_symptoms' => $organSystems['urinary']['weight'] > 0,
        'has_gi_symptoms' => $organSystems['gastrointestinal']['weight'] > 0,
        'has_hepatic_symptoms' => $organSystems['hepatobiliary']['weight'] > 0
    ];
}

/**
 * Check if a disease has required symptoms that MUST be present
 * If these symptoms are absent, the disease should be heavily penalized
 */
function getRequiredSymptoms($diseaseName) {
    $diseaseName = strtolower($diseaseName);
    
    $requiredSymptoms = [
        // UTI MUST have urinary symptoms
        'urinary tract infection' => [
            'any_of' => ['urination', 'urinary', 'dysuria', 'frequency', 'urgency', 'burning urination', 'urine'],
            'penalty_if_absent' => -300
        ],
        'uti' => [
            'any_of' => ['urination', 'urinary', 'dysuria', 'frequency', 'urgency', 'burning urination', 'urine'],
            'penalty_if_absent' => -300
        ],
        'pyelonephritis' => [
            'any_of' => ['urination', 'urinary', 'flank', 'kidney', 'fever'],
            'penalty_if_absent' => -250
        ],
        'cystitis' => [
            'any_of' => ['urination', 'urinary', 'dysuria', 'bladder', 'suprapubic'],
            'penalty_if_absent' => -300
        ],
        
        // Liver diseases MUST have liver-specific symptoms or history
        'alcoholic liver' => [
            'any_of' => ['jaundice', 'liver', 'hepatomegaly', 'ascites', 'chronic alcohol', 'heavy drinking', 'alcoholism'],
            'penalty_if_absent' => -250
        ],
        'alcohol-related liver' => [
            'any_of' => ['jaundice', 'liver', 'hepatomegaly', 'ascites', 'chronic alcohol', 'heavy drinking', 'alcoholism'],
            'penalty_if_absent' => -250
        ],
        'cirrhosis' => [
            'any_of' => ['jaundice', 'liver', 'hepatomegaly', 'ascites', 'spider naevi', 'palmar erythema'],
            'penalty_if_absent' => -250
        ],
        'hepatitis' => [
            'any_of' => ['jaundice', 'liver', 'yellow', 'hepatomegaly', 'dark urine'],
            'penalty_if_absent' => -200
        ],
        
        // Respiratory diseases MUST have respiratory symptoms
        'pneumonia' => [
            'any_of' => ['cough', 'breathing', 'fever', 'sputum', 'chest'],
            'penalty_if_absent' => -200
        ],
        'bronchitis' => [
            'any_of' => ['cough', 'breathing', 'sputum', 'chest'],
            'penalty_if_absent' => -200
        ],
        'asthma' => [
            'any_of' => ['wheeze', 'breathing', 'cough', 'chest tightness'],
            'penalty_if_absent' => -200
        ],
        
        // Cardiac diseases
        'myocardial infarction' => [
            'any_of' => ['chest pain', 'chest', 'arm pain', 'jaw pain', 'shortness of breath'],
            'penalty_if_absent' => -250
        ],
        'heart attack' => [
            'any_of' => ['chest pain', 'chest', 'arm pain', 'jaw pain', 'shortness of breath'],
            'penalty_if_absent' => -250
        ],
        
        // Stroke
        'stroke' => [
            'any_of' => ['weakness', 'paralysis', 'speech', 'facial droop', 'numbness'],
            'penalty_if_absent' => -250
        ],
        
        // Gallbladder
        'cholecystitis' => [
            'any_of' => ['right upper quadrant', 'ruq', 'murphy', 'gallbladder', 'fatty food'],
            'penalty_if_absent' => -150
        ],
        'cholelithiasis' => [
            'any_of' => ['right upper quadrant', 'ruq', 'biliary', 'gallstone', 'fatty food'],
            'penalty_if_absent' => -150
        ]
    ];
    
    foreach ($requiredSymptoms as $disease => $criteria) {
        if (strpos($diseaseName, $disease) !== false) {
            return $criteria;
        }
    }
    
    return null;
}

/**
 * Check if symptoms contain any of the required symptom keywords
 */
function hasRequiredSymptoms($symptoms, $requiredAnyOf) {
    $symptomsLower = strtolower($symptoms);
    
    foreach ($requiredAnyOf as $required) {
        if (strpos($symptomsLower, $required) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Normalize symptom text for better matching
 */
function normalizeSymptomText($text) {
    $text = strtolower(trim($text));
    
    // Common abbreviations and expansions
    $expansions = [
        'rlq' => 'right lower quadrant',
        'llq' => 'left lower quadrant',
        'ruq' => 'right upper quadrant',
        'luq' => 'left upper quadrant',
        'sob' => 'shortness of breath',
        'bp' => 'blood pressure',
        'hr' => 'heart rate',
        'temp' => 'temperature',
        'n/v' => 'nausea vomiting',
        'h/o' => 'history of',
        'c/o' => 'complains of',
        'b/l' => 'bilateral',
        'wbc' => 'white blood cell count',
        'rbc' => 'red blood cell count',
        'hb' => 'hemoglobin',
        'plt' => 'platelet',
        'esr' => 'erythrocyte sedimentation rate',
        'crp' => 'c reactive protein',
        'tsh' => 'thyroid stimulating hormone',
        't3' => 'triiodothyronine',
        't4' => 'thyroxine',
        'lft' => 'liver function test',
        'kft' => 'kidney function test',
        'rft' => 'renal function test',
        'ua' => 'urine analysis',
        'ecg' => 'electrocardiogram',
        'ekg' => 'electrocardiogram',
        'ct' => 'computed tomography',
        'mri' => 'magnetic resonance imaging',
        'usg' => 'ultrasonography',
        'xray' => 'x-ray',
        'htn' => 'hypertension',
        'dm' => 'diabetes mellitus',
        'uti' => 'urinary tract infection',
        'urti' => 'upper respiratory tract infection',
        'gerd' => 'gastroesophageal reflux disease',
        'ibs' => 'irritable bowel syndrome',
        'pcos' => 'polycystic ovary syndrome',
        'oa' => 'osteoarthritis',
        'ra' => 'rheumatoid arthritis',
        'sle' => 'systemic lupus erythematosus',
        'ms' => 'multiple sclerosis',
        'pd' => 'parkinson disease',
        'copd' => 'chronic obstructive pulmonary disease',
        'cfs' => 'chronic fatigue syndrome',
        'ocd' => 'obsessive compulsive disorder',
        'adhd' => 'attention deficit hyperactivity disorder',
        'ptsd' => 'post traumatic stress disorder'
    ];
    
    // Replace abbreviations
    foreach ($expansions as $abbr => $full) {
        $text = preg_replace('/\b' . preg_quote($abbr, '/') . '\b/i', $full, $text);
    }
    
    // Standardize symptom descriptions
    $standardizations = [
        '/\b(hurts?|aching|painful)\b/' => 'pain',
        '/\b(throwing up|vomited|puking)\b/' => 'vomiting',
        '/\b(loose motion|watery stool|frequent stool)\b/' => 'diarrhea',
        '/\b(cant sleep|difficulty sleeping|sleeplessness)\b/' => 'insomnia',
        '/\b(cant breathe|breathing difficulty|hard to breathe)\b/' => 'dyspnea shortness of breath',
        '/\b(racing heart|heart racing|fast heartbeat)\b/' => 'palpitations tachycardia',
        '/\b(feeling tired|always tired|no energy)\b/' => 'fatigue exhaustion',
        '/\b(putting on weight|gaining weight)\b/' => 'weight gain',
        '/\b(losing weight|lost weight)\b/' => 'weight loss',
        '/\b(running nose|blocked nose|stuffy nose)\b/' => 'nasal congestion rhinorrhea',
        '/\b(scratchy throat|itchy throat)\b/' => 'sore throat pharyngitis',
        '/\b(feeling dizzy|spinning sensation)\b/' => 'dizziness vertigo',
        '/\b(pins and needles|tingling sensation)\b/' => 'paresthesia numbness tingling',
        '/\b(mood swings|emotional)\b/' => 'mood changes irritability',
        '/\b(period problems?|irregular periods?)\b/' => 'menstrual irregularity dysmenorrhea',
        '/\b(burning pee|burns when peeing)\b/' => 'dysuria burning urination',
        '/\b(bloody stool|blood in stool)\b/' => 'hematochezia rectal bleeding',
        '/\b(yellow eyes|yellow skin)\b/' => 'jaundice icterus',
        '/\b(swollen joints?|puffy joints?)\b/' => 'joint swelling arthritis',
        '/\b(skin rash|breaking out)\b/' => 'rash dermatitis eruption'
    ];
    
    foreach ($standardizations as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }
    
    return $text;
}

/**
 * Detect the primary symptom category from input
 * Returns the most likely disease category based on symptom patterns
 */
function detectSymptomCategory($symptoms, $tests) {
    $symptomsLower = strtolower($symptoms . ' ' . $tests);
    
    $categoryPatterns = [
        'dermatological' => [
            'keywords' => ['skin', 'rash', 'patch', 'lesion', 'itchy', 'itching', 'circular', 'ring', 'scaly', 'eruption', 'fungal', 'dermatophyte', 'koh', 'wood lamp', 'tinea', 'ringworm', 'eczema', 'psoriasis', 'dermatitis', 'hives', 'urticaria'],
            'weight' => 0
        ],
        'musculoskeletal' => [
            'keywords' => ['joint', 'arthritis', 'bone', 'muscle', 'stiffness', 'swelling joint', 'rheumatoid', 'gout', 'back pain', 'neck pain'],
            'weight' => 0
        ],
        'neurological' => [
            'keywords' => ['migraine', 'stroke', 'paralysis', 'weakness', 'numbness', 'seizure', 'facial droop', 'speech difficulty', 'one-sided'],
            'weight' => 0
        ],
        'cardiovascular' => [
            // Extended to include hypertension-related symptoms
            'keywords' => ['chest pain', 'heart', 'palpitation', 'blood pressure', 'hypertension', 'cardiac', 'angina', 'shortness of breath', 'nosebleed', 'epistaxis', 'dizziness morning', 'morning headache', 'flushing'],
            'weight' => 0
        ],
        'respiratory' => [
            'keywords' => ['cough', 'breathing', 'wheeze', 'asthma', 'pneumonia', 'bronchitis', 'lung', 'sputum'],
            'weight' => 0
        ],
        'digestive' => [
            'keywords' => ['stomach', 'abdominal', 'nausea', 'vomiting', 'diarrhea', 'constipation', 'gastric', 'liver', 'appendix', 'burning', 'epigastric', 'acidity', 'reflux', 'belching', 'eructation', 'heartburn', 'acid', 'indigestion', 'dyspepsia', 'bloating', 'gerd', 'ulcer', 'gastritis', 'abdomen', 'gut', 'bowel', 'intestinal', 'esophageal', 'upper gi', 'antacid'],
            'weight' => 0
        ],
        'endocrine' => [
            'keywords' => ['thyroid', 'diabetes', 'hormone', 'weight gain', 'weight loss', 'fatigue', 'cold intolerance', 'heat intolerance'],
            'weight' => 0
        ],
        'infectious' => [
            'keywords' => ['fever', 'infection', 'bacterial', 'viral', 'typhoid', 'malaria', 'dengue'],
            'weight' => 0
        ]
    ];
    
    // Count keyword matches for each category
    foreach ($categoryPatterns as $category => &$data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($symptomsLower, $keyword) !== false) {
                $data['weight'] += 1;
            }
        }
    }
    
    // Find the category with highest weight
    $maxWeight = 0;
    $primaryCategory = null;
    foreach ($categoryPatterns as $category => $data) {
        if ($data['weight'] > $maxWeight) {
            $maxWeight = $data['weight'];
            $primaryCategory = $category;
        }
    }
    
    return [
        'primary' => $primaryCategory,
        'weights' => array_map(function($d) { return $d['weight']; }, $categoryPatterns)
    ];
}

/**
 * Check if lab tests indicate fungal infection
 */
function hasFungalLabConfirmation($tests) {
    $testsLower = strtolower($tests);
    $fungalIndicators = ['koh', 'fungal hyphae', 'dermatophyte', 'wood lamp positive', 'fungal culture positive', 'tinea'];
    
    foreach ($fungalIndicators as $indicator) {
        if (strpos($testsLower, $indicator) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Perform RAG-based diagnosis using local database
 */
function performRAGDiagnosis($symptoms, $tests, $duration, $severity, $age = null, $gender = null) {
    
    // First check if diseases table exists and has data
    $diseaseCount = DB::queryOne("SELECT COUNT(*) as cnt FROM diseases");
    $totalDiseases = $diseaseCount['cnt'] ?? 0;
    
    error_log("Diagnosis - Total diseases in DB: " . $totalDiseases);
    
    if ($totalDiseases == 0) {
        return [
            'diagnoses' => [],
            'analysis' => 'Disease database is empty. Please import diseases_schema.sql and diseases_data.sql files.',
            'matched_keywords' => [],
            'notes' => 'Database tables need to be populated.',
            'disclaimer' => 'Run: mysql homeo_db < database/diseases_schema.sql && mysql homeo_db < database/diseases_data.sql'
        ];
    }
    
    // Extract keywords from symptoms with enhanced extraction
    $keywords = extractDiagnosticKeywords($symptoms);
    
    // Also extract disease patterns (multi-word symptom combinations)
    $symptomPatterns = extractSymptomPatterns($symptoms);
    
    // CRITICAL: Extract NEGATED symptoms (e.g., "no vomiting", "without fever")
    $negatedSymptoms = extractNegatedSymptoms($symptoms);
    
    error_log("Diagnosis - Extracted keywords: " . implode(', ', $keywords));
    error_log("Diagnosis - Negated symptoms: " . implode(', ', $negatedSymptoms));
    
    // Remove negated symptoms from keywords
    $keywords = array_diff($keywords, $negatedSymptoms);
    
    if (empty($keywords)) {
        return [
            'diagnoses' => [],
            'analysis' => 'No confident diagnosis found based on available data. Please provide more specific symptoms.',
            'matched_keywords' => [],
            'notes' => 'Insufficient symptom data for analysis.'
        ];
    }

    // IMPORTANT: Detect the primary symptom category to filter irrelevant diseases
    $categoryInfo = detectSymptomCategory($symptoms, $tests);
    $primaryCategory = $categoryInfo['primary'];
    $categoryWeights = $categoryInfo['weights'];
    
    error_log("Diagnosis - Primary category detected: " . ($primaryCategory ?? 'none'));
    error_log("Diagnosis - Category weights: " . json_encode($categoryWeights));
    
    // CRITICAL: Extract duration for chronicity awareness
    $durationInfo = extractDuration($symptoms);
    $durationDays = $durationInfo['days'];
    $durationType = $durationInfo['type'];
    $isChronicPresentation = ($durationType === 'chronic' || $durationType === 'subacute' || hasChronicPresentationMarkers($symptoms));
    
    error_log("Diagnosis - Duration: {$durationInfo['days']} days, Type: $durationType, Chronic: " . ($isChronicPresentation ? 'YES' : 'NO'));
    
    // Check if fungal infection is confirmed by labs
    $fungalConfirmed = hasFungalLabConfirmation($tests);
    error_log("Diagnosis - Fungal lab confirmation: " . ($fungalConfirmed ? 'YES' : 'NO'));
    
    // CRITICAL: Extract modalities (aggravating/ameliorating factors) - NOT disease causes
    $modalities = extractModalities($symptoms);
    error_log("Diagnosis - Modalities detected: " . json_encode($modalities));
    
    // CRITICAL: Detect organ system from symptom location
    $organSystemInfo = detectOrganSystem($symptoms);
    error_log("Diagnosis - Organ system: " . json_encode($organSystemInfo));

    // Score diseases based on symptom matches
    $diseaseScores = [];
    $diseaseMatches = [];
    
    // Check for pathognomonic symptom combinations (highly specific for certain diseases)
    $symptomsLower = strtolower($symptoms);
    $pathognomonicBonus = [];
    
    // Calculate pattern-based bonuses using comprehensive disease pattern matching
    $pathognomonicBonus = calculatePathognomonicBonuses($symptomsLower, $symptomPatterns);
    
    // Search diseases by primary and secondary symptoms
    foreach ($keywords as $keyword) {
        if (strlen($keyword) < 3) continue;
        
        $pattern = '%' . $keyword . '%';
        
        // Search in disease symptoms fields
        $matches = DB::query(
            "SELECT id, disease_name, icd_code, category, 
                    primary_symptoms, secondary_symptoms, warning_signs,
                    clinical_findings, lab_tests, differential_diagnosis,
                    typical_onset, typical_duration, age_groups, gender_predisposition,
                    severity_level, urgency_level, reference_source
             FROM diseases 
             WHERE LOWER(primary_symptoms) LIKE ?
                OR LOWER(secondary_symptoms) LIKE ?
                OR LOWER(warning_signs) LIKE ?
                OR LOWER(clinical_findings) LIKE ?
                OR LOWER(disease_name) LIKE ?
             LIMIT 30",
            [$pattern, $pattern, $pattern, $pattern, $pattern]
        );
        
        // Handle query failure
        if ($matches === false) {
            error_log("Diagnosis - Query failed for keyword '$keyword'");
            continue;
        }
        
        error_log("Diagnosis - Keyword '$keyword' found " . count($matches) . " matches");
        
        // Debug: Log disease names found for key dermatological keywords
        if (in_array($keyword, ['circular', 'ring', 'ringworm', 'tinea', 'scaly', 'fungal'])) {
            $foundNames = array_column($matches, 'disease_name');
            error_log("Diagnosis - DEBUG: For '$keyword' found diseases: " . implode(', ', $foundNames));
        }
        
        foreach ($matches as $disease) {
            $key = $disease['id'];
            
            if (!isset($diseaseScores[$key])) {
                $diseaseScores[$key] = 0;
                $diseaseMatches[$key] = [
                    'disease' => $disease,
                    'matched_symptoms' => [],
                    'match_locations' => []
                ];
            }
            
            // Score based on where the match was found
            $score = 0;
            $diseaseName = strtolower($disease['disease_name'] ?? '');
            $primarySymptoms = strtolower($disease['primary_symptoms'] ?? '');
            $secondarySymptoms = strtolower($disease['secondary_symptoms'] ?? '');
            $warningSignsText = strtolower($disease['warning_signs'] ?? '');
            $clinicalFindings = strtolower($disease['clinical_findings'] ?? '');
            
            // HIGHEST score for disease name match (very specific)
            if (strpos($diseaseName, $keyword) !== false) {
                $score += 50; // Very high score for direct disease name match
                $diseaseMatches[$key]['match_locations'][] = 'disease_name';
            }
            
            // High score for primary symptoms
            if (strpos($primarySymptoms, $keyword) !== false) {
                $score += 10;
                $diseaseMatches[$key]['match_locations'][] = 'primary_symptoms';
            }
            
            // Good score for secondary symptoms
            if (strpos($secondarySymptoms, $keyword) !== false) {
                $score += 6;
                $diseaseMatches[$key]['match_locations'][] = 'secondary_symptoms';
            }
            
            // High score for warning signs (indicates serious match)
            if (strpos($warningSignsText, $keyword) !== false) {
                $score += 8;
                $diseaseMatches[$key]['match_locations'][] = 'warning_signs';
            }
            
            // Moderate score for clinical findings
            if (strpos($clinicalFindings, $keyword) !== false) {
                $score += 4;
                $diseaseMatches[$key]['match_locations'][] = 'clinical_findings';
            }
            
            $diseaseScores[$key] += $score;
            $diseaseMatches[$key]['matched_symptoms'][] = $keyword;
        }
    }
    
    // Also search using symptom_master junction table if available
    try {
        $symptomMatches = searchBySymptomJunction($keywords);
        foreach ($symptomMatches as $match) {
            $key = $match['disease_id'];
            if (!isset($diseaseScores[$key])) {
                $diseaseScores[$key] = 0;
                $diseaseMatches[$key] = [
                    'disease' => $match,
                    'matched_symptoms' => [],
                    'match_locations' => ['symptom_database']
                ];
            }
            
            // Weight by symptom importance
            $weightMultiplier = 1;
            switch ($match['symptom_weight'] ?? 'minor') {
                case 'pathognomonic': $weightMultiplier = 5; break;
                case 'major': $weightMultiplier = 3; break;
                case 'minor': $weightMultiplier = 1; break;
            }
            
            $diseaseScores[$key] += ($match['specificity_score'] ?? 50) / 10 * $weightMultiplier;
            $diseaseMatches[$key]['matched_symptoms'][] = $match['symptom_name'] ?? '';
        }
    } catch (Exception $e) {
        // Junction table may not exist yet
    }
    
    // Search by lab tests if provided
    if (!empty($tests)) {
        $testKeywords = extractDiagnosticKeywords($tests);
        foreach ($testKeywords as $testKw) {
            try {
                $testMatches = DB::query(
                    "SELECT l.disease_id, l.test_name, l.expected_finding, l.is_diagnostic, d.disease_name
                     FROM lab_test_findings l
                     JOIN diseases d ON l.disease_id = d.id
                     WHERE LOWER(l.test_name) LIKE ? OR LOWER(l.expected_finding) LIKE ?
                     LIMIT 20",
                    ['%' . $testKw . '%', '%' . $testKw . '%']
                );
                
                if ($testMatches === false) continue;
                
                foreach ($testMatches as $testMatch) {
                    $key = $testMatch['disease_id'];
                    if (isset($diseaseScores[$key])) {
                        // Boost score if test matches
                        $diseaseScores[$key] += $testMatch['is_diagnostic'] ? 15 : 8;
                        $diseaseMatches[$key]['matched_tests'][] = $testMatch['test_name'];
                    }
                }
            } catch (Exception $e) {
                // Lab test table may not exist
            }
        }
    }
    
    // Handle empty results
    if (empty($diseaseScores)) {
        error_log("Diagnosis - No disease scores found");
        return [
            'diagnoses' => [],
            'analysis' => 'No confident diagnosis found based on available data. The symptoms entered may not match any conditions in the database.',
            'matched_keywords' => $keywords,
            'notes' => 'Consider adding more specific symptoms or clinical findings.'
        ];
    }
    
    error_log("Diagnosis - Found " . count($diseaseScores) . " diseases with scores before filtering");
    
    // ============================================
    // CRITICAL: Apply category-based filtering and scoring adjustments
    // This prevents dangerous mismatches like showing Stroke for skin symptoms
    // ============================================
    
    foreach ($diseaseScores as $id => $score) {
        $disease = $diseaseMatches[$id]['disease'];
        $diseaseCategory = $disease['category'] ?? '';
        $diseaseName = $disease['disease_name'] ?? '';
        $matchedSymptoms = $diseaseMatches[$id]['matched_symptoms'] ?? [];
        
        // Define $giSymptomsDominant early - used throughout this loop
        // FIX: Declared as bool cast of preg_match so the second re-declaration inside viral block
        // does not silently change the type between iterations.
        $giSymptomsDominant = (bool) preg_match('/\b(gastric|stomach|epigastric|abdominal|acidity|reflux|belching|eructation|heartburn|ulcer|bloating|dyspepsia|nausea|vomiting|diarrhea|constipation)\b/i', $symptomsLower);
        
        // ============================================
        // AGE-BASED FILTERING - Block pediatric diseases for adults
        // ============================================
        $isPediatricDisease = preg_match('/\b(child|children|infant|pediatric|paediatric|newborn|neonatal|baby|toddler)\b/i', $diseaseName);
        $patientIsAdult = ($age !== null && $age >= 18);
        $patientIsChild = ($age !== null && $age < 18);
        
        // Block pediatric diseases for adult patients
        if ($isPediatricDisease && $patientIsAdult) {
            $diseaseScores[$id] -= 800; // Complete block
            error_log("Diagnosis - BLOCKED pediatric disease for adult: $diseaseName (patient age: $age)");
        }
        
        // Block adult-specific diseases for children
        $isAdultOnlyDisease = preg_match('/\b(prostat|menopaus|erectile|andropaus|presbycusis|cataract|macular\s+degeneration)\b/i', $diseaseName);
        if ($isAdultOnlyDisease && $patientIsChild) {
            $diseaseScores[$id] -= 800;
            error_log("Diagnosis - BLOCKED adult disease for child: $diseaseName (patient age: $age)");
        }

        // ============================================
        // GENDER-BASED FILTERING  (FIX: gender was previously unused)
        // ============================================
        $genderLower = strtolower((string)$gender);
        $maleOnlyPattern = '/\b(prostate|prostatic|testicular|penis|penile|erectile\s+dysfunction|epididymitis|varicocele|phimosis|paraphimosis|orchitis|bph)\b/i';
        $femaleOnlyPattern = '/\b(menstrual|menstruation|menopause|ovarian|ovary|uterine|uterus|endometrio|pcos|polycystic\s+ovary|cervical|cervix|vaginitis|vulv|breast\s+cancer|mastitis|pregnancy|gestational|eclampsia|preeclampsia|dysmenorrhea|amenorrhea)\b/i';

        if ($genderLower === 'female' && preg_match($maleOnlyPattern, $diseaseName)) {
            $diseaseScores[$id] -= 800;
            error_log("Diagnosis - BLOCKED male-only disease for female: $diseaseName");
        }
        if ($genderLower === 'male' && preg_match($femaleOnlyPattern, $diseaseName)) {
            $diseaseScores[$id] -= 800;
            error_log("Diagnosis - BLOCKED female-only disease for male: $diseaseName");
        }
        
        // ============================================
        // MANDATORY SYMPTOM VALIDATION - Additional diseases
        // ============================================
        
        // DYSPHAGIA - MUST have difficulty swallowing (NOT just "throat" or "food")
        if (stripos($diseaseName, 'dysphagia') !== false || 
            stripos($diseaseName, 'swallowing') !== false) {
            
            $hasDifficultySwallowing = preg_match('/\b(difficulty\s+swallowing|trouble\s+swallowing|can\'?t\s+swallow|unable\s+to\s+swallow|food\s+stuck|choking|chokes?\s+on\s+food|swallowing\s+problem|painful\s+swallowing|odynophagia)\b/i', $symptomsLower);
            $hasEsophagealSymptoms = preg_match('/\b(esophag|oesophag|regurgitat|stricture|obstruction)\b/i', $symptomsLower);
            
            // Dysphagia without actual swallowing difficulty is impossible
            if (!$hasDifficultySwallowing && !$hasEsophagealSymptoms) {
                $diseaseScores[$id] -= 700; // Complete block
                error_log("Diagnosis - BLOCKED Dysphagia: NO swallowing difficulty present (throat/food/meals alone is NOT dysphagia)");
            }
            // GI acid symptoms are NOT dysphagia
            if ($giSymptomsDominant && !$hasDifficultySwallowing) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Dysphagia: GI symptoms without swallowing difficulty = GERD, not dysphagia");
            }
        }
        
        // DIARRHEA diseases - MUST have diarrhea symptoms
        if (preg_match('/\b(diarrh|diarr)/i', $diseaseName)) {
            $hasDiarrheaSymptom = preg_match('/\b(diarrhea|diarrhoea|loose\s+stools?|watery\s+stools?|frequent\s+stools?|bowel\s+movements?\s+increased|running\s+stomach)\b/i', $symptomsLower);
            
            if (!$hasDiarrheaSymptom) {
                $diseaseScores[$id] -= 700; // Complete block
                error_log("Diagnosis - BLOCKED diarrhea disease: NO diarrhea symptoms present ($diseaseName)");
            }
        }
        
        // CONSTIPATION diseases - MUST have constipation symptoms
        if (stripos($diseaseName, 'constipation') !== false) {
            $hasConstipationSymptom = preg_match('/\b(constipat|hard\s+stools?|difficulty\s+passing\s+stool|straining|infrequent\s+bowel|no\s+bowel\s+movement)\b/i', $symptomsLower);
            
            if (!$hasConstipationSymptom) {
                $diseaseScores[$id] -= 600;
                error_log("Diagnosis - BLOCKED constipation disease: NO constipation symptoms present");
            }
        }
        
        // DEHYDRATION - MUST have fluid loss or dehydration signs
        if (stripos($diseaseName, 'dehydration') !== false) {
            $hasDehydrationSigns = preg_match('/\b(dehydrat|dry\s+mouth|thirst|decreased\s+urine|sunken\s+eyes|skin\s+turgor|vomiting|diarrhea|excessive\s+sweating)\b/i', $symptomsLower);
            
            if (!$hasDehydrationSigns) {
                $diseaseScores[$id] -= 600;
                error_log("Diagnosis - BLOCKED dehydration: NO dehydration signs present");
            }
        }
        
        // 0. CRITICAL: Check for WEAK-ONLY matches
        // If a disease only matches on generic symptoms, it should be penalized HEAVILY
        // These words trigger false positives across ALL diseases
        $genericSymptoms = [
            'weakness', 'fatigue', 'pain', 'tired', 'loss', 'symptoms', 'worse', 'past', 
            'severe', 'normal', 'meals', 'eating', 'food', 'discomfort', 'mouth', 
            'bloating', 'stress', 'anxiety', 'irritable', 'especially', 'frequent', 
            'occasional', 'lying', 'night', 'morning', 'afternoon', 'sensation', 
            'taking', 'relief', 'temporary', 'history', 'chronic', 'gradual'
        ];
        $hasSpecificMatch = false;
        $genericMatchCount = 0;
        
        foreach ($matchedSymptoms as $matched) {
            $matchedLower = strtolower($matched);
            $isGeneric = false;
            foreach ($genericSymptoms as $generic) {
                if ($matchedLower === $generic || strpos($matchedLower, $generic) !== false) {
                    $isGeneric = true;
                    $genericMatchCount++;
                    break;
                }
            }
            if (!$isGeneric && strlen($matchedLower) > 3) {
                $hasSpecificMatch = true;
            }
        }
        
        // If ONLY generic matches and no specific symptoms, heavily penalize
        if (!$hasSpecificMatch && $genericMatchCount > 0 && count($matchedSymptoms) <= 5) {
            $diseaseScores[$id] -= 400; // Increased from 300
            error_log("Diagnosis - WEAK MATCH ONLY penalty -400 for $diseaseName (only generic symptoms matched: " . implode(', ', $matchedSymptoms) . ")");
        }
        
        // Additional penalty: If more than 50% of matches are generic and it's a non-digestive disease in a GI case
        if ($giSymptomsDominant && $diseaseCategory !== 'digestive' && $genericMatchCount >= count($matchedSymptoms) * 0.5) {
            $diseaseScores[$id] -= 300;
            error_log("Diagnosis - Cross-system generic match penalty -300 for $diseaseName (GI case but non-GI disease with mostly generic matches)");
        }
        
        // 1. BOOST diseases that match the primary symptom category
        if ($primaryCategory && $diseaseCategory === $primaryCategory) {
            $diseaseScores[$id] += 50; // Category match bonus
            error_log("Diagnosis - Category match bonus +50 for $diseaseName (category: $diseaseCategory)");
        }
        
        // 2. PENALIZE diseases from unrelated categories when a clear category is detected
        if ($primaryCategory && $diseaseCategory !== $primaryCategory) {
            $categoryPenalties = [
                'dermatological' => ['neurological' => -100, 'cardiovascular' => -100, 'musculoskeletal' => -80, 'infectious' => -80],
                'neurological' => ['dermatological' => -80, 'digestive' => -60],
                'cardiovascular' => ['dermatological' => -80, 'digestive' => -60],
                'musculoskeletal' => ['neurological' => -60, 'cardiovascular' => -60],
                'digestive' => ['neurological' => -80, 'respiratory' => -100, 'dermatological' => -80, 'musculoskeletal' => -80],
            ];
            
            if (isset($categoryPenalties[$primaryCategory][$diseaseCategory])) {
                $penalty = $categoryPenalties[$primaryCategory][$diseaseCategory];
                $diseaseScores[$id] += $penalty;
                error_log("Diagnosis - Category mismatch penalty $penalty for $diseaseName (expected: $primaryCategory, got: $diseaseCategory)");
            }
        }
        
        // 2b. NEGATED SYMPTOMS CHECK
        // If a disease requires symptoms that are explicitly denied, penalize heavily
        $diseasePrimarySymptoms = [
            'dengue' => ['fever'],
            'malaria' => ['fever'],
            'typhoid' => ['fever'],
            'influenza' => ['fever', 'cough'],
            'covid' => ['fever', 'cough'],
            'pneumonia' => ['fever', 'cough'],
            'bronchitis' => ['cough'],
            'gastroenteritis' => ['diarrhea', 'vomiting'],
            'cholera' => ['diarrhea'],
            'food poisoning' => ['vomiting', 'diarrhea'],
            'meningitis' => ['fever', 'headache'],
            'stroke' => ['weakness', 'paralysis', 'numbness'],
            'myocardial infarction' => ['chest pain'],
            'heart attack' => ['chest pain'],
        ];
        
        $diseaseNameLowerForNegation = strtolower($diseaseName);
        foreach ($diseasePrimarySymptoms as $diseaseKey => $requiredSymptoms) {
            if (strpos($diseaseNameLowerForNegation, $diseaseKey) !== false) {
                foreach ($requiredSymptoms as $reqSymptom) {
                    if (in_array($reqSymptom, $negatedSymptoms)) {
                        $diseaseScores[$id] -= 400;
                        error_log("Diagnosis - NEGATED SYMPTOM penalty -400 for $diseaseName: '$reqSymptom' is explicitly denied");
                    }
                }
            }
        }
        
        // 3. HEAVILY PENALIZE dangerous mismatches when skin symptoms are present
        if ($primaryCategory === 'dermatological') {
            $dangerousMismatches = ['Ischemic Stroke', 'Hemorrhagic Stroke', 'Myocardial Infarction', 'Appendicitis', 'Meningitis'];
            if (in_array($diseaseName, $dangerousMismatches)) {
                $diseaseScores[$id] -= 200; // Severe penalty
                error_log("Diagnosis - DANGEROUS MISMATCH penalty -200 for $diseaseName (skin symptoms detected)");
            }
        }
        
        // 3b. HEAVILY PENALIZE Influenza/Flu for digestive/GI cases
        // "reflux" contains "flu" which causes false matches
        if ($primaryCategory === 'digestive') {
            $diseaseNameLower = strtolower($diseaseName);
            if (strpos($diseaseNameLower, 'influenza') !== false || $diseaseNameLower === 'flu') {
                // Check if there are actual flu symptoms (fever + body ache + respiratory)
                $hasFever = strpos($symptomsLower, 'fever') !== false;
                $hasBodyAche = preg_match('/\b(body\s*ache|myalgia|muscle\s*pain|chills)\b/i', $symptomsLower);
                $hasRespiratory = preg_match('/\b(cough|sore\s*throat|runny\s*nose|cold|congestion)\b/i', $symptomsLower);
                
                if (!$hasFever && !$hasBodyAche && !$hasRespiratory) {
                    $diseaseScores[$id] -= 200; // Severe penalty - not flu symptoms
                    error_log("Diagnosis - Influenza penalty -200 for GI case without flu symptoms");
                }
            }
            
            // Also penalize other respiratory infections for GI cases
            $respiratoryDiseases = ['pneumonia', 'bronchitis', 'common cold', 'upper respiratory', 'pharyngitis', 'tonsillitis', 'laryngitis'];
            foreach ($respiratoryDiseases as $respDisease) {
                if (strpos($diseaseNameLower, $respDisease) !== false) {
                    $diseaseScores[$id] -= 150;
                    error_log("Diagnosis - Respiratory disease penalty -150 for $diseaseName (digestive case)");
                    break;
                }
            }
            
            // CRITICAL: COVID-19 must have respiratory symptoms OR fever to be considered
            if (strpos($diseaseNameLower, 'covid') !== false || strpos($diseaseNameLower, 'coronavirus') !== false) {
                $hasFeverForCovid = strpos($symptomsLower, 'fever') !== false;
                $hasRespiratoryForCovid = preg_match('/\b(cough|breathing|shortness|breath|oxygen|dyspnea|respiratory)\b/i', $symptomsLower);
                $hasActualTasteLoss = preg_match('/\b(loss\s+of\s+taste|anosmia|loss\s+of\s+smell|cannot\s+taste|cannot\s+smell)\b/i', $symptomsLower);
                
                if (!$hasFeverForCovid && !$hasRespiratoryForCovid && !$hasActualTasteLoss) {
                    $diseaseScores[$id] -= 300; // Severe penalty - no COVID symptoms at all
                    error_log("Diagnosis - COVID-19 BLOCKED: No fever, no respiratory, no taste/smell loss in GI case");
                }
            }
        }
        
        // 3c. MANDATORY SYMPTOM VALIDATION for infectious diseases
        // COVID-19 and Influenza MUST have either fever, cough, or respiratory symptoms
        $diseaseNameLower = strtolower($diseaseName);
        $viralInfections = ['covid', 'influenza', 'flu', 'coronavirus', 'sars'];
        $isViralInfection = false;
        foreach ($viralInfections as $viral) {
            if (strpos($diseaseNameLower, $viral) !== false) {
                $isViralInfection = true;
                break;
            }
        }
        
        if ($isViralInfection) {
            // IMPORTANT: "sour taste" is NOT "loss of taste" - they are opposite symptoms!
            // GI sour taste = acid reflux symptom
            // COVID loss of taste = anosmia/ageusia
            $hasSourTaste = preg_match('/\b(sour\s+taste|acid\s+taste|metallic\s+taste|bad\s+taste|bitter\s+taste)\b/i', $symptomsLower);
            $hasActualTasteLoss = preg_match('/\b(loss\s+of\s+taste|cannot\s+taste|no\s+taste|lost\s+taste|ageusia)\b/i', $symptomsLower);
            
            // If "sour taste" but NOT "loss of taste", this is NOT a COVID symptom
            $tasteLossForCovid = $hasActualTasteLoss && !$hasSourTaste;
            
            $mandatoryViralSymptoms = [
                'fever' => strpos($symptomsLower, 'fever') !== false,
                'cough' => strpos($symptomsLower, 'cough') !== false,
                'respiratory' => preg_match('/\b(breathing|shortness|breath|respiratory|dyspnea|oxygen)\b/i', $symptomsLower),
                'body_ache' => preg_match('/\b(body\s*ache|myalgia|muscle\s*pain|chills|rigors)\b/i', $symptomsLower),
                'taste_smell' => $tasteLossForCovid || preg_match('/\b(loss\s+of\s+smell|anosmia|cannot\s+smell)\b/i', $symptomsLower),
                'sore_throat' => preg_match('/\b(sore\s*throat|pharyngitis|throat\s*pain)\b/i', $symptomsLower),
            ];
            
            $viralSymptomCount = array_sum(array_map('intval', $mandatoryViralSymptoms));
            
            // Check if GI symptoms are dominant (indicating this is NOT a viral infection)
            // FIX: Reuse outer $giSymptomsDominant but also allow a more restrictive viral-context check.
            // Previously shadowed the outer variable, creating inconsistent behavior per iteration.
            $giSymptomsDominantViralCtx = (bool) preg_match('/\b(gastric|stomach|epigastric|abdominal|acidity|reflux|belching|eructation|heartburn|ulcer|bloating|dyspepsia)\b/i', $symptomsLower);
            $giSymptomsDominant = $giSymptomsDominant || $giSymptomsDominantViralCtx;
            
            // If NO viral infection symptoms present, block the disease completely
            if ($viralSymptomCount == 0) {
                $diseaseScores[$id] -= 500; // Complete block
                error_log("Diagnosis - BLOCKED $diseaseName: No viral symptoms present (fever/cough/respiratory/body ache)");
                
                // Extra penalty if GI symptoms are dominant - this is clearly NOT a viral infection
                if ($giSymptomsDominant) {
                    $diseaseScores[$id] -= 300; // Additional GI dominance penalty
                    error_log("Diagnosis - EXTRA BLOCK $diseaseName: GI symptoms dominant, viral infection impossible");
                }
            } else if ($viralSymptomCount < 2) {
                // Only 1 symptom - needs strong penalty unless it's fever
                if (!$mandatoryViralSymptoms['fever']) {
                    $diseaseScores[$id] -= 200;
                    error_log("Diagnosis - Strong penalty for $diseaseName: Only 1 non-fever viral symptom");
                }
                
                // If GI symptoms dominant but only weak viral match, still penalize
                if ($giSymptomsDominant) {
                    $diseaseScores[$id] -= 200;
                    error_log("Diagnosis - GI dominant penalty for $diseaseName despite weak viral match");
                }
            }
        }
        
        // 3d. MANDATORY SYMPTOM VALIDATION for specific diseases
        // These diseases MUST have certain symptoms - without them, they are IMPOSSIBLE diagnoses
        
        // DENGUE FEVER - MUST have fever (it's literally in the name!)
        if (stripos($diseaseName, 'dengue') !== false) {
            $hasFeverForDengue = strpos($symptomsLower, 'fever') !== false;
            $hasRash = preg_match('/\b(rash|petechiae|bleeding|hemorrh|purpura)\b/i', $symptomsLower);
            $hasThrombocytopenia = preg_match('/\b(low\s+platelet|thrombocytopenia|platelet\s+count)\b/i', $symptomsLower);
            $hasBodyAcheDengue = preg_match('/\b(body\s*ache|myalgia|muscle\s*pain|joint\s*pain|arthralgia|bone\s*pain|severe\s*headache)\b/i', $symptomsLower);
            
            // Dengue WITHOUT fever is impossible
            if (!$hasFeverForDengue) {
                $diseaseScores[$id] -= 500; // Complete block
                error_log("Diagnosis - BLOCKED Dengue: NO FEVER (fever is mandatory for Dengue)");
            }
            // Even with "fever" mentioned somewhere, needs more dengue-specific symptoms
            if (!$hasRash && !$hasThrombocytopenia && !$hasBodyAcheDengue) {
                $diseaseScores[$id] -= 300;
                error_log("Diagnosis - Dengue penalty: No rash, no bleeding, no body ache, no platelet issues");
            }
            // If GI symptoms are dominant and no fever, absolutely block
            if ($giSymptomsDominant && !$hasFeverForDengue) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Dengue: GI case without fever");
            }
        }
        
        // MULTIPLE SCLEROSIS - MUST have neurological symptoms
        if (stripos($diseaseName, 'multiple sclerosis') !== false || $diseaseName === 'MS') {
            $hasNeurologicalSymptoms = preg_match('/\b(vision|blindness|optic|numbness|tingling|paresthesia|paralysis|weakness\s+in\s+(arm|leg|limb)|gait|balance|coordination|vertigo|speech|slurred|cognitive|memory\s+loss|bladder|incontinence|spasticity)\b/i', $symptomsLower);
            $hasMotorDeficit = preg_match('/\b(difficulty\s+walking|drop\s+foot|hemiparesis|monoparesis|paraparesis)\b/i', $symptomsLower);
            $hasSensoryDeficit = preg_match('/\b(numb|tingling|pins\s+and\s+needles|electric\s+shock|lhermitte)\b/i', $symptomsLower);
            
            // MS without ANY neurological symptoms is impossible
            if (!$hasNeurologicalSymptoms && !$hasMotorDeficit && !$hasSensoryDeficit) {
                $diseaseScores[$id] -= 600; // Complete block
                error_log("Diagnosis - BLOCKED Multiple Sclerosis: NO neurological symptoms present");
            }
            // If GI symptoms dominant, MS is extremely unlikely
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED MS: GI symptoms dominant, not neurological");
            }
        }
        
        // MALARIA - MUST have fever (cyclical) 
        if (stripos($diseaseName, 'malaria') !== false) {
            $hasFeverForMalaria = strpos($symptomsLower, 'fever') !== false;
            $hasChillsRigors = preg_match('/\b(chills|rigors|shivering|sweating)\b/i', $symptomsLower);
            
            if (!$hasFeverForMalaria) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Malaria: NO FEVER");
            }
            if ($giSymptomsDominant && !$hasFeverForMalaria) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Malaria: GI case without fever");
            }
        }
        
        // TYPHOID - MUST have fever
        if (stripos($diseaseName, 'typhoid') !== false) {
            $hasFeverForTyphoid = strpos($symptomsLower, 'fever') !== false;
            if (!$hasFeverForTyphoid) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Typhoid: NO FEVER");
            }
        }
        
        // MENINGITIS - MUST have neck stiffness or severe headache + fever
        if (stripos($diseaseName, 'meningitis') !== false) {
            $hasNeckStiffness = preg_match('/\b(neck\s+stiffness|stiff\s+neck|meningism|kernig|brudzinski)\b/i', $symptomsLower);
            $hasSevereHeadache = preg_match('/\b(severe\s+headache|worst\s+headache|thunderclap)\b/i', $symptomsLower);
            $hasFeverMeningitis = strpos($symptomsLower, 'fever') !== false;
            $hasPhotophobia = strpos($symptomsLower, 'photophobia') !== false || preg_match('/light\s+sensitive/i', $symptomsLower);
            
            if (!$hasNeckStiffness && !($hasSevereHeadache && $hasFeverMeningitis) && !$hasPhotophobia) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Meningitis: No neck stiffness, no severe headache+fever, no photophobia");
            }
        }
        
        // STROKE - MUST have sudden onset neurological deficit
        if (stripos($diseaseName, 'stroke') !== false) {
            $hasSuddenOnset = preg_match('/\b(sudden|acute|abrupt)\b/i', $symptomsLower);
            $hasNeurologicalDeficit = preg_match('/\b(weakness|paralysis|facial\s+droop|speech|slurred|aphasia|vision\s+loss|numbness|confusion)\b/i', $symptomsLower);
            $hasOneSided = preg_match('/\b(one\s+side|unilateral|left\s+side|right\s+side|hemiparesis)\b/i', $symptomsLower);
            
            // Chronic presentation rules out acute stroke
            if ($isChronicPresentation) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Stroke: Chronic presentation (>1 month)");
            }
            if (!$hasNeurologicalDeficit && !$hasOneSided) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Stroke: No neurological deficit or one-sided symptoms");
            }
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Stroke: GI symptoms dominant");
            }
        }
        
        // MYOCARDIAL INFARCTION - MUST have chest pain/pressure
        if (stripos($diseaseName, 'myocardial infarction') !== false || stripos($diseaseName, 'heart attack') !== false) {
            $hasChestPain = preg_match('/\b(chest\s+pain|chest\s+pressure|chest\s+tightness|substernal|retrosternal)\b/i', $symptomsLower);
            $hasRadiatingPain = preg_match('/\b(radiating|arm\s+pain|jaw\s+pain|left\s+arm)\b/i', $symptomsLower);
            
            if (!$hasChestPain && !$hasRadiatingPain) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED MI: No chest pain or radiating pain");
            }
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED MI: GI symptoms dominant");
            }
        }
        
        // STOMACH/GASTRIC CANCER - MUST have RED FLAG symptoms
        // Cancer should NEVER be suggested based on generic GI symptoms alone
        if (stripos($diseaseName, 'stomach cancer') !== false || 
            stripos($diseaseName, 'gastric cancer') !== false ||
            stripos($diseaseName, 'gastric carcinoma') !== false ||
            stripos($diseaseName, 'esophageal cancer') !== false ||
            stripos($diseaseName, 'colon cancer') !== false ||
            stripos($diseaseName, 'colorectal cancer') !== false ||
            stripos($diseaseName, 'pancreatic cancer') !== false) {
            
            // RED FLAGS for GI malignancy - at least ONE must be present
            $hasWeightLoss = preg_match('/\b(weight\s+loss|losing\s+weight|lost\s+weight|unintentional\s+weight|unexplained\s+weight)\b/i', $symptomsLower);
            $hasAnemia = preg_match('/\b(anemia|anaemia|pallor|pale|low\s+hemoglobin|low\s+hb|hemoglobin\s+low)\b/i', $symptomsLower);
            $hasGIBleeding = preg_match('/\b(hematemesis|melena|blood\s+in\s+stool|bloody\s+stool|black\s+stool|tarry\s+stool|vomiting\s+blood|rectal\s+bleeding|hematochezia)\b/i', $symptomsLower);
            $hasEarlySatiety = preg_match('/\b(early\s+satiety|full\s+quickly|can\'?t\s+eat\s+much|small\s+meals\s+only)\b/i', $symptomsLower);
            $hasDysphagia = preg_match('/\b(dysphagia|difficulty\s+swallowing|trouble\s+swallowing|food\s+stuck|can\'?t\s+swallow)\b/i', $symptomsLower);
            $hasMass = preg_match('/\b(mass|lump|tumor|tumour|palpable|growth|nodule)\b/i', $symptomsLower);
            $hasProgressiveWorsening = preg_match('/\b(progressive|getting\s+worse|worsening\s+over\s+months|deteriorating)\b/i', $symptomsLower);
            $hasLymphNodes = preg_match('/\b(lymph\s+node|supraclavicular|virchow|lymphadenopathy)\b/i', $symptomsLower);
            
            // Count how many red flags are present
            $redFlagCount = ($hasWeightLoss ? 1 : 0) + ($hasAnemia ? 1 : 0) + ($hasGIBleeding ? 1 : 0) + 
                           ($hasEarlySatiety ? 1 : 0) + ($hasDysphagia ? 1 : 0) + ($hasMass ? 1 : 0) + ($hasLymphNodes ? 1 : 0);
            
            // NO red flags = cancer should NOT be suggested
            if ($redFlagCount === 0) {
                $diseaseScores[$id] -= 600; // Heavy block - this is dangerous over-diagnosis
                error_log("Diagnosis - BLOCKED $diseaseName: NO RED FLAGS (no weight loss, no anemia, no GI bleeding, no dysphagia, no mass)");
            }
            // Only 1 weak red flag with common GI symptoms = still unlikely
            else if ($redFlagCount === 1 && !$hasGIBleeding && !$hasMass && !$hasLymphNodes) {
                $diseaseScores[$id] -= 200;
                error_log("Diagnosis - Penalized $diseaseName: Only 1 weak red flag, not convincing for malignancy");
            }
            // GI symptoms dominant without alarming features = likely benign
            if ($giSymptomsDominant && $redFlagCount === 0) {
                $diseaseScores[$id] -= 300;
                error_log("Diagnosis - BLOCKED $diseaseName: Classic GI symptoms but NO red flags - likely benign");
            }
        }
        
        // ATRIAL FIBRILLATION - MUST have cardiac symptoms, NOT GI
        if (stripos($diseaseName, 'atrial fibrillation') !== false || stripos($diseaseName, 'afib') !== false) {
            $hasPalpitations = preg_match('/\b(palpitation|irregular\s+heart|heart\s+racing|skipping\s+beat|fluttering)\b/i', $symptomsLower);
            $hasIrregularPulse = preg_match('/\b(irregular\s+pulse|pulse\s+irregular|arrhythmia)\b/i', $symptomsLower);
            $hasDizzinessSyncope = preg_match('/\b(dizzy|dizziness|lightheaded|syncope|fainting|blackout)\b/i', $symptomsLower);
            $hasChestSymptoms = preg_match('/\b(chest\s+pain|chest\s+discomfort|shortness\s+of\s+breath|dyspnea|breathless)\b/i', $symptomsLower);
            
            // AF without ANY cardiac symptoms is impossible
            if (!$hasPalpitations && !$hasIrregularPulse && !$hasDizzinessSyncope && !$hasChestSymptoms) {
                $diseaseScores[$id] -= 600;
                error_log("Diagnosis - BLOCKED Atrial Fibrillation: NO cardiac symptoms present");
            }
            // GI symptoms dominant = NOT AF
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Atrial Fibrillation: GI symptoms dominant, not cardiac");
            }
        }
        
        // BENIGN PROSTATIC HYPERPLASIA (BPH) - MUST have urinary symptoms
        if (stripos($diseaseName, 'prostatic hyperplasia') !== false || 
            stripos($diseaseName, 'prostate enlargement') !== false ||
            stripos($diseaseName, 'bph') !== false) {
            
            // BPH requires urinary symptoms - without these it's impossible
            $hasUrinaryFrequency = preg_match('/\b(urinary\s+frequency|frequent\s+urination|urinate\s+often|peeing\s+often)\b/i', $symptomsLower);
            $hasNocturia = preg_match('/\b(nocturia|night\s+urination|waking\s+to\s+urinate|urinate\s+at\s+night)\b/i', $symptomsLower);
            $hasWeakStream = preg_match('/\b(weak\s+stream|poor\s+stream|dribbling|hesitancy|straining\s+to\s+urinate|incomplete\s+emptying)\b/i', $symptomsLower);
            $hasUrinaryRetention = preg_match('/\b(urinary\s+retention|can\'?t\s+urinate|unable\s+to\s+urinate|bladder\s+fullness)\b/i', $symptomsLower);
            $hasProstateMention = preg_match('/\b(prostate|prostatic|psa)\b/i', $symptomsLower);
            
            // BPH without ANY urinary symptoms is impossible
            if (!$hasUrinaryFrequency && !$hasNocturia && !$hasWeakStream && !$hasUrinaryRetention && !$hasProstateMention) {
                $diseaseScores[$id] -= 700; // Complete block
                error_log("Diagnosis - BLOCKED BPH: NO urinary symptoms present (frequency, nocturia, weak stream, retention)");
            }
            // GI symptoms dominant = NOT BPH
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED BPH: GI symptoms dominant, not urological");
            }
        }
        
        // CONGESTIVE HEART FAILURE (CHF) - MUST have cardiac/respiratory symptoms
        if (stripos($diseaseName, 'heart failure') !== false || 
            stripos($diseaseName, 'cardiac failure') !== false ||
            stripos($diseaseName, 'chf') !== false) {
            
            // CHF requires specific symptoms - without these it's impossible
            $hasDyspnea = preg_match('/\b(shortness\s+of\s+breath|breathless|dyspnea|difficulty\s+breathing|can\'?t\s+breathe)\b/i', $symptomsLower);
            $hasOrthopnea = preg_match('/\b(orthopnea|can\'?t\s+lie\s+flat|need\s+pillows\s+to\s+sleep|breathless\s+lying)\b/i', $symptomsLower);
            $hasEdema = preg_match('/\b(edema|oedema|swelling\s+(legs?|ankles?|feet)|pedal\s+edema|pitting\s+edema|fluid\s+retention)\b/i', $symptomsLower);
            $hasJVP = preg_match('/\b(jvp|jugular|neck\s+veins)\b/i', $symptomsLower);
            $hasPND = preg_match('/\b(pnd|paroxysmal\s+nocturnal\s+dyspnea|waking\s+breathless)\b/i', $symptomsLower);
            $hasChestPainCHF = preg_match('/\b(chest\s+pain|chest\s+tightness|chest\s+pressure)\b/i', $symptomsLower);
            
            // CHF without cardinal symptoms is impossible
            if (!$hasDyspnea && !$hasOrthopnea && !$hasEdema && !$hasJVP && !$hasPND && !$hasChestPainCHF) {
                $diseaseScores[$id] -= 700; // Complete block
                error_log("Diagnosis - BLOCKED CHF: NO cardiac symptoms (dyspnea, orthopnea, edema, JVP, PND, chest pain)");
            }
            // GI symptoms dominant = NOT CHF (fatigue+lying down is GI reflux, not cardiac orthopnea)
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED CHF: GI symptoms dominant, not cardiac");
            }
        }
        
        // URINARY TRACT INFECTION (UTI) - MUST have urinary symptoms
        if (stripos($diseaseName, 'urinary tract infection') !== false || 
            stripos($diseaseName, 'uti') !== false ||
            stripos($diseaseName, 'cystitis') !== false ||
            stripos($diseaseName, 'pyelonephritis') !== false) {
            
            $hasDysuria = preg_match('/\b(dysuria|burning\s+urination|pain\s+urinating|painful\s+urination)\b/i', $symptomsLower);
            $hasUrinaryFrequencyUTI = preg_match('/\b(urinary\s+frequency|frequent\s+urination|urgency|urge\s+to\s+urinate)\b/i', $symptomsLower);
            $hasUrineChanges = preg_match('/\b(cloudy\s+urine|bloody\s+urine|foul\s+smelling\s+urine|hematuria)\b/i', $symptomsLower);
            $hasFlankPain = preg_match('/\b(flank\s+pain|loin\s+pain|kidney\s+pain)\b/i', $symptomsLower);
            
            if (!$hasDysuria && !$hasUrinaryFrequencyUTI && !$hasUrineChanges && !$hasFlankPain) {
                $diseaseScores[$id] -= 600;
                error_log("Diagnosis - BLOCKED UTI: NO urinary symptoms (dysuria, frequency, urine changes)");
            }
            if ($giSymptomsDominant) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED UTI: GI symptoms dominant, not urological");
            }
        }
        
        // CELIAC DISEASE - MUST have malabsorption symptoms (NOT just bloating)
        if (stripos($diseaseName, 'celiac') !== false || 
            stripos($diseaseName, 'coeliac') !== false ||
            stripos($diseaseName, 'gluten') !== false) {
            
            // Celiac REQUIRES malabsorption evidence - without these it's impossible
            $hasChronicDiarrhea = preg_match('/\b(chronic\s+diarrhea|diarrhoea|loose\s+stools|watery\s+stools|frequent\s+stools)\b/i', $symptomsLower);
            $hasWeightLossCeliac = preg_match('/\b(weight\s+loss|losing\s+weight|lost\s+weight|malnutrition|failure\s+to\s+thrive)\b/i', $symptomsLower);
            $hasAnemiaCeliac = preg_match('/\b(anemia|anaemia|pallor|pale|iron\s+deficiency|low\s+hemoglobin)\b/i', $symptomsLower);
            $hasGlutenTrigger = preg_match('/\b(gluten|wheat|bread|pasta|after\s+eating\s+bread|worse\s+with\s+wheat)\b/i', $symptomsLower);
            $hasDermatitisHerpetiformis = preg_match('/\b(dermatitis\s+herpetiformis|itchy\s+rash|blistering\s+rash|skin\s+rash)\b/i', $symptomsLower);
            $hasSteatorrhea = preg_match('/\b(steatorrhea|fatty\s+stools|oily\s+stools|floating\s+stools|foul\s+smelling\s+stools)\b/i', $symptomsLower);
            
            // Celiac without malabsorption evidence is impossible
            if (!$hasChronicDiarrhea && !$hasWeightLossCeliac && !$hasAnemiaCeliac && !$hasGlutenTrigger && !$hasDermatitisHerpetiformis && !$hasSteatorrhea) {
                $diseaseScores[$id] -= 700; // Complete block - bloating alone is NOT celiac
                error_log("Diagnosis - BLOCKED Celiac: NO malabsorption symptoms (diarrhea, weight loss, anemia, gluten trigger, steatorrhea)");
            }
            // Upper GI symptoms (epigastric, burning, reflux) are NOT celiac - that's GERD/gastritis
            $hasUpperGISymptoms = preg_match('/\b(epigastric|burning|reflux|heartburn|acid|belching|eructation)\b/i', $symptomsLower);
            if ($hasUpperGISymptoms && !$hasChronicDiarrhea && !$hasWeightLossCeliac) {
                $diseaseScores[$id] -= 500;
                error_log("Diagnosis - BLOCKED Celiac: Upper GI symptoms without malabsorption = GERD/gastritis, not celiac");
            }
        }
        
        // CHRONIC PANCREATITIS - MUST have specific pancreatic symptoms
        if (stripos($diseaseName, 'chronic pancreatitis') !== false ||
            (stripos($diseaseName, 'pancreatitis') !== false && stripos($diseaseName, 'acute') === false)) {
            
            // Chronic pancreatitis requires specific symptoms
            $hasBackPain = preg_match('/\b(back\s+pain|radiating\s+to\s+back|pain\s+radiates?\s+back|epigastric.*?back)\b/i', $symptomsLower);
            $hasAlcoholHistory = preg_match('/\b(alcohol|drinking|alcoholic|alcoholism|ethanol)\b/i', $symptomsLower);
            $hasSteatorrheaPanc = preg_match('/\b(steatorrhea|fatty\s+stools|oily\s+stools|floating\s+stools|malabsorption|foul\s+stools)\b/i', $symptomsLower);
            $hasDiabetes = preg_match('/\b(diabetes|diabetic|high\s+sugar|blood\s+sugar|glucose\s+intolerance)\b/i', $symptomsLower);
            $hasSeverePain = preg_match('/\b(severe\s+pain|excruciating|unbearable\s+pain|intense\s+pain)\b/i', $symptomsLower);
            $hasWeightLossPanc = preg_match('/\b(weight\s+loss|losing\s+weight|malnutrition)\b/i', $symptomsLower);
            
            // Chronic pancreatitis without cardinal features is unlikely
            if (!$hasBackPain && !$hasAlcoholHistory && !$hasSteatorrheaPanc && !$hasDiabetes && !$hasWeightLossPanc) {
                $diseaseScores[$id] -= 600; // Block - epigastric discomfort alone is NOT chronic pancreatitis
                error_log("Diagnosis - BLOCKED Chronic Pancreatitis: NO pancreatic symptoms (back pain, alcohol, steatorrhea, diabetes, weight loss)");
            }
            // Simple acid/reflux pattern = GERD, not pancreatitis
            $hasAcidRefluxPattern = preg_match('/\b(acid|reflux|heartburn|antacid|sour\s+taste|belching)\b/i', $symptomsLower);
            if ($hasAcidRefluxPattern && !$hasBackPain && !$hasAlcoholHistory) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED Chronic Pancreatitis: Acid/reflux pattern without back pain or alcohol = GERD");
            }
        }
        
        // INFLAMMATORY BOWEL DISEASE (IBD: Crohn's, Ulcerative Colitis) - MUST have bowel symptoms
        if (stripos($diseaseName, 'crohn') !== false || 
            stripos($diseaseName, 'ulcerative colitis') !== false ||
            stripos($diseaseName, 'inflammatory bowel') !== false) {
            
            $hasBloodyDiarrhea = preg_match('/\b(bloody\s+diarrhea|blood\s+in\s+stool|hematochezia|mucus\s+in\s+stool)\b/i', $symptomsLower);
            $hasChronicDiarrheaIBD = preg_match('/\b(chronic\s+diarrhea|persistent\s+diarrhea|frequent\s+stools|loose\s+stools)\b/i', $symptomsLower);
            $hasAbdominalCramps = preg_match('/\b(abdominal\s+cramps?|cramping|colicky|colic)\b/i', $symptomsLower);
            $hasWeightLossIBD = preg_match('/\b(weight\s+loss|malnutrition)\b/i', $symptomsLower);
            $hasFistula = preg_match('/\b(fistula|abscess|perianal)\b/i', $symptomsLower);
            $hasUpperGISymptoms = preg_match('/\b(epigastric|burning|reflux|heartburn|acid|belching|eructation)\b/i', $symptomsLower);
            
            if (!$hasBloodyDiarrhea && !$hasChronicDiarrheaIBD && !$hasFistula && !$hasWeightLossIBD) {
                $diseaseScores[$id] -= 600;
                error_log("Diagnosis - BLOCKED IBD: NO bowel symptoms (bloody diarrhea, chronic diarrhea, weight loss)");
            }
            // Upper GI acid symptoms are NOT IBD
            if ($hasUpperGISymptoms && !$hasBloodyDiarrhea && !$hasChronicDiarrheaIBD) {
                $diseaseScores[$id] -= 400;
                error_log("Diagnosis - BLOCKED IBD: Upper GI symptoms without bowel involvement");
            }
        }
        
        // 4. If fungal infection is confirmed by labs, BOOST fungal diseases and PENALIZE non-fungal
        if ($fungalConfirmed) {
            if (strpos(strtolower($diseaseName), 'tinea') !== false || 
                strpos(strtolower($diseaseName), 'ringworm') !== false ||
                strpos(strtolower($diseaseName), 'fungal') !== false) {
                $diseaseScores[$id] += 100; // Massive boost for fungal diseases when labs confirm
                error_log("Diagnosis - Fungal lab confirmation bonus +100 for $diseaseName");
            } else if ($diseaseCategory !== 'dermatological' && $diseaseCategory !== 'infectious') {
                $diseaseScores[$id] -= 80; // Penalize non-skin/infectious diseases when fungal confirmed
                error_log("Diagnosis - Non-fungal penalty -80 for $diseaseName (fungal labs positive)");
            }
        }
        
        // 5. Apply pathognomonic bonus scores
        if (isset($pathognomonicBonus[$diseaseName])) {
            $diseaseScores[$id] += $pathognomonicBonus[$diseaseName];
            error_log("Diagnosis - Applied pathognomonic bonus of {$pathognomonicBonus[$diseaseName]} to $diseaseName");
        }
        
        // 6. PENALIZE musculoskeletal diseases when fever+cough present without joint symptoms
        $hasFever = strpos($symptomsLower, 'fever') !== false;
        $hasCough = strpos($symptomsLower, 'cough') !== false;
        $hasJointSymptom = preg_match('/\b(joint|arthritis|stiff|swelling|symmetric|hand|finger|wrist|knee)\b/i', $symptomsLower);
        
        if ($hasFever && $hasCough && !$hasJointSymptom && $diseaseCategory === 'musculoskeletal') {
            $diseaseScores[$id] -= 100; // Penalize - likely respiratory infection, not joint disease
            error_log("Diagnosis - Musculoskeletal penalty -100 for $diseaseName (fever+cough without joint symptoms)");
        }
        
        // 7. BOOST infectious/respiratory diseases for simple fever+cough presentation
        if ($hasFever && $hasCough && !$hasJointSymptom) {
            if ($diseaseCategory === 'infectious' || $diseaseCategory === 'respiratory') {
                $diseaseScores[$id] += 30; // Boost respiratory/infectious diseases
                error_log("Diagnosis - Fever+cough bonus +30 for $diseaseName (category: $diseaseCategory)");
            }
        }
        
        // 8. CRITICAL: CHRONICITY-BASED PENALTY for emergency/acute conditions
        // When symptoms are CHRONIC (>1 month), penalize acute/emergency diagnoses heavily
        if ($isChronicPresentation) {
            $acuteEmergencyConditions = [
                // Cardiovascular emergencies - require SUDDEN onset
                'Acute Myocardial Infarction' => -250,
                'Myocardial Infarction' => -250,
                'Heart Attack' => -250,
                'Unstable Angina' => -200,
                'Aortic Dissection' => -250,
                'Cardiac Tamponade' => -250,
                'Acute Heart Failure' => -200,
                
                // Stroke - SUDDEN onset is key diagnostic criteria
                'Ischemic Stroke' => -250,
                'Hemorrhagic Stroke' => -250,
                'Stroke' => -250,
                'Transient Ischemic Attack' => -200,
                'TIA' => -200,
                
                // Other acute emergencies
                'Pulmonary Embolism' => -200,
                'Acute Appendicitis' => -200,
                'Appendicitis' => -200,
                'Acute Pancreatitis' => -180,
                'Meningitis' => -200,
                'Acute Meningitis' => -200,
                'Subarachnoid Hemorrhage' => -250,
                'Anaphylaxis' => -250,
                'Acute Asthma Attack' => -150,
                'Status Epilepticus' => -200,
                'Acute Cholecystitis' => -150,
                'Acute Bowel Obstruction' => -180,
                
                // Acute respiratory
                'Acute Respiratory Distress' => -180,
                'Pneumothorax' => -200,
                
                // Acute infections (these typically don't last months)
                'Acute Bronchitis' => -100,
                'Acute Tonsillitis' => -100,
                'Acute Gastroenteritis' => -100,
            ];
            
            foreach ($acuteEmergencyConditions as $condition => $penalty) {
                if (stripos($diseaseName, $condition) !== false || $diseaseName === $condition) {
                    $diseaseScores[$id] += $penalty;
                    error_log("Diagnosis - CHRONIC PRESENTATION penalty $penalty for $diseaseName (symptoms ongoing >1 month)");
                    break;
                }
            }
            
            // Also check disease urgency_level
            $urgency = $disease['urgency_level'] ?? '';
            if ($urgency === 'emergency' && !isset($pathognomonicBonus[$diseaseName])) {
                $diseaseScores[$id] -= 150;
                error_log("Diagnosis - Emergency urgency penalty -150 for $diseaseName (chronic presentation doesn't match emergency disease)");
            }
        }
        
        // 9. MORNING SYMPTOM PATTERN - Classic for Hypertension
        // Headache + dizziness + other symptoms ESPECIALLY IN THE MORNING suggests hypertension
        $hasMorningPattern = preg_match('/\b(morning|in the morning|on waking|wake up|especially.*morning)\b/i', $symptomsLower);
        $hasHeadache = strpos($symptomsLower, 'headache') !== false;
        $hasDizziness = strpos($symptomsLower, 'dizziness') !== false || strpos($symptomsLower, 'dizzy') !== false;
        $hasNosebleed = strpos($symptomsLower, 'nosebleed') !== false || strpos($symptomsLower, 'epistaxis') !== false;
        $hasBlurredVision = strpos($symptomsLower, 'blurred vision') !== false || strpos($symptomsLower, 'vision problem') !== false;
        $hasChestDiscomfort = strpos($symptomsLower, 'chest discomfort') !== false || strpos($symptomsLower, 'chest pain') !== false;
        
        // Hypertension pattern: morning headache + dizziness + any of nosebleed/blurred vision/chest discomfort
        $hypertensionSymptomCount = ($hasHeadache ? 1 : 0) + ($hasDizziness ? 1 : 0) + 
                                   ($hasNosebleed ? 1 : 0) + ($hasBlurredVision ? 1 : 0) + 
                                   ($hasChestDiscomfort ? 1 : 0);
        
        if (stripos($diseaseName, 'Hypertension') !== false || stripos($diseaseName, 'High Blood Pressure') !== false) {
            // Boost hypertension if morning pattern present
            if ($hasMorningPattern && $hasHeadache) {
                $diseaseScores[$id] += 150;
                error_log("Diagnosis - MORNING HEADACHE bonus +150 for $diseaseName (classic hypertension presentation)");
            }
            // Also boost if multiple hypertension symptoms present
            if ($hypertensionSymptomCount >= 3) {
                $diseaseScores[$id] += 100;
                error_log("Diagnosis - Multiple hypertension symptoms bonus +100 for $diseaseName ($hypertensionSymptomCount symptoms)");
            }
            // Chronic presentation strongly supports hypertension
            if ($isChronicPresentation) {
                $diseaseScores[$id] += 80;
                error_log("Diagnosis - Chronic presentation bonus +80 for $diseaseName (hypertension is typically chronic)");
            }
        }
        
        // 10. PENALIZE acute cardiovascular conditions when hypertension symptoms are present
        // If pattern looks like hypertension (morning headache, chronic, nosebleeds), reduce MI/Stroke scores
        if ($hasMorningPattern && $hasHeadache && $isChronicPresentation) {
            $cvAcuteConditions = ['Acute Myocardial Infarction', 'Ischemic Stroke', 'Hemorrhagic Stroke'];
            if (in_array($diseaseName, $cvAcuteConditions)) {
                $diseaseScores[$id] -= 100;
                error_log("Diagnosis - Hypertension pattern penalty -100 for $diseaseName (presentation suggests chronic hypertension, not acute event)");
            }
        }
        
        // 11. CRITICAL: REQUIRED SYMPTOMS CHECK
        // Some diseases MUST have specific symptoms present, otherwise heavily penalize
        $requiredSymptomsCriteria = getRequiredSymptoms($diseaseName);
        if ($requiredSymptomsCriteria) {
            $hasRequired = hasRequiredSymptoms($symptoms, $requiredSymptomsCriteria['any_of']);
            if (!$hasRequired) {
                $penalty = $requiredSymptomsCriteria['penalty_if_absent'];
                $diseaseScores[$id] += $penalty;
                error_log("Diagnosis - REQUIRED SYMPTOMS ABSENT: penalty $penalty for $diseaseName (missing: " . implode('/', $requiredSymptomsCriteria['any_of']) . ")");
            }
        }
        
        // 12. CRITICAL: ORGAN SYSTEM MISMATCH PENALTY
        // If symptoms clearly point to GI system, penalize urinary/respiratory diseases heavily
        $primaryOrgan = $organSystemInfo['primary'] ?? null;
        
        if ($primaryOrgan === 'gastrointestinal') {
            // GI symptoms present - penalize unrelated organ system diseases
            $unrelatedForGI = [
                'urinary tract infection' => -300,
                'uti' => -300,
                'cystitis' => -300,
                'pyelonephritis' => -280,
                'pneumonia' => -200,
                'bronchitis' => -200,
                'asthma' => -200,
                'stroke' => -250,
                'myocardial infarction' => -250,
            ];
            
            foreach ($unrelatedForGI as $unrelated => $penalty) {
                if (stripos($diseaseName, $unrelated) !== false) {
                    $diseaseScores[$id] += $penalty;
                    error_log("Diagnosis - ORGAN SYSTEM MISMATCH: penalty $penalty for $diseaseName (GI symptoms, not urinary/respiratory)");
                }
            }
            
            // 12b. UPPER GI vs LOWER GI differentiation
            // Upper GI: epigastric, stomach, gastric, esophageal, acid, reflux
            // Lower GI: colon, rectum, diarrhea, bloody stool, hematochezia
            $hasUpperGI = preg_match('/\b(epigastric|stomach|gastric|esophag|upper\s+abdomen|burning|acid|reflux|belching|heartburn)\b/i', $symptomsLower);
            $hasLowerGI = preg_match('/\b(colon|rectal|rectum|bloody\s+stool|hematochezia|diarrhea|lower\s+abdomen|mucus\s+in\s+stool)\b/i', $symptomsLower);
            
            // If upper GI symptoms and NO lower GI symptoms, penalize lower GI diseases
            if ($hasUpperGI && !$hasLowerGI) {
                $lowerGIDiseases = [
                    'ulcerative colitis' => -300,
                    'crohn' => -250,
                    'irritable bowel' => -200,
                    'ibs' => -200,
                    'colitis' => -250,
                    'diverticulitis' => -200,
                    'colorectal' => -200,
                    'rectal' => -200,
                    'hemorrhoid' => -200,
                    'proctitis' => -200,
                ];
                
                foreach ($lowerGIDiseases as $lowerGI => $penalty) {
                    if (stripos($diseaseName, $lowerGI) !== false) {
                        $diseaseScores[$id] += $penalty;
                        error_log("Diagnosis - UPPER/LOWER GI MISMATCH: penalty $penalty for $diseaseName (upper GI symptoms, not lower GI)");
                    }
                }
            }
        }
        
        if ($primaryOrgan === 'urinary') {
            // Urinary symptoms present - penalize GI diseases
            $unrelatedForUrinary = [
                'gastritis' => -200,
                'peptic ulcer' => -200,
                'gerd' => -200,
                'pancreatitis' => -200,
            ];
            
            foreach ($unrelatedForUrinary as $unrelated => $penalty) {
                if (stripos($diseaseName, $unrelated) !== false) {
                    $diseaseScores[$id] += $penalty;
                    error_log("Diagnosis - ORGAN SYSTEM MISMATCH: penalty $penalty for $diseaseName (urinary symptoms, not GI)");
                }
            }
        }
        
        // 13. CRITICAL: MODALITY VS CAUSATION - "worse with alcohol" ≠ "alcoholic disease"
        // If alcohol is mentioned as an AGGRAVATING FACTOR (modality), NOT as chronic use/history
        $alcoholIsModality = in_array('alcohol', $modalities['aggravating']);
        $hasAlcoholHistory = preg_match('/\b(alcoholic|alcoholism|heavy\s+drink|chronic\s+alcohol|history\s+of\s+alcohol|alcohol\s+abuse)\b/i', $symptoms);
        
        if ($alcoholIsModality && !$hasAlcoholHistory) {
            // Alcohol is just making symptoms worse, not causing the disease
            $alcoholCausedDiseases = [
                'alcohol-related liver' => -250,
                'alcoholic liver' => -250,
                'alcoholic hepatitis' => -250,
                'alcoholic cirrhosis' => -250,
                'alcoholic fatty liver' => -250,
                'alcohol use disorder' => -200,
                'alcoholism' => -200,
            ];
            
            foreach ($alcoholCausedDiseases as $alcoholDisease => $penalty) {
                if (stripos($diseaseName, $alcoholDisease) !== false) {
                    $diseaseScores[$id] += $penalty;
                    error_log("Diagnosis - MODALITY NOT CAUSATION: penalty $penalty for $diseaseName (alcohol is aggravating factor, not chronic use)");
                }
            }
        }
        
        // 14. CRITICAL: GI PATTERN RECOGNITION - Classic Gastritis/PUD/GERD patterns
        // Burning epigastric pain + worse empty stomach/spicy food/NSAIDs + nausea/bloating = Gastritis/PUD
        $hasEpigastricPain = preg_match('/\b(epigastric|upper\s+abdomen|stomach|gastric)\b/i', $symptomsLower) && 
                            preg_match('/\b(pain|burning|discomfort)\b/i', $symptomsLower);
        $hasBurningPain = strpos($symptomsLower, 'burning') !== false;
        $worseEmptyStomach = preg_match('/(?:worse|worsens?)\s+(?:on|with)?\s*(?:an?\s+)?empty\s+stomach/i', $symptomsLower);
        $worseSpicyFood = preg_match('/(?:worse|worsens?|after)\s+(?:eating\s+)?spicy/i', $symptomsLower);
        $worseNSAIDs = preg_match('/(?:worse|worsens?)\s+(?:with|after)?\s*nsaids?/i', $symptomsLower);
        $hasNausea = strpos($symptomsLower, 'nausea') !== false;
        $hasBloating = strpos($symptomsLower, 'bloating') !== false;
        $hasBelching = strpos($symptomsLower, 'belching') !== false || strpos($symptomsLower, 'eructation') !== false;
        $hasLossOfAppetite = preg_match('/loss\s+of\s+appetite|poor\s+appetite|anorexia/i', $symptomsLower);
        $hasEarlySatiety = preg_match('/fullness\s+after\s+(?:small\s+)?meals?|early\s+satiety/i', $symptomsLower);
        
        // Count GI pattern matches
        $giPatternCount = ($hasEpigastricPain ? 2 : 0) + ($hasBurningPain ? 1 : 0) + 
                         ($worseEmptyStomach ? 2 : 0) + ($worseSpicyFood ? 1 : 0) + 
                         ($worseNSAIDs ? 1 : 0) + ($hasNausea ? 1 : 0) + 
                         ($hasBloating ? 1 : 0) + ($hasBelching ? 1 : 0) +
                         ($hasLossOfAppetite ? 1 : 0) + ($hasEarlySatiety ? 1 : 0);
        
        // BOOST Gastritis and PUD if classic pattern present
        if ($giPatternCount >= 4) {
            $giDiseases = [
                'gastritis' => 200,
                'acute gastritis' => 200,
                'chronic gastritis' => 200,
                'peptic ulcer' => 180,
                'gastric ulcer' => 180,
                'duodenal ulcer' => 180,
                'dyspepsia' => 150,
                'functional dyspepsia' => 150,
                'gerd' => 120,
                'gastroesophageal reflux' => 120,
            ];
            
            foreach ($giDiseases as $giDisease => $bonus) {
                if (stripos($diseaseName, $giDisease) !== false) {
                    $diseaseScores[$id] += $bonus;
                    error_log("Diagnosis - GI PATTERN MATCH: bonus +$bonus for $diseaseName (matched $giPatternCount GI symptoms)");
                    break;
                }
            }
            
            // PENALIZE non-GI diseases when classic GI pattern is present
            $nonGIDiseases = [
                'urinary tract infection' => -300,
                'nafld' => -150,
                'non-alcoholic fatty liver' => -150,
                'alcohol-related liver' => -200,
            ];
            
            foreach ($nonGIDiseases as $nonGI => $penalty) {
                if (stripos($diseaseName, $nonGI) !== false) {
                    $diseaseScores[$id] += $penalty;
                    error_log("Diagnosis - GI PATTERN: penalty $penalty for $diseaseName (classic GI pattern, not this disease)");
                }
            }
        }
        
        // 15. SPECIFIC: If "worse after eating" + "epigastric" + "burning" = strong Gastritis indicator
        $worseAfterEating = preg_match('/(?:worse|worsens?)\s+(?:after|following)\s+(?:eating|meals?|food)/i', $symptomsLower);
        if ($hasEpigastricPain && $hasBurningPain && ($worseAfterEating || $worseSpicyFood)) {
            if (stripos($diseaseName, 'gastritis') !== false || stripos($diseaseName, 'peptic ulcer') !== false) {
                $diseaseScores[$id] += 100;
                error_log("Diagnosis - TEXTBOOK GASTRITIS/PUD: bonus +100 for $diseaseName (burning epigastric + worse after eating/spicy)");
            }
        }
        
        // 16. DYSPEPSIA BOOST - Stress-related functional GI symptoms
        // Dyspepsia is VERY common with: stress + bloating + belching + irregular meals + no alarming features
        $hasStress = preg_match('/\b(stress|anxiety|tense|work\s*pressure|overthink|worried)\b/i', $symptomsLower);
        $hasIrregularMeals = preg_match('/\b(irregular\s+meal|skip\s*meal|meal\s*timing|eating\s*outside|fast\s*food)\b/i', $symptomsLower);
        $hasNoAlarmingFeatures = !preg_match('/\b(blood|melena|hematemesis|weight\s*loss|anemia|vomiting\s*blood)\b/i', $symptomsLower);
        
        if (stripos($diseaseName, 'dyspepsia') !== false || stripos($diseaseName, 'indigestion') !== false) {
            $dyspepsiaBoost = 0;
            if ($hasStress) $dyspepsiaBoost += 100;
            if ($hasIrregularMeals) $dyspepsiaBoost += 80;
            if ($hasBelching) $dyspepsiaBoost += 60;
            if ($hasBloating) $dyspepsiaBoost += 60;
            if ($hasNoAlarmingFeatures) $dyspepsiaBoost += 40;
            if ($hasEarlySatiety) $dyspepsiaBoost += 50;
            
            if ($dyspepsiaBoost > 0) {
                $diseaseScores[$id] += $dyspepsiaBoost;
                error_log("Diagnosis - DYSPEPSIA BOOST: +$dyspepsiaBoost (stress=$hasStress, irregular=$hasIrregularMeals, belching=$hasBelching)");
            }
        }
        
        // 17. GASTRITIS vs PEPTIC ULCER DIFFERENTIATION
        // Peptic Ulcer: night pain, relieved by eating/antacids, more localized, may have complications history
        // Gastritis: burning, worse after eating, more diffuse discomfort
        
        $hasNightPain = preg_match('/\b(night|nocturnal|wake\s*up|waking\s*up|sleep).*pain/i', $symptomsLower) ||
                        preg_match('/\bpain.*(night|nocturnal|sleep)/i', $symptomsLower);
        $relievedByFood = preg_match('/(?:better|relief|improve)\s+(?:after|with|by)\s+(?:eating|food|meal)/i', $symptomsLower);
        $relievedByAntacid = preg_match('/(?:better|relief|improve)\s+(?:after|with|by)\s+(?:antacid|ppi|omeprazole)/i', $symptomsLower);
        $hasComplicationHistory = preg_match('/\b(ulcer\s*history|previous\s*ulcer|bleed|perforation|h\s*pylori)\b/i', $symptomsLower);
        
        // Ulcer-specific pattern: night pain + relief with food/antacids
        if (stripos($diseaseName, 'peptic ulcer') !== false || stripos($diseaseName, 'duodenal ulcer') !== false || stripos($diseaseName, 'gastric ulcer') !== false) {
            if ($hasNightPain) {
                $diseaseScores[$id] += 80;
                error_log("Diagnosis - Ulcer night pain bonus +80 for $diseaseName");
            }
            if ($relievedByFood || $relievedByAntacid) {
                $diseaseScores[$id] += 60;
                error_log("Diagnosis - Ulcer relief pattern bonus +60 for $diseaseName");
            }
            if ($hasComplicationHistory) {
                $diseaseScores[$id] += 100;
                error_log("Diagnosis - Ulcer history bonus +100 for $diseaseName");
            }
        }
        
        // Gastritis-specific pattern: burning + worse after eating (especially spicy/oily)
        if (stripos($diseaseName, 'gastritis') !== false) {
            if ($worseAfterEating && $hasBurningPain) {
                $diseaseScores[$id] += 80;
                error_log("Diagnosis - Gastritis classic pattern bonus +80");
            }
            // Acute vs Chronic: if duration > 3 months, prefer chronic gastritis
            if ($isChronicPresentation && stripos($diseaseName, 'acute') !== false) {
                $diseaseScores[$id] -= 100;
                error_log("Diagnosis - Acute gastritis penalty -100 for chronic presentation");
            } else if ($isChronicPresentation && stripos($diseaseName, 'chronic') !== false) {
                $diseaseScores[$id] += 80;
                error_log("Diagnosis - Chronic gastritis boost +80 for chronic presentation");
            }
        }
        
        // 18. REFLUX-SPECIFIC PATTERN (GERD)
        // Key: heartburn, acid regurgitation, worse lying down, better sitting upright
        $hasReflux = preg_match('/\b(reflux|regurgitation|acid\s+comes\s+up|sour\s+taste)\b/i', $symptomsLower);
        $worseLyingDown = preg_match('/(?:worse|worsens?)\s+(?:lying|recumbent|sleep|night)/i', $symptomsLower);
        $betterUpright = preg_match('/(?:better|improve)\s+(?:sitting|upright|standing)/i', $symptomsLower);
        $hasHeartburn = strpos($symptomsLower, 'heartburn') !== false;
        
        if (stripos($diseaseName, 'gerd') !== false || stripos($diseaseName, 'gastroesophageal reflux') !== false) {
            if ($hasReflux) $diseaseScores[$id] += 100;
            if ($worseLyingDown) $diseaseScores[$id] += 80;
            if ($betterUpright) $diseaseScores[$id] += 60;
            if ($hasHeartburn) $diseaseScores[$id] += 80;
            error_log("Diagnosis - GERD pattern: reflux=$hasReflux, lying=$worseLyingDown, upright=$betterUpright");
        }
    }
    
    // Remove diseases with negative or very low scores (likely irrelevant)
    // FIX: Also require at least 2 distinct matched symptoms OR a disease-name match
    //      to avoid single-keyword false positives flooding the top-5.
    $diseaseScoresFiltered = [];
    foreach ($diseaseScores as $id => $score) {
        if ($score <= 10) continue;
        $matchCount = isset($diseaseMatches[$id]['matched_symptoms'])
            ? count(array_unique($diseaseMatches[$id]['matched_symptoms']))
            : 0;
        $hadDiseaseNameMatch = isset($diseaseMatches[$id]['match_locations'])
            && in_array('disease_name', $diseaseMatches[$id]['match_locations'], true);
        // Minimum: 2 matched keywords OR a direct disease-name hit OR score>=100
        if ($matchCount < 2 && !$hadDiseaseNameMatch && $score < 100) {
            error_log("Diagnosis - Filtered low-evidence match (score=$score, matches=$matchCount): "
                . ($diseaseMatches[$id]['disease']['disease_name'] ?? 'unknown'));
            continue;
        }
        $diseaseScoresFiltered[$id] = $score;
    }
    $diseaseScores = $diseaseScoresFiltered;

    error_log("Diagnosis - Found " . count($diseaseScores) . " diseases after filtering");

    // Sort by score
    arsort($diseaseScores);
    
    // Calculate confidence levels and build results
    $maxScore = max($diseaseScores ?: [1]);
    $results = [];
    $count = 0;
    
    foreach ($diseaseScores as $id => $score) {
        if ($count >= 5) break;
        
        $match = $diseaseMatches[$id];
        $disease = $match['disease'];
        
        // Calculate confidence percentage
        $confidence = calculateConfidence($score, $maxScore, $count, count($match['matched_symptoms']));
        $confidenceLevel = getConfidenceLevel($confidence);
        
        // Build supporting findings
        $supportingFindings = buildSupportingFindings($disease, $match);

        // Ensure matching_symptoms is a proper indexed array
        // FIX: Filter out noisy/generic tokens so the clinician only sees meaningful matches.
        $matchedSymptoms = array_values(array_unique($match['matched_symptoms']));
        $matchedSymptoms = filterDisplayableSymptoms($matchedSymptoms);
        
        // Get homeopathic remedies for this disease
        $remedies = getRemediesForDisease($id);
        
        $results[] = [
            'diagnosis' => $disease['disease_name'],
            'icd_code' => $disease['icd_code'] ?? '',
            'category' => $disease['category'] ?? '',
            'confidence' => $confidence,
            'confidence_level' => $confidenceLevel,
            'matching_symptoms' => $matchedSymptoms,
            'supporting_findings' => $supportingFindings,
            'severity' => $disease['severity_level'] ?? 'moderate',
            'urgency' => $disease['urgency_level'] ?? 'routine',
            'differential_diagnosis' => $disease['differential_diagnosis'] ?? '',
            'recommended_tests' => $disease['lab_tests'] ?? '',
            'typical_onset' => $disease['typical_onset'] ?? '',
            'notes_for_doctor' => buildDoctorNotes($disease, $match, $severity),
            'homeopathic_remedies' => $remedies
        ];
        
        $count++;
    }
    
    // Generate analysis
    $analysis = generateAnalysis($results, $keywords, $duration, $severity);
    
    return [
        'diagnoses' => $results,
        'analysis' => $analysis,
        'matched_keywords' => $keywords,
        'total_diseases_searched' => count($diseaseScores),
        'disclaimer' => 'This is a diagnostic SUGGESTION tool based on database matching. It is NOT a final medical decision. Clinical correlation is essential.'
    ];
}

/**
 * Extract diagnostic keywords from symptom text
 */
function extractDiagnosticKeywords($text) {
    $text = strtolower($text);
    $keywords = [];
    
    // Medical symptom synonyms mapping
    $symptomMappings = [
        // Fever related
        'fever' => ['fever', 'pyrexia', 'febrile', 'temperature'],
        'high fever' => ['high fever', 'high temperature', 'pyrexia'],
        'chills' => ['chills', 'rigors', 'shivering'],
        'step-ladder fever' => ['step-ladder fever', 'step ladder', 'stepladder fever', 'gradually increasing fever'],
        
        // Pain related
        'headache' => ['headache', 'head pain', 'cephalalgia'],
        'throbbing headache' => ['throbbing headache', 'throbbing pain', 'pulsating headache'],
        'unilateral headache' => ['unilateral headache', 'one-sided headache', 'one sided headache', 'right side headache', 'left side headache'],
        'chest pain' => ['chest pain', 'angina', 'chest discomfort'],
        'abdominal pain' => ['abdominal pain', 'stomach pain', 'belly pain', 'tummy pain', 'abdominal discomfort'],
        'right lower quadrant' => ['right lower quadrant', 'rlq', 'right iliac', 'appendicitis area'],
        'back pain' => ['back pain', 'backache', 'lumbar pain'],
        'sore throat' => ['sore throat', 'throat pain', 'pharyngitis'],
        'joint pain' => ['joint pain', 'arthralgia', 'joints ache', 'painful joint'],
        'body aches' => ['body aches', 'body pain', 'myalgia', 'muscle aches', 'muscle pain', 'aching all over'],
        
        // Gout specific
        'big toe pain' => ['big toe', 'pain in big toe', 'toe pain', 'hallux', 'first mtp', 'metatarsophalangeal'],
        'uric acid' => ['uric acid', 'urate', 'hyperuricemia'],
        'gout' => ['gout', 'gouty', 'podagra'],
        'red swollen joint' => ['red swollen joint', 'swollen joint', 'red hot joint', 'hot swollen'],
        'nocturnal pain' => ['nocturnal', 'night pain', 'wakes at night', 'pain at night', 'started at night'],
        
        // Migraine specific
        'photophobia' => ['photophobia', 'light sensitivity', 'cannot tolerate light', 'sensitive to light'],
        'phonophobia' => ['phonophobia', 'sound sensitivity', 'cannot tolerate sound', 'sensitive to sound'],
        'visual aura' => ['visual aura', 'aura', 'visual disturbance', 'zigzag lines', 'blind spots'],
        'migraine' => ['migraine'],
        
        // Typhoid specific
        'rose spots' => ['rose spots', 'rose-colored spots', 'salmon-colored spots'],
        'relative bradycardia' => ['relative bradycardia', 'slow heart rate with fever'],
        'coated tongue' => ['coated tongue', 'white tongue', 'furred tongue'],
        'typhoid' => ['typhoid', 'enteric fever'],
        'splenomegaly' => ['splenomegaly', 'enlarged spleen', 'soft splenomegaly'],
        'widal' => ['widal', 'widal test'],
        
        // Influenza specific
        'flu' => ['flu', 'influenza'],
        'extreme fatigue' => ['extreme fatigue', 'exhausted', 'very tired', 'extremely weak', 'extreme weakness'],
        'myalgia' => ['myalgia', 'muscle aches', 'body aches', 'severe body aches'],
        'dry cough' => ['dry cough', 'non-productive cough', 'persistent cough'],
        
        // Respiratory
        'cough' => ['cough', 'tussis'],
        'shortness of breath' => ['shortness of breath', 'breathlessness', 'dyspnea', 'sob', 'difficulty breathing'],
        'wheezing' => ['wheezing', 'whistling breath'],
        'runny nose' => ['runny nose', 'rhinorrhea', 'nasal discharge'],
        
        // GI symptoms
        'nausea' => ['nausea', 'queasy', 'sick feeling'],
        'vomiting' => ['vomiting', 'vomit', 'throwing up', 'emesis'],
        'diarrhea' => ['diarrhea', 'loose motion', 'loose stool', 'watery stool'],
        'constipation' => ['constipation', 'hard stool'],
        'jaundice' => ['jaundice', 'yellow skin', 'yellow eyes', 'icterus'],
        
        // Urinary
        'painful urination' => ['painful urination', 'dysuria', 'burning urination', 'burning micturition'],
        'frequent urination' => ['frequent urination', 'urinary frequency', 'polyuria'],
        'blood in urine' => ['blood in urine', 'hematuria'],
        
        // Neurological
        'confusion' => ['confusion', 'disorientation', 'altered mental status'],
        'stiff neck' => ['stiff neck', 'neck stiffness', 'nuchal rigidity'],
        'weakness' => ['weakness', 'weak', 'paresis'],
        'numbness' => ['numbness', 'tingling', 'paresthesia'],
        'dizziness' => ['dizziness', 'dizzy', 'vertigo', 'lightheaded'],
        'seizure' => ['seizure', 'convulsion', 'fits'],
        
        // General
        'fatigue' => ['fatigue', 'tired', 'exhaustion', 'lethargy'],
        'weight loss' => ['weight loss', 'losing weight'],
        'loss of appetite' => ['loss of appetite', 'anorexia', 'poor appetite', 'not hungry'],
        'night sweats' => ['night sweats', 'sweating at night'],
        'rash' => ['rash', 'eruption', 'skin lesion'],
        'swelling' => ['swelling', 'edema', 'swollen'],
        
        // Ringworm/Fungal specific
        'circular patch' => ['circular patch', 'circular patches', 'round patch', 'ring-shaped', 'ring shaped', 'annular'],
        'central clearing' => ['central clearing', 'clear center', 'clearer center', 'clearing center'],
        'ringworm' => ['ringworm', 'tinea', 'dermatophyte', 'fungal infection', 'fungal skin'],
        'scaly border' => ['scaly border', 'raised border', 'raised scaly', 'scaly edges'],
        'spreading outward' => ['spreading outward', 'spreading', 'enlarging', 'increasing in size'],
        'satellite lesions' => ['satellite lesions', 'satellite patches', 'daughter lesions'],
        'koh positive' => ['koh positive', 'koh preparation', 'fungal hyphae'],
        'wood lamp' => ['wood lamp', 'fluorescence'],
        
        // Specific clinical signs
        'tenderness' => ['tenderness', 'tender', 'painful to touch'],
        'rebound tenderness' => ['rebound tenderness', 'rebound'],
        'guarding' => ['guarding', 'muscle guarding', 'abdominal guarding'],
        'mcburney' => ['mcburney', 'mcburney point', 'mcburney\'s'],
        'rovsing' => ['rovsing', 'rovsing sign'],
        
        // Body parts
        'right lower' => ['right lower', 'rlq', 'right iliac fossa'],
        'left lower' => ['left lower', 'llq', 'left iliac fossa'],
        'right upper' => ['right upper', 'ruq'],
        'left upper' => ['left upper', 'luq'],
        'periumbilical' => ['periumbilical', 'around navel', 'around umbilicus', 'near navel'],
        
        // Cardiovascular
        'palpitations' => ['palpitations', 'heart racing', 'irregular heartbeat'],
        'chest tightness' => ['chest tightness', 'tight chest', 'pressure in chest'],
        'radiating pain' => ['radiating', 'radiates to', 'spreading to'],
        
        // Bleeding
        'bleeding' => ['bleeding', 'blood', 'hemorrhage'],
        'petechiae' => ['petechiae', 'petechial', 'spots on skin'],
    ];
    
    // Check for symptom patterns
    foreach ($symptomMappings as $standard => $variants) {
        foreach ($variants as $variant) {
            if (strpos($text, $variant) !== false) {
                $keywords[] = $standard;
                break;
            }
        }
    }
    
    // Also extract individual meaningful words
    // FIX: Expanded stop-word list to cover meta/form-field words that previously
    // leaked into keyword extraction (e.g., "history", "exercise", "pattern").
    $stopWords = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
        'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare',
        'and', 'or', 'but', 'if', 'then', 'else', 'when', 'where', 'why',
        'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other',
        'some', 'such', 'no', 'nor', 'not', 'only', 'same', 'so', 'than',
        'too', 'very', 'just', 'also', 'now', 'here', 'there', 'these', 'those',
        'this', 'that', 'with', 'from', 'for', 'on', 'in', 'at', 'by', 'about',
        'into', 'through', 'during', 'before', 'after', 'above', 'below',
        'up', 'down', 'out', 'off', 'over', 'under', 'again', 'further',
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'you', 'your',
        'he', 'him', 'his', 'she', 'her', 'hers', 'it', 'its', 'they', 'them',
        'patient', 'feeling', 'feels', 'having', 'since', 'days', 'day', 'weeks',
        'week', 'months', 'month', 'ago', 'last', 'started', 'experiencing',
        'complains', 'complaints', 'mild', 'moderate', 'worsens',
        'movement', 'position', 'positive', 'negative', 'point',
        // Meta / form-field words that polluted keyword extraction
        'history', 'medical', 'surgical', 'family', 'allergies', 'medications',
        'exercise', 'diet', 'addiction', 'addictions', 'occupation', 'marital',
        'pattern', 'patterns', 'consultation', 'examination', 'observation',
        'general', 'particular', 'causation', 'modality', 'modalities',
        'typically', 'usually', 'sometimes', 'often', 'rarely', 'always',
        'symptom', 'symptoms', 'intensity', 'duration', 'category', 'location',
        'sensation', 'discomfort', 'relief', 'temporary', 'gradually', 'suddenly'
    ];
    
    // Important medical words to ALWAYS include (override stop words)
    $medicalKeywords = [
        'gout', 'migraine', 'typhoid', 'influenza', 'fever', 'pain', 'severe',
        'throbbing', 'nausea', 'vomiting', 'headache', 'cough', 'chills',
        'joint', 'swollen', 'tender', 'red', 'hot', 'toe', 'big', 'uric',
        'step', 'ladder', 'rose', 'spots', 'coated', 'tongue', 'spleen',
        'body', 'aches', 'fatigue', 'extreme', 'dry', 'throat', 'sore',
        'light', 'sound', 'aura', 'visual', 'unilateral', 'one-sided',
        'photophobia', 'phonophobia', 'abdominal', 'constipation', 'diarrhea',
        'widal', 'lower', 'abdomen',
        // Skin/Dermatological keywords
        'circular', 'ring', 'ringworm', 'tinea', 'fungal', 'scaly', 'patches',
        'patch', 'lesion', 'lesions', 'rash', 'itchy', 'itching', 'scaling',
        'border', 'center', 'clearing', 'spreading', 'satellite', 'dermatophyte',
        'koh', 'hyphae', 'wood', 'lamp', 'eruption', 'skin', 'flaky', 'raised',
        'annular', 'central', 'erythematous'
    ];
    
    $words = preg_split('/[\s,\.\-\/]+/', $text);
    foreach ($words as $word) {
        $word = trim($word);
        // Include word if: it's a medical keyword OR (length >= 4 AND not a stop word)
        if (in_array($word, $medicalKeywords) || (strlen($word) >= 4 && !in_array($word, $stopWords))) {
            $keywords[] = $word;
        }
    }
    
    return array_unique($keywords);
}

/**
 * Search using symptom junction table
 */
function searchBySymptomJunction($keywords) {
    $results = [];
    
    foreach ($keywords as $keyword) {
        if (strlen($keyword) < 3) continue;
        
        try {
            $matches = DB::query(
                "SELECT ds.disease_id, ds.symptom_weight, ds.specificity_score, ds.sensitivity_score,
                        d.disease_name, d.icd_code, d.category, d.primary_symptoms, 
                        d.secondary_symptoms, d.clinical_findings, d.lab_tests,
                        d.severity_level, d.urgency_level, d.differential_diagnosis,
                        sm.symptom_name
                 FROM disease_symptoms ds
                 JOIN diseases d ON ds.disease_id = d.id
                 JOIN symptom_master sm ON ds.symptom_id = sm.id
                 WHERE LOWER(sm.symptom_name) LIKE ? 
                    OR LOWER(sm.synonyms) LIKE ?
                 ORDER BY ds.specificity_score DESC
                 LIMIT 20",
                ['%' . $keyword . '%', '%' . $keyword . '%']
            );
            
            if ($matches !== false) {
                $results = array_merge($results, $matches);
            }
        } catch (Exception $e) {
            // Tables may not exist
        }
    }
    
    return $results;
}

/**
 * Calculate confidence percentage
 *
 * FIX: Previous version double-boosted top result (rank bonus + match bonus),
 * producing inflated "High" confidence scores for diseases that only weakly
 * matched. New version:
 *   - Anchors on absolute score (not just ratio to max).
 *   - Only gives rank bonus when the lead is meaningful (score >= 60% of max).
 *   - Gated match bonus: weak single-keyword matches never break 45%.
 *   - Caps at 90% (RAG alone is never definitive).
 */
function calculateConfidence($score, $maxScore, $rank, $matchCount) {
    if ($maxScore <= 0 || $score <= 0) return 10;

    // Base: ratio of this disease's score to the top score
    $ratio = $score / $maxScore;
    $baseConfidence = $ratio * 100;

    // Floor for weak evidence: < 2 matched symptoms is always "Low"
    if ($matchCount < 2 && $score < 40) {
        return max(10, min(30, round($baseConfidence * 0.6)));
    }

    // Rank bonus only applies if result stands out clearly (ratio >= 0.6)
    $rankBonus = 0;
    if ($rank === 0 && $ratio >= 0.6) {
        $rankBonus = 5;
    }

    // Match bonus capped + scaled down
    $matchBonus = min($matchCount * 2, 10);

    // Absolute-score floor penalty: very low raw scores never reach "High"
    $absolutePenalty = 0;
    if ($score < 30) {
        $absolutePenalty = -15;
    } elseif ($score < 60) {
        $absolutePenalty = -5;
    }

    $confidence = $baseConfidence + $rankBonus + $matchBonus + $absolutePenalty;

    // Cap at 90% - never claim higher certainty from RAG alone
    return min(90, max(10, round($confidence)));
}

/**
 * Get confidence level text
 * FIX: Raised "High" threshold from 75 to 78 and "Medium" from 50 to 55
 * so weak matches no longer show green badges.
 */
function getConfidenceLevel($confidence) {
    if ($confidence >= 78) return 'High';
    if ($confidence >= 55) return 'Medium';
    return 'Low';
}

/**
 * Filter out noisy / generic / meta tokens from matched_symptoms before display.
 * FIX: Previously, the output leaked tokens like "history", "medical", "exercise",
 * "pattern" that came from raw word tokenization, confusing clinicians.
 */
function filterDisplayableSymptoms(array $symptoms): array {
    $noisy = [
        'history', 'medical', 'exercise', 'pattern', 'patterns', 'symptom', 'symptoms',
        'patient', 'feeling', 'feels', 'having', 'since', 'started', 'experiencing',
        'complains', 'complaint', 'complaints', 'mild', 'moderate', 'severe', 'worse',
        'worsens', 'better', 'improves', 'chronic', 'acute', 'recent', 'past',
        'present', 'normal', 'daily', 'morning', 'night', 'evening', 'afternoon',
        'lying', 'sitting', 'standing', 'walking', 'movement', 'position',
        'occasional', 'frequent', 'temporary', 'relief', 'discomfort', 'sensation',
        'taking', 'following', 'especially', 'typically', 'gradually'
    ];
    $filtered = [];
    foreach ($symptoms as $s) {
        $s = trim((string)$s);
        if ($s === '' || strlen($s) < 3) continue;
        if (in_array(strtolower($s), $noisy, true)) continue;
        $filtered[] = $s;
    }
    // Deduplicate case-insensitively while preserving order
    $seen = [];
    $out = [];
    foreach ($filtered as $s) {
        $k = strtolower($s);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $s;
    }
    // Cap at 8 most relevant to avoid a noisy UI
    return array_slice($out, 0, 8);
}

/**
 * Build supporting findings
 */
function buildSupportingFindings($disease, $match) {
    $findings = [];
    
    // Add matched locations
    $locations = array_unique($match['match_locations'] ?? []);
    if (in_array('primary_symptoms', $locations)) {
        $findings[] = 'Matches primary disease symptoms';
    }
    if (in_array('warning_signs', $locations)) {
        $findings[] = 'Warning signs present';
    }
    if (in_array('clinical_findings', $locations)) {
        $findings[] = 'Consistent clinical findings';
    }
    
    // Add clinical findings from disease
    if (!empty($disease['clinical_findings'])) {
        $findings[] = 'Expected findings: ' . substr($disease['clinical_findings'], 0, 150);
    }
    
    return implode('; ', $findings);
}

/**
 * Build notes for doctor
 */
function buildDoctorNotes($disease, $match, $severity) {
    $notes = [];
    
    // Urgency warning
    if (($disease['urgency_level'] ?? '') === 'emergency') {
        $notes[] = '⚠️ EMERGENCY: Requires immediate attention';
    } elseif (($disease['urgency_level'] ?? '') === 'urgent') {
        $notes[] = '⚡ URGENT: Prompt evaluation recommended';
    }
    
    // Warning signs
    if (!empty($disease['warning_signs'])) {
        $notes[] = 'Watch for: ' . substr($disease['warning_signs'], 0, 100);
    }
    
    // Recommended tests
    if (!empty($disease['lab_tests'])) {
        $notes[] = 'Consider tests: ' . substr($disease['lab_tests'], 0, 100);
    }
    
    // Differential diagnosis
    if (!empty($disease['differential_diagnosis'])) {
        $notes[] = 'Rule out: ' . substr($disease['differential_diagnosis'], 0, 100);
    }
    
    return implode(' | ', $notes);
}

/**
 * Generate analysis text
 */
function generateAnalysis($results, $keywords, $duration, $severity) {
    if (empty($results)) {
        return 'Unable to determine a confident diagnosis from the provided symptoms.';
    }
    
    $topDiagnosis = $results[0];
    
    $analysis = "Based on analysis of symptoms (" . implode(', ', array_slice($keywords, 0, 5)) . "):\n\n";
    
    $analysis .= "PRIMARY CONSIDERATION: " . $topDiagnosis['diagnosis'];
    $analysis .= " (Confidence: " . $topDiagnosis['confidence_level'] . " - " . $topDiagnosis['confidence'] . "%)\n";
    
    if (count($results) > 1) {
        $analysis .= "\nDIFFERENTIAL DIAGNOSES TO CONSIDER:\n";
        for ($i = 1; $i < min(3, count($results)); $i++) {
            $analysis .= "- " . $results[$i]['diagnosis'] . " (" . $results[$i]['confidence'] . "%)\n";
        }
    }
    
    if (!empty($topDiagnosis['recommended_tests'])) {
        $analysis .= "\nRECOMMENDED INVESTIGATIONS:\n" . $topDiagnosis['recommended_tests'];
    }
    
    return $analysis;
}

/**
 * Get homeopathic remedies for a specific disease
 * Returns remedies from disease_remedies table with full details
 */
function getRemediesForDisease($diseaseId) {
    $remedies = [];
    
    error_log("getRemediesForDisease called with diseaseId: " . $diseaseId);
    
    try {
        // First try the disease_remedies table (most specific)
        // Use GROUP BY to avoid duplicates from multiple remedy entries with same name
        $mappings = DB::query(
            "SELECT dr.indication_strength, dr.specific_indication, dr.potency_recommendation,
                    dr.source_reference, MIN(r.id) as remedy_id, r.remedy_name, r.common_name,
                    r.keynote_symptoms, r.clinical_indications
             FROM disease_remedies dr
             JOIN remedies r ON dr.remedy_id = r.id
             WHERE dr.disease_id = ?
             GROUP BY r.remedy_name, dr.indication_strength
             ORDER BY FIELD(dr.indication_strength, 'primary', 'secondary', 'supportive'), r.remedy_name
             LIMIT 10",
            [$diseaseId]
        );
        
        // Handle query failure
        if ($mappings === false) {
            error_log("getRemediesForDisease - Query failed for disease $diseaseId");
            $mappings = [];
        }
        
        error_log("getRemediesForDisease - found " . count($mappings) . " remedies for disease $diseaseId");
        
        if (!empty($mappings)) {
            foreach ($mappings as $m) {
                $remedies[] = [
                    'remedy_id' => $m['remedy_id'],
                    'remedy_name' => $m['remedy_name'],
                    'common_name' => $m['common_name'] ?? '',
                    'indication_strength' => $m['indication_strength'],
                    'specific_indication' => $m['specific_indication'] ?? '',
                    'potency' => $m['potency_recommendation'] ?? '30C, 200C',
                    'source' => $m['source_reference'] ?? '',
                    'keynotes' => $m['keynote_symptoms'] ? substr($m['keynote_symptoms'], 0, 200) : ''
                ];
            }
            return $remedies;
        }
        
        // Fallback: Search based on disease name in clinical_indications
        $disease = DB::queryOne("SELECT disease_name FROM diseases WHERE id = ?", [$diseaseId]);
        if ($disease) {
            $diseaseName = $disease['disease_name'];
            
            // Search remedies by clinical indications
            $fallbackRemedies = DB::query(
                "SELECT id as remedy_id, remedy_name, common_name, keynote_symptoms, clinical_indications
                 FROM remedies 
                 WHERE LOWER(clinical_indications) LIKE ?
                    OR LOWER(keynote_symptoms) LIKE ?
                 ORDER BY is_popular DESC, remedy_name
                 LIMIT 6",
                ['%' . strtolower($diseaseName) . '%', '%' . strtolower($diseaseName) . '%']
            );
            
            if ($fallbackRemedies !== false) {
                foreach ($fallbackRemedies as $r) {
                    $remedies[] = [
                        'remedy_id' => $r['remedy_id'],
                        'remedy_name' => $r['remedy_name'],
                        'common_name' => $r['common_name'] ?? '',
                        'indication_strength' => 'supportive',
                        'specific_indication' => 'Based on clinical indications match',
                        'potency' => '30C, 200C',
                        'source' => 'Materia Medica',
                        'keynotes' => $r['keynote_symptoms'] ? substr($r['keynote_symptoms'], 0, 200) : ''
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching remedies for disease $diseaseId: " . $e->getMessage());
    }
    
    return $remedies;
}

/**
 * Extract multi-word symptom patterns for better disease matching
 */
function extractSymptomPatterns($text) {
    $text = strtolower($text);
    $patterns = [];
    
    // Multi-word symptom patterns to look for (disease-specific combinations)
    $multiWordPatterns = [
        // Thyroid
        'weight gain' => ['weight gain', 'gaining weight', 'putting on weight'],
        'weight loss' => ['weight loss', 'losing weight', 'lost weight'],
        'cold intolerance' => ['cold intolerance', 'feeling cold', 'cannot tolerate cold', 'sensitive to cold'],
        'heat intolerance' => ['heat intolerance', 'feeling hot', 'cannot tolerate heat', 'sensitive to heat'],
        'hair loss' => ['hair loss', 'losing hair', 'hair falling', 'alopecia'],
        'dry skin' => ['dry skin', 'rough skin', 'skin dryness'],
        'brittle nails' => ['brittle nails', 'nail breakage', 'weak nails'],
        'neck swelling' => ['neck swelling', 'goiter', 'thyroid swelling', 'swelling in neck'],
        
        // Diabetes
        'increased thirst' => ['increased thirst', 'excessive thirst', 'polydipsia', 'always thirsty'],
        'frequent urination' => ['frequent urination', 'polyuria', 'passing urine frequently', 'urinating often'],
        'increased hunger' => ['increased hunger', 'polyphagia', 'excessive hunger', 'always hungry'],
        'blurred vision' => ['blurred vision', 'vision problems', 'difficulty seeing', 'poor vision'],
        'slow healing' => ['slow healing', 'wounds not healing', 'delayed healing'],
        
        // PCOS
        'irregular periods' => ['irregular periods', 'irregular menstruation', 'missed periods', 'menstrual irregularity'],
        'facial hair' => ['facial hair', 'hirsutism', 'excess hair', 'hair on face'],
        'acne' => ['acne', 'pimples', 'breakouts'],
        'difficulty conceiving' => ['difficulty conceiving', 'infertility', 'cannot get pregnant', 'trouble conceiving'],
        
        // Cardiovascular
        'chest pain' => ['chest pain', 'angina', 'chest discomfort', 'pain in chest'],
        'shortness of breath' => ['shortness of breath', 'breathlessness', 'dyspnea', 'difficulty breathing'],
        'radiating to arm' => ['radiating to arm', 'pain in arm', 'left arm pain', 'radiates to left arm'],
        'jaw pain' => ['jaw pain', 'pain in jaw'],
        'cold sweats' => ['cold sweats', 'diaphoresis', 'sweating'],
        
        // Hypertension patterns - CRITICAL for common diagnosis
        'morning headache' => ['morning headache', 'headache in the morning', 'headache in morning', 'headache especially in the morning', 'wakes up with headache', 'headache on waking', 'occipital headache'],
        'nosebleed' => ['nosebleed', 'nosebleeds', 'epistaxis', 'nose bleeding', 'blood from nose'],
        'dizziness' => ['dizziness', 'dizzy', 'lightheaded', 'light headed', 'vertigo', 'spinning'],
        'flushing' => ['flushing', 'facial flushing', 'red face', 'face turns red', 'hot flushes'],
        'palpitations' => ['palpitations', 'heart racing', 'pounding heart', 'rapid heartbeat', 'irregular heartbeat'],
        'chest discomfort' => ['chest discomfort', 'chest heaviness', 'chest tightness', 'pressure in chest'],
        'vision problems' => ['vision problems', 'blurred vision', 'visual disturbance', 'difficulty seeing', 'vision changes'],
        
        // Neurological
        'one-sided headache' => ['one-sided headache', 'unilateral headache', 'one side headache', 'left side headache', 'right side headache'],
        'throbbing pain' => ['throbbing pain', 'pulsating pain', 'throbbing headache'],
        'sensitivity to light' => ['sensitivity to light', 'photophobia', 'light sensitivity', 'cannot tolerate light'],
        'sensitivity to sound' => ['sensitivity to sound', 'phonophobia', 'sound sensitivity', 'cannot tolerate noise'],
        'visual disturbance' => ['visual disturbance', 'aura', 'zigzag lines', 'visual aura', 'seeing spots'],
        'tremor' => ['tremor', 'trembling', 'shaking hands', 'involuntary shaking'],
        'muscle stiffness' => ['muscle stiffness', 'rigidity', 'stiff muscles'],
        
        // GI - CRITICAL patterns for gastritis/PUD/GERD
        'abdominal pain' => ['abdominal pain', 'stomach pain', 'belly pain', 'pain in abdomen'],
        'epigastric pain' => ['epigastric', 'upper abdomen', 'upper abdominal', 'in the upper part of abdomen'],
        'burning pain' => ['burning pain', 'burning sensation', 'burning discomfort', 'gnawing pain'],
        'right lower quadrant' => ['right lower quadrant', 'rlq pain', 'right iliac fossa', 'right lower abdomen'],
        'blood in stool' => ['blood in stool', 'bloody stool', 'rectal bleeding', 'hematochezia'],
        'mucus in stool' => ['mucus in stool', 'mucous diarrhea'],
        'alternating constipation diarrhea' => ['alternating constipation', 'constipation and diarrhea', 'bowel irregularity'],
        'heartburn' => ['heartburn', 'acid reflux', 'burning in chest after eating'],
        'nausea' => ['nausea', 'nauseated', 'feeling sick', 'queasy'],
        'vomiting' => ['vomiting', 'vomited', 'throwing up', 'emesis'],
        'bloating' => ['bloating', 'bloated', 'distended', 'abdominal distension', 'gas'],
        'belching' => ['belching', 'burping', 'eructation', 'passing gas upward'],
        'loss of appetite' => ['loss of appetite', 'poor appetite', 'no appetite', 'anorexia', 'decreased appetite'],
        'early satiety' => ['early satiety', 'fullness after small meals', 'feeling full quickly', 'fullness after eating', 'full after few bites'],
        'worse empty stomach' => ['worse on empty stomach', 'worsens on empty stomach', 'pain on empty stomach', 'worse when hungry', 'empty stomach'],
        'worse after eating' => ['worse after eating', 'worsens after eating', 'pain after meals', 'worse after meals', 'aggravated by food'],
        'worse spicy food' => ['worse with spicy', 'worsens with spicy', 'worse after spicy', 'spicy food aggravates', 'aggravated by spicy'],
        'worse alcohol' => ['worsens with alcohol', 'worse with alcohol', 'alcohol aggravates', 'discomfort worsens with alcohol'],
        'worse nsaids' => ['worsens with nsaids', 'worse with nsaids', 'nsaids aggravate', 'aggravated by nsaids', 'nsaid induced'],
        'weight loss' => ['weight loss', 'lost weight', 'losing weight', 'mild weight loss'],
        'indigestion' => ['indigestion', 'dyspepsia', 'digestive problems'],
        
        // Respiratory
        'productive cough' => ['productive cough', 'cough with phlegm', 'cough with sputum', 'wet cough'],
        'dry cough' => ['dry cough', 'non-productive cough', 'irritating cough'],
        'night sweats' => ['night sweats', 'sweating at night', 'nocturnal sweating'],
        'coughing blood' => ['coughing blood', 'hemoptysis', 'blood in sputum'],
        'wheezing' => ['wheezing', 'whistling sound', 'chest wheezing'],
        
        // Joint/MSK
        'joint pain' => ['joint pain', 'arthralgia', 'painful joints', 'aching joints'],
        'joint swelling' => ['joint swelling', 'swollen joints', 'puffy joints'],
        'morning stiffness' => ['morning stiffness', 'stiff in morning', 'stiffness on waking'],
        'back pain' => ['back pain', 'lower back pain', 'lumbar pain', 'backache'],
        'big toe pain' => ['big toe pain', 'pain in big toe', 'first mtp', 'podagra'],
        
        // Psychiatric
        'loss of interest' => ['loss of interest', 'anhedonia', 'no interest', 'lost interest'],
        'sleep problems' => ['sleep problems', 'insomnia', 'difficulty sleeping', 'cannot sleep'],
        'racing thoughts' => ['racing thoughts', 'thoughts racing', 'mind racing'],
        'panic attacks' => ['panic attacks', 'sudden fear', 'anxiety attacks'],
        'compulsive behavior' => ['compulsive behavior', 'repetitive behavior', 'obsessive thoughts'],
        
        // Skin
        'skin rash' => ['skin rash', 'rash', 'eruption', 'skin lesion'],
        'itching' => ['itching', 'pruritus', 'itchy skin'],
        'white patches' => ['white patches', 'depigmentation', 'loss of skin color'],
        'facial redness' => ['facial redness', 'flushing', 'red face', 'redness on face'],
        
        // Infectious
        'high fever' => ['high fever', 'high temperature', 'pyrexia'],
        'body aches' => ['body aches', 'myalgia', 'muscle aches', 'aching all over'],
        'step ladder fever' => ['step ladder fever', 'gradually increasing fever', 'stepladder'],
        'rose spots' => ['rose spots', 'salmon colored spots', 'pink spots on abdomen'],
        
        // Ear/ENT
        'ringing ears' => ['ringing ears', 'tinnitus', 'ear ringing', 'buzzing in ears'],
        'hearing loss' => ['hearing loss', 'decreased hearing', 'cannot hear properly'],
        'vertigo' => ['vertigo', 'spinning sensation', 'room spinning', 'dizziness']
    ];
    
    // Check for patterns
    foreach ($multiWordPatterns as $pattern => $variants) {
        foreach ($variants as $variant) {
            if (strpos($text, $variant) !== false) {
                $patterns[$pattern] = true;
                break;
            }
        }
    }
    
    return array_keys($patterns);
}

/**
 * Calculate pathognomonic bonuses for specific disease patterns
 */
function calculatePathognomonicBonuses($symptomsLower, $symptomPatterns) {
    $bonuses = [];
    
    // Disease pattern definitions with required symptoms
    $diseasePatterns = [
        // Thyroid disorders
        'Hypothyroidism' => [
            'bonus' => 120,
            'requires_any' => ['weight gain', 'cold intolerance', 'fatigue', 'constipation', 'dry skin', 'hair loss'],
            'min_matches' => 3,
            'direct_mentions' => ['hypothyroid', 'underactive thyroid', 'low thyroid']
        ],
        'Hyperthyroidism' => [
            'bonus' => 120,
            'requires_any' => ['weight loss', 'heat intolerance', 'palpitations', 'tremor', 'anxiety', 'sweating'],
            'min_matches' => 3,
            'direct_mentions' => ['hyperthyroid', 'overactive thyroid', 'thyrotoxicosis', 'graves']
        ],
        'Hashimoto Thyroiditis' => [
            'bonus' => 100,
            'requires_any' => ['fatigue', 'weight gain', 'neck swelling', 'cold intolerance'],
            'requires_all' => [],
            'min_matches' => 2,
            'direct_mentions' => ['hashimoto', 'autoimmune thyroid']
        ],
        'Graves Disease' => [
            'bonus' => 100,
            'requires_any' => ['weight loss', 'heat intolerance', 'tremor', 'bulging eyes', 'exophthalmos'],
            'min_matches' => 2,
            'direct_mentions' => ['graves disease', 'graves']
        ],
        
        // Diabetes
        'Type 1 Diabetes Mellitus' => [
            'bonus' => 100,
            'requires_any' => ['increased thirst', 'frequent urination', 'weight loss', 'fatigue', 'blurred vision'],
            'min_matches' => 3,
            'direct_mentions' => ['type 1 diabetes', 'juvenile diabetes', 'insulin dependent']
        ],
        'Type 2 Diabetes Mellitus' => [
            'bonus' => 100,
            'requires_any' => ['increased thirst', 'frequent urination', 'fatigue', 'slow healing', 'blurred vision'],
            'min_matches' => 3,
            'direct_mentions' => ['type 2 diabetes', 'adult onset diabetes', 'diabetes mellitus']
        ],
        
        // PCOS
        'Polycystic Ovary Syndrome (PCOS)' => [
            'bonus' => 130,
            'requires_any' => ['irregular periods', 'facial hair', 'acne', 'weight gain', 'difficulty conceiving'],
            'min_matches' => 2,
            'direct_mentions' => ['pcos', 'polycystic ovary', 'polycystic ovarian']
        ],
        
        // Cardiac
        'Myocardial Infarction' => [
            'bonus' => 150,
            'requires_any' => ['chest pain', 'radiating to arm', 'shortness of breath', 'cold sweats', 'jaw pain'],
            'min_matches' => 2,
            'direct_mentions' => ['heart attack', 'mi', 'myocardial infarction']
        ],
        
        // CRITICAL: Hypertension - Often missed but very common
        'Hypertension' => [
            'bonus' => 180,  // High bonus - very common condition
            'requires_any' => [
                'headache', 'dizziness', 'blurred vision', 'nosebleed', 'epistaxis',
                'chest discomfort', 'chest pain', 'fatigue', 'shortness of breath',
                'morning headache', 'palpitations', 'flushing'
            ],
            'min_matches' => 3,  // Need at least 3 symptoms
            'direct_mentions' => ['hypertension', 'high blood pressure', 'bp high', 'elevated bp', 'htn'],
            'chronic_bonus' => 100  // Extra bonus for chronic presentation
        ],
        'Essential Hypertension' => [
            'bonus' => 180,
            'requires_any' => [
                'headache', 'dizziness', 'blurred vision', 'nosebleed', 'epistaxis',
                'chest discomfort', 'fatigue', 'morning headache', 'palpitations'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['essential hypertension', 'primary hypertension'],
            'chronic_bonus' => 100
        ],
        'Hypertensive Crisis' => [
            'bonus' => 140,  // Lower than regular hypertension - needs severe symptoms
            'requires_any' => [
                'severe headache', 'confusion', 'chest pain', 'shortness of breath',
                'vision changes', 'nosebleed', 'nausea', 'vomiting'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['hypertensive crisis', 'hypertensive emergency', 'malignant hypertension']
        ],
        
        // Headaches
        'Migraine' => [
            'bonus' => 130,
            'requires_any' => ['one-sided headache', 'throbbing pain', 'sensitivity to light', 'sensitivity to sound', 'nausea', 'visual disturbance'],
            'min_matches' => 2,
            'direct_mentions' => ['migraine']
        ],
        'Tension-Type Headache' => [
            'bonus' => 100,
            'requires_any' => ['headache', 'bilateral', 'band-like', 'pressure', 'stress'],
            'min_matches' => 2,
            'direct_mentions' => ['tension headache', 'stress headache']
        ],
        
        // GI - CRITICAL: These are very common conditions
        'Gastritis' => [
            'bonus' => 200,  // High bonus - very common and classic presentation
            'requires_any' => [
                'epigastric', 'upper abdomen', 'burning pain', 'nausea', 'vomiting',
                'bloating', 'belching', 'loss of appetite', 'empty stomach',
                'worse after eating', 'worse spicy', 'fullness', 'indigestion'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['gastritis', 'gastric inflammation', 'stomach inflammation']
        ],
        'Acute Gastritis' => [
            'bonus' => 200,
            'requires_any' => [
                'epigastric', 'burning pain', 'nausea', 'vomiting', 'bloating',
                'worse after eating', 'worse spicy', 'nsaids', 'alcohol', 'upper abdomen'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['acute gastritis']
        ],
        'Chronic Gastritis' => [
            'bonus' => 190,
            'requires_any' => [
                'epigastric', 'burning', 'nausea', 'bloating', 'belching',
                'loss of appetite', 'fullness after meals', 'upper abdomen'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['chronic gastritis']
        ],
        'Peptic Ulcer Disease' => [
            'bonus' => 190,  // Second most common for this symptom pattern
            'requires_any' => [
                'epigastric', 'burning pain', 'empty stomach', 'nausea', 'vomiting',
                'weight loss', 'loss of appetite', 'night pain', 'nsaids', 'upper abdomen'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['peptic ulcer', 'stomach ulcer', 'gastric ulcer', 'duodenal ulcer', 'pud']
        ],
        'Gastric Ulcer' => [
            'bonus' => 185,
            'requires_any' => [
                'epigastric', 'burning', 'worse after eating', 'nausea', 'vomiting',
                'weight loss', 'upper abdomen'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['gastric ulcer', 'stomach ulcer']
        ],
        'Duodenal Ulcer' => [
            'bonus' => 185,
            'requires_any' => [
                'epigastric', 'burning', 'empty stomach', 'night pain', 'relieved by eating',
                'nausea', 'upper abdomen'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['duodenal ulcer']
        ],
        'Functional Dyspepsia' => [
            'bonus' => 160,
            'requires_any' => [
                'epigastric', 'bloating', 'fullness', 'nausea', 'belching',
                'upper abdomen', 'indigestion', 'early satiety'
            ],
            'min_matches' => 3,
            'direct_mentions' => ['functional dyspepsia', 'dyspepsia', 'indigestion']
        ],
        'Appendicitis' => [
            'bonus' => 140,
            'requires_any' => ['right lower quadrant', 'abdominal pain', 'nausea', 'vomiting', 'fever'],
            'min_matches' => 2,
            'direct_mentions' => ['appendicitis', 'appendix']
        ],
        'GERD' => [
            'bonus' => 150,
            'requires_any' => ['heartburn', 'acid reflux', 'regurgitation', 'chest pain after eating', 'lying down', 'burning chest'],
            'min_matches' => 2,
            'direct_mentions' => ['gerd', 'gastroesophageal reflux', 'acid reflux disease']
        ],
        'Irritable Bowel Syndrome' => [
            'bonus' => 110,
            'requires_any' => ['abdominal pain', 'bloating', 'alternating constipation diarrhea', 'gas'],
            'min_matches' => 2,
            'direct_mentions' => ['ibs', 'irritable bowel']
        ],
        'Crohn Disease' => [
            'bonus' => 110,
            'requires_any' => ['abdominal pain', 'diarrhea', 'weight loss', 'blood in stool', 'fatigue'],
            'min_matches' => 3,
            'direct_mentions' => ['crohn', 'crohns disease']
        ],
        'Ulcerative Colitis' => [
            'bonus' => 110,
            'requires_any' => ['blood in stool', 'diarrhea', 'abdominal pain', 'mucus in stool', 'urgency'],
            'min_matches' => 3,
            'direct_mentions' => ['ulcerative colitis', 'uc']
        ],
        
        // Joint
        'Gout' => [
            'bonus' => 140,
            'requires_any' => ['big toe pain', 'joint swelling', 'sudden onset', 'red hot joint', 'uric acid'],
            'min_matches' => 2,
            'direct_mentions' => ['gout', 'gouty arthritis', 'podagra']
        ],
        'Rheumatoid Arthritis' => [
            'bonus' => 110,
            'requires_any' => ['joint pain', 'morning stiffness', 'joint swelling', 'symmetric joint', 'small joint', 'hand joint', 'finger joint'],
            'min_matches' => 3,  // Require more matches to avoid false positives
            'direct_mentions' => ['rheumatoid', 'rheumatoid arthritis', 'ra arthritis']
        ],
        'Fibromyalgia' => [
            'bonus' => 110,
            'requires_any' => ['widespread pain', 'fatigue', 'sleep problems', 'tender points', 'brain fog'],
            'min_matches' => 3,
            'direct_mentions' => ['fibromyalgia', 'fibro']
        ],
        
        // Neurological
        'Parkinson Disease' => [
            'bonus' => 130,
            'requires_any' => ['tremor', 'muscle stiffness', 'bradykinesia', 'balance problems', 'shuffling gait'],
            'min_matches' => 2,
            'direct_mentions' => ['parkinson', 'parkinsons']
        ],
        'Multiple Sclerosis' => [
            'bonus' => 120,
            'requires_any' => ['numbness', 'visual disturbance', 'weakness', 'fatigue', 'balance problems'],
            'min_matches' => 3,
            'direct_mentions' => ['multiple sclerosis', 'ms']
        ],
        
        // Psychiatric
        'Panic Disorder' => [
            'bonus' => 120,
            'requires_any' => ['panic attacks', 'palpitations', 'shortness of breath', 'fear of dying', 'chest pain'],
            'min_matches' => 2,
            'direct_mentions' => ['panic disorder', 'panic attack']
        ],
        'Obsessive-Compulsive Disorder (OCD)' => [
            'bonus' => 120,
            'requires_any' => ['compulsive behavior', 'obsessive thoughts', 'repetitive', 'anxiety', 'intrusive thoughts'],
            'min_matches' => 2,
            'direct_mentions' => ['ocd', 'obsessive compulsive']
        ],
        'Insomnia' => [
            'bonus' => 100,
            'requires_any' => ['sleep problems', 'difficulty falling asleep', 'waking up early', 'fatigue'],
            'min_matches' => 2,
            'direct_mentions' => ['insomnia', 'sleeplessness']
        ],
        
        // Infectious - Common fever conditions (PRIORITY for simple fever+cough)
        'Acute Febrile Illness (Viral Fever)' => [
            'bonus' => 150,  // High bonus for this common condition
            'requires_any' => ['fever', 'cough', 'body aches', 'fatigue', 'malaise', 'runny nose'],
            'min_matches' => 2,  // Low threshold - very common presentation
            'direct_mentions' => ['viral fever', 'afi', 'febrile illness', 'fever with cough']
        ],
        'Acute Upper Respiratory Infection' => [
            'bonus' => 145,
            'requires_any' => ['fever', 'cough', 'sore throat', 'nasal congestion', 'runny nose'],
            'min_matches' => 2,
            'direct_mentions' => ['urti', 'upper respiratory', 'cold with fever']
        ],
        'Acute Tonsillitis' => [
            'bonus' => 140,
            'requires_any' => ['fever', 'sore throat', 'difficulty swallowing', 'throat pain', 'swollen tonsils'],
            'min_matches' => 2,
            'direct_mentions' => ['tonsillitis', 'tonsils', 'throat infection']
        ],
        'Common Cold (Acute Coryza)' => [
            'bonus' => 130,
            'requires_any' => ['runny nose', 'sneezing', 'cough', 'sore throat', 'nasal congestion'],
            'min_matches' => 2,
            'direct_mentions' => ['common cold', 'cold', 'coryza']
        ],
        'Acute Bronchitis' => [
            'bonus' => 125,
            'requires_any' => ['cough', 'chest discomfort', 'fever', 'fatigue', 'wheezing'],
            'min_matches' => 2,
            'direct_mentions' => ['bronchitis', 'chest infection']
        ],
        'Community Acquired Pneumonia' => [
            'bonus' => 135,
            'requires_any' => ['fever', 'productive cough', 'shortness of breath', 'chest pain', 'chills'],
            'min_matches' => 3,  // Require more symptoms for pneumonia
            'direct_mentions' => ['pneumonia', 'lung infection']
        ],
        'Typhoid Fever' => [
            'bonus' => 140,
            'requires_any' => ['step ladder fever', 'high fever', 'headache', 'abdominal pain', 'rose spots', 'constipation'],
            'min_matches' => 3,
            'direct_mentions' => ['typhoid', 'enteric fever', 'widal']
        ],
        'Influenza (Flu)' => [
            'bonus' => 140,
            'requires_any' => ['high fever', 'body aches', 'fatigue', 'dry cough', 'sore throat', 'chills', 'severe body aches'],
            'min_matches' => 3,
            'direct_mentions' => ['flu', 'influenza']
        ],
        'Dengue' => [
            'bonus' => 130,
            'requires_any' => ['high fever', 'body aches', 'headache', 'rash', 'bleeding'],
            'min_matches' => 3,
            'direct_mentions' => ['dengue', 'breakbone fever']
        ],
        
        // Ear
        'Meniere Disease' => [
            'bonus' => 130,
            'requires_any' => ['vertigo', 'ringing ears', 'hearing loss', 'ear fullness'],
            'min_matches' => 2,
            'direct_mentions' => ['meniere', 'menieres']
        ],
        'Tinnitus' => [
            'bonus' => 100,
            'requires_any' => ['ringing ears', 'buzzing ears', 'ear noise'],
            'min_matches' => 1,
            'direct_mentions' => ['tinnitus', 'ringing in ears']
        ],
        
        // Skin
        'Vitiligo' => [
            'bonus' => 120,
            'requires_any' => ['white patches', 'depigmentation', 'loss of skin color'],
            'min_matches' => 1,
            'direct_mentions' => ['vitiligo']
        ],
        'Urticaria (Hives)' => [
            'bonus' => 110,
            'requires_any' => ['itching', 'skin rash', 'wheals', 'swelling'],
            'min_matches' => 2,
            'direct_mentions' => ['urticaria', 'hives']
        ],
        'Tinea Corporis (Ringworm)' => [
            'bonus' => 150,
            'requires_any' => ['circular patch', 'ring-shaped', 'ring shaped', 'central clearing', 'clear center', 'scaly border', 'spreading outward', 'fungal', 'itchy circular', 'raised border'],
            'min_matches' => 2,
            'direct_mentions' => ['ringworm', 'tinea corporis', 'tinea', 'dermatophyte', 'fungal skin', 'koh positive', 'wood lamp']
        ],
        'Tinea Pedis (Athletes Foot)' => [
            'bonus' => 130,
            'requires_any' => ['itchy feet', 'foot rash', 'between toes', 'peeling skin feet', 'cracked skin feet'],
            'min_matches' => 2,
            'direct_mentions' => ['athletes foot', 'tinea pedis', 'foot fungus']
        ],
        'Tinea Cruris (Jock Itch)' => [
            'bonus' => 130,
            'requires_any' => ['groin rash', 'itchy groin', 'inner thigh rash', 'scaly groin'],
            'min_matches' => 2,
            'direct_mentions' => ['jock itch', 'tinea cruris', 'groin fungus']
        ],
        
        // Autoimmune
        'Systemic Lupus Erythematosus (SLE)' => [
            'bonus' => 130,
            'requires_any' => ['skin rash', 'joint pain', 'fatigue', 'butterfly rash', 'photosensitivity'],
            'min_matches' => 3,
            'direct_mentions' => ['lupus', 'sle']
        ],
        
        // Endometriosis
        'Endometriosis' => [
            'bonus' => 120,
            'requires_any' => ['painful periods', 'pelvic pain', 'pain during intercourse', 'difficulty conceiving', 'heavy periods'],
            'min_matches' => 2,
            'direct_mentions' => ['endometriosis', 'endo']
        ],
        
        // Chronic Fatigue
        'Chronic Fatigue Syndrome (ME/CFS)' => [
            'bonus' => 110,
            'requires_any' => ['fatigue', 'post-exertional malaise', 'sleep problems', 'brain fog', 'muscle pain'],
            'min_matches' => 3,
            'direct_mentions' => ['chronic fatigue', 'cfs', 'me/cfs']
        ]
    ];
    
    // Check each disease pattern
    foreach ($diseasePatterns as $disease => $criteria) {
        // Check for direct mentions first (highest priority)
        foreach ($criteria['direct_mentions'] as $mention) {
            if (strpos($symptomsLower, $mention) !== false) {
                $bonuses[$disease] = max($bonuses[$disease] ?? 0, $criteria['bonus'] + 50);
                error_log("Diagnosis - Direct mention bonus for $disease: " . ($criteria['bonus'] + 50));
                break;
            }
        }
        
        // Count pattern matches
        $matchCount = 0;
        foreach ($criteria['requires_any'] as $symptom) {
            // Check in patterns array first
            if (in_array($symptom, $symptomPatterns)) {
                $matchCount++;
            }
            // Also check directly in text
            elseif (strpos($symptomsLower, $symptom) !== false) {
                $matchCount++;
            }
        }
        
        // Apply bonus if enough matches
        if ($matchCount >= ($criteria['min_matches'] ?? 2)) {
            $calculatedBonus = $criteria['bonus'] + ($matchCount * 10);
            $bonuses[$disease] = max($bonuses[$disease] ?? 0, $calculatedBonus);
            error_log("Diagnosis - Pattern match bonus for $disease: $calculatedBonus (matched $matchCount symptoms)");
        }
    }
    
    return $bonuses;
}

exit;
