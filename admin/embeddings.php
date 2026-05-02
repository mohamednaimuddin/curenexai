<?php
/**
 * Vector Embeddings Service
 * Handles generation and storage of text embeddings using Google's Embedding API
 * Used for semantic search in the RAG system
 */

class EmbeddingsService {
    private $apiKey;
    private $model = 'text-embedding-004'; // Google's latest embedding model
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private $embeddingDimension = 768; // Dimension for text-embedding-004
    
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?? (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        
        if (empty($this->apiKey)) {
            throw new Exception('API key is required for embeddings service');
        }
    }
    
    /**
     * Generate embedding for a single text
     * @param string $text The text to embed
     * @return array The embedding vector
     */
    public function generateEmbedding($text) {
        $embeddings = $this->generateEmbeddings([$text]);
        return $embeddings[0] ?? null;
    }
    
    /**
     * Generate embeddings for multiple texts (batch)
     * @param array $texts Array of texts to embed
     * @return array Array of embedding vectors
     */
    public function generateEmbeddings($texts) {
        if (empty($texts)) {
            return [];
        }
        
        // Process in batches of 100 (API limit)
        $batchSize = 100;
        $allEmbeddings = [];
        
        for ($i = 0; $i < count($texts); $i += $batchSize) {
            $batch = array_slice($texts, $i, $batchSize);
            $batchEmbeddings = $this->callEmbeddingAPI($batch);
            $allEmbeddings = array_merge($allEmbeddings, $batchEmbeddings);
            
            // Rate limiting - small delay between batches
            if ($i + $batchSize < count($texts)) {
                usleep(100000); // 100ms delay
            }
        }
        
        return $allEmbeddings;
    }
    
    /**
     * Call the Google Embedding API
     * @param array $texts Batch of texts to embed
     * @return array Array of embeddings
     */
    private function callEmbeddingAPI($texts) {
        $url = "{$this->baseUrl}/models/{$this->model}:batchEmbedContents?key={$this->apiKey}";
        
        // Build request body
        $requests = [];
        foreach ($texts as $text) {
            // Truncate text if too long (max ~8000 tokens)
            $truncatedText = $this->truncateText($text, 8000);
            $requests[] = [
                'model' => "models/{$this->model}",
                'content' => [
                    'parts' => [
                        ['text' => $truncatedText]
                    ]
                ],
                'taskType' => 'RETRIEVAL_DOCUMENT'
            ];
        }
        
        $requestBody = json_encode(['requests' => $requests]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Embedding API request failed: $error");
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP $httpCode error";
            throw new Exception("Embedding API error: $errorMessage");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['embeddings'])) {
            throw new Exception("Invalid response from Embedding API");
        }
        
        // Extract embedding vectors
        $embeddings = [];
        foreach ($data['embeddings'] as $embedding) {
            $embeddings[] = $embedding['values'] ?? [];
        }
        
        return $embeddings;
    }
    
    /**
     * Generate embedding for search query
     * @param string $query The search query
     * @return array The embedding vector
     */
    public function generateQueryEmbedding($query) {
        $url = "{$this->baseUrl}/models/{$this->model}:embedContent?key={$this->apiKey}";
        
        $requestBody = json_encode([
            'model' => "models/{$this->model}",
            'content' => [
                'parts' => [
                    ['text' => $this->truncateText($query, 2000)]
                ]
            ],
            'taskType' => 'RETRIEVAL_QUERY'
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
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
            throw new Exception("Query embedding request failed: $error");
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP $httpCode error";
            throw new Exception("Query embedding error: $errorMessage");
        }
        
        $data = json_decode($response, true);
        
        return $data['embedding']['values'] ?? null;
    }
    
    /**
     * Calculate cosine similarity between two vectors
     * @param array $vec1 First vector
     * @param array $vec2 Second vector
     * @return float Similarity score (0-1)
     */
    public static function cosineSimilarity($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            return 0;
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);
        
        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }
        
        return $dotProduct / ($norm1 * $norm2);
    }
    
    /**
     * Find top-k most similar embeddings
     * @param array $queryEmbedding The query embedding
     * @param array $embeddings Array of [id => embedding] pairs
     * @param int $topK Number of results to return
     * @return array Array of [id, similarity] pairs sorted by similarity
     */
    public static function findSimilar($queryEmbedding, $embeddings, $topK = 10) {
        $similarities = [];
        
        foreach ($embeddings as $id => $embedding) {
            $similarity = self::cosineSimilarity($queryEmbedding, $embedding);
            $similarities[] = [
                'id' => $id,
                'similarity' => $similarity
            ];
        }
        
        // Sort by similarity descending
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        // Return top K
        return array_slice($similarities, 0, $topK);
    }
    
    /**
     * Truncate text to approximate token limit
     * @param string $text Text to truncate
     * @param int $maxTokens Approximate max tokens
     * @return string Truncated text
     */
    private function truncateText($text, $maxTokens) {
        // Rough estimation: 1 token ≈ 4 characters for English
        $maxChars = $maxTokens * 4;
        
        if (strlen($text) <= $maxChars) {
            return $text;
        }
        
        return substr($text, 0, $maxChars);
    }
    
    /**
     * Get the embedding dimension
     * @return int
     */
    public function getEmbeddingDimension() {
        return $this->embeddingDimension;
    }
}

