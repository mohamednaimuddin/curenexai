<?php
/**
 * Dermo AI - Skin Analysis Helper Functions
 * Provides AI (Gemini Vision) + RAG based skin analysis and remedy suggestions
 * Enhanced with dermatological terminology from clinical dermatology education
 */

require_once __DIR__ . '/gemini_api.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/privacy_helper.php';

/**
 * Get dermatology terminology from database for enhanced analysis
 * @param string $category - Filter by category (optional): primary_lesion, secondary_lesion, distribution, configuration, sign, investigation, anatomy
 * @return array
 */
function getDermatologyTerms($category = null) {
    try {
        if ($category) {
            return DB::query(
                "SELECT * FROM dermatology_terms WHERE category = ? ORDER BY term",
                [$category]
            );
        }
        return DB::query("SELECT * FROM dermatology_terms ORDER BY category, term");
    } catch (Exception $e) {
        error_log('Error fetching dermatology terms: ' . $e->getMessage());
        return [];
    }
}

/**
 * Search dermatology terms by keyword
 * @param string $keyword
 * @return array
 */
function searchDermatologyTerms($keyword) {
    try {
        $searchTerm = '%' . strtolower($keyword) . '%';
        return DB::query(
            "SELECT * FROM dermatology_terms 
             WHERE LOWER(term) LIKE ? 
                OR LOWER(description) LIKE ? 
                OR LOWER(examples) LIKE ?
                OR LOWER(associated_conditions) LIKE ?
             ORDER BY category, term",
            [$searchTerm, $searchTerm, $searchTerm, $searchTerm]
        );
    } catch (Exception $e) {
        error_log('Error searching dermatology terms: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get lesion type information from database
 * @param string $lesionType
 * @return array|null
 */
function getLesionTypeInfo($lesionType) {
    try {
        return DB::queryOne(
            "SELECT * FROM dermatology_terms 
             WHERE (category = 'primary_lesion' OR category = 'secondary_lesion')
               AND LOWER(term) = ?",
            [strtolower($lesionType)]
        );
    } catch (Exception $e) {
        error_log('Error fetching lesion type: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get clinical sign information
 * @param string $signName
 * @return array|null
 */
function getClinicalSignInfo($signName) {
    try {
        $searchTerm = '%' . strtolower($signName) . '%';
        return DB::queryOne(
            "SELECT * FROM dermatology_terms 
             WHERE category = 'sign' AND LOWER(term) LIKE ?",
            [$searchTerm]
        );
    } catch (Exception $e) {
        error_log('Error fetching clinical sign: ' . $e->getMessage());
        return null;
    }
}

/**
 * Match symptoms text against dermatology database terms
 * Returns matched terms for enhanced analysis
 */
function matchDermatologyTerms($symptomsText) {
    $matches = [
        'lesion_types' => [],
        'distributions' => [],
        'configurations' => [],
        'signs' => [],
        'investigations' => []
    ];
    
    $symptomsLower = strtolower($symptomsText);
    
    try {
        // Get all terms from database
        $allTerms = getDermatologyTerms();
        
        foreach ($allTerms as $term) {
            $termLower = strtolower($term['term']);
            $descLower = strtolower($term['description'] ?? '');
            
            // Check if term or key description words are in symptoms
            if (strpos($symptomsLower, $termLower) !== false) {
                switch ($term['category']) {
                    case 'primary_lesion':
                    case 'secondary_lesion':
                        $matches['lesion_types'][] = $term;
                        break;
                    case 'distribution':
                        $matches['distributions'][] = $term;
                        break;
                    case 'configuration':
                        $matches['configurations'][] = $term;
                        break;
                    case 'sign':
                        $matches['signs'][] = $term;
                        break;
                    case 'investigation':
                        $matches['investigations'][] = $term;
                        break;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error matching dermatology terms: ' . $e->getMessage());
    }
    
    return $matches;
}

/**
 * Optimize image for better AI analysis
 * Resizes large images and improves quality for analysis
 */
function optimizeImageForAnalysis($imagePath, $maxSize = 1920) {
    try {
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return null;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mime = $imageInfo['mime'];
        
        // Skip if already small enough
        if ($width <= $maxSize && $height <= $maxSize) {
            return null;
        }
        
        // Calculate new dimensions
        $ratio = min($maxSize / $width, $maxSize / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create source image based on type
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($imagePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($imagePath);
                break;
            default:
                return null;
        }
        
        if (!$source) {
            return null;
        }
        
        // Create optimized image with high quality resampling
        $optimized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
        }
        
        // High-quality resize
        imagecopyresampled($optimized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Apply sharpening for clearer skin details
        $sharpenMatrix = [
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0]
        ];
        imageconvolution($optimized, $sharpenMatrix, 1, 0);
        
        // Output to buffer
        ob_start();
        imagejpeg($optimized, null, 92); // High quality JPEG
        $data = ob_get_clean();
        
        imagedestroy($source);
        imagedestroy($optimized);
        
        return [
            'data' => $data,
            'mime' => 'image/jpeg',
            'width' => $newWidth,
            'height' => $newHeight
        ];
    } catch (Exception $e) {
        error_log('Image optimization error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Main function to analyze skin image
 * Returns both AI analysis and RAG-based remedy suggestions
 * 
 * @param string $imagePath Path to the skin image
 * @param string $skinArea Body area where skin issue is located
 * @param string $symptomsDescription Patient's description of symptoms
 * @param array $patient Patient data (will be anonymized before AI)
 * @param bool $skipAI Whether to skip external AI (use RAG only)
 * @param int $doctor_id Optional doctor ID for AI consent check
 * @return array Analysis results
 */
function analyzeSkinImage($imagePath, $skinArea = 'general', $symptomsDescription = '', $patient = null, $skipAI = false, $doctor_id = null) {
    $result = [
        'ai_analysis' => [],
        'rag_remedies' => [],
        'rag_analysis' => [],
        'success' => false,
        'error' => null,
        'ai_consent' => true
    ];
    
    // Check AI consent if doctor_id provided
    $aiConsentEnabled = true;
    if ($doctor_id) {
        $aiConsentEnabled = hasAIConsent($doctor_id);
        $result['ai_consent'] = $aiConsentEnabled;
    }
    
    // Always perform RAG-based analysis first (no API needed - 100% local)
    $ragAnalysis = analyzeWithRAG($symptomsDescription, $skinArea);
    $result['rag_analysis'] = $ragAnalysis;
    $result['rag_remedies'] = $ragAnalysis['remedies'] ?? [];
    
    // If skipAI is true OR AI consent not given, don't call the AI API
    if ($skipAI || !$aiConsentEnabled) {
        $result['success'] = !empty($result['rag_remedies']);
        if (!$aiConsentEnabled) {
            $result['error'] = 'External AI disabled. Enable in Privacy Settings to use Gemini Vision analysis.';
            $result['consent_disabled'] = true;
        }
        return $result;
    }
    
    try {
        // PRIVACY: Anonymize patient data before sending to external AI
        $anonymizedPatient = null;
        if ($patient) {
            $anonymizedPatient = [
                'age' => $patient['age'] ?? 'Unknown',
                'gender' => $patient['gender'] ?? 'Unknown'
                // Note: patient_name and other PII are NOT included
            ];
        }
        
        // Log that we're sending anonymized data (for audit)
        error_log("Dermo AI: Sending anonymized data to Gemini for doctor_id: " . ($doctor_id ?? 'unknown'));
        
        // Get AI analysis with vision (only anonymized data sent)
        $aiAnalysis = getGeminiSkinAnalysis($imagePath, $skinArea, $symptomsDescription, $anonymizedPatient);
        $result['ai_analysis'] = $aiAnalysis;
        
        // Update RAG remedies with AI-detected condition for better matching
        $condition = $aiAnalysis['condition'] ?? '';
        $characteristics = $aiAnalysis['characteristics'] ?? [];
        if (!empty($condition) || !empty($characteristics)) {
            $enhancedRag = analyzeWithRAG($symptomsDescription, $skinArea, $condition, $characteristics);
            $result['rag_analysis'] = $enhancedRag;
            $result['rag_remedies'] = $enhancedRag['remedies'] ?? [];
        }
        
        $result['success'] = true;
    } catch (Exception $e) {
        error_log('Skin analysis error: ' . $e->getMessage());
        $result['error'] = $e->getMessage();
        
        // Keep the RAG remedies we already got
        $result['success'] = !empty($result['rag_remedies']);
    }
    
    return $result;
}

/**
 * Analyze symptoms using RAG only (no AI API calls)
 * Provides detailed analysis based on symptom matching
 */
function analyzeWithRAG($symptomsDescription, $skinArea = 'general', $aiCondition = '', $aiCharacteristics = []) {
    $result = [
        'condition' => '',
        'description' => '',
        'characteristics' => [],
        'severity' => 'Moderate',
        'remedies' => [],
        'matched_patterns' => []
    ];
    
    // Combine all search terms
    $searchText = strtolower($symptomsDescription . ' ' . $skinArea . ' ' . $aiCondition);
    if (!empty($aiCharacteristics)) {
        $searchText .= ' ' . implode(' ', $aiCharacteristics);
    }
    
    // Detect condition from symptoms
    $detectedConditions = detectConditionFromSymptoms($searchText);
    $result['matched_patterns'] = $detectedConditions;
    
    if (!empty($detectedConditions)) {
        $primaryCondition = $detectedConditions[0];
        $result['condition'] = $primaryCondition['condition'];
        $result['description'] = $primaryCondition['description'];
        $result['characteristics'] = $primaryCondition['characteristics'];
        $result['severity'] = detectSeverity($searchText);
    } else {
        $result['condition'] = 'General Skin Condition';
        $result['description'] = 'Based on the symptoms described, a general skin assessment has been made.';
        $result['characteristics'] = extractKeySymptoms($searchText);
        $result['severity'] = detectSeverity($searchText);
    }
    
    // Get detailed remedies with explanations
    $result['remedies'] = getDetailedRAGRemedies($searchText, $detectedConditions, $skinArea);
    
    return $result;
}

/**
 * Detect condition from symptom text with detailed matching
 * Enhanced: Returns only best-matched condition, handles pathognomonic terms
 */
function detectConditionFromSymptoms($text) {
    $conditionPatterns = getConditionPatterns();
    $matches = [];
    $text = strtolower($text);
    
    foreach ($conditionPatterns as $condition => $pattern) {
        $score = 0;
        $matchedKeywords = [];
        $hasExcludeTerm = false;
        
        // Check for exclude terms first (to prevent Herpes Simplex when Herpes Zoster)
        if (isset($pattern['exclude_terms'])) {
            foreach ($pattern['exclude_terms'] as $excludeTerm) {
                if (strpos($text, strtolower($excludeTerm)) !== false) {
                    $hasExcludeTerm = true;
                    break;
                }
            }
        }
        
        // Skip this condition if exclude term found
        if ($hasExcludeTerm) {
            continue;
        }
        
        // Check pathognomonic terms (high value keywords) - weight 5x
        if (isset($pattern['pathognomonic'])) {
            foreach ($pattern['pathognomonic'] as $pathTerm) {
                if (strpos($text, strtolower($pathTerm)) !== false) {
                    $score += 5; // Pathognomonic terms get 5 points each
                    $matchedKeywords[] = $pathTerm . ' (pathognomonic)';
                }
            }
        }
        
        // Check regular keywords
        foreach ($pattern['keywords'] as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                $score += $pattern['weight'] ?? 1;
                $matchedKeywords[] = $keyword;
            }
        }
        
        if ($score > 0) {
            $matches[] = [
                'condition' => $condition,
                'score' => $score,
                'matched_keywords' => $matchedKeywords,
                'description' => $pattern['description'],
                'characteristics' => $pattern['characteristics']
            ];
        }
    }
    
    // Sort by score descending
    usort($matches, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Return only the BEST matched condition (not 3)
    // This prevents condition mixing (e.g., Herpes Simplex + Psoriasis + Herpes Zoster)
    if (!empty($matches)) {
        // If top match has significantly higher score (2x second), return only that
        if (count($matches) > 1 && $matches[0]['score'] >= 2 * $matches[1]['score']) {
            return [$matches[0]];
        }
        // Otherwise return top 1-2 conditions (if close scores)
        return array_slice($matches, 0, min(2, count($matches)));
    }
    
    return [];
}

/**
 * Get condition patterns for RAG detection
 * Enhanced with dermatological terminology from clinical dermatology education
 */
function getConditionPatterns() {
    return [
        'Eczema / Atopic Dermatitis' => [
            'keywords' => ['eczema', 'atopic', 'dry', 'itchy', 'flaky', 'rough', 'red patches', 'inflamed', 'scaly patches', 'cracked', 'lichenification', 'flexural', 'antecubital', 'popliteal', 'excoriation'],
            'description' => 'Eczema is a chronic inflammatory skin condition causing dry, itchy, and inflamed skin. It often appears in patches and can be triggered by allergens, stress, or environmental factors. Commonly affects flexural areas.',
            'characteristics' => ['Dry, rough patches', 'Intense itching', 'Red or inflamed areas', 'May have small vesicles', 'Lichenification from chronic scratching', 'Flexural distribution'],
            'lesion_types' => ['Papule', 'Plaque', 'Vesicle', 'Lichenification', 'Excoriation', 'Scale'],
            'weight' => 2
        ],
        'Psoriasis' => [
            'keywords' => ['psoriasis', 'silvery scales', 'thick patches', 'plaque', 'scaling', 'silvery', 'raised patches', 'joint pain', 'auspitz sign', 'koebner', 'extensor', 'guttate', 'pinpoint bleeding'],
            'description' => 'Psoriasis is an autoimmune condition causing rapid skin cell buildup, resulting in thick, silvery scales and red plaques. Auspitz sign positive. Often affects extensor surfaces (elbows, knees) and scalp.',
            'characteristics' => ['Thick, raised red plaques', 'Silvery-white scales', 'Auspitz sign (pinpoint bleeding)', 'Koebner phenomenon', 'Extensor surface distribution', 'Symmetrical'],
            'lesion_types' => ['Plaque', 'Papule', 'Scale'],
            'weight' => 2
        ],
        'Acne Vulgaris' => [
            'keywords' => ['acne', 'pimples', 'blackheads', 'whiteheads', 'oily', 'breakouts', 'comedones', 'pustules', 'cystic', 'zits', 'papulopustular', 'nodular', 'sebaceous'],
            'description' => 'Acne is a common skin condition caused by blocked pilosebaceous units with oil and dead skin cells. Can range from comedones to severe nodular/cystic lesions.',
            'characteristics' => ['Open comedones (blackheads)', 'Closed comedones (whiteheads)', 'Inflamed papules', 'Pustules with purulent material', 'Nodules in severe cases', 'Scarring possible'],
            'lesion_types' => ['Papule', 'Pustule', 'Nodule', 'Cyst', 'Scar'],
            'weight' => 2
        ],
        'Urticaria / Hives' => [
            'keywords' => ['urticaria', 'hives', 'welts', 'wheals', 'allergic', 'swollen', 'raised bumps', 'itchy bumps', 'angioedema', 'dermographism', 'evanescent'],
            'description' => 'Urticaria features wheals (firm, edematous, evanescent plaques) with pale center and pink margin. Can be triggered by allergens, medications, or physical stimuli. Dermographism is a form of physical urticaria.',
            'characteristics' => ['Raised, itchy wheals', 'Pale center with pink margin', 'Evanescent (short-lived)', 'Variable size and shape', 'Dermographism possible', 'Angioedema if deep'],
            'lesion_types' => ['Wheal'],
            'weight' => 2
        ],
        'Fungal Infection / Ringworm' => [
            'keywords' => ['fungal', 'ringworm', 'tinea', 'circular', 'ring-shaped', 'athlete', 'jock itch', 'candida', 'yeast', 'nummular', 'discoid', 'koh positive', 'hyphae'],
            'description' => 'Fungal skin infections (tinea) present as nummular/discoid (coin-shaped) lesions with raised scaly border and central clearing. KOH preparation shows hyphae. Wood lamp may show fluorescence.',
            'characteristics' => ['Ring-shaped (annular) rash', 'Raised, scaly border', 'Central clearing', 'Nummular/discoid configuration', 'KOH positive for hyphae', 'Spreads outward'],
            'lesion_types' => ['Plaque', 'Papule', 'Scale'],
            'weight' => 2
        ],
        'Contact Dermatitis' => [
            'keywords' => ['contact', 'reaction', 'touched', 'exposed', 'chemical', 'irritant', 'allergic reaction', 'rash after contact', 'patch test', 'linear', 'geometric'],
            'description' => 'Contact dermatitis is skin inflammation from contact with irritants or allergens. May show linear or geometric pattern matching contact area. Patch test positive in allergic type (Type IV hypersensitivity).',
            'characteristics' => ['Redness at contact site', 'Linear or geometric pattern', 'Vesicles in acute phase', 'Scaling and fissures if chronic', 'Well-demarcated borders', 'Patch test positive'],
            'lesion_types' => ['Papule', 'Vesicle', 'Plaque', 'Erosion', 'Scale', 'Fissure'],
            'weight' => 2
        ],
        'Herpes Simplex' => [
            'keywords' => ['cold sore', 'fever blister', 'perioral', 'genital herpes', 'labialis', 'recurrent at same site', 'hsv-1', 'hsv-2', 'oral herpes', 'lip sore', 'mouth sore'],
            'description' => 'Herpes simplex causes grouped/herpetiform vesicles on erythematous base. Tzanck smear shows multinucleated giant cells. Prodrome of tingling before outbreak. Recurrent at same location (lips, genitals).',
            'characteristics' => ['Grouped vesicles (herpetiform)', 'Erythematous base', 'Tingling prodrome', 'Umbilicated vesicles', 'Crusting after rupture', 'Tzanck smear positive', 'Recurrent at same site'],
            'lesion_types' => ['Vesicle', 'Erosion', 'Crust'],
            'weight' => 2,
            'exclude_terms' => ['dermatomal', 'unilateral band', 'trunk', 'intercostal', 'shingles', 'zoster']
        ],
        'Herpes Zoster / Shingles' => [
            'keywords' => ['shingles', 'zoster', 'herpes zoster', 'dermatomal', 'dermatomal distribution', 'unilateral', 'unilateral band', 'one side only', 'does not cross midline', 'nerve distribution', 'intercostal', 'burning pain', 'neuralgic pain', 'lancinating pain', 'postherpetic', 'post-herpetic', 'varicella reactivation', 'trunk rash', 'band-like', 'grouped vesicles', 'following nerve', 'severe pain', 'acute vesicular'],
            'description' => 'Herpes zoster presents with grouped vesicles in DERMATOMAL (following nerve) distribution, STRICTLY UNILATERAL (does not cross midline). Preceded by severe burning/neuralgic pain. Most commonly affects trunk in band-like pattern.',
            'characteristics' => ['Dermatomal distribution', 'Unilateral (one side only)', 'Does NOT cross midline', 'Grouped vesicles on erythematous base', 'Burning/lancinating neuralgic pain', 'Band-like pattern on trunk', 'Intercostal nerve common', 'Preceded by prodromal pain', 'May lead to postherpetic neuralgia'],
            'lesion_types' => ['Vesicle', 'Bulla', 'Erosion', 'Crust'],
            'weight' => 4,
            'pathognomonic' => ['dermatomal', 'unilateral', 'does not cross midline', 'intercostal', 'band-like', 'shingles', 'zoster']
        ],
        'Rosacea' => [
            'keywords' => ['rosacea', 'facial redness', 'flushing', 'broken vessels', 'nose redness', 'cheek redness', 'spider veins', 'telangiectasia', 'papulopustular', 'rhinophyma'],
            'description' => 'Rosacea is a chronic inflammatory condition with persistent facial erythema, telangiectasia, and papulopustules. Central face distribution. Advanced cases may show rhinophyma.',
            'characteristics' => ['Persistent facial erythema', 'Telangiectasia (dilated vessels)', 'Papules and pustules', 'Flushing triggers', 'Central face distribution', 'Rhinophyma in advanced cases'],
            'lesion_types' => ['Telangiectasia', 'Papule', 'Pustule'],
            'weight' => 2
        ],
        'Vitiligo' => [
            'keywords' => ['vitiligo', 'white patches', 'depigmentation', 'loss of color', 'pale patches', 'white spots', 'amelanotic', 'symmetrical', 'acral', 'koebner', 'wood lamp'],
            'description' => 'Vitiligo causes well-demarcated depigmented macules/patches due to melanocyte destruction. Shows chalk-white fluorescence under Wood lamp. Often symmetrical. Koebner phenomenon positive.',
            'characteristics' => ['Well-defined white patches', 'Chalk-white on Wood lamp', 'Symmetrical distribution', 'Acral involvement common', 'Koebner phenomenon', 'May have poliosis (white hair)'],
            'lesion_types' => ['Macule', 'Patch'],
            'weight' => 2
        ],
        'Seborrheic Dermatitis' => [
            'keywords' => ['seborrheic', 'dandruff', 'cradle cap', 'greasy scales', 'yellow scales', 'flaky scalp', 'eyebrow flakes', 'nasolabial', 'seborrhoeic distribution'],
            'description' => 'Seborrheic dermatitis causes yellowish, greasy scales in seborrhoeic areas: scalp, eyebrows, nasolabial folds, behind ears, sternum. Associated with Malassezia yeast.',
            'characteristics' => ['Yellowish, greasy scales', 'Seborrhoeic distribution', 'Erythema underneath', 'Scalp and face common', 'May involve ears and chest', 'Chronic relapsing course'],
            'lesion_types' => ['Scale', 'Plaque', 'Patch'],
            'weight' => 2
        ],
        'Boils / Furuncles' => [
            'keywords' => ['boil', 'furuncle', 'carbuncle', 'abscess', 'pus', 'painful lump', 'infected hair follicle', 'swollen bump', 'fluctuant', 'tender nodule'],
            'description' => 'Boils (furuncles) are painful nodules from infected hair follicles. May become fluctuant with pus formation. Carbuncle is confluence of multiple furuncles.',
            'characteristics' => ['Red, tender nodule', 'Swelling and warmth', 'Central pustule or necrosis', 'Fluctuant when mature', 'May drain pus', 'Fever if severe'],
            'lesion_types' => ['Nodule', 'Pustule', 'Abscess'],
            'weight' => 2
        ],
        'Impetigo' => [
            'keywords' => ['impetigo', 'honey-colored crust', 'golden crust', 'superficial', 'contagious', 'children', 'bullous', 'non-bullous'],
            'description' => 'Impetigo is superficial bacterial infection with characteristic honey-colored/golden crusts. Highly contagious. Can be non-bullous (crusted) or bullous (flaccid bullae).',
            'characteristics' => ['Honey-colored crusts', 'Superficial erosions', 'Highly contagious', 'Affects face and extremities', 'Flaccid bullae in bullous type', 'Children commonly affected'],
            'lesion_types' => ['Crust', 'Vesicle', 'Bulla', 'Erosion'],
            'weight' => 2
        ],
        'Scabies' => [
            'keywords' => ['scabies', 'mite', 'burrow', 'nocturnal itching', 'finger webs', 'interdigital', 'genitals', 'intense itching'],
            'description' => 'Scabies is caused by Sarcoptes scabiei mite. Pathognomonic finding is burrows (linear tunnels). Intense nocturnal pruritus. Affects finger webs, wrists, genitals.',
            'characteristics' => ['Burrows (linear tunnels)', 'Intense nocturnal itching', 'Interdigital involvement', 'Papules and vesicles', 'Excoriations from scratching', 'Genital lesions in adults'],
            'lesion_types' => ['Burrow', 'Papule', 'Vesicle', 'Excoriation'],
            'weight' => 2
        ],
        'Molluscum Contagiosum' => [
            'keywords' => ['molluscum', 'umbilicated', 'central dimple', 'waxy', 'dome-shaped', 'pearly', 'poxvirus'],
            'description' => 'Molluscum contagiosum presents with characteristic umbilicated (central dimple) dome-shaped papules with waxy/pearly surface. Caused by poxvirus. Contagious.',
            'characteristics' => ['Umbilicated papules', 'Central dimple/depression', 'Waxy/pearly surface', 'Dome-shaped', 'Express cheesy material', 'Self-limiting'],
            'lesion_types' => ['Papule'],
            'weight' => 2
        ],
        'Lichen Planus' => [
            'keywords' => ['lichen planus', 'flat-topped', 'violaceous', 'purple', 'wickham striae', 'polygonal', 'pruritic', 'koebner'],
            'description' => 'Lichen planus presents with the 6 Ps: Purple, Polygonal, Planar (flat-topped), Pruritic Papules and Plaques. Wickham striae (white lines) on surface. Koebner phenomenon positive.',
            'characteristics' => ['Flat-topped papules', 'Violaceous/purple color', 'Polygonal shape', 'Wickham striae', 'Intense pruritus', 'Koebner phenomenon'],
            'lesion_types' => ['Papule', 'Plaque'],
            'weight' => 2
        ],
        'Pemphigus Vulgaris' => [
            'keywords' => ['pemphigus', 'flaccid bulla', 'nikolsky sign', 'oral erosions', 'acantholytic', 'autoimmune', 'blistering'],
            'description' => 'Pemphigus vulgaris is an autoimmune bullous disease with flaccid bullae and positive Nikolsky sign. Oral erosions common. Tzanck shows acantholytic cells. Direct IF shows intercellular IgG.',
            'characteristics' => ['Flaccid bullae', 'Positive Nikolsky sign', 'Oral erosions', 'Acantholytic cells on Tzanck', 'Intercellular IgG on DIF', 'Erosions heal slowly'],
            'lesion_types' => ['Bulla', 'Erosion'],
            'weight' => 2
        ],
        'Bullous Pemphigoid' => [
            'keywords' => ['pemphigoid', 'tense bulla', 'elderly', 'pruritic', 'subepidermal', 'basement membrane'],
            'description' => 'Bullous pemphigoid features tense bullae on erythematous or normal skin, mainly in elderly. Unlike pemphigus, Nikolsky sign negative. DIF shows linear IgG at basement membrane zone.',
            'characteristics' => ['Tense bullae', 'Elderly patients', 'Nikolsky sign negative', 'Pruritic urticarial plaques', 'Linear BMZ IgG on DIF', 'Oral involvement rare'],
            'lesion_types' => ['Bulla', 'Wheal', 'Erosion'],
            'weight' => 2
        ],
        'Warts / Verruca' => [
            'keywords' => ['wart', 'verruca', 'verrucous', 'papillomatous', 'hpv', 'plantar', 'common wart', 'flat wart', 'genital wart'],
            'description' => 'Warts are HPV-induced lesions with verrucous (warty) papillomatous surface. Types include common (verruca vulgaris), plantar (verruca plantaris), flat, and genital (condyloma).',
            'characteristics' => ['Verrucous/papillomatous surface', 'Hyperkeratotic', 'Thrombosed capillaries (black dots)', 'Koebner phenomenon', 'HPV-induced', 'Various types by location'],
            'lesion_types' => ['Papule', 'Plaque', 'Nodule'],
            'weight' => 2
        ],
        'Erythema Multiforme' => [
            'keywords' => ['erythema multiforme', 'target lesion', 'targetoid', 'iris lesion', 'concentric', 'herpes-associated', 'drug reaction'],
            'description' => 'Erythema multiforme is characterized by pathognomonic target/iris lesions with concentric rings. Often triggered by herpes simplex or drugs. Involves acral areas.',
            'characteristics' => ['Target/iris lesions', 'Concentric rings', 'Acral distribution', 'Herpes or drug trigger', 'Mucosal involvement variable', 'Self-limiting'],
            'lesion_types' => ['Papule', 'Plaque', 'Vesicle', 'Bulla'],
            'weight' => 2
        ],
        'Melasma' => [
            'keywords' => ['melasma', 'chloasma', 'mask of pregnancy', 'hyperpigmentation', 'malar', 'facial pigmentation', 'brown patches'],
            'description' => 'Melasma causes symmetrical brown patches on face, especially malar area. Associated with pregnancy (chloasma), oral contraceptives, and sun exposure.',
            'characteristics' => ['Brown patches', 'Malar distribution', 'Symmetrical', 'Pregnancy/hormonal association', 'Sun-exacerbated', 'Well-demarcated'],
            'lesion_types' => ['Patch', 'Macule'],
            'weight' => 2
        ],
        'Purpura / Vasculitis' => [
            'keywords' => ['purpura', 'petechiae', 'ecchymosis', 'non-blanching', 'vasculitis', 'palpable purpura', 'henoch-schonlein'],
            'description' => 'Purpura is non-blanching extravasation of blood. Petechiae (<3mm), purpura (3-10mm), ecchymosis (>10mm). Palpable purpura suggests vasculitis. HSP affects extensor surfaces.',
            'characteristics' => ['Non-blanching erythema', 'Palpable if vasculitis', 'Extensor surface (HSP)', 'Variable size', 'May be painful', 'Check for systemic involvement'],
            'lesion_types' => ['Purpura', 'Petechiae'],
            'weight' => 2
        ],
        
        // ===============================
        // PIGMENTED LESIONS / NEVI / MOLES
        // ===============================
        'Pigmented Nevus / Mole' => [
            'keywords' => ['nevus', 'nevi', 'mole', 'pigmented', 'melanocytic', 'brown spot', 'dark spot', 'pigmented lesion', 'birthmark', 'beauty mark', 'congenital nevus', 'acquired nevus', 'junctional', 'compound', 'intradermal', 'well-demarcated', 'benign mole'],
            'pathognomonic' => ['nevus', 'mole', 'melanocytic', 'pigmented lesion'],
            'description' => 'Pigmented nevi (moles) are benign melanocytic lesions. They can be congenital or acquired. Types include junctional (flat), compound (slightly raised), and intradermal (dome-shaped). Uniform color and symmetrical borders are reassuring signs.',
            'characteristics' => ['Pigmented macule or papule', 'Brown to dark brown color', 'Well-demarcated borders', 'Uniform coloration', 'Round or oval shape', 'Stable over time', 'May have hair growth'],
            'lesion_types' => ['Macule', 'Papule', 'Nodule'],
            'weight' => 4
        ],
        'Atypical Nevus / Dysplastic Nevus' => [
            'keywords' => ['atypical', 'dysplastic', 'atypical nevus', 'dysplastic nevus', 'irregular borders', 'color variation', 'heterogeneous', 'asymmetric', 'variegated', 'uneven coloration', 'atypical features', 'irregular mole', 'changing mole', 'abcd criteria', 'ugly duckling'],
            'pathognomonic' => ['atypical', 'dysplastic', 'irregular borders', 'color variation', 'heterogeneous pigmentation'],
            'description' => 'Atypical (dysplastic) nevi have features that deviate from typical benign moles. ABCDE criteria: Asymmetry, Border irregularity, Color variation, Diameter >6mm, Evolution/change. These require monitoring and may warrant biopsy to rule out melanoma.',
            'characteristics' => ['Asymmetrical shape', 'Irregular or notched borders', 'Multiple colors (brown, black, tan, pink)', 'Diameter often >6mm', 'Flat or slightly raised', 'May show recent changes', 'Requires dermatoscopy'],
            'lesion_types' => ['Macule', 'Papule', 'Patch'],
            'weight' => 5
        ],
        'Seborrheic Keratosis' => [
            'keywords' => ['seborrheic keratosis', 'seborrhoeic keratosis', 'stuck on', 'waxy', 'warty', 'brown plaque', 'stuck-on appearance', 'horn cysts', 'keratin', 'elderly', 'senile wart', 'barnacle', 'greasy'],
            'pathognomonic' => ['stuck on', 'seborrheic keratosis', 'waxy', 'stuck-on appearance'],
            'description' => 'Seborrheic keratosis is a benign pigmented lesion with characteristic "stuck-on" waxy appearance. Common in elderly. Shows horn cysts on dermatoscopy. No malignant potential. Multiple lesions common.',
            'characteristics' => ['Stuck-on waxy appearance', 'Well-demarcated', 'Brown to black color', 'Rough/warty surface', 'Horn cysts visible', 'Common in elderly', 'Often multiple'],
            'lesion_types' => ['Papule', 'Plaque'],
            'weight' => 3
        ],
        'Lentigo / Lentigines' => [
            'keywords' => ['lentigo', 'lentigines', 'liver spot', 'age spot', 'sun spot', 'solar lentigo', 'lentigo simplex', 'freckle', 'ephelides', 'flat brown', 'sun damage'],
            'pathognomonic' => ['lentigo', 'liver spot', 'age spot', 'solar lentigo'],
            'description' => 'Lentigines are flat brown macules from increased melanocytes. Solar lentigines (liver spots) from sun exposure are common in elderly. Lentigo simplex occurs at any age. Distinguished from freckles (ephelides) which darken with sun.',
            'characteristics' => ['Flat brown macule', 'Well-defined borders', 'Uniform tan to brown color', 'Sun-exposed areas', 'Does not fade in winter', 'Common in elderly', 'Often multiple'],
            'lesion_types' => ['Macule', 'Patch'],
            'weight' => 3
        ],
        'Melanoma' => [
            'keywords' => ['melanoma', 'malignant', 'black lesion', 'irregular melanoma', 'amelanotic', 'nodular melanoma', 'superficial spreading', 'lentigo maligna', 'acral', 'subungual', 'rapidly changing', 'bleeding mole', 'ulcerated mole'],
            'pathognomonic' => ['melanoma', 'rapidly changing', 'bleeding mole', 'ulcerated pigmented'],
            'description' => 'MELANOMA is a malignant tumor of melanocytes. URGENT referral needed. ABCDE: Asymmetry, Border irregularity, Color variation (black, blue, red, white), Diameter >6mm, Evolution. Types: superficial spreading, nodular, lentigo maligna, acral lentiginous.',
            'characteristics' => ['Asymmetrical lesion', 'Irregular/notched borders', 'Multiple colors (black, brown, blue, red, white)', 'Diameter >6mm (often)', 'Recent change in size/color/shape', 'May bleed or ulcerate', 'URGENT - REFER IMMEDIATELY'],
            'lesion_types' => ['Macule', 'Papule', 'Nodule', 'Plaque'],
            'weight' => 6
        ],
        'Dermatofibroma' => [
            'keywords' => ['dermatofibroma', 'fibrous histiocytoma', 'dimple sign', 'button-like', 'firm nodule', 'leg nodule', 'brown nodule', 'pinch test positive'],
            'pathognomonic' => ['dermatofibroma', 'dimple sign', 'pinch test'],
            'description' => 'Dermatofibroma is a benign fibrous nodule, often on legs. Pathognomonic dimple sign (dimples when pinched). Firm, button-like consistency. Brown color. May follow insect bite or minor trauma.',
            'characteristics' => ['Firm nodule', 'Positive dimple/pinch sign', 'Brown to tan color', 'Common on lower extremities', 'Button-like consistency', 'Well-circumscribed', 'Usually solitary'],
            'lesion_types' => ['Nodule', 'Papule'],
            'weight' => 3
        ],
        'Basal Cell Carcinoma' => [
            'keywords' => ['basal cell', 'bcc', 'rodent ulcer', 'pearly', 'rolled border', 'telangiectasia', 'non-healing ulcer', 'translucent', 'face lesion', 'sun damage skin cancer'],
            'pathognomonic' => ['pearly', 'rolled border', 'rodent ulcer', 'basal cell'],
            'description' => 'Basal cell carcinoma is the most common skin cancer. Pearly/translucent papule with rolled borders and surface telangiectasia. Slow growing, locally invasive, rarely metastasizes. Sun-exposed areas, especially face.',
            'characteristics' => ['Pearly/translucent appearance', 'Rolled/raised borders', 'Surface telangiectasia', 'May ulcerate centrally', 'Slow growing', 'Sun-exposed areas', 'Non-healing wound'],
            'lesion_types' => ['Papule', 'Nodule', 'Ulcer'],
            'weight' => 4
        ],
        'Squamous Cell Carcinoma' => [
            'keywords' => ['squamous cell', 'scc', 'keratinizing', 'crusty', 'non-healing sore', 'indurated', 'sun damaged', 'actinic keratosis progression', 'lip lesion', 'eroded'],
            'pathognomonic' => ['squamous cell', 'scc', 'keratinizing tumor'],
            'description' => 'Squamous cell carcinoma arises from keratinocytes. Often on sun-damaged skin or from actinic keratoses. Indurated, keratinizing nodule or non-healing ulcer. Can metastasize. Higher risk on lips, ears, immunosuppressed.',
            'characteristics' => ['Indurated/firm nodule', 'Keratinizing/crusty surface', 'May ulcerate', 'Non-healing sore', 'Sun-exposed areas', 'Can arise in actinic keratosis', 'Potential to metastasize'],
            'lesion_types' => ['Nodule', 'Plaque', 'Ulcer'],
            'weight' => 4
        ],
        'Actinic Keratosis' => [
            'keywords' => ['actinic keratosis', 'solar keratosis', 'pre-cancer', 'precancer', 'rough spot', 'sandpaper', 'sun damage', 'ak', 'field cancerization'],
            'pathognomonic' => ['actinic keratosis', 'solar keratosis', 'sandpaper texture'],
            'description' => 'Actinic keratosis is a precancerous lesion from chronic sun damage. Rough, sandpaper-like texture. Pink to red with scale. Can progress to squamous cell carcinoma (risk ~10%). Field cancerization common.',
            'characteristics' => ['Rough/sandpaper texture', 'Pink to red color', 'Scaly surface', 'Sun-exposed areas', 'Often multiple', 'Precancerous', 'Better felt than seen'],
            'lesion_types' => ['Papule', 'Plaque', 'Scale'],
            'weight' => 3
        ],
        'Cafe-au-lait Macule' => [
            'keywords' => ['cafe au lait', 'cafe-au-lait', 'light brown', 'coffee colored', 'neurofibromatosis', 'coast of california', 'coast of maine', 'congenital', 'birthmark brown'],
            'description' => 'Cafe-au-lait macules are flat, uniformly light brown patches. Single lesion is common and benign. Multiple (>6) may indicate neurofibromatosis type 1. "Coast of California" (smooth) vs "Coast of Maine" (irregular) borders.',
            'characteristics' => ['Light brown/coffee color', 'Flat macule or patch', 'Well-demarcated', 'Uniform color', 'Present from birth/early childhood', '>6 lesions suggests NF1', 'Usually stable'],
            'lesion_types' => ['Macule', 'Patch'],
            'weight' => 2
        ],
        'Post-inflammatory Hyperpigmentation' => [
            'keywords' => ['post-inflammatory', 'pih', 'hyperpigmentation after', 'dark spot after', 'acne scar dark', 'healing dark', 'trauma pigmentation', 'dark skin after injury'],
            'description' => 'Post-inflammatory hyperpigmentation (PIH) occurs after skin inflammation or injury. More common in darker skin types. Resolves slowly over months to years. Sun protection important.',
            'characteristics' => ['Follows prior inflammation', 'Brown to dark brown', 'Matches shape of prior lesion', 'No texture change', 'More common in dark skin', 'Fades slowly', 'Sun protection helps'],
            'lesion_types' => ['Macule', 'Patch'],
            'weight' => 2
        ],
        'General Skin Irritation' => [
            'keywords' => ['irritation', 'rash', 'red', 'bumps', 'itching', 'inflammation', 'skin problem'],
            'description' => 'General skin irritation can have various causes including allergies, irritants, infections, or environmental factors.',
            'characteristics' => ['Redness', 'Itching', 'Inflammation', 'Variable appearance'],
            'lesion_types' => ['Papule', 'Macule', 'Patch'],
            'weight' => 1
        ]
    ];
}

/**
 * Get lesion morphology descriptions for better AI context
 */
function getLesionMorphology() {
    return [
        'primary_lesions' => [
            'macule' => 'Flat, circumscribed discoloration <0.5cm, not palpable',
            'patch' => 'Flat, circumscribed discoloration >0.5cm, not palpable',
            'papule' => 'Elevated, solid lesion <0.5cm',
            'plaque' => 'Elevated, flat-topped lesion >0.5cm, confluence of papules',
            'nodule' => 'Elevated, solid lesion >0.5cm with depth component',
            'tumor' => 'Solid elevation >2cm with depth',
            'vesicle' => 'Fluid-filled elevation <0.5cm',
            'bulla' => 'Fluid-filled elevation >0.5cm (large vesicle)',
            'pustule' => 'Elevation containing purulent material',
            'wheal' => 'Edematous, evanescent plaque with pale center',
            'cyst' => 'Nodule containing fluid/semisolid material',
            'burrow' => 'Linear tunnel in epidermis (scabies)',
            'purpura' => 'Non-blanching extravasation of blood',
            'telangiectasia' => 'Dilated capillaries visible on skin'
        ],
        'secondary_lesions' => [
            'scale' => 'Thickened stratum corneum',
            'crust' => 'Dried serum, blood, or pus',
            'erosion' => 'Superficial loss of epidermis, heals without scarring',
            'ulcer' => 'Full thickness loss, heals with scarring',
            'fissure' => 'Linear crack in skin',
            'excoriation' => 'Linear erosion from scratching',
            'lichenification' => 'Thickened skin from chronic rubbing',
            'scar' => 'New connective tissue from healed wound'
        ]
    ];
}

/**
 * Get clinical signs for condition identification
 */
function getClinicalSigns() {
    return [
        'nikolsky_sign' => [
            'description' => 'Skin sloughs off with rubbing',
            'conditions' => ['Pemphigus vulgaris', 'Toxic epidermal necrolysis', 'SSSS']
        ],
        'auspitz_sign' => [
            'description' => 'Pinpoint bleeding when scale removed',
            'conditions' => ['Psoriasis']
        ],
        'koebner_phenomenon' => [
            'description' => 'Lesions develop at sites of trauma',
            'conditions' => ['Psoriasis', 'Vitiligo', 'Lichen planus', 'Warts']
        ],
        'dermographism' => [
            'description' => 'Wheal formation from stroking skin',
            'conditions' => ['Physical urticaria']
        ],
        'wickham_striae' => [
            'description' => 'White lines on surface of papules',
            'conditions' => ['Lichen planus']
        ]
    ];
}

/**
 * Get detailed RAG remedies with comprehensive explanations
 * Enhanced: Caps relevance at 10/10, returns max 5 focused remedies
 */
function getDetailedRAGRemedies($searchText, $detectedConditions, $skinArea) {
    $remedies = [];
    $remedyDetails = getComprehensiveRemedyDetails();
    $skinRemedyMappings = getSkinRemedyMappings();
    
    // Collect relevant remedy names from mappings
    $relevantRemedyNames = [];
    
    // ONLY add remedies from the primary detected condition (not all conditions)
    // This prevents mixing Psoriasis remedies with Herpes Zoster
    if (!empty($detectedConditions)) {
        $primaryCondition = $detectedConditions[0]; // Use only top matched condition
        $condKey = strtolower(explode('/', $primaryCondition['condition'])[0]);
        $condKey = trim($condKey);
        
        foreach ($skinRemedyMappings as $mapKey => $remedyList) {
            if (strpos($mapKey, $condKey) !== false || strpos($condKey, $mapKey) !== false) {
                foreach ($remedyList as $remedyName) {
                    // Score based on condition match strength
                    $relevantRemedyNames[$remedyName] = ($relevantRemedyNames[$remedyName] ?? 0) + min($primaryCondition['score'], 10);
                }
            }
        }
    }
    
    // Fallback: Check searchText for condition keywords (but limit scope)
    if (empty($relevantRemedyNames)) {
        foreach ($skinRemedyMappings as $condition => $remedyList) {
            if (strpos($searchText, strtolower($condition)) !== false) {
                foreach ($remedyList as $remedyName) {
                    $relevantRemedyNames[$remedyName] = ($relevantRemedyNames[$remedyName] ?? 0) + 2;
                }
                break; // Only match FIRST found condition in text
            }
        }
    }
    
    // Add skin area specific remedies (minor boost)
    if (!empty($skinArea) && isset($skinRemedyMappings[$skinArea])) {
        foreach ($skinRemedyMappings[$skinArea] as $remedyName) {
            $relevantRemedyNames[$remedyName] = ($relevantRemedyNames[$remedyName] ?? 0) + 1;
        }
    }
    
    // Cap all scores at 10 (max relevance is 10/10)
    foreach ($relevantRemedyNames as $name => $score) {
        $relevantRemedyNames[$name] = min($score, 10);
    }
    
    // Sort by relevance score
    arsort($relevantRemedyNames);
    
    // Get detailed info for top 5 remedies (reduced from 8)
    $count = 0;
    foreach ($relevantRemedyNames as $remedyName => $score) {
        if ($count >= 5) break; // Max 5 remedies for focused treatment
        
        $normalizedName = normalizeRemedyName($remedyName);
        $details = $remedyDetails[$normalizedName] ?? null;
        
        if ($details) {
            $remedy = [
                'remedy_name' => $details['name'],
                'common_name' => $details['common_name'] ?? '',
                'score' => $score,
                'potency' => $details['potency'] ?? '30C',
                'keynote_symptoms' => implode('; ', $details['keynotes'] ?? []),
                'skin_indications' => $details['skin_indications'] ?? '',
                'modalities' => $details['modalities'] ?? [],
                'why_indicated' => generateWhyIndicated($details, $searchText, $detectedConditions),
                'dosage' => $details['dosage'] ?? '3 pellets 3 times daily'
            ];
            
            // Try to get additional info from database
            $dbInfo = getRemedyFromDatabase($normalizedName);
            if ($dbInfo) {
                $remedy['id'] = $dbInfo['id'];
                if (!empty($dbInfo['clinical_indications'])) {
                    $remedy['clinical_indications'] = $dbInfo['clinical_indications'];
                }
            }
            
            $remedies[] = $remedy;
            $count++;
        }
    }
    
    // If no remedies found, get default skin remedies
    if (empty($remedies)) {
        $defaultRemedies = ['sulphur', 'graphites', 'arsenicum', 'rhus-tox', 'apis'];
        foreach ($defaultRemedies as $name) {
            $normalizedName = normalizeRemedyName($name);
            $details = $remedyDetails[$normalizedName] ?? null;
            if ($details) {
                $remedies[] = [
                    'remedy_name' => $details['name'],
                    'common_name' => $details['common_name'] ?? '',
                    'score' => 5,
                    'potency' => $details['potency'] ?? '30C',
                    'keynote_symptoms' => implode('; ', $details['keynotes'] ?? []),
                    'skin_indications' => $details['skin_indications'] ?? '',
                    'why_indicated' => 'Common skin remedy for general skin conditions',
                    'dosage' => $details['dosage'] ?? '3 pellets 3 times daily'
                ];
            }
        }
    }
    
    return $remedies;
}

/**
 * Generate "Why Indicated" explanation for remedy
 */
function generateWhyIndicated($remedyDetails, $searchText, $detectedConditions) {
    $reasons = [];
    
    // Check keynotes against symptoms
    if (!empty($remedyDetails['keynotes'])) {
        foreach ($remedyDetails['keynotes'] as $keynote) {
            $keynoteWords = explode(' ', strtolower($keynote));
            foreach ($keynoteWords as $word) {
                if (strlen($word) > 4 && strpos($searchText, $word) !== false) {
                    $reasons[] = $keynote;
                    break;
                }
            }
        }
    }
    
    // Add skin indication if present
    if (!empty($remedyDetails['skin_indications'])) {
        $reasons[] = $remedyDetails['skin_indications'];
    }
    
    // Add condition-specific reasons
    if (!empty($detectedConditions)) {
        foreach ($detectedConditions as $cond) {
            if (!empty($remedyDetails['conditions']) && 
                in_array(strtolower($cond['condition']), array_map('strtolower', $remedyDetails['conditions']))) {
                $reasons[] = "Indicated for " . $cond['condition'];
            }
        }
    }
    
    // Add modality reasons
    if (!empty($remedyDetails['modalities'])) {
        $modText = '';
        if (!empty($remedyDetails['modalities']['worse'])) {
            $modText .= 'Worse: ' . implode(', ', $remedyDetails['modalities']['worse']);
        }
        if (!empty($remedyDetails['modalities']['better'])) {
            $modText .= ($modText ? '; ' : '') . 'Better: ' . implode(', ', $remedyDetails['modalities']['better']);
        }
        if ($modText) {
            $reasons[] = $modText;
        }
    }
    
    if (empty($reasons)) {
        return $remedyDetails['general_indication'] ?? 'Well-indicated remedy for skin conditions';
    }
    
    return implode('. ', array_slice(array_unique($reasons), 0, 3)) . '.';
}

/**
 * Comprehensive remedy details for skin conditions
 */
function getComprehensiveRemedyDetails() {
    return [
        'sulphur' => [
            'name' => 'Sulphur',
            'common_name' => 'Sublimated Sulphur',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Burning and itching worse from heat and washing',
                'Dirty, unhealthy looking skin',
                'Eruptions that itch intensely and burn after scratching',
                'Skin symptoms worse at night and from warmth of bed',
                'Offensive discharges and body odor'
            ],
            'skin_indications' => 'King of skin remedies. For itching, burning eruptions worse from heat. Dry, scaly, unhealthy skin that heals slowly.',
            'modalities' => [
                'worse' => ['heat', 'washing', 'night', 'warmth of bed', 'standing'],
                'better' => ['dry warm weather', 'lying on right side']
            ],
            'conditions' => ['Eczema', 'Psoriasis', 'Acne', 'Scabies', 'Boils'],
            'dosage' => '30C: 3 pellets 3 times daily; 200C: once weekly',
            'general_indication' => 'Primary remedy for most skin conditions with itching and burning'
        ],
        'graphites' => [
            'name' => 'Graphites',
            'common_name' => 'Black Lead / Plumbago',
            'potency' => '30C',
            'keynotes' => [
                'Thick, honey-like discharge from eruptions',
                'Cracks and fissures especially in skin folds',
                'Moist, weeping eczema behind ears and in folds',
                'Unhealthy skin, every injury suppurates',
                'Scars become keloid'
            ],
            'skin_indications' => 'For eczema with sticky, honey-like discharge. Cracks in skin folds, behind ears, corners of mouth. Thick, rough skin.',
            'modalities' => [
                'worse' => ['warmth', 'night', 'during menses'],
                'better' => ['in the dark', 'wrapping up']
            ],
            'conditions' => ['Eczema', 'Psoriasis', 'Cracks', 'Keloids', 'Dermatitis'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'Best for weeping eczema and skin with cracks and fissures'
        ],
        'arsenicum' => [
            'name' => 'Arsenicum Album',
            'common_name' => 'White Arsenic',
            'potency' => '30C',
            'keynotes' => [
                'Burning pains relieved by warmth',
                'Dry, rough, scaly skin',
                'Restlessness and anxiety with skin symptoms',
                'Itching worse from scratching, leads to burning',
                'Periodicity - symptoms return at regular intervals'
            ],
            'skin_indications' => 'Dry, rough, scaly eruptions with burning relieved by warmth. Psoriasis with fine scales. Great anxiety with skin problems.',
            'modalities' => [
                'worse' => ['cold', 'wet', 'midnight to 2am', 'scratching'],
                'better' => ['warmth', 'warm drinks', 'head elevated']
            ],
            'conditions' => ['Psoriasis', 'Eczema', 'Urticaria', 'Herpes', 'Skin cancer'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'For burning skin conditions relieved by warmth, with restlessness'
        ],
        'rhus-tox' => [
            'name' => 'Rhus Toxicodendron',
            'common_name' => 'Poison Ivy',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Vesicular eruptions with intense itching',
                'Restlessness - must keep moving',
                'Symptoms worse from initial movement, better continued motion',
                'Red, swollen, itching skin with small blisters',
                'Burning and stinging worse from cold and damp'
            ],
            'skin_indications' => 'For vesicular (blister) eruptions with intense itching. Herpes, shingles, poison ivy-type reactions. Red, swollen with small blisters.',
            'modalities' => [
                'worse' => ['cold', 'damp', 'rest', 'beginning motion', 'night'],
                'better' => ['warmth', 'movement', 'rubbing', 'hot bath']
            ],
            'conditions' => ['Herpes', 'Urticaria', 'Eczema', 'Vesicular eruptions', 'Shingles'],
            'dosage' => '30C: 3 pellets 3-4 times daily',
            'general_indication' => 'Primary remedy for vesicular eruptions and herpes'
        ],
        'apis' => [
            'name' => 'Apis Mellifica',
            'common_name' => 'Honey Bee',
            'potency' => '30C',
            'keynotes' => [
                'Stinging, burning pains like bee stings',
                'Swelling - edematous, puffy',
                'Symptoms worse from heat, better from cold applications',
                'Rosy red, shiny swelling',
                'Thirstlessness despite symptoms'
            ],
            'skin_indications' => 'For hives, allergic reactions with stinging, burning. Puffy, rosy swelling better from cold. Urticaria from heat or allergies.',
            'modalities' => [
                'worse' => ['heat', 'touch', 'pressure', 'afternoon'],
                'better' => ['cold applications', 'open air', 'uncovering']
            ],
            'conditions' => ['Urticaria', 'Allergic reactions', 'Edema', 'Insect bites', 'Cellulitis'],
            'dosage' => '30C: 3 pellets every 15-30 minutes in acute cases',
            'general_indication' => 'Best for hives and allergic swellings better from cold'
        ],
        'hepar-sulph' => [
            'name' => 'Hepar Sulphuris',
            'common_name' => 'Calcium Sulphide',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Extreme sensitivity to touch, pain, and cold',
                'Tendency to suppuration - boils, abscesses',
                'Splinter-like, sticking pains',
                'Pus formation with offensive smell',
                'Irritable, oversensitive temperament'
            ],
            'skin_indications' => 'For boils, abscesses, and suppurating skin conditions. Extremely sensitive skin. Use 200C to abort boils, 30C to promote drainage.',
            'modalities' => [
                'worse' => ['cold', 'touch', 'draft', 'uncovering'],
                'better' => ['warmth', 'wrapping up', 'damp weather']
            ],
            'conditions' => ['Boils', 'Abscess', 'Acne', 'Infected wounds', 'Suppuration'],
            'dosage' => '200C to abort; 30C to promote drainage',
            'general_indication' => 'Primary remedy for boils and abscesses'
        ],
        'silicea' => [
            'name' => 'Silicea',
            'common_name' => 'Silica / Pure Flint',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Promotes expulsion of foreign bodies',
                'Every little injury suppurates',
                'Felons, whitlows, ingrown toenails',
                'Cold, clammy sweating especially on feet',
                'Keloid formation, old scars break open'
            ],
            'skin_indications' => 'For slow-healing wounds, chronic abscesses, keloids. Promotes expulsion of splinters and foreign bodies. Unhealthy skin that suppurates.',
            'modalities' => [
                'worse' => ['cold', 'uncovering', 'new moon'],
                'better' => ['warmth', 'wrapping head', 'summer']
            ],
            'conditions' => ['Abscesses', 'Keloids', 'Boils', 'Felons', 'Slow healing'],
            'dosage' => '30C: 3 pellets 2 times daily for chronic cases',
            'general_indication' => 'For chronic suppuration and slow-healing skin conditions'
        ],
        'mezereum' => [
            'name' => 'Mezereum',
            'common_name' => 'Spurge Olive',
            'potency' => '30C',
            'keynotes' => [
                'Thick, leathery crusts with pus beneath',
                'Intense itching, worse at night in bed',
                'Vesicles surrounded by shiny skin',
                'Eruptions on scalp with thick crusts',
                'Burning and itching; scratching changes location'
            ],
            'skin_indications' => 'For eczema with thick crusts and pus underneath. Herpes zoster. Intense itching worse at night. Eruptions that ooze sticky fluid.',
            'modalities' => [
                'worse' => ['night', 'warmth of bed', 'touch'],
                'better' => ['open air']
            ],
            'conditions' => ['Eczema', 'Impetigo', 'Herpes zoster', 'Crusting eruptions'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'For crusting eczema with pus underneath the crusts'
        ],
        'petroleum' => [
            'name' => 'Petroleum',
            'common_name' => 'Rock Oil',
            'potency' => '30C',
            'keynotes' => [
                'Deep cracks and fissures in skin',
                'Skin extremely dry, rough, leathery',
                'Winter eczema - worse in cold weather',
                'Cracks that bleed easily',
                'Thick, greenish crusts'
            ],
            'skin_indications' => 'For extremely dry, cracked skin especially in winter. Deep fissures on hands, fingers, heels. Skin rough like leather.',
            'modalities' => [
                'worse' => ['winter', 'cold', 'dampness', 'eating'],
                'better' => ['warm air', 'dry weather']
            ],
            'conditions' => ['Eczema', 'Cracks', 'Fissures', 'Winter skin'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'Best for cracked, fissured skin worse in winter'
        ],
        'natrum-mur' => [
            'name' => 'Natrum Muriaticum',
            'common_name' => 'Common Salt',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Oily, greasy skin especially on face',
                'Eruptions at hairline and in creases',
                'Herpes labialis (cold sores) on lips',
                'Urticaria with violent itching',
                'Emotional component - grief, suppressed emotions'
            ],
            'skin_indications' => 'For oily skin, acne at hairline, cold sores. Urticaria after emotional stress. Skin conditions with grief or emotional suppression.',
            'modalities' => [
                'worse' => ['heat', 'sun', '10am', 'sea shore', 'emotional stress'],
                'better' => ['open air', 'cold bathing', 'going without food']
            ],
            'conditions' => ['Herpes', 'Urticaria', 'Acne', 'Oily skin', 'Seborrhea'],
            'dosage' => '30C: 3 pellets 2 times daily',
            'general_indication' => 'For cold sores and skin conditions with emotional component'
        ],
        'sepia' => [
            'name' => 'Sepia',
            'common_name' => 'Cuttlefish Ink',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Brown/yellowish discoloration, chloasma',
                'Ringworm in isolated spots',
                'Skin conditions related to hormones',
                'Herpes circinatus (ring-shaped)',
                'Indifference, aversion to family'
            ],
            'skin_indications' => 'For pigmentation disorders, melasma, ringworm. Hormonal skin problems. Brown spots and discolorations especially during pregnancy.',
            'modalities' => [
                'worse' => ['cold', 'before menses', 'morning and evening'],
                'better' => ['vigorous exercise', 'warmth', 'pressure']
            ],
            'conditions' => ['Ringworm', 'Pigmentation', 'Melasma', 'Herpes', 'Hormonal skin'],
            'dosage' => '30C: 3 pellets daily; 200C: once weekly',
            'general_indication' => 'For pigmentation and hormonal skin conditions'
        ],
        'tellurium' => [
            'name' => 'Tellurium',
            'common_name' => 'Tellurium Metal',
            'potency' => '30C',
            'keynotes' => [
                'Ring-shaped eruptions, classic ringworm',
                'Offensive, garlic-like odor from body',
                'Herpes circinatus on various parts',
                'Eczema behind ears',
                'Vesicular eruptions in rings'
            ],
            'skin_indications' => 'Specific for ringworm (tinea) with perfect ring formation. Classic circular fungal infections. Offensive body odor.',
            'modalities' => [
                'worse' => ['touch', 'cold', 'lying on affected side'],
                'better' => ['warm applications']
            ],
            'conditions' => ['Ringworm', 'Tinea', 'Fungal infections'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'Specific remedy for ringworm and fungal skin infections'
        ],
        'thuja' => [
            'name' => 'Thuja Occidentalis',
            'common_name' => 'Arbor Vitae',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Warts - soft, pedunculated, bleeding',
                'Oily skin with brown spots',
                'Effects of vaccination',
                'Skin looks dirty despite washing',
                'Eruptions only on covered parts'
            ],
            'skin_indications' => 'Primary remedy for warts of all types. Skin conditions from vaccination. Oily skin, brown age spots.',
            'modalities' => [
                'worse' => ['cold', 'damp', '3am and 3pm', 'vaccination'],
                'better' => ['warmth', 'wrapping']
            ],
            'conditions' => ['Warts', 'Condylomata', 'Oily skin', 'Brown spots'],
            'dosage' => '30C for acute; 200C for chronic/deep warts',
            'general_indication' => 'First remedy for warts and vaccination effects'
        ],
        'urtica-urens' => [
            'name' => 'Urtica Urens',
            'common_name' => 'Stinging Nettle',
            'potency' => '30C',
            'keynotes' => [
                'Hives with violent itching and burning',
                'Urticaria from eating shellfish',
                'Blotchy, raised eruptions',
                'Stinging, prickling sensation',
                'Alternating with joint symptoms'
            ],
            'skin_indications' => 'Specific for urticaria (hives) especially from shellfish allergy. Intense stinging, burning, and itching like nettle rash.',
            'modalities' => [
                'worse' => ['shellfish', 'cold water', 'touch', 'yearly'],
                'better' => ['rubbing', 'lying down']
            ],
            'conditions' => ['Urticaria', 'Hives', 'Allergic rash', 'Burns'],
            'dosage' => '30C: every 15-30 minutes in acute urticaria',
            'general_indication' => 'Specific remedy for hives and allergic skin reactions'
        ],
        'calcarea' => [
            'name' => 'Calcarea Carbonica',
            'common_name' => 'Carbonate of Lime',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Cold, damp, sweaty skin',
                'Unhealthy skin - small wounds fester',
                'Thick, crusty eruptions on scalp',
                'Warts on face and hands',
                'Chubby, fair, sweaty constitution'
            ],
            'skin_indications' => 'For eczema in fair, chubby individuals. Milk crusts in infants. Unhealthy skin that suppurates easily.',
            'modalities' => [
                'worse' => ['cold', 'wet', 'full moon', 'exertion'],
                'better' => ['dry weather', 'lying on painful side']
            ],
            'conditions' => ['Eczema', 'Milk crust', 'Warts', 'Unhealthy skin'],
            'dosage' => '30C: weekly; 200C: monthly',
            'general_indication' => 'Constitutional remedy for chronic eczema'
        ],
        'psorinum' => [
            'name' => 'Psorinum',
            'common_name' => 'Scabies Vesicle',
            'potency' => '200C',
            'keynotes' => [
                'Extremely offensive odor despite washing',
                'Dirty, greasy skin appearance',
                'Intense itching worse at night, warmth of bed',
                'All skin symptoms worse from warmth',
                'History of suppressed eruptions'
            ],
            'skin_indications' => 'For chronic, recurrent skin conditions. History of suppressed eczema or scabies. Extremely itchy at night. Offensive smell.',
            'modalities' => [
                'worse' => ['cold', 'night', 'warmth of bed', 'weather changes'],
                'better' => ['heat', 'warm clothing']
            ],
            'conditions' => ['Chronic eczema', 'Scabies', 'Psoriasis', 'Recurrent eruptions'],
            'dosage' => '200C: one dose, wait for reaction',
            'general_indication' => 'Deep-acting remedy for chronic, recurrent skin diseases'
        ],
        'kali-brom' => [
            'name' => 'Kali Bromatum',
            'common_name' => 'Potassium Bromide',
            'potency' => '30C',
            'keynotes' => [
                'Acne - large, indurated pustules',
                'Acne on face, chest, shoulders',
                'Bluish-red eruptions',
                'Restless hands, constant fidgeting',
                'Worse during menses'
            ],
            'skin_indications' => 'Specific for acne vulgaris with large, bluish pustules. Teen acne. Leaves scars and marks.',
            'modalities' => [
                'worse' => ['menses', 'warmth'],
                'better' => ['cold', 'when occupied']
            ],
            'conditions' => ['Acne', 'Pustular eruptions'],
            'dosage' => '30C: 3 pellets 2 times daily',
            'general_indication' => 'One of the main remedies for acne'
        ],
        'antimonium-crud' => [
            'name' => 'Antimonium Crudum',
            'common_name' => 'Black Sulphide of Antimony',
            'potency' => '30C',
            'keynotes' => [
                'Thick, hard, honey-colored crusts',
                'Horny warts, especially on soles',
                'Eczema with gastric symptoms',
                'Irritable when looked at or touched',
                'White coated tongue'
            ],
            'skin_indications' => 'For crusty eruptions with thick, honey-colored crusts. Hard, horny warts on soles. Impetigo. Eczema with digestive issues.',
            'modalities' => [
                'worse' => ['heat', 'cold bathing', 'overeating', 'sun'],
                'better' => ['rest', 'open air']
            ],
            'conditions' => ['Warts', 'Impetigo', 'Crusty eczema', 'Calluses'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'For crusty eruptions and horny growths'
        ],
        'dulcamara' => [
            'name' => 'Dulcamara',
            'common_name' => 'Bittersweet',
            'potency' => '30C',
            'keynotes' => [
                'Eruptions from cold, damp weather',
                'Thick, crusty, moist eruptions',
                'Urticaria from getting wet',
                'Warts, large, smooth, flat',
                'Every cold settles in skin'
            ],
            'skin_indications' => 'For eruptions triggered by damp, cold weather. Urticaria from getting wet. Large, flat warts. Weather-sensitive skin.',
            'modalities' => [
                'worse' => ['cold', 'damp', 'weather changes', 'night'],
                'better' => ['warmth', 'dry weather', 'motion']
            ],
            'conditions' => ['Urticaria', 'Warts', 'Weather-triggered eruptions'],
            'dosage' => '30C: at first sign of weather-triggered eruptions',
            'general_indication' => 'For skin conditions triggered by cold, damp weather'
        ],
        'berberis-aq' => [
            'name' => 'Berberis Aquifolium',
            'common_name' => 'Oregon Grape',
            'potency' => '6C or Mother Tincture',
            'keynotes' => [
                'Blood purifier for skin',
                'Acne with rough, scaly skin',
                'Blotchy complexion',
                'Psoriasis with dry, rough skin',
                'Clear, beautiful complexion (after treatment)'
            ],
            'skin_indications' => 'Excellent blood purifier for chronic skin conditions. Acne, psoriasis, eczema. Promotes clear complexion.',
            'modalities' => [
                'worse' => ['cold'],
                'better' => ['warmth']
            ],
            'conditions' => ['Acne', 'Psoriasis', 'Eczema', 'Blotchy skin'],
            'dosage' => 'Mother Tincture: 10-15 drops 3 times daily; or 6C pellets',
            'general_indication' => 'Blood purifier for chronic skin conditions'
        ],
        'croton-tig' => [
            'name' => 'Croton Tiglium',
            'common_name' => 'Croton Oil Seed',
            'potency' => '30C',
            'keynotes' => [
                'Intense itching but painful to scratch',
                'Vesicular eruptions with intense itch',
                'Eruptions on genitals and face',
                'Feels better from gentle rubbing',
                'Skin feels tight'
            ],
            'skin_indications' => 'For vesicular eczema with intense itching that is painful to scratch. Better from gentle rubbing only. Genital herpes.',
            'modalities' => [
                'worse' => ['scratching', 'washing', 'summer'],
                'better' => ['gentle rubbing']
            ],
            'conditions' => ['Vesicular eczema', 'Genital herpes', 'Intense itching'],
            'dosage' => '30C: 3 pellets 3 times daily',
            'general_indication' => 'For vesicular eruptions with itching painful to scratch'
        ],
        
        // ===============================
        // PIGMENTED LESIONS REMEDIES
        // ===============================
        'carcinosin' => [
            'name' => 'Carcinosin',
            'common_name' => 'Nosode from Carcinoma',
            'potency' => '200C or 1M',
            'keynotes' => [
                'Family history of cancer',
                'Multiple moles, especially if changing',
                'Perfectionist personality',
                'History of suppressed emotions',
                'Cafe-au-lait spots, multiple nevi'
            ],
            'skin_indications' => 'Constitutional remedy for atypical moles, dysplastic nevi, and when there is family history of cancer or melanoma. Multiple pigmented lesions.',
            'modalities' => [
                'worse' => ['suppression', 'grief', 'overwork'],
                'better' => ['creative expression', 'dancing', 'music']
            ],
            'conditions' => ['Atypical nevi', 'Dysplastic nevi', 'Multiple moles', 'Melanoma tendency', 'Pigmented lesions'],
            'dosage' => '200C: single dose; 1M: single dose under supervision',
            'general_indication' => 'Deep constitutional remedy for pigmented lesion tendencies and cancer prevention'
        ],
        'nitric-acid' => [
            'name' => 'Nitricum Acidum',
            'common_name' => 'Nitric Acid',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Splinter-like pains in lesions',
                'Dark, irregular moles that may bleed',
                'Warts that bleed on washing',
                'Ulcers with sharp, sticking pains',
                'Cracks at mucocutaneous junctions'
            ],
            'skin_indications' => 'For dark pigmented lesions, especially moles with tendency to bleed. Warts and condylomata. Splinter-like pains characteristic.',
            'modalities' => [
                'worse' => ['touch', 'cold', 'night', 'changing weather'],
                'better' => ['riding in car', 'mild weather']
            ],
            'conditions' => ['Dark moles', 'Bleeding warts', 'Condylomata', 'Pigmented lesions', 'Fissures'],
            'dosage' => '30C: 3 pellets 2 times daily',
            'general_indication' => 'Key remedy for dark, irregular moles with bleeding tendency'
        ],
        'conium' => [
            'name' => 'Conium Maculatum',
            'common_name' => 'Poison Hemlock',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Hard, stony lumps and tumors',
                'Effects of injury to glands',
                'Progressive weakness ascending upward',
                'Induration after trauma',
                'Elderly patients with nodules'
            ],
            'skin_indications' => 'For hard, indurated nodules and tumors. Useful in elderly with suspicious lesions. Indicated when there is hardening and induration.',
            'modalities' => [
                'worse' => ['celibacy', 'cold', 'injury', 'night'],
                'better' => ['motion', 'pressure', 'fasting']
            ],
            'conditions' => ['Indurated tumors', 'Hard nodules', 'Suspicious lesions', 'Elderly skin growths'],
            'dosage' => '30C: 3 pellets twice daily; 200C: weekly',
            'general_indication' => 'For hard, indurated skin lesions and tumors, especially in elderly'
        ],
        'phosphorus' => [
            'name' => 'Phosphorus',
            'common_name' => 'Phosphorus',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Bleeding tendency - small wounds bleed profusely',
                'Tall, slender, fair individuals',
                'Burning pains relieved by cold',
                'Fear and anxiety, desires company',
                'Moles that bleed easily'
            ],
            'skin_indications' => 'For pigmented lesions with bleeding tendency. Moles that bleed on slight touch. Constitutional remedy for fair individuals with multiple nevi.',
            'modalities' => [
                'worse' => ['evening', 'lying on left side', 'cold'],
                'better' => ['cold food', 'sleep', 'rubbing']
            ],
            'conditions' => ['Bleeding moles', 'Pigmented lesions', 'Purpura', 'Easy bruising'],
            'dosage' => '30C: 3 pellets twice daily',
            'general_indication' => 'For pigmented lesions with bleeding tendency'
        ],
        'sepia' => [
            'name' => 'Sepia',
            'common_name' => 'Cuttlefish Ink',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Brownish discoloration of skin',
                'Chloasma, melasma',
                'Saddle across nose pigmentation',
                'Hormonal skin changes',
                'Yellow-brown liver spots'
            ],
            'skin_indications' => 'Primary remedy for hyperpigmentation, melasma, liver spots. Brown patches especially on face. Hormonal pigmentation changes.',
            'modalities' => [
                'worse' => ['cold', 'morning', 'pregnancy', 'before menses'],
                'better' => ['exercise', 'warmth', 'pressure']
            ],
            'conditions' => ['Melasma', 'Chloasma', 'Liver spots', 'Hyperpigmentation', 'Brown patches'],
            'dosage' => '30C: 3 pellets twice daily; 200C: weekly',
            'general_indication' => 'First choice for brown pigmentation and melasma'
        ],
        'lycopodium' => [
            'name' => 'Lycopodium Clavatum',
            'common_name' => 'Club Moss',
            'potency' => '30C or 200C',
            'keynotes' => [
                'Brown spots on skin, especially abdomen',
                'Right-sided symptoms',
                '4-8 PM aggravation',
                'Premature aging appearance',
                'Liver involvement affecting skin'
            ],
            'skin_indications' => 'For brown pigmented spots, especially on abdomen and face. Age spots, liver spots. Constitutional remedy with right-sided focus.',
            'modalities' => [
                'worse' => ['4-8pm', 'warmth', 'right side'],
                'better' => ['motion', 'cool air', 'after midnight']
            ],
            'conditions' => ['Brown spots', 'Age spots', 'Liver spots', 'Premature aging skin'],
            'dosage' => '30C: evening dose; 200C: weekly',
            'general_indication' => 'For brown spots and liver-related pigmentation'
        ],
        'calcarea-fluor' => [
            'name' => 'Calcarea Fluorica',
            'common_name' => 'Calcium Fluoride',
            'potency' => '6X or 12X',
            'keynotes' => [
                'Hard, stony tumors',
                'Elasticity of tissues',
                'Indurated glands',
                'Keloids and hard scars',
                'Bone exostoses'
            ],
            'skin_indications' => 'For hard, indurated nodules. Helps soften hard tumors and nodules. Useful for dermatofibroma and hard skin lesions.',
            'modalities' => [
                'worse' => ['cold', 'damp', 'rest'],
                'better' => ['warmth', 'rubbing', 'continued motion']
            ],
            'conditions' => ['Hard nodules', 'Dermatofibroma', 'Keloids', 'Indurated lesions'],
            'dosage' => '6X or 12X: 4 tablets 3 times daily',
            'general_indication' => 'Tissue salt for hard, indurated skin lesions'
        ],
        'hydrastis' => [
            'name' => 'Hydrastis Canadensis',
            'common_name' => 'Golden Seal',
            'potency' => '30C or Mother Tincture',
            'keynotes' => [
                'Thick, ropy, yellow discharges',
                'Cancer tendency',
                'Weak, debilitated patients',
                'Ulcerations with tough, stringy base',
                'Liver affections'
            ],
            'skin_indications' => 'For pre-cancerous and suspicious skin lesions. Supports treatment of skin cancers. Ulcerating lesions with thick discharge.',
            'modalities' => [
                'worse' => ['open air', 'motion', 'night'],
                'better' => ['rest', 'pressure', 'warmth']
            ],
            'conditions' => ['Pre-cancerous lesions', 'Skin ulcers', 'Cancer support'],
            'dosage' => '30C: 3 pellets twice daily; MT: 10 drops 3 times daily',
            'general_indication' => 'Supportive remedy for suspicious or pre-cancerous skin lesions'
        ]
    ];
}

/**
 * Normalize remedy name for matching
 */
function normalizeRemedyName($name) {
    $name = strtolower(trim($name));
    $name = str_replace([' ', '_'], '-', $name);
    // Common abbreviations
    $abbrevMap = [
        'ars' => 'arsenicum',
        'ars-alb' => 'arsenicum',
        'rhus-t' => 'rhus-tox',
        'hep' => 'hepar-sulph',
        'hep-s' => 'hepar-sulph',
        'nat-m' => 'natrum-mur',
        'nat-mur' => 'natrum-mur',
        'calc' => 'calcarea',
        'calc-c' => 'calcarea',
        'sil' => 'silicea',
        'graph' => 'graphites',
        'sulph' => 'sulphur',
        'puls' => 'pulsatilla',
        'sep' => 'sepia',
        'lyc' => 'lycopodium',
        'bell' => 'belladonna',
        'merc' => 'mercurius',
        'nit-ac' => 'nitric-acid',
        'ant-c' => 'antimonium-crud',
        'kali-b' => 'kali-brom'
    ];
    
    return $abbrevMap[$name] ?? $name;
}

/**
 * Get remedy info from database
 */
function getRemedyFromDatabase($name) {
    $searchName = '%' . str_replace('-', '%', $name) . '%';
    return DB::queryOne(
        "SELECT MIN(id) as id, remedy_name, 
                GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms,
                GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications
         FROM remedies 
         WHERE LOWER(remedy_name) LIKE ? 
         GROUP BY remedy_name
         LIMIT 1",
        [$searchName]
    );
}

/**
 * Detect severity from symptom text
 */
function detectSeverity($text) {
    $severe = ['severe', 'intense', 'unbearable', 'extreme', 'spreading rapidly', 'bleeding', 'infected', 'fever', 'pus'];
    $mild = ['mild', 'slight', 'minor', 'occasional', 'small', 'little'];
    
    foreach ($severe as $word) {
        if (strpos($text, $word) !== false) return 'Severe';
    }
    foreach ($mild as $word) {
        if (strpos($text, $word) !== false) return 'Mild';
    }
    return 'Moderate';
}

/**
 * Extract key symptoms from text
 */
function extractKeySymptoms($text) {
    $symptoms = [];
    $keywords = ['itching', 'burning', 'redness', 'swelling', 'dry', 'scaly', 'cracked', 
                 'oozing', 'crusting', 'blisters', 'patches', 'spots', 'rash', 'pain',
                 'flaky', 'rough', 'inflamed', 'tender', 'warm', 'hot'];
    
    foreach ($keywords as $kw) {
        if (strpos($text, $kw) !== false) {
            $symptoms[] = ucfirst($kw);
        }
    }
    
    return array_slice($symptoms, 0, 6);
}

/**
 * Get Gemini AI skin analysis using Vision API
 */
function getGeminiSkinAnalysis($imagePath, $skinArea, $symptomsDescription, $patient = null) {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API key not configured');
    }
    
    // Read and encode image - optimize for API
    $imageData = file_get_contents($imagePath);
    $mimeType = mime_content_type($imagePath);
    
    // Optimize image if too large (resize for better API processing)
    $maxSize = 1920;
    $optimizedImage = optimizeImageForAnalysis($imagePath, $maxSize);
    if ($optimizedImage !== null) {
        $imageData = $optimizedImage['data'];
        $mimeType = $optimizedImage['mime'];
    }
    
    $base64Image = base64_encode($imageData);
    
    // Build context
    $patientContext = '';
    if ($patient) {
        $patientContext = "Patient Details: Name: {$patient['patient_name']}, Age: {$patient['age']} years, Gender: {$patient['gender']}.";
    }
    
    $symptomsContext = !empty($symptomsDescription) ? "Patient-reported symptoms: {$symptomsDescription}" : "No specific symptoms reported.";
    
    $prompt = <<<PROMPT
You are an expert dermatologist AI assistant specializing in skin condition analysis for homeopathic treatment. Analyze this skin image with clinical precision.

**Image Details:**
- Body Area: {$skinArea}
- {$symptomsContext}
{$patientContext}

**CRITICAL ANALYSIS INSTRUCTIONS:**
1. Examine the image carefully for: lesion morphology, color variations, texture, distribution pattern, borders, scaling, vesicles, papules, pustules, erythema, hyperpigmentation/hypopigmentation
2. Consider differential diagnoses based on visual findings
3. Assess severity based on: extent of involvement, inflammation level, signs of secondary infection
4. Provide homeopathic remedies that match the specific presentation

**RESPOND ONLY WITH THIS EXACT JSON FORMAT (no markdown, no explanation):**
{
  "condition": "Primary diagnosis (be specific, e.g., 'Atopic Dermatitis - Subacute Phase' not just 'Eczema')",
  "description": "Detailed clinical description of observed skin findings including morphology, distribution, and notable features",
  "characteristics": ["Observable finding 1", "Observable finding 2", "Observable finding 3", "Observable finding 4", "Observable finding 5"],
  "severity": "Mild|Moderate|Severe",
  "differential_diagnoses": ["Alternative diagnosis 1", "Alternative diagnosis 2"],
  "recommendations": "Brief clinical recommendation",
  "remedies": [
    {"name": "Remedy Name", "potency": "30C", "indication": "Specific indication matching the observed presentation"},
    {"name": "Remedy Name 2", "potency": "200C", "indication": "Why this remedy matches"},
    {"name": "Remedy Name 3", "potency": "6C", "indication": "Specific symptom match"},
    {"name": "Remedy Name 4", "potency": "30C", "indication": "Constitutional indication"},
    {"name": "Remedy Name 5", "potency": "12C", "indication": "Supporting indication"}
  ]
}

Include exactly 5 homeopathic remedies ranked by relevance to the visual presentation.
PROMPT;

    // Call Gemini Vision API - try multiple models (working models first)
    $modelsToTry = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.5-pro'];
    
    // Dedicated API key for Dermo feature + fallback to main keys
    $dermoApiKey = 'AIzaSyClJz0p-dHP2Wi94b11yRsWF28r1XFp0PA';
    $apiKeys = array_merge([$dermoApiKey], defined('GEMINI_API_KEYS') ? GEMINI_API_KEYS : [GEMINI_API_KEY]);
    $apiKeys = array_filter(array_unique($apiKeys)); // Remove duplicates and empty
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
            'temperature' => 0.2,  // Lower temperature for more accurate/consistent analysis
            'maxOutputTokens' => 8192,
            'topP' => 0.8,
            'topK' => 20,
            'responseMimeType' => 'application/json'  // Request JSON response
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
                $lastError = 'CURL error: ' . $curlError;
                error_log("Gemini Vision CURL error: " . $curlError);
                continue; // Try next key
            }
            
            if ($httpCode === 429) {
                // Rate limited, try next key/model
                $lastError = "Rate limit exceeded (HTTP 429)";
                error_log("Gemini Vision rate limited, trying next...");
                continue;
            }
            
            if ($httpCode !== 200) {
                $lastError = "API error (HTTP {$httpCode})";
                error_log("Gemini Vision API error (HTTP {$httpCode}): " . $response);
                continue; // Try next key
            }
            
            $data = json_decode($response, true);
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $lastError = 'Unexpected API response format';
                error_log('Unexpected Gemini response: ' . $response);
                continue; // Try next key
            }
            
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
            
            // Extract JSON from response - try to find complete JSON object
            $jsonMatch = [];
            
            // First try: find JSON between code blocks
            if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $responseText, $codeMatch)) {
                $jsonString = trim($codeMatch[1]);
                $analysisData = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($analysisData)) {
                    return $analysisData;
                }
            }
            
            // Second try: find raw JSON object
            if (preg_match('/\{[\s\S]*\}/m', $responseText, $jsonMatch)) {
                $jsonString = $jsonMatch[0];
                $analysisData = json_decode($jsonString, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($analysisData)) {
                    return $analysisData;
                }
            }
            
            // Third try: Parse the text response manually
            $parsed = parseTextResponse($responseText);
            if (!empty($parsed['condition']) || !empty($parsed['description'])) {
                return $parsed;
            }
            
            // If all parsing fails, create structured response from raw text
            return [
                'condition' => extractConditionFromText($responseText),
                'description' => cleanResponseText($responseText),
                'characteristics' => extractCharacteristicsFromText($responseText),
                'severity' => extractSeverityFromText($responseText),
                'remedies' => extractRemediesFromText($responseText)
            ];
        } // end foreach apiKeys
    } // end foreach models
    
    // All keys and models failed
    throw new Exception($lastError ?? 'All API keys exhausted (rate limited)');
}

