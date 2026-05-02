<?php
/**
 * Vector-based RAG (Retrieval-Augmented Generation) System
 * 
 * This replaces the traditional keyword-based search with semantic vector search
 * for finding relevant remedies, repertory rubrics, and diseases.
 */

require_once __DIR__ . '/embeddings.php';

class VectorRAG {
    private $embeddingsService;
    private $vectorStore;
    private $useHybridSearch = true; // Combine vector + keyword search
    
    // Polychrest remedies - well-proven, commonly used remedies that should be prioritized
    // These are the "big guns" of homeopathy with extensive clinical verification
    private $polychrests = [
        // Top-tier polychrests (highest boost)
        'aconitum napellus' => 3.0, 'arsenicum album' => 3.0, 'belladonna' => 3.0,
        'bryonia alba' => 3.0, 'calcarea carbonica' => 3.0, 'carbo vegetabilis' => 3.0,
        'chamomilla' => 3.0, 'china officinalis' => 3.0, 'gelsemium sempervirens' => 3.0,
        'ignatia amara' => 3.0, 'lachesis muta' => 3.0, 'lycopodium clavatum' => 3.0,
        'mercurius solubilis' => 3.0, 'natrum muriaticum' => 3.0, 'nux vomica' => 3.0,
        'phosphorus' => 3.0, 'pulsatilla nigricans' => 3.0, 'rhus toxicodendron' => 3.0,
        'sepia' => 3.0, 'silicea' => 3.0, 'sulphur' => 3.0, 'thuja occidentalis' => 3.0,
        
        // Second-tier polychrests (good boost)
        'antimonium tartaricum' => 2.5, 'apis mellifica' => 2.5, 'argentum nitricum' => 2.5,
        'arnica montana' => 2.5, 'aurum metallicum' => 2.5, 'baryta carbonica' => 2.5,
        'calcarea phosphorica' => 2.5, 'causticum' => 2.5, 'cocculus indicus' => 2.5,
        'colocynthis' => 2.5, 'conium maculatum' => 2.5, 'cuprum metallicum' => 2.5,
        'digitalis purpurea' => 2.5, 'drosera rotundifolia' => 2.5, 'dulcamara' => 2.5,
        'ferrum metallicum' => 2.5, 'graphites' => 2.5, 'hepar sulphuris' => 2.5,
        'hyoscyamus niger' => 2.5, 'ipecacuanha' => 2.5, 'kali bichromicum' => 2.5,
        'kali carbonicum' => 2.5, 'ledum palustre' => 2.5, 'magnesium phosphoricum' => 2.5,
        'natrum carbonicum' => 2.5, 'nitric acid' => 2.5, 'opium' => 2.5,
        'petroleum' => 2.5, 'plumbum metallicum' => 2.5, 'podophyllum' => 2.5,
        'psorinum' => 2.5, 'ruta graveolens' => 2.5, 'sanguinaria canadensis' => 2.5,
        'spigelia anthelmia' => 2.5, 'spongia tosta' => 2.5, 'staphysagria' => 2.5,
        'stramonium' => 2.5, 'tuberculinum' => 2.5, 'veratrum album' => 2.5, 'zincum metallicum' => 2.5,
        
        // Third-tier - commonly used remedies (moderate boost)
        'aethusa cynapium' => 2.0, 'allium cepa' => 2.0, 'alumina' => 2.0,
        'ammonium carbonicum' => 2.0, 'anacardium orientale' => 2.0, 'antimonium crudum' => 2.0,
        'borax' => 2.0, 'bromium' => 2.0, 'cactus grandiflorus' => 2.0,
        'calendula officinalis' => 2.0, 'cantharis' => 2.0, 'carbo animalis' => 2.0,
        'caulophyllum' => 2.0, 'chelidonium majus' => 2.0, 'cicuta virosa' => 2.0,
        'cina' => 2.0, 'clematis erecta' => 2.0, 'coffea cruda' => 2.0,
        'colchicum autumnale' => 2.0, 'crotalus horridus' => 2.0, 'eupatorium perfoliatum' => 2.0,
        'euphrasia officinalis' => 2.0, 'ferrum phosphoricum' => 2.0, 'glonoinum' => 2.0,
        'hamamelis virginiana' => 2.0, 'helleborus niger' => 2.0, 'hypericum perforatum' => 2.0,
        'iodum' => 2.0, 'iris versicolor' => 2.0, 'kreosotum' => 2.0,
        'lac caninum' => 2.0, 'laurocerasus' => 2.0, 'lilium tigrinum' => 2.0,
        'lobelia inflata' => 2.0, 'magnesia muriatica' => 2.0, 'medorrhinum' => 2.0,
        'mezereum' => 2.0, 'moschus' => 2.0, 'murex purpurea' => 2.0,
        'mygale lasiodora' => 2.0, 'naja tripudians' => 2.0, 'natrum phosphoricum' => 2.0,
        'natrum sulphuricum' => 2.0, 'oleander' => 2.0, 'palladium' => 2.0,
        'phosphoric acid' => 2.0, 'phytolacca decandra' => 2.0, 'platina' => 2.0,
        'ranunculus bulbosus' => 2.0, 'sabadilla' => 2.0, 'sabina' => 2.0,
        'sambucus nigra' => 2.0, 'secale cornutum' => 2.0, 'selenium' => 2.0,
        'senecio aureus' => 2.0, 'senega' => 2.0, 'stannum metallicum' => 2.0,
        'sticta pulmonaria' => 2.0, 'sulphuric acid' => 2.0, 'symphytum officinale' => 2.0,
        'tabacum' => 2.0, 'taraxacum' => 2.0, 'terebinthina' => 2.0,
        'theridion curassavicum' => 2.0, 'trillium pendulum' => 2.0, 'urtica urens' => 2.0,
        'ustilago maydis' => 2.0, 'valeriana officinalis' => 2.0, 'viburnum opulus' => 2.0,
    ];
    
    // Remedies that should be deprioritized (obscure, unproven, or rarely used)
    // These require VERY specific confirming symptoms before prescribing
    private $obscureRemedies = [
        // Not really homeopathic remedies
        'acidum acetylsalicylicum' => 0.3, // Aspirin
        
        // Syphilitic/deep-acting remedies requiring specific pathology
        'aurum bromatum' => 0.4,           // Needs syphilitic background
        'aurum arsenicum' => 0.4,          // Deep destructive pathology only
        'aurum iodatum' => 0.5,            // Requires glandular pathology
        'aurum muriaticum' => 0.5,         // Specific syphilitic indications
        'aurum muriaticum natronatum' => 0.4,
        'mercurius corrosivus' => 0.5,     // Severe ulcerative conditions only
        'mercurius cyanatus' => 0.4,       // Diphtheria/gangrenous conditions
        'syphilinum' => 0.4,               // Syphilitic miasm required
        
        // Acids requiring specific metabolic/debility symptoms
        'acidum butyricum' => 0.4,         // Very specific GI symptoms
        'acidum lacticum' => 0.5,
        'acidum oxalicum' => 0.5,
        'acidum picricum' => 0.5,          // Severe mental/sexual exhaustion
        'ammonium picricum' => 0.4,        // Rare, specific neuralgia
        
        // Chemical/industrial substances requiring specific exposure
        'anilinum' => 0.3,                 // Industrial poisoning only
        'benzolum' => 0.3,
        'carboneum sulphuratum' => 0.4,
        
        // Obscure remedies
        'echinops strigosus' => 0.4,
        'eupion' => 0.4,
        'lolium temulentum' => 0.4,
        'sium latifolium' => 0.4,
        'convolvulus arvensis' => 0.4,
        'oreodaphne californica' => 0.4,
        
        // Snake venoms requiring specific symptoms
        'elaps corallinus' => 0.5,         // Specific hemorrhagic symptoms
        'bothrops lanceolatus' => 0.4,     // Thrombosis/hemorrhage
        'vipera berus' => 0.5,             // Venous conditions specific
    ];
    
    // Age-specific remedies that should be penalized if patient age doesn't match
    // These remedies have SPECIFIC age indications
    private $ageSpecificRemedies = [
        // Baryta - specifically for ELDERLY (arteriosclerosis) or CHILDREN (delayed development)
        'baryta carbonica' => ['min_age' => 60, 'max_age' => 12, 'penalty' => 0.2],
        'baryta acetica' => ['min_age' => 60, 'max_age' => 12, 'penalty' => 0.25],
        'baryta iodata' => ['min_age' => 60, 'max_age' => 12, 'penalty' => 0.3],
        'baryta muriatica' => ['min_age' => 60, 'max_age' => 12, 'penalty' => 0.25],
        
        // Calcarea carb - more indicated in children/young adults
        'calcarea carbonica' => ['max_age' => 10, 'penalty' => 0.7], // Mild penalty for adults
        
        // Cina - mainly for children with worm symptoms
        'cina' => ['max_age' => 14, 'penalty' => 0.4],
        
        // Chamomilla - mainly for infants/children
        'chamomilla' => ['max_age' => 10, 'penalty' => 0.6],
    ];
    
    public function __construct() {
        $this->embeddingsService = new EmbeddingsService();
        $this->vectorStore = new VectorStore();
    }
    
    /**
     * Get polychrest boost factor for a remedy
     */
    private function getPolychrestBoost($remedyName) {
        $normalizedName = strtolower(trim($remedyName));
        
        // Check for exact match first
        if (isset($this->polychrests[$normalizedName])) {
            return $this->polychrests[$normalizedName];
        }
        
        // Check if it's an obscure remedy to deprioritize
        if (isset($this->obscureRemedies[$normalizedName])) {
            return $this->obscureRemedies[$normalizedName];
        }
        
        // Check for partial matches (handle variations in naming)
        foreach ($this->polychrests as $polychrest => $boost) {
            // Check if the polychrest name is contained in the remedy name or vice versa
            if (strpos($normalizedName, $polychrest) !== false || 
                strpos($polychrest, $normalizedName) !== false) {
                return $boost;
            }
            
            // Check first word match (e.g., "belladonna" matches "belladonna 30c")
            $polychrestFirst = explode(' ', $polychrest)[0];
            $remedyFirst = explode(' ', $normalizedName)[0];
            if ($polychrestFirst === $remedyFirst && strlen($polychrestFirst) > 3) {
                return $boost;
            }
        }
        
        // Default: no boost (neutral)
        return 1.0;
    }
    
    /**
     * Get age-specific penalty for a remedy
     * Some remedies are specifically indicated for certain age groups
     * Baryta = elderly/children, Cina = children, etc.
     * 
     * @param string $remedyName The remedy name
     * @param int|null $patientAge Patient's age
     * @return float Multiplier (1.0 = no penalty, lower = penalized)
     */
    private function getAgeSpecificPenalty($remedyName, $patientAge) {
        if ($patientAge === null) {
            return 1.0; // No penalty if age unknown
        }
        
        $normalizedName = strtolower(trim($remedyName));
        
        // Check for partial matches (baryta carbonica, baryta carb, etc.)
        foreach ($this->ageSpecificRemedies as $remedy => $ageData) {
            if (strpos($normalizedName, explode(' ', $remedy)[0]) === 0) {
                // Found a match - check age appropriateness
                $minAge = $ageData['min_age'] ?? 0;
                $maxAge = $ageData['max_age'] ?? 150;
                $penalty = $ageData['penalty'] ?? 0.5;
                
                // Baryta-type remedies: indicated for elderly (>60) OR children (<12)
                // Middle-aged adults should be penalized
                if (isset($ageData['min_age']) && isset($ageData['max_age'])) {
                    // Remedy for extremes of age (like Baryta)
                    if ($patientAge > $maxAge && $patientAge < $minAge) {
                        // Patient is in middle age - heavy penalty
                        return $penalty;
                    }
                } elseif (isset($ageData['max_age'])) {
                    // Remedy mainly for children
                    if ($patientAge > $maxAge) {
                        return $penalty;
                    }
                } elseif (isset($ageData['min_age'])) {
                    // Remedy mainly for elderly
                    if ($patientAge < $minAge) {
                        return $penalty;
                    }
                }
                
                break;
            }
        }
        
        return 1.0; // No penalty
    }
    
    /**
     * Calculate symptom adequacy score (0.0 to 1.0)
     * Determines how confident we can be based on available case data
     * 
     * HOMEOPATHIC PRINCIPLES:
     * - Minimum 3 well-defined symptoms for confident prescription
     * - Modalities (better/worse) greatly increase confidence
     * - Mental symptoms weighted heavily
     * - Constitutional picture matters
     */
    private function calculateSymptomAdequacy($symptoms, $consultation) {
        $adequacy = 0.0;
        
        // Count of meaningful symptoms
        $symptomCount = is_array($symptoms) ? count($symptoms) : 0;
        
        // Check for mental/emotional symptoms
        $hasMentalSymptoms = false;
        $mentalKeywords = ['mood', 'anxiety', 'fear', 'anger', 'irritable', 'sad', 'weeping', 'depression', 'restless', 'jealousy', 'grief', 'impatient'];
        
        $allText = strtolower(
            ($consultation['chief_complaint'] ?? '') . ' ' .
            ($consultation['mental_symptoms'] ?? '') . ' ' .
            ($consultation['general_symptoms'] ?? '') . ' ' .
            ($consultation['particular_symptoms'] ?? '')
        );
        
        foreach ($mentalKeywords as $keyword) {
            if (strpos($allText, $keyword) !== false) {
                $hasMentalSymptoms = true;
                break;
            }
        }
        
        // Check for modalities (better/worse conditions)
        $hasModalities = false;
        $modalityKeywords = ['better', 'worse', 'aggravation', 'amelioration', 'morning', 'evening', 'night', 'cold', 'warm', 'motion', 'rest', 'eating', 'pressure', '11 am', '11am'];
        
        foreach ($modalityKeywords as $keyword) {
            if (strpos($allText, $keyword) !== false) {
                $hasModalities = true;
                break;
            }
        }
        
        // Check for causation
        $hasCausation = !empty($consultation['causation']);
        
        // Check for constitutional data
        $hasConstitutional = !empty($consultation['thermal_state']) || 
                            !empty($consultation['thirst']) || 
                            !empty($consultation['appetite']);
        
        // Check for physical generals
        $hasPhysicalGenerals = !empty($consultation['general_symptoms']);
        
        // SCORING:
        // Symptom count: 0-3 symptoms = 0.1-0.4, 4+ symptoms = 0.5-0.7
        if ($symptomCount >= 5) {
            $adequacy += 0.4;
        } elseif ($symptomCount >= 3) {
            $adequacy += 0.3;
        } elseif ($symptomCount >= 2) {
            $adequacy += 0.2;
        } else {
            $adequacy += 0.1;  // 0-1 symptoms: very low baseline
        }
        
        // Mental symptoms: +0.15
        if ($hasMentalSymptoms) {
            $adequacy += 0.15;
        }
        
        // Modalities: +0.15 (very important for differentiation)
        if ($hasModalities) {
            $adequacy += 0.15;
        }
        
        // Causation: +0.1
        if ($hasCausation) {
            $adequacy += 0.1;
        }
        
        // Constitutional data: +0.1
        if ($hasConstitutional) {
            $adequacy += 0.1;
        }
        
        // Physical generals: +0.1
        if ($hasPhysicalGenerals) {
            $adequacy += 0.1;
        }
        
        // Cap at 1.0
        return min($adequacy, 1.0);
    }
    