/**
 * Vector Store - Handles storage and retrieval of embeddings
 */
class VectorStore {
    private $db;
    private $tableName = 'vector_embeddings';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Store an embedding
     * @param string $entityType Type of entity (remedy, repertory, disease)
     * @param int $entityId ID of the entity
     * @param array $embedding The embedding vector
     * @param string $textContent Original text that was embedded
     */
    public function store($entityType, $entityId, $embedding, $textContent = null) {
        $embeddingJson = json_encode($embedding);
        
        $sql = "INSERT INTO {$this->tableName} (entity_type, entity_id, embedding, text_content, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    embedding = VALUES(embedding),
                    text_content = VALUES(text_content),
                    updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId, $embeddingJson, $textContent]);
    }
    
    /**
     * Store multiple embeddings in batch
     * @param array $items Array of [entityType, entityId, embedding, textContent]
     */
    public function storeBatch($items) {
        if (empty($items)) return;
        
        $sql = "INSERT INTO {$this->tableName} (entity_type, entity_id, embedding, text_content, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    embedding = VALUES(embedding),
                    text_content = VALUES(text_content),
                    updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($items as $item) {
            $embeddingJson = json_encode($item['embedding']);
            $stmt->execute([
                $item['entity_type'],
                $item['entity_id'],
                $embeddingJson,
                $item['text_content'] ?? null
            ]);
        }
    }
    
    /**
     * Get embedding for an entity
     * @param string $entityType
     * @param int $entityId
     * @return array|null
     */
    public function get($entityType, $entityId) {
        $sql = "SELECT embedding FROM {$this->tableName} WHERE entity_type = ? AND entity_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId]);
        $row = $stmt->fetch();
        
        if ($row) {
            return json_decode($row['embedding'], true);
        }
        return null;
    }
    
    /**
     * Get all embeddings for an entity type
     * @param string $entityType
     * @return array [entityId => embedding]
     */
    public function getAllByType($entityType) {
        $sql = "SELECT entity_id, embedding FROM {$this->tableName} WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType]);
        
        $embeddings = [];
        while ($row = $stmt->fetch()) {
            $embeddings[$row['entity_id']] = json_decode($row['embedding'], true);
        }
        return $embeddings;
    }
    
    /**
     * Search for similar entities using vector similarity
     * @param string $entityType Type to search in
     * @param array $queryEmbedding Query embedding vector
     * @param int $topK Number of results
     * @param float $minSimilarity Minimum similarity threshold
     * @return array Array of [entity_id, similarity, text_content]
     */
    public function search($entityType, $queryEmbedding, $topK = 10, $minSimilarity = 0.5) {
        // Get all embeddings of this type
        $sql = "SELECT entity_id, embedding, text_content FROM {$this->tableName} WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType]);
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $embedding = json_decode($row['embedding'], true);
            $similarity = EmbeddingsService::cosineSimilarity($queryEmbedding, $embedding);
            
            if ($similarity >= $minSimilarity) {
                $results[] = [
                    'entity_id' => $row['entity_id'],
                    'similarity' => $similarity,
                    'text_content' => $row['text_content']
                ];
            }
        }
        
        // Sort by similarity descending
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $topK);
    }
    
    /**
     * Check if embeddings exist for an entity type
     * @param string $entityType
     * @return int Count of embeddings
     */
    public function countByType($entityType) {
        $sql = "SELECT COUNT(*) as cnt FROM {$this->tableName} WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType]);
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    }
    
    /**
     * Delete embeddings for an entity
     * @param string $entityType
     * @param int $entityId
     */
    public function delete($entityType, $entityId) {
        $sql = "DELETE FROM {$this->tableName} WHERE entity_type = ? AND entity_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType, $entityId]);
    }
    
    /**
     * Delete all embeddings of a type
     * @param string $entityType
     */
    public function deleteByType($entityType) {
        $sql = "DELETE FROM {$this->tableName} WHERE entity_type = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entityType]);
    }
}