/**
 * Parse text response when JSON parsing fails
 */
function parseTextResponse($text) {
    $result = [
        'condition' => '',
        'description' => '',
        'characteristics' => [],
        'severity' => 'Moderate',
        'remedies' => []
    ];
    
    // Try to extract condition
    if (preg_match('/condition["\s:]+([^",\n]+)/i', $text, $m)) {
        $result['condition'] = trim($m[1], " \t\n\r\0\x0B\"'");
    }
    
    // Try to extract description
    if (preg_match('/description["\s:]+([^"]+?)(?:"|,\s*"characteristics)/is', $text, $m)) {
        $result['description'] = trim($m[1], " \t\n\r\0\x0B\"'");
    }
    
    // Try to extract severity
    if (preg_match('/severity["\s:]+([^",\n]+)/i', $text, $m)) {
        $result['severity'] = trim($m[1], " \t\n\r\0\x0B\"'");
    }
    
    // Try to extract characteristics array
    if (preg_match('/characteristics["\s:]+\[([^\]]+)\]/is', $text, $m)) {
        $chars = preg_split('/[",]+/', $m[1]);
        $result['characteristics'] = array_filter(array_map('trim', $chars));
    }
    
    // Try to extract remedies
    if (preg_match_all('/name["\s:]+([^",]+)/i', $text, $names) && 
        preg_match_all('/potency["\s:]+([^",]+)/i', $text, $potencies) &&
        preg_match_all('/indication["\s:]+([^"]+?)(?:"|,\s*\})/is', $text, $indications)) {
        
        for ($i = 0; $i < min(count($names[1]), 5); $i++) {
            $result['remedies'][] = [
                'name' => trim($names[1][$i] ?? '', " \t\n\r\0\x0B\"'"),
                'potency' => trim($potencies[1][$i] ?? '30C', " \t\n\r\0\x0B\"'"),
                'indication' => trim($indications[1][$i] ?? '', " \t\n\r\0\x0B\"'")
            ];
        }
    }
    
    return $result;
}