    /**
     * Search for remedies using vector similarity
     * 
     * @param string $query The search query (symptoms, conditions, etc.)
     * @param int $topK Number of results to return
     * @param float $minSimilarity Minimum similarity threshold (0-1)
     * @return array Array of matching remedies with scores
     */
    public function searchRemedies($query, $topK = 10, $minSimilarity = 0.5) {
        try {
            // Generate query embedding
            $queryEmbedding = $this->embeddingsService->generateQueryEmbedding($query);
            
            if (empty($queryEmbedding)) {
                throw new Exception("Failed to generate query embedding");
            }
            
            // Search vector store
            $vectorResults = $this->vectorStore->search('remedy', $queryEmbedding, $topK * 2, $minSimilarity);
            
            if (empty($vectorResults)) {
                return ['remedies' => [], 'method' => 'vector', 'query' => $query];
            }
            
            // Get full remedy details
            $remedyIds = array_column($vectorResults, 'entity_id');
            $placeholders = implode(',', array_fill(0, count($remedyIds), '?'));
            
            $remedies = DB::query(
                "SELECT * FROM remedies WHERE id IN ($placeholders)",
                $remedyIds
            );
            
            // Map remedies by ID for quick lookup
            $remedyMap = [];
            foreach ($remedies as $remedy) {
                $remedyMap[$remedy['id']] = $remedy;
            }
            
            // Build results with similarity scores
            $results = [];
            foreach ($vectorResults as $result) {
                $remedyId = $result['entity_id'];
                if (isset($remedyMap[$remedyId])) {
                    $remedy = $remedyMap[$remedyId];
                    $results[] = [
                        'id' => $remedy['id'],
                        'remedy_name' => $remedy['remedy_name'],
                        'common_name' => $remedy['common_name'] ?? '',
                        'similarity' => round($result['similarity'], 4),
                        'match_percentage' => round($result['similarity'] * 100, 1),
                        'keynote_symptoms' => $remedy['keynote_symptoms'] ?? '',
                        'clinical_indications' => $remedy['clinical_indications'] ?? '',
                        'book_reference' => $remedy['book_reference'] ?? ''
                    ];
                }
            }
            
            // Sort by similarity and take top K
            usort($results, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            return [
                'remedies' => array_slice($results, 0, $topK),
                'method' => 'vector',
                'query' => $query,
                'total_searched' => count($vectorResults)
            ];
            
        } catch (Exception $e) {
            error_log("VectorRAG searchRemedies error: " . $e->getMessage());
            return [
                'remedies' => [],
                'method' => 'vector',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Search repertory rubrics using vector similarity
     */
    public function searchRepertory($query, $topK = 10, $minSimilarity = 0.5) {
        try {
            $queryEmbedding = $this->embeddingsService->generateQueryEmbedding($query);
            
            if (empty($queryEmbedding)) {
                throw new Exception("Failed to generate query embedding");
            }
            
            $vectorResults = $this->vectorStore->search('repertory', $queryEmbedding, $topK * 2, $minSimilarity);
            
            if (empty($vectorResults)) {
                return ['rubrics' => [], 'method' => 'vector'];
            }
            
            $rubricIds = array_column($vectorResults, 'entity_id');
            $placeholders = implode(',', array_fill(0, count($rubricIds), '?'));
            
            // Get rubrics with their associated remedies
            $rubrics = DB::query(
                "SELECT r.*, 
                        GROUP_CONCAT(DISTINCT rem.remedy_name ORDER BY rr.grade DESC SEPARATOR ', ') as remedies,
                        GROUP_CONCAT(DISTINCT CONCAT(rem.remedy_name, ':', rr.grade) SEPARATOR '; ') as remedy_grades
                 FROM repertory r
                 LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id
                 LEFT JOIN remedies rem ON rr.remedy_id = rem.id
                 WHERE r.id IN ($placeholders)
                 GROUP BY r.id",
                $rubricIds
            );
            
            $rubricMap = [];
            foreach ($rubrics as $rubric) {
                $rubricMap[$rubric['id']] = $rubric;
            }
            
            $results = [];
            foreach ($vectorResults as $result) {
                $rubricId = $result['entity_id'];
                if (isset($rubricMap[$rubricId])) {
                    $rubric = $rubricMap[$rubricId];
                    $results[] = [
                        'id' => $rubric['id'],
                        'rubric' => $rubric['rubric'],
                        'complete_rubric' => $rubric['complete_rubric'] ?? '',
                        'category' => $rubric['category'],
                        'similarity' => round($result['similarity'], 4),
                        'match_percentage' => round($result['similarity'] * 100, 1),
                        'remedies' => $rubric['remedies'] ?? '',
                        'remedy_grades' => $rubric['remedy_grades'] ?? ''
                    ];
                }
            }
            
            usort($results, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            return [
                'rubrics' => array_slice($results, 0, $topK),
                'method' => 'vector',
                'query' => $query
            ];
            
        } catch (Exception $e) {
            error_log("VectorRAG searchRepertory error: " . $e->getMessage());
            return ['rubrics' => [], 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Search diseases using vector similarity
     */
    public function searchDiseases($query, $topK = 10, $minSimilarity = 0.5) {
        try {
            $queryEmbedding = $this->embeddingsService->generateQueryEmbedding($query);
            
            if (empty($queryEmbedding)) {
                throw new Exception("Failed to generate query embedding");
            }
            
            $vectorResults = $this->vectorStore->search('disease', $queryEmbedding, $topK * 2, $minSimilarity);
            
            if (empty($vectorResults)) {
                return ['diseases' => [], 'method' => 'vector'];
            }
            
            $diseaseIds = array_column($vectorResults, 'entity_id');
            $placeholders = implode(',', array_fill(0, count($diseaseIds), '?'));
            
            $diseases = DB::query(
                "SELECT * FROM diseases WHERE id IN ($placeholders)",
                $diseaseIds
            );
            
            $diseaseMap = [];
            foreach ($diseases as $disease) {
                $diseaseMap[$disease['id']] = $disease;
            }
            
            $results = [];
            foreach ($vectorResults as $result) {
                $diseaseId = $result['entity_id'];
                if (isset($diseaseMap[$diseaseId])) {
                    $disease = $diseaseMap[$diseaseId];
                    $results[] = [
                        'id' => $disease['id'],
                        'disease_name' => $disease['disease_name'] ?? '',
                        'description' => $disease['description'] ?? '',
                        'similarity' => round($result['similarity'], 4),
                        'match_percentage' => round($result['similarity'] * 100, 1)
                    ];
                }
            }
            
            usort($results, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            return [
                'diseases' => array_slice($results, 0, $topK),
                'method' => 'vector',
                'query' => $query
            ];
            
        } catch (Exception $e) {
            error_log("VectorRAG searchDiseases error: " . $e->getMessage());
            return ['diseases' => [], 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate RAG-based remedy suggestions for a consultation
     * This is the main method used by the AI suggestion system
     * 
     * @param array $consultation Consultation data including symptoms
     * @return array Remedy suggestions with explanations
     */
    public function generateSuggestions($consultation) {
        $symptoms = $consultation['symptoms'] ?? [];
        $chiefComplaint = $consultation['chief_complaint'] ?? '';
        $diagnosis = $consultation['diagnosis'] ?? '';
        $patientAge = $consultation['age'] ?? null;
        
        // Build comprehensive query from consultation data
        $queryParts = [];
        
        // Add chief complaint with emphasis
        if (!empty($chiefComplaint)) {
            $queryParts[] = "Chief complaint: " . $chiefComplaint;
        }
        
        // Add diagnosis - crucial for follow-up consultations
        // This often contains the specific condition being treated
        if (!empty($diagnosis)) {
            $queryParts[] = "Diagnosis: " . $diagnosis;
        }
        
        // Add general symptoms (free text field - very important!)
        if (!empty($consultation['general_symptoms'])) {
            $queryParts[] = "General symptoms: " . $consultation['general_symptoms'];
        }
        
        // Add particular symptoms (free text field - very important!)
        if (!empty($consultation['particular_symptoms'])) {
            $queryParts[] = "Particular symptoms: " . $consultation['particular_symptoms'];
        }
        
        // Add symptoms with their attributes (from symptoms table)
        foreach ($symptoms as $symptom) {
            $symptomText = $symptom['symptom'] ?? $symptom['symptom_text'] ?? '';
            if (!empty($symptomText)) {
                $parts = [$symptomText];
                if (!empty($symptom['location'])) $parts[] = "in " . $symptom['location'];
                if (!empty($symptom['sensation'])) $parts[] = "sensation: " . $symptom['sensation'];
                if (!empty($symptom['modality'])) $parts[] = "modality: " . $symptom['modality'];
                if (!empty($symptom['severity']) || !empty($symptom['intensity'])) {
                    $severity = $symptom['severity'] ?? $symptom['intensity'];
                    $parts[] = "intensity: " . $severity;
                }
                $queryParts[] = implode(', ', $parts);
            }
        }
        
        // Add mental/emotional state if available
        if (!empty($consultation['mental_state'])) {
            $queryParts[] = "Mental state: " . $consultation['mental_state'];
        }
        
        // Add modalities from consultation
        if (!empty($consultation['thermal_state'])) {
            $queryParts[] = "Thermal state: " . $consultation['thermal_state'];
        }
        if (!empty($consultation['thirst'])) {
            $queryParts[] = "Thirst: " . $consultation['thirst'];
        }
        
        // Add consultation-level modalities
        if (!empty($consultation['aggravation'])) {
            $queryParts[] = "Aggravation: " . $consultation['aggravation'];
        }
        if (!empty($consultation['amelioration'])) {
            $queryParts[] = "Amelioration: " . $consultation['amelioration'];
        }
        
        $searchQuery = implode("\n", $queryParts);
        
        if (empty(trim($searchQuery))) {
            return [
                'error' => 'No symptoms to analyze',
                'remedies' => []
            ];
        }
        
        // Search for matching remedies
        $remedyResults = $this->searchRemedies($searchQuery, 10, 0.45);
        
        if (empty($remedyResults['remedies'])) {
            // Fallback: try with just chief complaint
            $remedyResults = $this->searchRemedies($chiefComplaint, 10, 0.4);
        }
        
        // Also search repertory for additional matches
        $repertoryResults = $this->searchRepertory($searchQuery, 20, 0.4);
        
        // HYBRID SEARCH: Also do keyword-based search for specific symptom combinations
        // This catches specific symptoms that embeddings might miss
        $keywordMatches = $this->hybridKeywordSearch($chiefComplaint, $symptoms, $consultation);
        
        // Combine and score remedies from all sources
        $combinedRemedies = $this->combineResults(
            $remedyResults['remedies'], 
            $repertoryResults['rubrics'], 
            $symptoms,
            $keywordMatches,
            $consultation  // Pass consultation for context-aware filtering
        );
        
        // Determine potency once for the case (same for all remedies)
        $potencyRecommendation = $this->determinePotency($symptoms, $patientAge, $consultation);
        
        // Build final suggestions
        $suggestions = [];
        $count = 0;
        
        foreach ($combinedRemedies as $remedy) {
            if ($count >= 5) break;
            
            // Fetch complete remedy data if missing
            if (empty($remedy['keynote_symptoms']) || empty($remedy['book_reference']) || empty($remedy['common_name'])) {
                $fullData = $this->fetchRemedyByName($remedy['remedy_name']);
                if (!empty($fullData)) {
                    $remedy['common_name'] = $remedy['common_name'] ?: ($fullData['common_name'] ?? '');
                    $remedy['keynote_symptoms'] = $remedy['keynote_symptoms'] ?: ($fullData['keynote_symptoms'] ?? '');
                    $remedy['book_reference'] = $remedy['book_reference'] ?: ($fullData['book_reference'] ?? '');
                    $remedy['id'] = $remedy['id'] ?: ($fullData['id'] ?? 0);
                }
            }
            
            $suggestions[] = [
                'name' => $remedy['remedy_name'],
                'common_name' => $remedy['common_name'] ?? '',
                'match_percentage' => min(95, $remedy['combined_score']),
                'potency' => is_array($potencyRecommendation) ? $potencyRecommendation['potency'] : $potencyRecommendation,
                'potency_details' => is_array($potencyRecommendation) ? $potencyRecommendation : ['potency' => $potencyRecommendation],
                'score' => $remedy['raw_score'],
                'reasoning' => $this->buildReasoning($remedy, $symptoms),
                'reference' => $remedy['book_reference'] ?: "Boericke's Materia Medica",
                'matched_terms' => $remedy['matched_terms'] ?? [],
                'matched_fields' => ['vector_similarity'],
                'repertory_rubrics' => $remedy['matching_rubrics'] ?? [],
                'keynote_excerpt' => $this->getKeynoteExcerpt($remedy)
            ];
            
            $count++;
        }
        
        return [
            'remedies' => $suggestions,
            'case_analysis' => $this->buildCaseAnalysis($suggestions, $symptoms, $chiefComplaint),
            'potency_recommendation' => $potencyRecommendation,
            'total_remedies_searched' => $remedyResults['total_searched'] ?? count($combinedRemedies),
            'search_method' => 'vector_embeddings',
            'search_query' => $searchQuery,
            'cautions' => $this->generateCautions($suggestions)
        ];
    }
    
    /**
     * Combine remedy results from direct search and repertory search
     * 
     * HOMEOPATHIC HIERARCHY SCORING:
     * Unlike simple semantic matching, homeopathic prescribing follows a hierarchy:
     * 
     * 1. PQRS (Peculiar, Queer, Rare, Strange) - Weight: 4x
     *    These are the most individualizing symptoms that point to specific remedies
     * 
     * 2. Mental/Emotional Symptoms - Weight: 3x
     *    "The mind leads the body" - mental symptoms outrank physical
     * 
     * 3. General Symptoms (affect whole person) - Weight: 2x
     *    Thermal state, thirst, food desires/aversions, sleep position
     * 
     * 4. Particular Symptoms (local) - Weight: 1x
     *    Location-specific physical symptoms
     * 
     * 5. Modalities (what makes better/worse) - Weight: 2x
     *    These differentiate between similar remedies
     * 
     * TOTALITY SCORING:
     * A remedy covering 5 rubrics with moderate grades often beats
     * a remedy covering 1 rubric with high grade (breadth over depth)
     * 
     * ELIMINATION:
     * If a remedy is absent from a strongly marked rubric, it should
     * be eliminated or heavily penalized
     */
    private function combineResults($directRemedies, $repertoryRubrics, $symptoms, $keywordMatches = [], $consultation = []) {
        $combinedScores = [];
        
        // Determine if this is an adult allergic rhinitis case (context-aware filtering)
        $isAdultAllergyCase = $this->isAdultAllergyCase($consultation);
        
        // CONTEXT-AWARE HIERARCHY ADJUSTMENT
        // For physical pathology cases (sinusitis, bronchitis, etc.), particular symptoms matter MORE
        // For mental/emotional cases, mental symptoms matter MORE
        $caseType = $this->determineCaseType($consultation);
        $hierarchyAdjustment = $this->getHierarchyAdjustment($caseType);
        
        // Detect secondary mental symptoms (e.g., "irritable FROM discomfort")
        $secondaryMentalSymptoms = $this->detectSecondaryMentalSymptoms($consultation);
        
        // STEP 1: Classify rubrics by homeopathic hierarchy
        $classifiedRubrics = $this->classifyRubricsByHierarchy($repertoryRubrics, $symptoms, $consultation);
        
        // STEP 1.5: Apply discharge character matching for respiratory cases
        // Thick yellow/green discharge is a KEY differentiating symptom
        $dischargeMatches = $this->matchDischargeCharacter($consultation);
        
        // Add scores from direct remedy search (vector similarity)
        // NOTE: Vector similarity is a STARTING POINT, not the final word
        foreach ($directRemedies as $remedy) {
            $key = strtolower($remedy['remedy_name']);
            if (!isset($combinedScores[$key])) {
                $combinedScores[$key] = [
                    'remedy_name' => $remedy['remedy_name'],
                    'common_name' => $remedy['common_name'] ?? '',
                    'id' => $remedy['id'],
                    'raw_score' => 0,
                    'vector_score' => 0,
                    'hierarchy_score' => 0,
                    'totality_score' => 0,
                    'rubric_coverage' => 0,
                    'similarity' => $remedy['similarity'],
                    'keynote_symptoms' => $remedy['keynote_symptoms'] ?? '',
                    'book_reference' => $remedy['book_reference'] ?? '',
                    'matching_rubrics' => [],
                    'keyword_matches' => [],
                    'hierarchy_matches' => [
                        'pqrs' => [],
                        'mental' => [],
                        'general' => [],
                        'particular' => [],
                        'modality' => []
                    ],
                    'discharge_match' => false,
                    'case_type_bonus' => 0
                ];
            }
            // Vector similarity contributes to score BUT capped at 30% of total weight
            // This prevents high semantic match from dominating over proper homeopathic selection
            $combinedScores[$key]['vector_score'] = $remedy['similarity'] * 30;
            $combinedScores[$key]['raw_score'] += $remedy['similarity'] * 30;
        }
        
        // Apply DISCHARGE CHARACTER bonuses (very important for respiratory cases)
        // Track PRIMARY discharge remedies (those with high bonuses - the main sinusitis remedies)
        $primaryDischargeThreshold = 80;  // Only remedies with bonus >= 80 are PRIMARY matches
        
        foreach ($dischargeMatches as $remedyName => $bonus) {
            $key = strtolower($remedyName);
            $isPrimaryDischargeMatch = ($bonus >= $primaryDischargeThreshold);
            
            if (isset($combinedScores[$key])) {
                $combinedScores[$key]['raw_score'] += $bonus;
                // Only mark as TRUE discharge match if it's a PRIMARY indicator
                if ($isPrimaryDischargeMatch) {
                    $combinedScores[$key]['discharge_match'] = true;
                }
                $combinedScores[$key]['case_type_bonus'] = ($combinedScores[$key]['case_type_bonus'] ?? 0) + $bonus;
            } else {
                // Add remedy if not already present but matches discharge
                $combinedScores[$key] = [
                    'remedy_name' => $remedyName,
                    'common_name' => '',
                    'id' => 0,
                    'raw_score' => $bonus,
                    'vector_score' => 0,
                    'hierarchy_score' => 0,
                    'totality_score' => 0,
                    'rubric_coverage' => 0,
                    'similarity' => 0,
                    'keynote_symptoms' => '',
                    'book_reference' => '',
                    'matching_rubrics' => [],
                    'keyword_matches' => ['discharge_character'],
                    'hierarchy_matches' => [
                        'pqrs' => [],
                        'mental' => [],
                        'general' => [],
                        'particular' => [],
                        'modality' => []
                    ],
                    'discharge_match' => $isPrimaryDischargeMatch,
                    'case_type_bonus' => $bonus
                ];
            }
        }
        
        // Add scores from keyword/hybrid search (high priority for specific symptom matches)
        foreach ($keywordMatches as $match) {
            $key = strtolower($match['remedy_name']);
            if (!isset($combinedScores[$key])) {
                $combinedScores[$key] = [
                    'remedy_name' => $match['remedy_name'],
                    'common_name' => $match['common_name'] ?? '',
                    'id' => $match['id'] ?? 0,
                    'raw_score' => 0,
                    'vector_score' => 0,
                    'hierarchy_score' => 0,
                    'totality_score' => 0,
                    'rubric_coverage' => 0,
                    'similarity' => 0,
                    'keynote_symptoms' => $match['keynote_symptoms'] ?? '',
                    'book_reference' => $match['book_reference'] ?? '',
                    'matching_rubrics' => [],
                    'keyword_matches' => [],
                    'hierarchy_matches' => [
                        'pqrs' => [],
                        'mental' => [],
                        'general' => [],
                        'particular' => [],
                        'modality' => []
                    ]
                ];
            }
            
            // Keyword matches get scores based on clinical relevance
            $combinedScores[$key]['raw_score'] += $match['score'] * 8;
            $combinedScores[$key]['keyword_matches'][] = $match['matched_term'];
            
            // Update keynotes if we have better info
            if (empty($combinedScores[$key]['keynote_symptoms']) && !empty($match['keynote_symptoms'])) {
                $combinedScores[$key]['keynote_symptoms'] = $match['keynote_symptoms'];
            }
        }
        
        // STEP 2: Apply HIERARCHY-BASED scoring from repertory matches
        // This is where homeopathic principles override semantic matching
        $totalRubricCount = count($repertoryRubrics);
        
        foreach ($repertoryRubrics as $rubric) {
            $remedyList = explode(',', $rubric['remedies'] ?? '');
            $gradeInfo = $this->parseRemedyGrades($rubric['remedy_grades'] ?? '');
            
            // Determine the hierarchy category of this rubric
            $hierarchyCategory = $this->determineRubricHierarchy($rubric['rubric'], $rubric['category'] ?? '');
            
            // Use CONTEXT-AWARE hierarchy weight based on case type
            // For physical pathology: particular symptoms weighted higher, mental lower
            // For mental primary: mental symptoms weighted higher
            $hierarchyWeight = $hierarchyAdjustment[$hierarchyCategory] ?? $this->getHierarchyWeight($hierarchyCategory);
            
            // SECONDARY MENTAL SYMPTOM PENALTY
            // If this is a mental rubric but mental symptoms are secondary (e.g., "irritable from discomfort"),
            // reduce its weight significantly
            if ($hierarchyCategory === 'mental' && !empty($secondaryMentalSymptoms)) {
                $hierarchyWeight *= 0.5; // 50% reduction for secondary mental symptoms
            }
            
            foreach ($remedyList as $remedyName) {
                $remedyName = trim($remedyName);
                if (empty($remedyName)) continue;
                
                $key = strtolower($remedyName);
                if (!isset($combinedScores[$key])) {
                    $combinedScores[$key] = [
                        'remedy_name' => $remedyName,
                        'common_name' => '',
                        'raw_score' => 0,
                        'vector_score' => 0,
                        'hierarchy_score' => 0,
                        'totality_score' => 0,
                        'rubric_coverage' => 0,
                        'similarity' => 0,
                        'matching_rubrics' => [],
                        'hierarchy_matches' => [
                            'pqrs' => [],
                            'mental' => [],
                            'general' => [],
                            'particular' => [],
                            'modality' => []
                        ]
                    ];
                }
                
                // GRADE-WEIGHTED scoring with HIERARCHY multiplier
                // Grade 1 = mentioned, Grade 2 = common, Grade 3 = important, Grade 4 = keynote
                $grade = $gradeInfo[$remedyName] ?? 1;
                $gradeMultiplier = pow($grade, 1.5); // Non-linear: Grade 4 = 8x, Grade 3 = 5.2x, Grade 2 = 2.8x
                
                // Calculate rubric score: similarity × grade × hierarchy weight
                $rubricScore = $rubric['similarity'] * $gradeMultiplier * $hierarchyWeight;
                
                $combinedScores[$key]['hierarchy_score'] += $rubricScore;
                $combinedScores[$key]['raw_score'] += $rubricScore;
                $combinedScores[$key]['rubric_coverage']++;
                
                // Track which hierarchy categories this remedy covers
                $combinedScores[$key]['hierarchy_matches'][$hierarchyCategory][] = [
                    'rubric' => $rubric['rubric'],
                    'grade' => $grade,
                    'similarity' => $rubric['similarity']
                ];
                
                $combinedScores[$key]['matching_rubrics'][] = [
                    'rubric' => $rubric['rubric'],
                    'grade' => $grade,
                    'similarity' => $rubric['similarity'],
                    'hierarchy' => $hierarchyCategory,
                    'weight' => $hierarchyWeight
                ];
            }
        }
        
        // STEP 3: Apply TOTALITY bonus
        // Remedies covering more rubrics get a bonus (breadth of coverage)
        foreach ($combinedScores as $key => &$remedy) {
            $coverage = $remedy['rubric_coverage'];
            if ($totalRubricCount > 0 && $coverage > 0) {
                // Totality bonus: logarithmic scaling to favor broad coverage
                $coverageRatio = $coverage / $totalRubricCount;
                $totalityBonus = log10(1 + $coverage * 10) * 15 * $coverageRatio;
                $remedy['totality_score'] = $totalityBonus;
                $remedy['raw_score'] += $totalityBonus;
            }
        }
        unset($remedy);
        
        // Calculate combined score (normalized to 0-95%)
        // APPLY POLYCHREST BOOST to prioritize well-known, clinically proven remedies
        $maxScore = 0;
        
        // Track which remedies have discharge/pathology matches
        $hasDischargeSymptom = !empty($dischargeMatches);
        
        foreach ($combinedScores as $key => &$remedy) {
            // Apply polychrest multiplier
            $polychrestBoost = $this->getPolychrestBoost($remedy['remedy_name']);
            $remedy['raw_score'] *= $polychrestBoost;
            $remedy['polychrest_boost'] = $polychrestBoost;
            
            // AGE-SPECIFIC PENALTY
            // Apply penalty for remedies indicated for specific age groups
            // e.g., Baryta for elderly/children - not middle-aged adults
            $patientAge = $consultation['age'] ?? null;
            $agePenalty = $this->getAgeSpecificPenalty($remedy['remedy_name'], $patientAge);
            if ($agePenalty < 1.0) {
                $remedy['raw_score'] *= $agePenalty;
                $remedy['age_penalty'] = $agePenalty;
            }
            
            // CONTEXT-AWARE DEPRIORITIZATION
            // Deprioritize children's remedies for adult allergic rhinitis cases
            if ($isAdultAllergyCase) {
                $deprioritizeFactor = $this->getContextDeprioritization($remedy['remedy_name'], 'adult_allergy');
                $remedy['raw_score'] *= $deprioritizeFactor;
            }
            
            // PHYSICAL PATHOLOGY PENALTY
            // For physical pathology cases with clear discharge symptoms:
            // Penalize remedies that don't match the key pathological symptoms
            // This prevents mental remedies from dominating physical cases
            if ($caseType === 'physical_pathology' && $hasDischargeSymptom) {
                $hasDischargeMatch = isset($remedy['discharge_match']) && $remedy['discharge_match'];
                
                if (!$hasDischargeMatch) {
                    // Remedy doesn't match the key physical pathology
                    // Apply a significant penalty (50% reduction)
                    $remedy['raw_score'] *= 0.5;
                    $remedy['pathology_penalty'] = true;
                }
            }
            
            // SECONDARY MENTAL SYMPTOM PENALTY for the remedy itself
            // If mental keywords matched but they're secondary, reduce the contribution
            if ($caseType === 'physical_pathology' && !empty($secondaryMentalSymptoms)) {
                // Check if this remedy is primarily known for mental symptoms
                $mentalRemedies = ['nux vomica', 'ignatia amara', 'staphysagria', 'phosphoric acid', 'natrum muriaticum'];
                $ismentalRemedy = in_array(strtolower($remedy['remedy_name']), $mentalRemedies);
                
                // If the remedy's main indication is mental but the case is physical pathology
                // with secondary mental symptoms, reduce its score
                if ($ismentalRemedy && empty($remedy['discharge_match'])) {
                    $remedy['raw_score'] *= 0.6;  // 40% penalty
                    $remedy['secondary_mental_penalty'] = true;
                }
            }
            
            // CONSTITUTIONAL CONTRADICTION PENALTY
            // Apply severe penalty when remedy's ESSENTIAL characteristics contradict patient symptoms
            // This is critical for accurate homeopathic prescribing
            $contradictionPenalty = $this->calculateConstitutionalContradiction($remedy['remedy_name'], $consultation);
            if ($contradictionPenalty > 0) {
                $remedy['raw_score'] *= (1 - $contradictionPenalty);
                $remedy['constitutional_contradiction'] = $contradictionPenalty;
            }
            
            if ($remedy['raw_score'] > $maxScore) {
                $maxScore = $remedy['raw_score'];
            }
        }
        unset($remedy);
        
        // SYMPTOM ADEQUACY CHECK
        // Calculate how complete the case data is for confident prescribing
        $symptomAdequacy = $this->calculateSymptomAdequacy($symptoms, $consultation);
        
        if ($maxScore > 0) {
            foreach ($combinedScores as $key => &$remedy) {
                // Non-linear scaling: higher scores get better percentages
                $normalizedScore = $remedy['raw_score'] / $maxScore;
                
                // BASE calculation: 50-95% range
                $baseScore = 95 - (1 - $normalizedScore) * 45;
                
                // ADEQUACY CAP: Reduce maximum confidence when case data is insufficient
                // Full adequacy (1.0) = can reach 95%
                // Moderate adequacy (0.5) = capped at 72%
                // Low adequacy (0.3) = capped at 64%
                $maxAllowedScore = 50 + ($symptomAdequacy * 45);  // Range: 50-95%
                
                // Apply polychrest preference for sparse cases
                // When symptoms are insufficient, strongly prefer well-known remedies
                $polychrestBoost = $remedy['polychrest_boost'] ?? 1.0;
                if ($symptomAdequacy < 0.5) {
                    if ($polychrestBoost >= 2.5) {
                        // Polychrests get a relative boost in low-information scenarios
                        $baseScore = min($baseScore * 1.15, $maxAllowedScore);
                    } elseif ($polychrestBoost < 1.0) {
                        // Obscure remedies get heavily penalized
                        $baseScore = $baseScore * 0.7;
                    }
                }
                
                // Final score: minimum of base calculation and adequacy cap
                $remedy['combined_score'] = round(min($baseScore, $maxAllowedScore), 1);
                $remedy['symptom_adequacy'] = $symptomAdequacy;
            }
        }
        
        // Sort by combined score
        uasort($combinedScores, function($a, $b) {
            return $b['raw_score'] <=> $a['raw_score'];
        });
        
        return array_values($combinedScores);
    }
    
    /**
     * Check if this is an adult allergic rhinitis case
     */
    private function isAdultAllergyCase($consultation) {
        $age = $consultation['age'] ?? 0;
        $isAdult = $age >= 12;  // Children under 12 can use Cina etc.
        
        // Check for allergic rhinitis indicators
        $text = strtolower(
            ($consultation['chief_complaint'] ?? '') . ' ' .
            ($consultation['diagnosis'] ?? '') . ' ' .
            ($consultation['particular_symptoms'] ?? '')
        );
        
        $allergyIndicators = [
            'allerg', 'rhinitis', 'hay fever', 'sneezing', 'coryza', 'runny nose',
            'watery discharge', 'itching', 'vasomotor'
        ];
        
        $hasAllergyIndicator = false;
        foreach ($allergyIndicators as $indicator) {
            if (strpos($text, $indicator) !== false) {
                $hasAllergyIndicator = true;
                break;
            }
        }
        
        return $isAdult && $hasAllergyIndicator;
    }
    
    /**
     * Get context-specific deprioritization factor for remedies
     */
    private function getContextDeprioritization($remedyName, $context) {
        $normalizedName = strtolower(trim($remedyName));
        
        // Context-specific deprioritization maps
        $contextMaps = [
            'adult_allergy' => [
                // Children's remedies that should be deprioritized for adult allergic rhinitis
                'cina' => 0.3,  // Primary children's remedy for worms
                'chamomilla' => 0.5,  // Often children's remedy
                'calcarea carbonica' => 0.7,  // Less relevant for acute allergic
                'antimonium tartaricum' => 0.6,  // Respiratory but for elderly/children
                'aethusa cynapium' => 0.4,  // Milk intolerance in infants
            ]
        ];
        
        if (isset($contextMaps[$context][$normalizedName])) {
            return $contextMaps[$context][$normalizedName];
        }
        
        return 1.0;  // No deprioritization
    }
    
    /**
     * Determine the type of case for context-aware hierarchy adjustment
     * 
     * PHYSICAL PATHOLOGY cases (sinusitis, bronchitis, eczema, etc.):
     * - Particular symptoms matter MORE
     * - Discharge character is KEY
     * - Mental symptoms secondary unless constitutional
     * 
     * MENTAL/EMOTIONAL cases (anxiety, depression, grief):
     * - Mental symptoms matter MORE
     * - Physical symptoms often secondary
     */
    private function determineCaseType($consultation) {
        $diagnosis = strtolower($consultation['diagnosis'] ?? '');
        $chiefComplaint = strtolower($consultation['chief_complaint'] ?? '');
        $particular = strtolower($consultation['particular_symptoms'] ?? '');
        $mental = strtolower($consultation['mental_state'] ?? '');
        
        $text = $diagnosis . ' ' . $chiefComplaint . ' ' . $particular;
        
        // Physical pathology indicators
        $physicalPathologyPatterns = [
            'sinusitis', 'rhinitis', 'bronchitis', 'pneumonia', 'tonsillitis',
            'pharyngitis', 'laryngitis', 'otitis', 'conjunctivitis',
            'dermatitis', 'eczema', 'psoriasis', 'acne', 'urticaria',
            'gastritis', 'colitis', 'hepatitis', 'nephritis', 'cystitis',
            'arthritis', 'bursitis', 'tendinitis', 'neuritis',
            'discharge', 'pus', 'suppuration', 'inflammation',
            'infection', 'abscess', 'ulcer', 'swelling', 'tumor',
            'blocked nose', 'nasal obstruction', 'loss of smell',
            'cough', 'wheeze', 'expectoration', 'sputum'
        ];
        
        // Mental/emotional primary indicators
        $mentalPrimaryPatterns = [
            'anxiety disorder', 'panic disorder', 'depression', 'grief',
            'ptsd', 'ocd', 'phobia', 'insomnia', 'hysteria',
            'emotional trauma', 'mental breakdown', 'psychosis'
        ];
        
        // Check for physical pathology
        foreach ($physicalPathologyPatterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return 'physical_pathology';
            }
        }
        
        // Check for primary mental condition
        foreach ($mentalPrimaryPatterns as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return 'mental_primary';
            }
        }
        
        // If mental state is substantial but physical is minimal
        if (strlen($mental) > 100 && strlen($particular) < 50) {
            return 'mental_primary';
        }
        
        // Default: balanced case
        return 'balanced';
    }
    
    /**
     * Get hierarchy weight adjustments based on case type
     * 
     * For PHYSICAL PATHOLOGY:
     * - Boost particular symptoms (discharge character, local symptoms)
     * - Reduce mental weight (often secondary/reactive)
     * 
     * For MENTAL PRIMARY:
     * - Boost mental symptoms
     * - Particular symptoms support but don't lead
     */
    private function getHierarchyAdjustment($caseType) {
        $adjustments = [
            'physical_pathology' => [
                'particular' => 2.5,  // Boost from 1.0 to 2.5
                'mental' => 1.5,      // Reduce from 3.0 to 1.5 (still relevant but not dominant)
                'general' => 2.0,     // Keep same
                'modality' => 2.5,    // Keep same
                'pqrs' => 4.0         // Keep same
            ],
            'mental_primary' => [
                'particular' => 1.0,  // Keep same
                'mental' => 4.0,      // Boost from 3.0 to 4.0
                'general' => 2.0,     // Keep same
                'modality' => 2.5,    // Keep same
                'pqrs' => 4.0         // Keep same
            ],
            'balanced' => [
                'particular' => 1.0,
                'mental' => 3.0,
                'general' => 2.0,
                'modality' => 2.5,
                'pqrs' => 4.0
            ]
        ];
        
        return $adjustments[$caseType] ?? $adjustments['balanced'];
    }
    
    /**
     * Detect secondary mental symptoms that should be weighted less
     * 
     * "Irritable FROM discomfort" is different from constitutional irritability
     * "Anxious about health" during illness is different from anxiety disorder
     */
    private function detectSecondaryMentalSymptoms($consultation) {
        $mental = strtolower($consultation['mental_state'] ?? '');
        $secondary = [];
        
        // Patterns indicating secondary/reactive mental symptoms
        $secondaryPatterns = [
            '/irritable.*from.*discomfort/i' => 'irritability',
            '/irritable.*due.*to/i' => 'irritability',
            '/irritable.*because/i' => 'irritability',
            '/anxious.*about.*health/i' => 'health_anxiety',
            '/worried.*about.*condition/i' => 'health_anxiety',
            '/depressed.*from.*pain/i' => 'reactive_depression',
            '/sad.*due.*to.*illness/i' => 'reactive_depression',
            '/cannot.*concentrate.*from.*pain/i' => 'secondary_confusion',
            '/restless.*from.*discomfort/i' => 'secondary_restlessness'
        ];
        
        foreach ($secondaryPatterns as $pattern => $type) {
            if (preg_match($pattern, $mental)) {
                $secondary[] = $type;
            }
        }
        
        // Also check for explicit "from" or "due to" phrases
        if (preg_match('/(?:irritabl|anxious|restless|depress).*(?:from|due to|because of).*(?:pain|discomfort|symptom|illness|condition)/i', $mental)) {
            $secondary[] = 'general_secondary';
        }
        
        return $secondary;
    }
    
    /**
     * Match discharge character - CRITICAL for respiratory cases
     * 
     * Discharge character (thick/thin, color, consistency) is a KEY differentiating
     * symptom in homeopathy that often determines the remedy.
     * 
     * THICK YELLOW/GREEN = Pulsatilla, Kali-bich, Hepar-sulph, Merc-sol
     * THIN WATERY = Allium-cepa, Arsenicum, Natrum-mur
     * BLAND = Pulsatilla, Allium-cepa (nasal)
     * ACRID/EXCORIATING = Arsenicum, Allium-cepa (tears)
     * 
     * IMPORTANT: These bonuses are HIGH because discharge character is often
     * THE deciding factor between remedies in respiratory cases.
     * A remedy without discharge coverage should rarely top a sinusitis case.
     */
    private function matchDischargeCharacter($consultation) {
        $particular = strtolower($consultation['particular_symptoms'] ?? '');
        $chief = strtolower($consultation['chief_complaint'] ?? '');
        $general = strtolower($consultation['general_symptoms'] ?? '');
        $diagnosis = strtolower($consultation['diagnosis'] ?? '');
        $text = $particular . ' ' . $chief . ' ' . $general . ' ' . $diagnosis;
        
        $matches = [];
        $hasDischargeSymptom = false;
        
        // THICK YELLOW/GREEN discharge - Very specific indicator
        if (preg_match('/thick.*(yellow|green|yellow-green|greenish)/i', $text) ||
            preg_match('/(yellow|green|yellow-green|greenish).*thick/i', $text) ||
            preg_match('/(yellow|green).*(nasal|discharge|mucus)/i', $text) ||
            preg_match('/(yellow-green|yellowish.?green)/i', $text)) {
            
            $hasDischargeSymptom = true;
            
            // Top remedies for thick yellow/green discharge - HIGH BONUSES
            // These should DOMINATE the results for such a specific symptom
            $matches['Pulsatilla Nigricans'] = 150;  // KEYNOTE - thick, bland, yellow-green
            $matches['Kali Bichromicum'] = 130;      // Thick, stringy, yellow
            $matches['Hepar Sulphuris'] = 100;       // Thick, yellow, offensive
            $matches['Mercurius Solubilis'] = 90;    // Yellow-green, offensive
            $matches['Hydrastis Canadensis'] = 85;   // Thick, yellow, ropy
            $matches['Silicea'] = 70;                // Chronic suppuration
        }
        
        // Loss of smell with nasal symptoms - important concomitant
        if (preg_match('/loss.*smell|anosmia|cannot.*smell/i', $text)) {
            $hasDischargeSymptom = true;
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 50;
            $matches['Natrum Muriaticum'] = ($matches['Natrum Muriaticum'] ?? 0) + 40;
            $matches['Kali Bichromicum'] = ($matches['Kali Bichromicum'] ?? 0) + 35;
            $matches['Silicea'] = ($matches['Silicea'] ?? 0) + 35;
            $matches['Lemna Minor'] = ($matches['Lemna Minor'] ?? 0) + 30;
        }
        
        // Post-nasal drip
        if (preg_match('/post.?nasal|drip.*throat|hawking/i', $text)) {
            $hasDischargeSymptom = true;
            $matches['Hydrastis Canadensis'] = ($matches['Hydrastis Canadensis'] ?? 0) + 45;
            $matches['Kali Bichromicum'] = ($matches['Kali Bichromicum'] ?? 0) + 45;
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 35;
            $matches['Corallium Rubrum'] = ($matches['Corallium Rubrum'] ?? 0) + 30;
        }
        
        // Frontal headache with sinusitis
        if (preg_match('/frontal.*head|head.*frontal|sinus.*head/i', $text)) {
            $matches['Kali Bichromicum'] = ($matches['Kali Bichromicum'] ?? 0) + 40;
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 30;
            $matches['Silicea'] = ($matches['Silicea'] ?? 0) + 25;
        }
        
        // Nasal obstruction/blocked nose
        if (preg_match('/blocked.*nose|nasal.*obstruct|stuffy.*nose|cannot.*breathe.*nose/i', $text)) {
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 30;
            $matches['Sambucus Nigra'] = ($matches['Sambucus Nigra'] ?? 0) + 35;
            $matches['Nux Vomica'] = ($matches['Nux Vomica'] ?? 0) + 15;
            $matches['Lycopodium Clavatum'] = ($matches['Lycopodium Clavatum'] ?? 0) + 15;
        }
        
        // THIN WATERY discharge
        if (preg_match('/thin.*water|watery.*discharge|profuse.*water|running.*nose/i', $text)) {
            $hasDischargeSymptom = true;
            $matches['Allium Cepa'] = ($matches['Allium Cepa'] ?? 0) + 100;
            $matches['Arsenicum Album'] = ($matches['Arsenicum Album'] ?? 0) + 80;
            $matches['Natrum Muriaticum'] = ($matches['Natrum Muriaticum'] ?? 0) + 70;
            $matches['Sabadilla'] = ($matches['Sabadilla'] ?? 0) + 60;
        }
        
        // ACRID/EXCORIATING discharge
        if (preg_match('/acrid|excoriate|burn.*nostril|raw.*nose|sore.*nostril/i', $text)) {
            $hasDischargeSymptom = true;
            $matches['Arsenicum Album'] = ($matches['Arsenicum Album'] ?? 0) + 80;
            $matches['Allium Cepa'] = ($matches['Allium Cepa'] ?? 0) + 70;
            $matches['Arum Triphyllum'] = ($matches['Arum Triphyllum'] ?? 0) + 60;
        }
        
        // CHRONIC sinusitis - specific remedies
        if (preg_match('/chronic.*sinus|sinus.*chronic|recur.*sinus|persistent.*sinus/i', $text) ||
            preg_match('/chronic.*rhinosinusitis/i', $diagnosis)) {
            $matches['Silicea'] = ($matches['Silicea'] ?? 0) + 60;
            $matches['Kali Bichromicum'] = ($matches['Kali Bichromicum'] ?? 0) + 55;
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 50;
            $matches['Calcarea Carbonica'] = ($matches['Calcarea Carbonica'] ?? 0) + 40;
            $matches['Thuja Occidentalis'] = ($matches['Thuja Occidentalis'] ?? 0) + 35;
        }
        
        // SINUSITIS general (not just chronic)
        if (preg_match('/sinusitis|sinus.*infection|rhinosinusitis/i', $text . ' ' . $diagnosis)) {
            // Boost all sinusitis-indicated remedies
            $matches['Pulsatilla Nigricans'] = ($matches['Pulsatilla Nigricans'] ?? 0) + 40;
            $matches['Kali Bichromicum'] = ($matches['Kali Bichromicum'] ?? 0) + 40;
            $matches['Silicea'] = ($matches['Silicea'] ?? 0) + 30;
            $matches['Mercurius Solubilis'] = ($matches['Mercurius Solubilis'] ?? 0) + 25;
            $matches['Hepar Sulphuris'] = ($matches['Hepar Sulphuris'] ?? 0) + 25;
        }
        
        return $matches;
    }

    /**
     * Parse remedy grades from string
     */
    private function parseRemedyGrades($gradesString) {
        $grades = [];
        $parts = explode(';', $gradesString);
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^(.+):(\d)$/', $part, $matches)) {
                $grades[trim($matches[1])] = (int)$matches[2];
            }
        }
        return $grades;
    }
    
    /**
     * Classify rubrics by homeopathic hierarchy
     * Returns rubrics organized by their importance category
     */
    private function classifyRubricsByHierarchy($rubrics, $symptoms, $consultation) {
        $classified = [
            'pqrs' => [],      // Peculiar, Queer, Rare, Strange
            'mental' => [],    // Mental/Emotional symptoms
            'general' => [],   // General symptoms (whole person)
            'particular' => [],// Particular symptoms (local)
            'modality' => []   // Modalities
        ];
        
        foreach ($rubrics as $rubric) {
            $category = $this->determineRubricHierarchy($rubric['rubric'], $rubric['category'] ?? '');
            $classified[$category][] = $rubric;
        }
        
        return $classified;
    }
    
    /**
     * Determine which hierarchy category a rubric belongs to
     * 
     * PQRS - The most individualizing symptoms:
     * - Strange concomitants (e.g., "thirst with diarrhea")
     * - Opposite to expected (e.g., "burning better by heat")
     * - Very specific modalities
     * - Unusual mental states
     */
    private function determineRubricHierarchy($rubricText, $category) {
        $rubric = strtolower($rubricText);
        $cat = strtolower($category);
        
        // PQRS indicators - Strange, Peculiar symptoms
        $pqrsPatterns = [
            '/burning.*better.*heat/',
            '/cold.*better.*cold/',
            '/worse.*rest.*better.*motion/',
            '/better.*motion/',
            '/desires.*salt/',
            '/aversion.*consolation/',
            '/better.*lying.*painful.*side/',
            '/worse.*sympathy/',
            '/loquacity/',
            '/contradiction.*agg/',
            '/with.*weeping/',
            '/alternating/',
            '/strange/',
            '/peculiar/',
            '/paradoxical/'
        ];
        
        foreach ($pqrsPatterns as $pattern) {
            if (preg_match($pattern, $rubric)) {
                return 'pqrs';
            }
        }
        
        // Mental/Emotional category
        if ($cat === 'mind' || $cat === 'mental') {
            return 'mental';
        }
        
        $mentalKeywords = [
            'anxiety', 'fear', 'anger', 'irritab', 'weeping', 'grief', 'jealous',
            'suspicious', 'delusion', 'memory', 'confusion', 'indifferen', 'apathy',
            'restless', 'despair', 'suicid', 'company', 'alone', 'consolation',
            'anticipation', 'hurry', 'impatien', 'mortification', 'fright'
        ];
        
        foreach ($mentalKeywords as $keyword) {
            if (strpos($rubric, $keyword) !== false) {
                return 'mental';
            }
        }
        
        // General symptoms (affect the whole person)
        $generalKeywords = [
            'chilly', 'warm', 'cold', 'heat', 'thirst', 'appetite', 'desire',
            'aversion', 'sleep', 'perspiration', 'weakness', 'fatigue', 'energy',
            'food', 'drink', 'side', 'morning', 'evening', 'night', 'periodic',
            'weather', 'temperature', 'season'
        ];
        
        foreach ($generalKeywords as $keyword) {
            if (strpos($rubric, $keyword) !== false) {
                return 'general';
            }
        }
        
        // Modalities (what makes better/worse)
        if (preg_match('/(agg|amel|better|worse|<|>)/', $rubric)) {
            return 'modality';
        }
        
        // Default to particular (local symptoms)
        return 'particular';
    }
    
    /**
     * Get the scoring weight for each hierarchy category
     * 
     * These weights reflect homeopathic prescribing principles:
     * - PQRS symptoms are most valuable for individualization
     * - Mental symptoms outrank physical in most cases
     * - Modalities are crucial for differentiating similar remedies
     */
    private function getHierarchyWeight($category) {
        $weights = [
            'pqrs' => 4.0,      // Highest weight - most individualizing
            'mental' => 3.0,    // Mental symptoms very important
            'modality' => 2.5,  // Modalities help differentiate
            'general' => 2.0,   // General symptoms moderate weight
            'particular' => 1.0 // Local symptoms lowest weight
        ];
        
        return $weights[$category] ?? 1.0;
    }

    /**
     * Determine appropriate potency based on symptoms, patient, and case analysis
     * 
     * Homeopathic Potency Selection Guidelines:
     * - Low potencies (6C, 12C): Sensitive patients, elderly, children, frequent repetition needed
     * - Medium potencies (30C): Most common, safe starting point, acute/sub-acute cases
     * - High potencies (200C): Clear symptom picture, strong vitality, acute intense cases
     * - Very high potencies (1M, 10M): Constitutional treatment, chronic deep-seated cases with clear picture
     * 
     * Repetition Guidelines:
     * - Acute: Frequent repetition (hourly to every few hours) until improvement
     * - Chronic: Infrequent repetition (single dose or weekly), wait for action
     */
    private function determinePotency($symptoms, $age = null, $consultation = null) {
        $potencyFactors = [
            'chronicity_score' => 0,      // Higher = more chronic = higher potency
            'vitality_score' => 50,        // Higher = stronger vitality = can handle higher potency
            'symptom_clarity' => 50,       // Higher = clearer picture = higher potency safer
            'sensitivity_score' => 0,      // Higher = more sensitive = lower potency
            'intensity_score' => 50,       // Higher = more intense = higher potency
        ];
        
        $hasSevere = false;
        $hasAcute = false;
        $hasChronic = false;
        $hasMentalSymptoms = false;
        $symptomCount = count($symptoms);
        
        // Analyze symptoms for chronicity and intensity
        foreach ($symptoms as $symptom) {
            $severity = strtolower($symptom['severity'] ?? $symptom['intensity'] ?? '');
            if ($severity === 'severe' || $severity === 'high') {
                $hasSevere = true;
                $potencyFactors['intensity_score'] += 20;
            } elseif ($severity === 'moderate') {
                $potencyFactors['intensity_score'] += 10;
            }
            
            $duration = strtolower($symptom['duration'] ?? '');
            // Acute indicators
            if (preg_match('/hour|minute|sudden|today|yesterday/', $duration)) {
                $hasAcute = true;
            }
            // Chronic indicators
            if (preg_match('/month|year|chronic|long|always|since childhood/', $duration)) {
                $hasChronic = true;
                $potencyFactors['chronicity_score'] += 20;
            }
            if (preg_match('/week/', $duration)) {
                $potencyFactors['chronicity_score'] += 10;
            }
        }
        
        // Check consultation for additional factors
        if ($consultation) {
            $allText = strtolower(
                ($consultation['chief_complaint'] ?? '') . ' ' .
                ($consultation['general_symptoms'] ?? '') . ' ' .
                ($consultation['particular_symptoms'] ?? '') . ' ' .
                ($consultation['mental_state'] ?? '')
            );
            
            // Mental symptoms indicate need for higher potency (deeper action)
            if (!empty($consultation['mental_state'])) {
                $hasMentalSymptoms = true;
                $potencyFactors['symptom_clarity'] += 15;
            }
            
            // Check for chronic disease indicators
            $chronicIndicators = ['years', 'chronic', 'recurrent', 'recurring', 'since childhood', 
                                  'long standing', 'persistent', 'ongoing'];
            foreach ($chronicIndicators as $indicator) {
                if (strpos($allText, $indicator) !== false) {
                    $hasChronic = true;
                    $potencyFactors['chronicity_score'] += 15;
                    break;
                }
            }
            
            // Check for sensitivity indicators
            $sensitivityIndicators = ['sensitive', 'allergic', 'reaction to', 'intolerant',
                                      'hypersensitive', 'adverse reaction'];
            foreach ($sensitivityIndicators as $indicator) {
                if (strpos($allText, $indicator) !== false) {
                    $potencyFactors['sensitivity_score'] += 30;
                    break;
                }
            }
            
            // Clear symptom picture increases confidence for higher potency
            if ($symptomCount >= 5) {
                $potencyFactors['symptom_clarity'] += 20;
            } elseif ($symptomCount >= 3) {
                $potencyFactors['symptom_clarity'] += 10;
            }
        }
        
        // Age considerations
        if ($age !== null) {
            if ($age < 2) {
                $potencyFactors['sensitivity_score'] += 40;
                $potencyFactors['vitality_score'] = 40;
            } elseif ($age < 5) {
                $potencyFactors['sensitivity_score'] += 25;
                $potencyFactors['vitality_score'] = 50;
            } elseif ($age < 12) {
                $potencyFactors['sensitivity_score'] += 10;
                $potencyFactors['vitality_score'] = 60;
            } elseif ($age > 80) {
                $potencyFactors['sensitivity_score'] += 30;
                $potencyFactors['vitality_score'] = 40;
            } elseif ($age > 65) {
                $potencyFactors['sensitivity_score'] += 15;
                $potencyFactors['vitality_score'] = 50;
            } else {
                // Adults 12-65 have good vitality
                $potencyFactors['vitality_score'] = 70;
            }
        }
        
        // Calculate composite score
        $compositeScore = (
            $potencyFactors['chronicity_score'] * 0.25 +
            $potencyFactors['vitality_score'] * 0.25 +
            $potencyFactors['symptom_clarity'] * 0.25 +
            $potencyFactors['intensity_score'] * 0.15 -
            $potencyFactors['sensitivity_score'] * 0.10
        );
        
        // Determine potency and repetition based on composite score
        $potencyResult = $this->selectPotencyFromScore($compositeScore, $hasAcute, $hasChronic, $hasMentalSymptoms);
        
        return $potencyResult;
    }
    
    /**
     * Select potency and repetition guidelines based on case analysis
     */
    private function selectPotencyFromScore($score, $isAcute, $isChronic, $hasMentalSymptoms) {
        // For acute cases with clear indications
        if ($isAcute && !$isChronic) {
            if ($score >= 70) {
                return [
                    'potency' => '200C',
                    'repetition' => 'Single dose, repeat in 2-4 hours if needed',
                    'frequency' => 'Every 2-4 hours until relief',
                    'duration' => '1-3 days',
                    'notes' => 'Acute case with strong symptoms - 200C for rapid action'
                ];
            } elseif ($score >= 50) {
                return [
                    'potency' => '30C',
                    'repetition' => '3 times daily',
                    'frequency' => 'Every 4-6 hours',
                    'duration' => '2-5 days',
                    'notes' => 'Acute case - 30C is safe and effective starting point'
                ];
            } else {
                return [
                    'potency' => '12C',
                    'repetition' => '4 times daily',
                    'frequency' => 'Every 4 hours',
                    'duration' => '3-5 days',
                    'notes' => 'Low potency for sensitive patient in acute phase'
                ];
            }
        }
        
        // For chronic cases
        if ($isChronic) {
            if ($hasMentalSymptoms && $score >= 65) {
                return [
                    'potency' => '1M',
                    'repetition' => 'Single dose',
                    'frequency' => 'Wait 4-6 weeks before repeating',
                    'duration' => 'Constitutional treatment - monitor for 4-6 weeks',
                    'notes' => 'Chronic case with clear mental picture - 1M for deep constitutional action'
                ];
            } elseif ($score >= 60) {
                return [
                    'potency' => '200C',
                    'repetition' => 'Single dose or weekly',
                    'frequency' => 'Once weekly for 3-4 weeks',
                    'duration' => '4-6 weeks observation',
                    'notes' => 'Chronic case with good vitality - 200C for constitutional effect'
                ];
            } elseif ($score >= 45) {
                return [
                    'potency' => '30C',
                    'repetition' => 'Daily or twice weekly',
                    'frequency' => 'Once or twice daily',
                    'duration' => '2-4 weeks, then reassess',
                    'notes' => 'Chronic case - 30C allows gradual, safe improvement'
                ];
            } else {
                return [
                    'potency' => '12C or 30C',
                    'repetition' => 'Daily',
                    'frequency' => 'Once or twice daily',
                    'duration' => '3-4 weeks',
                    'notes' => 'Sensitive patient with chronic condition - start low'
                ];
            }
        }
        
        // Default for mixed/unclear cases
        if ($score >= 60) {
            return [
                'potency' => '30C',
                'repetition' => 'Twice daily',
                'frequency' => 'Morning and evening',
                'duration' => '1-2 weeks, then reassess',
                'notes' => '30C is the safest and most versatile starting potency'
            ];
        } else {
            return [
                'potency' => '30C',
                'repetition' => 'Once daily',
                'frequency' => 'Once daily',
                'duration' => '1-2 weeks',
                'notes' => 'Conservative approach for unclear symptom picture'
            ];
        }
    }
    
    /**
     * Build reasoning text for a remedy suggestion
     */
    private function buildReasoning($remedy, $symptoms) {
        $parts = [];
        
        // HIERARCHY-BASED REASONING - Most important first
        // This shows users WHY the remedy was selected according to homeopathic principles
        
        // 1. Show hierarchy matches (PQRS > Mental > Modality > General > Particular)
        if (!empty($remedy['hierarchy_matches'])) {
            $hierarchyInfo = [];
            
            // PQRS matches are most significant
            if (!empty($remedy['hierarchy_matches']['pqrs'])) {
                $count = count($remedy['hierarchy_matches']['pqrs']);
                $hierarchyInfo[] = "{$count} peculiar/strange symptom(s)";
            }
            
            // Mental matches
            if (!empty($remedy['hierarchy_matches']['mental'])) {
                $count = count($remedy['hierarchy_matches']['mental']);
                $hierarchyInfo[] = "{$count} mental symptom(s)";
            }
            
            // Modality matches
            if (!empty($remedy['hierarchy_matches']['modality'])) {
                $count = count($remedy['hierarchy_matches']['modality']);
                $hierarchyInfo[] = "{$count} modality match(es)";
            }
            
            if (!empty($hierarchyInfo)) {
                $parts[] = "Homeopathic hierarchy match: " . implode(', ', $hierarchyInfo);
            }
        }
        
        // 2. Show totality score (breadth of coverage)
        if (!empty($remedy['rubric_coverage']) && $remedy['rubric_coverage'] > 1) {
            $parts[] = "Covers {$remedy['rubric_coverage']} rubrics (totality)";
        }
        
        // 3. Show matching rubrics with grades
        if (!empty($remedy['matching_rubrics'])) {
            $rubricCount = count($remedy['matching_rubrics']);
            $highGradeRubrics = array_filter($remedy['matching_rubrics'], function($r) {
                return ($r['grade'] ?? 1) >= 3;
            });
            
            if (!empty($highGradeRubrics)) {
                $topRubrics = array_slice($highGradeRubrics, 0, 2);
                $rubricNames = array_map(function($r) {
                    $grade = str_repeat('●', $r['grade'] ?? 1);
                    return "{$r['rubric']} [{$grade}]";
                }, $topRubrics);
                $parts[] = "High-grade rubrics: " . implode('; ', $rubricNames);
            } else {
                $topRubrics = array_slice($remedy['matching_rubrics'], 0, 2);
                $rubricNames = array_map(function($r) { return $r['rubric']; }, $topRubrics);
                $parts[] = "Matches {$rubricCount} rubric(s): " . implode('; ', $rubricNames);
            }
        }
        
        // 4. Semantic similarity (now secondary, not primary)
        if (!empty($remedy['similarity']) && $remedy['similarity'] > 0.7) {
            $parts[] = "Semantic match: " . round($remedy['similarity'] * 100, 1) . "%";
        }
        
        // 5. Add keynote reference if available
        if (!empty($remedy['keynote_symptoms'])) {
            $keynote = substr($remedy['keynote_symptoms'], 0, 120);
            $parts[] = "Keynote: " . $keynote;
        }
        
        return implode(". ", $parts);
    }
    
    /**
     * Get keynote excerpt for display
     */
    private function getKeynoteExcerpt($remedy) {
        $keynotes = $remedy['keynote_symptoms'] ?? '';
        if (strlen($keynotes) > 200) {
            return substr($keynotes, 0, 200) . '...';
        }
        return $keynotes;
    }
    
    /**
     * Build case analysis summary
     * Now includes hierarchy-based analysis explanation
     */
    private function buildCaseAnalysis($suggestions, $symptoms, $chiefComplaint) {
        $symptomCount = count($symptoms);
        $analysisLines = [];
        
        $analysisLines[] = "**Case Analysis** (Hierarchy-Based Scoring)";
        $analysisLines[] = "";
        
        // Explain the hierarchy approach
        $analysisLines[] = "Remedies scored using homeopathic hierarchy:";
        $analysisLines[] = "• PQRS (Peculiar/Strange) symptoms: 4× weight";
        $analysisLines[] = "• Mental symptoms: 3× weight";
        $analysisLines[] = "• Modalities: 2.5× weight";
        $analysisLines[] = "• General symptoms: 2× weight";
        $analysisLines[] = "• Particular symptoms: 1× weight";
        $analysisLines[] = "";
        
        if (!empty($suggestions)) {
            $topRemedy = $suggestions[0];
            $analysisLines[] = "**Top Remedy: {$topRemedy['name']}**";
            
            // Show hierarchy breakdown if available
            if (!empty($topRemedy['reasoning'])) {
                $analysisLines[] = $topRemedy['reasoning'];
            }
            
            if (count($suggestions) > 1) {
                $differentials = array_slice(array_column($suggestions, 'name'), 1, 3);
                $analysisLines[] = "";
                $analysisLines[] = "**Differentials:** " . implode(', ', $differentials);
            }
        }
        
        $analysisLines[] = "";
        $analysisLines[] = "_Note: High semantic score alone does not guarantee correct remedy._";
        $analysisLines[] = "_Verify matches against actual rubrics and keynote symptoms._";
        
        return implode("\n", $analysisLines);
    }
    
    /**
     * Generate cautions for the suggestions
     */
    private function generateCautions($suggestions) {
        $cautions = [];
        $cautions[] = "These are AI-assisted suggestions based on semantic similarity analysis.";
        $cautions[] = "Always verify with classical homeopathic repertorization.";
        $cautions[] = "Consider the totality of symptoms and patient constitution.";
        $cautions[] = "Start with the lowest effective potency and observe response.";
        $cautions[] = "These suggestions support, but do not replace, professional clinical judgment.";
        
        return implode("\n", $cautions);
    }
    
    /**
     * Check if vector embeddings are available
     */
    public function hasEmbeddings($entityType = null) {
        if ($entityType) {
            return $this->vectorStore->countByType($entityType) > 0;
        }
        
        // Check all types
        return $this->vectorStore->countByType('remedy') > 0;
    }
    
    /**
     * Get embedding statistics
     */
    public function getEmbeddingStats() {
        return [
            'remedies' => $this->vectorStore->countByType('remedy'),
            'repertory' => $this->vectorStore->countByType('repertory'),
            'diseases' => $this->vectorStore->countByType('disease')
        ];
    }
    
    /**
     * Extract and categorize modalities from consultation text
     * 
     * REPERTORY-STYLE MODALITY EXTRACTION:
     * In homeopathy, modalities are categorized as:
     * - Aggravations (Agg/<): Factors that make symptoms WORSE
     * - Ameliorations (Amel/>): Factors that make symptoms BETTER
     * 
     * Categories include: Thermal, Time, Position/Motion, Weather, Food/Drink, Mental
     */
    private function extractModalities($text, $consultation = []) {
        $modalities = [
            'aggravations' => [],
            'ameliorations' => [],
            'thermal' => null,
            'time' => [],
            'position' => [],
            'weather' => [],
            'food' => [],
            'mental' => [],
            'raw_modality_score' => 0
        ];
        
        // Add consultation-level modalities
        if (!empty($consultation['aggravation'])) {
            $text .= ' worse from ' . strtolower($consultation['aggravation']);
        }
        if (!empty($consultation['amelioration'])) {
            $text .= ' better from ' . strtolower($consultation['amelioration']);
        }
        if (!empty($consultation['thermal_state'])) {
            $text .= ' ' . strtolower($consultation['thermal_state']);
        }
        
        $text = strtolower($text);
        
        // THERMAL MODALITIES - very important in repertory
        $thermalAggPatterns = [
            'worse.*warmth' => 'warmth_agg',
            'worse.*heat' => 'heat_agg',
            'worse.*warm.*room' => 'warm_room_agg',
            'worse.*warm.*bed' => 'warm_bed_agg',
            'worse.*sun' => 'sun_agg',
            'worse.*cold' => 'cold_agg',
            'worse.*winter' => 'winter_agg',
            'worse.*draft|worse.*draught' => 'draft_agg',
            'aggrav.*warmth' => 'warmth_agg',
            'aggrav.*cold' => 'cold_agg',
        ];
        
        $thermalAmelPatterns = [
            'better.*warmth|relief.*warmth' => 'warmth_amel',
            'better.*heat|relief.*heat' => 'heat_amel',
            'better.*warm.*application' => 'warm_application_amel',
            'better.*cold|relief.*cold' => 'cold_amel',
            'better.*cold.*application' => 'cold_application_amel',
            'better.*open.*air' => 'open_air_amel',
            'amel.*warmth' => 'warmth_amel',
            'amel.*cold' => 'cold_amel',
        ];
        
        // Check thermal constitution
        if (preg_match('/chilly|cold.*patient|always.*cold|freezing/', $text)) {
            $modalities['thermal'] = 'chilly';
        } elseif (preg_match('/hot.*patient|warm.*blood|overheated|can\'t.*stand.*heat/', $text)) {
            $modalities['thermal'] = 'hot';
        }
        
        foreach ($thermalAggPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['aggravations'][] = $tag;
                $modalities['raw_modality_score'] += 15; // Important clinical info
            }
        }
        
        foreach ($thermalAmelPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['ameliorations'][] = $tag;
                $modalities['raw_modality_score'] += 15;
            }
        }
        
        // TIME MODALITIES
        $timePatterns = [
            'worse.*morning|morning.*worse' => 'morning_agg',
            'worse.*evening|evening.*worse' => 'evening_agg',
            'worse.*night|night.*worse|nocturnal' => 'night_agg',
            'worse.*3.*am|worse.*4.*am' => 'early_morning_agg',
            'worse.*4.*8.*pm|worse.*afternoon' => 'afternoon_agg',
            'better.*morning' => 'morning_amel',
            'better.*night' => 'night_amel',
            'periodical|periodic' => 'periodic',
        ];
        
        foreach ($timePatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['time'][] = $tag;
                $modalities['raw_modality_score'] += 10;
            }
        }
        
        // POSITION/MOTION MODALITIES - critical for musculoskeletal
        $positionPatterns = [
            'worse.*rest|rest.*worse|after.*rest' => 'rest_agg',
            'worse.*motion|motion.*worse|movement.*worse' => 'motion_agg',
            'worse.*beginning.*motion|initial.*movement' => 'beginning_motion_agg',
            'worse.*continued.*motion|prolonged.*movement' => 'continued_motion_agg',
            'worse.*lying|lying.*worse' => 'lying_agg',
            'worse.*sitting|sitting.*worse' => 'sitting_agg',
            'worse.*standing|standing.*worse' => 'standing_agg',
            'worse.*bending' => 'bending_agg',
            'worse.*stooping' => 'stooping_agg',
            'worse.*walking|walking.*worse' => 'walking_agg',
            'worse.*climbing|stairs.*worse' => 'climbing_agg',
            
            'better.*rest|rest.*better|relief.*rest' => 'rest_amel',
            'better.*motion|motion.*better|relief.*motion' => 'motion_amel',
            'better.*continued.*motion|continued.*movement.*better' => 'continued_motion_amel',
            'better.*lying|lying.*better' => 'lying_amel',
            'better.*sitting' => 'sitting_amel',
            'better.*walking' => 'walking_amel',
            'better.*pressure|pressure.*better|firm.*pressure' => 'pressure_amel',
            'worse.*pressure|pressure.*worse' => 'pressure_agg',
            'better.*hard.*pressure' => 'hard_pressure_amel',
            'better.*rubbing|massage.*helps' => 'rubbing_amel',
        ];
        
        foreach ($positionPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['position'][] = $tag;
                $modalities['raw_modality_score'] += 20; // Position modalities very important
            }
        }
        
        // WEATHER MODALITIES
        $weatherPatterns = [
            'worse.*damp|damp.*worse|humidity.*worse' => 'damp_agg',
            'worse.*wet.*weather' => 'wet_weather_agg',
            'worse.*storm|before.*storm' => 'storm_agg',
            'worse.*change.*weather' => 'weather_change_agg',
            'worse.*cloudy' => 'cloudy_agg',
            'better.*dry.*weather' => 'dry_weather_amel',
        ];
        
        foreach ($weatherPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['weather'][] = $tag;
                $modalities['raw_modality_score'] += 10;
            }
        }
        
        // FOOD/DRINK MODALITIES
        $foodPatterns = [
            'thirstless|no.*thirst|without.*thirst' => 'thirstless',
            'thirst.*large|drinks.*large|gulps' => 'thirst_large_quantities',
            'thirst.*small.*sips|sips.*water' => 'thirst_small_sips',
            'worse.*eating|after.*eating|after.*food' => 'eating_agg',
            'better.*eating|eating.*better' => 'eating_amel',
            'worse.*fatty.*food|fat.*worse' => 'fat_agg',
            'worse.*spicy|spice.*worse' => 'spicy_agg',
            'worse.*coffee' => 'coffee_agg',
            'worse.*alcohol' => 'alcohol_agg',
            'desire.*cold.*drink' => 'cold_drinks_desire',
            'desire.*warm.*drink' => 'warm_drinks_desire',
            'aversion.*fat|fat.*aversion' => 'fat_aversion',
        ];
        
        foreach ($foodPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['food'][] = $tag;
                $modalities['raw_modality_score'] += 8;
            }
        }
        
        // MENTAL/EMOTIONAL MODALITIES
        $mentalPatterns = [
            'worse.*stress|stress.*worse' => 'stress_agg',
            'worse.*anxiety|anxiety.*worse' => 'anxiety_agg',
            'worse.*anger|anger.*worse|after.*anger' => 'anger_agg',
            'worse.*grief|grief.*worse|after.*grief' => 'grief_agg',
            'worse.*fright|after.*fright|shock' => 'fright_agg',
            'worse.*excitement' => 'excitement_agg',
            'better.*company|company.*better' => 'company_amel',
            'better.*alone|solitude' => 'alone_amel',
            'worse.*consolation|consolation.*aggrav' => 'consolation_agg',
            'better.*consolation' => 'consolation_amel',
        ];
        
        foreach ($mentalPatterns as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $modalities['mental'][] = $tag;
                $modalities['raw_modality_score'] += 12;
            }
        }
        
        return $modalities;
    }
    
    /**
     * Apply modality-based remedy scoring
     * Uses repertory-style modality matching to boost or penalize remedies
     */
    private function applyModalityScoring($modalities, $matches) {
        // Remedy modality profiles (based on materia medica keynotes)
        $remedyModalities = [
            // HOT REMEDIES - worse from heat/warmth
            'Sulphur' => ['thermal' => 'hot', 'warmth_agg' => true, 'warm_bed_agg' => true, 'night_agg' => true],
            'Pulsatilla Nigricans' => ['thermal' => 'hot', 'warmth_agg' => true, 'open_air_amel' => true, 'thirstless' => true, 'fat_agg' => true, 'consolation_amel' => true],
            'Apis Mellifica' => ['thermal' => 'hot', 'warmth_agg' => true, 'cold_application_amel' => true, 'thirstless' => true, 'pressure_agg' => true],
            'Lachesis Muta' => ['thermal' => 'hot', 'warmth_agg' => true, 'morning_agg' => true, 'pressure_agg' => true],
            'Iodum' => ['thermal' => 'hot', 'warmth_agg' => true, 'eating_amel' => true],
            
            // COLD REMEDIES - worse from cold, better from warmth  
            'Arsenicum Album' => ['thermal' => 'chilly', 'warmth_amel' => true, 'cold_agg' => true, 'night_agg' => true, 'midnight_agg' => true, 'thirst_small_sips' => true, 'company_amel' => true],
            'Nux Vomica' => ['thermal' => 'chilly', 'warmth_amel' => true, 'cold_agg' => true, 'morning_agg' => true, 'draft_agg' => true, 'coffee_agg' => true, 'alcohol_agg' => true, 'spicy_agg' => true, 'eating_agg' => true],
            'Hepar Sulphuris' => ['thermal' => 'chilly', 'warmth_amel' => true, 'cold_agg' => true, 'draft_agg' => true, 'pressure_agg' => true],
            'Silicea' => ['thermal' => 'chilly', 'warmth_amel' => true, 'cold_agg' => true],
            'Magnesia Phosphorica' => ['thermal' => 'chilly', 'warmth_amel' => true, 'warm_application_amel' => true, 'pressure_amel' => true, 'hard_pressure_amel' => true],
            'Calcarea Carbonica' => ['thermal' => 'chilly', 'warmth_amel' => true, 'cold_agg' => true, 'wet_weather_agg' => true],
            'Phosphorus' => ['thermal' => 'chilly', 'cold_drinks_desire' => true, 'company_amel' => true, 'storm_agg' => true],
            'Kali Carbonicum' => ['thermal' => 'chilly', 'early_morning_agg' => true, 'draft_agg' => true],
            
            // MOTION MODALITIES - critical for musculoskeletal
            'Rhus Toxicodendron' => ['rest_agg' => true, 'beginning_motion_agg' => true, 'continued_motion_amel' => true, 'motion_amel' => true, 'damp_agg' => true, 'cold_agg' => true, 'night_agg' => true, 'rubbing_amel' => true],
            'Bryonia Alba' => ['motion_agg' => true, 'rest_amel' => true, 'pressure_amel' => true, 'hard_pressure_amel' => true, 'warmth_amel' => true],
            'Ruta Graveolens' => ['rest_agg' => true, 'motion_amel' => true, 'lying_agg' => true],
            'Phytolacca Decandra' => ['motion_agg' => true, 'damp_agg' => true, 'night_agg' => true],
            
            // MENTAL STATE MODALITIES
            'Ignatia Amara' => ['grief_agg' => true, 'consolation_agg' => true, 'alone_amel' => true],
            'Natrum Muriaticum' => ['grief_agg' => true, 'consolation_agg' => true, 'alone_amel' => true, 'sun_agg' => true],
            'Staphisagria' => ['anger_agg' => true, 'grief_agg' => true],
            'Chamomilla' => ['anger_agg' => true, 'consolation_agg' => true],
            'Aconitum Napellus' => ['fright_agg' => true, 'cold_agg' => true, 'night_agg' => true],
            'Argentum Nitricum' => ['thermal' => 'hot', 'anxiety_agg' => true, 'warmth_agg' => true, 'anticipation_agg' => true],
            'Gelsemium Sempervirens' => ['thermal' => 'chilly', 'anxiety_agg' => true, 'fright_agg' => true, 'excitement_agg' => true, 'thirstless' => true, 'prostration' => true],
            
            // INSOMNIA-SPECIFIC REMEDY MODALITIES
            'Coffea Cruda' => ['thermal' => 'ambithermal', 'excitement_agg' => true, 'mental_activity_agg' => true, 'joy_agg' => true, 'noise_agg' => true],
            
            // DIGESTIVE MODALITIES
            'Lycopodium Clavatum' => ['afternoon_agg' => true, 'eating_agg' => true, 'warm_drinks_desire' => true],
            'China Officinalis' => ['periodic' => true, 'eating_agg' => true],
            'Carbo Vegetabilis' => ['eating_agg' => true, 'fat_agg' => true, 'open_air_amel' => true],
            'Robinia Pseudoacacia' => ['night_agg' => true, 'lying_agg' => true],
        ];
        
        // Calculate modality match scores
        $allPatientModalities = array_merge(
            $modalities['aggravations'],
            $modalities['ameliorations'],
            $modalities['time'],
            $modalities['position'],
            $modalities['weather'],
            $modalities['food'],
            $modalities['mental']
        );
        
        foreach ($matches as $key => &$match) {
            $remedyName = $match['remedy_name'];
            
            // Check if we have modality profile for this remedy
            if (isset($remedyModalities[$remedyName])) {
                $profile = $remedyModalities[$remedyName];
                $matchCount = 0;
                $mismatchCount = 0;
                
                // Check thermal match
                if ($modalities['thermal'] && isset($profile['thermal'])) {
                    if ($modalities['thermal'] === $profile['thermal']) {
                        $matchCount += 2; // Thermal match is important
                    } else {
                        $mismatchCount += 2; // Thermal mismatch is significant
                    }
                }
                
                // Check each patient modality against remedy profile
                foreach ($allPatientModalities as $patientMod) {
                    if (isset($profile[$patientMod]) && $profile[$patientMod]) {
                        $matchCount++;
                    }
                }
                
                // Apply bonuses/penalties
                if ($matchCount > 0) {
                    $modalityBonus = $matchCount * 15;
                    $match['score'] += $modalityBonus;
                    $match['modality_matches'] = $matchCount;
                }
                
                if ($mismatchCount > 0) {
                    $modalityPenalty = $mismatchCount * 0.15;
                    $match['score'] *= (1 - $modalityPenalty);
                    $match['modality_mismatches'] = $mismatchCount;
                }
            }
        }
        unset($match);
        
        return $matches;
    }
    
    /**
     * Calculate constitutional contradiction penalty
     * 
     * CRITICAL HOMEOPATHIC PRINCIPLE:
     * A remedy cannot be correct if its ESSENTIAL characteristics contradict
     * the patient's clear constitutional symptoms.
     * 
     * For example:
     * - Gelsemium (chilly, thirstless, prostration, dull mind) cannot match
     *   a patient who is HOT, THIRSTY, RESTLESS with RACING thoughts
     * - This is a fundamental mismatch of remedy "type" with patient "type"
     * 
     * @param string $remedyName The remedy to check
     * @param array $consultation The patient's consultation data
     * @return float Penalty factor (0.0 = no penalty, 0.8 = severe 80% penalty)
     */
    private function calculateConstitutionalContradiction($remedyName, $consultation) {
        // Constitutional profiles for key remedies
        // Format: 'characteristic' => ['matching_value', 'contradicting_values']
        $constitutionalProfiles = [
            // DEPRESSED/TORPID NERVOUS SYSTEM REMEDIES
            'Gelsemium Sempervirens' => [
                'thermal' => ['chilly', ['hot']],  // Chilly remedy - HOT patient contradicts
                'thirst' => ['thirstless', ['excessive', 'great']],  // Thirstless - excessive thirst contradicts
                'mental_type' => ['dull', ['racing', 'overactive', 'restless']],  // Dull mind - racing thoughts contradict
                'energy' => ['prostration', ['restless', 'agitated']],  // Weak/prostrated - restlessness contradicts
                'nervous_system' => ['depressed', ['excited', 'stimulated']],  // Depressed NS - excited state contradicts
            ],
            
            // EXCITED/STIMULATED NERVOUS SYSTEM REMEDIES
            'Coffea Cruda' => [
                'thermal' => ['ambithermal', []],  // No strong thermal
                'thirst' => ['normal', []],
                'mental_type' => ['excited', ['dull', 'slow']],  // Excited - dull contradicts
                'energy' => ['wakeful', ['drowsy', 'sleepy']],  // Wakeful - drowsy contradicts
                'nervous_system' => ['excited', ['depressed']],
            ],
            
            'Nux Vomica' => [
                'thermal' => ['chilly', ['hot']],  // Very chilly remedy
                'mental_type' => ['irritable', []],  // Irritable, overworked
                'energy' => ['oversensitive', ['dull']],  // Oversensitive - dull contradicts
                'nervous_system' => ['excited', ['depressed', 'torpid']],
            ],
            
            'Lachesis Muta' => [
                'thermal' => ['hot', ['chilly']],  // Hot remedy - chilly contradicts
                'sleep_aggravation' => ['worse_sleep', ['better_sleep']],  // Worse after sleep
                'mental_type' => ['loquacious', ['silent', 'taciturn']],
                'constriction' => ['cannot_bear', []],  // Cannot bear tight clothing
            ],
            
            'Sulphur' => [
                'thermal' => ['hot', ['chilly']],  // Very hot remedy
                'thirst' => ['thirsty', ['thirstless']],  // Usually thirsty
                'mental_type' => ['philosophical', []],
                'skin' => ['burning_itching', []],
            ],
            
            'Pulsatilla Nigricans' => [
                'thermal' => ['hot', ['chilly']],  // Hot, wants open air
                'thirst' => ['thirstless', ['excessive', 'great']],  // Thirstless
                'mental_type' => ['weepy', ['stoic']],  // Weepy, changeable
                'consolation' => ['better', ['worse']],  // Better from consolation
            ],
            
            'Arsenicum Album' => [
                'thermal' => ['chilly', ['hot']],  // Very chilly
                'thirst' => ['small_sips', ['large_quantities']],  // Small sips, not large gulps
                'mental_type' => ['anxious', ['calm', 'indifferent']],  // Anxious, fastidious
                'energy' => ['restless', ['calm']],  // Restless despite weakness
            ],
            
            'Bryonia Alba' => [
                'thermal' => ['ambithermal', []],
                'thirst' => ['excessive', ['thirstless']],  // Very thirsty for large quantities
                'mental_type' => ['irritable', []],
                'motion' => ['worse', ['better']],  // Worse from any motion
            ],
            
            'Argentum Nitricum' => [
                'thermal' => ['hot', ['chilly']],  // Hot patient
                'thirst' => ['normal', []],
                'mental_type' => ['anticipatory', ['calm']],  // Anticipatory anxiety
                'cravings' => ['sweets', []],  // Craves sweets
            ],
            
            'Phosphorus' => [
                'thermal' => ['chilly', ['hot']],  // Chilly but desires cold drinks
                'thirst' => ['cold_drinks', []],  // Craves cold drinks
                'mental_type' => ['sympathetic', ['indifferent']],
                'company' => ['desires', ['aversion']],  // Desires company
            ],
        ];
        
        $normalizedName = $this->normalizeRemedyName($remedyName);
        
        // Check if we have a profile for this remedy
        if (!isset($constitutionalProfiles[$normalizedName])) {
            return 0.0;  // No penalty if no profile
        }
        
        $profile = $constitutionalProfiles[$normalizedName];
        $totalPenalty = 0.0;
        $contradictionCount = 0;
        
        // Extract patient characteristics from consultation
        $patientThermal = strtolower($consultation['thermal_state'] ?? '');
        $patientThirst = strtolower($consultation['thirst'] ?? '');
        $patientMental = strtolower(($consultation['mental_state'] ?? '') . ' ' . ($consultation['general_symptoms'] ?? ''));
        $patientParticular = strtolower($consultation['particular_symptoms'] ?? '');
        $allText = $patientMental . ' ' . $patientParticular . ' ' . strtolower($consultation['chief_complaint'] ?? '');
        
        // Check THERMAL contradiction (most important)
        if (isset($profile['thermal']) && !empty($patientThermal)) {
            $expectedThermal = $profile['thermal'][0];
            $contradictingThermals = $profile['thermal'][1];
            
            if (in_array($patientThermal, $contradictingThermals)) {
                // Direct thermal contradiction - severe penalty
                $totalPenalty += 0.35;
                $contradictionCount++;
            }
        }
        
        // Check THIRST contradiction
        if (isset($profile['thirst']) && !empty($patientThirst)) {
            $contradictingThirsts = $profile['thirst'][1];
            
            foreach ($contradictingThirsts as $contraThirst) {
                if (strpos($patientThirst, $contraThirst) !== false) {
                    $totalPenalty += 0.20;
                    $contradictionCount++;
                    break;
                }
            }
        }
        
        // Check MENTAL TYPE contradiction (very important for nervous system remedies)
        if (isset($profile['mental_type'])) {
            $contradictingMentals = $profile['mental_type'][1];
            
            foreach ($contradictingMentals as $contraMental) {
                if (preg_match('/\b' . preg_quote($contraMental, '/') . '/i', $allText)) {
                    $totalPenalty += 0.25;
                    $contradictionCount++;
                    break;
                }
            }
        }
        
        // Check ENERGY/NERVOUS SYSTEM contradiction
        if (isset($profile['energy'])) {
            $contradictingEnergy = $profile['energy'][1];
            
            foreach ($contradictingEnergy as $contraEnergy) {
                if (preg_match('/\b' . preg_quote($contraEnergy, '/') . '/i', $allText)) {
                    $totalPenalty += 0.20;
                    $contradictionCount++;
                    break;
                }
            }
        }
        
        // Cap the penalty at 80% reduction
        return min($totalPenalty, 0.80);
    }
    
    /**
     * Normalize remedy name to standard format
     */
    private function normalizeRemedyName($name) {
        $name = trim($name);
        
        // Common name mappings
        $mappings = [
            'gelsemium' => 'Gelsemium Sempervirens',
            'coffea' => 'Coffea Cruda',
            'nux vom' => 'Nux Vomica',
            'lachesis' => 'Lachesis Muta',
            'pulsatilla' => 'Pulsatilla Nigricans',
            'arsenicum' => 'Arsenicum Album',
            'ars alb' => 'Arsenicum Album',
            'bryonia' => 'Bryonia Alba',
            'arg nit' => 'Argentum Nitricum',
            'phosphorus' => 'Phosphorus',
            'sulphur' => 'Sulphur',
        ];
        
        $lowerName = strtolower($name);
        foreach ($mappings as $partial => $full) {
            if (strpos($lowerName, $partial) !== false) {
                return $full;
            }
        }
        
        // Return with proper capitalization if no mapping found
        return ucwords(strtolower($name));
    }
    
    /**
     * Categorize symptoms into Physical vs Mental for dual-weight scoring
     * 
     * HOMEOPATHIC SYMPTOM HIERARCHY:
     * Mental/Emotional symptoms are considered most important (PQRS symptoms)
     * but physical symptoms give us the "totality" of the case.
     * 
     * Weighting: Physical 60-70%, Mental 30-40% depending on clarity
     */
    private function categorizeSymptoms($symptoms, $consultation = []) {
        $categories = [
            'mental' => [],
            'physical' => [],
            'general' => [],     // Constitution, thermal state, etc.
            'particular' => [],  // Local symptoms
            'mental_weight' => 0.35,  // Default mental weight
            'physical_weight' => 0.65, // Default physical weight
        ];
        
        $mentalKeywords = [
            'anxiety', 'anxious', 'fear', 'afraid', 'panic', 'worry', 'worries',
            'depression', 'depressed', 'sad', 'sadness', 'grief', 'weeping', 'crying',
            'anger', 'angry', 'irritable', 'irritability', 'rage', 'impatient',
            'restless', 'restlessness', 'nervous', 'nervousness',
            'confidence', 'shy', 'timid', 'stage fright', 'anticipation',
            'jealous', 'jealousy', 'suspicious', 'mistrust',
            'concentration', 'memory', 'confusion', 'mental fog',
            'mood', 'emotional', 'feelings', 'mental state', 'mind',
            'insomnia', 'sleeplessness', 'nightmares', 'dreams',
            'obsessive', 'compulsive', 'phobia', 'aversion',
            'company', 'alone', 'solitude', 'consolation'
        ];
        
        $generalKeywords = [
            'thermal', 'chilly', 'hot patient', 'temperature',
            'thirst', 'thirsty', 'thirstless', 'appetite',
            'perspiration', 'sweat', 'sweating',
            'sleep', 'energy', 'fatigue', 'weakness', 'tired',
            'desires', 'cravings', 'aversions'
        ];
        
        // Check consultation mental state field
        if (!empty($consultation['mental_state'])) {
            $categories['mental'][] = $consultation['mental_state'];
            // If clear mental symptoms, increase mental weight
            if (strlen($consultation['mental_state']) > 50) {
                $categories['mental_weight'] = 0.40;
                $categories['physical_weight'] = 0.60;
            }
        }
        
        // Categorize each symptom
        foreach ($symptoms as $symptom) {
            $symptomText = strtolower($symptom['symptom'] ?? $symptom['symptom_text'] ?? '');
            $location = strtolower($symptom['location'] ?? '');
            
            $isMental = false;
            $isGeneral = false;
            
            // Check for mental keywords
            foreach ($mentalKeywords as $keyword) {
                if (strpos($symptomText, $keyword) !== false) {
                    $isMental = true;
                    break;
                }
            }
            
            // Check for general symptoms
            if (!$isMental) {
                foreach ($generalKeywords as $keyword) {
                    if (strpos($symptomText, $keyword) !== false) {
                        $isGeneral = true;
                        break;
                    }
                }
            }
            
            if ($isMental) {
                $categories['mental'][] = $symptom;
            } elseif ($isGeneral) {
                $categories['general'][] = $symptom;
            } elseif (!empty($location)) {
                $categories['particular'][] = $symptom;
            } else {
                $categories['physical'][] = $symptom;
            }
        }
        
        // Adjust weights based on symptom distribution
        $mentalCount = count($categories['mental']);
        $physicalCount = count($categories['physical']) + count($categories['particular']);
        
        if ($mentalCount >= 3 && $physicalCount < 3) {
            // Predominantly mental case
            $categories['mental_weight'] = 0.45;
            $categories['physical_weight'] = 0.55;
        } elseif ($physicalCount >= 5 && $mentalCount < 2) {
            // Predominantly physical case  
            $categories['mental_weight'] = 0.30;
            $categories['physical_weight'] = 0.70;
        }
        
        return $categories;
    }
    
    /**
     * Extract causation factors (etiology) from consultation
     * 
     * HOMEOPATHIC CAUSATION (Ailments From):
     * - Emotional: grief, anger, fright, disappointment
     * - Physical: injury, exposure, overexertion
     * - Lifestyle: sedentary, diet, work stress
     */
    private function extractCausationFactors($text, $consultation = []) {
        $causations = [
            'emotional' => [],
            'physical' => [],
            'lifestyle' => [],
            'onset' => null,
            'causation_score' => 0
        ];
        
        $text = strtolower($text);
        
        // Add consultation history
        if (!empty($consultation['history'])) {
            $text .= ' ' . strtolower($consultation['history']);
        }
        if (!empty($consultation['medical_history'])) {
            $text .= ' ' . strtolower($consultation['medical_history']);
        }
        
        // EMOTIONAL CAUSATIONS - very important in homeopathy
        $emotionalCausations = [
            'grief|loss|bereavement|death.*relative|died' => 'grief',
            'anger|rage|vexation|indignation' => 'anger',
            'fright|shock|fear|scared|frightened' => 'fright',
            'disappointment|let.*down|failed' => 'disappointment',
            'humiliation|insulted|mortification' => 'humiliation',
            'anxiety.*about|worry.*about|concerned.*about' => 'anxiety',
            'jealousy|betrayal' => 'jealousy',
            'suppressed.*emotion|held.*in|bottled.*up' => 'suppressed_emotions',
            'after.*argument|fight.*with|quarrel' => 'anger',
        ];
        
        foreach ($emotionalCausations as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $causations['emotional'][] = $tag;
                $causations['causation_score'] += 20; // Emotional causation is significant
            }
        }
        
        // PHYSICAL CAUSATIONS
        $physicalCausations = [
            'injury|trauma|accident|fall|blow' => 'injury',
            'exposure.*cold|caught.*cold|got.*wet' => 'cold_exposure',
            'overexertion|overwork|strained' => 'overexertion',
            'surgery|operation|post.*operative' => 'surgery',
            'vaccination|after.*vaccine' => 'vaccination',
            'medication|drug|side.*effect' => 'medication',
            'infection|viral|bacterial|after.*flu' => 'infection',
            'pregnancy|childbirth|postpartum|delivery' => 'childbirth',
            'menopause|hormonal.*change' => 'hormonal',
        ];
        
        foreach ($physicalCausations as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $causations['physical'][] = $tag;
                $causations['causation_score'] += 15;
            }
        }
        
