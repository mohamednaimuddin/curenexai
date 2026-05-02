<?php
/**
 * Generate Vector Embeddings for Homeopathic Database
 * 
 * This script generates embeddings for:
 * - Remedies (materia medica)
 * - Repertory rubrics
 * - Diseases
 * 
 * Run from command line: php generate_embeddings.php [type]
 * Types: remedies, repertory, diseases, all
 */

// Set execution time limit (may take a while for large datasets)
set_time_limit(0);
ini_set('memory_limit', '512M');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/embeddings.log');
error_reporting(E_ALL);

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/embeddings.php';

// CLI output helper
function output($message, $type = 'info') {
    $colors = [
        'info' => "\033[36m",    // Cyan
        'success' => "\033[32m", // Green
        'warning' => "\033[33m", // Yellow
        'error' => "\033[31m",   // Red
        'reset' => "\033[0m"
    ];
    
    $prefix = '';
    if (php_sapi_name() === 'cli') {
        $prefix = $colors[$type] ?? '';
    }
    $suffix = php_sapi_name() === 'cli' ? $colors['reset'] : '';
    
    $timestamp = date('Y-m-d H:i:s');
    echo "{$prefix}[{$timestamp}] {$message}{$suffix}" . PHP_EOL;
}

/**
 * Build text content for remedy embedding
 * Combines all relevant fields into searchable text
 */
function buildRemedyText($remedy) {
    $parts = [];
    
    // Primary identification
    $parts[] = "Remedy: " . ($remedy['remedy_name'] ?? '');
    if (!empty($remedy['common_name'])) {
        $parts[] = "Common name: " . $remedy['common_name'];
    }
    if (!empty($remedy['family'])) {
        $parts[] = "Family: " . $remedy['family'];
    }
    
    // Key symptoms and indications (most important for matching)
    if (!empty($remedy['keynote_symptoms'])) {
        $parts[] = "Keynote symptoms: " . $remedy['keynote_symptoms'];
    }
    if (!empty($remedy['clinical_indications'])) {
        $parts[] = "Clinical indications: " . $remedy['clinical_indications'];
    }
    if (!empty($remedy['characteristic_symptoms'])) {
        $parts[] = "Characteristic symptoms: " . $remedy['characteristic_symptoms'];
    }
    
    // Mental and general symptoms
    if (!empty($remedy['mind_symptoms'])) {
        $parts[] = "Mind symptoms: " . $remedy['mind_symptoms'];
    }
    
    // Regional symptoms (combined for brevity)
    $regionalParts = [];
    $regions = ['head', 'eye', 'ear', 'nose', 'face', 'mouth', 'throat', 
                'stomach', 'abdomen', 'rectum', 'urinary', 'male', 'female',
                'respiratory', 'heart', 'back', 'extremities', 'skin', 'sleep', 'fever'];
    
    foreach ($regions as $region) {
        $field = $region . '_symptoms';
        if (!empty($remedy[$field])) {
            $regionalParts[] = ucfirst($region) . ": " . $remedy[$field];
        }
    }
    if (!empty($regionalParts)) {
        $parts[] = "Regional symptoms: " . implode('; ', $regionalParts);
    }
    
    // Modalities
    if (!empty($remedy['modalities'])) {
        $parts[] = "Modalities: " . $remedy['modalities'];
    }
    if (!empty($remedy['aggravation'])) {
        $parts[] = "Worse from: " . $remedy['aggravation'];
    }
    if (!empty($remedy['amelioration'])) {
        $parts[] = "Better from: " . $remedy['amelioration'];
    }
    
    return implode("\n", $parts);
}

/**
 * Build text content for repertory rubric embedding
 */
function buildRepertoryText($rubric) {
    $parts = [];
    
    $parts[] = "Rubric: " . ($rubric['rubric'] ?? '');
    
    if (!empty($rubric['complete_rubric'])) {
        $parts[] = "Full rubric: " . $rubric['complete_rubric'];
    }
    
    $parts[] = "Category: " . ($rubric['category'] ?? 'general');
    
    if (!empty($rubric['sub_category'])) {
        $parts[] = "Sub-category: " . $rubric['sub_category'];
    }
    
    // Add related remedies to the text for better semantic matching
    if (!empty($rubric['remedies'])) {
        $parts[] = "Indicated remedies: " . $rubric['remedies'];
    }
    
    return implode("\n", $parts);
}

