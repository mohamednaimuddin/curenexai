<?php
// AI Lab Report & Consultation Analysis with RAG + Gemini
// Usage: include and call analyzeLabAndConsultation($lab_text, $consultation, $doctor_id)

require_once __DIR__ . '/gemini_api.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/privacy_helper.php';

function extractPdfText($pdfPath) {
    // Use pdfminer.six via Python for PDF text extraction
    $output = '';
    
    // Security: Validate the path is within allowed directory
    $realPath = realpath($pdfPath);
    $allowedDir = realpath(__DIR__ . '/../uploads/');
    
    if ($realPath === false || strpos($realPath, $allowedDir) !== 0) {
        error_log("Security: Invalid PDF path attempted: " . $pdfPath);
        return '';
    }
    
    // Use escapeshellarg for safe command execution
    $scriptPath = escapeshellarg(__DIR__ . '/pdf_extract.py');
    $safePath = escapeshellarg($realPath);
    $cmd = "python $scriptPath $safePath";
    exec($cmd, $result, $returnCode);
    
    if ($returnCode !== 0) {
        error_log("PDF extraction failed with code: " . $returnCode);
        return '';
    }
    
    if (!empty($result)) {
        $output = implode("\n", $result);
    }
    return $output;
}

function extractImageText($imgPath) {
    // Use pytesseract via Python for OCR
    $output = '';
    
    // Security: Validate the path is within allowed directory
    $realPath = realpath($imgPath);
    $allowedDir = realpath(__DIR__ . '/../uploads/');
    
    if ($realPath === false || strpos($realPath, $allowedDir) !== 0) {
        error_log("Security: Invalid image path attempted: " . $imgPath);
        return '';
    }
    
    // Use escapeshellarg for safe command execution
    $scriptPath = escapeshellarg(__DIR__ . '/img_ocr.py');
    $safePath = escapeshellarg($realPath);
    $cmd = "python $scriptPath $safePath";
    exec($cmd, $result, $returnCode);
    
    if ($returnCode !== 0) {
        error_log("Image OCR failed with code: " . $returnCode);
        return '';
    }
    
    if (!empty($result)) {
        $output = implode("\n", $result);
    }
    return $output;
}

/**
 * Extract text from lab report image using Gemini Vision API
 * This provides much better OCR for lab reports than traditional tesseract
 * 
 * @param string $imagePath Path to the image file
 * @return string Extracted text from the lab report image
 */
function extractLabImageTextWithGemini($imagePath) {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        error_log('Gemini API key not configured for image OCR');
        return 'Error: Gemini API key not configured for image OCR.';
    }
    
    // Read and encode image
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        return 'Error: Could not read image file.';
    }
    
    $mimeType = mime_content_type($imagePath);
    
    // Validate mime type
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimeType, $allowedMimes)) {
        return 'Error: Unsupported image format.';
    }
    
    $base64Image = base64_encode($imageData);
    
    $prompt = <<<PROMPT
You are an expert medical OCR system specialized in extracting text from lab report images with clinical-grade accuracy.

**CRITICAL INSTRUCTIONS:**
1. Extract EVERY piece of text visible in this lab report image with 100% accuracy
2. Pay special attention to:
   - Test names (full name and abbreviations)
   - Numerical values (preserve decimals, e.g., "12.5", "0.89", "145.2")
   - Units (mg/dL, g/dL, IU/L, U/L, mmol/L, mEq/L, %, cells/cumm, etc.)
   - Reference ranges (e.g., "10-40", "4.0-11.0", "Male: 13-17, Female: 12-16")
   - Flags: H/High, L/Low, +/++/+++, Positive/Negative, Reactive/Non-Reactive
   - Abnormal markers (*, ↑, ↓, bold text, highlighted values)

3. COMMON LAB TESTS TO LOOK FOR:
   - CBC: Hemoglobin, WBC, RBC, Platelets, PCV/Hematocrit, MCV, MCH, MCHC, RDW, Neutrophils, Lymphocytes, Eosinophils, Basophils, Monocytes
   - Liver: SGPT/ALT, SGOT/AST, ALP, GGT, Bilirubin (Total, Direct, Indirect), Albumin, Globulin, A/G Ratio, Total Protein
   - Kidney: Urea, Creatinine, BUN, eGFR, Uric Acid, Electrolytes (Sodium, Potassium, Chloride)
   - Diabetes: Fasting Glucose, Post-Prandial Glucose, HbA1c, Fasting Insulin, HOMA-IR
   - Thyroid: TSH, T3, T4, Free T3, Free T4, Anti-TPO
   - Lipid: Total Cholesterol, HDL, LDL, VLDL, Triglycerides, TC/HDL Ratio
   - Vitamins: Vitamin D (25-OH), Vitamin B12, Folate, Ferritin, Iron, TIBC
   - Hormones: FSH, LH, Prolactin, Estradiol, Testosterone, Cortisol, DHEA-S
   - Inflammatory: CRP, ESR, RA Factor, ANA
   - Urine: Color, pH, Specific Gravity, Protein, Glucose, RBC, WBC, Casts, Crystals

4. EXTRACTION FORMAT:
   - Use consistent format: TEST NAME: VALUE UNIT (Reference: RANGE) [FLAG if abnormal]
   - Keep sections grouped (e.g., HEMATOLOGY, BIOCHEMISTRY, URINALYSIS)
   - Preserve column alignments where possible

**OUTPUT EXAMPLE:**
PATIENT INFORMATION:
Name: [extracted if visible]
Date: [extracted date]

HEMATOLOGY:
Hemoglobin: 10.2 g/dL (Reference: 12-16) [LOW]
WBC: 8500 cells/cumm (Reference: 4000-11000)
Platelet Count: 250000 /cumm (Reference: 150000-400000)

LIVER FUNCTION:
SGPT/ALT: 85 U/L (Reference: 7-56) [HIGH]
SGOT/AST: 42 U/L (Reference: 10-40) [HIGH]
...

Extract ALL text from this image now. Do NOT interpret or analyze - just extract accurately.
PROMPT;

    // Try multiple models for best OCR results
    $modelsToTry = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.5-pro'];
    
    // API keys for OCR
    $apiKeys = defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) ? GEMINI_API_KEYS : [GEMINI_API_KEY];
    $apiKeys = array_filter(array_unique($apiKeys));
    $lastError = null;
    
    $requestData = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,  // Very low temperature for accurate text extraction
            'maxOutputTokens' => 8192,
            'topP' => 0.8,
            'topK' => 10
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE'
            ]
        ]
    ];
    
    // Try each model with each API key
    foreach ($modelsToTry as $model) {
        foreach ($apiKeys as $apiKey) {
            if (empty($apiKey)) continue;
            
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $lastError = "Connection error: {$curlError}";
                continue;
            }
            
            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $lastError = $errorData['error']['message'] ?? "HTTP Error {$httpCode}";
                
                // Check if it's a quota error
                if (strpos(strtolower($lastError), 'quota') !== false || 
                    strpos(strtolower($lastError), 'rate limit') !== false ||
                    $httpCode === 429) {
                    continue; // Try next API key
                }
                continue;
            }
            
            // Parse successful response
            $data = json_decode($response, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $extractedText = $data['candidates'][0]['content']['parts'][0]['text'];
                
                // Clean up the extracted text
                $extractedText = trim($extractedText);
                
                // Log success
                error_log("Lab image OCR successful using model: {$model}");
                
                return $extractedText;
            }
            
            $lastError = 'Invalid response format from Gemini API';
        }
    }
    
    // All attempts failed
    error_log("Lab image OCR failed. Last error: {$lastError}");
    return "Error extracting text from image: {$lastError}";
}

/**
 * ============================================================================
 * CLINICAL INTELLIGENCE SYSTEM
 * Enhanced analysis for comorbidities, metabolic syndrome, severity scoring
 * ============================================================================
 */

/**
 * Analyze comorbidities and disease interactions
 * Detects patterns like HTN + DM + Obesity → Metabolic Syndrome
 */
function analyzeComorbidities($consultation, $abnormalLabs) {
    $comorbidities = [];
    $patterns = [];
    $riskScore = 0;
    
    $diagnosis = strtolower($consultation['diagnosis'] ?? '');
    $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
    $pastHistory = strtolower($consultation['past_history'] ?? '');
    $physicalExam = strtolower($consultation['physical_examination'] ?? '');
    $allText = "$diagnosis $chiefComplaint $pastHistory $physicalExam";
    
    // Detect existing conditions
    $conditions = [
        'diabetes' => preg_match('/diabet|dm\b|type\s*[12]\s*d[m]?|blood\s*sugar|glucose|hba1c/i', $allText),
        'hypertension' => preg_match('/hypertens|htn|high\s*bp|blood\s*pressure.*(?:high|elevated)|(?:150|160|170|180|190|200)\/(?:9[0-9]|1[0-5][0-9])/i', $allText),
        'obesity' => preg_match('/obes|bmi.*(?:2[5-9]|3[0-9]|4[0-9])|overweight|weight\s*issue/i', $allText),
        'dyslipidemia' => preg_match('/dyslipid|hyperlipid|cholesterol|triglyceride|lipid\s*abnormal/i', $allText),
        'thyroid' => preg_match('/thyroid|hypothyr|hyperthyr|\btsh\b/i', $allText),
        'kidney_disease' => preg_match('/ckd|chronic\s*kidney|renal\s*failure|creatinine\s*(?:high|elevated)/i', $allText),
        // More specific liver patterns - use word boundaries to avoid matching "deliver", "Oliver" etc.
        'liver_disease' => preg_match('/\b(liver\s*(disease|disorder|damage|problem|failure|cirrhosis)|hepat|cirrhosis|fatty\s*liver|nafld|nash)\b/i', $allText),
        'heart_disease' => preg_match('/cardiac|heart|coronary|cad|angina|chf|heart\s*failure/i', $allText),
        'neuropathy' => preg_match('/neuropath|burning.*feet|burning.*(sensation|feet)|tingling.*feet|numbness.*feet|peripheral\s*neuropathy/i', $allText),
        // More specific vision pattern - "blurred vision" associated with diabetes is different from retinopathy
        'retinopathy' => preg_match('/\bretinopathy\b|diabetic\s*eye|macular|fundus\s*exam/i', $allText),
        'nephropathy' => preg_match('/nephropathy|protein.*urine|microalbumin/i', $allText),
    ];
    
    // Add lab-detected conditions
    foreach ($abnormalLabs as $lab) {
        $param = strtolower($lab['parameter']);
        if (preg_match('/glucose|sugar|hba1c/i', $param) && $lab['status'] === 'elevated') {
            $conditions['diabetes'] = true;
        }
        if (preg_match('/creatinine|urea|bun|egfr/i', $param)) {
            $conditions['kidney_disease'] = true;
        }
        if (preg_match('/sgpt|sgot|alt|ast|bilirubin/i', $param) && $lab['status'] === 'elevated') {
            $conditions['liver_disease'] = true;
        }
        if (preg_match('/cholesterol|triglyceride|ldl|hdl/i', $param)) {
            $conditions['dyslipidemia'] = true;
        }
        if (preg_match('/tsh|t3|t4/i', $param)) {
            $conditions['thyroid'] = true;
        }
    }
    
    // Build comorbidity list
    $conditionNames = [
        'diabetes' => 'Type 2 Diabetes Mellitus',
        'hypertension' => 'Hypertension',
        'obesity' => 'Obesity/Overweight',
        'dyslipidemia' => 'Dyslipidemia',
        'thyroid' => 'Thyroid Disorder',
        'kidney_disease' => 'Chronic Kidney Disease',
        'liver_disease' => 'Liver Disease',
        'heart_disease' => 'Cardiovascular Disease',
        'neuropathy' => 'Peripheral Neuropathy',
        'retinopathy' => 'Diabetic Retinopathy',
        'nephropathy' => 'Diabetic Nephropathy',
    ];
    
    foreach ($conditions as $key => $present) {
        if ($present) {
            $comorbidities[] = $conditionNames[$key];
            $riskScore += 10;
        }
    }
    
    // Detect clinical patterns
    $hasDM = $conditions['diabetes'];
    $hasHTN = $conditions['hypertension'];
    $hasObesity = $conditions['obesity'];
    $hasDyslipidemia = $conditions['dyslipidemia'];
    
    // Metabolic Syndrome Detection (3 or more of: obesity, HTN, DM, dyslipidemia)
    $metabolicCount = ($hasDM ? 1 : 0) + ($hasHTN ? 1 : 0) + ($hasObesity ? 1 : 0) + ($hasDyslipidemia ? 1 : 0);
    if ($metabolicCount >= 3) {
        $patterns['metabolic_syndrome'] = [
            'name' => 'Metabolic Syndrome',
            'components' => array_filter([
                $hasDM ? 'Diabetes' : null,
                $hasHTN ? 'Hypertension' : null,
                $hasObesity ? 'Obesity' : null,
                $hasDyslipidemia ? 'Dyslipidemia' : null,
            ]),
            'severity' => 'high',
            'implications' => 'Increased cardiovascular risk. Requires comprehensive lifestyle modification.',
            'remedies' => ['lycopodium', 'sulphur', 'natrum-mur', 'calcarea', 'phytolacca']
        ];
        $riskScore += 20;
    }
    
    // Diabetic Triad (DM + Neuropathy + Nephropathy/Retinopathy)
    if ($hasDM && $conditions['neuropathy'] && ($conditions['nephropathy'] || $conditions['retinopathy'])) {
        $patterns['diabetic_complications'] = [
            'name' => 'Diabetic Complications Triad',
            'components' => ['Diabetes', 'Neuropathy', 'Nephropathy/Retinopathy'],
            'severity' => 'critical',
            'implications' => 'Advanced diabetes with microvascular complications. HbA1c target and renal function monitoring critical.',
            'remedies' => ['syzygium', 'arsenicum', 'phosphorus', 'plumbum', 'uranium-nit']
        ];
        $riskScore += 25;
    }
    
    // Cardiometabolic Pattern (HTN + DM + Heart Disease)
    if ($hasHTN && $hasDM && $conditions['heart_disease']) {
        $patterns['cardiometabolic'] = [
            'name' => 'Cardiometabolic Pattern',
            'components' => ['Hypertension', 'Diabetes', 'Heart Disease'],
            'severity' => 'critical',
            'implications' => 'Very high cardiovascular risk. Consider cardiac protection remedies.',
            'remedies' => ['crataegus', 'digitalis', 'cactus', 'aurum', 'syzygium']
        ];
        $riskScore += 30;
    }
    
    // DM + HTN Pattern (common combination)
    if ($hasDM && $hasHTN && !isset($patterns['cardiometabolic'])) {
        $patterns['dm_htn'] = [
            'name' => 'Diabetes-Hypertension Comorbidity',
            'components' => ['Diabetes', 'Hypertension'],
            'severity' => 'moderate',
            'implications' => 'Combined metabolic-vascular risk. Target BP <130/80 in diabetics.',
            'remedies' => ['syzygium', 'crataegus', 'rauwolfia', 'natrum-mur', 'phosphoric-acid']
        ];
        $riskScore += 15;
    }
    
    return [
        'comorbidities' => $comorbidities,
        'patterns' => $patterns,
        'risk_score' => min($riskScore, 100),
        'risk_level' => $riskScore >= 50 ? 'high' : ($riskScore >= 25 ? 'moderate' : 'low'),
        'conditions' => $conditions
    ];
}

/**
 * Parse BMI and physical examination findings
 */
function analyzePhysicalExam($consultation) {
    $physicalExam = $consultation['physical_examination'] ?? '';
    $findings = [
        'bmi' => null,
        'bmi_category' => null,
        'bp_systolic' => null,
        'bp_diastolic' => null,
        'bp_category' => null,
        'weight' => null,
        'height' => null,
        'pulse' => null,
        'temperature' => null,
        'clinical_implications' => [],
        'remedies_suggested' => []
    ];
    
    // Extract BMI
    if (preg_match('/bmi[:\s]*([0-9]+\.?[0-9]*)/i', $physicalExam, $m)) {
        $bmi = floatval($m[1]);
        $findings['bmi'] = $bmi;
        
        if ($bmi < 18.5) {
            $findings['bmi_category'] = 'Underweight';
            $findings['clinical_implications'][] = 'Consider nutritional deficiency, malabsorption';
            $findings['remedies_suggested'][] = ['china', 'natrum-mur', 'calcarea', 'lycopodium', 'phosphorus'];
        } elseif ($bmi < 25) {
            $findings['bmi_category'] = 'Normal';
        } elseif ($bmi < 30) {
            $findings['bmi_category'] = 'Overweight';
            $findings['clinical_implications'][] = 'Increased metabolic risk, insulin resistance likely';
            $findings['remedies_suggested'][] = ['calcarea', 'phytolacca', 'fucus', 'graphites', 'thyroidinum'];
        } elseif ($bmi < 35) {
            $findings['bmi_category'] = 'Obese Class I';
            $findings['clinical_implications'][] = 'Moderate obesity - metabolic syndrome risk';
            $findings['remedies_suggested'][] = ['calcarea', 'phytolacca', 'graphites', 'fucus', 'antimonium-crud'];
        } elseif ($bmi < 40) {
            $findings['bmi_category'] = 'Obese Class II';
            $findings['clinical_implications'][] = 'Severe obesity - high cardiovascular risk';
            $findings['remedies_suggested'][] = ['calcarea', 'graphites', 'antimonium-crud', 'capsicum', 'phytolacca'];
        } else {
            $findings['bmi_category'] = 'Obese Class III (Morbid)';
            $findings['clinical_implications'][] = 'Morbid obesity - very high mortality risk';
            $findings['remedies_suggested'][] = ['calcarea', 'graphites', 'antimonium-crud', 'ferrum', 'capsicum'];
        }
    }
    
    // Extract Weight
    if (preg_match('/weight[:\s]*([0-9]+\.?[0-9]*)\s*kg/i', $physicalExam, $m)) {
        $findings['weight'] = floatval($m[1]);
    }
    
    // Extract Height
    if (preg_match('/height[:\s]*([0-9]+\.?[0-9]*)\s*(cm|m)/i', $physicalExam, $m)) {
        $height = floatval($m[1]);
        if ($m[2] === 'm') $height *= 100;
        $findings['height'] = $height;
        
        // Calculate BMI if not provided
        if (!$findings['bmi'] && $findings['weight'] && $findings['height']) {
            $heightM = $findings['height'] / 100;
            $findings['bmi'] = round($findings['weight'] / ($heightM * $heightM), 1);
        }
    }
    
    // Extract Blood Pressure
    if (preg_match('/(?:bp|blood\s*pressure)[:\s]*([0-9]+)\s*[\/\\\\]\s*([0-9]+)/i', $physicalExam, $m)) {
        $findings['bp_systolic'] = intval($m[1]);
        $findings['bp_diastolic'] = intval($m[2]);
        
        $sys = $findings['bp_systolic'];
        $dia = $findings['bp_diastolic'];
        
        if ($sys < 120 && $dia < 80) {
            $findings['bp_category'] = 'Normal';
        } elseif ($sys < 130 && $dia < 80) {
            $findings['bp_category'] = 'Elevated';
        } elseif ($sys < 140 || $dia < 90) {
            $findings['bp_category'] = 'Stage 1 Hypertension';
            $findings['clinical_implications'][] = 'Early hypertension - lifestyle modification first';
            $findings['remedies_suggested'][] = ['natrum-mur', 'crataegus', 'rauwolfia', 'lachesis'];
        } elseif ($sys < 180 && $dia < 120) {
            $findings['bp_category'] = 'Stage 2 Hypertension';
            $findings['clinical_implications'][] = 'Established hypertension - target organ damage risk';
            $findings['remedies_suggested'][] = ['crataegus', 'rauwolfia', 'natrum-mur', 'glonoine', 'baryta-mur'];
        } else {
            $findings['bp_category'] = 'Hypertensive Crisis';
            $findings['clinical_implications'][] = 'URGENT: Hypertensive emergency - immediate attention needed';
            $findings['remedies_suggested'][] = ['glonoine', 'veratrum-vir', 'crataegus', 'aconite'];
        }
    }
    
    // Extract Pulse
    if (preg_match('/pulse[:\s]*([0-9]+)\s*(?:bpm|\/min)?/i', $physicalExam, $m)) {
        $findings['pulse'] = intval($m[1]);
        
        if ($findings['pulse'] > 100) {
            $findings['clinical_implications'][] = 'Tachycardia - consider anxiety, hyperthyroidism, anemia';
            $findings['remedies_suggested'][] = ['digitalis', 'crataegus', 'spigelia', 'arsenicum'];
        } elseif ($findings['pulse'] < 60) {
            $findings['clinical_implications'][] = 'Bradycardia - consider hypothyroidism, cardiac conduction issue';
            $findings['remedies_suggested'][] = ['digitalis', 'kalmia', 'thyroidinum'];
        }
    }
    
    return $findings;
}