        // LIFESTYLE CAUSATIONS - modern relevance
        $lifestyleCausations = [
            'sedentary|desk.*job|sitting.*all.*day|computer.*work' => 'sedentary',
            'stress.*work|work.*stress|job.*stress|workplace' => 'work_stress',
            'night.*shift|irregular.*hours|shift.*work' => 'irregular_hours',
            'poor.*diet|junk.*food|irregular.*meals' => 'diet',
            'alcohol|drinking|alcoholic' => 'alcohol',
            'smoking|smoker|tobacco' => 'smoking',
            'lack.*sleep|sleep.*deprivation|insomnia' => 'sleep_deprivation',
            'financial.*stress|money.*problem' => 'financial_stress',
            'relationship.*problem|marital|divorce' => 'relationship_stress',
            'travel|jetlag|frequent.*travel' => 'travel',
        ];
        
        foreach ($lifestyleCausations as $pattern => $tag) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $causations['lifestyle'][] = $tag;
                $causations['causation_score'] += 10;
            }
        }
        
        // ONSET TYPE
        if (preg_match('/sudden|suddenly|acute|rapid/', $text)) {
            $causations['onset'] = 'sudden';
        } elseif (preg_match('/gradual|slowly|over.*time|progressive/', $text)) {
            $causations['onset'] = 'gradual';
        }
        
        return $causations;
    }
    
    /**
     * Apply causation-based remedy scoring
     * Remedies have specific "ailments from" profiles
     */
    private function applyCausationScoring($causations, $matches) {
        // Remedy causation profiles (Ailments From)
        $remedyCausations = [
            // Grief remedies
            'Ignatia Amara' => ['grief' => 25, 'disappointment' => 20, 'suppressed_emotions' => 20],
            'Natrum Muriaticum' => ['grief' => 25, 'disappointment' => 15, 'humiliation' => 15],
            'Phosphoric Acidum' => ['grief' => 25, 'disappointment' => 20],
            'Aurum Metallicum' => ['grief' => 20, 'disappointment' => 25, 'humiliation' => 20],
            
            // Anger remedies
            'Staphisagria' => ['anger' => 25, 'suppressed_emotions' => 30, 'humiliation' => 25],
            'Chamomilla' => ['anger' => 20],
            'Colocynthis' => ['anger' => 25, 'suppressed_emotions' => 20],
            'Nux Vomica' => ['anger' => 15, 'work_stress' => 20, 'alcohol' => 15, 'sedentary' => 15, 'spicy_food' => 10],
            
            // Fright/shock remedies
            'Aconitum Napellus' => ['fright' => 30, 'cold_exposure' => 20],
            'Opium' => ['fright' => 25],
            'Argentum Nitricum' => ['anxiety' => 20, 'fright' => 15],
            'Gelsemium Sempervirens' => ['fright' => 20, 'anxiety' => 20],
            
            // Physical causation remedies
            'Arnica Montana' => ['injury' => 30, 'overexertion' => 25],
            'Rhus Toxicodendron' => ['overexertion' => 20, 'cold_exposure' => 15, 'injury' => 15],
            'Bryonia Alba' => ['cold_exposure' => 15],
            
            // Hormonal/female remedies
            'Sepia Succus' => ['childbirth' => 25, 'hormonal' => 25, 'overexertion' => 15],
            'Pulsatilla Nigricans' => ['hormonal' => 20, 'grief' => 15],
            'Lachesis Muta' => ['hormonal' => 20],
            
            // Lifestyle-related
            'Kali Phosphoricum' => ['work_stress' => 20, 'sleep_deprivation' => 20],
            'Phosphorus' => ['work_stress' => 15],
            'China Officinalis' => ['overexertion' => 15],
        ];
        
        $allCausations = array_merge(
            $causations['emotional'],
            $causations['physical'],
            $causations['lifestyle']
        );
        
        if (empty($allCausations)) {
            return $matches;
        }
        
        foreach ($matches as $key => &$match) {
            $remedyName = $match['remedy_name'];
            
            if (isset($remedyCausations[$remedyName])) {
                $profile = $remedyCausations[$remedyName];
                
                foreach ($allCausations as $causation) {
                    if (isset($profile[$causation])) {
                        $match['score'] += $profile[$causation];
                        $match['causation_matches'][] = $causation;
                    }
                }
            }
        }
        unset($match);
        
        return $matches;
    }

    /**
     * Hybrid keyword search for specific symptom combinations
     * This supplements vector search by finding exact clinical symptom matches
     * 
     * @param string $chiefComplaint
     * @param array $symptoms
     * @return array Matching remedies with scores
     */
    private function hybridKeywordSearch($chiefComplaint, $symptoms, $consultation = []) {
        $matches = [];
        $text = strtolower($chiefComplaint);
        
        // CRITICAL: Add diagnosis FIRST with high priority
        // For follow-up consultations, the diagnosis is the most important indicator
        if (!empty($consultation['diagnosis'])) {
            $diagnosis = strtolower($consultation['diagnosis']);
            // Add diagnosis text multiple times to give it more weight in term extraction
            $text .= ' diagnosis: ' . $diagnosis . ' condition: ' . $diagnosis;
        }
        
        // Process mental state carefully - extract symptoms but not improvement notes
        $mentalState = strtolower($consultation['mental_state'] ?? '');
        if (!empty($mentalState)) {
            // Filter out improvement indicators - these describe PAST symptoms, not current
            $mentalState = $this->filterImprovementContext($mentalState);
        }
        
        // Add symptoms to search text - include all relevant fields
        foreach ($symptoms as $symptom) {
            $symptomText = $symptom['symptom'] ?? $symptom['symptom_text'] ?? '';
            $text .= ' ' . strtolower($symptomText);
            
            // Include modality which often contains key clinical info
            if (!empty($symptom['modality'])) {
                $text .= ' ' . strtolower($symptom['modality']);
            }
            // Include location
            if (!empty($symptom['location'])) {
                $text .= ' ' . strtolower($symptom['location']);
            }
            // Include sensation
            if (!empty($symptom['sensation'])) {
                $text .= ' ' . strtolower($symptom['sensation']);
            }
        }
        
        // CRITICAL: Include general_symptoms and particular_symptoms text fields
        // These contain the actual clinical narrative from the consultation form
        if (!empty($consultation['general_symptoms'])) {
            $text .= ' ' . strtolower($consultation['general_symptoms']);
        }
        if (!empty($consultation['particular_symptoms'])) {
            $text .= ' ' . strtolower($consultation['particular_symptoms']);
        }
        
        // Include consultation-level modalities and constitutional info
        if (!empty($consultation['aggravation'])) {
            $text .= ' aggravation ' . strtolower($consultation['aggravation']);
        }
        if (!empty($consultation['amelioration'])) {
            $text .= ' amelioration better ' . strtolower($consultation['amelioration']);
        }
        if (!empty($consultation['thermal_state'])) {
            $text .= ' ' . strtolower($consultation['thermal_state']);
        }
        // Mental state is processed earlier with improvement filtering
        if (!empty($mentalState)) {
            $text .= ' ' . $mentalState;
        }
        
        // First, apply clinical polychrest mappings for specific symptom patterns
        $matches = $this->applyClinicalPolychrests($text, $consultation);
        
        // Extract key clinical terms from the text
        $searchTerms = $this->extractClinicalTerms($text);
        
        if (empty($searchTerms)) {
            return $matches;
        }
        
        // Build SQL search for specific symptom combinations
        foreach ($searchTerms as $term) {
            $termPattern = '%' . $term . '%';
            
            // Search in keynote symptoms and clinical indications (most important)
            $sql = "SELECT id, remedy_name, common_name, keynote_symptoms, clinical_indications, 
                           mind_symptoms, book_reference
                    FROM remedies 
                    WHERE LOWER(keynote_symptoms) LIKE ? 
                       OR LOWER(clinical_indications) LIKE ?
                       OR LOWER(mind_symptoms) LIKE ?
                       OR LOWER(characteristic_symptoms) LIKE ?
                    LIMIT 20";
            
            $results = DB::query($sql, [$termPattern, $termPattern, $termPattern, $termPattern]);
            
            foreach ($results as $remedy) {
                $key = strtolower($remedy['remedy_name']);
                $score = 1;
                
                // Higher scores for matches in more important fields
                if (stripos($remedy['keynote_symptoms'] ?? '', $term) !== false) {
                    $score += 5;
                }
                if (stripos($remedy['clinical_indications'] ?? '', $term) !== false) {
                    $score += 4;
                }
                if (stripos($remedy['mind_symptoms'] ?? '', $term) !== false) {
                    $score += 3;
                }
                
                if (!isset($matches[$key])) {
                    $matches[$key] = [
                        'id' => $remedy['id'],
                        'remedy_name' => $remedy['remedy_name'],
                        'common_name' => $remedy['common_name'] ?? '',
                        'score' => 0,
                        'matched_term' => $term,
                        'keynote_symptoms' => $remedy['keynote_symptoms'] ?? '',
                        'book_reference' => $remedy['book_reference'] ?? ''
                    ];
                }
                
                $matches[$key]['score'] += $score;
            }
        }
        
        // Also search repertory for specific rubrics
        $repertoryMatches = $this->searchRepertoryKeywords($searchTerms);
        foreach ($repertoryMatches as $match) {
            $key = strtolower($match['remedy_name']);
            if (!isset($matches[$key])) {
                $matches[$key] = [
                    'id' => $match['remedy_id'] ?? 0,
                    'remedy_name' => $match['remedy_name'],
                    'common_name' => '',
                    'score' => 0,
                    'matched_term' => $match['rubric'],
                    'keynote_symptoms' => '',
                    'book_reference' => ''
                ];
            }
            // Repertory matches are highly specific
            $matches[$key]['score'] += $match['grade'] * 3;
        }
        
        // Sort by score
        uasort($matches, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_values($matches);
    }
    
    /**
     * Filter improvement context from symptom text
     * Removes phrases that indicate past/resolved symptoms (e.g., "less irritable", "mood improved")
     * This prevents the RAG from suggesting remedies based on IMPROVED (past) symptoms
     */
    private function filterImprovementContext($text) {
        // Patterns that indicate improvement - we want to REMOVE the symptom word after these
        $improvementPatterns = [
            '/less\s+(\w+)/i' => '', // "less irritable" -> remove
            '/no\s+longer\s+(\w+)/i' => '', // "no longer anxious"
            '/(\w+)\s+improved/i' => '', // "mood improved"
            '/(\w+)\s+better/i' => '', // "sleep better"
            '/(\w+)\s+resolved/i' => '', // "pain resolved"
            '/(\w+)\s+gone/i' => '', // "headache gone"
            '/reduced\s+(\w+)/i' => '', // "reduced anxiety"
            '/decreasing\s+(\w+)/i' => '', // "decreasing pain"
            '/improving\s+(\w+)/i' => '', // "improving energy"
            '/improvement\s+in\s+(\w+)/i' => '', // "improvement in sleep"
            '/not\s+as\s+(\w+)/i' => '', // "not as tired"
        ];
        
        foreach ($improvementPatterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        return trim($text);
    }
    
    /**
     * Extract clinical terms from symptom text
     * Maps common phrases to clinical/homeopathic terminology
     */
    private function extractClinicalTerms($text) {
        $terms = [];
        
        // Clinical term mappings - common phrases to homeopathic terms
        $mappings = [
            // Cyanosis / circulation
            'blue lip' => ['cyanosis', 'blue', 'lips blue', 'face blue', 'circulation'],
            'blue face' => ['cyanosis', 'blue face', 'face bluish', 'circulation'],
            'blue skin' => ['cyanosis', 'blue', 'bluish', 'circulation'],
            'turns blue' => ['cyanosis', 'blue', 'bluish', 'circulation'],
            
            // Anger / emotional
            'angry' => ['anger', 'irritable', 'irritability', 'rage', 'vexation'],
            'anger' => ['anger', 'irritable', 'irritability', 'rage', 'vexation', 'indignation'],
            'rage' => ['rage', 'fury', 'violent', 'anger'],
            'mood swing' => ['mood', 'changeable', 'alternating', 'capricious'],
            'irritable' => ['irritable', 'irritability', 'cross', 'peevish'],
            
            // Physical symptoms from emotions
            'from anger' => ['ailments from anger', 'anger agg', 'vexation'],
            'from fright' => ['ailments from fright', 'fear'],
            'from grief' => ['ailments from grief', 'disappointed love'],
            'from emotion' => ['emotions', 'emotional', 'hysteria'],
            
            // Specific combinations (very valuable)
            'blue.*anger' => ['cyanosis anger', 'face blue anger', 'ailments anger'],
            'lip.*blue' => ['lips blue', 'cyanosis lips'],
            
            // Respiratory from emotions
            'cant breathe.*angry' => ['dyspnea anger', 'suffocation emotion'],
            'breath.*anger' => ['breathing anger', 'respiration emotional'],
            
            // Spasms from emotions
            'spasm' => ['spasm', 'convulsion', 'cramping'],
            'convulsion' => ['convulsion', 'spasm', 'fit'],
            
            // HEADACHE - location specific
            'temple' => ['temples', 'temporal', 'headache temples'],
            'temples' => ['temples', 'temporal', 'headache temples', 'bilateral temples'],
            'occiput' => ['occiput', 'occipital', 'back of head', 'headache occiput'],
            'forehead' => ['forehead', 'frontal', 'headache frontal'],
            'vertex' => ['vertex', 'top of head'],
            'one side' => ['unilateral', 'hemicrania', 'one-sided'],
            
            // HEADACHE - sensation specific
            'pulling' => ['drawing', 'pulling', 'tensive'],
            'throbbing' => ['throbbing', 'pulsating', 'hammering'],
            'bursting' => ['bursting', 'splitting', 'pressing outward'],
            'pressing' => ['pressing', 'pressure', 'constricting'],
            'stitching' => ['stitching', 'stabbing', 'shooting'],
            
            // HEADACHE - modalities
            'evening' => ['evening', 'worse evening', 'agg evening', 'afternoon'],
            'morning' => ['morning', 'worse morning', 'agg morning', 'on waking'],
            'motion' => ['motion', 'movement', 'worse motion'],
            'rest' => ['rest', 'lying down', 'better rest'],
            'heat' => ['heat', 'warmth', 'worse heat', 'hot'],
            'cold' => ['cold', 'worse cold', 'better cold'],
            
            // WEAKNESS / FATIGUE
            'tired' => ['fatigue', 'exhaustion', 'weakness', 'tired'],
            'weakness' => ['weakness', 'prostration', 'debility'],
            'cant walk' => ['walking difficult', 'cannot walk', 'gait', 'staggering'],
            'not able to walk' => ['walking difficult', 'cannot walk', 'paralysis', 'weakness legs'],
            'balance' => ['balance', 'vertigo', 'staggering', 'unsteady'],
            'vertigo' => ['vertigo', 'dizziness', 'giddiness'],
            'dizziness' => ['vertigo', 'dizziness', 'swimming'],
            'suddenly' => ['sudden', 'acute', 'violent onset'],
            'sudden' => ['sudden', 'acute', 'violent'],
        ];
        
        // Check for mapped terms
        foreach ($mappings as $trigger => $clinicalTerms) {
            // Handle regex patterns
            if (strpos($trigger, '.*') !== false) {
                if (preg_match('/' . $trigger . '/i', $text)) {
                    $terms = array_merge($terms, $clinicalTerms);
                }
            } else if (strpos($text, $trigger) !== false) {
                $terms = array_merge($terms, $clinicalTerms);
            }
        }
        
        // Extract individual significant words
        $significantWords = [
            // Emotional/mental
            'blue', 'cyanosis', 'anger', 'angry', 'rage', 'fury', 'vexation',
            'irritable', 'mood', 'changeable', 'hysteria', 'spasm', 'convulsion',
            'suffocation', 'dyspnea', 'palpitation', 'trembling', 'jealousy',
            'grief', 'disappointed', 'indignation', 'suppressed',
            // Headache related
            'headache', 'temples', 'temple', 'occiput', 'occipital', 'forehead',
            'vertex', 'pulling', 'throbbing', 'pressing', 'bursting', 'stitching',
            'hemicrania', 'migraine', 'cephalalgia',
            // Modalities
            'evening', 'morning', 'night', 'afternoon',
            // Weakness/fatigue
            'weakness', 'tired', 'fatigue', 'exhaustion', 'prostration',
            'vertigo', 'dizziness', 'balance', 'staggering', 'unsteady',
            'paralysis', 'numbness', 'trembling',
        ];
        
        foreach ($significantWords as $word) {
            if (strpos($text, $word) !== false) {
                $terms[] = $word;
            }
        }
        
        return array_unique($terms);
    }
    
    /**
     * Search repertory using keywords
     */
    private function searchRepertoryKeywords($terms) {
        $matches = [];
        
        foreach ($terms as $term) {
            if (strlen($term) < 3) continue;
            
            $sql = "SELECT r.rubric, rr.grade, rr.remedy_id, rem.remedy_name
                    FROM repertory r
                    INNER JOIN repertory_remedies rr ON r.id = rr.repertory_id
                    INNER JOIN remedies rem ON rr.remedy_id = rem.id
                    WHERE LOWER(r.rubric) LIKE ? OR LOWER(r.complete_rubric) LIKE ?
                    ORDER BY rr.grade DESC
                    LIMIT 30";
            
            $results = DB::query($sql, ['%' . $term . '%', '%' . $term . '%']);
            
            foreach ($results as $row) {
                $matches[] = [
                    'remedy_name' => $row['remedy_name'],
                    'remedy_id' => $row['remedy_id'],
                    'rubric' => $row['rubric'],
                    'grade' => (int)$row['grade']
                ];
            }
        }
        
        return $matches;
    }
    
    /**
     * Apply clinical polychrest mappings for specific symptom patterns
     * These are evidence-based remedy-symptom correlations from clinical practice
     */
    private function applyClinicalPolychrests($text, $consultation = []) {
        $matches = [];
        
        // ALSO check diagnosis directly with higher weight
        $diagnosisText = strtolower($consultation['diagnosis'] ?? '');
        
        // Clinical polychrest mappings - symptom pattern => remedies with scores
        $clinicalMappings = [
            // DIAGNOSIS-BASED patterns - HIGHEST PRIORITY for follow-up consultations
            // These should override incidental symptom matches
            'chronic.*migraine|migraine.*chronic|migraine.*improving|migraine.*follow' => [
                'Natrum Muriaticum' => 80, 'Iris Versicolor' => 70, 'Sanguinaria Canadensis' => 65,
                'Gelsemium Sempervirens' => 55, 'Spigelia Anthelmia' => 50, 'Belladonna' => 45
            ],
            'migraine.*aura|aura.*migraine' => [
                'Natrum Muriaticum' => 75, 'Iris Versicolor' => 70, 'Cyclamen Europaeum' => 55,
                'Kali Bichromicum' => 50, 'Gelsemium Sempervirens' => 45
            ],
            
            // HEADACHE patterns
            'temple.*occiput|occiput.*temple|temples.*occiput|occiput.*temples' => [
                'Gelsemium Sempervirens' => 30, 'Spigelia Anthelmia' => 20, 
                'Sanguinaria Canadensis' => 12, 'Natrum Muriaticum' => 10
            ],
            'temples to occiput|temple to occiput' => [
                'Gelsemium Sempervirens' => 35, 'Spigelia Anthelmia' => 15,
                'Sanguinaria Canadensis' => 10, 'Natrum Muriaticum' => 8
            ],
            'temple' => [
                'Spigelia Anthelmia' => 12, 'Belladonna' => 10, 'Iris Versicolor' => 8,
                'Lachesis Muta' => 7, 'Gelsemium Sempervirens' => 8
            ],
            'occiput' => [
                'Gelsemium Sempervirens' => 12, 'Silicea' => 10, 'Petroleum' => 8,
                'Natrum Muriaticum' => 8, 'Spigelia Anthelmia' => 7
            ],
            'pulling.*head|head.*pulling' => [
                'Gelsemium Sempervirens' => 12, 'Spigelia Anthelmia' => 10, 
                'Pulsatilla Nigricans' => 8, 'China Officinalis' => 7
            ],
            'evening.*worse|worse.*evening|more.*evening' => [
                'Pulsatilla Nigricans' => 10, 'Phosphorus' => 9, 'Sulphur' => 8,
                'Lachesis Muta' => 7, 'Belladonna' => 6
            ],
            'throbbing.*head|head.*throbbing' => [
                'Belladonna' => 15, 'Glonoinum' => 12, 'Natrum Muriaticum' => 10
            ],
            
            // MIGRAINE patterns - specific clinical mappings
            'migraine|hemicrania' => [
                'Iris Versicolor' => 55, 'Sanguinaria Canadensis' => 50, 'Natrum Muriaticum' => 50,
                'Gelsemium Sempervirens' => 40, 'Spigelia Anthelmia' => 40, 'Belladonna' => 35
            ],
            'migraine.*aura|aura.*migraine|visual.*aura|aura.*visual' => [
                'Iris Versicolor' => 20, 'Natrum Muriaticum' => 18, 'Cyclamen Europaeum' => 14,
                'Kali Bichromicum' => 12, 'Gelsemium Sempervirens' => 10
            ],
            'zigzag|blind.*spot|scotoma|visual.*disturbance' => [
                'Natrum Muriaticum' => 18, 'Iris Versicolor' => 16, 'Cyclamen Europaeum' => 14,
                'Phosphorus' => 10
            ],
            'vomiting.*relief|relief.*vomiting|better.*vomiting' => [
                'Iris Versicolor' => 20, 'Sanguinaria Canadensis' => 16, 'Digitalis Purpurea' => 10,
                'Veratrum Album' => 8
            ],
            'one.*side.*head|unilateral.*head|left.*side.*head' => [
                'Spigelia Anthelmia' => 15, 'Lachesis Muta' => 14, 'Sanguinaria Canadensis' => 12,
                'Iris Versicolor' => 12, 'Natrum Muriaticum' => 10
            ],
            'right.*side.*head' => [
                'Sanguinaria Canadensis' => 18, 'Iris Versicolor' => 14, 'Belladonna' => 12,
                'Lycopodium Clavatum' => 10
            ],
            'premenstrual.*head|head.*premenstrual|menses.*head|head.*menses|period.*head' => [
                'Natrum Muriaticum' => 20, 'Sepia' => 16, 'Pulsatilla Nigricans' => 14,
                'Lachesis Muta' => 12, 'Kreosotum' => 10
            ],
            'photophobia|light.*agg|worse.*light|sensitive.*light' => [
                'Belladonna' => 15, 'Natrum Muriaticum' => 14, 'Phosphorus' => 12,
                'Nux Vomica' => 10, 'Silicea' => 10
            ],
            'nausea.*headache|headache.*nausea' => [
                'Iris Versicolor' => 16, 'Ipecacuanha' => 14, 'Cocculus Indicus' => 12,
                'Sanguinaria Canadensis' => 12, 'Natrum Muriaticum' => 10
            ],
            'pulsating.*head|head.*pulsating' => [
                'Belladonna' => 16, 'Glonoinum' => 14, 'Natrum Muriaticum' => 12,
                'Lachesis Muta' => 10, 'Iris Versicolor' => 10
            ],
            
            // TENSION HEADACHE patterns - HIGH PRIORITY for stress-related cases
            'tension.*headache|tension.*type.*headache|stress.*headache|headache.*stress' => [
                'Nux Vomica' => 55, 'Bryonia Alba' => 50, 'Gelsemium Sempervirens' => 45,
                'Natrum Muriaticum' => 40, 'Cimicifuga Racemosa' => 35, 'Ignatia Amara' => 30
            ],
            'neck.*stiff.*headache|headache.*neck.*stiff|stiff.*neck.*head' => [
                'Gelsemium Sempervirens' => 55, 'Cimicifuga Racemosa' => 50, 'Bryonia Alba' => 45,
                'Nux Vomica' => 40, 'Rhus Toxicodendron' => 30, 'Lachnanthes Tinctoria' => 25
            ],
            'neck.*stiff|stiff.*neck|neck.*rigid|cervical.*stiff' => [
                'Gelsemium Sempervirens' => 45, 'Cimicifuga Racemosa' => 40, 'Lachnanthes Tinctoria' => 35,
                'Rhus Toxicodendron' => 30, 'Bryonia Alba' => 25, 'Causticum' => 20
            ],
            'headache.*worse.*motion|worse.*motion.*head|motion.*agg.*head' => [
                'Bryonia Alba' => 60, 'Gelsemium Sempervirens' => 35, 'Spigelia Anthelmia' => 30,
                'Belladonna' => 25, 'Nux Vomica' => 20
            ],
            'headache.*better.*pressure|pressure.*better.*head|better.*pressing|tight.*band.*relief' => [
                'Bryonia Alba' => 55, 'Argentum Nitricum' => 30, 'Magnesium Phosphoricum' => 25,
                'Pulsatilla Nigricans' => 20
            ],
            'headache.*constipation|constipation.*headache' => [
                'Bryonia Alba' => 50, 'Nux Vomica' => 45, 'Natrum Muriaticum' => 35,
                'Opium' => 25, 'Alumina' => 20
            ],
            
            // WORK STRESS / OCCUPATIONAL patterns - HIGH PRIORITY
            'work.*stress|stress.*work|job.*stress|deadline|overwork|office.*stress|occupational' => [
                'Nux Vomica' => 60, 'Bryonia Alba' => 45, 'Kali Phosphoricum' => 40,
                'Phosphoric Acid' => 35, 'Natrum Muriaticum' => 30, 'Ignatia Amara' => 25
            ],
            'screen.*time|computer.*strain|eye.*strain.*head|monitor|digital.*strain' => [
                'Ruta Graveolens' => 50, 'Natrum Muriaticum' => 40, 'Phosphorus' => 35,
                'Nux Vomica' => 30, 'Gelsemium Sempervirens' => 25
            ],
            'sedentary|sitting.*long|desk.*work|office.*work' => [
                'Nux Vomica' => 45, 'Bryonia Alba' => 35, 'Sulphur' => 30,
                'Calcarea Carbonica' => 25, 'Sepia' => 20
            ],
            'mental.*fatigue|mental.*exhaustion|brain.*fag|overwork.*mental|intellectual.*strain' => [
                'Kali Phosphoricum' => 55, 'Phosphoric Acid' => 45, 'Nux Vomica' => 40,
                'Anacardium Orientale' => 35, 'Picric Acid' => 30, 'Silicea' => 25
            ],
            'difficulty.*concentrat|poor.*concentrat|cant.*focus|focus.*problem|attention.*difficult' => [
                'Kali Phosphoricum' => 45, 'Phosphoric Acid' => 40, 'Anacardium Orientale' => 35,
                'Nux Vomica' => 30, 'Lycopodium Clavatum' => 25, 'Baryta Carbonica' => 20
            ],
            'irritable.*work|work.*irritab|business.*worries|business.*anxiety' => [
                'Nux Vomica' => 55, 'Bryonia Alba' => 45, 'Lycopodium Clavatum' => 35,
                'Arsenicum Album' => 30, 'Kali Carbonicum' => 25
            ],
            'irritable.*family|family.*irritab|snappy.*home|cross.*home' => [
                'Nux Vomica' => 50, 'Sepia' => 40, 'Chamomilla' => 35,
                'Kali Carbonicum' => 30, 'Lycopodium Clavatum' => 25
            ],
            
            // CHILLY + HEADACHE combination
            'chilly.*headache|headache.*chilly|cold.*headache' => [
                'Nux Vomica' => 45, 'Silicea' => 40, 'Arsenicum Album' => 35,
                'Calcarea Carbonica' => 30, 'Psorinum' => 25
            ],
            'chilly.*warm.*food|desire.*warm.*food|warm.*drink.*desire' => [
                'Arsenicum Album' => 40, 'Nux Vomica' => 35, 'Calcarea Carbonica' => 30,
                'Lycopodium Clavatum' => 25, 'Silicea' => 20
            ],
            
            // HYPERTENSION-related headache
            'hypertension.*headache|blood.*pressure.*headache|bp.*headache|headache.*bp' => [
                'Glonoinum' => 45, 'Belladonna' => 40, 'Natrum Muriaticum' => 35,
                'Lachesis Muta' => 30, 'Nux Vomica' => 25, 'Rauwolfia Serpentina' => 20
            ],
            
            // ANGER / EMOTIONAL patterns
            'angry|anger' => [
                'Chamomilla' => 15, 'Nux Vomica' => 14, 'Staphysagria' => 12,
                'Colocynthis' => 10, 'Bryonia Alba' => 8
            ],
            'blue.*angry|angry.*blue|lip.*blue.*anger' => [
                'Chamomilla' => 18, 'Cuprum Metallicum' => 15, 'Lachesis Muta' => 12,
                'Veratrum Album' => 10
            ],
            'mood.*swing|changeable.*mood' => [
                'Ignatia Amara' => 12, 'Pulsatilla Nigricans' => 12, 'Nux Vomica' => 10,
                'Lachesis Muta' => 8
            ],
            'irritable|irritability' => [
                'Nux Vomica' => 14, 'Chamomilla' => 12, 'Bryonia Alba' => 10,
                'Hepar Sulphuris' => 8
            ],
            'weepy|tearful|crying.*easily|want.*cry' => [
                'Pulsatilla Nigricans' => 16, 'Natrum Muriaticum' => 14, 'Ignatia Amara' => 14,
                'Sepia' => 12, 'Lac Caninum' => 10
            ],
            'want.*alone|desire.*alone|aversion.*company|wants.*solitude' => [
                'Natrum Muriaticum' => 18, 'Sepia' => 14, 'Ignatia Amara' => 12,
                'Gelsemium Sempervirens' => 10, 'Carbo Animalis' => 8
            ],
            'consolation.*agg|worse.*consolation|sympathy.*agg' => [
                'Natrum Muriaticum' => 20, 'Sepia' => 14, 'Silicea' => 10,
                'Ignatia Amara' => 8
            ],
            'grief|sorrow|loss|bereavement' => [
                'Ignatia Amara' => 18, 'Natrum Muriaticum' => 16, 'Phosphoric Acid' => 14,
                'Causticum' => 10, 'Aurum Metallicum' => 10
            ],
            'anxiety|anxious|worry|fearful' => [
                'Arsenicum Album' => 15, 'Aconitum Napellus' => 14, 'Gelsemium Sempervirens' => 12,
                'Argentum Nitricum' => 12, 'Phosphorus' => 10
            ],
            
            // ANTICIPATORY ANXIETY / PERFORMANCE ANXIETY - HIGH PRIORITY PATTERNS
            'anticipatory.*anxiety|anxiety.*anticipat|before.*event|before.*exam|before.*interview|before.*presentation' => [
                'Argentum Nitricum' => 60, 'Gelsemium Sempervirens' => 45, 'Lycopodium Clavatum' => 30,
                'Silicea' => 20, 'Anacardium Orientale' => 15
            ],
            'fear.*public|public.*speaking|stage.*fright|fear.*audience|fear.*judgment|fear.*crowd' => [
                'Argentum Nitricum' => 55, 'Gelsemium Sempervirens' => 50, 'Lycopodium Clavatum' => 35,
                'Silicea' => 25, 'Carbo Vegetabilis' => 15
            ],
            'diarrhea.*anticipat|anticipat.*diarrhea|loose.*stool.*anxiety|anxiety.*loose.*stool|diarrhea.*before.*event' => [
                'Argentum Nitricum' => 70, 'Gelsemium Sempervirens' => 40, 'Podophyllum Peltatum' => 20,
                'Phosphoric Acid' => 15
            ],
            'hurried|hurry|impulsive|impatient|hasty|rushed' => [
                'Argentum Nitricum' => 40, 'Nux Vomica' => 25, 'Sulphur' => 20,
                'Medorrhinum' => 18, 'Tarentula Hispanica' => 15
            ],
            'crav.*sweet|sweet.*crav|desire.*sweet|love.*sweet|want.*sweet' => [
                'Argentum Nitricum' => 45, 'Lycopodium Clavatum' => 30, 'Sulphur' => 25,
                'China Officinalis' => 20, 'Calcarea Carbonica' => 15
            ],
            'perfectionist|fear.*mistake|fear.*failure|fear.*not.*good.*enough' => [
                'Argentum Nitricum' => 40, 'Silicea' => 35, 'Lycopodium Clavatum' => 30,
                'Arsenicum Album' => 25, 'Carcinosinum' => 20
            ],
            'trembling.*anxiety|anxiety.*trembl|shaking.*fear|nervous.*tremor' => [
                'Gelsemium Sempervirens' => 50, 'Argentum Nitricum' => 35, 'Aconitum Napellus' => 25,
                'Ignatia Amara' => 20
            ],
            'paralysis.*fear|cant.*move.*fear|frozen.*fear|stage.*paralysis' => [
                'Gelsemium Sempervirens' => 55, 'Argentum Nitricum' => 30, 'Opium' => 25,
                'Aconitum Napellus' => 20
            ],
            'cold.*sweat.*anxiety|sweat.*anxiety|perspiration.*fear|clammy.*anxiety' => [
                'Argentum Nitricum' => 40, 'Gelsemium Sempervirens' => 35, 'Veratrum Album' => 30,
                'Arsenicum Album' => 25
            ],
            
            // IBS / FUNCTIONAL GUT PATTERNS
            'ibs|irritable.*bowel|functional.*gut|stress.*stomach|nervous.*stomach' => [
                'Argentum Nitricum' => 40, 'Lycopodium Clavatum' => 35, 'Nux Vomica' => 30,
                'Colocynthis' => 25, 'Asafoetida' => 20
            ],
            'bloating.*gas|gas.*bloating|flatulence.*distension|abdominal.*distension' => [
                'Lycopodium Clavatum' => 40, 'Carbo Vegetabilis' => 35, 'China Officinalis' => 30,
                'Argentum Nitricum' => 25, 'Nux Vomica' => 20
            ],
            'gurgling.*abdomen|abdomen.*gurgling|rumbling.*stomach|borborygmi' => [
                'Argentum Nitricum' => 35, 'Podophyllum Peltatum' => 30, 'Aloe Socotrina' => 25,
                'China Officinalis' => 20
            ],
            'cramping.*abdomen|abdominal.*cramp|colicky.*pain|spasmodic.*abdomen' => [
                'Colocynthis' => 40, 'Magnesia Phosphorica' => 35, 'Cuprum Metallicum' => 30,
                'Nux Vomica' => 25, 'Argentum Nitricum' => 20
            ],
            'urgent.*stool|urgency.*diarrhea|rush.*toilet|sudden.*urge.*stool' => [
                'Aloe Socotrina' => 40, 'Argentum Nitricum' => 35, 'Podophyllum Peltatum' => 35,
                'Sulphur' => 25
            ],
            
            // THERMAL patterns
            'hot.*patient|warm.*blood|heat.*agg|worse.*heat' => [
                'Pulsatilla Nigricans' => 14, 'Sulphur' => 12, 'Lachesis Muta' => 12,
                'Apis Mellifica' => 10, 'Natrum Muriaticum' => 10
            ],
            'chilly|cold.*patient|cold.*agg|worse.*cold' => [
                'Arsenicum Album' => 14, 'Nux Vomica' => 12, 'Silicea' => 12,
                'Calcarea Carbonica' => 10, 'Phosphorus' => 10
            ],
            'thirstless|no.*thirst|absence.*thirst' => [
                'Pulsatilla Nigricans' => 16, 'Apis Mellifica' => 14, 'Gelsemium Sempervirens' => 12,
                'China Officinalis' => 10
            ],
            'thirsty|great.*thirst|excessive.*thirst' => [
                'Bryonia Alba' => 14, 'Arsenicum Album' => 12, 'Phosphorus' => 12,
                'Natrum Muriaticum' => 12, 'Sulphur' => 10
            ],
            
            // WEAKNESS / FATIGUE patterns
            'not.*walk|cannot.*walk|cant.*walk|unable.*walk' => [
                'Gelsemium Sempervirens' => 15, 'Cocculus Indicus' => 14,
                'Phosphoric Acid' => 12, 'Alumina' => 10, 'Conium Maculatum' => 8
            ],
            'tired|fatigue|exhausted|weakness' => [
                'Phosphoric Acid' => 12, 'Gelsemium Sempervirens' => 11,
                'China Officinalis' => 10, 'Arsenicum Album' => 9, 'Carbo Vegetabilis' => 8
            ],
            'vertigo|dizziness|balance|stagger' => [
                'Cocculus Indicus' => 14, 'Conium Maculatum' => 12, 'Gelsemium Sempervirens' => 11,
                'Phosphorus' => 10, 'Bryonia Alba' => 8
            ],
            'sudden.*onset|suddenly.*occur' => [
                'Aconitum Napellus' => 14, 'Belladonna' => 12, 'Gelsemium Sempervirens' => 10
            ],
            
            // ================================================================
            // INSOMNIA / SLEEP DISORDER PATTERNS - HIGH PRIORITY
            // ================================================================
            
            // OVERACTIVE MIND INSOMNIA - Primary Pattern for Mental Activity
            'racing.*thoughts|thoughts.*racing|mind.*overactive|overactive.*mind|thoughts.*wont.*stop|cant.*stop.*thinking' => [
                'Coffea Cruda' => 70, 'Nux Vomica' => 55, 'Arsenicum Album' => 40,
                'Argentum Nitricum' => 35, 'Lachesis Muta' => 30, 'Calcarea Carbonica' => 25
            ],
            'sleepless.*thoughts|thoughts.*sleepless|thinking.*cant.*sleep|cant.*sleep.*thinking|mind.*active.*night|active.*mind.*bed' => [
                'Coffea Cruda' => 75, 'Nux Vomica' => 60, 'Arsenicum Album' => 45,
                'Lachesis Muta' => 35, 'Calcarea Carbonica' => 30
            ],
            'work.*thoughts.*sleep|sleep.*work.*thoughts|thinking.*work.*bed|business.*thoughts.*night' => [
                'Nux Vomica' => 70, 'Coffea Cruda' => 55, 'Bryonia Alba' => 40,
                'Lycopodium Clavatum' => 35, 'Arsenicum Album' => 30
            ],
            
            // HOT PATIENT INSOMNIA - Thermal State + Sleep Issues  
            'hot.*patient.*sleep|hot.*cant.*sleep|hot.*insomnia|warm.*restless.*sleep|hot.*restless.*bed' => [
                'Sulphur' => 65, 'Lachesis Muta' => 55, 'Pulsatilla Nigricans' => 50,
                'Arsenicum Album' => 35, 'Calcarea Carbonica' => 30
            ],
            'hot.*restless|restless.*hot|hot.*patient.*restless|restless.*bed.*hot' => [
                'Sulphur' => 60, 'Arsenicum Album' => 50, 'Lachesis Muta' => 45,
                'Rhus Toxicodendron' => 40, 'Pulsatilla Nigricans' => 35
            ],
            
            // EXCESSIVE THIRST + SLEEP - Important Constitutional Marker
            'thirsty.*sleep|thirst.*night|excessive.*thirst.*insomnia|drinks.*water.*night' => [
                'Arsenicum Album' => 55, 'Bryonia Alba' => 45, 'Phosphorus' => 40,
                'Natrum Muriaticum' => 35, 'Sulphur' => 30
            ],
            
            // RESTLESSNESS IN BED
            'restless.*bed|bed.*restless|tossing.*turning|cant.*lie.*still|moves.*around.*bed' => [
                'Arsenicum Album' => 60, 'Rhus Toxicodendron' => 55, 'Sulphur' => 45,
                'Coffea Cruda' => 40, 'Zincum Metallicum' => 35, 'Tarentula Hispanica' => 30
            ],
            'restless.*midnight|midnight.*restless|restless.*after.*midnight' => [
                'Arsenicum Album' => 65, 'Rhus Toxicodendron' => 50, 'Nux Vomica' => 40,
                'Sulphur' => 35
            ],
            
            // ANTICIPATORY INSOMNIA
            'cant.*sleep.*anticipation|anticipation.*sleep|worried.*tomorrow|anxious.*next.*day' => [
                'Argentum Nitricum' => 60, 'Arsenicum Album' => 45, 'Coffea Cruda' => 40,
                'Gelsemium Sempervirens' => 35, 'Calcarea Carbonica' => 30
            ],
            'anxiety.*about.*sleep|anxiety.*not.*sleeping|worried.*about.*sleep|anxious.*insomnia' => [
                'Arsenicum Album' => 55, 'Coffea Cruda' => 50, 'Nux Vomica' => 45,
                'Argentum Nitricum' => 40, 'Calcarea Carbonica' => 35
            ],
            
            // EARLY WAKING / WAKING TOO EARLY
            'early.*waking|wakes.*early|waking.*3.*am|waking.*4.*am|early.*morning.*waking|cant.*sleep.*after.*3' => [
                'Nux Vomica' => 65, 'Arsenicum Album' => 55, 'Kali Carbonicum' => 50,
                'Sulphur' => 40, 'Coffea Cruda' => 35
            ],
            'wakes.*unrested|unrefreshing.*sleep|not.*refreshed|tired.*waking|fatigued.*waking' => [
                'Nux Vomica' => 60, 'Lachesis Muta' => 50, 'Sulphur' => 45,
                'Phosphorus' => 35, 'Calcarea Carbonica' => 30
            ],
            
            // LIGHT SLEEP / EASILY WAKENED
            'light.*sleep|sleep.*light|wakes.*easily|slight.*noise|easily.*disturbed|sensitive.*noise' => [
                'Coffea Cruda' => 60, 'Nux Vomica' => 55, 'Opium' => 40,
                'Phosphorus' => 35, 'Sulphur' => 30
            ],
            'slightest.*noise|wakes.*noise|disturbed.*sound|light.*sleeper' => [
                'Coffea Cruda' => 65, 'Nux Vomica' => 50, 'Opium' => 45,
                'Phosphorus' => 40, 'Silicea' => 30
            ],
            
            // DIFFICULTY FALLING ASLEEP
            'difficulty.*falling.*asleep|cant.*fall.*asleep|takes.*long.*sleep|hours.*fall.*asleep' => [
                'Coffea Cruda' => 60, 'Nux Vomica' => 55, 'Arsenicum Album' => 45,
                'Ignatia Amara' => 40, 'Pulsatilla Nigricans' => 35
            ],
            'mind.*wont.*turn.*off|brain.*wont.*shut|cant.*quiet.*mind|mental.*activity.*night' => [
                'Coffea Cruda' => 70, 'Nux Vomica' => 55, 'Arsenicum Album' => 45,
                'Lachesis Muta' => 40, 'Calcarea Carbonica' => 30
            ],
            
            // SLEEPLESSNESS FROM GRIEF/EMOTIONS
            'sleepless.*grief|grief.*insomnia|cant.*sleep.*loss|sleep.*emotional' => [
                'Ignatia Amara' => 65, 'Natrum Muriaticum' => 55, 'Phosphoric Acid' => 45,
                'Staphisagria' => 35, 'Aurum Metallicum' => 30
            ],
            'sleepless.*worry|worry.*cant.*sleep|anxious.*thoughts.*bed' => [
                'Arsenicum Album' => 55, 'Coffea Cruda' => 50, 'Calcarea Carbonica' => 40,
                'Argentum Nitricum' => 35, 'Nux Vomica' => 30
            ],
            
            // INSOMNIA WITH DAYTIME FATIGUE
            'daytime.*fatigue.*insomnia|cant.*sleep.*tired.*day|insomnia.*tired.*day|exhausted.*but.*cant.*sleep' => [
                'Nux Vomica' => 55, 'Coffea Cruda' => 50, 'Kali Phosphoricum' => 45,
                'Phosphoric Acid' => 40, 'Cocculus Indicus' => 35
            ],
            
            // CHRONIC INSOMNIA
            'chronic.*insomnia|long.*standing.*insomnia|years.*sleep.*problem|persistent.*sleeplessness' => [
                'Nux Vomica' => 55, 'Coffea Cruda' => 50, 'Sulphur' => 45,
                'Calcarea Carbonica' => 40, 'Natrum Muriaticum' => 35, 'Lachesis Muta' => 30
            ],
            
            // PRIMARY INSOMNIA (no underlying cause)
            'primary.*insomnia|insomnia.*no.*cause|sleepless.*no.*reason' => [
                'Coffea Cruda' => 55, 'Nux Vomica' => 50, 'Sulphur' => 40,
                'Lachesis Muta' => 35, 'Calcarea Carbonica' => 30
            ],
            
            // MUSCULOSKELETAL / BACK PAIN patterns - HIGH PRIORITY
            'lower.*back.*pain|back.*pain|lumbar.*pain|lumbago' => [
                'Rhus Toxicodendron' => 50, 'Bryonia Alba' => 40, 'Nux Vomica' => 35,
                'Kali Carbonicum' => 30, 'Calcarea Fluorica' => 25, 'Aesculus Hippocastanum' => 20
            ],
            'stiffness.*morning|morning.*stiffness|stiff.*waking|waking.*stiff' => [
                'Rhus Toxicodendron' => 60, 'Bryonia Alba' => 30, 'Nux Vomica' => 25,
                'Kali Carbonicum' => 20, 'Causticum' => 18
            ],
            'worse.*rest|rest.*worse|aggrav.*rest|pain.*rest' => [
                'Rhus Toxicodendron' => 60, 'Pulsatilla Nigricans' => 25, 'Ferrum Metallicum' => 20,
                'Chamomilla' => 15
            ],
            'better.*motion|motion.*better|movement.*amel|relief.*motion|better.*moving|moving.*better' => [
                'Rhus Toxicodendron' => 60, 'Pulsatilla Nigricans' => 25, 'Ferrum Metallicum' => 20,
                'Dulcamara' => 15
            ],
            'worse.*motion|motion.*worse|movement.*agg|pain.*motion|still.*better|rest.*better' => [
                'Bryonia Alba' => 60, 'Ledum Palustre' => 25, 'Nux Vomica' => 20,
                'Colchicum Autumnale' => 15
            ],
            'worse.*sitting|sitting.*worse|prolonged.*sitting|desk.*work.*pain' => [
                'Rhus Toxicodendron' => 55, 'Nux Vomica' => 45, 'Sulphur' => 30,
                'Aesculus Hippocastanum' => 25, 'Kali Carbonicum' => 20
            ],
            'sciatica|sciatic.*pain|radiat.*leg|radiat.*buttock|buttock.*pain' => [
                'Rhus Toxicodendron' => 45, 'Colocynthis' => 45, 'Magnesia Phosphorica' => 40,
                'Gnaphalium Polycephalum' => 35, 'Hypericum Perforatum' => 30, 'Bryonia Alba' => 25
            ],
            'right.*sciatica|sciatica.*right|right.*leg.*pain' => [
                'Magnesia Phosphorica' => 50, 'Lycopodium Clavatum' => 40, 'Colocynthis' => 35,
                'Rhus Toxicodendron' => 30
            ],
            'left.*sciatica|sciatica.*left|left.*leg.*pain' => [
                'Colocynthis' => 50, 'Rhus Toxicodendron' => 35, 'Kali Carbonicum' => 30,
                'Gnaphalium Polycephalum' => 25
            ],
            'muscle.*spasm|spasm.*muscle|paraspinal.*spasm|muscle.*tension' => [
                'Magnesia Phosphorica' => 45, 'Rhus Toxicodendron' => 40, 'Nux Vomica' => 35,
                'Cuprum Metallicum' => 30, 'Causticum' => 20
            ],
            'cervical.*pain|neck.*pain|shoulder.*pain|shoulder.*tension' => [
                'Cimicifuga Racemosa' => 45, 'Rhus Toxicodendron' => 40, 'Bryonia Alba' => 30,
                'Lachnanthes Tinctoria' => 25, 'Gelsemium Sempervirens' => 25
            ],
            'joint.*pain|arthritis|arthralgia|polyarthritis' => [
                'Rhus Toxicodendron' => 45, 'Bryonia Alba' => 40, 'Ledum Palustre' => 35,
                'Calcarea Carbonica' => 25, 'Causticum' => 25
            ],
            'joint.*stiff|stiff.*joint|rigidity' => [
                'Rhus Toxicodendron' => 50, 'Causticum' => 40, 'Bryonia Alba' => 30,
                'Calcarea Fluorica' => 25, 'Guaiacum' => 20
            ],
            'worse.*damp|damp.*worse|wet.*weather.*worse|weather.*change.*pain' => [
                'Rhus Toxicodendron' => 55, 'Dulcamara' => 50, 'Natrum Sulphuricum' => 35,
                'Calcarea Carbonica' => 25, 'Phytolacca Decandra' => 20
            ],
            'worse.*cold.*damp|cold.*wet.*worse' => [
                'Rhus Toxicodendron' => 55, 'Dulcamara' => 50, 'Calcarea Phosphorica' => 30,
                'Natrum Sulphuricum' => 25
            ],
            'better.*warmth|warmth.*better|heat.*amel|warm.*application.*better' => [
                'Rhus Toxicodendron' => 45, 'Magnesia Phosphorica' => 45, 'Arsenicum Album' => 35,
                'Hepar Sulphuris' => 25, 'Nux Vomica' => 20
            ],
            'better.*cold|cold.*amel|cold.*application.*better|ice.*better' => [
                'Ledum Palustre' => 50, 'Apis Mellifica' => 45, 'Pulsatilla Nigricans' => 30,
                'Bryonia Alba' => 20
            ],
            
            // SEDENTARY LIFESTYLE + BACK PAIN
            'sedentary.*back|back.*pain.*desk|desk.*work.*back|prolonged.*sitting.*back' => [
                'Nux Vomica' => 55, 'Rhus Toxicodendron' => 50, 'Sulphur' => 35,
                'Calcarea Carbonica' => 25, 'Aesculus Hippocastanum' => 20
            ],
            'poor.*posture|posture.*problem|slouching' => [
                'Sulphur' => 40, 'Calcarea Carbonica' => 35, 'Nux Vomica' => 30,
                'Phosphorus' => 25, 'Calcarea Phosphorica' => 20
            ],
            
            // RESPIRATORY patterns
            'cough.*night|night.*cough' => [
                'Drosera Rotundifolia' => 14, 'Hyoscyamus Niger' => 12, 'Spongia Tosta' => 10,
                'Arsenicum Album' => 10, 'Rumex Crispus' => 8
            ],
            'dry.*cough' => [
                'Bryonia Alba' => 12, 'Phosphorus' => 11, 'Spongia Tosta' => 10,
                'Rumex Crispus' => 9, 'Aconitum Napellus' => 8
            ],
            
            // ASTHMA patterns - comprehensive clinical mappings
            'asthma|asthmatic|bronchial.*asthma' => [
                'Arsenicum Album' => 16, 'Ipecacuanha' => 14, 'Antimonium Tartaricum' => 14,
                'Blatta Orientalis' => 12, 'Sambucus Nigra' => 12, 'Natrum Sulphuricum' => 10
            ],
            'asthma.*night|night.*asthma|nocturnal.*asthma' => [
                'Arsenicum Album' => 20, 'Sambucus Nigra' => 16, 'Spongia Tosta' => 14,
                'Grindelia Robusta' => 12, 'Ipecacuanha' => 10
            ],
            '2.*am|3.*am|2-3.*am|after.*midnight|midnight.*agg' => [
                'Arsenicum Album' => 22, 'Kali Carbonicum' => 18, 'Drosera Rotundifolia' => 12,
                'Rumex Crispus' => 10, 'Ammonium Carbonicum' => 8
            ],
            'worse.*night.*2|worse.*night.*3|aggrav.*2.*am|aggrav.*3.*am' => [
                'Arsenicum Album' => 22, 'Kali Carbonicum' => 18, 'Drosera Rotundifolia' => 14
            ],
            'wheezing|wheeze' => [
                'Antimonium Tartaricum' => 14, 'Arsenicum Album' => 12, 'Ipecacuanha' => 12,
                'Blatta Orientalis' => 10, 'Grindelia Robusta' => 10
            ],
            'dyspnea|dyspnoea|difficult.*breath|breathing.*difficult|shortness.*breath' => [
                'Arsenicum Album' => 14, 'Antimonium Tartaricum' => 14, 'Carbo Vegetabilis' => 12,
                'Grindelia Robusta' => 10, 'Lobelia Inflata' => 10, 'Ipecacuanha' => 10
            ],
            'chest.*tight|tightness.*chest|constriction.*chest' => [
                'Phosphorus' => 14, 'Arsenicum Album' => 12, 'Spongia Tosta' => 12,
                'Lobelia Inflata' => 10, 'Nux Vomica' => 8
            ],
            'spasmodic.*cough|cough.*spasm|paroxysmal.*cough' => [
                'Drosera Rotundifolia' => 16, 'Cuprum Metallicum' => 14, 'Ipecacuanha' => 12,
                'Coccus Cacti' => 10, 'Hyoscyamus Niger' => 10
            ],
            'dust.*exposure|dust.*allergy|dust.*trigger' => [
                'Blatta Orientalis' => 18, 'Arsenicum Album' => 14, 'Pothos Foetidus' => 12,
                'Bromium' => 10, 'Silicea' => 8
            ],
            'anxious.*breath|anxiety.*breath|fear.*suffoc|restless.*breath' => [
                'Arsenicum Album' => 20, 'Aconitum Napellus' => 16, 'Lobelia Inflata' => 12,
                'Grindelia Robusta' => 10
            ],
            'chilly.*asthma|cold.*asthma|asthma.*chilly' => [
                'Arsenicum Album' => 22, 'Hepar Sulphuris' => 14, 'Nux Vomica' => 12,
                'Spongia Tosta' => 10
            ],
            'warm.*drinks.*desire|desires.*warm|wants.*warm.*drink' => [
                'Arsenicum Album' => 18, 'Lycopodium Clavatum' => 14, 'Bryonia Alba' => 10,
                'Chelidonium Majus' => 10, 'Nux Vomica' => 8
            ],
            'rattling.*chest|mucus.*rattling' => [
                'Antimonium Tartaricum' => 18, 'Ipecacuanha' => 14, 'Hepar Sulphuris' => 10,
                'Senega' => 10
            ],
            
            // ACUTE FEVER patterns - very important for prescribing
            'sudden.*fever|fever.*sudden|rapid.*onset.*fever|fever.*came.*suddenly' => [
                'Aconitum Napellus' => 60, 'Belladonna' => 50, 'Ferrum Phosphoricum' => 35,
                'Gelsemium Sempervirens' => 25, 'Bryonia Alba' => 20
            ],
            'high.*fever|fever.*high|burning.*fever|fever.*104|fever.*103' => [
                'Belladonna' => 55, 'Aconitum Napellus' => 50, 'Arsenicum Album' => 35,
                'Phosphorus' => 30, 'Rhus Toxicodendron' => 25
            ],
            'fear.*death|thinks.*die|will.*die|fear.*dying|death.*fear' => [
                'Aconitum Napellus' => 60, 'Arsenicum Album' => 45, 'Phosphorus' => 30,
                'Lachesis Muta' => 25, 'Argentum Nitricum' => 20
            ],
            'after.*cold.*wind|exposure.*cold.*wind|cold.*wind.*exposure|caught.*cold.*wind' => [
                'Aconitum Napellus' => 55, 'Hepar Sulphuris' => 30, 'Nux Vomica' => 25,
                'Bryonia Alba' => 20, 'Rhus Toxicodendron' => 20
            ],
            'fever.*restless|restless.*fever|tossing.*fever|cannot.*lie.*still.*fever' => [
                'Aconitum Napellus' => 50, 'Arsenicum Album' => 45, 'Rhus Toxicodendron' => 40,
                'Belladonna' => 30
            ],
            'fever.*face.*red|red.*face.*fever|flushed.*face|hot.*red.*face' => [
                'Belladonna' => 60, 'Aconitum Napellus' => 40, 'Gelsemium Sempervirens' => 25,
                'Ferrum Phosphoricum' => 25
            ],
            'fever.*dry.*skin|dry.*hot.*skin|no.*sweat.*fever' => [
                'Aconitum Napellus' => 55, 'Belladonna' => 50, 'Bryonia Alba' => 30,
                'Nux Vomica' => 20
            ],
            'fever.*thirst.*large|great.*thirst.*fever|thirsty.*fever|drinks.*large.*quantities' => [
                'Aconitum Napellus' => 50, 'Bryonia Alba' => 50, 'Phosphorus' => 35,
                'Arsenicum Album' => 30, 'Natrum Muriaticum' => 25
            ],
            'fever.*first.*stage|beginning.*fever|onset.*fever|early.*fever' => [
                'Aconitum Napellus' => 55, 'Ferrum Phosphoricum' => 45, 'Belladonna' => 40,
                'Gelsemium Sempervirens' => 30
            ],
            'fever.*anxiety.*restless|anxious.*restless.*fever|fear.*anxiety.*fever' => [
                'Aconitum Napellus' => 65, 'Arsenicum Album' => 45
            ],
            'fever.*after.*fright|fright.*then.*fever|shock.*fever|ailments.*fright' => [
                'Aconitum Napellus' => 60, 'Opium' => 40, 'Gelsemium Sempervirens' => 35
            ],
            'fever.*child|child.*fever|pediatric.*fever' => [
                'Belladonna' => 50, 'Aconitum Napellus' => 50, 'Chamomilla' => 35,
                'Ferrum Phosphoricum' => 30, 'Pulsatilla Nigricans' => 25
            ],
            
            // ALLERGIC RHINITIS / HAY FEVER patterns - HIGH SCORES for clinical specificity
            'allergic.*rhinitis|rhinitis.*allergic|hay.*fever|vasomotor.*rhinitis' => [
                'Sabadilla' => 70, 'Allium Cepa' => 65, 'Arsenicum Album' => 55,
                'Nux Vomica' => 50, 'Natrum Muriaticum' => 40, 'Wyethia Helenioides' => 35, 'Arundo Mauritanica' => 30
            ],
            'sneezing.*succession|successive.*sneez|paroxysmal.*sneez|violent.*sneez|bouts.*sneez' => [
                'Sabadilla' => 80, 'Arsenicum Album' => 50, 'Allium Cepa' => 45,
                'Nux Vomica' => 40, 'Natrum Muriaticum' => 30, 'Silicea' => 20
            ],
            'sneez.*10|sneez.*15|sneez.*20|multiple.*sneez|continuous.*sneez' => [
                'Sabadilla' => 85, 'Arsenicum Album' => 45, 'Arundo Mauritanica' => 35,
                'Nux Vomica' => 30
            ],
            // ITCHING PALATE - CLASSIC Sabadilla indication
            'itching.*palate|palate.*itch|itch.*roof.*mouth|tickling.*palate|palate.*tickl' => [
                'Sabadilla' => 90, 'Wyethia Helenioides' => 70, 'Arundo Mauritanica' => 60,
                'Nux Vomica' => 25
            ],
            // Combined itching symptoms - very specific to allergic rhinitis
            'itching.*nose.*eye|itch.*nose.*palate|nose.*eye.*palate.*itch|itch.*eye.*nose' => [
                'Sabadilla' => 85, 'Wyethia Helenioides' => 65, 'Arundo Mauritanica' => 55,
                'Allium Cepa' => 40
            ],
            'watery.*discharge|discharge.*watery|profuse.*nasal|nasal.*profuse|copious.*discharge|profuse.*watery.*coryza' => [
                'Allium Cepa' => 60, 'Arsenicum Album' => 45, 'Natrum Muriaticum' => 40,
                'Sabadilla' => 35, 'Euphrasia' => 30
            ],
            'better.*open.*air|open.*air.*amel|relief.*open.*air|feels.*better.*open' => [
                'Allium Cepa' => 35, 'Pulsatilla Nigricans' => 28, 'Argentum Nitricum' => 18,
                'Lycopodium Clavatum' => 10, 'Carbo Vegetabilis' => 10
            ],
            'nasal.*congestion|stuffed.*nose|blocked.*nose|congested.*nose' => [
                'Nux Vomica' => 14, 'Arsenicum Album' => 12, 'Sambucus Nigra' => 12,
                'Kali Bichromicum' => 10, 'Ammonium Carbonicum' => 10
            ],
            'itchy.*nose|nose.*itchy|tickling.*nose|tingling.*nose' => [
                'Arundo Mauritanica' => 16, 'Wyethia Helenioides' => 14, 'Sabadilla' => 12,
                'Allium Cepa' => 10
            ],
            'seasonal.*allerg|allerg.*season|spring.*allerg|autumn.*allerg' => [
                'Sabadilla' => 25, 'Sabadilla Officinalis' => 25, 'Allium Cepa' => 22, 'Natrum Muriaticum' => 18,
                'Arsenicum Album' => 15, 'Dulcamara' => 12
            ],
            
            // SKIN / ECZEMA patterns - HIGH SCORES for clinical specificity
            'eczema|dermatitis|atopic' => [
                'Graphites' => 35, 'Sulphur' => 30, 'Arsenicum Album' => 18,
                'Petroleum' => 18, 'Mezereum' => 15, 'Rhus Toxicodendron' => 14
            ],
            'itching.*night|night.*itching|itch.*worse.*night|worse.*night.*itch' => [
                'Sulphur' => 40, 'Psorinum' => 30, 'Arsenicum Album' => 18,
                'Mercurius Solubilis' => 14, 'Dolichos Pruriens' => 14
            ],
            'itching.*warmth|warmth.*itch|warm.*bed.*itch|itch.*warm.*bed|worse.*warmth.*bed|warmth.*of.*bed' => [
                'Sulphur' => 50, 'Psorinum' => 35, 'Mercurius Solubilis' => 20,
                'Pulsatilla Nigricans' => 15, 'Ledum Palustre' => 12
            ],
            'scratch.*marks|excoriation|scratching' => [
                'Sulphur' => 22, 'Arsenicum Album' => 18, 'Rhus Toxicodendron' => 14,
                'Graphites' => 14, 'Mezereum' => 10
            ],
            'skin.*rash|rash.*skin|eruption|urticaria|hives' => [
                'Rhus Toxicodendron' => 20, 'Apis Mellifica' => 20, 'Urtica Urens' => 20,
                'Sulphur' => 18, 'Arsenicum Album' => 14
            ],
            'dry.*skin|skin.*dry|cracked.*skin|fissure' => [
                'Petroleum' => 25, 'Graphites' => 22, 'Sulphur' => 18,
                'Arsenicum Album' => 14, 'Silicea' => 14
            ],
            'weeping.*eczema|moist.*eruption|oozing.*skin' => [
                'Graphites' => 30, 'Rhus Toxicodendron' => 22, 'Mezereum' => 18,
                'Arsenicum Album' => 14
            ],
            'allerg.*skin|skin.*allerg|atopic.*dermatitis' => [
                'Sulphur' => 28, 'Graphites' => 25, 'Arsenicum Album' => 20,
                'Petroleum' => 18, 'Psorinum' => 18
            ],
            
            // FEMALE / HORMONAL patterns - HIGH PRIORITY for gynecological cases
            'pcos|polycystic.*ovarian|ovarian.*syndrome|pco' => [
                'Pulsatilla Nigricans' => 55, 'Sepia' => 50, 'Lachesis Muta' => 40,
                'Calcarea Carbonica' => 35, 'Natrum Muriaticum' => 30, 'Apis Mellifica' => 25
            ],
            'irregular.*period|period.*irregular|irregular.*menses|menses.*irregular|delayed.*period|period.*delayed' => [
                'Pulsatilla Nigricans' => 50, 'Sepia' => 45, 'Calcarea Carbonica' => 35,
                'Natrum Muriaticum' => 30, 'Senecio Aureus' => 25, 'Cimicifuga Racemosa' => 20
            ],
            'amenorrhea|absent.*menses|no.*period|suppressed.*menses' => [
                'Pulsatilla Nigricans' => 50, 'Sepia' => 45, 'Senecio Aureus' => 40,
                'Calcarea Carbonica' => 35, 'Graphites' => 30, 'Ferrum Metallicum' => 25
            ],
            'pms|premenstrual.*syndrome|premenstrual.*symptoms|before.*period.*symptoms' => [
                'Pulsatilla Nigricans' => 50, 'Sepia' => 50, 'Lachesis Muta' => 45,
                'Natrum Muriaticum' => 35, 'Cimicifuga Racemosa' => 30, 'Cyclamen Europaeum' => 25
            ],
            'irritable.*premenstrual|premenstrual.*irritab|irritab.*before.*period|mood.*before.*period' => [
                'Sepia' => 55, 'Lachesis Muta' => 50, 'Natrum Muriaticum' => 40,
                'Pulsatilla Nigricans' => 35, 'Chamomilla' => 25
            ],
            'weepy.*premenstrual|cry.*before.*period|tearful.*menses|emotional.*period' => [
                'Pulsatilla Nigricans' => 60, 'Sepia' => 45, 'Natrum Muriaticum' => 40,
                'Ignatia Amara' => 35, 'Cyclamen Europaeum' => 25
            ],
            'weepy|tearful|cry.*easily|easily.*cry|weeping|wants.*cry' => [
                'Pulsatilla Nigricans' => 45, 'Natrum Muriaticum' => 40, 'Ignatia Amara' => 40,
                'Sepia' => 35, 'Lac Caninum' => 25
            ],
            'changeable.*mood|mood.*swing|emotional.*instab|mood.*change' => [
                'Pulsatilla Nigricans' => 50, 'Ignatia Amara' => 45, 'Cyclamen Europaeum' => 35,
                'Lachesis Muta' => 30, 'Natrum Muriaticum' => 25
            ],
            'desire.*solitude|wants.*alone|aversion.*company|prefer.*alone|desire.*alone' => [
                'Sepia' => 55, 'Natrum Muriaticum' => 50, 'Ignatia Amara' => 35,
                'Gelsemium Sempervirens' => 25, 'Carbo Animalis' => 20
            ],
            'heavy.*flow|heavy.*period|menorrhagia|profuse.*menses|heavy.*menses' => [
                'Sabina' => 45, 'China Officinalis' => 40, 'Sepia' => 35,
                'Calcarea Carbonica' => 30, 'Ferrum Metallicum' => 30, 'Phosphorus' => 25
            ],
            'clot.*menses|menses.*clot|clot.*period|blood.*clot.*menses' => [
                'Sabina' => 45, 'Sepia' => 40, 'Crocus Sativus' => 35,
                'Pulsatilla Nigricans' => 30, 'Chamomilla' => 25, 'Belladonna' => 20
            ],
            'ovarian.*pain|pain.*ovary|ovarian.*cyst|cyst.*ovary' => [
                'Apis Mellifica' => 45, 'Lachesis Muta' => 45, 'Sepia' => 35,
                'Palladium' => 30, 'Oophorinum' => 25, 'Platina' => 20
            ],
            'left.*ovary|ovary.*left|left.*ovarian' => [
                'Lachesis Muta' => 60, 'Apis Mellifica' => 35, 'Argentum Metallicum' => 25,
                'Palladium' => 20, 'Thuja Occidentalis' => 15
            ],
            'worse.*before.*menses|worse.*before.*period|agg.*before.*menses|premenstrual.*agg|before.*menses.*worse' => [
                'Lachesis Muta' => 55, 'Sepia' => 40, 'Natrum Muriaticum' => 30,
                'Pulsatilla Nigricans' => 25, 'Conium Maculatum' => 20
            ],
            'right.*ovary|ovary.*right' => [
                'Apis Mellifica' => 45, 'Palladium' => 40, 'Lycopodium Clavatum' => 30,
                'Belladonna' => 25
            ],
            'breast.*tender.*period|breast.*pain.*menses|mastalgia|breast.*sore' => [
                'Lac Caninum' => 45, 'Conium Maculatum' => 40, 'Phytolacca Decandra' => 35,
                'Calcarea Carbonica' => 30, 'Pulsatilla Nigricans' => 25
            ],
            'acne.*menses|acne.*period|pimple.*period|breakout.*period|acne.*premenstrual' => [
                'Pulsatilla Nigricans' => 45, 'Sepia' => 40, 'Kali Bromatum' => 35,
                'Sulphur' => 30, 'Hepar Sulphuris' => 20
            ],
            'hormonal.*imbalance|hormone.*problem|endocrine' => [
                'Sepia' => 50, 'Pulsatilla Nigricans' => 45, 'Thyroidinum' => 35,
                'Calcarea Carbonica' => 30, 'Lachesis Muta' => 30
            ],
            'bloating.*menses|bloat.*period|water.*retention.*period|abdominal.*distension.*period' => [
                'Pulsatilla Nigricans' => 45, 'Sepia' => 40, 'Lycopodium Clavatum' => 35,
                'Calcarea Carbonica' => 25, 'Graphites' => 20
            ],
            'hot.*patient.*female|female.*hot|warm.*blood.*female|worse.*heat.*female' => [
                'Pulsatilla Nigricans' => 50, 'Lachesis Muta' => 45, 'Sulphur' => 30,
                'Sepia' => 25, 'Apis Mellifica' => 25
            ],
            'hirsutism|excess.*hair.*female|facial.*hair.*female' => [
                'Sepia' => 45, 'Thuja Occidentalis' => 40, 'Oleum Jecoris Aselli' => 30,
                'Calcarea Carbonica' => 25, 'Medorrhinum' => 20
            ],
            'acne.*chin|chin.*acne|jawline.*acne|acne.*jawline' => [
                'Sepia' => 50, 'Pulsatilla Nigricans' => 40, 'Sulphur' => 30,
                'Kali Bromatum' => 25, 'Hepar Sulphuris' => 20
            ],
            'anxiety.*fertility|fertility.*anxiety|worry.*pregnant|cant.*conceive' => [
                'Sepia' => 45, 'Natrum Muriaticum' => 40, 'Ignatia Amara' => 35,
                'Phosphoric Acid' => 25, 'Pulsatilla Nigricans' => 25
            ],
            
            // GERD / ACID REFLUX patterns - HIGH PRIORITY
            'gerd|gastroesophageal.*reflux|acid.*reflux|reflux' => [
                'Nux Vomica' => 55, 'Robinia Pseudoacacia' => 50, 'Natrum Phosphoricum' => 40,
                'Iris Versicolor' => 35, 'Arsenicum Album' => 30, 'Phosphorus' => 25
            ],
            'heartburn|heart.*burn|burning.*chest|pyrosis' => [
                'Robinia Pseudoacacia' => 55, 'Nux Vomica' => 50, 'Arsenicum Album' => 40,
                'Phosphorus' => 35, 'Sulphur' => 30, 'Iris Versicolor' => 25
            ],
            'burning.*esophagus|esophagus.*burn|burning.*throat.*stomach' => [
                'Robinia Pseudoacacia' => 55, 'Arsenicum Album' => 45, 'Phosphorus' => 40,
                'Iris Versicolor' => 35, 'Nux Vomica' => 30
            ],
            'sour.*belch|belching.*sour|sour.*eructation|acid.*eructation|eructation.*acid' => [
                'Robinia Pseudoacacia' => 60, 'Nux Vomica' => 50, 'Natrum Phosphoricum' => 40,
                'Carbo Vegetabilis' => 30, 'Lycopodium Clavatum' => 25
            ],
            'water.*brash|waterbrash|regurgitation.*acid|acid.*regurgitation' => [
                'Robinia Pseudoacacia' => 55, 'Nux Vomica' => 45, 'Arsenicum Album' => 35,
                'Natrum Muriaticum' => 30, 'Phosphorus' => 25
            ],
            'acidity|hyperacidity|excess.*acid|stomach.*acid' => [
                'Robinia Pseudoacacia' => 55, 'Natrum Phosphoricum' => 50, 'Nux Vomica' => 45,
                'Iris Versicolor' => 35, 'Argentum Nitricum' => 30
            ],
            'worse.*rich.*food|rich.*food.*agg|fatty.*food.*agg|greasy.*food.*worse' => [
                'Pulsatilla Nigricans' => 50, 'Nux Vomica' => 40, 'Carbo Vegetabilis' => 35,
                'Lycopodium Clavatum' => 30, 'China Officinalis' => 25
            ],
            'worse.*spicy.*food|spicy.*food.*agg|spicy.*agg' => [
                'Nux Vomica' => 50, 'Arsenicum Album' => 35, 'Phosphorus' => 30,
                'Sulphur' => 25
            ],
            'nausea.*without.*vomit|nausea.*no.*vomit|nausea.*cant.*vomit' => [
                'Ipecacuanha' => 55, 'Nux Vomica' => 40, 'Antimonium Crudum' => 30,
                'Colchicum Autumnale' => 25
            ],
            
            // NUX VOMICA MENTAL-DIGESTIVE PATTERNS - HIGH PRIORITY
            'hurried|hurry|impatient|always.*rush|rushed|hasty' => [
                'Nux Vomica' => 50, 'Argentum Nitricum' => 40, 'Sulphur' => 25,
                'Medorrhinum' => 20, 'Tarentula Hispanica' => 15
            ],
            'businessman|business.*stress|work.*stress|competitive|ambitious' => [
                'Nux Vomica' => 55, 'Lycopodium Clavatum' => 40, 'Arsenicum Album' => 30,
                'Kali Carbonicum' => 25, 'Bryonia Alba' => 20
            ],
            'desires.*stimulant|crave.*coffee|coffee.*desire|wants.*coffee|love.*coffee' => [
                'Nux Vomica' => 55, 'Chamomilla' => 30, 'Coffea Cruda' => 25,
                'Causticum' => 20
            ],
            'desires.*spicy|crave.*spicy|want.*spicy.*food|love.*spicy' => [
                'Nux Vomica' => 50, 'Phosphorus' => 35, 'Sulphur' => 30,
                'Arsenicum Album' => 20
            ],
            'sedentary.*lifestyle|sedentary|desk.*job|sitting.*work' => [
                'Nux Vomica' => 50, 'Bryonia Alba' => 35, 'Sulphur' => 30,
                'Calcarea Carbonica' => 25, 'Sepia' => 20
            ],
            'irregular.*eating|eating.*irregular|irregular.*meal|skip.*meal|late.*night.*eating|late.*dinner' => [
                'Nux Vomica' => 50, 'Lycopodium Clavatum' => 35, 'Sulphur' => 30,
                'Arsenicum Album' => 25
            ],
            'type.*a.*personality|workaholic|overachiever|driven|perfectionist.*work' => [
                'Nux Vomica' => 55, 'Arsenicum Album' => 40, 'Carcinosinum' => 30,
                'Lycopodium Clavatum' => 25
            ],
            
            // BLOATING patterns
            'bloating.*after.*meal|bloat.*after.*eating|distension.*after.*food' => [
                'Lycopodium Clavatum' => 50, 'Carbo Vegetabilis' => 45, 'Nux Vomica' => 40,
                'China Officinalis' => 35, 'Pulsatilla Nigricans' => 30
            ],
            'bloating|distension.*abdomen|abdominal.*bloat' => [
                'Lycopodium Clavatum' => 45, 'Carbo Vegetabilis' => 40, 'China Officinalis' => 35,
                'Nux Vomica' => 30, 'Argentum Nitricum' => 25
            ],
            
            // HOT patient with digestive issues
            'hot.*patient.*digest|hot.*digestive|warm.*blood.*stomach' => [
                'Phosphorus' => 45, 'Sulphur' => 40, 'Pulsatilla Nigricans' => 35,
                'Argentum Nitricum' => 30, 'Nux Vomica' => 20
            ],
            'desires.*cold.*drink|cold.*drink.*desire|want.*cold.*water|thirst.*cold' => [
                'Phosphorus' => 50, 'Arsenicum Album' => 35, 'Bryonia Alba' => 30,
                'Veratrum Album' => 25, 'Natrum Muriaticum' => 20
            ],
            'excessive.*thirst|great.*thirst|drinks.*large.*quantities' => [
                'Phosphorus' => 45, 'Bryonia Alba' => 40, 'Natrum Muriaticum' => 35,
                'Arsenicum Album' => 30, 'Sulphur' => 25
            ],
            
            // DIGESTIVE patterns
            'nausea|vomiting' => [
                'Ipecacuanha' => 14, 'Nux Vomica' => 12, 'Arsenicum Album' => 10
            ],
            'constipation' => [
                'Nux Vomica' => 12, 'Bryonia Alba' => 11, 'Alumina' => 10, 'Opium' => 9
            ],
            
            // ================================================================
            // CHAMOMILLA PATTERNS - Anger with physical symptoms (CRITICAL)
            // ================================================================
            'blue.*lip.*anger|anger.*blue.*lip|lip.*blue.*when.*angry|angry.*lip.*blue' => [
                'Chamomilla' => 120, 'Cuprum Metallicum' => 35, 'Lachesis Muta' => 15,
                'Cina' => 20, 'Veratrum Album' => 15
            ],
            'gets.*blue.*lip|lip.*turn.*blue.*anger|blue.*lip.*gets|gets.*angry.*blue' => [
                'Chamomilla' => 100, 'Cuprum Metallicum' => 40, 'Cina' => 25
            ],
            'blue.*lip|lip.*blue|cyanotic.*lip|lips.*turn.*blue|bluish.*lip' => [
                'Chamomilla' => 65, 'Cuprum Metallicum' => 55, 'Lachesis Muta' => 35,
                'Carbo Vegetabilis' => 30, 'Veratrum Album' => 25, 'Antimonium Tartaricum' => 20
            ],
            'blue.*anger|anger.*blue|turn.*blue.*angry|angry.*turn.*blue|blue.*when.*angry' => [
                'Chamomilla' => 95, 'Cuprum Metallicum' => 40, 'Nux Vomica' => 25,
                'Staphysagria' => 20
            ],
            'anger.*physical|physical.*anger|angry.*symptom|symptom.*anger' => [
                'Chamomilla' => 70, 'Staphysagria' => 50, 'Colocynthis' => 45,
                'Nux Vomica' => 40, 'Bryonia Alba' => 25
            ],
            'ailments.*anger|from.*anger|after.*anger|since.*anger' => [
                'Staphysagria' => 55, 'Chamomilla' => 65, 'Colocynthis' => 50,
                'Nux Vomica' => 40, 'Bryonia Alba' => 30
            ],
            'extreme.*irritab|very.*irritab|intensely.*irritab|highly.*irritab' => [
                'Chamomilla' => 60, 'Nux Vomica' => 50, 'Hepar Sulphuris' => 40,
                'Bryonia Alba' => 30, 'Cina' => 25
            ],
            'inconsolable|uncalmable|cannot.*calm|nothing.*please|nothing.*satisfy' => [
                'Chamomilla' => 75, 'Cina' => 45, 'Rheum' => 35, 'Antimonium Tartaricum' => 25
            ],
            'one.*cheek.*red|cheek.*red.*one|red.*cheek.*pale|hot.*red.*face.*anger' => [
                'Chamomilla' => 70, 'Aconitum Napellus' => 30, 'Belladonna' => 25
            ],
            'tantrum|rage.*child|child.*rage|screaming.*anger' => [
                'Chamomilla' => 65, 'Cina' => 55, 'Tuberculinum' => 30, 'Stramonium' => 25
            ],
            
            // ================================================================
            // GELSEMIUM PATTERNS - Weakness, headache, anticipation (CRITICAL)
            // ================================================================
            'weakness.*walk|walk.*weakness|unable.*walk|cant.*walk|not.*able.*walk|cannot.*walk' => [
                'Gelsemium Sempervirens' => 70, 'Cocculus Indicus' => 50, 'Conium Maculatum' => 40,
                'Alumina' => 30, 'Phosphoric Acid' => 25
            ],
            'heaviness.*head|head.*heavy|dull.*heavy.*head|heavy.*dull.*head' => [
                'Gelsemium Sempervirens' => 65, 'Bryonia Alba' => 40, 'Nux Vomica' => 30,
                'Carbo Vegetabilis' => 25
            ],
            'droopy.*eyelid|eyelid.*heavy|heavy.*eyelid|ptosis|lid.*droop' => [
                'Gelsemium Sempervirens' => 70, 'Causticum' => 40, 'Sepia' => 30,
                'Conium Maculatum' => 25
            ],
            'trembling.*weakness|weakness.*trembling|shaky.*weak|weak.*shaky' => [
                'Gelsemium Sempervirens' => 65, 'Cocculus Indicus' => 45, 'China Officinalis' => 35,
                'Phosphoric Acid' => 30
            ],
            'headache.*band|band.*around.*head|band.*head|constricting.*head|tight.*band' => [
                'Gelsemium Sempervirens' => 55, 'Argentum Nitricum' => 40, 'Sulphur' => 30,
                'Carbo Vegetabilis' => 25
            ],
            'headache.*start.*occiput|occiput.*spread|pain.*back.*head.*forward|occipital.*spread' => [
                'Gelsemium Sempervirens' => 65, 'Sanguinaria Canadensis' => 45, 'Silicea' => 35
            ],
            'dullness|apathy|prostration|lethargy|sluggish' => [
                'Gelsemium Sempervirens' => 50, 'Phosphoric Acid' => 45, 'Carbo Vegetabilis' => 35,
                'Opium' => 30, 'Baptisia Tinctoria' => 30
            ],
            'tired.*exhausted|extreme.*tired|profound.*fatigue|cannot.*move|too.*weak' => [
                'Gelsemium Sempervirens' => 55, 'Phosphoric Acid' => 50, 'China Officinalis' => 45,
                'Arsenicum Album' => 35, 'Carbo Vegetabilis' => 30
            ],
            'muscular.*weakness|muscle.*weak|limb.*heavy|heavy.*limb|legs.*heavy' => [
                'Gelsemium Sempervirens' => 60, 'Cocculus Indicus' => 45, 'Conium Maculatum' => 40,
                'Kali Carbonicum' => 30
            ],
            'loss.*balance|balance.*problem|unsteady|stagger.*walk|lose.*balance' => [
                'Gelsemium Sempervirens' => 55, 'Cocculus Indicus' => 50, 'Conium Maculatum' => 45,
                'Phosphorus' => 30, 'Argentum Nitricum' => 25
            ],
            
            // ================================================================
            // RHUS TOX PATTERNS - Joint stiffness, worse rest (CRITICAL)
            // ================================================================
            'stiff.*joint|joint.*stiff|joint.*stiffness|stiffness.*joint' => [
                'Rhus Toxicodendron' => 70, 'Causticum' => 45, 'Bryonia Alba' => 35,
                'Calcarea Fluorica' => 30, 'Kali Carbonicum' => 25
            ],
            'knee.*pain|pain.*knee|bilateral.*knee|both.*knee' => [
                'Rhus Toxicodendron' => 50, 'Bryonia Alba' => 45, 'Calcarea Carbonica' => 35,
                'Apis Mellifica' => 30, 'Benzoic Acid' => 25
            ],
            'difficulty.*walk|walk.*difficulty|trouble.*walk|walking.*difficult' => [
                'Rhus Toxicodendron' => 55, 'Gelsemium Sempervirens' => 45, 'Cocculus Indicus' => 40,
                'Conium Maculatum' => 35, 'Alumina' => 30
            ],
            'worse.*first.*motion|first.*motion.*worse|initial.*motion.*agg|pain.*start.*move' => [
                'Rhus Toxicodendron' => 75, 'Pulsatilla Nigricans' => 25, 'Ferrum Metallicum' => 20
            ],
            'limber.*up|better.*continued.*motion|movement.*help|keeps.*moving' => [
                'Rhus Toxicodendron' => 70, 'Pulsatilla Nigricans' => 30, 'Ferrum Metallicum' => 25
            ],
            'restless.*pain|cant.*find.*position|toss.*turn|change.*position|restless.*night' => [
                'Rhus Toxicodendron' => 60, 'Arsenicum Album' => 55, 'Chamomilla' => 35,
                'Aconitum Napellus' => 30
            ],
            'rusty.*gate|rusty.*hinge|creaking.*joint' => [
                'Rhus Toxicodendron' => 70, 'Causticum' => 40
            ],
            'hip.*pain|pain.*hip|hip.*damage|hip.*problem|hip.*joint' => [
                'Rhus Toxicodendron' => 55, 'Colocynthis' => 45, 'Calcarea Fluorica' => 35,
                'Bryonia Alba' => 30, 'Argentum Metallicum' => 25
            ],
            'step.*difficult|not.*able.*step|cannot.*step|unable.*step|cant.*take.*step' => [
                'Rhus Toxicodendron' => 60, 'Gelsemium Sempervirens' => 50, 'Conium Maculatum' => 40,
                'Bryonia Alba' => 30
            ],
            'sprain|strain|overexertion|lifted.*heavy|heavy.*lifting' => [
                'Rhus Toxicodendron' => 60, 'Arnica Montana' => 55, 'Bryonia Alba' => 35,
                'Ruta Graveolens' => 30, 'Calcarea Carbonica' => 20
            ],
            
            // ================================================================  
            // BELLADONNA PATTERNS - Simple fever, sudden onset (CRITICAL)
            // ================================================================
            'fever' => [
                'Belladonna' => 50, 'Aconitum Napellus' => 45, 'Ferrum Phosphoricum' => 40,
                'Gelsemium Sempervirens' => 30, 'Bryonia Alba' => 25
            ],
            'high.*temperature|temperature.*high|fever.*38|fever.*39|fever.*40|fever.*102|fever.*103|fever.*104' => [
                'Belladonna' => 60, 'Aconitum Napellus' => 50, 'Arsenicum Album' => 35,
                'Ferrum Phosphoricum' => 30
            ],
            'burning.*hot|hot.*burning|radiating.*heat|skin.*burning.*hot' => [
                'Belladonna' => 55, 'Aconitum Napellus' => 45, 'Arsenicum Album' => 35,
                'Phosphorus' => 30
            ],
            'glassy.*eye|eyes.*glassy|dilated.*pupil|pupil.*dilated' => [
                'Belladonna' => 65, 'Stramonium' => 45, 'Opium' => 35, 'Hyoscyamus Niger' => 30
            ],
            'hot.*head.*cold.*feet|head.*hot.*feet.*cold|head.*burning' => [
                'Belladonna' => 70, 'Arnica Montana' => 35, 'Calcarea Carbonica' => 25
            ],
            'sudden.*violent|violent.*onset|intense.*sudden|acute.*intense' => [
                'Belladonna' => 60, 'Aconitum Napellus' => 55, 'Arsenicum Album' => 30
            ],
            
            // ================================================================
            // IRIS VERSICOLOR PATTERNS - Migraine with vomiting (CRITICAL)
            // ================================================================
            'migraine.*vomit|vomit.*migraine|sick.*headache|headache.*vomit' => [
                'Iris Versicolor' => 75, 'Sanguinaria Canadensis' => 55, 'Ipecacuanha' => 40,
                'Nux Vomica' => 30, 'Natrum Muriaticum' => 25
            ],
            'periodical.*headache|weekly.*headache|headache.*every.*week|regular.*headache|recurring.*headache' => [
                'Iris Versicolor' => 65, 'Natrum Muriaticum' => 50, 'Sanguinaria Canadensis' => 45,
                'Calcarea Carbonica' => 30
            ],
            'blurred.*vision.*headache|visual.*blur.*headache|headache.*vision.*disturb' => [
                'Iris Versicolor' => 65, 'Natrum Muriaticum' => 55, 'Gelsemium Sempervirens' => 40,
                'Cyclamen Europaeum' => 35
            ],
            'bilious.*headache|bile.*headache|hepatic.*headache|liver.*headache' => [
                'Iris Versicolor' => 70, 'Chelidonium Majus' => 50, 'Nux Vomica' => 40,
                'Bryonia Alba' => 30
            ],
            'burning.*tongue|stringy.*saliva|ropy.*vomit|thread.*vomit' => [
                'Iris Versicolor' => 60, 'Kali Bichromicum' => 50
            ],
            'sunday.*headache|weekend.*headache|rest.*day.*headache|holiday.*headache' => [
                'Iris Versicolor' => 60, 'Natrum Muriaticum' => 45, 'Nux Vomica' => 35
            ],
            
            // ================================================================
            // ALLIUM CEPA PATTERNS - Allergic rhinitis specifics (CRITICAL)
            // ================================================================
            'acrid.*discharge|burning.*discharge|excoriating.*discharge|discharge.*burn.*nostril' => [
                'Allium Cepa' => 70, 'Arsenicum Album' => 50, 'Arum Triphyllum' => 40,
                'Cepa' => 30
            ],
            'bland.*tear|lacrimation.*bland|eye.*water.*bland|eyes.*water.*not.*burn' => [
                'Allium Cepa' => 70, 'Euphrasia' => 20  // Opposite in Euphrasia
            ],
            // MORNING SNEEZING - Key pattern, high priority
            'sneezing.*morning|morning.*sneezing|worse.*morning.*sneez|sneez.*upon.*waking|morning.*violent.*sneez' => [
                'Nux Vomica' => 75, 'Sabadilla' => 70, 'Allium Cepa' => 60,
                'Arsenicum Album' => 45
            ],
            // Combination: Morning sneezing + Chilly patient = Nux Vomica or Arsenicum
            'morning.*sneez.*chill|chill.*morning.*sneez|sneez.*morning.*cold|chilly.*sneez' => [
                'Nux Vomica' => 85, 'Arsenicum Album' => 70, 'Sabadilla' => 55,
                'Silicea' => 30
            ],
            // Combination: Morning sneezing + Irritable = Nux Vomica
            'morning.*sneez.*irritab|irritab.*morning.*sneez|sneez.*irritab.*morning' => [
                'Nux Vomica' => 90, 'Chamomilla' => 30, 'Sabadilla' => 25
            ],
            'worse.*warm.*room|warm.*room.*worse|better.*cold.*air|cold.*air.*better|open.*air.*better' => [
                'Allium Cepa' => 60, 'Pulsatilla Nigricans' => 55, 'Apis Mellifica' => 40,
                'Carbo Vegetabilis' => 30
            ],
            'streaming.*eye|eye.*stream|profuse.*lacrimation|copious.*tear' => [
                'Allium Cepa' => 55, 'Euphrasia' => 55, 'Natrum Muriaticum' => 35
            ],
            'hoarse.*cough|cough.*hoarse|larynx.*raw|raw.*larynx|tickling.*larynx' => [
                'Allium Cepa' => 50, 'Phosphorus' => 45, 'Rumex Crispus' => 45,
                'Causticum' => 35
            ],
            'cold.*spring|spring.*cold|hay.*fever|autumn.*cold|seasonal.*rhinitis' => [
                'Allium Cepa' => 55, 'Sabadilla' => 50, 'Arsenicum Album' => 40,
                'Natrum Muriaticum' => 35, 'Dulcamara' => 30
            ],
            
            // ================================================================
            // ARSENICUM ALBUM - Asthma 12am-2am patterns (CRITICAL)
            // ================================================================
            'asthma.*12|12.*am.*asthma|midnight.*asthma|asthma.*after.*midnight' => [
                'Arsenicum Album' => 75, 'Kali Carbonicum' => 35, 'Drosera Rotundifolia' => 25
            ],
            'asthma.*1.*am|1.*am.*asthma|asthma.*2.*am|2.*am.*asthma' => [
                'Arsenicum Album' => 75, 'Kali Carbonicum' => 40, 'Drosera Rotundifolia' => 30
            ],
            'asthma.*burning|burning.*asthma|burn.*chest.*asthma' => [
                'Arsenicum Album' => 65, 'Phosphorus' => 45, 'Sulphur' => 35
            ],
            'asthma.*restless|restless.*asthma|cant.*lie.*asthma|must.*sit.*asthma' => [
                'Arsenicum Album' => 70, 'Carbo Vegetabilis' => 40, 'Kali Carbonicum' => 35
            ],
            'asthma.*anxious|anxiety.*asthma|fear.*asthma|panic.*asthma' => [
                'Arsenicum Album' => 70, 'Aconitum Napellus' => 45, 'Lobelia Inflata' => 35
            ],
            'asthma.*thirst.*sip|sip.*water.*asthma|small.*sip.*frequent' => [
                'Arsenicum Album' => 65, 'Phosphorus' => 30
            ],
            'asthma.*worse.*cold|cold.*air.*asthma|cold.*agg.*asthma' => [
                'Arsenicum Album' => 60, 'Hepar Sulphuris' => 50, 'Nux Vomica' => 35
            ],
            'asthma.*dust|dust.*asthma|dust.*trigger.*asthma' => [
                'Arsenicum Album' => 55, 'Blatta Orientalis' => 50, 'Pothos Foetidus' => 40,
                'Bromium' => 30
            ],
            'exacerbation.*asthma|asthma.*attack|acute.*asthma|asthma.*flare' => [
                'Arsenicum Album' => 50, 'Ipecacuanha' => 45, 'Antimonium Tartaricum' => 40,
                'Sambucus Nigra' => 35
            ],
        ];
        
        foreach ($clinicalMappings as $pattern => $remedies) {
            $isMatch = false;
            
            // Check if pattern uses regex (contains special chars)
            if (preg_match('/[.*|]/', $pattern)) {
                $isMatch = preg_match('/' . $pattern . '/i', $text);
            } else {
                $isMatch = stripos($text, $pattern) !== false;
            }
            
            if ($isMatch) {
                foreach ($remedies as $remedyName => $score) {
                    $key = strtolower($remedyName);
                    if (!isset($matches[$key])) {
                        // Fetch actual remedy data from database
                        $remedyData = $this->fetchRemedyByName($remedyName);
                        
                        $matches[$key] = [
                            'id' => $remedyData['id'] ?? 0,
                            'remedy_name' => $remedyData['remedy_name'] ?? $remedyName,
                            'common_name' => $remedyData['common_name'] ?? '',
                            'score' => 0,
                            'matched_term' => $pattern,
                            'keynote_symptoms' => $remedyData['keynote_symptoms'] ?? '',
                            'book_reference' => $remedyData['book_reference'] ?? ''
                        ];
                    }
                    $matches[$key]['score'] += $score;
                }
            }
        }
        
        // CLINICAL LOGIC: Apply contradictory modality PENALTIES
        $matches = $this->applyModalityPenalties($text, $matches);
        
        // CLINICAL LOGIC: Apply MODALITY-BASED SCORING (new enhanced system)
        $modalities = $this->extractModalities($text, $consultation);
        $matches = $this->applyModalityScoring($modalities, $matches);
        
        // CLINICAL LOGIC: Apply CAUSATION-BASED SCORING
        $causations = $this->extractCausationFactors($text, $consultation);
        $matches = $this->applyCausationScoring($causations, $matches);
        
        // CLINICAL LOGIC: Penalize NOSODES when no miasmatic indication
        $matches = $this->applyNosodePenalties($text, $matches);
        
        // CLINICAL LOGIC: Apply keynote COMBINATION bonuses
        $matches = $this->applyKeynoteCombinations($text, $matches);
        
        return $matches;
    }
    
    /**
     * Penalize nosodes when there's no clear miasmatic indication
     * Nosodes should NOT be first-line remedies without specific miasmatic signs
     */
    private function applyNosodePenalties($text, $matches) {
        // List of nosodes that require specific indications
        $nosodes = [
            'Tuberculinum' => [
                'required_patterns' => ['tubercul|tb|chronic.*cough|recurrent.*infection|travel.*desire|change.*desire|dissatisf|never.*well.*since'],
                'penalty' => 0.2  // 80% penalty if no tubercular indicators
            ],
            'Medorrhinum' => [
                'required_patterns' => ['sycotic|wart|gonorrhea|hurried|extremes|night.*person|better.*seaside|history.*std'],
                'penalty' => 0.2
            ],
            'Psorinum' => [
                'required_patterns' => ['psoric|dirty|offensive|despair|hopeless|hunger|cold', 'itch.*warmth|warmth.*worse.*itch'],
                'penalty' => 0.3  // Less penalty as it's often indicated for skin
            ],
            'Carcinosinum' => [
                'required_patterns' => ['cancer.*history|family.*cancer|perfectionist|fastidious|suppressed|never.*complain|desire.*travel'],
                'penalty' => 0.25
            ],
            'Syphilinum' => [
                'required_patterns' => ['syphil|destruc|ulcer|bone.*pain|night.*agg|history.*syphil'],
                'penalty' => 0.2
            ],
        ];
        
        foreach ($nosodes as $nosodeName => $rules) {
            $key = strtolower($nosodeName);
            if (!isset($matches[$key])) continue;
            
            // Check if any required pattern is present
            $hasIndication = false;
            foreach ($rules['required_patterns'] as $pattern) {
                if (preg_match('/' . $pattern . '/i', $text)) {
                    $hasIndication = true;
                    break;
                }
            }
            
            // If no miasmatic indication, apply penalty
            if (!$hasIndication) {
                $matches[$key]['score'] *= $rules['penalty'];
                $matches[$key]['nosode_penalty'] = 'No miasmatic indication found';
            }
        }
        
        // Also penalize SARCODES (organ remedies) when constitutional polychrests are clearly indicated
        // Sarcodes should be supportive, not first-line
        $sarcodes = [
            'Oophorinum' => 0.25,       // Ovarian extract - penalize unless specifically indicated
            'Pituitaria Glandula' => 0.2,
            'Thyroidinum' => 0.3,        // Less penalty - sometimes first line
            'Orchitinum' => 0.2,
            'Adrenalinum' => 0.25,
            'Pancreatinum' => 0.2,
            'Mammary Glands' => 0.2,
        ];
        
        // Check if constitutional symptoms are present (indicating polychrest is better choice)
        $hasConstitutionalSymptoms = preg_match('/weepy|tearful|mood.*swing|changeable|irritab|desire.*alone|solitude|emotional|mental|anxiety|hot.*patient|chilly|thermal/i', $text);
        
        if ($hasConstitutionalSymptoms) {
            foreach ($sarcodes as $sarcodeName => $penalty) {
                $key = strtolower($sarcodeName);
                if (isset($matches[$key])) {
                    $matches[$key]['score'] *= $penalty;
                    $matches[$key]['sarcode_penalty'] = 'Constitutional symptoms present - prefer polychrest';
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Apply penalties for contradictory modalities
     * In homeopathy, a remedy that is BETTER by warmth should NOT be prescribed 
     * when patient is WORSE from warmth - this is a contraindication
     */
    private function applyModalityPenalties($text, $matches) {
        // Contradictory modality rules: [patient_pattern => [remedy => penalty_multiplier]]
        $contradictions = [
            // Patient WORSE from warmth → penalize remedies BETTER from warmth
            'worse.*warmth|warmth.*worse|worse.*warm.*bed|warm.*bed.*worse|aggrav.*warmth|warmth.*agg' => [
                'Arsenicum Album' => 0.3,  // Arsenicum is markedly BETTER by warmth
                'Hepar Sulphuris' => 0.4,  // Very chilly, better warmth
                'Nux Vomica' => 0.5,       // Chilly, better warmth
                'Silicea' => 0.5,          // Chilly
                'Magnesia Phosphorica' => 0.4, // Better warmth
            ],
            
            // Patient BETTER from warmth → penalize remedies WORSE from warmth
            'better.*warmth|warmth.*better|amel.*warmth|warmth.*amel|relief.*warmth' => [
                'Sulphur' => 0.4,          // Worse from heat
                'Pulsatilla Nigricans' => 0.4, // Hot patient, worse warmth
                'Apis Mellifica' => 0.3,    // Markedly worse from heat
                'Lachesis Muta' => 0.5,    // Worse from heat
            ],
            
            // Patient WORSE in open air → penalize remedies BETTER in open air  
            'worse.*open.*air|open.*air.*worse|aggrav.*open.*air' => [
                'Allium Cepa' => 0.4,      // Better open air
                'Pulsatilla Nigricans' => 0.4, // Better open air
                'Argentum Nitricum' => 0.5,
            ],
            
            // Patient BETTER in open air → penalize remedies WORSE in open air
            'better.*open.*air|open.*air.*better|relief.*open.*air|feels.*better.*open' => [
                'Nux Vomica' => 0.5,       // Worse in open air
                'Hepar Sulphuris' => 0.4,  // Very sensitive to cold air
                'Silicea' => 0.5,          // Worse cold air
            ],
            
            // Patient is HOT/warm-blooded → penalize very chilly remedies
            'hot.*patient|warm.*blood|overheated|heat.*seeking' => [
                'Arsenicum Album' => 0.4,
                'Nux Vomica' => 0.5,
                'Hepar Sulphuris' => 0.4,
                'Silicea' => 0.5,
            ],
            
            // Patient is CHILLY → penalize hot remedies
            'chilly.*patient|cold.*patient|chilly|feels.*cold' => [
                'Sulphur' => 0.5,
                'Pulsatilla Nigricans' => 0.5,
                'Apis Mellifica' => 0.4,
            ],
        ];
        
        foreach ($contradictions as $pattern => $penalties) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                foreach ($penalties as $remedyName => $multiplier) {
                    $key = strtolower($remedyName);
                    if (isset($matches[$key])) {
                        $matches[$key]['score'] *= $multiplier;
                        $matches[$key]['penalty_applied'] = $pattern;
                    }
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Apply bonuses for keynote COMBINATIONS
     * In homeopathy, when multiple keynotes of a remedy appear together,
     * the remedy becomes STRONGLY indicated - this is the totality principle
     */
    private function applyKeynoteCombinations($text, $matches) {
        // Keynote combinations: multiple symptoms that together strongly indicate a remedy
        $combinations = [
            // ACONITUM NAPELLUS KEYNOTES - Sudden onset, fear, restlessness
            // Classic: Sudden high fever + fear of death + restlessness after cold wind exposure
            [
                'patterns' => ['sudden|rapidly', 'fever|hot'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 80
            ],
            [
                'patterns' => ['sudden.*fever|fever.*sudden', 'fear|fright|anxious'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 120
            ],
            [
                'patterns' => ['fear.*death|death.*fear|will.*die|thinks.*die|going.*die'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 100  // Single strong keynote
            ],
            [
                'patterns' => ['cold.*wind|wind.*cold|exposure.*wind', 'fever|hot|sudden'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 130
            ],
            [
                'patterns' => ['sudden', 'restless', 'fear|anxious'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 150  // Classic triad
            ],
            [
                'patterns' => ['fever.*dry|dry.*skin|no.*sweat', 'restless|tossing'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 100
            ],
            [
                'patterns' => ['face.*red|red.*face|flushed', 'fever.*sudden|sudden.*fever'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 90
            ],
            [
                'patterns' => ['first.*stage|beginning|onset|early.*stage', 'fever'],
                'remedy' => 'Aconitum Napellus',
                'bonus' => 80
            ],
            
            // Allium Cepa KEYNOTES: watery discharge + better open air + sneezing
            [
                'patterns' => ['watery.*discharge|discharge.*watery|profuse.*nasal|copious', 'better.*open.*air|open.*air|feels.*better.*open'],
                'remedy' => 'Allium Cepa',
                'bonus' => 80  // Strong bonus when BOTH present
            ],
            [
                'patterns' => ['watery.*discharge|discharge.*watery|profuse', 'sneez', 'better.*open.*air|open.*air'],
                'remedy' => 'Allium Cepa',
                'bonus' => 120  // Very strong bonus when ALL THREE present
            ],
            
            // Sabadilla KEYNOTES: paroxysmal sneezing + hay fever
            [
                'patterns' => ['sneez.*succession|bouts.*sneez|10.*15.*sneez|paroxysmal.*sneez', 'hay.*fever|allergic.*rhinitis|rhinitis'],
                'remedy' => 'Sabadilla',
                'bonus' => 60
            ],
            [
                'patterns' => ['sneez.*succession|bouts.*sneez|10.*15.*sneez', 'hay.*fever|allergic.*rhinitis'],
                'remedy' => 'Sabadilla Officinalis',
                'bonus' => 60
            ],
            
            // Graphites KEYNOTES: eczema + oozing/sticky + cracks
            [
                'patterns' => ['eczema|dermatitis', 'crack|fissure|behind.*ear'],
                'remedy' => 'Graphites',
                'bonus' => 70
            ],
            
            // Sulphur for chronic skin: itching + worse warmth + worse night
            [
                'patterns' => ['itch', 'worse.*warmth|warmth.*worse|warm.*bed', 'worse.*night|night.*itch'],
                'remedy' => 'Sulphur',
                'bonus' => 50  // Reduced - it's constitutional, not acute
            ],
            
            // Psorinum: itching + worse warmth of bed (even more specific than Sulphur)
            [
                'patterns' => ['itch', 'warmth.*bed|warm.*bed|heat.*bed'],
                'remedy' => 'Psorinum',
                'bonus' => 70
            ],
            
            // Arsenicum KEYNOTES: burning + better warmth + anxiety + midnight aggravation
            [
                'patterns' => ['burn', 'better.*warmth|warmth.*better', 'anxious|anxiety|restless'],
                'remedy' => 'Arsenicum Album',
                'bonus' => 80
            ],
            [
                'patterns' => ['2.*am|3.*am|midnight', 'anxious|restless', 'chilly'],
                'remedy' => 'Arsenicum Album', 
                'bonus' => 100
            ],
            
            // ARGENTUM NITRICUM KEYNOTES - CLASSIC COMBINATION
            // Anticipatory anxiety + diarrhea + hurried + craves sweets = TEXTBOOK Arg-Nit
            [
                'patterns' => ['anticipat|before.*event|before.*exam|before.*presentation', 'diarrhea|loose.*stool'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 100  // Very strong - this is THE keynote
            ],
            [
                'patterns' => ['anticipat|before.*event', 'hurried|impuls|hasty'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 80
            ],
            [
                'patterns' => ['anticipat|anxiety.*before|fear.*event', 'crav.*sweet|sweet.*crav'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 90
            ],
            [
                'patterns' => ['anticipat', 'diarrhea|loose.*stool', 'hurried|impuls'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 140  // Triple keynote match
            ],
            [
                'patterns' => ['fear.*public|public.*speak|stage.*fright|fear.*judgment', 'diarrhea|loose.*stool|ibs'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 100
            ],
            [
                'patterns' => ['anxiety', 'crav.*sweet', 'diarrhea|loose'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 120
            ],
            
            // GELSEMIUM KEYNOTES - Stage fright + paralysis/weakness + trembling
            [
                'patterns' => ['stage.*fright|fear.*public|fear.*audience', 'trembl|shak|weak'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 90
            ],
            [
                'patterns' => ['anticipat|before.*event', 'trembl|shak', 'paralysis|frozen|cant.*move'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 110
            ],
            [
                'patterns' => ['anxiety.*before|anticipat', 'diarrhea', 'weak|trembl'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 80
            ],
            
            // LYCOPODIUM KEYNOTES - anticipatory + bloating/gas + lack of confidence
            [
                'patterns' => ['anticipat|fear.*failure|lack.*confiden', 'bloat|gas|flatulen'],
                'remedy' => 'Lycopodium Clavatum',
                'bonus' => 70
            ],
            [
                'patterns' => ['anxiety', 'bloat|flatulen', 'afternoon.*agg|4.*pm|5.*pm'],
                'remedy' => 'Lycopodium Clavatum',
                'bonus' => 80
            ],
            
            // NUX VOMICA KEYNOTES - TENSION HEADACHE + WORK STRESS
            // Irritability + work stress + constipation + chilly = TEXTBOOK Nux Vomica
            [
                'patterns' => ['headache|head.*ache', 'work.*stress|stress|deadline|overwork', 'irritab'],
                'remedy' => 'Nux Vomica',
                'bonus' => 120  // Strong match for tension headache
            ],
            [
                'patterns' => ['headache', 'constipat', 'irritab|angry'],
                'remedy' => 'Nux Vomica',
                'bonus' => 100
            ],
            [
                'patterns' => ['headache', 'chilly', 'irritab'],
                'remedy' => 'Nux Vomica',
                'bonus' => 90
            ],
            [
                'patterns' => ['work.*stress|overwork|sedentary', 'constipat', 'chilly'],
                'remedy' => 'Nux Vomica',
                'bonus' => 100
            ],
            [
                'patterns' => ['headache', 'work.*stress|deadline|office', 'fatigue|exhaust'],
                'remedy' => 'Nux Vomica',
                'bonus' => 110
            ],
            [
                'patterns' => ['irritab.*family|family.*irritab|snappy|cross.*home', 'work.*stress|business'],
                'remedy' => 'Nux Vomica',
                'bonus' => 80
            ],
            
            // BRYONIA ALBA KEYNOTES - headache worse motion + better pressure + constipation
            [
                'patterns' => ['headache', 'worse.*motion|motion.*agg|movement.*worse', 'better.*pressure|pressure.*better|lying.*still'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 130  // Triple keynote match - very strong
            ],
            [
                'patterns' => ['headache', 'worse.*motion|movement.*worse', 'constipat'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 110
            ],
            [
                'patterns' => ['headache', 'irritab', 'constipat'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 90
            ],
            [
                'patterns' => ['throbbing.*head|headache.*throb', 'worse.*motion|motion.*agg'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 100
            ],
            [
                'patterns' => ['headache', 'dry.*mouth|thirst', 'constipat'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 90
            ],
            
            // GELSEMIUM for TENSION HEADACHE with NECK STIFFNESS
            [
                'patterns' => ['headache', 'neck.*stiff|stiff.*neck', 'fatigue|weak|heavy'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 120
            ],
            [
                'patterns' => ['headache.*occiput|occiput.*headache', 'neck.*stiff|stiff.*neck'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 110
            ],
            [
                'patterns' => ['headache', 'neck.*stiff', 'anxiety|anxious|stress'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 100
            ],
            [
                'patterns' => ['dull.*headache|heavy.*headache|headache.*dull|headache.*heavy', 'neck'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 80
            ],
            
            // CIMICIFUGA (ACTAEA RACEMOSA) for neck tension + headache
            [
                'patterns' => ['neck.*stiff|stiff.*neck|neck.*tension', 'headache', 'muscle.*tense|muscle.*tight'],
                'remedy' => 'Cimicifuga Racemosa',
                'bonus' => 100
            ],
            [
                'patterns' => ['neck.*tension', 'occiput|back.*head'],
                'remedy' => 'Cimicifuga Racemosa',
                'bonus' => 80
            ],
            
            // KALI PHOSPHORICUM for mental exhaustion headache
            [
                'patterns' => ['headache', 'mental.*fatigue|mental.*exhaust|brain.*fag|overwork', 'concentrat'],
                'remedy' => 'Kali Phosphoricum',
                'bonus' => 100
            ],
            [
                'patterns' => ['nervous.*exhaust|mental.*strain|intellectual', 'fatigue|weak'],
                'remedy' => 'Kali Phosphoricum',
                'bonus' => 90
            ],
            
            // NATRUM MURIATICUM for stress headache
            [
                'patterns' => ['headache', 'stress|grief|suppressed.*emotion', 'worse.*sun|worse.*light'],
                'remedy' => 'Natrum Muriaticum',
                'bonus' => 90
            ],
            [
                'patterns' => ['headache.*morning|morning.*headache', 'stress'],
                'remedy' => 'Natrum Muriaticum',
                'bonus' => 80
            ],
            
            // PULSATILLA KEYNOTES - CLASSIC FEMALE REMEDY
            // Irregular menses + weepy + changeable + hot + better open air = TEXTBOOK Pulsatilla
            [
                'patterns' => ['irregular.*period|period.*irregular|pcos', 'weepy|tearful|cry'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 120
            ],
            [
                'patterns' => ['irregular.*menses|menses.*irregular', 'changeable.*mood|mood.*swing'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 110
            ],
            [
                'patterns' => ['period|menses', 'weepy|tearful', 'hot|warm.*blood|worse.*heat'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 130  // Triple keynote
            ],
            [
                'patterns' => ['period|menses', 'bloating', 'mood.*swing|changeable'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 100
            ],
            [
                'patterns' => ['hot|warm.*blood|worse.*warm', 'weepy|tearful|emotional'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 90
            ],
            [
                'patterns' => ['better.*open.*air|open.*air.*better', 'weepy|changeable'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 100
            ],
            [
                'patterns' => ['acne.*period|period.*acne|pms|premenstrual', 'weepy|emotional'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 90
            ],
            [
                'patterns' => ['pcos|polycystic', 'hot|warm', 'irregular'],
                'remedy' => 'Pulsatilla Nigricans',
                'bonus' => 140  // Very strong for PCOS
            ],
            
            // SEPIA KEYNOTES - HORMONAL + INDIFFERENCE + DESIRE SOLITUDE
            // Hormonal + irritable + desire solitude + bearing down = TEXTBOOK Sepia
            [
                'patterns' => ['period|menses|hormonal', 'desire.*alone|want.*alone|solitude', 'irritab'],
                'remedy' => 'Sepia',
                'bonus' => 140  // Triple keynote - very strong
            ],
            [
                'patterns' => ['pcos|polycystic|irregular.*period', 'irritab.*premenstrual|premenstrual.*irritab'],
                'remedy' => 'Sepia',
                'bonus' => 120
            ],
            [
                'patterns' => ['hormonal', 'hirsutism|acne.*chin|facial.*hair'],
                'remedy' => 'Sepia',
                'bonus' => 110
            ],
            [
                'patterns' => ['period|menses', 'indifferen|aversion.*family|tired.*family'],
                'remedy' => 'Sepia',
                'bonus' => 130
            ],
            [
                'patterns' => ['irregular.*period', 'clot|heavy.*flow', 'irritab'],
                'remedy' => 'Sepia',
                'bonus' => 100
            ],
            [
                'patterns' => ['desire.*solitude|want.*alone', 'weepy.*times|tearful.*times'],
                'remedy' => 'Sepia',
                'bonus' => 90
            ],
            [
                'patterns' => ['acne.*jawline|chin.*acne', 'period|hormonal'],
                'remedy' => 'Sepia',
                'bonus' => 100
            ],
            
            // LACHESIS KEYNOTES - Left-sided + worse before menses + hot
            [
                'patterns' => ['left.*ovary|ovary.*left', 'worse.*before.*period|premenstrual'],
                'remedy' => 'Lachesis Muta',
                'bonus' => 120
            ],
            [
                'patterns' => ['pms|premenstrual', 'hot|warm.*blood', 'irritab|jealous'],
                'remedy' => 'Lachesis Muta',
                'bonus' => 110
            ],
            [
                'patterns' => ['worse.*before.*menses|worse.*before.*period', 'better.*flow|relief.*menses'],
                'remedy' => 'Lachesis Muta',
                'bonus' => 130  // Classic Lachesis keynote
            ],
            
            // NATRUM MURIATICUM for hormonal/emotional
            [
                'patterns' => ['period|hormonal', 'grief|suppressed.*emotion', 'desire.*alone|solitude'],
                'remedy' => 'Natrum Muriaticum',
                'bonus' => 100
            ],
            [
                'patterns' => ['consolation.*agg|worse.*consolation', 'weepy|tearful', 'alone'],
                'remedy' => 'Natrum Muriaticum',
                'bonus' => 120
            ],
            
            // NUX VOMICA KEYNOTES - GERD/DIGESTIVE + MENTAL PICTURE
            // Hurried businessman + stimulants + digestive = TEXTBOOK Nux Vomica
            [
                'patterns' => ['heartburn|acid.*reflux|gerd|acidity', 'hurried|impatient|businessman|competitive'],
                'remedy' => 'Nux Vomica',
                'bonus' => 140  // Very strong match
            ],
            [
                'patterns' => ['digestive|stomach|reflux|heartburn', 'desires.*stimulant|coffee|spicy'],
                'remedy' => 'Nux Vomica',
                'bonus' => 130
            ],
            [
                'patterns' => ['heartburn|reflux|acidity', 'businessman|work.*stress|business.*stress'],
                'remedy' => 'Nux Vomica',
                'bonus' => 130
            ],
            [
                'patterns' => ['bloating|nausea', 'hurried|impatient', 'irritab'],
                'remedy' => 'Nux Vomica',
                'bonus' => 120
            ],
            [
                'patterns' => ['irregular.*eating|late.*night|rich.*food', 'stomach|digestive|reflux'],
                'remedy' => 'Nux Vomica',
                'bonus' => 110
            ],
            [
                'patterns' => ['hurried|impatient|ambitious', 'digestive|stomach', 'sedentary|desk'],
                'remedy' => 'Nux Vomica',
                'bonus' => 130
            ],
            [
                'patterns' => ['sour.*belch|acid.*eructation|water.*brash', 'stress|businessman|work'],
                'remedy' => 'Nux Vomica',
                'bonus' => 110
            ],
            [
                'patterns' => ['desires.*stimulant|coffee', 'desires.*spicy|spicy.*food'],
                'remedy' => 'Nux Vomica',
                'bonus' => 100  // Double craving keynote
            ],
            
            // ROBINIA for severe acidity
            [
                'patterns' => ['heartburn|acidity|acid.*reflux', 'sour.*belch|sour.*eructation|water.*brash'],
                'remedy' => 'Robinia Pseudoacacia',
                'bonus' => 130
            ],
            [
                'patterns' => ['burning.*esophagus|esophagus.*burn', 'sour|acid'],
                'remedy' => 'Robinia Pseudoacacia',
                'bonus' => 120
            ],
            [
                'patterns' => ['acidity|hyperacidity', 'worse.*night|night.*agg'],
                'remedy' => 'Robinia Pseudoacacia',
                'bonus' => 100
            ],
            
            // LYCOPODIUM for digestive with bloating
            [
                'patterns' => ['bloating|distension', 'digestive|stomach', '4.*pm|5.*pm|afternoon|evening'],
                'remedy' => 'Lycopodium Clavatum',
                'bonus' => 120
            ],
            [
                'patterns' => ['bloating', 'flatulence|gas', 'anxiety|low.*confidence'],
                'remedy' => 'Lycopodium Clavatum',
                'bonus' => 110
            ],
            
            // CARBO VEGETABILIS for bloating with gas
            [
                'patterns' => ['bloating|distension', 'belching|eructation|gas', 'weakness|faint'],
                'remedy' => 'Carbo Vegetabilis',
                'bonus' => 110
            ],
            [
                'patterns' => ['bloating', 'wants.*air|desires.*air|fan|fanning'],
                'remedy' => 'Carbo Vegetabilis',
                'bonus' => 120
            ],
            
            // PHOSPHORUS for burning + cold drinks
            [
                'patterns' => ['burning.*stomach|stomach.*burn|burning.*esophagus', 'desires.*cold.*drink|cold.*drink'],
                'remedy' => 'Phosphorus',
                'bonus' => 120
            ],
            [
                'patterns' => ['vomiting', 'desires.*cold.*drink|thirst.*cold', 'vomit.*warm'],
                'remedy' => 'Phosphorus',
                'bonus' => 110
            ],
            
            // ARGENTUM NITRICUM for digestive + anxiety
            [
                'patterns' => ['heartburn|acidity|bloating', 'anxiety|anxious|nervous', 'hurried|anticipat'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 100
            ],
            [
                'patterns' => ['digestive|stomach', 'crav.*sweet|sweets', 'anxiety'],
                'remedy' => 'Argentum Nitricum',
                'bonus' => 110
            ],
            
            // RHUS TOXICODENDRON KEYNOTES - BACK PAIN + STIFFNESS
            // Morning stiffness + better motion + worse rest = TEXTBOOK Rhus Tox
            [
                'patterns' => ['back.*pain|lower.*back|lumbar|lumbago', 'stiffness.*morning|morning.*stiff'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 140  // Very strong match
            ],
            [
                'patterns' => ['back.*pain|lumbar', 'better.*motion|motion.*better|movement.*amel'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 140
            ],
            [
                'patterns' => ['back.*pain|lumbar', 'worse.*rest|rest.*worse'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 140
            ],
            [
                'patterns' => ['stiff', 'better.*motion|motion.*amel', 'worse.*rest'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 160  // Triple keynote
            ],
            [
                'patterns' => ['back.*pain', 'worse.*sitting|sitting.*worse|prolonged.*sit|desk'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 130
            ],
            [
                'patterns' => ['back.*pain|sciatica', 'sedentary|desk.*work', 'stiff'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 140
            ],
            [
                'patterns' => ['muscle.*spasm|paraspinal', 'stiff', 'worse.*rest|better.*motion'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 150
            ],
            [
                'patterns' => ['joint.*pain|arthritis', 'morning.*stiff|stiff.*morning', 'better.*motion'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 150
            ],
            [
                'patterns' => ['worse.*damp|damp.*worse|wet.*weather', 'stiff|pain'],
                'remedy' => 'Rhus Toxicodendron',
                'bonus' => 120
            ],
            
            // BRYONIA for back pain worse motion
            [
                'patterns' => ['back.*pain|lumbar', 'worse.*motion|motion.*worse|movement.*agg'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 140
            ],
            [
                'patterns' => ['back.*pain', 'better.*rest|rest.*better|lying.*still'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 130
            ],
            [
                'patterns' => ['pain', 'worse.*motion', 'better.*pressure|pressure.*amel'],
                'remedy' => 'Bryonia Alba',
                'bonus' => 150  // Triple keynote
            ],
            
            // COLOCYNTHIS for sciatica
            [
                'patterns' => ['sciatica|sciatic', 'cramping|cramp|drawing'],
                'remedy' => 'Colocynthis',
                'bonus' => 120
            ],
            [
                'patterns' => ['sciatica|leg.*pain', 'better.*pressure|pressure.*better|hard.*pressure'],
                'remedy' => 'Colocynthis',
                'bonus' => 130
            ],
            [
                'patterns' => ['sciatica|leg.*pain', 'better.*flex|flexion|bending|drawing.*up'],
                'remedy' => 'Colocynthis',
                'bonus' => 130
            ],
            
            // MAGNESIA PHOSPHORICA for right-sided sciatica
            [
                'patterns' => ['sciatica|nerve.*pain', 'right.*side|right.*leg'],
                'remedy' => 'Magnesia Phosphorica',
                'bonus' => 120
            ],
            [
                'patterns' => ['cramping|spasm', 'better.*warmth|warmth.*better|heat.*amel'],
                'remedy' => 'Magnesia Phosphorica',
                'bonus' => 110
            ],
            
            // KALI CARBONICUM for back pain with weakness
            [
                'patterns' => ['back.*pain|lumbar', 'weakness|weak', '3.*am|early.*morning'],
                'remedy' => 'Kali Carbonicum',
                'bonus' => 120
            ],
            [
                'patterns' => ['back.*pain', 'worse.*lying|lying.*worse', 'must.*sit.*up'],
                'remedy' => 'Kali Carbonicum',
                'bonus' => 110
            ],
            
            // NUX VOMICA for sedentary + back pain
            [
                'patterns' => ['back.*pain', 'sedentary|desk.*work|sitting.*work', 'stress|irritab'],
                'remedy' => 'Nux Vomica',
                'bonus' => 130
            ],
            [
                'patterns' => ['back.*pain|lumbar', 'must.*turn.*over|turn.*in.*bed'],
                'remedy' => 'Nux Vomica',
                'bonus' => 120
            ],
            
            // GELSEMIUM for back pain with fatigue/anxiety
            [
                'patterns' => ['back.*pain|muscle.*pain', 'fatigue|weak|exhaust', 'anxiety|anxious'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 110
            ],
            [
                'patterns' => ['muscle.*tension|muscle.*ache', 'stress|anxiety', 'fatigue'],
                'remedy' => 'Gelsemium Sempervirens',
                'bonus' => 100
            ],
        ];
        
        foreach ($combinations as $combo) {
            $allMatch = true;
            foreach ($combo['patterns'] as $pattern) {
                if (!preg_match('/' . $pattern . '/i', $text)) {
                    $allMatch = false;
                    break;
                }
            }
            
            if ($allMatch) {
                $key = strtolower($combo['remedy']);
                if (isset($matches[$key])) {
                    $matches[$key]['score'] += $combo['bonus'];
                    $matches[$key]['combination_bonus'] = true;
                } else {
                    // Add remedy if not already present but combination matches
                    $remedyData = $this->fetchRemedyByName($combo['remedy']);
                    if (!empty($remedyData)) {
                        $matches[$key] = [
                            'id' => $remedyData['id'] ?? 0,
                            'remedy_name' => $remedyData['remedy_name'] ?? $combo['remedy'],
                            'common_name' => $remedyData['common_name'] ?? '',
                            'score' => $combo['bonus'],
                            'matched_term' => 'keynote_combination',
                            'keynote_symptoms' => $remedyData['keynote_symptoms'] ?? '',
                            'book_reference' => $remedyData['book_reference'] ?? '',
                            'combination_bonus' => true
                        ];
                    }
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Fetch remedy data by name from database
     */
    private function fetchRemedyByName($name) {
        static $cache = [];
        
        $key = strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        
        // Try exact match first
        $result = DB::query(
            "SELECT id, remedy_name, common_name, keynote_symptoms, clinical_indications, 
                    mind_symptoms, book_reference 
             FROM remedies 
             WHERE LOWER(remedy_name) = ? LIMIT 1",
            [strtolower($name)]
        );
        
        // If no exact match, try partial match
        if (empty($result)) {
            $result = DB::query(
                "SELECT id, remedy_name, common_name, keynote_symptoms, clinical_indications, 
                        mind_symptoms, book_reference 
                 FROM remedies 
                 WHERE LOWER(remedy_name) LIKE ? LIMIT 1",
                ['%' . strtolower($name) . '%']
            );
        }
        
        $cache[$key] = $result[0] ?? [];
        return $cache[$key];
    }
}