/**
 * Extract condition from unstructured text
 */
function extractConditionFromText($text) {
    // Look for common skin condition names
    $conditions = ['eczema', 'psoriasis', 'acne', 'dermatitis', 'urticaria', 'rosacea', 
                   'vitiligo', 'fungal', 'ringworm', 'herpes', 'normal skin', 'healthy skin',
                   'clear skin', 'dry skin', 'oily skin'];
    
    $textLower = strtolower($text);
    foreach ($conditions as $cond) {
        if (strpos($textLower, $cond) !== false) {
            return ucwords($cond);
        }
    }
    
    // Try to extract from "appears to be" or similar phrases
    if (preg_match('/(?:appears to be|looks like|suggests?|indicates?|showing)\s+([a-z\s]+(?:skin|condition|appearance))/i', $text, $m)) {
        return ucwords(trim($m[1]));
    }
    
    return 'Skin Condition Analyzed';
}

/**
 * Clean response text for display
 */
function cleanResponseText($text) {
    // Remove JSON artifacts
    $text = preg_replace('/```json\s*/i', '', $text);
    $text = preg_replace('/```\s*/', '', $text);
    $text = preg_replace('/^\s*\{[\s\S]*$/', '', $text); // Remove JSON-like content
    $text = trim($text);
    
    if (empty($text)) {
        return 'The skin has been analyzed. Please see the characteristics and remedies for details.';
    }
    
    return $text;
}