/**
 * Analyze patient compliance and lifestyle factors
 */
function analyzeComplianceFactors($consultation) {
    $factors = [
        'compliance_score' => 100,
        'compliance_level' => 'good',
        'issues' => [],
        'lifestyle_factors' => [],
        'stress_indicators' => [],
        'recommendations' => [],
        'constitutional_remedies' => []
    ];
    
    $pastHistory = strtolower($consultation['past_history'] ?? '');
    $causation = strtolower($consultation['causation'] ?? '');
    $mentalState = strtolower($consultation['mental_state'] ?? '');
    $notes = strtolower($consultation['notes'] ?? '');
    $allText = "$pastHistory $causation $mentalState $notes";
    
    // Compliance indicators (negative)
    $complianceIssues = [
        'poor compliance' => 20,
        'non-compliant' => 25,
        'irregular medication' => 15,
        'missed doses' => 10,
        'doesn\'t follow' => 15,
        'poor diet' => 15,
        'non-adherent' => 20,
        'defaulter' => 20,
        'erratic' => 10,
        'inconsistent' => 10,
    ];
    
    foreach ($complianceIssues as $issue => $penalty) {
        if (strpos($allText, $issue) !== false) {
            $factors['compliance_score'] -= $penalty;
            $factors['issues'][] = ucfirst($issue);
        }
    }
    
    // Lifestyle factors
    $lifestylePatterns = [
        'sedentary' => ['factor' => 'Sedentary lifestyle', 'remedies' => ['calcarea', 'sulphur', 'nux-vomica']],
        'smoking' => ['factor' => 'Tobacco use', 'remedies' => ['caladium', 'tabacum', 'nux-vomica']],
        'alcohol' => ['factor' => 'Alcohol consumption', 'remedies' => ['nux-vomica', 'sulphur', 'lachesis']],
        'fast food' => ['factor' => 'Poor dietary habits', 'remedies' => ['nux-vomica', 'sulphur', 'lycopodium']],
        'junk food' => ['factor' => 'Poor dietary habits', 'remedies' => ['nux-vomica', 'sulphur', 'lycopodium']],
        'stress' => ['factor' => 'High stress', 'remedies' => ['nux-vomica', 'ignatia', 'phosphoric-acid', 'kali-phos']],
        'work stress' => ['factor' => 'Occupational stress', 'remedies' => ['nux-vomica', 'kali-phos', 'phosphoric-acid']],
        'sleep depriv' => ['factor' => 'Sleep deprivation', 'remedies' => ['coffea', 'nux-vomica', 'kali-phos']],
        'irregular sleep' => ['factor' => 'Poor sleep hygiene', 'remedies' => ['coffea', 'nux-vomica', 'passiflora']],
    ];
    
    foreach ($lifestylePatterns as $pattern => $data) {
        if (strpos($allText, $pattern) !== false) {
            $factors['lifestyle_factors'][] = $data['factor'];
            $factors['constitutional_remedies'] = array_merge(
                $factors['constitutional_remedies'], 
                $data['remedies']
            );
            $factors['compliance_score'] -= 5;
        }
    }
    
    // Stress and mental state indicators
    $stressIndicators = [
        'anxiety' => ['indicator' => 'Anxiety', 'remedies' => ['argentum-nit', 'arsenicum', 'gelsemium', 'phosphorus']],
        'irritability' => ['indicator' => 'Irritability', 'remedies' => ['nux-vomica', 'chamomilla', 'bryonia']],
        'depression' => ['indicator' => 'Depression', 'remedies' => ['aurum', 'natrum-mur', 'ignatia', 'sepia']],
        'anger' => ['indicator' => 'Anger issues', 'remedies' => ['nux-vomica', 'staphysagria', 'chamomilla']],
        'worry' => ['indicator' => 'Excessive worry', 'remedies' => ['arsenicum', 'phosphorus', 'calcarea']],
        'restless' => ['indicator' => 'Restlessness', 'remedies' => ['arsenicum', 'rhus-tox', 'aconite']],
        'fear' => ['indicator' => 'Fear/Apprehension', 'remedies' => ['aconite', 'argentum-nit', 'gelsemium']],
    ];
    
    foreach ($stressIndicators as $pattern => $data) {
        if (strpos($allText, $pattern) !== false || strpos($mentalState, $pattern) !== false) {
            $factors['stress_indicators'][] = $data['indicator'];
            $factors['constitutional_remedies'] = array_merge(
                $factors['constitutional_remedies'], 
                $data['remedies']
            );
        }
    }
    
    // Calculate compliance level
    $factors['compliance_score'] = max(0, $factors['compliance_score']);
    if ($factors['compliance_score'] >= 80) {
        $factors['compliance_level'] = 'good';
    } elseif ($factors['compliance_score'] >= 60) {
        $factors['compliance_level'] = 'moderate';
    } else {
        $factors['compliance_level'] = 'poor';
    }
    
    // Generate recommendations
    if ($factors['compliance_level'] === 'poor') {
        $factors['recommendations'][] = 'Address compliance issues as priority';
        $factors['recommendations'][] = 'Consider simplified dosing regimen';
        $factors['recommendations'][] = 'Patient education on disease management';
    }
    
    if (!empty($factors['lifestyle_factors'])) {
        $factors['recommendations'][] = 'Lifestyle modification counseling recommended';
    }
    
    if (!empty($factors['stress_indicators'])) {
        $factors['recommendations'][] = 'Stress management techniques advised';
        $factors['recommendations'][] = 'Consider constitutional remedy addressing mental state';
    }
    
    // Remove duplicate remedies
    $factors['constitutional_remedies'] = array_unique($factors['constitutional_remedies']);
    
    return $factors;
}

/**
 * Analyze constitutional profile (thermal, thirst, appetite, sleep)
 */
function analyzeConstitutionalProfile($consultation) {
    $profile = [
        'thermal_type' => $consultation['thermal_state'] ?? 'unknown',
        'thirst_pattern' => $consultation['thirst'] ?? 'normal',
        'appetite_pattern' => $consultation['appetite'] ?? 'normal',
        'sleep_pattern' => $consultation['sleep_pattern'] ?? 'normal',
        'constitutional_type' => null,
        'matching_remedies' => [],
        'miasmatic_tendency' => null
    ];
    
    // Constitutional remedy mapping based on combination
    $thermal = strtolower($profile['thermal_type']);
    $thirst = strtolower($profile['thirst_pattern']);
    $appetite = strtolower($profile['appetite_pattern']);
    
    // Hot + Thirsty constitutions
    if ($thermal === 'hot' && strpos($thirst, 'excessive') !== false) {
        $profile['constitutional_type'] = 'Phosphorus-type';
        $profile['matching_remedies'] = ['phosphorus', 'sulphur', 'natrum-mur', 'bryonia'];
    }
    // Hot + Thirstless
    elseif ($thermal === 'hot' && (strpos($thirst, 'less') !== false || strpos($thirst, 'low') !== false)) {
        $profile['constitutional_type'] = 'Pulsatilla-type';
        $profile['matching_remedies'] = ['pulsatilla', 'apis', 'antimonium-crud'];
    }
    // Chilly + Thirsty
    elseif ($thermal === 'chilly' && strpos($thirst, 'excessive') !== false) {
        $profile['constitutional_type'] = 'Arsenicum-type';
        $profile['matching_remedies'] = ['arsenicum', 'nux-vomica', 'hepar'];
    }
    // Chilly + Thirstless
    elseif ($thermal === 'chilly' && (strpos($thirst, 'less') !== false || strpos($thirst, 'low') !== false)) {
        $profile['constitutional_type'] = 'Calcarea-type';
        $profile['matching_remedies'] = ['calcarea', 'graphites', 'silica', 'sepia'];
    }
    
    // Appetite-based additions
    if (strpos($appetite, 'increased') !== false || strpos($appetite, 'excessive') !== false) {
        $profile['matching_remedies'] = array_merge(
            $profile['matching_remedies'],
            ['phosphorus', 'iodium', 'sulphur', 'lycopodium', 'natrum-mur']
        );
    } elseif (strpos($appetite, 'decreased') !== false || strpos($appetite, 'poor') !== false) {
        $profile['matching_remedies'] = array_merge(
            $profile['matching_remedies'],
            ['china', 'arsenicum', 'natrum-mur', 'silica', 'carbo-veg']
        );
    }
    
    // Sleep pattern analysis
    $sleep = strtolower($profile['sleep_pattern']);
    if (preg_match('/disturbed|restless|waking|insomnia|poor/i', $sleep)) {
        $profile['matching_remedies'] = array_merge(
            $profile['matching_remedies'],
            ['coffea', 'nux-vomica', 'arsenicum', 'ignatia', 'passiflora']
        );
    }
    
    // Miasmatic tendency based on patterns
    if (preg_match('/recurring|chronic|long.*standing/i', $consultation['present_illness'] ?? '')) {
        $profile['miasmatic_tendency'] = 'Psoric/Sycotic';
    }
    if (preg_match('/destruction|degenerat|ulcer|malignant/i', $consultation['present_illness'] ?? '')) {
        $profile['miasmatic_tendency'] = 'Syphilitic';
    }
    
    $profile['matching_remedies'] = array_unique($profile['matching_remedies']);
    
    return $profile;
}

/**
 * Calculate disease severity score based on lab values and symptoms
 */
function calculateDiseaseSeverity($abnormalLabs, $consultation, $comorbidities) {
    $severity = [
        'score' => 0,
        'level' => 'mild',
        'critical_findings' => [],
        'urgent_attention' => [],
        'lab_severity' => 0,
        'symptom_severity' => 0,
        'comorbidity_severity' => 0
    ];
    
    // Lab-based severity
    foreach ($abnormalLabs as $lab) {
        $labSeverity = $lab['severity'] ?? 'moderate';
        if ($labSeverity === 'critical') {
            $severity['lab_severity'] += 30;
            $severity['critical_findings'][] = $lab['parameter'] . ': ' . $lab['value'] . ' (' . strtoupper($lab['status']) . ')';
        } elseif ($labSeverity === 'high') {
            $severity['lab_severity'] += 15;
        } else {
            $severity['lab_severity'] += 5;
        }
    }
    
    // Symptom-based severity
    $symptoms = strtolower(
        ($consultation['chief_complaint'] ?? '') . ' ' .
        ($consultation['present_illness'] ?? '') . ' ' .
        ($consultation['general_symptoms'] ?? '') . ' ' .
        ($consultation['particular_symptoms'] ?? '')
    );
    
    $severeSymptoms = [
        'severe' => 10, 'intense' => 10, 'unbearable' => 15,
        'acute' => 8, 'sudden onset' => 10, 'worsening' => 8,
        'progressive' => 8, 'constant' => 5, 'persistent' => 5,
        'fever' => 5, 'bleeding' => 15, 'weight loss' => 10,
        'weakness' => 5, 'fatigue' => 5, 'breathless' => 10,
    ];
    
    foreach ($severeSymptoms as $symptom => $points) {
        if (strpos($symptoms, $symptom) !== false) {
            $severity['symptom_severity'] += $points;
        }
    }
    
    // Comorbidity burden
    $comorbidityCount = count($comorbidities['comorbidities'] ?? []);
    $severity['comorbidity_severity'] = $comorbidityCount * 10;
    
    // Pattern-based severity boost
    if (!empty($comorbidities['patterns'])) {
        foreach ($comorbidities['patterns'] as $pattern) {
            if (($pattern['severity'] ?? '') === 'critical') {
                $severity['comorbidity_severity'] += 20;
            } elseif (($pattern['severity'] ?? '') === 'high') {
                $severity['comorbidity_severity'] += 10;
            }
        }
    }
    
    // Calculate total score
    $severity['score'] = min(100, 
        $severity['lab_severity'] + 
        $severity['symptom_severity'] + 
        $severity['comorbidity_severity']
    );
    
    // Determine level
    if ($severity['score'] >= 70) {
        $severity['level'] = 'severe';
        $severity['urgent_attention'][] = 'High disease severity - prioritize stabilization';
    } elseif ($severity['score'] >= 40) {
        $severity['level'] = 'moderate';
    } else {
        $severity['level'] = 'mild';
    }
    
    return $severity;
}

/**
 * Extract lab-related medical terms for RAG search
 * IMPROVED: Only flags lab values that are ABNORMAL, not just present
 */