/**
 * Build text content for disease embedding
 */
function buildDiseaseText($disease) {
    $parts = [];
    
    $parts[] = "Disease: " . ($disease['disease_name'] ?? '');
    
    if (!empty($disease['description'])) {
        $parts[] = "Description: " . $disease['description'];
    }
    if (!empty($disease['common_symptoms'])) {
        $parts[] = "Common symptoms: " . $disease['common_symptoms'];
    }
    if (!empty($disease['keynote_symptoms'])) {
        $parts[] = "Keynote symptoms: " . $disease['keynote_symptoms'];
    }
    if (!empty($disease['differential_diagnosis'])) {
        $parts[] = "Differential diagnosis: " . $disease['differential_diagnosis'];
    }
    
    return implode("\n", $parts);
}

/**
 * Generate embeddings for remedies
 */
function generateRemedyEmbeddings() {
    output("Starting remedy embeddings generation...", 'info');
    
    try {
        $embeddingsService = new EmbeddingsService();
        $vectorStore = new VectorStore();
        
        // Get all remedies
        $remedies = DB::query("SELECT * FROM remedies ORDER BY id");
        $totalRemedies = count($remedies);
        
        output("Found {$totalRemedies} remedies to process", 'info');
        
        if ($totalRemedies === 0) {
            output("No remedies found in database", 'warning');
            return;
        }
        
        // Log the generation start
        DB::query(
            "INSERT INTO embedding_generation_log (entity_type, total_entities, status, started_at) 
             VALUES ('remedy', ?, 'running', NOW())",
            [$totalRemedies]
        );
        $logId = DB::lastInsertId();
        
        // Process in batches
        $batchSize = 20;
        $processed = 0;
        $failed = 0;
        
        for ($i = 0; $i < $totalRemedies; $i += $batchSize) {
            $batch = array_slice($remedies, $i, $batchSize);
            $texts = [];
            $remedyIds = [];
            
            foreach ($batch as $remedy) {
                $text = buildRemedyText($remedy);
                $texts[] = $text;
                $remedyIds[] = [
                    'id' => $remedy['id'],
                    'name' => $remedy['remedy_name'],
                    'text' => $text
                ];
            }
            
            try {
                // Generate embeddings for batch
                $embeddings = $embeddingsService->generateEmbeddings($texts);
                
                // Store embeddings
                $storeItems = [];
                foreach ($embeddings as $index => $embedding) {
                    if (!empty($embedding)) {
                        $storeItems[] = [
                            'entity_type' => 'remedy',
                            'entity_id' => $remedyIds[$index]['id'],
                            'embedding' => $embedding,
                            'text_content' => substr($remedyIds[$index]['text'], 0, 500) // Store excerpt
                        ];
                        $processed++;
                    } else {
                        $failed++;
                        output("Failed to generate embedding for: " . $remedyIds[$index]['name'], 'warning');
                    }
                }
                
                $vectorStore->storeBatch($storeItems);
                
                $progress = round(($i + count($batch)) / $totalRemedies * 100, 1);
                output("Progress: {$progress}% ({$processed} processed, {$failed} failed)", 'info');
                
                // Update log
                DB::query(
                    "UPDATE embedding_generation_log SET processed_entities = ?, failed_entities = ? WHERE id = ?",
                    [$processed, $failed, $logId]
                );
                
            } catch (Exception $e) {
                $failed += count($batch);
                output("Batch error: " . $e->getMessage(), 'error');
                
                // If quota error, wait and retry
                if (strpos($e->getMessage(), 'quota') !== false || strpos($e->getMessage(), '429') !== false) {
                    output("Rate limit hit, waiting 60 seconds...", 'warning');
                    sleep(60);
                    $i -= $batchSize; // Retry this batch
                }
            }
            
            // Small delay between batches
            usleep(200000); // 200ms
        }
        
        // Update log as completed
        DB::query(
            "UPDATE embedding_generation_log SET status = 'completed', processed_entities = ?, failed_entities = ?, completed_at = NOW() WHERE id = ?",
            [$processed, $failed, $logId]
        );
        
        output("Remedy embeddings completed: {$processed} successful, {$failed} failed", 'success');
        
    } catch (Exception $e) {
        output("Error generating remedy embeddings: " . $e->getMessage(), 'error');
        if (isset($logId)) {
            DB::query(
                "UPDATE embedding_generation_log SET status = 'failed', error_message = ? WHERE id = ?",
                [$e->getMessage(), $logId]
            );
        }
    }
}