/**
 * Extract characteristics from text
 */
function extractCharacteristicsFromText($text) {
    $chars = [];
    $keywords = ['redness', 'scaling', 'itching', 'dryness', 'oiliness', 'pustules', 
                 'vesicles', 'patches', 'lesions', 'inflammation', 'swelling', 
                 'discoloration', 'clear', 'healthy', 'smooth', 'even tone'];
    
    $textLower = strtolower($text);
    foreach ($keywords as $kw) {
        if (strpos($textLower, $kw) !== false) {
            $chars[] = ucfirst($kw);
        }
    }
    
    return array_slice($chars, 0, 5);
}

/**
 * Extract severity from text
 */
function extractSeverityFromText($text) {
    $textLower = strtolower($text);
    
    if (strpos($textLower, 'severe') !== false || strpos($textLower, 'serious') !== false) {
        return 'Severe';
    }
    if (strpos($textLower, 'mild') !== false || strpos($textLower, 'minor') !== false) {
        return 'Mild';
    }
    if (strpos($textLower, 'normal') !== false || strpos($textLower, 'healthy') !== false || strpos($textLower, 'clear') !== false) {
        return 'Normal';
    }
    
    return 'Moderate';
}

/**
 * Extract remedies from text
 */
function extractRemediesFromText($text) {
    $remedies = [];
    
    // Common homeopathic remedy names to look for
    $commonRemedies = [
        'Sulphur' => 'General skin remedy for itching and eruptions',
        'Graphites' => 'For eczema with sticky discharge',
        'Arsenicum Album' => 'For dry, scaly skin with burning',
        'Rhus Toxicodendron' => 'For vesicular eruptions with itching',
        'Apis Mellifica' => 'For swelling and stinging skin',
        'Natrum Muriaticum' => 'For oily skin and acne',
        'Pulsatilla' => 'For changeable skin conditions',
        'Sepia' => 'For pigmentation and hormonal skin issues',
        'Calcarea Carbonica' => 'For dry, unhealthy skin'
    ];
    
    $textLower = strtolower($text);
    foreach ($commonRemedies as $name => $indication) {
        $searchName = strtolower(explode(' ', $name)[0]); // First word
        if (strpos($textLower, $searchName) !== false) {
            $remedies[] = [
                'name' => $name,
                'potency' => '30C',
                'indication' => $indication
            ];
        }
    }
    
    return array_slice($remedies, 0, 5);
}

