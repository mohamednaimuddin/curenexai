<?php
/**
 * Database Connection Class
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            // Security: Don't expose database errors in production
            if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
                die("A database error occurred. Please try again later or contact support.");
            } else {
                die("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Database Query Builder Helper
 */
class DB {
    private static function getConnection() {
        return Database::getInstance()->getConnection();
    }
    
    /**
     * Execute a SELECT query
     */
    public static function query($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute a SELECT query and return single row
     */
    public static function queryOne($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute INSERT, UPDATE, DELETE queries
     */
    public static function execute($sql, $params = []) {
        try {
            $stmt = self::getConnection()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Execute Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert a record and return last insert ID
     */
    public static function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = self::getConnection()->prepare($sql);
            $result = $stmt->execute($values);
            
            if ($result) {
                return self::getConnection()->lastInsertId();
            } else {
                // Get error info
                $errorInfo = $stmt->errorInfo();
                error_log("Insert failed: " . print_r($errorInfo, true));
                throw new Exception("Insert failed: " . ($errorInfo[2] ?? 'Unknown error'));
            }
        } catch (PDOException $e) {
            error_log("Insert Error in table '{$table}': " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Data: " . print_r($data, true));
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Update records
     */
    public static function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        
        try {
            $stmt = self::getConnection()->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Update Error - SQL: " . $sql);
                error_log("Update Error - Params: " . print_r($params, true));
                error_log("Update Error - Info: " . print_r($errorInfo, true));
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Update PDO Exception: " . $e->getMessage());
            error_log("Update SQL: " . $sql);
            error_log("Update Params: " . print_r($params, true));
            return false;
        }
    }
    
    /**
     * Delete records
     */
    public static function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return self::execute($sql, $params);
    }
    
    /**
     * Get records with pagination
     */
    public static function paginate($table, $page = 1, $perPage = ITEMS_PER_PAGE, $where = '', $params = [], $orderBy = 'id DESC') {
        // Security: Cast pagination to integers to prevent SQL injection
        $safePerPage = (int)$perPage;
        $safePage = max(1, (int)$page);
        $offset = ($safePage - 1) * $safePerPage;
        
        // Security: Validate orderBy to prevent SQL injection
        // Only allow simple column names with optional DESC/ASC
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\s+(?:ASC|DESC))?$/i', trim($orderBy))) {
            $orderBy = 'id DESC'; // Safe default
        }
        
        // Count total records
        $countSql = "SELECT COUNT(*) as total FROM {$table}" . ($where ? " WHERE {$where}" : "");
        $total = self::queryOne($countSql, $params)['total'] ?? 0;
        
        // Get records
        $sql = "SELECT * FROM {$table}" . ($where ? " WHERE {$where}" : "") . 
               " ORDER BY {$orderBy} LIMIT {$safePerPage} OFFSET {$offset}";
        $records = self::query($sql, $params);
        
        return [
            'data' => $records,
            'total' => $total,
            'page' => $safePage,
            'perPage' => $safePerPage,
            'totalPages' => ceil($total / $safePerPage)
        ];
    }
    
    /**
     * Search with FULLTEXT
     */
    public static function fullTextSearch($table, $columns, $searchTerm, $additionalWhere = '', $params = []) {
        $columnList = implode(', ', $columns);
        $sql = "SELECT *, MATCH({$columnList}) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance 
                FROM {$table} 
                WHERE MATCH({$columnList}) AGAINST(? IN NATURAL LANGUAGE MODE)";
        
        if ($additionalWhere) {
            $sql .= " AND {$additionalWhere}";
        }
        
        $sql .= " ORDER BY relevance DESC";
        
        array_unshift($params, $searchTerm, $searchTerm);
        return self::query($sql, $params);
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit() {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback() {
        return self::getConnection()->rollBack();
    }
    
    /**
     * Get the last inserted ID
     */
    public static function lastInsertId() {
        return self::getConnection()->lastInsertId();
    }
}