function extractLabMedicalTerms($labText, $consultation) {
    $terms = [];
    $labTextLower = strtolower($labText);
    $consultationText = strtolower(($consultation['chief_complaint'] ?? '') . ' ' . ($consultation['diagnosis'] ?? ''));
    
    // First, detect ABNORMAL lab values by parsing the report
    $abnormalFindings = detectAbnormalLabValues($labText);
    
    // Lab value indicators - ONLY used when values are ABNORMAL
    $labIndicators = [
        // ============ BLOOD / HEMATOLOGY ============
        // Anemia
        'low hemoglobin' => ['ferrum', 'ferrum-phos', 'china', 'nat-mur', 'calcarea-phos', 'phosphorus', 'cyclamen', 'ferrum-met'],
        'high hemoglobin' => ['phosphorus', 'crotalus', 'lachesis'],
        'anemia' => ['ferrum', 'ferrum-met', 'ferrum-phos', 'china', 'phosphorus', 'nat-mur', 'calcarea', 'calcarea-phos', 'arsenicum'],
        'low rbc' => ['ferrum', 'china', 'nat-mur', 'arsenicum', 'phosphoric-acid'],
        'high rbc' => ['phosphorus', 'crotalus', 'verat-vir'],
        'low hematocrit' => ['ferrum', 'ferrum-met', 'china', 'nat-mur', 'arsenicum'], // Low PCV
        'low pcv' => ['ferrum', 'ferrum-met', 'china', 'nat-mur', 'arsenicum'],
        'low mcv' => ['ferrum', 'plumbum'], // Microcytic
        'high mcv' => ['phosphorus', 'arsenicum', 'nat-mur'], // Macrocytic
        'low platelet' => ['crotalus', 'phosphorus', 'lachesis', 'hamamelis', 'secale'],
        'thrombocytopenia' => ['crotalus', 'phosphorus', 'lachesis', 'arsenicum', 'hamamelis'],
        'high platelet' => ['ferrum', 'china'],
        'high wbc' => ['pyrogen', 'pyrogenium', 'hepar', 'hepar-sulph', 'mercurius', 'silica', 'belladonna', 'arsenicum'],
        'low wbc' => ['arsenicum', 'phosphorus', 'china', 'carbo-veg'],
        'leukopenia' => ['arsenicum', 'phosphorus', 'china', 'carbo-veg', 'natrum-mur'],
        'leukocytosis' => ['pyrogen', 'hepar', 'mercurius', 'belladonna'],
        'high eosinophil' => ['arsenicum', 'natrum-sulph', 'cina', 'spigelia', 'teucrium'],
        'high neutrophil' => ['pyrogen', 'mercurius', 'hepar'],
        'high lymphocyte' => ['natrum-mur', 'tuberculinum', 'calcarea'],
        'low lymphocyte' => ['arsenicum', 'phosphorus', 'china', 'carbo-veg', 'tuberculinum'],
        
        // ============ LIVER FUNCTION TESTS ============
        'elevated sgpt' => ['chelidonium', 'lycopodium', 'carduus', 'carduus-mar', 'nux-vomica', 'phosphorus', 'china', 'mercurius'],
        'elevated sgot' => ['chelidonium', 'lycopodium', 'carduus', 'carduus-mar', 'nux-vomica', 'phosphorus', 'china'],
        'elevated alt' => ['chelidonium', 'lycopodium', 'carduus', 'carduus-mar', 'nux-vomica', 'china', 'phosphorus'],
        'elevated ast' => ['chelidonium', 'lycopodium', 'carduus', 'carduus-mar', 'nux-vomica', 'phosphorus'],
        'elevated ggt' => ['chelidonium', 'lycopodium', 'carduus', 'nux-vomica', 'phosphorus', 'china', 'fel-tauri'],
        'elevated alp' => ['chelidonium', 'calcarea', 'phosphorus', 'symphytum', 'silicea'],
        'elevated alkaline phosphatase' => ['chelidonium', 'calcarea', 'phosphorus', 'symphytum'],
        'elevated bilirubin' => ['chelidonium', 'myrica', 'chionanthus', 'carduus', 'phosphorus', 'podophyllum', 'natrum-sulph'],
        'jaundice' => ['chelidonium', 'myrica', 'chionanthus', 'phosphorus', 'china', 'mercurius', 'natrum-sulph', 'podophyllum'],
        'fatty liver' => ['chelidonium', 'lycopodium', 'carduus', 'phosphorus', 'nux-vomica', 'fel-tauri', 'cholesterinum'],
        'hepatitis' => ['chelidonium', 'phosphorus', 'lycopodium', 'carduus', 'china', 'mercurius'],
        'cirrhosis' => ['carduus', 'chelidonium', 'lycopodium', 'phosphorus', 'arsenicum', 'china'],
        'low albumin' => ['china', 'arsenicum', 'phosphorus', 'lycopodium', 'calcarea'],
        'elevated globulin' => ['mercurius', 'natrum-mur', 'lycopodium'],
        
        // ============ KIDNEY FUNCTION TESTS ============
        'elevated creatinine' => ['apis', 'cantharis', 'berberis', 'solidago', 'terebinth', 'mercurius-cor', 'arsenicum'],
        'elevated urea' => ['apis', 'cantharis', 'solidago', 'uranium-nit', 'mercurius-cor', 'arsenicum'],
        'elevated bun' => ['apis', 'cantharis', 'arsenicum', 'solidago', 'berberis'],
        'elevated uric acid' => ['colchicum', 'ledum', 'urtica', 'benzoic-acid', 'guaiacum', 'lycopodium', 'lithium-carb'],
        'low egfr' => ['apis', 'cantharis', 'berberis', 'solidago', 'arsenicum', 'mercurius-cor'],
        'chronic kidney' => ['apis', 'arsenicum', 'cantharis', 'solidago', 'berberis', 'mercurius-cor'],
        'proteinuria' => ['apis', 'arsenicum', 'phosphorus', 'terebinth', 'cantharis'],
        'hematuria' => ['cantharis', 'terebinth', 'hamamelis', 'erigeron', 'phosphorus'],
        'high potassium' => ['apis', 'arsenicum', 'kali-carb'],
        'low potassium' => ['kali-carb', 'kali-phos', 'kali-mur'],
        'low sodium' => ['natrum-mur', 'china', 'arsenicum'],
        'high sodium' => ['apis', 'arsenicum', 'natrum-mur'],
        
        // ============ THYROID FUNCTION TESTS ============
        'elevated tsh' => ['thyroidinum', 'calcarea', 'calcarea-carb', 'graphites', 'sepia', 'bromium', 'fucus'],
        'low tsh' => ['iodium', 'natrum-mur', 'thyroidinum', 'lachesis'],
        'hypothyroid' => ['thyroidinum', 'calcarea', 'calcarea-carb', 'graphites', 'sepia', 'bromium', 'fucus', 'baryta-carb'],
        'hyperthyroid' => ['iodium', 'natrum-mur', 'thyroidinum', 'lachesis', 'spongia', 'ferrum-iod'],
        'elevated t3' => ['iodium', 'natrum-mur', 'thyroidinum', 'lachesis'],
        'elevated t4' => ['iodium', 'thyroidinum', 'natrum-mur'],
        'low t3' => ['thyroidinum', 'calcarea', 'graphites', 'sepia'],
        'low t4' => ['thyroidinum', 'calcarea', 'graphites', 'bromium'],
        'elevated anti-tpo' => ['thyroidinum', 'calcarea', 'natrum-mur', 'silica'],
        'hashimoto' => ['thyroidinum', 'calcarea', 'natrum-mur', 'silica', 'arsenicum-iod'],
        'goiter' => ['iodium', 'spongia', 'calcarea', 'bromium', 'fucus'],
        
        // ============ DIABETES / BLOOD SUGAR ============
        'elevated glucose' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'lycopodium', 'argentum-nit', 'insulinum'],
        'elevated fasting glucose' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'cephalandra', 'gymnema'],
        'elevated pp glucose' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'cephalandra', 'lycopodium'],
        'elevated hba1c' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'lycopodium', 'argentum-nit', 'insulinum'],
        'diabetes' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'lycopodium', 'arsenicum', 'cephalandra', 'gymnema', 'insulinum'],
        'diabetic' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'cephalandra', 'lycopodium', 'arsenicum'],
        'glycosuria' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'terebinth'],
        'urine sugar' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'terebinth', 'cephalandra'],
        'pre-diabetes' => ['syzygium', 'uranium-nit', 'lycopodium', 'cephalandra'],
        'insulin resistance' => ['lycopodium', 'syzygium', 'uranium-nit', 'chelidonium'],
        
        // ============ LIPID PROFILE ============
        'elevated cholesterol' => ['cholesterinum', 'fel-tauri', 'chelidonium', 'nux-vomica', 'lycopodium', 'crataegus'],
        'elevated triglyceride' => ['cholesterinum', 'fel-tauri', 'chelidonium', 'lycopodium', 'carduus'],
        'elevated ldl' => ['cholesterinum', 'chelidonium', 'crataegus', 'lycopodium', 'allium-sat'],
        'low hdl' => ['cholesterinum', 'lycopodium', 'crataegus', 'nux-vomica'],
        'dyslipidemia' => ['cholesterinum', 'fel-tauri', 'chelidonium', 'lycopodium', 'nux-vomica', 'crataegus'],
        'hypercholesterolemia' => ['cholesterinum', 'fel-tauri', 'chelidonium', 'crataegus'],
        
        // ============ INFLAMMATORY MARKERS ============
        'elevated crp' => ['pyrogen', 'pyrogenium', 'hepar', 'mercurius', 'rhus-tox', 'arsenicum', 'belladonna'],
        'elevated esr' => ['pyrogen', 'ferrum', 'arsenicum', 'rhus-tox', 'mercurius'],
        'elevated hs-crp' => ['crataegus', 'arsenicum', 'strophanthus'],
        'inflammation' => ['apis', 'belladonna', 'bryonia', 'ferrum-phos', 'mercurius', 'rhus-tox'],
        'elevated ra factor' => ['rhus-tox', 'bryonia', 'causticum', 'ledum', 'colchicum'],
        'rheumatoid' => ['rhus-tox', 'bryonia', 'causticum', 'kali-bich', 'medorrhinum'],
        
        // ============ VITAMINS & MINERALS ============
        'low vitamin d' => ['calcarea', 'calcarea-carb', 'phosphorus', 'calcarea-phos', 'silica', 'lycopodium'],
        'low vitamin b12' => ['phosphorus', 'arsenicum', 'ferrum', 'natrum-mur', 'china', 'carbo-veg'],
        'low iron' => ['ferrum', 'ferrum-met', 'ferrum-phos', 'china', 'nat-mur', 'arsenicum'],
        'low ferritin' => ['ferrum', 'ferrum-met', 'china', 'natrum-mur', 'phosphorus'],
        'elevated ferritin' => ['phosphorus', 'lachesis', 'lycopodium'],
        'iron deficiency' => ['ferrum', 'ferrum-met', 'ferrum-phos', 'china', 'calcarea'],
        'low folate' => ['phosphorus', 'arsenicum', 'china'],
        'low calcium' => ['calcarea', 'calcarea-carb', 'calcarea-phos', 'phosphorus', 'silica'],
        'high calcium' => ['calcarea', 'lycopodium', 'phosphorus'],
        'low magnesium' => ['magnesia-carb', 'magnesia-phos', 'magnesia-mur'],
        
        // ============ CARDIAC MARKERS ============
        'elevated troponin' => ['crataegus', 'digitalis', 'cactus', 'arnica', 'strophanthus'],
        'elevated bnp' => ['crataegus', 'digitalis', 'cactus', 'strophanthus', 'lachesis'],
        'elevated ck-mb' => ['arnica', 'crataegus', 'digitalis'],
        'elevated ldh' => ['arnica', 'phosphorus', 'arsenicum'],
        'elevated homocysteine' => ['crataegus', 'natrum-mur', 'phosphorus'],
        'cardiac' => ['crataegus', 'digitalis', 'cactus', 'spigelia', 'aurum'],
        
        // ============ COAGULATION ============
        'elevated d-dimer' => ['arnica', 'hamamelis', 'lachesis', 'phosphorus'],
        'elevated pt' => ['phosphorus', 'crotalus', 'lachesis', 'hamamelis'],
        'elevated inr' => ['phosphorus', 'crotalus', 'lachesis'],
        'low platelet' => ['crotalus', 'phosphorus', 'lachesis', 'arsenicum'],
        
        // ============ PANCREATIC ============
        'elevated amylase' => ['iris', 'conium', 'phosphorus', 'iodum'],
        'elevated lipase' => ['iris', 'conium', 'phosphorus'],
        'pancreatitis' => ['iris', 'conium', 'phosphorus', 'belladonna', 'iodum'],
        
        // ============ HORMONES ============
        'elevated prolactin' => ['pulsatilla', 'sepia', 'natrum-mur', 'calcarea'],
        'low testosterone' => ['agnus', 'selenium', 'lycopodium', 'conium'],
        'elevated cortisol' => ['arsenicum', 'phosphoric-acid', 'kali-phos'],
        'pcos' => ['pulsatilla', 'sepia', 'nat-mur', 'thuja', 'apis'],
        
        // ============ TUMOR MARKERS ============
        'elevated psa' => ['conium', 'sabal', 'thuja', 'lycopodium', 'chimaphila'],
        'elevated cea' => ['carcinosin', 'conium', 'phosphorus'],
        
        // ============ URINE ============
        'urine protein' => ['apis', 'arsenicum', 'phosphorus', 'terebinth'],
        'urine glucose' => ['syzygium', 'uranium-nit', 'phosphoric-acid'],
        'urine rbc' => ['cantharis', 'terebinth', 'hamamelis'],
        'urine wbc' => ['cantharis', 'apis', 'mercurius-cor'],
        'uti' => ['cantharis', 'apis', 'berberis', 'sarsaparilla', 'staphysagria'],
    ];
    
    // Chief complaint / symptom based indicators (ALWAYS search these)
    $symptomIndicators = [
        // ============ HEAD & NEUROLOGICAL ============
        // Headache/Migraine
        'headache' => ['belladonna', 'bryonia', 'gelsemium', 'natrum-mur', 'sanguinaria', 'spigelia', 'glonoine', 'iris'],
        'migraine' => ['sanguinaria', 'iris', 'gelsemium', 'natrum-mur', 'spigelia', 'lac-defloratum', 'kali-bich', 'silica'],
        'right-sided headache' => ['sanguinaria', 'belladonna', 'lycopodium', 'iris', 'silica'],
        'left-sided headache' => ['spigelia', 'lachesis', 'natrum-mur', 'bryonia'],
        'throbbing' => ['belladonna', 'glonoine', 'natrum-mur', 'sanguinaria', 'ferrum-phos'],
        'aura' => ['gelsemium', 'natrum-mur', 'iris', 'cyclamen', 'silica'],
        'photophobia' => ['belladonna', 'natrum-mur', 'phosphorus', 'conium', 'glonoine'],
        'vertigo' => ['cocculus', 'conium', 'phosphorus', 'gelsemium', 'china', 'bryonia', 'borax'],
        'dizziness' => ['cocculus', 'conium', 'gelsemium', 'phosphorus', 'china', 'bryonia'],
        'tinnitus' => ['china', 'salicylic-acid', 'chininum-sulph', 'carboneum-sulph', 'natrum-salicyl'],
        'trigeminal' => ['spigelia', 'magnesia-phos', 'colocynth', 'verbascum', 'mezereum'],
        'neuralgia' => ['spigelia', 'magnesia-phos', 'colocynth', 'arsenicum', 'aconite', 'hypericum'],
        'numbness' => ['aconite', 'rhus-tox', 'hypericum', 'plumbum', 'phosphorus'],
        'tingling' => ['aconite', 'hypericum', 'secale', 'phosphorus', 'rhus-tox'],
        
        // ============ EYE ============
        'conjunctivitis' => ['argentum-nit', 'euphrasia', 'pulsatilla', 'belladonna', 'sulphur'],
        'eye strain' => ['ruta', 'natrum-mur', 'phosphorus', 'argentum-nit'],
        'dry eyes' => ['natrum-mur', 'alumina', 'sulphur', 'zincum'],
        'cataract' => ['calcarea-fluor', 'phosphorus', 'silicea', 'cineraria'],
        'glaucoma' => ['phosphorus', 'spigelia', 'belladonna', 'gelsemium'],
        
        // ============ EAR NOSE THROAT ============
        'sinusitis' => ['kali-bich', 'silica', 'pulsatilla', 'hepar', 'hydrastis', 'mercurius'],
        'sinus' => ['kali-bich', 'silica', 'pulsatilla', 'hepar', 'hydrastis', 'sanguinaria'],
        'rhinitis' => ['arsenicum-iod', 'allium-cepa', 'sabadilla', 'natrum-mur', 'arsenicum'],
        'post nasal drip' => ['kali-bich', 'hydrastis', 'corallium', 'natrum-mur'],
        'tonsillitis' => ['belladonna', 'mercurius', 'hepar', 'baryta-carb', 'phytolacca'],
        'pharyngitis' => ['belladonna', 'mercurius', 'phytolacca', 'lachesis', 'lycopodium'],
        'sore throat' => ['belladonna', 'mercurius', 'phytolacca', 'lachesis', 'hepar'],
        'ear infection' => ['belladonna', 'chamomilla', 'pulsatilla', 'hepar', 'mercurius'],
        'otitis' => ['belladonna', 'chamomilla', 'pulsatilla', 'hepar', 'mercurius', 'silica'],
        
        // ============ RESPIRATORY ============
        'cough' => ['bryonia', 'phosphorus', 'drosera', 'rumex', 'spongia', 'hepar', 'antimonium-tart'],
        'dry cough' => ['bryonia', 'drosera', 'phosphorus', 'spongia', 'aconite', 'rumex'],
        'productive cough' => ['antimonium-tart', 'pulsatilla', 'kali-bich', 'hepar', 'ipecac'],
        'asthma' => ['arsenicum', 'ipecac', 'sambucus', 'kali-carb', 'spongia', 'natrum-sulph', 'blatta-orient'],
        'bronchitis' => ['bryonia', 'phosphorus', 'antimonium-tart', 'hepar', 'kali-bich', 'senega'],
        'pneumonia' => ['phosphorus', 'bryonia', 'antimonium-tart', 'arsenicum', 'chelidonium', 'lycopodium'],
        'wheezing' => ['arsenicum', 'ipecac', 'antimonium-tart', 'sambucus', 'spongia'],
        'breathlessness' => ['arsenicum', 'antimonium-tart', 'carbo-veg', 'lycopodium', 'lachesis'],
        'dyspnea' => ['arsenicum', 'carbo-veg', 'antimonium-tart', 'lycopodium', 'lachesis'],
        'copd' => ['antimonium-tart', 'carbo-veg', 'arsenicum', 'senega', 'grindelia'],
        
        // ============ CARDIOVASCULAR ============
        'palpitation' => ['digitalis', 'cactus', 'spigelia', 'crataegus', 'arsenicum', 'natrum-mur', 'lachesis'],
        'hypertension' => ['crataegus', 'rauwolfia', 'natrum-mur', 'glonoine', 'lachesis', 'baryta-mur'],
        'hypotension' => ['china', 'carbo-veg', 'gelsemium', 'veratrum-alb'],
        'angina' => ['cactus', 'crataegus', 'spigelia', 'arsenicum', 'digitalis'],
        'chest pain' => ['cactus', 'spigelia', 'bryonia', 'arsenicum', 'ranunculus-bulb'],
        'varicose' => ['hamamelis', 'pulsatilla', 'carbo-veg', 'calcarea-fluor', 'vipera'],
        'edema' => ['apis', 'arsenicum', 'lycopodium', 'china', 'digitalis', 'apocynum'],
        'swelling' => ['apis', 'arsenicum', 'rhus-tox', 'bryonia', 'pulsatilla'],
        
        // ============ DIGESTIVE ============
        'acidity' => ['nux-vomica', 'robinia', 'natrum-phos', 'arsenicum', 'carbo-veg', 'lycopodium'],
        'gerd' => ['nux-vomica', 'robinia', 'iris', 'phosphorus', 'arsenicum', 'carbo-veg'],
        'acid reflux' => ['nux-vomica', 'robinia', 'natrum-phos', 'carbo-veg', 'iris'],
        'heartburn' => ['nux-vomica', 'robinia', 'iris', 'phosphorus', 'arsenicum'],
        'constipation' => ['nux-vomica', 'bryonia', 'alumina', 'opium', 'silica', 'lycopodium', 'plumbum'],
        'diarrhea' => ['arsenicum', 'podophyllum', 'aloe', 'china', 'veratrum-alb', 'phosphorus'],
        'ibs' => ['nux-vomica', 'lycopodium', 'argentum-nit', 'sulphur', 'asafoetida', 'colocynth'],
        'bloating' => ['lycopodium', 'carbo-veg', 'china', 'nux-vomica', 'argentum-nit'],
        'flatulence' => ['lycopodium', 'carbo-veg', 'china', 'argentum-nit', 'raphanus'],
        'indigestion' => ['nux-vomica', 'carbo-veg', 'lycopodium', 'pulsatilla', 'china'],
        'nausea' => ['ipecac', 'nux-vomica', 'arsenicum', 'cocculus', 'tabacum', 'sepia'],
        'vomiting' => ['ipecac', 'arsenicum', 'nux-vomica', 'phosphorus', 'veratrum-alb'],
        'gastritis' => ['arsenicum', 'nux-vomica', 'phosphorus', 'carbo-veg', 'bismuthum'],
        'ulcer' => ['argentum-nit', 'arsenicum', 'phosphorus', 'kali-bich', 'uranium-nit'],
        'peptic ulcer' => ['argentum-nit', 'arsenicum', 'kali-bich', 'nux-vomica', 'phosphorus'],
        'hemorrhoids' => ['nux-vomica', 'hamamelis', 'aloe', 'aesculus', 'sulphur', 'collinsonia'],
        'piles' => ['nux-vomica', 'hamamelis', 'aloe', 'aesculus', 'sulphur', 'collinsonia'],
        'fissure' => ['ratanhia', 'nitricum-acid', 'graphites', 'paeonia', 'silica'],
        
        // ============ URINARY ============
        'urinary tract infection' => ['cantharis', 'apis', 'berberis', 'sarsaparilla', 'staphysagria', 'terebinth'],
        'burning urination' => ['cantharis', 'apis', 'sarsaparilla', 'berberis', 'staphysagria'],
        'frequent urination' => ['syzygium', 'lycopodium', 'causticum', 'staphysagria', 'equisetum', 'clematis', 'uranium-nit'],
        'polyuria' => ['syzygium', 'uranium-nit', 'phosphoric-acid', 'lycopodium', 'natrum-mur'],
        'excessive thirst' => ['syzygium', 'phosphorus', 'natrum-mur', 'arsenicum', 'bryonia', 'uranium-nit'],
        'polydipsia' => ['syzygium', 'phosphorus', 'natrum-mur', 'arsenicum', 'uranium-nit'],
        'burning feet' => ['sulphur', 'arsenicum', 'phosphorus', 'medorrhinum', 'syzygium'],
        'neuropathy' => ['arsenicum', 'phosphorus', 'plumbum', 'hypericum', 'syzygium'],
        'incontinence' => ['causticum', 'sepia', 'natrum-mur', 'pulsatilla', 'kreosotum'],
        'kidney stone' => ['berberis', 'lycopodium', 'calcarea-carb', 'sarsaparilla', 'pareira'],
        'renal colic' => ['berberis', 'colocynth', 'cantharis', 'lycopodium', 'magnesia-phos'],
        'prostate' => ['sabal', 'conium', 'thuja', 'lycopodium', 'chimaphila', 'selenium'],
        'bph' => ['sabal', 'conium', 'thuja', 'lycopodium', 'chimaphila'],
        
        // ============ MUSCULOSKELETAL ============
        'joint pain' => ['rhus-tox', 'bryonia', 'ledum', 'colchicum', 'kali-carb', 'causticum'],
        'arthritis' => ['rhus-tox', 'bryonia', 'ledum', 'colchicum', 'causticum', 'calcarea-fluor'],
        'osteoarthritis' => ['rhus-tox', 'bryonia', 'calcarea-fluor', 'causticum', 'colchicum'],
        'rheumatoid' => ['rhus-tox', 'bryonia', 'causticum', 'kali-bich', 'medorrhinum', 'ledum'],
        'back pain' => ['rhus-tox', 'bryonia', 'kali-carb', 'nux-vomica', 'aesculus', 'hypericum'],
        'sciatica' => ['colocynth', 'magnesia-phos', 'rhus-tox', 'gnaphalium', 'hypericum', 'arnica'],
        'lumbar' => ['rhus-tox', 'bryonia', 'kali-carb', 'berberis', 'aesculus'],
        'cervical' => ['cimicifuga', 'rhus-tox', 'lachnanthes', 'guaiacum', 'hypericum'],
        'spondylosis' => ['rhus-tox', 'calcarea-fluor', 'kali-carb', 'silica', 'hypericum'],
        'gout' => ['colchicum', 'ledum', 'benzoic-acid', 'urtica', 'guaiacum', 'lycopodium'],
        'fibromyalgia' => ['rhus-tox', 'bryonia', 'arnica', 'ruta', 'causticum', 'kali-phos'],
        'muscle pain' => ['arnica', 'rhus-tox', 'bryonia', 'magnesia-phos', 'ruta'],
        'cramps' => ['magnesia-phos', 'cuprum', 'colocynth', 'nux-vomica', 'veratrum-alb'],
        'stiffness' => ['rhus-tox', 'bryonia', 'causticum', 'calcarea-fluor', 'kali-carb'],
        
        // ============ SKIN ============
        'eczema' => ['graphites', 'sulphur', 'mezereum', 'arsenicum', 'petroleum', 'rhus-tox'],
        'psoriasis' => ['arsenicum', 'sulphur', 'graphites', 'petroleum', 'arsenicum-iod', 'sepia'],
        'acne' => ['sulphur', 'kali-brom', 'hepar', 'berberis-aq', 'calcarea-sulph', 'pulsatilla'],
        'urticaria' => ['apis', 'urtica', 'rhus-tox', 'arsenicum', 'natrum-mur', 'astacus'],
        'hives' => ['apis', 'urtica', 'rhus-tox', 'arsenicum', 'natrum-mur'],
        'itching' => ['sulphur', 'arsenicum', 'mezereum', 'dolichos', 'graphites'],
        'fungal' => ['sepia', 'tellurium', 'sulphur', 'graphites', 'bacillinum'],
        'ringworm' => ['sepia', 'tellurium', 'bacillinum', 'sulphur', 'chrysarobinum'],
        'warts' => ['thuja', 'causticum', 'nitricum-acid', 'calcarea', 'antimonium-crud'],
        'abscess' => ['hepar', 'silica', 'mercurius', 'myristica', 'calcarea-sulph'],
        'boils' => ['hepar', 'silica', 'belladonna', 'tarentula-cub', 'arsenicum'],
        'herpes' => ['rhus-tox', 'natrum-mur', 'arsenicum', 'ranunculus-bulb', 'mezereum'],
        'vitiligo' => ['arsenicum-sulph-flavum', 'natrum-mur', 'sepia', 'arsenic-alb', 'syphilinum'],
        'alopecia' => ['phosphorus', 'arsenicum', 'lycopodium', 'natrum-mur', 'fluoric-acid'],
        'hair fall' => ['phosphorus', 'lycopodium', 'natrum-mur', 'arsenicum', 'silica', 'fluoric-acid'],
        
        // ============ GYNECOLOGICAL ============
        'irregular periods' => ['pulsatilla', 'sepia', 'cimicifuga', 'senecio', 'calcarea'],
        'dysmenorrhea' => ['magnesia-phos', 'colocynth', 'pulsatilla', 'cimicifuga', 'caulophyllum'],
        'painful periods' => ['magnesia-phos', 'colocynth', 'pulsatilla', 'cimicifuga', 'chamomilla'],
        'amenorrhea' => ['pulsatilla', 'sepia', 'senecio', 'natrum-mur', 'graphites'],
        'menorrhagia' => ['phosphorus', 'china', 'sabina', 'erigeron', 'trillium', 'hamamelis'],
        'heavy bleeding' => ['phosphorus', 'china', 'sabina', 'ipecac', 'hamamelis'],
        'leucorrhea' => ['sepia', 'pulsatilla', 'calcarea', 'kreosotum', 'alumina', 'borax'],
        'white discharge' => ['sepia', 'pulsatilla', 'calcarea', 'kreosotum', 'alumina'],
        'menopausal' => ['sepia', 'lachesis', 'sulphur', 'graphites', 'sanguinaria'],
        'hot flashes' => ['lachesis', 'sepia', 'sulphur', 'sanguinaria', 'glonoinum'],
        'fibroids' => ['calcarea', 'phosphorus', 'fraxinus', 'thlaspi', 'aurum-mur'],
        'ovarian cyst' => ['apis', 'lachesis', 'lycopodium', 'colocynth', 'palladium'],
        'infertility' => ['sepia', 'natrum-carb', 'borax', 'sabina', 'aurum'],
        
        // ============ MENTAL / EMOTIONAL ============
        'anxiety' => ['arsenicum', 'aconite', 'argentum-nit', 'gelsemium', 'phosphorus', 'kali-ars'],
        'depression' => ['aurum', 'natrum-mur', 'ignatia', 'sepia', 'arsenicum', 'kali-phos'],
        'irritable' => ['nux-vomica', 'chamomilla', 'bryonia', 'staphysagria', 'lycopodium'],
        'anger' => ['nux-vomica', 'chamomilla', 'staphysagria', 'bryonia', 'lycopodium', 'hepar'],
        'angry' => ['nux-vomica', 'chamomilla', 'staphysagria', 'colocynth', 'bryonia'],
        'rage' => ['chamomilla', 'stramonium', 'nux-vomica', 'belladonna', 'hepar', 'tarentula'],
        'violent anger' => ['stramonium', 'belladonna', 'hyoscyamus', 'nux-vomica', 'hepar'],
        'suppressed anger' => ['staphysagria', 'ignatia', 'colocynth', 'aurum', 'natrum-mur'],
        'blue lip' => ['lachesis', 'carbo-veg', 'cuprum', 'veratrum-alb', 'camphora', 'laurocerasus'],
        'blue lips' => ['lachesis', 'carbo-veg', 'cuprum', 'veratrum-alb', 'camphora', 'laurocerasus'],
        'lip turns blue' => ['lachesis', 'carbo-veg', 'cuprum', 'veratrum-alb', 'camphora'],
        'lips turn blue' => ['lachesis', 'carbo-veg', 'cuprum', 'veratrum-alb', 'camphora'],
        'cyanosis' => ['lachesis', 'carbo-veg', 'cuprum', 'laurocerasus', 'digitalis', 'antimonium-tart'],
        'cyanotic' => ['lachesis', 'carbo-veg', 'cuprum', 'laurocerasus', 'digitalis'],
        'blue from anger' => ['lachesis', 'chamomilla', 'nux-vomica', 'staphysagria', 'moschus'],
        'blue when angry' => ['lachesis', 'chamomilla', 'nux-vomica', 'staphysagria', 'moschus'],
        'ailments from anger' => ['staphysagria', 'colocynth', 'chamomilla', 'nux-vomica', 'bryonia', 'ignatia'],
        'insomnia' => ['coffea', 'nux-vomica', 'passiflora', 'kali-phos', 'arsenicum', 'ignatia'],
        'sleeplessness' => ['coffea', 'nux-vomica', 'arsenicum', 'passiflora', 'ignatia'],
        'grief' => ['ignatia', 'natrum-mur', 'phosphoric-acid', 'causticum'],
        'fear' => ['aconite', 'arsenicum', 'phosphorus', 'argentum-nit', 'lycopodium', 'calcarea'],
        'panic attack' => ['aconite', 'arsenicum', 'argentum-nit', 'gelsemium'],
        'stress' => ['nux-vomica', 'kali-phos', 'ignatia', 'arsenicum', 'phosphoric-acid'],
        'ocd' => ['arsenicum', 'natrum-mur', 'silicea', 'thuja', 'carcinosin'],
        'adhd' => ['tuberculinum', 'stramonium', 'hyoscyamus', 'tarentula', 'veratrum-alb'],
        
        // ============ GENERAL / CONSTITUTIONAL ============
        'chilly' => ['arsenicum', 'calcarea', 'silica', 'psorinum', 'nux-vomica', 'hepar'],
        'hot' => ['sulphur', 'pulsatilla', 'lachesis', 'apis', 'iodum', 'natrum-mur'],
        'weakness' => ['china', 'arsenicum', 'phosphoric-acid', 'gelsemium', 'carbo-veg', 'kali-carb'],
        'fatigue' => ['phosphoric-acid', 'kali-phos', 'china', 'arsenicum', 'carbo-veg', 'gelsemium'],
        'chronic fatigue' => ['phosphoric-acid', 'kali-phos', 'arsenicum', 'gelsemium', 'china'],
        'body pain' => ['rhus-tox', 'bryonia', 'arnica', 'eupatorium-perf', 'gelsemium', 'china'],
        'body ache' => ['rhus-tox', 'bryonia', 'arnica', 'eupatorium-perf', 'gelsemium'],
        'generalized pain' => ['rhus-tox', 'bryonia', 'arnica', 'colocynth', 'chamomilla'],
        'aching all over' => ['eupatorium-perf', 'gelsemium', 'rhus-tox', 'bryonia', 'arnica'],
        'soreness' => ['arnica', 'rhus-tox', 'baptisia', 'ruta', 'phytolacca'],
        'weight gain' => ['calcarea', 'graphites', 'phytolacca', 'fucus', 'antimonium-crud'],
        'weight loss' => ['arsenicum', 'phosphorus', 'iodum', 'natrum-mur', 'tuberculinum'],
        'fever' => ['belladonna', 'aconite', 'bryonia', 'ferrum-phos', 'gelsemium', 'arsenicum'],
        'recurrent infections' => ['silica', 'tuberculinum', 'calcarea', 'phosphorus', 'sulphur'],
        'low immunity' => ['silica', 'tuberculinum', 'arsenicum', 'phosphorus', 'calcarea'],
        'allergy' => ['natrum-mur', 'arsenicum', 'apis', 'histaminum', 'sulphur'],
        'hay fever' => ['allium-cepa', 'sabadilla', 'arsenicum', 'natrum-mur', 'euphrasia'],
        
        // ============ PREGNANCY RELATED ============
        'pregnancy' => ['sepia', 'pulsatilla', 'caulophyllum', 'cimicifuga', 'nux-vomica'],
        'morning sickness' => ['ipecac', 'sepia', 'nux-vomica', 'colchicum', 'symphoricarpus'],
        'nausea pregnancy' => ['ipecac', 'sepia', 'nux-vomica', 'colchicum', 'symphoricarpus'],
        'pregnancy body pain' => ['arnica', 'rhus-tox', 'cimicifuga', 'kali-carb', 'bellis'],
        'pregnant' => ['sepia', 'pulsatilla', 'caulophyllum', 'cimicifuga', 'phosphorus'],
    ];
    
    // Indicator descriptions for case analysis
    $indicatorDescriptions = [
        // Lab Findings
        'elevated ggt' => 'Elevated GGT - indicates liver/biliary stress',
        'elevated sgpt' => 'Elevated SGPT/ALT - liver enzyme elevation',
        'elevated sgot' => 'Elevated SGOT/AST - liver/cardiac enzyme elevation',
        'elevated bilirubin' => 'Elevated Bilirubin - jaundice/liver dysfunction',
        'elevated creatinine' => 'Elevated Creatinine - kidney function impairment',
        'elevated urea' => 'Elevated Blood Urea - kidney stress',
        'elevated uric acid' => 'Elevated Uric Acid - gout/kidney concern',
        'elevated glucose' => 'Elevated Blood Glucose - diabetes indicator',
        'elevated fasting glucose' => 'Elevated Fasting Glucose - diabetes mellitus indicator',
        'elevated pp glucose' => 'Elevated Post-Prandial Glucose - impaired glucose tolerance',
        'elevated hba1c' => 'Elevated HbA1c - poor long-term blood sugar control',
        'glycosuria' => 'Glycosuria (Sugar in Urine) - uncontrolled diabetes',
        'urine sugar' => 'Sugar in Urine - diabetes spillover',
        'elevated cholesterol' => 'Elevated Cholesterol - lipid metabolism issue',
        'elevated triglyceride' => 'Elevated Triglycerides - metabolic concern',
        'elevated ldl' => 'Elevated LDL - cardiovascular risk factor',
        'low hdl' => 'Low HDL - protective cholesterol deficient',
        'elevated tsh' => 'Elevated TSH - hypothyroidism indicator',
        'low tsh' => 'Low TSH - hyperthyroidism indicator',
        'elevated t3' => 'Elevated T3 - hyperthyroid state',
        'elevated t4' => 'Elevated T4 - hyperthyroid state',
        'low t3' => 'Low T3 - hypothyroid state',
        'low t4' => 'Low T4 - hypothyroid state',
        'low hemoglobin' => 'Low Hemoglobin - anemia',
        'high hemoglobin' => 'High Hemoglobin - polycythemia',
        'low rbc' => 'Low RBC - red cell deficiency',
        'low hematocrit' => 'Low Hematocrit/PCV - anemia indicator',
        'low pcv' => 'Low PCV - packed cell volume deficiency',
        'high wbc' => 'High WBC - infection/inflammation',
        'low wbc' => 'Low WBC (Leukopenia) - immunocompromised state, infection risk',
        'low platelet' => 'Low Platelets - bleeding risk',
        'high neutrophil' => 'High Neutrophils - bacterial infection/inflammation',
        'low lymphocyte' => 'Low Lymphocytes - immune suppression/viral causes',
        'elevated crp' => 'Elevated CRP - active inflammation',
        'elevated esr' => 'Elevated ESR - inflammation/infection marker',
        'low vitamin d' => 'Low Vitamin D - deficiency state',
        'low vitamin b12' => 'Low B12 - deficiency/neurological concern',
        'low iron' => 'Low Iron - iron deficiency',
        'low ferritin' => 'Low Ferritin - iron stores depleted',
        'elevated ferritin' => 'Elevated Ferritin - iron overload/inflammation',
        'elevated potassium' => 'Elevated Potassium - cardiac risk',
        'low potassium' => 'Low Potassium - muscle/cardiac concern',
        'elevated troponin' => 'Elevated Troponin - cardiac injury marker',
        'elevated bnp' => 'Elevated BNP - heart failure indicator',
        'elevated psa' => 'Elevated PSA - prostate concern',
        'elevated amylase' => 'Elevated Amylase - pancreatic issue',
        'elevated lipase' => 'Elevated Lipase - pancreatic issue',
        'proteinuria' => 'Protein in urine - kidney damage indicator',
        'hematuria' => 'Blood in urine - urinary tract concern',
        
        // Chief Complaints
        'headache' => 'Headache - primary symptom',
        'migraine' => 'Migraine - primary diagnosis',
        'right-sided headache' => 'Right-sided headache pattern',
        'left-sided headache' => 'Left-sided headache pattern',
        'vertigo' => 'Vertigo - balance/vestibular issue',
        'sinusitis' => 'Sinusitis - sinus inflammation',
        'cough' => 'Cough - respiratory symptom',
        'asthma' => 'Asthma - bronchospastic condition',
        'hypertension' => 'Hypertension - elevated blood pressure',
        'palpitation' => 'Palpitation - cardiac awareness',
        'acidity' => 'Acidity - gastric hyperacidity',
        'constipation' => 'Constipation - bowel irregularity',
        'diarrhea' => 'Diarrhea - loose motions',
        'joint pain' => 'Joint pain - articular symptoms',
        'back pain' => 'Back pain - spinal symptoms',
        'eczema' => 'Eczema - skin inflammation',
        'anxiety' => 'Anxiety - mental symptom',
        'depression' => 'Depression - mood disorder',
        'anger' => 'Anger/Irritability - mental symptom',
        'blue lips' => 'Blue lips (cyanosis) - circulation/oxygenation issue',
        'body pain' => 'Body pain - generalized musculoskeletal symptom',
        'pregnancy' => 'Pregnancy - gestational state',
        'diabetes' => 'Diabetes - metabolic disorder',
        'hypothyroid' => 'Hypothyroidism - low thyroid function',
        'hyperthyroid' => 'Hyperthyroidism - elevated thyroid function',
    ];
    
    $matchedRemedies = [];
    $remedyNamesToFetch = [];
    $detectedFindings = [];
    
    // PRIORITY WEIGHTING: Define high-priority lab markers
    $diabetesPriorityMarkers = ['hba1c', 'fasting glucose', 'pp glucose', 'glucose', 'urine glucose', 'glycosuria'];
    $highPriorityWeight = 8; // HbA1c, Fasting glucose get highest weight
    $mediumPriorityWeight = 5; // Other lab abnormalities
    $lowPriorityWeight = 3; // General lab findings
    
    // STEP 1: Process ABNORMAL lab findings (high priority for organ remedies)
    foreach ($abnormalFindings as $finding) {
        $findingKey = strtolower($finding['status'] . ' ' . $finding['parameter']);
        $paramLower = strtolower($finding['parameter']);
        
        // Determine weight based on clinical priority
        $scoreWeight = $lowPriorityWeight;
        foreach ($diabetesPriorityMarkers as $marker) {
            if (strpos($paramLower, $marker) !== false || strpos($marker, $paramLower) !== false) {
                $scoreWeight = $highPriorityWeight;
                break;
            }
        }
        // Severity-based bonus
        if (isset($finding['severity']) && $finding['severity'] === 'critical') {
            $scoreWeight += 5;
        } elseif (isset($finding['severity']) && $finding['severity'] === 'high') {
            $scoreWeight += 3;
        }
        
        // Check if we have remedies for this abnormal finding
        // FIXED: Use word-boundary matching to avoid false positives like "ast" in "fasting"
        foreach ($labIndicators as $indicator => $remedies) {
            // Extract the core parameter from the indicator (e.g., "elevated sgpt" -> "sgpt")
            $indicatorParam = preg_replace('/^(elevated|low|high|abnormal)\s+/i', '', $indicator);
            $findingParam = strtolower($finding['parameter']);
            
            // Check for meaningful match using word boundaries, not substring matching
            // This prevents "ast" from matching "fasting" or "sgot" from matching unrelated words
            $isMatch = false;
            
            // Direct parameter match (e.g., indicator "elevated glucose" matches finding "Fasting Glucose")
            if (preg_match('/\b' . preg_quote($indicatorParam, '/') . '\b/i', $findingParam)) {
                $isMatch = true;
            }
            // Reverse match: finding param appears as whole word in indicator
            elseif (preg_match('/\b' . preg_quote($findingParam, '/') . '\b/i', $indicator)) {
                $isMatch = true;
            }
            // Special handling for lab test abbreviations (exact match for short abbreviations)
            elseif (strlen($indicatorParam) <= 4) {
                // For short abbreviations like "ast", "alt", "tsh", require exact word match
                $indicatorWords = preg_split('/\s+/', strtolower($indicator));
                $findingWords = preg_split('/\s+/', $findingParam);
                $isMatch = count(array_intersect($indicatorWords, $findingWords)) > 0;
            }
            
            if ($isMatch) {
                
                $terms[] = $indicator;
                if (isset($indicatorDescriptions[$indicator])) {
                    $detectedFindings[$indicator] = $indicatorDescriptions[$indicator] . " (Value: {$finding['value']}, Normal: {$finding['range']})";
                } else {
                    $detectedFindings[$indicator] = ucfirst($finding['parameter']) . " is {$finding['status']} (Value: {$finding['value']}, Normal: {$finding['range']})";
                }
                
                foreach ($remedies as $remedy) {
                    if (!isset($matchedRemedies[$remedy])) {
                        $matchedRemedies[$remedy] = ['score' => 0, 'indicators' => [], 'remedy' => null, 'source' => 'lab'];
                        $remedyNamesToFetch[] = $remedy;
                    }
                    $matchedRemedies[$remedy]['score'] += $scoreWeight; // Priority-weighted scoring
                    $matchedRemedies[$remedy]['indicators'][] = "abnormal: $indicator";
                }
            }
        }
    }
    
    // STEP 2: Process CHIEF COMPLAINT and SYMPTOMS (HIGHEST priority in homeopathy)
    $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
    $diagnosis = strtolower($consultation['diagnosis'] ?? '');
    $symptoms = strtolower(
        ($consultation['present_illness'] ?? '') . ' ' .
        ($consultation['general_symptoms'] ?? '') . ' ' .
        ($consultation['particular_symptoms'] ?? '') . ' ' .
        ($consultation['mental_state'] ?? '') . ' ' .
        ($consultation['modalities'] ?? '')
    );
    
    $fullSymptomText = $chiefComplaint . ' ' . $diagnosis . ' ' . $symptoms;
    
    foreach ($symptomIndicators as $indicator => $remedies) {
        if (strpos($fullSymptomText, $indicator) !== false) {
            $terms[] = $indicator;
            if (isset($indicatorDescriptions[$indicator])) {
                $detectedFindings[$indicator] = $indicatorDescriptions[$indicator];
            }
            
            foreach ($remedies as $remedy) {
                if (!isset($matchedRemedies[$remedy])) {
                    $matchedRemedies[$remedy] = ['score' => 0, 'indicators' => [], 'remedy' => null, 'source' => 'symptom'];
                    $remedyNamesToFetch[] = $remedy;
                }
                // SYMPTOMS get HIGHER weight than lab findings (Law of Individualization)
                $matchedRemedies[$remedy]['score'] += 5;
                $matchedRemedies[$remedy]['indicators'][] = "symptom: $indicator";
                $matchedRemedies[$remedy]['source'] = 'symptom'; // Mark as symptom-based
            }
        }
    }
    
    // STEP 3: Check for specific modalities
    $modalities = strtolower($consultation['modalities'] ?? '');
    $modalityRemedies = [
        // Time Modalities
        'worse morning' => ['nux-vomica', 'natrum-mur', 'sanguinaria', 'bryonia', 'kali-bich', 'podophyllum'],
        'worse evening' => ['pulsatilla', 'phosphorus', 'lycopodium', 'sepia', 'sulphur'],
        'worse night' => ['arsenicum', 'mercurius', 'rhus-tox', 'aconite', 'drosera', 'syphilinum'],
        'worse 3am' => ['arsenicum', 'kali-carb', 'china', 'thuja'],
        'worse 4am' => ['nux-vomica', 'podophyllum', 'chelidonium'],
        'worse 10am' => ['sulphur', 'natrum-mur', 'gelsemium'],
        'worse 4pm' => ['lycopodium', 'carbo-veg'],
        'worse periodically' => ['arsenicum', 'china', 'cedron', 'natrum-mur'],
        
        // Weather/Temperature
        'worse cold' => ['arsenicum', 'hepar', 'nux-vomica', 'rhus-tox', 'silica', 'calcarea'],
        'worse heat' => ['apis', 'pulsatilla', 'lachesis', 'sulphur', 'iodum', 'glonoinum'],
        'worse wet' => ['rhus-tox', 'dulcamara', 'natrum-sulph', 'arsenicum'],
        'worse dry' => ['causticum', 'bryonia', 'nux-vomica'],
        'worse storm' => ['phosphorus', 'rhododendron', 'natrum-carb'],
        'worse sun' => ['glonoinum', 'natrum-carb', 'natrum-mur', 'lachesis'],
        'worse draft' => ['hepar', 'silica', 'calcarea', 'china'],
        
        // Position/Movement
        'worse motion' => ['bryonia', 'colchicum', 'nux-vomica', 'cocculux'],
        'better motion' => ['rhus-tox', 'pulsatilla', 'ferrum', 'lycopodium'],
        'worse rest' => ['rhus-tox', 'pulsatilla', 'ferrum', 'chamomilla'],
        'better rest' => ['bryonia', 'colchicum', 'nux-vomica', 'belladonna'],
        'worse lying' => ['arsenicum', 'drosera', 'hyoscyamus', 'pulsatilla'],
        'better lying' => ['bryonia', 'colchicum', 'nux-vomica'],
        'worse sitting' => ['rhus-tox', 'pulsatilla', 'sepia', 'aesculus'],
        'worse standing' => ['sulphur', 'valeriana', 'pulsatilla', 'aloe'],
        'worse walking' => ['bryonia', 'colchicum', 'arsenicum'],
        'better walking' => ['pulsatilla', 'ferrum', 'rhus-tox', 'sepia'],
        'worse bending' => ['dioscorea', 'belladonna'],
        'better bending' => ['colocynth', 'magnesia-phos', 'kali-carb'],
        
        // Touch/Pressure
        'worse touch' => ['apis', 'belladonna', 'china', 'hepar', 'lachesis'],
        'better touch' => ['bryonia', 'calc', 'magnesia-phos'],
        'worse pressure' => ['apis', 'china', 'lachesis', 'hepar'],
        'better pressure' => ['bryonia', 'magnesia-phos', 'china', 'colocynth', 'dioscorea'],
        'worse tight clothing' => ['lachesis', 'nux-vomica', 'lycopodium', 'argentum-nit'],
        
        // Food/Drink
        'worse eating' => ['nux-vomica', 'kali-bich', 'lycopodium', 'pulsatilla'],
        'better eating' => ['anacardium', 'chelidonium', 'phosphorus', 'sepia'],
        'worse fasting' => ['sulphur', 'phosphorus', 'china', 'lycopodium'],
        'worse cold drinks' => ['arsenicum', 'rhus-tox', 'carbo-veg'],
        'better cold drinks' => ['phosphorus', 'bryonia', 'cuprum'],
        'worse warm drinks' => ['phosphorus', 'pulsatilla', 'antimonium-crud'],
        'better warm drinks' => ['arsenicum', 'lycopodium', 'chelidonium'],
        'worse milk' => ['calcarea', 'natrum-carb', 'aethusa', 'magnesia-mur'],
        'worse fatty food' => ['pulsatilla', 'carbo-veg', 'thuja', 'cyclamen'],
        'worse sweets' => ['argentum-nit', 'lycopodium', 'sulphur'],
        
        // Light/Noise/Smell
        'worse light' => ['belladonna', 'natrum-mur', 'phosphorus', 'gelsemium', 'conium'],
        'better dark' => ['belladonna', 'sanguinaria', 'silica', 'phosphorus'],
        'worse noise' => ['belladonna', 'nux-vomica', 'coffea', 'theridion', 'china'],
        'better quiet' => ['belladonna', 'nux-vomica', 'gelsemium'],
        'worse odors' => ['colchicum', 'nux-vomica', 'sepia', 'phosphorus'],
        
        // Sleep
        'worse sleep' => ['lachesis', 'spongia', 'grindelia', 'phosphorus'],
        'better sleep' => ['phosphorus', 'sanguinaria', 'nux-vomica'],
        'worse after sleep' => ['lachesis', 'spongia', 'opium'],
        
        // Other
        'worse exertion' => ['arsenicum', 'china', 'gelsemium', 'phosphorus', 'digitalis'],
        'worse mental exertion' => ['natrum-carb', 'kali-phos', 'phosphorus', 'silica'],
        'better vomiting' => ['sanguinaria', 'iris', 'digitalis', 'nux-vomica'],
        'worse consolation' => ['natrum-mur', 'sepia', 'silica', 'ignatia'],
        'better consolation' => ['pulsatilla', 'phosphorus'],
        'worse company' => ['natrum-mur', 'sepia', 'ignatia'],
        'better company' => ['phosphorus', 'pulsatilla', 'arsenicum'],
        'worse menstruation' => ['sepia', 'cimicifuga', 'magnesia-carb', 'lachesis'],
        'better menstruation' => ['lachesis', 'zincum'],
    ];
    
    foreach ($modalityRemedies as $modality => $remedies) {
        if (strpos($modalities, str_replace(['worse ', 'better '], '', $modality)) !== false) {
            foreach ($remedies as $remedy) {
                if (!isset($matchedRemedies[$remedy])) {
                    $matchedRemedies[$remedy] = ['score' => 0, 'indicators' => [], 'remedy' => null, 'source' => 'modality'];
                    $remedyNamesToFetch[] = $remedy;
                }
                $matchedRemedies[$remedy]['score'] += 4; // Modalities are very important
                $matchedRemedies[$remedy]['indicators'][] = "modality: $modality";
            }
        }
    }
    
    // STEP 4: Thermal state
    $thermalState = strtolower($consultation['thermal_state'] ?? '');
    if ($thermalState === 'chilly') {
        $chillyRemedies = ['arsenicum', 'calcarea', 'silica', 'psorinum', 'nux-vomica', 'hepar', 'kali-carb'];
        foreach ($chillyRemedies as $remedy) {
            if (isset($matchedRemedies[$remedy])) {
                $matchedRemedies[$remedy]['score'] += 2; // Bonus for matching thermal
            }
        }
    } elseif ($thermalState === 'hot') {
        $hotRemedies = ['sulphur', 'pulsatilla', 'lachesis', 'apis', 'iodum', 'secale'];
        foreach ($hotRemedies as $remedy) {
            if (isset($matchedRemedies[$remedy])) {
                $matchedRemedies[$remedy]['score'] += 2;
            }
        }
    }
    
    // Fetch remedy details from database for matched remedies
    if (!empty($remedyNamesToFetch)) {
        foreach ($remedyNamesToFetch as $remedyName) {
            $pattern = '%' . strtolower($remedyName) . '%';
            $results = DB::query(
                "SELECT MIN(id) as id, remedy_name, 
                        MAX(common_name) as common_name,
                        GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms, 
                        GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications,
                        GROUP_CONCAT(DISTINCT book_reference SEPARATOR '; ') as book_reference
                 FROM remedies 
                 WHERE LOWER(remedy_name) LIKE ?
                 GROUP BY remedy_name
                 LIMIT 1",
                [$pattern]
            );
            if (!empty($results)) {
                $key = strtolower($remedyName);
                if (isset($matchedRemedies[$key])) {
                    $matchedRemedies[$key]['remedy'] = $results[0];
                }
            }
        }
    }
    
    // CLINICAL PLAUSIBILITY CHECK
    // Add warnings for extreme values that don't match symptoms
    $clinicalWarnings = [];
    
    foreach ($abnormalFindings as $finding) {
        $paramLower = strtolower($finding['parameter']);
        $severity = $finding['severity'] ?? 'moderate';
        
        if ($severity === 'critical') {
            // Check if symptoms support critical lab findings
            $symptomText = strtolower(
                ($consultation['chief_complaint'] ?? '') . ' ' .
                ($consultation['present_illness'] ?? '') . ' ' .
                ($consultation['general_symptoms'] ?? '')
            );
            
            // WBC critically low - should see infection/fever symptoms
            if (strpos($paramLower, 'wbc') !== false && $finding['status'] === 'low') {
                $hasInfectionSymptoms = preg_match('/fever|infection|chills|sepsis|weak/i', $symptomText);
                if (!$hasInfectionSymptoms) {
                    $clinicalWarnings[] = [
                        'parameter' => $finding['parameter'],
                        'message' => 'CRITICAL VALUE: Severe leukopenia detected but no infection symptoms reported. Verify value or check for data entry error.',
                        'severity' => 'critical'
                    ];
                }
            }
            
            // Glucose critically high - should see diabetes symptoms
            if (strpos($paramLower, 'glucose') !== false && $finding['status'] === 'elevated') {
                $hasDiabetesSymptoms = preg_match('/thirst|urination|polyuria|fatigue|blurred|weight/i', $symptomText);
                if (!$hasDiabetesSymptoms) {
                    $clinicalWarnings[] = [
                        'parameter' => $finding['parameter'],
                        'message' => 'Severely elevated glucose without typical diabetes symptoms. Consider fasting state or acute illness.',
                        'severity' => 'warning'
                    ];
                }
            }
        }
    }
    
    return [
        'terms' => $terms, 
        'matched_remedies' => $matchedRemedies,
        'detected_findings' => $detectedFindings,
        'abnormal_labs' => $abnormalFindings,
        'clinical_warnings' => $clinicalWarnings
    ];
}

