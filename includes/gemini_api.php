<?php
/**
 * Google Gemini API Handler
 * Handles communication with Google's Gemini AI API
 * Supports multiple API keys with automatic fallback
 */

class GeminiAPI {
    private $apiKey;
    private $apiKeys = [];
    private $currentKeyIndex = 0;
    private $model;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1';
    private static $exhaustedKeys = []; // Track exhausted keys across instances
    
    public function __construct($apiKey = null, $model = null) {
        // Check if Gemini API is enabled in system settings
        if (function_exists('isGeminiEnabled') && !isGeminiEnabled()) {
            throw new Exception('Gemini API is currently disabled by the administrator.');
        }
        
        // Load all available API keys
        if (defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS)) {
            $this->apiKeys = array_filter(GEMINI_API_KEYS); // Remove empty keys
        }
        
        // Add single key if provided or from config
        if ($apiKey) {
            $this->apiKey = $apiKey;
        } elseif (!empty($this->apiKeys)) {
            // Find first non-exhausted key
            $this->apiKey = $this->getNextAvailableKey();
        } else {
            $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        }
        
        $this->model = $model ?? GEMINI_MODEL;
        
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key is not configured. Please add your API key in config/config.php');
        }
    }
    
    /**
     * Get the next available (non-exhausted) API key
     */
    private function getNextAvailableKey() {
        $now = time();
        
        // Reset exhausted keys that are older than 1 hour
        foreach (self::$exhaustedKeys as $key => $timestamp) {
            if ($now - $timestamp > 3600) {
                unset(self::$exhaustedKeys[$key]);
            }
        }
        
        // Find first non-exhausted key
        foreach ($this->apiKeys as $index => $key) {
            $keyHash = md5($key);
            if (!isset(self::$exhaustedKeys[$keyHash])) {
                $this->currentKeyIndex = $index;
                return $key;
            }
        }
        
        // All keys exhausted, try the first one anyway (quota might have reset)
        $this->currentKeyIndex = 0;
        return $this->apiKeys[0] ?? '';
    }
    
    /**
     * Mark current key as exhausted and switch to next
     */
    private function switchToNextKey() {
        if (empty($this->apiKeys) || count($this->apiKeys) <= 1) {
            return false;
        }
        
        // Mark current key as exhausted
        $currentKeyHash = md5($this->apiKey);
        self::$exhaustedKeys[$currentKeyHash] = time();
        
        error_log("Gemini API: Key " . ($this->currentKeyIndex + 1) . " exhausted, switching to next key");
        
        // Get next available key
        $newKey = $this->getNextAvailableKey();
        
        if ($newKey && $newKey !== $this->apiKey) {
            $this->apiKey = $newKey;
            error_log("Gemini API: Switched to key " . ($this->currentKeyIndex + 1));
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if error indicates quota exhaustion
     */
    private function isQuotaError($errorMessage) {
        $quotaIndicators = [
            'quota',
            'rate limit',
            'resource exhausted',
            'RESOURCE_EXHAUSTED',
            '429',
            'too many requests',
            'exceeded',
            'limit reached'
        ];
        
        $lowerError = strtolower($errorMessage);
        foreach ($quotaIndicators as $indicator) {
            if (strpos($lowerError, strtolower($indicator)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate content using Gemini AI
     * 
     * @param string $prompt The prompt to send to Gemini
     * @param array $options Optional parameters (temperature, maxTokens, etc.)
     * @return array Response containing generated text and metadata
     */
    public function generateContent($prompt, $options = []) {
        $temperature = $options['temperature'] ?? AI_TEMPERATURE;
        $maxTokens = $options['maxTokens'] ?? AI_MAX_TOKENS;
        $maxRetries = count($this->apiKeys) > 0 ? count($this->apiKeys) : 1;
        
        // Ensure model name doesn't have 'models/' prefix (we'll add it in the URL)
        $modelName = str_replace('models/', '', $this->model);
        
        $requestData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)$temperature,
                'maxOutputTokens' => (int)$maxTokens,
                'topP' => 0.95,
                'topK' => 40
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
        
        $lastError = null;
        
        // Try with each available key
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $url = "{$this->baseUrl}/models/{$modelName}:generateContent?key={$this->apiKey}";
            
            // Debug log
            error_log("Gemini API: Attempt " . ($attempt + 1) . " with key " . ($this->currentKeyIndex + 1));
            
            $response = $this->makeRequest($url, $requestData);
            
            if ($response['success']) {
                return $this->parseResponse($response['data']);
            }
            
            $lastError = $response['error'] ?? 'Unknown error';
            
            // Check if it's a quota error and we have more keys
            if ($this->isQuotaError($lastError)) {
                if (!$this->switchToNextKey()) {
                    // No more keys available
                    break;
                }
                // Continue to retry with new key
                continue;
            }
            
            // Not a quota error, don't retry
            break;
        }
        
        throw new Exception($lastError ?? 'Failed to generate content');
    }
    
    /**
     * Generate remedy suggestions for homeopathic consultation
     * 
     * @param array $consultationData Patient and symptom data
     * @return array Suggested remedies with explanations
     */
    public function generateRemedySuggestions($consultationData) {
        $prompt = $this->buildHomeopathyPrompt($consultationData);
        
        try {
            $response = $this->generateContent($prompt, [
                'temperature' => 0.3, // Lower temperature for more focused medical suggestions
                'maxTokens' => 2048
            ]);
            
            return [
                'success' => true,
                'suggestions' => $this->parseRemedySuggestions($response['text']),
                'raw_response' => $response['text'],
                'model' => $this->model
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build a comprehensive prompt for homeopathic remedy suggestions
     */
    private function buildHomeopathyPrompt($data) {
        $prompt = "You are an expert homeopathic physician. Analyze this case and recommend remedies.\n\n";
        $prompt .= "PATIENT:\n";
        $prompt .= "Age: " . ($data['age'] ?? 'Unknown') . " years, ";
        $prompt .= "Gender: " . ($data['gender'] ?? 'Unknown') . "\n";
        if (!empty($data['allergies'])) {
            $prompt .= "Allergies: " . $data['allergies'] . "\n";
        }
        if (!empty($data['blood_group'])) {
            $prompt .= "Blood Group: " . $data['blood_group'] . "\n";
        }
        
        // Include lab report data if available - IMPORTANT for lab analysis
        if (!empty($data['lab_report'])) {
            $prompt .= "\n**LAB REPORT:**\n";
            $prompt .= $data['lab_report'] . "\n";
            $prompt .= "\nIMPORTANT: Analyze the above lab report values carefully. Identify any abnormal values (high/low) and suggest remedies specifically for those lab findings. Focus on:\n";
            $prompt .= "- Liver function tests (SGPT/ALT, SGOT/AST, Bilirubin, ALP, GGT)\n";
            $prompt .= "- Kidney function tests (Creatinine, Urea, BUN, Uric Acid)\n";
            $prompt .= "- Blood sugar (Fasting, PP, HbA1c)\n";
            $prompt .= "- Lipid profile (Cholesterol, LDL, HDL, Triglycerides)\n";
            $prompt .= "- CBC (Hemoglobin, WBC, Platelets, RBC)\n";
            $prompt .= "- Thyroid (TSH, T3, T4)\n";
            $prompt .= "- Inflammatory markers (CRP, ESR)\n";
            $prompt .= "- Vitamins and minerals (Vitamin D, B12, Iron, Calcium)\n\n";
        }
        
        $prompt .= "\nCHIEF COMPLAINT: " . ($data['chief_complaint'] ?? 'Not specified') . "\n";
        if (!empty($data['present_illness'])) {
            $prompt .= "Present Illness: " . $data['present_illness'] . "\n";
        }
        if (!empty($data['general_symptoms'])) {
            $prompt .= "General Symptoms: " . $data['general_symptoms'] . "\n";
        }
        if (!empty($data['particular_symptoms'])) {
            $prompt .= "Particular Symptoms: " . $data['particular_symptoms'] . "\n";
        }
        if (!empty($data['physical_examination'])) {
            $prompt .= "Physical Examination: " . $data['physical_examination'] . "\n";
        }
        if (!empty($data['mental_state'])) {
            $prompt .= "Mental & Emotional State: " . $data['mental_state'] . "\n";
        }
        if (!empty($data['thermal_state'])) {
            $prompt .= "Thermal State: " . $data['thermal_state'] . "\n";
        }
        if (!empty($data['thirst'])) {
            $prompt .= "Thirst: " . $data['thirst'] . "\n";
        }
        if (!empty($data['appetite'])) {
            $prompt .= "Appetite: " . $data['appetite'] . "\n";
        }
        if (!empty($data['past_history'])) {
            $prompt .= "Past Medical History: " . $data['past_history'] . "\n";
        }
        if (!empty($data['family_history'])) {
            $prompt .= "Family History: " . $data['family_history'] . "\n";
        }
        if (!empty($data['causation'])) {
            $prompt .= "Causation / Exciting Cause: " . $data['causation'] . "\n";
        }
        if (!empty($data['diagnosis'])) {
            $prompt .= "Diagnosis: " . $data['diagnosis'] . "\n";
        }
        if (!empty($data['notes'])) {
            $prompt .= "Clinical Notes: " . $data['notes'] . "\n";
        }
        if (!empty($data['symptoms']) && is_array($data['symptoms'])) {
            $prompt .= "\nSYMPTOMS:\n";
            foreach ($data['symptoms'] as $symptom) {
                $prompt .= "• " . $symptom['symptom'];
                if (!empty($symptom['severity'])) {
                    $prompt .= " (Intensity: " . $symptom['severity'] . ")";
                }
                if (!empty($symptom['duration'])) {
                    $prompt .= " - Duration: " . $symptom['duration'];
                }
                $prompt .= "\n";
            }
        }
        $prompt .= "\nProvide TOP 5 homeopathic remedies in JSON format. For each remedy, specify the reference book/source (e.g., 'Boenninghausen\'s Materia Medica & Repertory', 'Kent\'s Repertory', etc.) in a 'reference' field.\n\n";
        $prompt .= "{\n";
        $prompt .= '  "remedies": [\n';
        $prompt .= '    {"name": "Belladonna", "match_percentage": 85, "potency": "30C", "reasoning": "For sudden onset with heat", "matching_symptoms": ["headache", "heat"], "reference": "Boenninghausen\'s Materia Medica & Repertory"}\n';
        $prompt .= '  ],\n';
        $prompt .= '  "case_analysis": "Brief overview",\n';
        $prompt .= '  "differential": ["Alt remedy 1", "Alt remedy 2"],\n';
        $prompt .= '  "cautions": "Important notes"\n';
        $prompt .= "}\n\n";
        $prompt .= "Respond with ONLY the JSON, nothing else.";
        
        return $prompt;
    }
    
    /**
     * Parse remedy suggestions from Gemini response
     */
    private function parseRemedySuggestions($text) {
        // Try to extract JSON from the response
        // Gemini sometimes wraps JSON in markdown code blocks
        $jsonPattern = '/```json\s*(.*?)\s*```/s';
        if (preg_match($jsonPattern, $text, $matches)) {
            $jsonText = $matches[1];
        } else {
            // Try to find JSON object directly
            $jsonPattern = '/\{.*\}/s';
            if (preg_match($jsonPattern, $text, $matches)) {
                $jsonText = $matches[0];
            } else {
                $jsonText = $text;
            }
        }
        
        $suggestions = json_decode($jsonText, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON parsing fails, return raw text
            return [
                'remedies' => [],
                'raw_text' => $text,
                'parse_error' => json_last_error_msg()
            ];
        }
        
        // Normalize remedy data - ensure match_percentage and reference exist
        if (isset($suggestions['remedies']) && is_array($suggestions['remedies'])) {
            foreach ($suggestions['remedies'] as &$remedy) {
                // Map different confidence field names to match_percentage
                if (!isset($remedy['match_percentage'])) {
                    if (isset($remedy['confidence'])) {
                        $remedy['match_percentage'] = $remedy['confidence'];
                    } elseif (isset($remedy['score'])) {
                        $remedy['match_percentage'] = $remedy['score'];
                    } else {
                        $remedy['match_percentage'] = 75; // Default
                    }
                }

                // Ensure potency exists
                if (!isset($remedy['potency'])) {
                    $remedy['potency'] = '30C'; // Default
                }

                // Ensure reasoning exists
                if (!isset($remedy['reasoning'])) {
                    $remedy['reasoning'] = 'Indicated for this case based on symptom matching.';
                }

                // Ensure matching_symptoms is an array
                if (!isset($remedy['matching_symptoms']) || !is_array($remedy['matching_symptoms'])) {
                    $remedy['matching_symptoms'] = [];
                }

                // Ensure reference exists
                if (!isset($remedy['reference']) || empty($remedy['reference'])) {
                    $remedy['reference'] = "Boenninghausen's Materia Medica & Repertory";
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Make HTTP request to Gemini API
     */
    private function makeRequest($url, $data) {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error
            ];
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'HTTP Error ' . $httpCode;
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'http_code' => $httpCode
            ];
        }
        
        return [
            'success' => true,
            'data' => json_decode($response, true)
        ];
    }
    
    /**
     * Parse Gemini API response
     */
    private function parseResponse($data) {
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid response format from Gemini API');
        }
        
        $text = $data['candidates'][0]['content']['parts'][0]['text'];
        $finishReason = $data['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        
        return [
            'text' => $text,
            'finish_reason' => $finishReason,
            'safety_ratings' => $data['candidates'][0]['safetyRatings'] ?? []
        ];
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $response = $this->generateContent('Hello, respond with OK if you can read this.', [
                'temperature' => 0.1,
                'maxTokens' => 50
            ]);
            
            return [
                'success' => true,
                'message' => 'Gemini API connection successful',
                'model' => $this->model,
                'response' => $response['text']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