/**
 * Get RAG-based remedies from local database for skin conditions
 */
function getSkinRAGRemedies($condition, $characteristics = [], $symptomsDescription = '', $skinArea = 'general') {
    $remedies = [];
    $searchTerms = [];
    
    // Build search terms from various inputs
    if (!empty($condition)) {
        $searchTerms[] = strtolower($condition);
    }
    
    if (!empty($characteristics)) {
        foreach ($characteristics as $char) {
            $searchTerms[] = strtolower($char);
        }
    }
    
    if (!empty($symptomsDescription)) {
        // Extract key terms from symptoms description
        $words = preg_split('/[\s,;.]+/', strtolower($symptomsDescription));
        $searchTerms = array_merge($searchTerms, $words);
    }
    
    // Add skin area
    if (!empty($skinArea) && $skinArea !== 'general') {
        $searchTerms[] = strtolower($skinArea);
    }
    
    // Remove common words and duplicates
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just'];
    
    $searchTerms = array_filter($searchTerms, function($term) use ($stopWords) {
        return strlen($term) > 2 && !in_array($term, $stopWords);
    });
    $searchTerms = array_unique($searchTerms);
    
    // Skin-specific remedy mappings for RAG
    $skinRemedyMappings = getSkinRemedyMappings();
    
    // Score remedies based on matching terms
    $remedyScores = [];
    
    foreach ($searchTerms as $term) {
        foreach ($skinRemedyMappings as $keyword => $mappedRemedies) {
            if (strpos($keyword, $term) !== false || strpos($term, $keyword) !== false) {
                foreach ($mappedRemedies as $remedy) {
                    if (!isset($remedyScores[$remedy])) {
                        $remedyScores[$remedy] = ['score' => 0, 'indications' => []];
                    }
                    $remedyScores[$remedy]['score'] += 3;
                    $remedyScores[$remedy]['indications'][] = $keyword;
                }
            }
        }
    }
    
    // Sort by score
    arsort($remedyScores);
    
    // Fetch remedy details from database
    $topRemedies = array_slice(array_keys($remedyScores), 0, 10);
    
    if (!empty($topRemedies)) {
        foreach ($topRemedies as $remedyName) {
            $pattern = '%' . $remedyName . '%';
            $dbRemedy = DB::queryOne(
                "SELECT MIN(id) as id, remedy_name, 
                        MAX(common_name) as common_name,
                        GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms, 
                        GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications
                 FROM remedies 
                 WHERE LOWER(remedy_name) LIKE ?
                 GROUP BY remedy_name
                 LIMIT 1",
                [$pattern]
            );
            
            if ($dbRemedy) {
                $remedies[] = [
                    'id' => $dbRemedy['id'],
                    'remedy_name' => $dbRemedy['remedy_name'],
                    'common_name' => $dbRemedy['common_name'],
                    'keynote_symptoms' => $dbRemedy['keynote_symptoms'],
                    'clinical_indications' => $dbRemedy['clinical_indications'],
                    'score' => $remedyScores[$remedyName]['score'],
                    'indications' => array_unique($remedyScores[$remedyName]['indications'])
                ];
            } else {
                // Add from mapping even if not in database
                $remedies[] = [
                    'remedy_name' => ucfirst($remedyName),
                    'score' => $remedyScores[$remedyName]['score'],
                    'indications' => array_unique($remedyScores[$remedyName]['indications'])
                ];
            }
        }
    }
    
    // If no matches found, do a general database search for skin-related remedies
    if (empty($remedies) && !empty($searchTerms)) {
        $searchString = implode(' ', array_slice($searchTerms, 0, 5));
        $dbRemedies = DB::query(
            "SELECT MIN(id) as id, remedy_name, 
                    MAX(common_name) as common_name,
                    GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms,
                    GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications
             FROM remedies 
             WHERE LOWER(keynote_symptoms) LIKE ? 
                OR LOWER(clinical_indications) LIKE ?
                OR LOWER(remedy_name) LIKE ?
             GROUP BY remedy_name
             LIMIT 10",
            ['%' . strtolower($searchString) . '%', '%' . strtolower($searchString) . '%', '%' . strtolower($searchString) . '%']
        );
        
        foreach ($dbRemedies as $r) {
            $remedies[] = [
                'id' => $r['id'],
                'remedy_name' => $r['remedy_name'],
                'common_name' => $r['common_name'],
                'keynote_symptoms' => $r['keynote_symptoms'],
                'clinical_indications' => $r['clinical_indications'],
                'score' => 5,
                'indications' => ['database match']
            ];
        }
    }
    
    // If still no results, return common skin remedies as fallback
    if (empty($remedies)) {
        $commonSkinRemedies = ['Sulphur', 'Graphites', 'Arsenicum Album', 'Rhus Toxicodendron', 'Apis Mellifica'];
        foreach ($commonSkinRemedies as $index => $name) {
            $dbRemedy = DB::queryOne(
                "SELECT MIN(id) as id, remedy_name, MAX(common_name) as common_name,
                        GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms
                 FROM remedies WHERE LOWER(remedy_name) LIKE ? GROUP BY remedy_name LIMIT 1",
                ['%' . strtolower(explode(' ', $name)[0]) . '%']
            );
            
            if ($dbRemedy) {
                $remedies[] = [
                    'id' => $dbRemedy['id'],
                    'remedy_name' => $dbRemedy['remedy_name'],
                    'common_name' => $dbRemedy['common_name'],
                    'keynote_symptoms' => $dbRemedy['keynote_symptoms'],
                    'score' => 10 - $index,
                    'indications' => ['common skin remedy']
                ];
            } else {
                $remedies[] = [
                    'remedy_name' => $name,
                    'score' => 10 - $index,
                    'indications' => ['common skin remedy']
                ];
            }
        }
    }
    
    return $remedies;
}

