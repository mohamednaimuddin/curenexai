<?php
/**
 * RAG (Retrieval-Augmented Generation) Functions for Homeopathy
 * Comprehensive version with clinical priority mappings
 * Based on standard homeopathic materia medica and repertory
 */

if (!function_exists('extractMedicalPhrases')) {
    /**
     * Extract medical phrases and expand to clinical terms
     */
    function extractMedicalPhrases($text) {
        $text = strtolower($text);
        $medicalTerms = [];
        
        // Clinical expansions - map symptoms to clinical terminology
        $clinicalExpansions = [
            'headache' => ['cephalalgia', 'head pain', 'migraine'],
            'migraine' => ['hemicranial', 'sick headache', 'one-sided headache'],
            'throbbing' => ['pulsating', 'beating', 'hammering'],
            'burning' => ['heat', 'scorching', 'fire'],
            'stomach' => ['gastric', 'epigastric', 'digestive'],
            'acidity' => ['hyperacidity', 'sour', 'acid dyspepsia'],
            'joint' => ['articular', 'rheumatic', 'arthritis'],
            'anxiety' => ['apprehension', 'fear', 'nervousness'],
            'cough' => ['tussis', 'bronchial', 'respiratory'],
            'fever' => ['pyrexia', 'febrile', 'temperature'],
            'diarrhea' => ['loose stool', 'watery stool', 'bowel'],
            'constipation' => ['hard stool', 'difficult stool', 'sluggish bowel'],
            'skin' => ['dermal', 'cutaneous', 'eruption'],
            'itching' => ['pruritus', 'itchy', 'scratching'],
            'urination' => ['micturition', 'urinary', 'bladder'],
            'period' => ['menses', 'menstrual', 'menstruation'],
            'irregular' => ['scanty', 'delayed', 'suppressed'],
        ];
        
        // Body parts
        $bodyParts = ['head', 'temple', 'occiput', 'vertex', 'forehead', 'eye', 'ear', 
            'nose', 'throat', 'chest', 'heart', 'stomach', 'liver', 'abdomen', 'back', 
            'spine', 'shoulder', 'arm', 'wrist', 'hand', 'hip', 'leg', 'knee', 'ankle', 
            'foot', 'skin', 'joint', 'muscle', 'nerve'];
        
        // Symptom terms
        $symptomTerms = ['pain', 'ache', 'burning', 'throbbing', 'pressing', 'stitching',
            'numbness', 'weakness', 'swelling', 'stiffness', 'itching', 'fever', 'chill',
            'nausea', 'vomiting', 'diarrhea', 'constipation', 'cough', 'wheeze', 'anxiety',
            'fear', 'restless', 'fatigue', 'tired', 'insomnia', 'palpitation'];
        
        // Extract and expand
        foreach ($clinicalExpansions as $trigger => $expansions) {
            if (strpos($text, $trigger) !== false) {
                $medicalTerms[] = $trigger;
                $medicalTerms = array_merge($medicalTerms, $expansions);
            }
        }
        
        foreach ($bodyParts as $term) {
            if (strpos($text, $term) !== false) $medicalTerms[] = $term;
        }
        foreach ($symptomTerms as $term) {
            if (strpos($text, $term) !== false) $medicalTerms[] = $term;
        }
        
        return array_unique($medicalTerms);
    }
}