/**
 * Generate embeddings for repertory rubrics
 */
function generateRepertoryEmbeddings() {
    output("Starting repertory embeddings generation...", 'info');
    
    try {
        $embeddingsService = new EmbeddingsService();
        $vectorStore = new VectorStore();
        
        // Get all repertory rubrics with their associated remedies
        $rubrics = DB::query(
            "SELECT r.*, 
                    GROUP_CONCAT(DISTINCT rem.remedy_name ORDER BY rr.grade DESC SEPARATOR ', ') as remedies
             FROM repertory r
             LEFT JOIN repertory_remedies rr ON r.id = rr.repertory_id
             LEFT JOIN remedies rem ON rr.remedy_id = rem.id
             GROUP BY r.id
             ORDER BY r.id"
        );
        $totalRubrics = count($rubrics);
        
        output("Found {$totalRubrics} rubrics to process", 'info');
        
        if ($totalRubrics === 0) {
            output("No rubrics found in database", 'warning');
            return;
        }
        
        // Log the generation start
        DB::query(
            "INSERT INTO embedding_generation_log (entity_type, total_entities, status, started_at) 
             VALUES ('repertory', ?, 'running', NOW())",
            [$totalRubrics]
        );
        $logId = DB::lastInsertId();
        
        // Process in batches
        $batchSize = 50; // Repertory texts are shorter, can batch more
        $processed = 0;
        $failed = 0;
        
        for ($i = 0; $i < $totalRubrics; $i += $batchSize) {
            $batch = array_slice($rubrics, $i, $batchSize);
            $texts = [];
            $rubricIds = [];
            
            foreach ($batch as $rubric) {
                $text = buildRepertoryText($rubric);
                $texts[] = $text;
                $rubricIds[] = [
                    'id' => $rubric['id'],
                    'rubric' => $rubric['rubric'],
                    'text' => $text
                ];
            }
            
            try {
                $embeddings = $embeddingsService->generateEmbeddings($texts);
                
                $storeItems = [];
                foreach ($embeddings as $index => $embedding) {
                    if (!empty($embedding)) {
                        $storeItems[] = [
                            'entity_type' => 'repertory',
                            'entity_id' => $rubricIds[$index]['id'],
                            'embedding' => $embedding,
                            'text_content' => substr($rubricIds[$index]['text'], 0, 500)
                        ];
                        $processed++;
                    } else {
                        $failed++;
                    }
                }
                
                $vectorStore->storeBatch($storeItems);
                
                $progress = round(($i + count($batch)) / $totalRubrics * 100, 1);
                output("Progress: {$progress}% ({$processed} processed)", 'info');
                
                DB::query(
                    "UPDATE embedding_generation_log SET processed_entities = ?, failed_entities = ? WHERE id = ?",
                    [$processed, $failed, $logId]
                );
                
            } catch (Exception $e) {
                $failed += count($batch);
                output("Batch error: " . $e->getMessage(), 'error');
                
                if (strpos($e->getMessage(), 'quota') !== false || strpos($e->getMessage(), '429') !== false) {
                    output("Rate limit hit, waiting 60 seconds...", 'warning');
                    sleep(60);
                    $i -= $batchSize;
                }
            }
            
            usleep(200000);
        }
        
        DB::query(
            "UPDATE embedding_generation_log SET status = 'completed', processed_entities = ?, failed_entities = ?, completed_at = NOW() WHERE id = ?",
            [$processed, $failed, $logId]
        );
        
        output("Repertory embeddings completed: {$processed} successful, {$failed} failed", 'success');
        
    } catch (Exception $e) {
        output("Error generating repertory embeddings: " . $e->getMessage(), 'error');
    }
}