/**
 * Comprehensive skin remedy mappings for homeopathy
 */
function getSkinRemedyMappings() {
    return [
        // Eczema and related
        'eczema' => ['graphites', 'sulphur', 'arsenicum', 'mezereum', 'petroleum', 'rhus-tox', 'calcarea'],
        'atopic' => ['sulphur', 'graphites', 'arsenicum', 'calcarea', 'lycopodium'],
        'dry skin' => ['petroleum', 'graphites', 'sulphur', 'arsenicum', 'alumina'],
        'cracked skin' => ['petroleum', 'graphites', 'natrum-mur', 'nitric-acid', 'antimonium-crudum'],
        'weeping eczema' => ['graphites', 'mezereum', 'rhus-tox', 'croton-tig'],
        'lichenification' => ['sulphur', 'lycopodium', 'graphites', 'arsenicum'],
        'flexural' => ['graphites', 'sulphur', 'psorinum', 'lycopodium', 'sepia'],
        
        // Psoriasis
        'psoriasis' => ['arsenicum-iod', 'graphites', 'sulphur', 'petroleum', 'arsenicum', 'sepia', 'kali-ars'],
        'scaly' => ['arsenicum', 'graphites', 'kali-ars', 'sepia', 'sulphur'],
        'silvery scales' => ['arsenicum-iod', 'kali-ars', 'sepia'],
        'plaque' => ['graphites', 'sulphur', 'arsenicum'],
        'auspitz sign' => ['arsenicum-iod', 'sulphur', 'graphites'],
        'guttate' => ['arsenicum', 'sulphur', 'kali-ars'],
        'extensor' => ['arsenicum', 'sulphur', 'sepia', 'graphites'],
        
        // Acne
        'acne' => ['sulphur', 'hepar-sulph', 'kali-brom', 'silicea', 'berberis-aq', 'pulsatilla'],
        'pimples' => ['sulphur', 'hepar-sulph', 'calcarea-sulph', 'antimonium-crud'],
        'blackheads' => ['sulphur', 'nitric-acid', 'eugenia'],
        'pustular' => ['hepar-sulph', 'silicea', 'calcarea-sulph', 'kali-bich'],
        'cystic acne' => ['silicea', 'calcarea-sulph', 'sulphur', 'tuberculinum'],
        'oily skin' => ['natrum-mur', 'thuja', 'selenium'],
        'comedones' => ['sulphur', 'nitric-acid', 'berberis-aq'],
        'papulopustular' => ['hepar-sulph', 'sulphur', 'kali-brom'],
        
        // Dermatitis
        'dermatitis' => ['sulphur', 'rhus-tox', 'graphites', 'mezereum', 'croton-tig'],
        'contact dermatitis' => ['rhus-tox', 'anacardium', 'croton-tig', 'apis'],
        'seborrheic' => ['natrum-mur', 'sulphur', 'graphites', 'oleander'],
        'seborrhoeic' => ['natrum-mur', 'sulphur', 'graphites', 'oleander'],
        
        // Urticaria / Hives
        'urticaria' => ['apis', 'urtica-urens', 'rhus-tox', 'natrum-mur', 'dulcamara'],
        'hives' => ['apis', 'urtica-urens', 'rhus-tox', 'chloral'],
        'welts' => ['apis', 'urtica-urens', 'rhus-tox'],
        'wheals' => ['apis', 'urtica-urens', 'rhus-tox', 'histaminum'],
        'allergic rash' => ['apis', 'sulphur', 'rhus-tox', 'histaminum'],
        'dermographism' => ['apis', 'urtica-urens', 'histaminum'],
        'evanescent' => ['apis', 'urtica-urens'],
        'angioedema' => ['apis', 'arsenicum', 'natrum-mur'],
        
        // Fungal infections
        'fungal' => ['sepia', 'tellurium', 'sulphur', 'graphites', 'bacillinum'],
        'ringworm' => ['tellurium', 'sepia', 'sulphur', 'chrysarobinum', 'bacillinum'],
        'tinea' => ['tellurium', 'sepia', 'sulphur', 'chrysarobinum'],
        'candida' => ['borax', 'helonias', 'kreosotum', 'thuja'],
        'nummular' => ['arsenicum', 'graphites', 'tellurium', 'sepia'],
        'discoid' => ['arsenicum', 'graphites', 'tellurium', 'sepia'],
        'hyphae' => ['tellurium', 'sepia', 'sulphur'],
        
        // Vitiligo
        'vitiligo' => ['arsenicum-sulph-flavum', 'hydrocotyle', 'sepia', 'syphilinum', 'phosphorus'],
        'depigmentation' => ['arsenicum-sulph-flavum', 'hydrocotyle', 'natrum-mur'],
        'white patches' => ['arsenicum-sulph-flavum', 'hydrocotyle', 'sepia'],
        'amelanotic' => ['arsenicum-sulph-flavum', 'hydrocotyle'],
        
        // Herpes
        'herpes' => ['rhus-tox', 'natrum-mur', 'hepar-sulph', 'graphites', 'arsenicum'],
        'herpes simplex' => ['natrum-mur', 'rhus-tox', 'sepia', 'hepar-sulph'],
        'herpes zoster' => ['rhus-tox', 'ranunculus', 'mezereum', 'arsenicum', 'variolinum'],
        'shingles' => ['rhus-tox', 'ranunculus', 'mezereum', 'arsenicum'],
        'cold sores' => ['natrum-mur', 'rhus-tox', 'hepar-sulph'],
        'blisters' => ['rhus-tox', 'cantharis', 'ranunculus', 'arsenicum'],
        'vesicles' => ['rhus-tox', 'graphites', 'ranunculus', 'mezereum'],
        'grouped vesicles' => ['rhus-tox', 'ranunculus', 'natrum-mur'],
        'herpetiform' => ['rhus-tox', 'ranunculus', 'mezereum'],
        'dermatomal' => ['rhus-tox', 'ranunculus', 'mezereum', 'arsenicum'],
        
        // Scabies
        'scabies' => ['sulphur', 'psorinum', 'arsenicum', 'sepia', 'causticum'],
        'intense itching' => ['sulphur', 'arsenicum', 'mezereum', 'psorinum'],
        'nocturnal itching' => ['sulphur', 'psorinum', 'arsenicum'],
        'burrow' => ['sulphur', 'psorinum', 'arsenicum'],
        'interdigital' => ['sulphur', 'graphites', 'psorinum'],
        
        // Impetigo
        'impetigo' => ['antimonium-crud', 'mezereum', 'graphites', 'sulphur', 'arsenicum'],
        'honey-colored crust' => ['antimonium-crud', 'mezereum', 'graphites'],
        'golden crust' => ['antimonium-crud', 'mezereum'],
        
        // Bullous diseases
        'pemphigus' => ['arsenicum', 'phosphorus', 'mercurius', 'rhus-tox', 'lachesis'],
        'bullous' => ['arsenicum', 'cantharis', 'rhus-tox', 'ranunculus'],
        'bulla' => ['arsenicum', 'cantharis', 'rhus-tox'],
        'nikolsky sign' => ['arsenicum', 'mercurius', 'phosphorus'],
        'flaccid bulla' => ['arsenicum', 'mercurius'],
        'tense bulla' => ['cantharis', 'rhus-tox'],
        
        // Lichen Planus
        'lichen planus' => ['sulphur', 'arsenicum', 'phosphorus', 'antimonium-crud'],
        'flat-topped papule' => ['sulphur', 'arsenicum', 'antimonium-crud'],
        'violaceous' => ['sulphur', 'arsenicum', 'lachesis'],
        'wickham striae' => ['sulphur', 'arsenicum'],
        
        // Molluscum
        'molluscum' => ['thuja', 'sulphur', 'silicea', 'antimonium-crud'],
        'umbilicated' => ['thuja', 'antimonium-crud'],
        'central dimple' => ['thuja', 'antimonium-crud'],
        
        // Erythema Multiforme
        'erythema multiforme' => ['rhus-tox', 'apis', 'arsenicum', 'belladonna'],
        'target lesion' => ['rhus-tox', 'apis', 'arsenicum'],
        'targetoid' => ['rhus-tox', 'apis'],
        'iris lesion' => ['rhus-tox', 'apis'],
        
        // Purpura / Vasculitis
        'purpura' => ['phosphorus', 'lachesis', 'arsenicum', 'secale', 'crotalus'],
        'petechiae' => ['phosphorus', 'lachesis', 'arsenicum'],
        'ecchymosis' => ['phosphorus', 'arnica', 'lachesis'],
        'vasculitis' => ['arsenicum', 'phosphorus', 'lachesis', 'mercurius'],
        'non-blanching' => ['phosphorus', 'lachesis', 'arsenicum'],
        
        // Symptoms
        'itching' => ['sulphur', 'arsenicum', 'graphites', 'dolichos', 'mezereum'],
        'burning' => ['arsenicum', 'apis', 'cantharis', 'sulphur', 'phosphorus'],
        'redness' => ['belladonna', 'apis', 'sulphur', 'rhus-tox'],
        'swelling' => ['apis', 'rhus-tox', 'arsenicum', 'bryonia'],
        'oozing' => ['graphites', 'mezereum', 'rhus-tox', 'croton-tig'],
        'crusting' => ['mezereum', 'graphites', 'antimonium-crud', 'cicuta'],
        'bleeding' => ['phosphorus', 'lachesis', 'mercurius', 'nitric-acid'],
        'pain' => ['apis', 'arsenicum', 'rhus-tox', 'hepar-sulph'],
        'excoriation' => ['sulphur', 'arsenicum', 'mezereum', 'graphites'],
        'erosion' => ['arsenicum', 'mercurius', 'nitric-acid'],
        'ulceration' => ['arsenicum', 'mercurius', 'nitric-acid', 'silicea', 'hepar-sulph'],
        'fissure' => ['petroleum', 'graphites', 'nitric-acid', 'natrum-mur'],
        'scale' => ['arsenicum', 'graphites', 'sulphur', 'kali-ars'],
        'scar' => ['thiosinaminum', 'graphites', 'silicea', 'fluoricum-acid'],
        
        // ===============================
        // PIGMENTED LESIONS / NEVI / MOLES
        // ===============================
        // Nevi and Moles
        'nevus' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur', 'graphites'],
        'nevi' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur', 'graphites'],
        'mole' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur'],
        'moles' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur'],
        'pigmented lesion' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur'],
        'pigmented nevus' => ['thuja', 'nitric-acid', 'phosphorus', 'carcinosin', 'sulphur'],
        'melanocytic' => ['thuja', 'carcinosin', 'phosphorus', 'arsenicum', 'sulphur'],
        'birthmark' => ['thuja', 'phosphorus', 'calcarea', 'sulphur'],
        'congenital nevus' => ['thuja', 'phosphorus', 'calcarea-fluor', 'sulphur'],
        'acquired nevus' => ['thuja', 'nitric-acid', 'sulphur', 'graphites'],
        'junctional' => ['thuja', 'phosphorus', 'sulphur'],
        'compound nevus' => ['thuja', 'nitric-acid', 'phosphorus'],
        'intradermal' => ['thuja', 'calcarea', 'sulphur'],
        
        // Atypical / Dysplastic Nevi
        'atypical' => ['carcinosin', 'thuja', 'arsenicum', 'phosphorus', 'conium'],
        'dysplastic' => ['carcinosin', 'thuja', 'arsenicum', 'phosphorus', 'conium'],
        'atypical nevus' => ['carcinosin', 'thuja', 'arsenicum', 'phosphorus', 'nitric-acid'],
        'dysplastic nevus' => ['carcinosin', 'thuja', 'arsenicum', 'phosphorus', 'nitric-acid'],
        'irregular borders' => ['carcinosin', 'arsenicum', 'thuja', 'nitric-acid', 'phosphorus'],
        'color variation' => ['carcinosin', 'arsenicum', 'thuja', 'phosphorus'],
        'heterogeneous' => ['carcinosin', 'arsenicum', 'thuja', 'phosphorus'],
        'asymmetric' => ['carcinosin', 'arsenicum', 'thuja', 'phosphorus'],
        'variegated' => ['carcinosin', 'arsenicum', 'phosphorus'],
        'changing mole' => ['carcinosin', 'arsenicum', 'thuja', 'phosphorus', 'conium'],
        
        // Seborrheic Keratosis
        'seborrheic keratosis' => ['thuja', 'antimonium-crud', 'causticum', 'sulphur', 'graphites'],
        'stuck on' => ['thuja', 'antimonium-crud', 'causticum'],
        'waxy' => ['thuja', 'antimonium-crud', 'graphites'],
        'horn cysts' => ['antimonium-crud', 'thuja', 'causticum'],
        'senile wart' => ['thuja', 'antimonium-crud', 'causticum', 'nitric-acid'],
        
        // Lentigines
        'lentigo' => ['sepia', 'lycopodium', 'phosphorus', 'sulphur', 'natrum-carb'],
        'lentigines' => ['sepia', 'lycopodium', 'phosphorus', 'sulphur', 'natrum-carb'],
        'liver spot' => ['sepia', 'lycopodium', 'sulphur', 'phosphorus'],
        'age spot' => ['sepia', 'lycopodium', 'sulphur', 'phosphorus'],
        'sun spot' => ['sepia', 'lycopodium', 'sulphur', 'natrum-mur'],
        'solar lentigo' => ['sepia', 'lycopodium', 'sulphur', 'natrum-mur'],
        
        // Skin Cancers (for awareness - always refer)
        'melanoma' => ['carcinosin', 'arsenicum', 'phosphorus', 'conium', 'hydrastis'],
        'basal cell' => ['arsenicum', 'conium', 'phosphorus', 'thuja', 'hydrastis'],
        'bcc' => ['arsenicum', 'conium', 'phosphorus', 'thuja'],
        'squamous cell' => ['arsenicum', 'conium', 'phosphorus', 'hydrastis', 'thuja'],
        'scc' => ['arsenicum', 'conium', 'phosphorus', 'thuja'],
        'pearly' => ['arsenicum', 'conium', 'silicea'],
        'rolled border' => ['arsenicum', 'conium', 'silicea'],
        'non-healing ulcer' => ['arsenicum', 'mercurius', 'silicea', 'nitric-acid'],
        'actinic keratosis' => ['arsenicum', 'sulphur', 'thuja', 'nitric-acid'],
        'solar keratosis' => ['arsenicum', 'sulphur', 'thuja'],
        'precancer' => ['arsenicum', 'thuja', 'conium', 'carcinosin'],
        
        // Dermatofibroma
        'dermatofibroma' => ['silicea', 'calcarea-fluor', 'baryta-carb', 'graphites'],
        'dimple sign' => ['silicea', 'calcarea-fluor'],
        'fibrous' => ['silicea', 'calcarea-fluor', 'thiosinaminum'],
        
        // Other pigmented conditions
        'cafe au lait' => ['phosphorus', 'calcarea', 'sulphur', 'lycopodium'],
        'cafe-au-lait' => ['phosphorus', 'calcarea', 'sulphur', 'lycopodium'],
        'post-inflammatory' => ['sepia', 'sulphur', 'berberis', 'thuja'],
        'pih' => ['sepia', 'sulphur', 'berberis', 'thuja'],
        'dark spot' => ['sepia', 'lycopodium', 'sulphur', 'thuja', 'berberis'],
        'brown spot' => ['sepia', 'lycopodium', 'sulphur', 'phosphorus'],
        
        // Body areas
        'face' => ['sulphur', 'calcarea', 'graphites', 'rhus-tox', 'cicuta'],
        'scalp' => ['graphites', 'sulphur', 'oleander', 'mezereum', 'natrum-mur'],
        'hands' => ['petroleum', 'graphites', 'natrum-mur', 'sulphur'],
        'feet' => ['graphites', 'silica', 'sulphur', 'petroleum'],
        'folds' => ['graphites', 'sulphur', 'psorinum', 'lycopodium'],
        'malar' => ['sepia', 'lycopodium', 'natrum-mur', 'sulphur'],
        'nasolabial' => ['natrum-mur', 'sulphur', 'graphites'],
        'genital' => ['thuja', 'nitric-acid', 'mercurius', 'hepar-sulph'],
        'acral' => ['arsenicum', 'phosphorus', 'silicea'],
        
        // Conditions
        'warts' => ['thuja', 'nitric-acid', 'antimonium-crud', 'causticum', 'dulcamara'],
        'verruca' => ['thuja', 'nitric-acid', 'antimonium-crud', 'causticum'],
        'verrucous' => ['thuja', 'antimonium-crud', 'nitric-acid'],
        'papillomatous' => ['thuja', 'antimonium-crud'],
        'moles' => ['thuja', 'phosphorus', 'sulphur'],
        'boils' => ['hepar-sulph', 'silicea', 'belladonna', 'arsenicum', 'calcarea-sulph'],
        'abscess' => ['hepar-sulph', 'silicea', 'mercurius', 'calcarea-sulph'],
        'carbuncle' => ['anthracinum', 'arsenicum', 'tarentula-cub', 'silicea'],
        'cellulitis' => ['apis', 'arsenicum', 'rhus-tox', 'belladonna', 'lachesis'],
        'rosacea' => ['carbo-an', 'rhus-tox', 'eugenia', 'lachesis'],
        'telangiectasia' => ['carbo-an', 'lachesis', 'phosphorus', 'fluoricum-acid'],
        'keloid' => ['graphites', 'silicea', 'thiosinaminum', 'fluoricum-acid'],
        'stretch marks' => ['calcarea-fluor', 'graphites', 'silicea'],
        'pigmentation' => ['sepia', 'lycopodium', 'berberis', 'sulphur'],
        'melasma' => ['sepia', 'lycopodium', 'thuja', 'plumbum'],
        'chloasma' => ['sepia', 'lycopodium', 'natrum-mur'],
        'hyperpigmentation' => ['sepia', 'lycopodium', 'argentum-nit'],
        'freckles' => ['lycopodium', 'phosphorus', 'sulphur', 'natrum-carb'],
        'sunburn' => ['cantharis', 'urtica-urens', 'belladonna', 'sol'],
        'photosensitive' => ['sulphur', 'natrum-mur', 'phosphorus'],
        
        // Distribution patterns
        'symmetrical' => ['arsenicum', 'sulphur', 'graphites', 'sepia'],
        'unilateral' => ['rhus-tox', 'ranunculus', 'mezereum'],
        'generalized' => ['sulphur', 'arsenicum', 'psorinum'],
        'koebner' => ['sulphur', 'graphites', 'arsenicum', 'thuja'],
        
        // Modalities
        'worse heat' => ['sulphur', 'apis', 'pulsatilla', 'natrum-mur'],
        'worse cold' => ['arsenicum', 'hepar-sulph', 'rhus-tox', 'silicea'],
        'worse night' => ['sulphur', 'arsenicum', 'mercurius', 'psorinum'],
        'worse scratching' => ['sulphur', 'arsenicum', 'psorinum', 'graphites'],
        'better cold' => ['apis', 'pulsatilla', 'sulphur', 'ledum'],
        'better warmth' => ['arsenicum', 'rhus-tox', 'hepar-sulph', 'silicea'],
        
        // Primary lesion types
        'macule' => ['arsenicum-sulph-flavum', 'phosphorus', 'sepia'],
        'patch' => ['arsenicum-sulph-flavum', 'sepia', 'natrum-mur'],
        'papule' => ['sulphur', 'arsenicum', 'antimonium-crud', 'rhus-tox'],
        'nodule' => ['silicea', 'hepar-sulph', 'calcarea-fluor', 'baryta-carb'],
        'cyst' => ['silicea', 'calcarea', 'graphites', 'baryta-carb'],
        'tumor' => ['silicea', 'calcarea-fluor', 'conium', 'phytolacca'],
    ];
}