if (!function_exists('generateRAGFromDatabase')) {
    /**
     * Generate remedy suggestions with clinical priority mappings
     * Uses proven homeopathic clinical associations
     */
    function generateRAGFromDatabase($consultation) {
        $symptoms = $consultation['symptoms'] ?? [];
        $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
        
        if (empty($symptoms) && empty($chiefComplaint)) {
            return ['error' => 'No symptoms to analyze', 'remedies' => []];
        }
        
        // ========================================
        // CLINICAL PRIORITY MAPPINGS
        // Based on Boericke, Kent, Allen's Keynotes
        // ========================================
        $priorityRemedies = [
            // HEADACHE / MIGRAINE
            'headache' => [
                'Belladonna' => 25, 'Spigelia Anthelmia' => 22, 'Gelsemium Sempervirens' => 20,
                'Bryonia Alba' => 18, 'Natrum Muriaticum' => 18, 'Nux Vomica' => 15,
                'Sanguinaria Canadensis' => 15, 'Iris Versicolor' => 14, 'Glonoine' => 12
            ],
            'migraine' => [
                'Belladonna' => 25, 'Spigelia Anthelmia' => 24, 'Iris Versicolor' => 22,
                'Sanguinaria Canadensis' => 20, 'Natrum Muriaticum' => 18, 
                'Gelsemium Sempervirens' => 16, 'Kali Bichromicum' => 14
            ],
            'throbbing' => [
                'Belladonna' => 25, 'Glonoine' => 20, 'Natrum Muriaticum' => 15,
                'China Officinalis' => 12, 'Ferrum Metallicum' => 10
            ],
            'photophobia' => [
                'Belladonna' => 22, 'Natrum Muriaticum' => 18, 'Phosphorus' => 15,
                'Conium Maculatum' => 12, 'Graphites' => 10
            ],
            
            // GASTRIC / DIGESTIVE
            'gastritis' => [
                'Nux Vomica' => 25, 'Arsenicum Album' => 22, 'Carbo Vegetabilis' => 20,
                'Lycopodium Clavatum' => 18, 'Phosphorus' => 16, 'Pulsatilla Nigricans' => 14
            ],
            'acidity' => [
                'Nux Vomica' => 25, 'Carbo Vegetabilis' => 22, 'Robinia Pseudoacacia' => 20,
                'Arsenicum Album' => 18, 'Lycopodium Clavatum' => 16, 'Sulphur' => 14
            ],
            'burning' => [
                'Arsenicum Album' => 22, 'Sulphur' => 20, 'Phosphorus' => 18,
                'Cantharis' => 16, 'Apis Mellifica' => 14
            ],
            'bloating' => [
                'Lycopodium Clavatum' => 25, 'Carbo Vegetabilis' => 22, 'China Officinalis' => 18,
                'Nux Vomica' => 16, 'Argentum Nitricum' => 14
            ],
            'nausea' => [
                'Ipecacuanha' => 25, 'Nux Vomica' => 20, 'Arsenicum Album' => 18,
                'Phosphorus' => 15, 'Sepia' => 12, 'Cocculus Indicus' => 12
            ],
            
            // FEVER
            'fever' => [
                'Aconitum Napellus' => 25, 'Belladonna' => 24, 'Bryonia Alba' => 20,
                'Gelsemium Sempervirens' => 18, 'Ferrum Phosphoricum' => 16,
                'Arsenicum Album' => 14, 'Rhus Toxicodendron' => 12
            ],
            'chills' => [
                'Arsenicum Album' => 22, 'Nux Vomica' => 20, 'China Officinalis' => 18,
                'Rhus Toxicodendron' => 16, 'Gelsemium Sempervirens' => 14
            ],
            
            // RESPIRATORY
            'cough' => [
                'Bryonia Alba' => 22, 'Phosphorus' => 20, 'Drosera Rotundifolia' => 20,
                'Spongia Tosta' => 18, 'Rumex Crispus' => 16, 'Antimonium Tartaricum' => 14
            ],
            'asthma' => [
                'Arsenicum Album' => 25, 'Ipecacuanha' => 22, 'Spongia Tosta' => 20,
                'Kali Carbonicum' => 18, 'Natrum Sulphuricum' => 16, 'Sambucus Nigra' => 14
            ],
            'wheeze' => [
                'Arsenicum Album' => 22, 'Ipecacuanha' => 20, 'Antimonium Tartaricum' => 18,
                'Kali Carbonicum' => 16, 'Carbo Vegetabilis' => 14
            ],
            'dyspnea' => [
                'Arsenicum Album' => 22, 'Carbo Vegetabilis' => 20, 'Lachesis' => 18,
                'Phosphorus' => 16, 'Digitalis' => 14
            ],
            
            // JOINT / ARTHRITIS
            'arthritis' => [
                'Rhus Toxicodendron' => 25, 'Bryonia Alba' => 22, 'Causticum' => 20,
                'Calcarea Carbonica' => 18, 'Ledum Palustre' => 16, 'Colchicum' => 14
            ],
            'joint' => [
                'Rhus Toxicodendron' => 25, 'Bryonia Alba' => 22, 'Causticum' => 18,
                'Ledum Palustre' => 16, 'Ruta Graveolens' => 14, 'Calcarea Fluorica' => 12
            ],
            'stiffness' => [
                'Rhus Toxicodendron' => 25, 'Bryonia Alba' => 20, 'Causticum' => 18,
                'Kali Carbonicum' => 15, 'Calcarea Carbonica' => 12
            ],
            'swelling' => [
                'Apis Mellifica' => 25, 'Rhus Toxicodendron' => 20, 'Bryonia Alba' => 18,
                'Ledum Palustre' => 16, 'Pulsatilla Nigricans' => 14
            ],
            
            // SKIN
            'eczema' => [
                'Sulphur' => 25, 'Graphites' => 22, 'Arsenicum Album' => 20,
                'Mezereum' => 18, 'Petroleum' => 16, 'Rhus Toxicodendron' => 14
            ],
            'psoriasis' => [
                'Sulphur' => 25, 'Arsenicum Album' => 20, 'Arsenicum Iodatum' => 18,
                'Graphites' => 16, 'Petroleum' => 14, 'Sepia' => 12
            ],
            'itching' => [
                'Sulphur' => 25, 'Arsenicum Album' => 20, 'Mezereum' => 18,
                'Rhus Toxicodendron' => 16, 'Dolichos Pruriens' => 14
            ],
            'urticaria' => [
                'Apis Mellifica' => 25, 'Urtica Urens' => 22, 'Rhus Toxicodendron' => 18,
                'Arsenicum Album' => 16, 'Natrum Muriaticum' => 14
            ],
            
            // DIABETES
            'diabetes' => [
                'Syzygium Jambolanum' => 25, 'Phosphoric Acid' => 22, 'Uranium Nitricum' => 20,
                'Arsenicum Album' => 18, 'Lycopodium Clavatum' => 16, 'Phosphorus' => 14
            ],
            'polyuria' => [
                'Phosphoric Acid' => 22, 'Syzygium Jambolanum' => 20, 'Uranium Nitricum' => 18,
                'Natrum Muriaticum' => 15, 'Lycopodium Clavatum' => 12
            ],
            'polydipsia' => [
                'Phosphorus' => 20, 'Arsenicum Album' => 18, 'Bryonia Alba' => 16,
                'Natrum Muriaticum' => 14, 'Syzygium Jambolanum' => 12
            ],
            
            // HYPERTENSION
            'hypertension' => [
                'Belladonna' => 22, 'Glonoine' => 20, 'Lachesis' => 18,
                'Natrum Muriaticum' => 16, 'Nux Vomica' => 14, 'Crataegus Oxyacantha' => 12
            ],
            'palpitation' => [
                'Digitalis' => 22, 'Spigelia Anthelmia' => 20, 'Lachesis' => 18,
                'Cactus Grandiflorus' => 16, 'Arsenicum Album' => 14, 'Phosphorus' => 12
            ],
            
            // THYROID
            'thyroid' => [
                'Thyroidinum' => 25, 'Calcarea Carbonica' => 20, 'Sepia' => 18,
                'Natrum Muriaticum' => 16, 'Bromium' => 14, 'Iodum' => 12
            ],
            'hypothyroid' => [
                'Thyroidinum' => 25, 'Calcarea Carbonica' => 22, 'Graphites' => 18,
                'Sepia' => 16, 'Baryta Carbonica' => 14
            ],
            'hyperthyroid' => [
                'Iodum' => 22, 'Natrum Muriaticum' => 20, 'Thyroidinum' => 18,
                'Lachesis' => 16, 'Phosphorus' => 14
            ],
            
            // PCOS / FEMALE
            'pcos' => [
                'Pulsatilla Nigricans' => 25, 'Sepia' => 22, 'Calcarea Carbonica' => 20,
                'Thuja Occidentalis' => 18, 'Natrum Muriaticum' => 16, 'Lachesis' => 14
            ],
            'irregular' => [
                'Pulsatilla Nigricans' => 22, 'Sepia' => 20, 'Calcarea Carbonica' => 18,
                'Natrum Muriaticum' => 16, 'Senecio Aureus' => 14
            ],
            'menses' => [
                'Pulsatilla Nigricans' => 20, 'Sepia' => 20, 'Calcarea Carbonica' => 18,
                'Lachesis' => 16, 'Cyclamen' => 14, 'Cimicifuga' => 12
            ],
            
            // ANXIETY / MENTAL
            'anxiety' => [
                'Arsenicum Album' => 25, 'Aconitum Napellus' => 22, 'Argentum Nitricum' => 20,
                'Gelsemium Sempervirens' => 18, 'Phosphorus' => 16, 'Ignatia Amara' => 14
            ],
            'depression' => [
                'Ignatia Amara' => 25, 'Natrum Muriaticum' => 22, 'Aurum Metallicum' => 20,
                'Sepia' => 18, 'Arsenicum Album' => 16, 'Phosphoric Acid' => 14
            ],
            'insomnia' => [
                'Coffea Cruda' => 25, 'Nux Vomica' => 22, 'Passiflora Incarnata' => 20,
                'Ignatia Amara' => 18, 'Arsenicum Album' => 16, 'Kali Phosphoricum' => 14
            ],
            'restless' => [
                'Arsenicum Album' => 25, 'Rhus Toxicodendron' => 22, 'Aconitum Napellus' => 18,
                'Tarentula Hispanica' => 16, 'Zincum Metallicum' => 14
            ],
            
            // DIARRHEA / CONSTIPATION
            'diarrhea' => [
                'Arsenicum Album' => 25, 'Podophyllum Peltatum' => 22, 'Veratrum Album' => 20,
                'Phosphorus' => 18, 'Aloe Socotrina' => 16, 'China Officinalis' => 14
            ],
            'constipation' => [
                'Nux Vomica' => 25, 'Bryonia Alba' => 22, 'Alumina' => 20,
                'Opium' => 18, 'Silicea' => 16, 'Plumbum Metallicum' => 14
            ],
            
            // COMMON PHYSICAL
            'fatigue' => [
                'Arsenicum Album' => 20, 'Phosphoric Acid' => 18, 'China Officinalis' => 16,
                'Kali Phosphoricum' => 14, 'Gelsemium Sempervirens' => 12
            ],
            'weakness' => [
                'Arsenicum Album' => 20, 'China Officinalis' => 18, 'Phosphoric Acid' => 16,
                'Carbo Vegetabilis' => 14, 'Gelsemium Sempervirens' => 12
            ],
        ];
        
        // Build search terms
        $searchTerms = [];
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'to', 'of', 'in', 
            'on', 'for', 'with', 'and', 'or', 'but', 'not', 'be', 'have', 'has', 'from',
            'worse', 'better', 'since', 'after', 'before', 'during', 'about', 'feeling'];
        
        foreach ($symptoms as $symptom) {
            $symptomText = $symptom['symptom'] ?? $symptom['symptom_text'] ?? '';
            $words = preg_split('/[\s,\-\.\/]+/', strtolower($symptomText));
            foreach ($words as $word) {
                $word = trim($word);
                if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                    $searchTerms[] = $word;
                }
            }
        }
        
        // Add chief complaint words
        $complaintWords = preg_split('/[\s,\-\.\/]+/', $chiefComplaint);
        foreach ($complaintWords as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $searchTerms[] = $word;
            }
        }
        
        // Add medical phrases
        $searchTerms = array_merge($searchTerms, extractMedicalPhrases($chiefComplaint));
        $searchTerms = array_unique(array_filter($searchTerms));
        
        // Initialize remedy scores
        $remedyScores = [];
        $remedyData = [];
        
        // ========================================
        // STEP 1: Apply Priority Clinical Mappings 
        // ========================================
        foreach ($searchTerms as $term) {
            $termLower = strtolower($term);
            if (isset($priorityRemedies[$termLower])) {
                foreach ($priorityRemedies[$termLower] as $remedyName => $bonus) {
                    $key = strtolower($remedyName);
                    if (!isset($remedyScores[$key])) {
                        $remedyScores[$key] = 0;
                        $remedyData[$key] = ['remedy_name' => $remedyName];
                    }
                    $remedyScores[$key] += $bonus;
                }
            }
        }
        
        // ========================================
        // STEP 2: Search Repertory (High Grade = High Weight)
        // ========================================
        foreach ($symptoms as $symptom) {
            $symptomText = strtolower($symptom['symptom'] ?? $symptom['symptom_text'] ?? '');
            if (empty($symptomText) || strlen($symptomText) < 3) continue;
            
            $repertoryMatches = DB::query(
                "SELECT rr.remedy_id, rr.grade, rem.remedy_name, rem.common_name,
                        rem.keynote_symptoms, rem.clinical_indications
                 FROM repertory r
                 INNER JOIN repertory_remedies rr ON r.id = rr.repertory_id
                 INNER JOIN remedies rem ON rr.remedy_id = rem.id
                 WHERE LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?
                 ORDER BY rr.grade DESC
                 LIMIT 25",
                ['%' . $symptomText . '%', '%' . $symptomText . '%']
            );
            
            foreach ($repertoryMatches as $match) {
                $key = strtolower($match['remedy_name']);
                if (!isset($remedyScores[$key])) {
                    $remedyScores[$key] = 0;
                    $remedyData[$key] = $match;
                }
                // Grade 4 = 16 points, Grade 3 = 9 points, Grade 2 = 4 points, Grade 1 = 1 point
                $remedyScores[$key] += pow($match['grade'], 2);
            }
        }
        
        // ========================================
        // STEP 3: Search Materia Medica (keynotes and clinical)
        // ========================================
        foreach ($searchTerms as $term) {
            if (strlen($term) < 4) continue;
            
            $termPattern = '%' . $term . '%';
            $matches = DB::query(
                "SELECT MIN(id) as id, remedy_name, 
                        MAX(common_name) as common_name,
                        GROUP_CONCAT(DISTINCT keynote_symptoms SEPARATOR ' | ') as keynote_symptoms,
                        GROUP_CONCAT(DISTINCT clinical_indications SEPARATOR ' | ') as clinical_indications
                 FROM remedies 
                 WHERE LOWER(keynote_symptoms) LIKE ? 
                    OR LOWER(clinical_indications) LIKE ?
                 GROUP BY remedy_name
                 LIMIT 20",
                [$termPattern, $termPattern]
            );
            
            foreach ($matches as $remedy) {
                $key = strtolower($remedy['remedy_name']);
                
                // Skip generic acids and chemicals that often match text but aren't clinical
                if (preg_match('/^(acidum|acid|acet|butyr|carbol|citr|form|lacti|nitr|oxal|picr|salicyl)/i', $remedy['remedy_name'])) {
                    // Only allow if we already have priority score for this remedy
                    if (!isset($remedyScores[$key]) || $remedyScores[$key] < 10) {
                        continue;
                    }
                }
                
                if (!isset($remedyScores[$key])) {
                    $remedyScores[$key] = 0;
                }
                if (!isset($remedyData[$key]) || empty($remedyData[$key]['keynote_symptoms'])) {
                    $remedyData[$key] = $remedy;
                }
                
                $score = 0;
                // Keynote match = high value (5 points)
                if (stripos($remedy['keynote_symptoms'] ?? '', $term) !== false) $score += 5;
                // Clinical indication match = medium value (3 points)
                if (stripos($remedy['clinical_indications'] ?? '', $term) !== false) $score += 3;
                
                $remedyScores[$key] += $score;
            }
        }
        
        // ========================================
        // STEP 4: Apply Filters and Adjustments
        // ========================================
        
        // Filter out very low scores
        $remedyScores = array_filter($remedyScores, fn($score) => $score >= 5);
        
        // Sort by score
        arsort($remedyScores);
        
        if (empty($remedyScores)) {
            return [
                'remedies' => [],
                'case_analysis' => 'No matching remedies found. Try adding more specific symptoms.',
                'cautions' => 'Consider consulting standard materia medica.',
                'total_remedies_searched' => 0,
                'search_terms' => $searchTerms
            ];
        }
        
        // ========================================
        // BUILD RESULTS
        // ========================================
        $topRemedies = [];
        $maxScore = max($remedyScores);
        $count = 0;
        
        foreach ($remedyScores as $key => $score) {
            if ($count >= 5) break;
            
            $data = $remedyData[$key] ?? [];
            
            // Calculate match percentage with good distribution
            $basePercent = ($score / $maxScore) * 100;
            $matchPercent = round(max(60, min(98, $basePercent - ($count * 3))));
            
            // Determine potency based on symptom intensity
            $potency = '30C';
            $maxIntensity = 0;
            foreach ($symptoms as $s) {
                $intensity = $s['intensity'] ?? $s['severity'] ?? 5;
                if (is_string($intensity)) {
                    $intensity = $intensity === 'severe' ? 8 : ($intensity === 'moderate' ? 5 : 3);
                }
                $maxIntensity = max($maxIntensity, (int)$intensity);
            }
            if ($maxIntensity >= 8) $potency = '200C';
            elseif ($maxIntensity >= 6) $potency = '30C or 200C';
            
            // Build reasoning
            $reasoning = '';
            if (!empty($data['keynote_symptoms'])) {
                $reasoning = substr($data['keynote_symptoms'], 0, 200);
            } elseif (!empty($data['clinical_indications'])) {
                $reasoning = substr($data['clinical_indications'], 0, 200);
            }
            
            $topRemedies[] = [
                'name' => $data['remedy_name'] ?? ucwords(str_replace('_', ' ', $key)),
                'common_name' => $data['common_name'] ?? '',
                'match_percentage' => $matchPercent,
                'potency' => $potency,
                'score' => $score,
                'reasoning' => $reasoning ?: 'Indicated based on symptom totality and repertorial analysis.'
            ];
            $count++;
        }
        
        return [
            'remedies' => $topRemedies,
            'case_analysis' => 'Analysis based on ' . count($searchTerms) . ' clinical terms from ' . count($symptoms) . ' symptoms. Priority clinical mappings applied.',
            'cautions' => 'AI-assisted suggestion. Always verify with standard homeopathic texts and consider the totality of symptoms.',
            'total_remedies_searched' => count($remedyScores),
            'search_terms' => array_slice($searchTerms, 0, 15),
            'method' => 'clinical_priority_rag'
        ];
    }
}