/**
 * Detect abnormal lab values by parsing the lab report text
 * Returns array of abnormal findings with parameter, value, range, and status
 */
function detectAbnormalLabValues($labText) {
    $abnormalFindings = [];
    $lines = preg_split('/[\r\n]+/', $labText);
    
    // Common lab parameters with their typical reference ranges
    // Format: 'parameter_pattern' => ['min' => X, 'max' => Y, 'name' => 'Display Name']
    $labRanges = [
        // Liver Function Tests (use word boundaries to avoid matching inside other words)
        'sgpt|\balt\b' => ['min' => 7, 'max' => 56, 'name' => 'SGPT/ALT', 'unit' => 'U/L'],
        'sgot|\bast\b|aspartate' => ['min' => 10, 'max' => 40, 'name' => 'SGOT/AST', 'unit' => 'U/L'],
        'ggt|gamma.?glutamyl' => ['min' => 9, 'max' => 48, 'name' => 'GGT', 'unit' => 'U/L'],
        'alkaline.?phosphatase|alp' => ['min' => 44, 'max' => 147, 'name' => 'Alkaline Phosphatase', 'unit' => 'U/L'],
        'bilirubin.?total|total.?bilirubin' => ['min' => 0.1, 'max' => 1.2, 'name' => 'Total Bilirubin', 'unit' => 'mg/dL'],
        'bilirubin.?direct|direct.?bilirubin|conjugated' => ['min' => 0, 'max' => 0.3, 'name' => 'Direct Bilirubin', 'unit' => 'mg/dL'],
        'bilirubin.?indirect|indirect.?bilirubin|unconjugated' => ['min' => 0.1, 'max' => 0.9, 'name' => 'Indirect Bilirubin', 'unit' => 'mg/dL'],
        
        // Proteins
        'albumin' => ['min' => 3.5, 'max' => 5.5, 'name' => 'Albumin', 'unit' => 'g/dL'],
        'globulin' => ['min' => 2.0, 'max' => 3.5, 'name' => 'Globulin', 'unit' => 'g/dL'],
        'a.?g.?ratio|albumin.?globulin' => ['min' => 1.0, 'max' => 2.5, 'name' => 'A/G Ratio', 'unit' => ''],
        'total.?protein' => ['min' => 6.0, 'max' => 8.3, 'name' => 'Total Protein', 'unit' => 'g/dL'],
        
        // Kidney Function Tests
        'creatinine' => ['min' => 0.6, 'max' => 1.2, 'name' => 'Creatinine', 'unit' => 'mg/dL'],
        'blood.?urea|urea(?!.?nitrogen)' => ['min' => 11, 'max' => 45, 'name' => 'Blood Urea', 'unit' => 'mg/dL'],
        'bun|urea.?nitrogen' => ['min' => 7, 'max' => 25, 'name' => 'BUN', 'unit' => 'mg/dL'],
        'uric.?acid' => ['min' => 2.5, 'max' => 7.0, 'name' => 'Uric Acid', 'unit' => 'mg/dL'],
        'egfr|gfr' => ['min' => 90, 'max' => 999, 'name' => 'eGFR', 'unit' => 'mL/min'],
        'cystatin' => ['min' => 0.5, 'max' => 1.0, 'name' => 'Cystatin C', 'unit' => 'mg/L'],
        
        // Diabetes / Blood Sugar
        'hba1c|glycosylated|glycated' => ['min' => 4.0, 'max' => 6.0, 'name' => 'HbA1c', 'unit' => '%'],
        'fasting.?glucose|glucose.?fasting|fbs|fbg' => ['min' => 70, 'max' => 100, 'name' => 'Fasting Glucose', 'unit' => 'mg/dL'],
        'random.?glucose|glucose.?random|rbs|rbg' => ['min' => 70, 'max' => 140, 'name' => 'Random Glucose', 'unit' => 'mg/dL'],
        'pp.?glucose|post.?prandial|ppbs|2.?hr' => ['min' => 70, 'max' => 140, 'name' => 'PP Glucose', 'unit' => 'mg/dL'],
        'fasting.?insulin' => ['min' => 2.6, 'max' => 24.9, 'name' => 'Fasting Insulin', 'unit' => 'μIU/mL'],
        'homa.?ir' => ['min' => 0, 'max' => 2.5, 'name' => 'HOMA-IR', 'unit' => ''],
        
        // Lipid Profile
        'total.?cholesterol|cholesterol.?total' => ['min' => 0, 'max' => 200, 'name' => 'Total Cholesterol', 'unit' => 'mg/dL'],
        'ldl' => ['min' => 0, 'max' => 100, 'name' => 'LDL', 'unit' => 'mg/dL'],
        'hdl' => ['min' => 40, 'max' => 999, 'name' => 'HDL', 'unit' => 'mg/dL'],
        'triglyceride|tg' => ['min' => 0, 'max' => 150, 'name' => 'Triglycerides', 'unit' => 'mg/dL'],
        'vldl' => ['min' => 0, 'max' => 30, 'name' => 'VLDL', 'unit' => 'mg/dL'],
        'tc.?hdl|cholesterol.?hdl.?ratio' => ['min' => 0, 'max' => 5.0, 'name' => 'TC/HDL Ratio', 'unit' => ''],
        
        // Thyroid Function Tests
        'tsh' => ['min' => 0.4, 'max' => 4.0, 'name' => 'TSH', 'unit' => 'mIU/L'],
        't3.?total|total.?t3' => ['min' => 80, 'max' => 200, 'name' => 'Total T3', 'unit' => 'ng/dL'],
        't4.?total|total.?t4' => ['min' => 5.0, 'max' => 12.0, 'name' => 'Total T4', 'unit' => 'μg/dL'],
        'free.?t3|ft3' => ['min' => 2.3, 'max' => 4.2, 'name' => 'Free T3', 'unit' => 'pg/mL'],
        'free.?t4|ft4' => ['min' => 0.8, 'max' => 1.8, 'name' => 'Free T4', 'unit' => 'ng/dL'],
        'anti.?tpo|tpo.?antibod' => ['min' => 0, 'max' => 34, 'name' => 'Anti-TPO', 'unit' => 'IU/mL'],
        'thyroglobulin|tg.?antibod' => ['min' => 0, 'max' => 115, 'name' => 'TG Antibody', 'unit' => 'IU/mL'],
        
        // CBC - Red Blood Cells
        'hemoglobin|hgb|hb(?!a1c)' => ['min' => 12.0, 'max' => 17.5, 'name' => 'Hemoglobin', 'unit' => 'g/dL'],
        'hematocrit|hct|pcv' => ['min' => 36, 'max' => 50, 'name' => 'Hematocrit/PCV', 'unit' => '%'],
        'rbc|red.?blood.?cell|erythrocyte' => ['min' => 4.0, 'max' => 5.5, 'name' => 'RBC', 'unit' => 'million/μL'],
        'mcv|mean.?corpus.?vol' => ['min' => 80, 'max' => 100, 'name' => 'MCV', 'unit' => 'fL'],
        'mch(?!c)|mean.?corpus.?hemo' => ['min' => 27, 'max' => 33, 'name' => 'MCH', 'unit' => 'pg'],
        'mchc|mean.?corpus.?hemo.?conc' => ['min' => 32, 'max' => 36, 'name' => 'MCHC', 'unit' => 'g/dL'],
        'rdw|red.?cell.?dist' => ['min' => 11.5, 'max' => 14.5, 'name' => 'RDW', 'unit' => '%'],
        
        // CBC - White Blood Cells
        'wbc|white.?blood|leucocyte|tlc' => ['min' => 4000, 'max' => 11000, 'name' => 'WBC/TLC', 'unit' => '/μL'],
        'neutrophil' => ['min' => 40, 'max' => 70, 'name' => 'Neutrophils', 'unit' => '%'],
        'lymphocyte' => ['min' => 20, 'max' => 40, 'name' => 'Lymphocytes', 'unit' => '%'],
        'monocyte' => ['min' => 2, 'max' => 10, 'name' => 'Monocytes', 'unit' => '%'],
        'eosinophil' => ['min' => 1, 'max' => 6, 'name' => 'Eosinophils', 'unit' => '%'],
        'basophil' => ['min' => 0, 'max' => 2, 'name' => 'Basophils', 'unit' => '%'],
        
        // CBC - Platelets
        'platelet|plt' => ['min' => 150000, 'max' => 400000, 'name' => 'Platelets', 'unit' => '/μL'],
        'mpv|mean.?platelet' => ['min' => 7.5, 'max' => 11.5, 'name' => 'MPV', 'unit' => 'fL'],
        
        // Electrolytes - Note: Be careful with abbreviations that could match units
        'sodium|na(?!\s*\d)' => ['min' => 136, 'max' => 145, 'name' => 'Sodium', 'unit' => 'mEq/L'],
        'potassium|k(?!idney)(?!g)' => ['min' => 3.5, 'max' => 5.0, 'name' => 'Potassium', 'unit' => 'mEq/L'],
        'chloride|cl(?!ass|ear|in)' => ['min' => 98, 'max' => 106, 'name' => 'Chloride', 'unit' => 'mEq/L'],
        'calcium|ca(?!rbon|lci)|serum.?calcium' => ['min' => 8.5, 'max' => 10.5, 'name' => 'Calcium', 'unit' => 'mg/dL'],
        'magnesium|serum.?mg(?=\s|:|\d)|^mg\s+\d' => ['min' => 1.7, 'max' => 2.2, 'name' => 'Magnesium', 'unit' => 'mg/dL'],
        'phosphorus|phosphate|serum.?phos' => ['min' => 2.5, 'max' => 4.5, 'name' => 'Phosphorus', 'unit' => 'mg/dL'],
        'bicarbonate|hco3' => ['min' => 22, 'max' => 28, 'name' => 'Bicarbonate', 'unit' => 'mEq/L'],
        
        // Inflammatory Markers
        'crp|c.?reactive' => ['min' => 0, 'max' => 3.0, 'name' => 'CRP', 'unit' => 'mg/L'],
        'hs.?crp|high.?sensitive.?crp' => ['min' => 0, 'max' => 1.0, 'name' => 'hs-CRP', 'unit' => 'mg/L'],
        'esr|sed.?rate|erythrocyte.?sed' => ['min' => 0, 'max' => 20, 'name' => 'ESR', 'unit' => 'mm/hr'],
        'ra.?factor|rheumatoid' => ['min' => 0, 'max' => 14, 'name' => 'RA Factor', 'unit' => 'IU/mL'],
        'ana|antinuclear' => ['min' => 0, 'max' => 1, 'name' => 'ANA', 'unit' => 'ratio'],
        
        // Vitamins & Minerals
        'vitamin.?d|25.?oh.?d|cholecalciferol' => ['min' => 30, 'max' => 100, 'name' => 'Vitamin D', 'unit' => 'ng/mL'],
        'vitamin.?b12|b12|cobalamin' => ['min' => 200, 'max' => 900, 'name' => 'Vitamin B12', 'unit' => 'pg/mL'],
        'folate|folic.?acid' => ['min' => 3, 'max' => 20, 'name' => 'Folate', 'unit' => 'ng/mL'],
        'iron|serum.?iron' => ['min' => 60, 'max' => 170, 'name' => 'Serum Iron', 'unit' => 'μg/dL'],
        'ferritin' => ['min' => 12, 'max' => 300, 'name' => 'Ferritin', 'unit' => 'ng/mL'],
        'tibc|total.?iron.?bind' => ['min' => 250, 'max' => 400, 'name' => 'TIBC', 'unit' => 'μg/dL'],
        'transferrin.?sat' => ['min' => 20, 'max' => 50, 'name' => 'Transferrin Saturation', 'unit' => '%'],
        
        // Hormones
        'prolactin' => ['min' => 2, 'max' => 25, 'name' => 'Prolactin', 'unit' => 'ng/mL'],
        'fsh|follicle.?stim' => ['min' => 1.5, 'max' => 12, 'name' => 'FSH', 'unit' => 'mIU/mL'],
        'lh|luteinizing' => ['min' => 1.2, 'max' => 10, 'name' => 'LH', 'unit' => 'mIU/mL'],
        'estradiol|e2' => ['min' => 10, 'max' => 400, 'name' => 'Estradiol', 'unit' => 'pg/mL'],
        'testosterone' => ['min' => 270, 'max' => 1070, 'name' => 'Testosterone', 'unit' => 'ng/dL'],
        'cortisol' => ['min' => 6, 'max' => 23, 'name' => 'Cortisol', 'unit' => 'μg/dL'],
        'dhea' => ['min' => 35, 'max' => 500, 'name' => 'DHEA-S', 'unit' => 'μg/dL'],
        'progesterone' => ['min' => 0.1, 'max' => 27, 'name' => 'Progesterone', 'unit' => 'ng/mL'],
        
        // Cardiac Markers
        'troponin' => ['min' => 0, 'max' => 0.04, 'name' => 'Troponin', 'unit' => 'ng/mL'],
        'ck.?mb|creatine.?kinase.?mb' => ['min' => 0, 'max' => 25, 'name' => 'CK-MB', 'unit' => 'U/L'],
        'ldh|lactate.?dehydro' => ['min' => 120, 'max' => 246, 'name' => 'LDH', 'unit' => 'U/L'],
        'bnp|brain.?natriuretic|nt.?probnp' => ['min' => 0, 'max' => 100, 'name' => 'BNP', 'unit' => 'pg/mL'],
        'homocysteine' => ['min' => 5, 'max' => 15, 'name' => 'Homocysteine', 'unit' => 'μmol/L'],
        
        // Coagulation
        'pt|prothrombin.?time' => ['min' => 11, 'max' => 14, 'name' => 'PT', 'unit' => 'seconds'],
        'inr|international.?norm' => ['min' => 0.8, 'max' => 1.2, 'name' => 'INR', 'unit' => ''],
        'aptt|ptt|partial.?thromb' => ['min' => 25, 'max' => 35, 'name' => 'aPTT', 'unit' => 'seconds'],
        'd.?dimer' => ['min' => 0, 'max' => 0.5, 'name' => 'D-Dimer', 'unit' => 'μg/mL'],
        'fibrinogen' => ['min' => 200, 'max' => 400, 'name' => 'Fibrinogen', 'unit' => 'mg/dL'],
        
        // Urine Tests
        'urine.?protein|protein.?urine|proteinuria' => ['min' => 0, 'max' => 0, 'name' => 'Urine Protein', 'unit' => ''],
        'urine.?glucose|glucose.?urine|glycosuria' => ['min' => 0, 'max' => 0, 'name' => 'Urine Glucose', 'unit' => ''],
        'urine.?rbc|rbc.?urine' => ['min' => 0, 'max' => 3, 'name' => 'Urine RBC', 'unit' => '/HPF'],
        'urine.?wbc|wbc.?urine|pus.?cell' => ['min' => 0, 'max' => 5, 'name' => 'Urine WBC/Pus', 'unit' => '/HPF'],
        'microalbumin|acr|albumin.?creatinine' => ['min' => 0, 'max' => 30, 'name' => 'Microalbumin/ACR', 'unit' => 'mg/g'],
        
        // Pancreatic
        'amylase' => ['min' => 25, 'max' => 125, 'name' => 'Amylase', 'unit' => 'U/L'],
        'lipase' => ['min' => 10, 'max' => 140, 'name' => 'Lipase', 'unit' => 'U/L'],
        
        // Cancer Markers
        'psa|prostate.?specific' => ['min' => 0, 'max' => 4.0, 'name' => 'PSA', 'unit' => 'ng/mL'],
        'cea|carcinoembr' => ['min' => 0, 'max' => 3.0, 'name' => 'CEA', 'unit' => 'ng/mL'],
        'ca.?125' => ['min' => 0, 'max' => 35, 'name' => 'CA-125', 'unit' => 'U/mL'],
        'afp|alpha.?feto' => ['min' => 0, 'max' => 10, 'name' => 'AFP', 'unit' => 'ng/mL'],
    ];
    
    // Parse lab text line by line looking for values
    foreach ($lines as $line) {
        $lineLower = strtolower($line);
        
        // Skip lines that look like headers, reference tables, or interpretations
        if (preg_match('/degree\s+of\s+control|normal\s+control|good\s+control|fair\s+control|poor\s+control/i', $line)) {
            continue; // Skip HbA1c interpretation table rows
        }
        if (preg_match('/^(note|comment|interpretation|remarks):/i', trim($line))) {
            continue; // Skip note sections
        }
        if (preg_match('/^\s*(<|>|≤|≥)\s*\d/i', trim($line))) {
            continue; // Skip reference range only lines
        }
        
        foreach ($labRanges as $pattern => $range) {
            // Check if this line contains the parameter name within first 30-40 chars
            // This prevents matching values that appear later in explanation text
            $lineStart = substr($lineLower, 0, 50);
            
            // Context-aware pattern matching: 
            // Skip general WBC/RBC patterns if this is a urine context
            $isUrineContext = preg_match('/urine|pus.?cell|\/hpf|hpf|microscop/i', $lineLower);
            // Check if this is a general blood WBC/RBC pattern (not urine-specific)
            $isGeneralBloodPattern = (
                strpos($pattern, 'wbc') === 0 || 
                strpos($pattern, 'white') === 0 || 
                strpos($pattern, 'leucocyte') === 0 ||
                strpos($pattern, 'rbc') === 0 ||
                strpos($pattern, 'red') === 0 ||
                strpos($pattern, 'erythrocyte') === 0
            ) && strpos($pattern, 'urine') === false;
            
            if ($isUrineContext && $isGeneralBloodPattern) {
                continue; // Skip general blood patterns in urine context - let urine-specific patterns match
            }
            
            if (preg_match("/($pattern)/i", $lineStart)) {
                // CRITICAL FIX: Skip lines that are clearly reference/interpretation text
                // These often contain years (2015, 2020) or explanatory text that could be misread as values
                if (preg_match('/\b(reference|recommendation|ada|who|guideline|criteria|interval|interpret|normal|note|advised|comment)/i', $lineLower)) {
                    // But allow lines that look like actual results (have : followed by number with unit)
                    if (!preg_match('/:\s*\d+\.?\d*\s*(mg|g|%|u\/l|iu|ml|mcg|μ|m?eq|mmol|cumm|\/μl|\/ul|lakh|thousand)/i', $line)) {
                        continue; // Skip reference/guideline lines
                    }
                }
                
                // Skip lines containing year patterns (1990-2030) that aren't lab values
                if (preg_match('/\b(19[89]\d|20[012]\d)\b/i', $lineLower)) {
                    // Check if this looks like a year reference rather than a lab value
                    if (preg_match('/\b(year|recommendation|guideline|ada|who|edition|version|standard)\b/i', $lineLower) ||
                        preg_match('/\b(19[89]\d|20[012]\d)\b(?!\s*(mg|g|%|u\/l|iu|ml|mcg))/i', $line)) {
                        continue; // Skip year-containing lines unless value has unit
                    }
                }
                
                // Try multiple patterns to extract numeric value from this line
                // Pattern 1: Value with unit immediately after (e.g., "12.5 g/dL", "85 U/L")
                // Pattern 2: Value after colon/equals (e.g., "Value: 123", "Result = 45.6")
                // Pattern 3: Value in tabular format (e.g., "SGPT     85    7-56")
                // Pattern 4: Value with H/L flag (e.g., "10.2 H", "85 L")
                // Pattern 5: Comma-separated numbers (e.g., "8,900", "150,000")
                
                $value = null;
                $valueStr = '';
                
                // For pattern matching, look for the value AFTER the parameter name
                // Find where the parameter name ends
                preg_match("/($pattern)/i", $line, $paramMatch);
                $paramEnd = $paramMatch[0] ? strpos($lineLower, strtolower($paramMatch[0])) + strlen($paramMatch[0]) : 0;
                $lineAfterParam = substr($line, $paramEnd);
                
                // Helper function to parse numeric value (handles commas)
                $parseNumericValue = function($str) {
                    // Remove commas from numbers like "8,900" -> "8900"
                    $cleaned = str_replace(',', '', $str);
                    return floatval($cleaned);
                };
                
                // First check for H/L flags which indicate abnormal (handles comma-separated numbers)
                if (preg_match('/\b(\d{1,3}(?:,\d{3})*\.?\d*|\d+\.?\d*)\s*[^\d]*(H|L|HIGH|LOW|↑|↓|\*)\b/i', $lineAfterParam, $flagMatch)) {
                    $value = $parseNumericValue($flagMatch[1]);
                    $valueStr = str_replace(',', '', $flagMatch[1]);
                }
                // Pattern with unit (handles comma-separated numbers)
                elseif (preg_match('/\b(\d{1,3}(?:,\d{3})*\.?\d*|\d+\.?\d*)\s*(?:' . preg_quote($range['unit'], '/') . '|mg\/dl|g\/dl|%|u\/l|iu\/l|iu\/ml|ml|ng\/ml|pg\/ml|μg\/dl|meq\/l|mmol\/l|cumm|\/μl|\/ul|mcg|μiu|miu)/i', $lineAfterParam, $valueMatch)) {
                    $value = $parseNumericValue($valueMatch[1]);
                    $valueStr = str_replace(',', '', $valueMatch[1]);
                }
                // Pattern after colon or equals (handles comma-separated numbers)
                elseif (preg_match('/^[:\s=]*(\d{1,3}(?:,\d{3})*\.?\d*|\d+\.?\d*)/i', $lineAfterParam, $valueMatch)) {
                    $value = $parseNumericValue($valueMatch[1]);
                    $valueStr = str_replace(',', '', $valueMatch[1]);
                }
                // Pattern: look for a numeric value that's likely the result (handles comma-separated)
                elseif (preg_match('/\s+(\d{1,3}(?:,\d{3})*\.?\d*|\d+\.?\d{0,2})(?:\s+|$)/i', $lineAfterParam, $valueMatch)) {
                    // Make sure this isn't part of a reference range (which often has dashes)
                    if (!preg_match('/^\s*\d+\.?\d*\s*-/', $lineAfterParam)) {
                        $value = $parseNumericValue($valueMatch[1]);
                        $valueStr = str_replace(',', '', $valueMatch[1]);
                    }
                }
                
                if ($value !== null) {
                    // Handle WBC and platelets which may be expressed in lakhs/thousands
                    // IMPROVED: Only multiply if "lakh", "lac", "thousand", "K" suffix is detected
                    $isUrinePattern = preg_match('/urine|pus/i', $pattern);
                    if (!$isUrinePattern && preg_match('/wbc|white|leucocyte|tlc|platelet|plt/i', $pattern)) {
                        // Check for explicit lakh/thousand notation in the line
                        $hasLakhSuffix = preg_match('/\b' . preg_quote($valueStr, '/') . '\s*(lakh|lac|L)\b/i', $lineAfterParam);
                        $hasThousandSuffix = preg_match('/\b' . preg_quote($valueStr, '/') . '\s*(thousand|K|x\s*10\^?3)\b/i', $lineAfterParam);
                        $hasMillionSuffix = preg_match('/\b' . preg_quote($valueStr, '/') . '\s*(million|mill|M|x\s*10\^?6)\b/i', $lineAfterParam);
                        
                        if ($hasLakhSuffix) {
                            $value *= 100000; // 2.4 lakh = 240,000
                        } elseif ($hasThousandSuffix) {
                            $value *= 1000; // 9.0 thousand = 9,000
                        } elseif ($hasMillionSuffix) {
                            $value *= 1000000;
                        } elseif ($value < 20 && preg_match('/lakh|lac/i', $line)) {
                            // Fallback: if "lakh" appears anywhere in line and value is small
                            $value *= 100000;
                        } elseif ($value < 20 && preg_match('/thousand|x\s*10\^?3/i', $line)) {
                            // Fallback: if "thousand" appears anywhere in line and value is small
                            $value *= 1000;
                        }
                        // Otherwise keep raw value (e.g., 9000 means 9000 /µL)
                    }
                    
                    // Handle percentage values that might be given differently
                    if ($range['unit'] === '%' && $value > 100) {
                        continue; // Skip obviously wrong percentages
                    }
                    
                    // Handle hemoglobin which should be between 4-20 typically
                    if (preg_match('/hemoglobin|hgb|hb(?!a1c)/i', $pattern) && ($value > 25 || $value < 3)) {
                        continue; // Skip obviously wrong hemoglobin values
                    }
                    
                    // CRITICAL FIX: Handle glucose values - skip values that look like years (1900-2030) or are unrealistic
                    // Maximum glucose ever recorded is around 2000 mg/dL but values >600 are rare
                    // Values like 2015, 2020, 2023 etc. are almost certainly years from comments
                    if (preg_match('/glucose|sugar|fbs|rbs|ppbs|fbg|rbg/i', $pattern)) {
                        // Skip 4-digit numbers that look like years (1990-2030)
                        if ($value >= 1990 && $value <= 2030) {
                            continue; // Almost certainly a year reference
                        }
                        // Skip any glucose value above 800 - extremely rare clinically
                        if ($value > 800) {
                            continue; // Unrealistic glucose value
                        }
                        // Skip values below 20 - likely errors
                        if ($value < 20) {
                            continue;
                        }
                    }
                    
                    // Handle HbA1c - should be between 3% and 20%
                    if (preg_match('/hba1c|glycosylated|glycated/i', $pattern) && ($value > 20 || $value < 3)) {
                        continue; // Skip obviously wrong HbA1c values
                    }
                    
                    // Check if abnormal
                    $status = null;
                    if ($value < $range['min']) {
                        $status = 'low';
                    } elseif ($value > $range['max']) {
                        $status = 'elevated';
                    }
                    
                    if ($status) {
                        // Check for duplicate before adding
                        $isDuplicate = false;
                        foreach ($abnormalFindings as $existing) {
                            if ($existing['parameter'] === $range['name']) {
                                $isDuplicate = true;
                                break;
                            }
                        }
                        
                        if (!$isDuplicate) {
                            // Determine severity level for critical value alerting
                            $severity = 'moderate'; // default
                            
                            // CRITICAL VALUE DETECTION - medical emergency levels
                            $criticalRanges = [
                                'WBC/TLC' => ['critical_low' => 1000, 'critical_high' => 30000],
                                'Platelets' => ['critical_low' => 50000, 'critical_high' => 1000000],
                                'Hemoglobin' => ['critical_low' => 7.0, 'critical_high' => 20.0],
                                'Fasting Glucose' => ['critical_low' => 50, 'critical_high' => 400],
                                'PP Glucose' => ['critical_low' => 50, 'critical_high' => 500],
                                'HbA1c' => ['critical_low' => 3.0, 'critical_high' => 14.0],
                                'Creatinine' => ['critical_low' => 0.3, 'critical_high' => 10.0],
                                'Potassium' => ['critical_low' => 2.5, 'critical_high' => 6.5],
                                'Sodium' => ['critical_low' => 120, 'critical_high' => 160],
                                'Troponin' => ['critical_low' => 0, 'critical_high' => 0.1],
                            ];
                            
                            if (isset($criticalRanges[$range['name']])) {
                                $critRange = $criticalRanges[$range['name']];
                                if ($value < $critRange['critical_low'] || $value > $critRange['critical_high']) {
                                    $severity = 'critical';
                                }
                            }
                            
                            // HIGH PRIORITY markers for diabetes management
                            $highPriorityParams = ['HbA1c', 'Fasting Glucose', 'PP Glucose', 'Urine Glucose'];
                            if (in_array($range['name'], $highPriorityParams) && $status === 'elevated') {
                                $severity = ($severity === 'critical') ? 'critical' : 'high';
                            }
                            
                            $abnormalFindings[] = [
                                'parameter' => $range['name'],
                                'value' => $valueStr . ' ' . $range['unit'],
                                'range' => $range['min'] . ' - ' . $range['max'] . ' ' . $range['unit'],
                                'status' => $status,
                                'severity' => $severity,
                                'raw_line' => trim($line)
                            ];
                        }
                    }
                }
                break; // Move to next line after finding a match
            }
        }
    }
    
    // Also check for explicit "HIGH" or "LOW" markers in the text
    // Enhanced to catch more patterns used in lab reports
    // But avoid section headers, noise words, and partial words from OCR
    $excludeWords = [
        // Section headers and report structure
        'test', 'tests', 'report', 'biochemistry', 'hematology', 'chemistry',
        'profile', 'panel', 'function', 'cbc', 'lft', 'kft', 'lipid', 'thyroid',
        'patient', 'date', 'sample', 'reference', 'range', 'normal', 'unit',
        'value', 'result', 'interpretation', 'comment', 'remarks', 'note',
        'section', 'department', 'laboratory', 'hospital', 'clinic', 'hospita',
        // Contact/Address info
        'email', 'emai', 'gmail', 'gmai', 'phone', 'contact', 'address',
        'campus', 'karnataka', 'mangalore', 'india', 'appointment',
        // Doctor/Staff names and titles
        'doctor', 'technician', 'lab tech', 'ravis', 'thunga', 'referred',
        // Document structure words
        'page', 'degree', 'control', 'contro', 'norma', 'fair', 'good', 'poor',
        'target', 'goal', 'criteria', 'beneficial', 'appropriate', 'glycemic',
        'concentration', 'associated', 'intake', 'paralle', 'parallel',
        'treatment', 'cortiso', 'cortisol', 'perfusion', 'renal', 'rena',
        'glomerular', 'catabolism', 'protein', 'levels', 'urea in',
        // Common partial/broken words from OCR
        'muc', 'stil', 'goa', 'beneficia', 'targeting', 'may be', 'may still',
        'when the', 'er than', 'is a', 'and is', 'or a'
    ];
    
    // DISABLED: These flag patterns cause too many false positives from OCR text
    // The numeric value parsing above is more reliable
    // DISABLED: Flag pattern detection causes false positives from OCR text artifacts
    // Only rely on numeric value comparison against reference ranges
    /*
    $flagPatterns = [
        // Match only clear lab parameter patterns with HIGH/LOW flags
        // Pattern: PARAMETER_NAME: VALUE (RANGE) [HIGH] or similar
        '/\b(hemoglobin|hb|wbc|rbc|platelet|sgpt|sgot|alt|ast|creatinine|urea|glucose|hba1c|tsh|cholesterol|triglyceride|ldl|hdl|bilirubin|albumin|ferritin|vitamin)\s*[:\-]?[^\[\]]*\[(HIGH|LOW|H|L)\]/i',
    ];
    
    foreach ($flagPatterns as $flagPattern) {
        if (preg_match_all($flagPattern, $labText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // For the specific lab parameter pattern, match[1] is the parameter, match[2] is the flag
                $param = trim($match[1] ?? '');
                $flag = trim($match[2] ?? '');
                
                // Determine status from flag
                $status = null;
                if (preg_match('/high|H/i', $flag)) {
                    $status = 'elevated';
                } elseif (preg_match('/low|L/i', $flag)) {
                    $status = 'low';
                }
                
                if (!$status || strlen($param) < 2) continue;
                
                // Map common abbreviations to full names
                $paramMap = [
                    'hb' => 'Hemoglobin', 'hemoglobin' => 'Hemoglobin',
                    'wbc' => 'WBC', 'rbc' => 'RBC', 'platelet' => 'Platelets',
                    'sgpt' => 'SGPT/ALT', 'alt' => 'SGPT/ALT',
                    'sgot' => 'SGOT/AST', 'ast' => 'SGOT/AST',
                    'creatinine' => 'Creatinine', 'urea' => 'Blood Urea',
                    'glucose' => 'Blood Glucose', 'hba1c' => 'HbA1c',
                    'tsh' => 'TSH', 'cholesterol' => 'Total Cholesterol',
                    'triglyceride' => 'Triglycerides', 'ldl' => 'LDL', 'hdl' => 'HDL',
                    'bilirubin' => 'Bilirubin', 'albumin' => 'Albumin',
                    'ferritin' => 'Ferritin', 'vitamin' => 'Vitamin'
                ];
                
                $normalizedParam = $paramMap[strtolower($param)] ?? ucfirst($param);
                
                // Avoid duplicates
                $exists = false;
                foreach ($abnormalFindings as $finding) {
                    if (stripos($finding['parameter'], $normalizedParam) !== false) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $abnormalFindings[] = [
                        'parameter' => $normalizedParam,
                        'value' => 'Marked as ' . $status,
                        'range' => 'Normal range',
                        'status' => $status
                    ];
                }
            }
        }
    }
    */
    
    // SPECIAL: Detect qualitative urine findings (+++, ++, +, Positive)
    // These are critical for diabetes detection
    $urineQualitativePatterns = [
        'sugar|glucose' => 'Urine Glucose',
        'protein|albumin' => 'Urine Protein',
        'ketone|acetone' => 'Urine Ketones',
    ];
    
    foreach ($urineQualitativePatterns as $pattern => $paramName) {
        // Look for urine context with qualitative positive result
        if (preg_match('/urine[^.]*(' . $pattern . ')[^.]*(\+{1,4}|positive|present|trace)/i', $labText, $match) ||
            preg_match('/(' . $pattern . ')[^.]*urine[^.]*(\+{1,4}|positive|present|trace)/i', $labText, $match)) {
            
            $qualValue = trim($match[2] ?? 'Positive');
            
            // Check for duplicates
            $exists = false;
            foreach ($abnormalFindings as $finding) {
                if (stripos($finding['parameter'], $paramName) !== false) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                $abnormalFindings[] = [
                    'parameter' => $paramName,
                    'value' => $qualValue,
                    'range' => 'Negative/Nil',
                    'status' => 'elevated',
                    'raw_line' => 'Qualitative detection'
                ];
            }
        }
    }
    
    return $abnormalFindings;
}

