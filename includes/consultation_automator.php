<?php
/**
 * Consultation Automator Class
 * 
 * Automatically tests consultation workflows by:
 * 1. Creating test consultations
 * 2. Validating RAG and Gemini AI outputs
 * 3. Generating remarks/quality assessments
 * 4. Keeping a detailed journal in PDF/XML format
 * 
 * @author CurenexAI
 * @version 1.0.0
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/vector_rag.php';
require_once __DIR__ . '/gemini_api.php';

class ConsultationAutomator {
    private $doctorId;
    private $results = [];
    private $startTime;
    private $testCases = [];
    private $journal = [];
    
    /**
     * Quality thresholds for AI output validation
     */
    private $thresholds = [
        'min_rag_remedies' => 3,
        'min_gemini_remedies' => 3,
        'min_confidence_score' => 0.5,
        'max_response_time' => 30, // seconds
        'min_explanation_length' => 50, // characters
    ];
    
    /**
     * Constructor
     * @param int $doctorId The doctor performing automated tests
     */
    public function __construct($doctorId) {
        $this->doctorId = $doctorId;
        $this->startTime = microtime(true);
        $this->initTestCases();
    }
    
    /**
     * Initialize standard test cases for automated testing
     */
    private function initTestCases() {
        $this->testCases = [
            [
                'name' => 'Respiratory - Cold & Cough',
                'patient' => [
                    'patient_name' => 'Test Patient - Respiratory',
                    'age' => 35,
                    'gender' => 'male',
                    'blood_group' => 'A+',
                    'allergies' => 'None'
                ],
                'consultation' => [
                    'chief_complaint' => 'Persistent cold and cough for 5 days',
                    'present_illness' => 'Started with watery nasal discharge, now thick yellow mucus. Frequent sneezing. Cough worse at night.',
                    'thermal_state' => 'chilly',
                    'thirst' => 'increased',
                    'appetite' => 'reduced',
                    'mental_state' => 'Irritable when disturbed',
                    'modalities' => 'Worse: cold air, night. Better: warm drinks, rest'
                ],
                'symptoms' => [
                    ['symptom_text' => 'Yellow thick nasal discharge', 'intensity' => 'moderate', 'location' => 'nose', 'sensation' => 'stuffiness'],
                    ['symptom_text' => 'Dry cough at night', 'intensity' => 'severe', 'location' => 'chest', 'modality' => 'worse at night'],
                    ['symptom_text' => 'Sneezing frequently', 'intensity' => 'moderate', 'location' => 'nose']
                ],
                'expected_remedies' => ['nux vomica', 'pulsatilla', 'arsenicum album', 'bryonia']
            ],
            [
                'name' => 'Digestive - Gastritis',
                'patient' => [
                    'patient_name' => 'Test Patient - Digestive',
                    'age' => 42,
                    'gender' => 'female',
                    'blood_group' => 'B+',
                    'allergies' => 'None'
                ],
                'consultation' => [
                    'chief_complaint' => 'Burning pain in stomach after eating',
                    'present_illness' => 'Chronic burning sensation in epigastric region. Worse after spicy food. Heartburn and acid reflux.',
                    'thermal_state' => 'warm',
                    'thirst' => 'increased for cold water',
                    'appetite' => 'variable',
                    'mental_state' => 'Anxious about health',
                    'modalities' => 'Worse: after eating, spicy food. Better: cold drinks, sitting up'
                ],
                'symptoms' => [
                    ['symptom_text' => 'Burning pain in stomach', 'intensity' => 'severe', 'location' => 'epigastrium', 'sensation' => 'burning'],
                    ['symptom_text' => 'Acid reflux', 'intensity' => 'moderate', 'location' => 'chest'],
                    ['symptom_text' => 'Bloating after meals', 'intensity' => 'moderate', 'location' => 'abdomen']
                ],
                'expected_remedies' => ['phosphorus', 'arsenicum album', 'nux vomica', 'lycopodium']
            ],
            [
                'name' => 'Musculoskeletal - Joint Pain',
                'patient' => [
                    'patient_name' => 'Test Patient - Rheumatic',
                    'age' => 55,
                    'gender' => 'male',
                    'blood_group' => 'O+',
                    'allergies' => 'Sulfa drugs'
                ],
                'consultation' => [
                    'chief_complaint' => 'Severe joint pain in knees and hips',
                    'present_illness' => 'Arthritis pain worse in damp weather. Morning stiffness that improves with movement. Pain travels from joint to joint.',
                    'thermal_state' => 'chilly',
                    'thirst' => 'normal',
                    'appetite' => 'good',
                    'mental_state' => 'Restless, cannot sit still',
                    'modalities' => 'Worse: rest, first motion, damp weather. Better: continued motion, warmth'
                ],
                'symptoms' => [
                    ['symptom_text' => 'Joint pain worse on first motion', 'intensity' => 'severe', 'location' => 'knees', 'modality' => 'better continued motion'],
                    ['symptom_text' => 'Morning stiffness', 'intensity' => 'moderate', 'location' => 'multiple joints'],
                    ['symptom_text' => 'Restless legs at night', 'intensity' => 'moderate', 'location' => 'legs']
                ],
                'expected_remedies' => ['rhus toxicodendron', 'bryonia', 'calcarea carbonica', 'ledum']
            ],
            [
                'name' => 'Mental - Anxiety & Stress',
                'patient' => [
                    'patient_name' => 'Test Patient - Mental',
                    'age' => 28,
                    'gender' => 'female',
                    'blood_group' => 'AB+',
                    'allergies' => 'None'
                ],
                'consultation' => [
                    'chief_complaint' => 'Severe anxiety and panic attacks',
                    'present_illness' => 'Constant worry about future. Anticipatory anxiety before events. Palpitations with anxiety.',
                    'thermal_state' => 'warm',
                    'thirst' => 'increased for small sips',
                    'appetite' => 'reduced due to anxiety',
                    'mental_state' => 'Fearful of death, needs company, fastidious',
                    'modalities' => 'Worse: alone, after midnight, cold. Better: warm drinks, company'
                ],
                'symptoms' => [
                    ['symptom_text' => 'Anticipatory anxiety', 'intensity' => 'severe', 'location' => 'mind'],
                    ['symptom_text' => 'Palpitations with anxiety', 'intensity' => 'moderate', 'location' => 'heart'],
                    ['symptom_text' => 'Fear of death', 'intensity' => 'severe', 'location' => 'mind']
                ],
                'expected_remedies' => ['arsenicum album', 'argentum nitricum', 'gelsemium', 'ignatia']
            ],
            [
                'name' => 'Skin - Eczema',
                'patient' => [
                    'patient_name' => 'Test Patient - Skin',
                    'age' => 18,
                    'gender' => 'male',
                    'blood_group' => 'A+',
                    'allergies' => 'Dust, pollens'
                ],
                'consultation' => [
                    'chief_complaint' => 'Chronic eczema with intense itching',
                    'present_illness' => 'Dry, scaly patches on arms and legs. Worse in winter. Itching worse at night, scratches till bleeding.',
                    'thermal_state' => 'warm',
                    'thirst' => 'low',
                    'appetite' => 'good',
                    'mental_state' => 'Sensitive, easily offended',
                    'modalities' => 'Worse: warmth of bed, bathing, wool. Better: cold applications, open air'
                ],
                'symptoms' => [
                    ['symptom_text' => 'Dry scaly eczema', 'intensity' => 'severe', 'location' => 'arms and legs', 'sensation' => 'dry, rough'],
                    ['symptom_text' => 'Intense itching worse warmth', 'intensity' => 'severe', 'modality' => 'worse warmth of bed'],
                    ['symptom_text' => 'Scratches till bleeding', 'intensity' => 'moderate', 'location' => 'skin']
                ],
                'expected_remedies' => ['sulphur', 'graphites', 'arsenicum album', 'petroleum']
            ]
        ];
    }
    
    /**
     * Run all automated test cases
     * @return array Results of all tests
     */
    public function runAllTests() {
        $this->logJournal('INFO', 'Automated Testing Started', [
            'doctor_id' => $this->doctorId,
            'test_cases' => count($this->testCases),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        foreach ($this->testCases as $index => $testCase) {
            $result = $this->runTestCase($testCase, $index + 1);
            $this->results[] = $result;
        }
        
        $this->logJournal('INFO', 'Automated Testing Completed', [
            'total_tests' => count($this->results),
            'passed' => count(array_filter($this->results, fn($r) => $r['overall_status'] === 'PASS')),
            'failed' => count(array_filter($this->results, fn($r) => $r['overall_status'] === 'FAIL')),
            'duration' => round(microtime(true) - $this->startTime, 2) . 's'
        ]);
        
        return [
            'success' => true,
            'results' => $this->results,
            'summary' => $this->generateSummary(),
            'journal' => $this->journal
        ];
    }
    
    /**
     * Run a single test case
     * @param array $testCase The test case data
     * @param int $testNumber Test sequence number
     * @return array Test result
     */
    public function runTestCase($testCase, $testNumber) {
        $result = [
            'test_number' => $testNumber,
            'name' => $testCase['name'],
            'patient_id' => null,
            'consultation_id' => null,
            'rag_result' => null,
            'gemini_result' => null,
            'validation' => [],
            'remarks' => [],
            'overall_status' => 'PENDING',
            'execution_time' => 0
        ];
        
        $testStart = microtime(true);
        
        try {
            // Step 1: Create test patient
            $patientData = array_merge($testCase['patient'], [
                'doctor_id' => $this->doctorId,
                'phone' => '9999900000',
                'email' => 'test_' . time() . '@test.com',
                'address' => 'Test Address',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $patientId = DB::insert('patients', $patientData);
            $result['patient_id'] = $patientId;
            
            $this->logJournal('DEBUG', 'Patient Created', ['patient_id' => $patientId, 'name' => $testCase['patient']['patient_name']]);
            
            // Step 2: Create consultation
            $consultationData = array_merge($testCase['consultation'], [
                'patient_id' => $patientId,
                'doctor_id' => $this->doctorId,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $consultationId = DB::insert('consultations', $consultationData);
            $result['consultation_id'] = $consultationId;
            
            $this->logJournal('DEBUG', 'Consultation Created', ['consultation_id' => $consultationId]);
            
            // Step 3: Add symptoms
            foreach ($testCase['symptoms'] as $symptom) {
                $symptomData = array_merge($symptom, [
                    'consultation_id' => $consultationId,
                    'category' => 'general'
                ]);
                DB::insert('symptoms', $symptomData);
            }
            
            // Step 4: Test RAG Output
            $result['rag_result'] = $this->testRAGOutput($consultationId);
            
            // Step 5: Test Gemini Output
            $result['gemini_result'] = $this->testGeminiOutput($consultationId);
            
            // Step 6: Validate outputs
            $result['validation'] = $this->validateOutputs($result, $testCase);
            
            // Step 7: Generate remarks
            $result['remarks'] = $this->generateRemarks($result, $testCase);
            
            // Determine overall status
            $result['overall_status'] = $this->determineOverallStatus($result['validation']);
            
        } catch (Exception $e) {
            $result['overall_status'] = 'ERROR';
            $result['remarks'][] = ['type' => 'error', 'message' => $e->getMessage()];
            $this->logJournal('ERROR', 'Test Case Failed', ['error' => $e->getMessage(), 'test' => $testCase['name']]);
        }
        
        $result['execution_time'] = round(microtime(true) - $testStart, 3);
        
        $this->logJournal('INFO', 'Test Case Completed', [
            'test' => $testCase['name'],
            'status' => $result['overall_status'],
            'duration' => $result['execution_time'] . 's'
        ]);
        
        return $result;
    }
    
    /**
     * Test RAG (Vector Search) output
     */
    private function testRAGOutput($consultationId) {
        $result = [
            'success' => false,
            'method' => 'unknown',
            'remedies' => [],
            'response_time' => 0,
            'error' => null
        ];
        
        $start = microtime(true);
        
        try {
            // Fetch consultation data
            $consultation = DB::queryOne(
                "SELECT c.*, p.patient_name, p.age, p.gender, p.blood_group, p.allergies
                 FROM consultations c
                 LEFT JOIN patients p ON c.patient_id = p.id
                 WHERE c.id = ?",
                [$consultationId]
            );
            
            $symptoms = DB::query(
                "SELECT symptom_text as symptom, intensity as severity, duration, location, sensation, modality
                 FROM symptoms WHERE consultation_id = ?",
                [$consultationId]
            );
            
            $consultation['symptoms'] = $symptoms ?: [];
            
            // Try Vector RAG
            $vectorRag = new VectorRAG();
            
            if ($vectorRag->hasEmbeddings('remedy')) {
                $ragResponse = $vectorRag->generateSuggestions($consultation);
                $result['method'] = 'vector_embeddings';
                $result['success'] = true;
                $result['remedies'] = $ragResponse['remedies'] ?? [];
                $result['rubrics_matched'] = $ragResponse['rubrics_matched'] ?? 0;
                $result['raw_response'] = $ragResponse;
            } else {
                $result['method'] = 'fallback_keyword';
                $result['error'] = 'Vector embeddings not available';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        $result['response_time'] = round(microtime(true) - $start, 3);
        
        return $result;
    }
    
    /**
     * Test Gemini AI output
     */
    private function testGeminiOutput($consultationId) {
        $result = [
            'success' => false,
            'model' => 'unknown',
            'remedies' => [],
            'case_analysis' => '',
            'response_time' => 0,
            'error' => null
        ];
        
        $start = microtime(true);
        
        try {
            if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
                $result['error'] = 'Gemini API not configured';
                return $result;
            }
            
            // Fetch consultation data
            $consultation = DB::queryOne(
                "SELECT c.*, p.patient_name, p.age, p.gender, p.blood_group, p.allergies
                 FROM consultations c
                 LEFT JOIN patients p ON c.patient_id = p.id
                 WHERE c.id = ?",
                [$consultationId]
            );
            
            $symptoms = DB::query(
                "SELECT symptom_text as symptom, intensity as severity, duration, location, sensation, modality
                 FROM symptoms WHERE consultation_id = ?",
                [$consultationId]
            );
            
            $consultation['symptoms'] = $symptoms ?: [];
            
            $gemini = new GeminiAPI();
            $geminiResponse = $gemini->generateRemedySuggestions($consultation);
            
            if ($geminiResponse['success']) {
                $result['success'] = true;
                $result['model'] = $geminiResponse['model'] ?? GEMINI_MODEL;
                $result['remedies'] = $geminiResponse['suggestions']['remedies'] ?? [];
                $result['case_analysis'] = $geminiResponse['suggestions']['case_analysis'] ?? '';
                $result['cautions'] = $geminiResponse['suggestions']['cautions'] ?? '';
                $result['raw_response'] = $geminiResponse;
            } else {
                $result['error'] = $geminiResponse['error'] ?? 'Unknown error';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        $result['response_time'] = round(microtime(true) - $start, 3);
        
        return $result;
    }
    
    /**
     * Validate AI outputs against expected results and thresholds
     */
    private function validateOutputs($result, $testCase) {
        $validation = [
            'rag_remedies_count' => [
                'status' => 'FAIL',
                'expected' => '>= ' . $this->thresholds['min_rag_remedies'],
                'actual' => count($result['rag_result']['remedies'] ?? [])
            ],
            'gemini_remedies_count' => [
                'status' => 'FAIL',
                'expected' => '>= ' . $this->thresholds['min_gemini_remedies'],
                'actual' => count($result['gemini_result']['remedies'] ?? [])
            ],
            'rag_response_time' => [
                'status' => 'FAIL',
                'expected' => '< ' . $this->thresholds['max_response_time'] . 's',
                'actual' => $result['rag_result']['response_time'] ?? 0
            ],
            'gemini_response_time' => [
                'status' => 'FAIL',
                'expected' => '< ' . $this->thresholds['max_response_time'] . 's',
                'actual' => $result['gemini_result']['response_time'] ?? 0
            ],
            'expected_remedy_match' => [
                'status' => 'FAIL',
                'expected' => implode(', ', $testCase['expected_remedies']),
                'actual' => '',
                'match_count' => 0
            ]
        ];
        
        // Validate RAG remedies count
        $ragCount = count($result['rag_result']['remedies'] ?? []);
        if ($ragCount >= $this->thresholds['min_rag_remedies']) {
            $validation['rag_remedies_count']['status'] = 'PASS';
        } elseif ($ragCount > 0) {
            $validation['rag_remedies_count']['status'] = 'WARN';
        }
        
        // Validate Gemini remedies count
        $geminiCount = count($result['gemini_result']['remedies'] ?? []);
        if ($geminiCount >= $this->thresholds['min_gemini_remedies']) {
            $validation['gemini_remedies_count']['status'] = 'PASS';
        } elseif ($geminiCount > 0) {
            $validation['gemini_remedies_count']['status'] = 'WARN';
        }
        
        // Validate response times
        if (($result['rag_result']['response_time'] ?? 100) < $this->thresholds['max_response_time']) {
            $validation['rag_response_time']['status'] = 'PASS';
        }
        
        if (($result['gemini_result']['response_time'] ?? 100) < $this->thresholds['max_response_time']) {
            $validation['gemini_response_time']['status'] = 'PASS';
        }
        
        // Check expected remedy matches
        $matchedRemedies = [];
        $allSuggestedRemedies = array_merge(
            array_column($result['rag_result']['remedies'] ?? [], 'name'),
            array_column($result['gemini_result']['remedies'] ?? [], 'name')
        );
        
        $allSuggestedRemedies = array_map('strtolower', $allSuggestedRemedies);
        
        foreach ($testCase['expected_remedies'] as $expected) {
            $expectedLower = strtolower($expected);
            foreach ($allSuggestedRemedies as $suggested) {
                if (strpos($suggested, $expectedLower) !== false) {
                    $matchedRemedies[] = $expected;
                    break;
                }
            }
        }
        
        $validation['expected_remedy_match']['actual'] = implode(', ', $matchedRemedies);
        $validation['expected_remedy_match']['match_count'] = count($matchedRemedies);
        
        if (count($matchedRemedies) >= 2) {
            $validation['expected_remedy_match']['status'] = 'PASS';
        } elseif (count($matchedRemedies) >= 1) {
            $validation['expected_remedy_match']['status'] = 'WARN';
        }
        
        return $validation;
    }
    
    /**
     * Generate remarks based on test results
     */
    private function generateRemarks($result, $testCase) {
        $remarks = [];
        
        // RAG Performance Remark
        if ($result['rag_result']['success']) {
            $ragCount = count($result['rag_result']['remedies'] ?? []);
            $ragMethod = $result['rag_result']['method'];
            
            if ($ragCount >= 5) {
                $remarks[] = [
                    'type' => 'success',
                    'source' => 'RAG',
                    'message' => "Excellent RAG performance: {$ragCount} remedies found using {$ragMethod}"
                ];
            } elseif ($ragCount >= 3) {
                $remarks[] = [
                    'type' => 'info',
                    'source' => 'RAG',
                    'message' => "Good RAG performance: {$ragCount} remedies found"
                ];
            } else {
                $remarks[] = [
                    'type' => 'warning',
                    'source' => 'RAG',
                    'message' => "Limited RAG results: Only {$ragCount} remedies found. Consider improving embeddings."
                ];
            }
        } else {
            $remarks[] = [
                'type' => 'error',
                'source' => 'RAG',
                'message' => "RAG failed: " . ($result['rag_result']['error'] ?? 'Unknown error')
            ];
        }
        
        // Gemini Performance Remark
        if ($result['gemini_result']['success']) {
            $geminiCount = count($result['gemini_result']['remedies'] ?? []);
            $hasAnalysis = !empty($result['gemini_result']['case_analysis']);
            
            if ($geminiCount >= 5 && $hasAnalysis) {
                $remarks[] = [
                    'type' => 'success',
                    'source' => 'Gemini',
                    'message' => "Excellent Gemini analysis: {$geminiCount} remedies with detailed case analysis"
                ];
            } elseif ($geminiCount >= 3) {
                $remarks[] = [
                    'type' => 'info',
                    'source' => 'Gemini',
                    'message' => "Good Gemini response: {$geminiCount} remedies suggested"
                ];
            } else {
                $remarks[] = [
                    'type' => 'warning',
                    'source' => 'Gemini',
                    'message' => "Limited Gemini results. Consider refining the prompt."
                ];
            }
        } else {
            $remarks[] = [
                'type' => 'error',
                'source' => 'Gemini',
                'message' => "Gemini API failed: " . ($result['gemini_result']['error'] ?? 'Unknown error')
            ];
        }
        
        // Cross-validation Remark
        $ragRemedies = array_map('strtolower', array_column($result['rag_result']['remedies'] ?? [], 'name'));
        $geminiRemedies = array_map('strtolower', array_column($result['gemini_result']['remedies'] ?? [], 'name'));
        
        $commonRemedies = array_intersect($ragRemedies, $geminiRemedies);
        
        if (count($commonRemedies) >= 2) {
            $remarks[] = [
                'type' => 'success',
                'source' => 'Cross-Validation',
                'message' => "Strong consensus: " . count($commonRemedies) . " remedies agreed by both RAG and Gemini"
            ];
        } elseif (count($commonRemedies) >= 1) {
            $remarks[] = [
                'type' => 'info',
                'source' => 'Cross-Validation',
                'message' => "Partial agreement between RAG and Gemini on " . count($commonRemedies) . " remedy(ies)"
            ];
        } else {
            $remarks[] = [
                'type' => 'warning',
                'source' => 'Cross-Validation',
                'message' => "No common remedies between RAG and Gemini - manual review recommended"
            ];
        }
        
        // Response time evaluation
        $totalTime = ($result['rag_result']['response_time'] ?? 0) + ($result['gemini_result']['response_time'] ?? 0);
        if ($totalTime > 20) {
            $remarks[] = [
                'type' => 'warning',
                'source' => 'Performance',
                'message' => "Slow total response time: {$totalTime}s - Consider optimization"
            ];
        }
        
        return $remarks;
    }
    
    /**
     * Determine overall test status
     */
    private function determineOverallStatus($validation) {
        $passCount = 0;
        $failCount = 0;
        $warnCount = 0;
        
        foreach ($validation as $check) {
            switch ($check['status']) {
                case 'PASS': $passCount++; break;
                case 'FAIL': $failCount++; break;
                case 'WARN': $warnCount++; break;
            }
        }
        
        if ($failCount > 2) return 'FAIL';
        if ($failCount > 0 || $warnCount > 2) return 'WARN';
        return 'PASS';
    }
    
    /**
     * Generate summary of all test results
     */
    private function generateSummary() {
        $passed = count(array_filter($this->results, fn($r) => $r['overall_status'] === 'PASS'));
        $failed = count(array_filter($this->results, fn($r) => $r['overall_status'] === 'FAIL'));
        $warned = count(array_filter($this->results, fn($r) => $r['overall_status'] === 'WARN'));
        $errors = count(array_filter($this->results, fn($r) => $r['overall_status'] === 'ERROR'));
        
        $avgRagTime = 0;
        $avgGeminiTime = 0;
        $totalRagRemedies = 0;
        $totalGeminiRemedies = 0;
        
        foreach ($this->results as $r) {
            $avgRagTime += $r['rag_result']['response_time'] ?? 0;
            $avgGeminiTime += $r['gemini_result']['response_time'] ?? 0;
            $totalRagRemedies += count($r['rag_result']['remedies'] ?? []);
            $totalGeminiRemedies += count($r['gemini_result']['remedies'] ?? []);
        }
        
        $count = count($this->results);
        
        return [
            'total_tests' => $count,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warned,
            'errors' => $errors,
            'pass_rate' => $count > 0 ? round(($passed / $count) * 100, 1) : 0,
            'avg_rag_response_time' => $count > 0 ? round($avgRagTime / $count, 3) : 0,
            'avg_gemini_response_time' => $count > 0 ? round($avgGeminiTime / $count, 3) : 0,
            'total_rag_remedies' => $totalRagRemedies,
            'total_gemini_remedies' => $totalGeminiRemedies,
            'total_execution_time' => round(microtime(true) - $this->startTime, 2)
        ];
    }
    
    /**
     * Add entry to journal
     */
    private function logJournal($level, $message, $data = []) {
        $this->journal[] = [
            'timestamp' => date('Y-m-d H:i:s.') . substr(microtime(), 2, 3),
            'level' => $level,
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Get the test results
     */
    public function getResults() {
        return $this->results;
    }
    
    /**
     * Get the journal entries
     */
    public function getJournal() {
        return $this->journal;
    }
    
    /**
     * Clean up test data (optional - call after exporting journal)
     */
    public function cleanup() {
        foreach ($this->results as $result) {
            if ($result['consultation_id']) {
                DB::execute("DELETE FROM symptoms WHERE consultation_id = ?", [$result['consultation_id']]);
                DB::execute("DELETE FROM consultations WHERE id = ?", [$result['consultation_id']]);
            }
            if ($result['patient_id']) {
                DB::execute("DELETE FROM patients WHERE id = ? AND patient_name LIKE 'Test Patient%'", [$result['patient_id']]);
            }
        }
        
        $this->logJournal('INFO', 'Test data cleaned up');
    }
}