/**
 * Analyze skin symptoms text only (without image)
 * Useful for quick remedy suggestions based on description
 */
function analyzeSkinsymptomsOnly($symptomsDescription, $skinArea = 'general', $patient = null) {
    $result = [
        'ai_analysis' => [],
        'rag_remedies' => [],
        'success' => false
    ];
    
    try {
        // Get AI analysis
        $aiAnalysis = getGeminiTextAnalysis($symptomsDescription, $skinArea, $patient);
        $result['ai_analysis'] = $aiAnalysis;
        
        // Get RAG remedies
        $condition = $aiAnalysis['condition'] ?? '';
        $characteristics = $aiAnalysis['characteristics'] ?? [];
        $result['rag_remedies'] = getSkinRAGRemedies($condition, $characteristics, $symptomsDescription, $skinArea);
        
        $result['success'] = true;
    } catch (Exception $e) {
        error_log('Text skin analysis error: ' . $e->getMessage());
        $result['rag_remedies'] = getSkinRAGRemedies('', [], $symptomsDescription, $skinArea);
    }
    
    return $result;
}

/**
 * Get Gemini analysis based on text description only
 */
function getGeminiTextAnalysis($symptomsDescription, $skinArea, $patient = null) {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API key not configured');
    }
    
    $patientContext = '';
    if ($patient) {
        $patientContext = "Patient: {$patient['patient_name']}, Age: {$patient['age']}, Gender: {$patient['gender']}.";
    }
    
    $prompt = <<<PROMPT