/**
 * Generate RAG suggestions from local database based on lab report
 * IMPROVED: Prioritizes symptoms over normal lab values
 */
function generateLabRAGSuggestions($labText, $consultation) {
    $extraction = extractLabMedicalTerms($labText, $consultation);
    $terms = $extraction['terms'];
    $preMatchedRemedies = $extraction['matched_remedies'];
    $detectedFindings = $extraction['detected_findings'] ?? [];
    $abnormalLabs = $extraction['abnormal_labs'] ?? [];
    $clinicalWarnings = $extraction['clinical_warnings'] ?? [];
    
    // Check if we have any symptom-based matches (priority) or only lab matches
    $hasSymptomMatches = false;
    $hasAbnormalLabMatches = false;
    
    foreach ($preMatchedRemedies as $remedy) {
        if (isset($remedy['source']) && $remedy['source'] === 'symptom') {
            $hasSymptomMatches = true;
        }
        foreach ($remedy['indicators'] ?? [] as $indicator) {
            if (strpos($indicator, 'abnormal:') === 0) {
                $hasAbnormalLabMatches = true;
            }
        }
    }
    
    if (empty($terms) && empty($preMatchedRemedies)) {
        return [
            'success' => false,
            'error' => 'No relevant symptoms or abnormal lab findings for analysis',
            'remedies' => []
        ];
    }
    
    // Search remedies database for additional matches based on chief complaint
    $remedyScores = $preMatchedRemedies;
    $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
    $diagnosis = strtolower($consultation['diagnosis'] ?? '');
    
    // Search for remedies matching the diagnosis/complaint in database
    if (!empty($chiefComplaint) || !empty($diagnosis)) {
        $searchTerms = array_filter([$chiefComplaint, $diagnosis]);
        foreach ($searchTerms as $term) {
            if (strlen($term) < 3) continue;
            
            $sql = "SELECT MIN(id) as id, remedy_name, 
                           MAX(common_name) as common_name,
                           GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms, 
                           GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications,
                           GROUP_CONCAT(DISTINCT book_reference SEPARATOR '; ') as book_reference
                    FROM remedies 
                    WHERE LOWER(keynote_symptoms) LIKE ? 
                       OR LOWER(clinical_indications) LIKE ?
                    GROUP BY remedy_name
                    LIMIT 20";
            
            $termPattern = '%' . $term . '%';
            $matches = DB::query($sql, [$termPattern, $termPattern]);
            
            foreach ($matches as $remedy) {
                $key = strtolower($remedy['remedy_name']);
                if (!isset($remedyScores[$key])) {
                    $remedyScores[$key] = ['score' => 0, 'indicators' => [], 'remedy' => $remedy, 'source' => 'symptom'];
                }
                
                // High score for matching chief complaint/diagnosis
                $remedyScores[$key]['score'] += 6;
                $remedyScores[$key]['indicators'][] = "diagnosis match: $term";
                $remedyScores[$key]['source'] = 'symptom';
                
                if (!isset($remedyScores[$key]['remedy'])) {
                    $remedyScores[$key]['remedy'] = $remedy;
                }
            }
        }
    }
    
    if (empty($remedyScores)) {
        return [
            'success' => false,
            'error' => 'No matching remedies found for symptoms or lab findings',
            'remedies' => []
        ];
    }
    
    // ============================================================================
    // CLINICAL INTELLIGENCE ANALYSIS
    // ============================================================================
    
    // Analyze comorbidities and disease patterns
    $comorbidityAnalysis = analyzeComorbidities($consultation, $abnormalLabs);
    
    // Analyze physical examination findings (BMI, BP, etc.)
    $physicalExamAnalysis = analyzePhysicalExam($consultation);
    
    // Analyze compliance and lifestyle factors
    $complianceAnalysis = analyzeComplianceFactors($consultation);
    
    // Analyze constitutional profile
    $constitutionalProfile = analyzeConstitutionalProfile($consultation);
    
    // Calculate disease severity
    $severityAnalysis = calculateDiseaseSeverity($abnormalLabs, $consultation, $comorbidityAnalysis);
    
    // Boost scores for remedies that match comorbidity patterns
    foreach ($comorbidityAnalysis['patterns'] as $pattern) {
        foreach ($pattern['remedies'] ?? [] as $patternRemedy) {
            $key = strtolower($patternRemedy);
            if (isset($remedyScores[$key])) {
                $remedyScores[$key]['score'] += 8;
                $remedyScores[$key]['indicators'][] = 'pattern: ' . $pattern['name'];
            } else {
                $remedyScores[$key] = [
                    'score' => 8,
                    'indicators' => ['pattern: ' . $pattern['name']],
                    'source' => 'comorbidity'
                ];
            }
        }
    }
    
    // Boost scores for constitutional remedies
    foreach ($constitutionalProfile['matching_remedies'] as $constRemedy) {
        $key = strtolower($constRemedy);
        if (isset($remedyScores[$key])) {
            $remedyScores[$key]['score'] += 5;
            $remedyScores[$key]['indicators'][] = 'constitutional: ' . ($constitutionalProfile['constitutional_type'] ?? 'matching');
        }
    }
    
    // Boost scores for compliance-related remedies (stress, lifestyle)
    foreach ($complianceAnalysis['constitutional_remedies'] as $compRemedy) {
        $key = strtolower($compRemedy);
        if (isset($remedyScores[$key])) {
            $remedyScores[$key]['score'] += 3;
            $remedyScores[$key]['indicators'][] = 'lifestyle: stress/compliance';
        }
    }
    
    // Sort by score
    uasort($remedyScores, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Get top 5 remedies
    $topRemedies = [];
    $maxScore = max(array_column($remedyScores, 'score'));
    if ($maxScore <= 0) $maxScore = 1;
    $count = 0;
    
    foreach ($remedyScores as $key => $data) {
        if ($count >= 5) break;
        
        $remedy = $data['remedy'] ?? null;
        $remedyName = $remedy ? $remedy['remedy_name'] : ucfirst($key);
        $commonName = $remedy['common_name'] ?? '';
        $reference = $remedy['book_reference'] ?? 'Local Database';
        $matchPercentage = min(95, round(($data['score'] / $maxScore) * 100));
        
        // Build reasoning from indicators - separate symptom vs lab
        $indicators = array_unique($data['indicators'] ?? []);
        $symptomIndicators = array_filter($indicators, fn($i) => strpos($i, 'symptom:') === 0 || strpos($i, 'modality:') === 0 || strpos($i, 'diagnosis') !== false);
        $labIndicators = array_filter($indicators, fn($i) => strpos($i, 'abnormal:') === 0);
        
        $reasoningParts = [];
        if (!empty($symptomIndicators)) {
            $reasoningParts[] = "Symptom match: " . implode(', ', array_map(fn($i) => str_replace(['symptom: ', 'modality: ', 'diagnosis match: '], '', $i), array_slice($symptomIndicators, 0, 3)));
        }
        if (!empty($labIndicators)) {
            $reasoningParts[] = "Lab finding: " . implode(', ', array_map(fn($i) => str_replace('abnormal: ', '', $i), array_slice($labIndicators, 0, 2)));
        }
        
        $reasoning = !empty($reasoningParts) ? implode(' | ', $reasoningParts) : "Matches clinical indications";
        
        $topRemedies[] = [
            'name' => $remedyName,
            'common_name' => $commonName,
            'match_percentage' => $matchPercentage,
            'potency' => '30C',
            'reasoning' => $reasoning,
            'reference' => $reference,
            'matched_indicators' => $indicators,
            'source' => $data['source'] ?? 'unknown'
        ];
        
        $count++;
    }
    
    // Build enhanced case analysis with clinical intelligence
    $clinicalIntelligence = [
        'comorbidity' => $comorbidityAnalysis,
        'physical_exam' => $physicalExamAnalysis,
        'compliance' => $complianceAnalysis,
        'constitutional' => $constitutionalProfile,
        'severity' => $severityAnalysis
    ];
    
    $caseAnalysis = buildRAGCaseAnalysis($detectedFindings, $terms, $topRemedies, $consultation, $abnormalLabs, $clinicalWarnings, $clinicalIntelligence);
    
    return [
        'success' => true,
        'remedies' => $topRemedies,
        'search_terms' => array_slice($terms, 0, 10),
        'case_analysis' => $caseAnalysis,
        'abnormal_labs' => $abnormalLabs,
        'clinical_warnings' => $clinicalWarnings,
        'has_symptom_focus' => $hasSymptomMatches,
        'clinical_intelligence' => $clinicalIntelligence
    ];
}

/**
 * Build a dynamic case analysis from RAG findings
 * ENHANCED: Includes comorbidity analysis, severity scoring, constitutional profile
 * REDESIGNED: Returns formatted HTML with comprehensive clinical insights
 */
function buildRAGCaseAnalysis($detectedFindings, $terms, $topRemedies, $consultation, $abnormalLabs = [], $clinicalWarnings = [], $clinicalIntelligence = []) {
    $html = '<div class="rag-analysis-container">';
    
    // Extract intelligence components
    $comorbidity = $clinicalIntelligence['comorbidity'] ?? [];
    $physicalExam = $clinicalIntelligence['physical_exam'] ?? [];
    $compliance = $clinicalIntelligence['compliance'] ?? [];
    $constitutional = $clinicalIntelligence['constitutional'] ?? [];
    $severity = $clinicalIntelligence['severity'] ?? [];
    
    // Check if there are any ABNORMAL lab values
    $hasAbnormalLabs = !empty($abnormalLabs);
    
    // ==================== DISEASE SEVERITY OVERVIEW ====================
    if (!empty($severity) && isset($severity['score'])) {
        $severityLevel = $severity['level'] ?? 'mild';
        $severityScore = $severity['score'] ?? 0;
        $severityClass = ($severityLevel === 'severe') ? 'severity-critical' : 
                        (($severityLevel === 'moderate') ? 'severity-moderate' : 'severity-mild');
        
        $html .= '<div class="analysis-section severity-overview-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-chart-line"></i> Disease Severity Assessment</h5>';
        $html .= '<div class="severity-dashboard">';
        
        // Severity gauge
        $html .= '<div class="severity-gauge ' . $severityClass . '">';
        $html .= '<div class="gauge-fill" style="width: ' . $severityScore . '%"></div>';
        $html .= '<div class="gauge-label">';
        $html .= '<span class="score">' . $severityScore . '</span>';
        $html .= '<span class="label">' . ucfirst($severityLevel) . ' Severity</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Component breakdown
        $html .= '<div class="severity-breakdown">';
        if (($severity['lab_severity'] ?? 0) > 0) {
            $html .= '<div class="breakdown-item"><i class="fas fa-vial"></i> Lab: +' . $severity['lab_severity'] . '</div>';
        }
        if (($severity['symptom_severity'] ?? 0) > 0) {
            $html .= '<div class="breakdown-item"><i class="fas fa-stethoscope"></i> Symptoms: +' . $severity['symptom_severity'] . '</div>';
        }
        if (($severity['comorbidity_severity'] ?? 0) > 0) {
            $html .= '<div class="breakdown-item"><i class="fas fa-layer-group"></i> Comorbidities: +' . $severity['comorbidity_severity'] . '</div>';
        }
        $html .= '</div>';
        
        // Critical findings
        if (!empty($severity['critical_findings'])) {
            $html .= '<div class="critical-findings">';
            $html .= '<strong><i class="fas fa-exclamation-triangle"></i> Critical Findings:</strong> ';
            $html .= htmlspecialchars(implode('; ', $severity['critical_findings']));
            $html .= '</div>';
        }
        
        $html .= '</div>'; // severity-dashboard
        $html .= '</div>'; // severity-overview-section
    }
    
    // ==================== COMORBIDITY ANALYSIS ====================
    if (!empty($comorbidity['comorbidities']) || !empty($comorbidity['patterns'])) {
        $html .= '<div class="analysis-section comorbidity-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-layer-group"></i> Comorbidity Analysis</h5>';
        
        // Risk level badge
        $riskLevel = $comorbidity['risk_level'] ?? 'low';
        $riskScore = $comorbidity['risk_score'] ?? 0;
        $riskClass = ($riskLevel === 'high') ? 'risk-high' : (($riskLevel === 'moderate') ? 'risk-moderate' : 'risk-low');
        
        $html .= '<div class="risk-badge ' . $riskClass . '">';
        $html .= '<i class="fas fa-heartbeat"></i> Overall Risk: <strong>' . ucfirst($riskLevel) . '</strong> (Score: ' . $riskScore . '/100)';
        $html .= '</div>';
        
        // List of detected conditions
        if (!empty($comorbidity['comorbidities'])) {
            $html .= '<div class="comorbidity-list">';
            $html .= '<strong>Detected Conditions:</strong>';
            $html .= '<div class="condition-tags">';
            foreach ($comorbidity['comorbidities'] as $condition) {
                $html .= '<span class="condition-tag"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($condition) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Clinical patterns (e.g., Metabolic Syndrome)
        if (!empty($comorbidity['patterns'])) {
            $html .= '<div class="clinical-patterns">';
            foreach ($comorbidity['patterns'] as $patternKey => $pattern) {
                $patternSeverity = $pattern['severity'] ?? 'moderate';
                $patternClass = ($patternSeverity === 'critical') ? 'pattern-critical' : 
                               (($patternSeverity === 'high') ? 'pattern-high' : 'pattern-moderate');
                
                $html .= '<div class="pattern-card ' . $patternClass . '">';
                $html .= '<div class="pattern-header">';
                $html .= '<i class="fas fa-project-diagram"></i> ';
                $html .= '<strong>' . htmlspecialchars($pattern['name']) . '</strong>';
                $html .= '<span class="pattern-severity">' . ucfirst($patternSeverity) . '</span>';
                $html .= '</div>';
                
                if (!empty($pattern['components'])) {
                    $html .= '<div class="pattern-components">';
                    $html .= '<small>Components: ' . htmlspecialchars(implode(' + ', $pattern['components'])) . '</small>';
                    $html .= '</div>';
                }
                
                if (!empty($pattern['implications'])) {
                    $html .= '<div class="pattern-implications">';
                    $html .= '<i class="fas fa-info-circle"></i> ' . htmlspecialchars($pattern['implications']);
                    $html .= '</div>';
                }
                
                if (!empty($pattern['remedies'])) {
                    $html .= '<div class="pattern-remedies">';
                    $html .= '<small><i class="fas fa-prescription"></i> Suggested: ' . htmlspecialchars(implode(', ', array_slice($pattern['remedies'], 0, 3))) . '</small>';
                    $html .= '</div>';
                }
                
                $html .= '</div>'; // pattern-card
            }
            $html .= '</div>';
        }
        
        $html .= '</div>'; // comorbidity-section
    }
    
    // ==================== PHYSICAL EXAMINATION FINDINGS ====================
    if (!empty($physicalExam['bmi']) || !empty($physicalExam['bp_systolic'])) {
        $html .= '<div class="analysis-section physical-exam-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-weight"></i> Physical Examination Analysis</h5>';
        
        $html .= '<div class="vital-stats-grid">';
        
        // BMI Card
        if (!empty($physicalExam['bmi'])) {
            $bmiCategory = $physicalExam['bmi_category'] ?? 'Normal';
            $bmiClass = ($bmiCategory === 'Obese Class III (Morbid)' || $bmiCategory === 'Obese Class II') ? 'vital-critical' : 
                       (($bmiCategory === 'Obese Class I' || $bmiCategory === 'Overweight') ? 'vital-warning' : 'vital-normal');
            
            $html .= '<div class="vital-card ' . $bmiClass . '">';
            $html .= '<div class="vital-icon"><i class="fas fa-weight"></i></div>';
            $html .= '<div class="vital-content">';
            $html .= '<div class="vital-value">' . $physicalExam['bmi'] . '</div>';
            $html .= '<div class="vital-label">BMI - ' . htmlspecialchars($bmiCategory) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // BP Card
        if (!empty($physicalExam['bp_systolic'])) {
            $bpCategory = $physicalExam['bp_category'] ?? 'Normal';
            $bpClass = (strpos($bpCategory, 'Crisis') !== false || strpos($bpCategory, 'Stage 2') !== false) ? 'vital-critical' : 
                      ((strpos($bpCategory, 'Stage 1') !== false || $bpCategory === 'Elevated') ? 'vital-warning' : 'vital-normal');
            
            $html .= '<div class="vital-card ' . $bpClass . '">';
            $html .= '<div class="vital-icon"><i class="fas fa-heartbeat"></i></div>';
            $html .= '<div class="vital-content">';
            $html .= '<div class="vital-value">' . $physicalExam['bp_systolic'] . '/' . $physicalExam['bp_diastolic'] . '</div>';
            $html .= '<div class="vital-label">BP - ' . htmlspecialchars($bpCategory) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Pulse Card
        if (!empty($physicalExam['pulse'])) {
            $pulse = $physicalExam['pulse'];
            $pulseClass = ($pulse > 100 || $pulse < 60) ? 'vital-warning' : 'vital-normal';
            
            $html .= '<div class="vital-card ' . $pulseClass . '">';
            $html .= '<div class="vital-icon"><i class="fas fa-wave-square"></i></div>';
            $html .= '<div class="vital-content">';
            $html .= '<div class="vital-value">' . $pulse . ' bpm</div>';
            $html .= '<div class="vital-label">Pulse</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // vital-stats-grid
        
        // Clinical implications
        if (!empty($physicalExam['clinical_implications'])) {
            $html .= '<div class="clinical-implications">';
            $html .= '<strong><i class="fas fa-clipboard-list"></i> Clinical Implications:</strong>';
            $html .= '<ul>';
            foreach ($physicalExam['clinical_implications'] as $implication) {
                $html .= '<li>' . htmlspecialchars($implication) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // physical-exam-section
    }
    
    // ==================== LAB REPORT SUMMARY ====================
    $html .= '<div class="analysis-section lab-summary-section">';
    $html .= '<h5 class="section-title"><i class="fas fa-vial"></i> Lab Report Summary</h5>';
    
    if ($hasAbnormalLabs) {
        $html .= '<div class="abnormal-values-container">';
        $html .= '<div class="alert-badge warning"><i class="fas fa-exclamation-triangle"></i> Abnormal Values Detected</div>';
        $html .= '<div class="lab-values-grid">';
        
        foreach ($abnormalLabs as $finding) {
            $severity = $finding['severity'] ?? 'moderate';
            $status = $finding['status'];
            
            // Determine CSS class based on status and severity
            $statusClass = ($status === 'low') ? 'value-low' : 'value-high';
            $severityClass = ($severity === 'critical') ? 'severity-critical' : (($severity === 'high') ? 'severity-high' : 'severity-moderate');
            $icon = ($status === 'low') ? 'fa-arrow-down' : 'fa-arrow-up';
            $severityIcon = ($severity === 'critical') ? 'fa-skull-crossbones' : (($severity === 'high') ? 'fa-exclamation-circle' : 'fa-info-circle');
            
            $html .= '<div class="lab-value-card ' . $statusClass . ' ' . $severityClass . '">';
            $html .= '<div class="lab-value-header">';
            $html .= '<span class="param-name">' . htmlspecialchars($finding['parameter']) . '</span>';
            $html .= '<span class="status-badge ' . $statusClass . '"><i class="fas ' . $icon . '"></i> ' . strtoupper($status) . '</span>';
            $html .= '</div>';
            $html .= '<div class="lab-value-body">';
            $html .= '<div class="current-value">' . htmlspecialchars($finding['value']) . '</div>';
            $html .= '<div class="reference-range"><i class="fas fa-ruler-horizontal"></i> Normal: ' . htmlspecialchars($finding['range']) . '</div>';
            $html .= '</div>';
            if ($severity === 'critical') {
                $html .= '<div class="severity-alert"><i class="fas ' . $severityIcon . '"></i> CRITICAL VALUE - Requires immediate attention</div>';
            } elseif ($severity === 'high') {
                $html .= '<div class="severity-warning"><i class="fas ' . $severityIcon . '"></i> High priority finding</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>'; // lab-values-grid
        $html .= '</div>'; // abnormal-values-container
    } else {
        $html .= '<div class="normal-values-container">';
        $html .= '<div class="alert-badge success"><i class="fas fa-check-circle"></i> All Values Normal</div>';
        $html .= '<p class="normal-message">All lab values appear to be within normal limits. No organ-specific remedies indicated based on lab findings alone.</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // lab-summary-section
    
    // ==================== CLINICAL WARNINGS ====================
    if (!empty($clinicalWarnings)) {
        $html .= '<div class="analysis-section clinical-warnings-section">';
        $html .= '<h5 class="section-title text-warning"><i class="fas fa-shield-alt"></i> Clinical Alerts</h5>';
        foreach ($clinicalWarnings as $warning) {
            $warnClass = ($warning['severity'] === 'critical') ? 'alert-critical' : 'alert-warning';
            $html .= '<div class="clinical-alert ' . $warnClass . '">';
            $html .= '<i class="fas fa-exclamation-triangle"></i>';
            $html .= '<div class="alert-content">';
            $html .= '<strong>' . htmlspecialchars($warning['parameter']) . ':</strong> ';
            $html .= htmlspecialchars($warning['message']);
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // ==================== PRIMARY FOCUS - CHIEF COMPLAINT ====================
    if (!empty($consultation)) {
        $html .= '<div class="analysis-section chief-complaint-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-stethoscope"></i> Primary Focus - Chief Complaint</h5>';
        $html .= '<div class="complaint-details">';
        
        if (!empty($consultation['chief_complaint'])) {
            $html .= '<div class="detail-row"><span class="detail-label"><i class="fas fa-comment-medical"></i> Presenting Complaint:</span><span class="detail-value primary-complaint">' . htmlspecialchars($consultation['chief_complaint']) . '</span></div>';
        }
        if (!empty($consultation['diagnosis'])) {
            $html .= '<div class="detail-row"><span class="detail-label"><i class="fas fa-diagnoses"></i> Diagnosis:</span><span class="detail-value">' . htmlspecialchars($consultation['diagnosis']) . '</span></div>';
        }
        if (!empty($consultation['present_illness'])) {
            $presentIllness = $consultation['present_illness'];
            if (strlen($presentIllness) > 200) {
                $presentIllness = substr($presentIllness, 0, 200) . '...';
            }
            $html .= '<div class="detail-row"><span class="detail-label"><i class="fas fa-history"></i> Present Illness:</span><span class="detail-value">' . htmlspecialchars($presentIllness) . '</span></div>';
        }
        if (!empty($consultation['modalities'])) {
            $html .= '<div class="detail-row"><span class="detail-label"><i class="fas fa-arrows-alt-h"></i> Modalities:</span><span class="detail-value modalities">' . htmlspecialchars($consultation['modalities']) . '</span></div>';
        }
        if (!empty($consultation['thermal_state'])) {
            $thermalIcon = ($consultation['thermal_state'] === 'hot') ? 'fa-temperature-high' : 'fa-snowflake';
            $html .= '<div class="detail-row"><span class="detail-label"><i class="fas ' . $thermalIcon . '"></i> Thermal State:</span><span class="detail-value thermal-badge thermal-' . $consultation['thermal_state'] . '">' . ucfirst($consultation['thermal_state']) . ' patient</span></div>';
        }
        
        $html .= '</div>'; // complaint-details
        $html .= '</div>'; // chief-complaint-section
    }
    
    // ==================== REMEDY SELECTION RATIONALE ====================
    if (!empty($topRemedies)) {
        $html .= '<div class="analysis-section remedy-rationale-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-prescription"></i> Remedy Selection Rationale</h5>';
        
        // Check if remedies are symptom-based or lab-based
        $symptomBasedRemedies = array_filter($topRemedies, fn($r) => ($r['source'] ?? '') === 'symptom');
        $labBasedRemedies = array_filter($topRemedies, fn($r) => ($r['source'] ?? '') === 'lab');
        
        $html .= '<div class="rationale-grid">';
        
        if (!empty($symptomBasedRemedies)) {
            $symptomRemedyNames = array_column(array_slice($symptomBasedRemedies, 0, 3), 'name');
            $html .= '<div class="rationale-card symptom-based">';
            $html .= '<div class="rationale-header"><i class="fas fa-user-md"></i> Symptom-Based Selection</div>';
            $html .= '<div class="remedy-names">' . implode(', ', array_map('htmlspecialchars', $symptomRemedyNames)) . '</div>';
            $html .= '<div class="rationale-note">Based on chief complaint, modalities, and symptom totality.</div>';
            $html .= '</div>';
        }
        
        if (!empty($labBasedRemedies) && $hasAbnormalLabs) {
            $labRemedyNames = array_column(array_slice($labBasedRemedies, 0, 3), 'name');
            $html .= '<div class="rationale-card lab-based">';
            $html .= '<div class="rationale-header"><i class="fas fa-flask"></i> Lab-Indicated Remedies</div>';
            $html .= '<div class="remedy-names">' . implode(', ', array_map('htmlspecialchars', $labRemedyNames)) . '</div>';
            $html .= '<div class="rationale-note">Supportive remedies based on abnormal lab findings.</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // rationale-grid
        
        // If no abnormal labs, emphasize symptom focus
        if (!$hasAbnormalLabs) {
            $html .= '<div class="important-note">';
            $html .= '<i class="fas fa-lightbulb"></i>';
            $html .= '<div class="note-content">';
            $html .= '<strong>Important:</strong> Since lab values are normal, remedy selection should focus on:';
            $html .= '<ol><li>Chief complaint and presenting symptoms</li><li>Modalities (aggravations and ameliorations)</li><li>Mental/emotional state</li><li>Constitutional features</li></ol>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // remedy-rationale-section
    }
    
    // ==================== CONSTITUTIONAL PROFILE ====================
    if (!empty($constitutional) && (!empty($constitutional['constitutional_type']) || !empty($constitutional['matching_remedies']))) {
        $html .= '<div class="analysis-section constitutional-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-user-circle"></i> Constitutional Profile</h5>';
        
        $html .= '<div class="constitutional-grid">';
        
        // Thermal state
        $thermal = $constitutional['thermal_type'] ?? 'unknown';
        $thermalIcon = ($thermal === 'hot') ? 'fa-temperature-high' : (($thermal === 'chilly') ? 'fa-snowflake' : 'fa-thermometer-half');
        $thermalClass = ($thermal === 'hot') ? 'thermal-hot' : (($thermal === 'chilly') ? 'thermal-cold' : 'thermal-neutral');
        
        $html .= '<div class="constitution-item ' . $thermalClass . '">';
        $html .= '<i class="fas ' . $thermalIcon . '"></i>';
        $html .= '<span class="const-label">Thermal</span>';
        $html .= '<span class="const-value">' . ucfirst($thermal) . '</span>';
        $html .= '</div>';
        
        // Thirst
        $thirst = $constitutional['thirst_pattern'] ?? 'normal';
        $html .= '<div class="constitution-item">';
        $html .= '<i class="fas fa-glass-water"></i>';
        $html .= '<span class="const-label">Thirst</span>';
        $html .= '<span class="const-value">' . ucfirst($thirst) . '</span>';
        $html .= '</div>';
        
        // Appetite
        $appetite = $constitutional['appetite_pattern'] ?? 'normal';
        $html .= '<div class="constitution-item">';
        $html .= '<i class="fas fa-utensils"></i>';
        $html .= '<span class="const-label">Appetite</span>';
        $html .= '<span class="const-value">' . ucfirst($appetite) . '</span>';
        $html .= '</div>';
        
        // Sleep
        $sleep = $constitutional['sleep_pattern'] ?? 'normal';
        $html .= '<div class="constitution-item">';
        $html .= '<i class="fas fa-bed"></i>';
        $html .= '<span class="const-label">Sleep</span>';
        $html .= '<span class="const-value">' . ucfirst(substr($sleep, 0, 15)) . (strlen($sleep) > 15 ? '...' : '') . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // constitutional-grid
        
        // Constitutional type
        if (!empty($constitutional['constitutional_type'])) {
            $html .= '<div class="constitutional-type">';
            $html .= '<strong><i class="fas fa-fingerprint"></i> Constitution:</strong> ';
            $html .= htmlspecialchars($constitutional['constitutional_type']);
            $html .= '</div>';
        }
        
        // Miasmatic tendency
        if (!empty($constitutional['miasmatic_tendency'])) {
            $html .= '<div class="miasmatic-tendency">';
            $html .= '<small><i class="fas fa-dna"></i> Miasmatic Tendency: ' . htmlspecialchars($constitutional['miasmatic_tendency']) . '</small>';
            $html .= '</div>';
        }
        
        // Matching constitutional remedies
        if (!empty($constitutional['matching_remedies'])) {
            $html .= '<div class="constitutional-remedies">';
            $html .= '<small><i class="fas fa-prescription"></i> Constitutional Remedies: ';
            $html .= htmlspecialchars(implode(', ', array_slice($constitutional['matching_remedies'], 0, 5)));
            $html .= '</small></div>';
        }
        
        $html .= '</div>'; // constitutional-section
    }
    
    // ==================== COMPLIANCE & LIFESTYLE ====================
    if (!empty($compliance) && (!empty($compliance['issues']) || !empty($compliance['lifestyle_factors']) || !empty($compliance['stress_indicators']))) {
        $html .= '<div class="analysis-section compliance-section">';
        $html .= '<h5 class="section-title"><i class="fas fa-clipboard-check"></i> Compliance & Lifestyle Assessment</h5>';
        
        // Compliance score
        $complianceScore = $compliance['compliance_score'] ?? 100;
        $complianceLevel = $compliance['compliance_level'] ?? 'good';
        $complianceClass = ($complianceLevel === 'poor') ? 'compliance-poor' : 
                          (($complianceLevel === 'moderate') ? 'compliance-moderate' : 'compliance-good');
        
        $html .= '<div class="compliance-gauge ' . $complianceClass . '">';
        $html .= '<div class="gauge-circle">';
        $html .= '<span class="gauge-value">' . $complianceScore . '%</span>';
        $html .= '</div>';
        $html .= '<div class="gauge-info">';
        $html .= '<strong>Compliance: ' . ucfirst($complianceLevel) . '</strong>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Compliance issues
        if (!empty($compliance['issues'])) {
            $html .= '<div class="compliance-issues">';
            $html .= '<strong><i class="fas fa-exclamation-circle text-warning"></i> Issues Detected:</strong>';
            $html .= '<div class="issue-tags">';
            foreach ($compliance['issues'] as $issue) {
                $html .= '<span class="issue-tag"><i class="fas fa-times-circle"></i> ' . htmlspecialchars($issue) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Lifestyle factors
        if (!empty($compliance['lifestyle_factors'])) {
            $html .= '<div class="lifestyle-factors">';
            $html .= '<strong><i class="fas fa-running"></i> Lifestyle Factors:</strong>';
            $html .= '<div class="factor-tags">';
            foreach ($compliance['lifestyle_factors'] as $factor) {
                $html .= '<span class="factor-tag">' . htmlspecialchars($factor) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Stress indicators
        if (!empty($compliance['stress_indicators'])) {
            $html .= '<div class="stress-indicators">';
            $html .= '<strong><i class="fas fa-brain text-danger"></i> Mental/Emotional:</strong>';
            $html .= '<div class="stress-tags">';
            foreach ($compliance['stress_indicators'] as $indicator) {
                $html .= '<span class="stress-tag">' . htmlspecialchars($indicator) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Recommendations
        if (!empty($compliance['recommendations'])) {
            $html .= '<div class="compliance-recommendations">';
            $html .= '<strong><i class="fas fa-lightbulb text-success"></i> Recommendations:</strong>';
            $html .= '<ul>';
            foreach ($compliance['recommendations'] as $rec) {
                $html .= '<li>' . htmlspecialchars($rec) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // compliance-section
    }
    
    // ==================== DISCLAIMER ====================
    $html .= '<div class="analysis-disclaimer">';
    $html .= '<i class="fas fa-info-circle"></i> ';
    $html .= '<em>Homeopathic remedy selection follows the Law of Individualization. Normal lab values do NOT indicate specific organ remedies. Treatment should be based on the totality of symptoms.</em>';
    $html .= '</div>';
    
    $html .= '</div>'; // rag-analysis-container
    
    return $html;
}

/**
 * Main function: Analyze lab report with both RAG and Gemini AI
 * 
 * @param string $lab_text The extracted lab report text
 * @param array $consultation The consultation data
 * @param int $doctor_id Optional doctor ID to check AI consent
 * @return array Analysis results
 */
function analyzeLabAndConsultation($lab_text, $consultation, $doctor_id = null) {
    $result = [
        'success' => true,
        'lab_text' => $lab_text,
        'consultation' => $consultation,
        'rag' => null,
        'gemini' => null,
        'remedies' => [],
        'analysis' => '',
        'ai_consent' => true
    ];
    
    // Check AI consent if doctor_id provided
    $aiConsentEnabled = true;
    if ($doctor_id) {
        $aiConsentEnabled = hasAIConsent($doctor_id);
        $result['ai_consent'] = $aiConsentEnabled;
    }
    
    // 1. RAG Analysis from local database (always runs - no external API)
    try {
        $ragResult = generateLabRAGSuggestions($lab_text, $consultation);
        $result['rag'] = $ragResult;
    } catch (Exception $e) {
        $result['rag'] = ['success' => false, 'error' => $e->getMessage(), 'remedies' => []];
    }
    
    // 2. Gemini AI Analysis (only if consent given)
    if ($aiConsentEnabled) {
        try {
            $gemini = new GeminiAPI();
            
            // PRIVACY: Anonymize data before sending to external AI
            $anonymizedLabText = anonymizeText($lab_text);
            $anonymizedConsultation = anonymizeForAI($consultation ?? []);
            
            // Prepare anonymized data for AI
            $consultationData = $anonymizedConsultation;
            $consultationData['lab_report'] = $anonymizedLabText;
            $consultationData['doctor_context'] = 'You are a homeopathic doctor. Analyze both the lab report and the specific consultation details for this patient. Each consultation may be different, so base your remedy suggestions on the provided consultation and lab findings.';
            
            // Log that we're sending anonymized data (for audit)
            error_log("Lab AI: Sending anonymized data to Gemini for doctor_id: " . ($doctor_id ?? 'unknown'));
            
            $geminiResult = $gemini->generateRemedySuggestions($consultationData);
            
            if (!empty($geminiResult['success']) && !empty($geminiResult['suggestions']['remedies'])) {
                $result['gemini'] = [
                    'success' => true,
                    'remedies' => $geminiResult['suggestions']['remedies'],
                    'case_analysis' => $geminiResult['suggestions']['case_analysis'] ?? '',
                    'cautions' => $geminiResult['suggestions']['cautions'] ?? ''
                ];
            } else {
                $result['gemini'] = [
                    'success' => false,
                    'error' => $geminiResult['error'] ?? 'No AI analysis available',
                    'remedies' => []
                ];
            }
        } catch (Exception $e) {
            $result['gemini'] = ['success' => false, 'error' => $e->getMessage(), 'remedies' => []];
        }
    } else {
        // AI consent not given - skip Gemini
        $result['gemini'] = [
            'success' => false,
            'error' => 'External AI disabled. Enable in Privacy Settings to use Gemini AI suggestions.',
            'remedies' => [],
            'consent_disabled' => true
        ];
    }
    
    // Combine results for backward compatibility
    $allRemedies = [];
    if (!empty($result['rag']['remedies'])) {
        $allRemedies = array_merge($allRemedies, $result['rag']['remedies']);
    }
    if (!empty($result['gemini']['remedies'])) {
        $allRemedies = array_merge($allRemedies, $result['gemini']['remedies']);
    }
    $result['remedies'] = $allRemedies;
    
    // Combined analysis
    $analyses = [];
    if (!empty($result['rag']['case_analysis'])) {
        $analyses[] = "**RAG Database Analysis:**\n" . $result['rag']['case_analysis'];
    }
    if (!empty($result['gemini']['case_analysis'])) {
        $analyses[] = "**Gemini AI Analysis:**\n" . $result['gemini']['case_analysis'];
    }
    $result['analysis'] = implode("\n\n", $analyses) ?: 'No analysis available.';
    
    return $result;
}