/**
 * Generate embeddings for diseases
 */
function generateDiseaseEmbeddings() {
    output("Starting disease embeddings generation...", 'info');
    
    try {
        $embeddingsService = new EmbeddingsService();
        $vectorStore = new VectorStore();
        
        // Check if diseases table exists
        $tableExists = DB::query("SHOW TABLES LIKE 'diseases'");
        if (empty($tableExists)) {
            output("Diseases table not found, skipping...", 'warning');
            return;
        }
        
        $diseases = DB::query("SELECT * FROM diseases ORDER BY id");
        $totalDiseases = count($diseases);
        
        output("Found {$totalDiseases} diseases to process", 'info');
        
        if ($totalDiseases === 0) {
            output("No diseases found in database", 'warning');
            return;
        }
        
        DB::query(
            "INSERT INTO embedding_generation_log (entity_type, total_entities, status, started_at) 
             VALUES ('disease', ?, 'running', NOW())",
            [$totalDiseases]
        );
        $logId = DB::lastInsertId();
        
        $batchSize = 30;
        $processed = 0;
        $failed = 0;
        
        for ($i = 0; $i < $totalDiseases; $i += $batchSize) {
            $batch = array_slice($diseases, $i, $batchSize);
            $texts = [];
            $diseaseIds = [];
            
            foreach ($batch as $disease) {
                $text = buildDiseaseText($disease);
                $texts[] = $text;
                $diseaseIds[] = [
                    'id' => $disease['id'],
                    'name' => $disease['disease_name'] ?? 'Unknown',
                    'text' => $text
                ];
            }
            
            try {
                $embeddings = $embeddingsService->generateEmbeddings($texts);
                
                $storeItems = [];
                foreach ($embeddings as $index => $embedding) {
                    if (!empty($embedding)) {
                        $storeItems[] = [
                            'entity_type' => 'disease',
                            'entity_id' => $diseaseIds[$index]['id'],
                            'embedding' => $embedding,
                            'text_content' => substr($diseaseIds[$index]['text'], 0, 500)
                        ];
                        $processed++;
                    } else {
                        $failed++;
                    }
                }
                
                $vectorStore->storeBatch($storeItems);
                
                $progress = round(($i + count($batch)) / $totalDiseases * 100, 1);
                output("Progress: {$progress}% ({$processed} processed)", 'info');
                
            } catch (Exception $e) {
                $failed += count($batch);
                output("Batch error: " . $e->getMessage(), 'error');
                
                if (strpos($e->getMessage(), 'quota') !== false) {
                    sleep(60);
                    $i -= $batchSize;
                }
            }
            
            usleep(200000);
        }
        
        DB::query(
            "UPDATE embedding_generation_log SET status = 'completed', processed_entities = ?, failed_entities = ?, completed_at = NOW() WHERE id = ?",
            [$processed, $failed, $logId]
        );
        
        output("Disease embeddings completed: {$processed} successful, {$failed} failed", 'success');
        
    } catch (Exception $e) {
        output("Error generating disease embeddings: " . $e->getMessage(), 'error');
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

// Determine what to generate
$type = $argv[1] ?? 'all';

output("=== Vector Embeddings Generator ===", 'info');
output("Type: {$type}", 'info');

// First, create the embeddings table if it doesn't exist
try {
    $sql = file_get_contents(__DIR__ . '/database/create_embeddings_table.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            DB::execute($statement);
        }
    }
    output("Embeddings tables verified/created", 'success');
} catch (Exception $e) {
    output("Error creating tables: " . $e->getMessage(), 'error');
    exit(1);
}

// Generate embeddings based on type
switch (strtolower($type)) {
    case 'remedies':
        generateRemedyEmbeddings();
        break;
        
    case 'repertory':
        generateRepertoryEmbeddings();
        break;
        
    case 'diseases':
        generateDiseaseEmbeddings();
        break;
        
    case 'all':
        generateRemedyEmbeddings();
        generateRepertoryEmbeddings();
        generateDiseaseEmbeddings();
        break;
        
    default:
        output("Unknown type: {$type}. Use: remedies, repertory, diseases, or all", 'error');
        exit(1);
}

output("=== Embedding generation complete ===", 'success');