You are an expert dermatologist and homeopathic physician. Based on the following symptom description, provide an analysis and remedy suggestions.

{$patientContext}
Affected Area: {$skinArea}
Symptoms Description: {$symptomsDescription}

Please provide:
1. Most likely skin condition
2. Key characteristics based on the description
3. Severity assessment
4. Top 5 homeopathic remedies with potency and indications

Respond in JSON format:
{
    "condition": "Likely condition name",
    "description": "Analysis of the symptoms",
    "characteristics": ["characteristic1", "characteristic2"],
    "severity": "Mild|Moderate|Severe",
    "remedies": [
        {
            "name": "Remedy Name",
            "potency": "30C",
            "indication": "Why this remedy"
        }
    ]
}
PROMPT;

    try {
        $gemini = new GeminiAPI();
        $response = $gemini->generateContent($prompt);
        
        if ($response['success'] && !empty($response['text'])) {
            $jsonMatch = [];
            if (preg_match('/\{[\s\S]*\}/m', $response['text'], $jsonMatch)) {
                $analysisData = json_decode($jsonMatch[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analysisData;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Gemini text analysis error: ' . $e->getMessage());
    }
    
    return [
        'condition' => 'Unable to determine',
        'description' => 'Analysis based on symptoms description',
        'characteristics' => [],
        'severity' => 'Unknown',
        'remedies' => []
    ];
}
