<?php
/**
 * Rate Limiting Helper
 * Prevents API abuse and brute force attacks
 */

class RateLimiter {
    private static $cacheDir;
    private static $useFileCache = true;
    
    /**
     * Initialize cache directory
     */
    private static function init() {
        if (!self::$cacheDir) {
            self::$cacheDir = dirname(__DIR__) . '/logs/rate_limit';
            if (!is_dir(self::$cacheDir)) {
                if (!@mkdir(self::$cacheDir, 0755, true)) {
                    // Can't create directory, disable file caching
                    self::$useFileCache = false;
                    error_log("Rate limiter: Cannot create cache directory, using session-based limiting");
                }
            } elseif (!is_writable(self::$cacheDir)) {
                // Directory exists but not writable
                self::$useFileCache = false;
                error_log("Rate limiter: Cache directory not writable, using session-based limiting");
            }
        }
    }
    
    /**
     * Check if request is rate limited
     * 
     * @param string $key Unique identifier (e.g., IP address, user ID)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public static function check($key, $maxRequests = null, $windowSeconds = null) {
        self::init();
        
        $maxRequests = $maxRequests ?? (defined('RATE_LIMIT_REQUESTS') ? RATE_LIMIT_REQUESTS : 60);
        $windowSeconds = $windowSeconds ?? (defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : 60);
        
        // If file cache is not available, use session-based rate limiting
        if (!self::$useFileCache) {
            return self::checkSession($key, $maxRequests, $windowSeconds);
        }
        
        $cacheKey = md5($key);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.json';
        
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Load existing data
        $data = [];
        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content) {
                $data = json_decode($content, true) ?: [];
            }
        }
        
        // Filter out expired requests
        $data = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check if limit exceeded
        $requestCount = count($data);
        $allowed = $requestCount < $maxRequests;
        
        if ($allowed) {
            // Add current request
            $data[] = $now;
            $written = @file_put_contents($cacheFile, json_encode($data));
            if ($written === false) {
                // Fall back to session if file write fails
                error_log("Rate limiter: Failed to write cache file, falling back to session");
                self::$useFileCache = false;
            }
        }
        
        // Calculate reset time
        $oldestRequest = !empty($data) ? min($data) : $now;
        $resetAt = $oldestRequest + $windowSeconds;
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxRequests - $requestCount - ($allowed ? 1 : 0)),
            'reset_at' => $resetAt,
            'retry_after' => $allowed ? 0 : ($resetAt - $now)
        ];
    }
    
    /**
     * Session-based rate limiting fallback
     */
    private static function checkSession($key, $maxRequests, $windowSeconds) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // No session available, allow request (can't rate limit without storage)
            return [
                'allowed' => true,
                'remaining' => $maxRequests - 1,
                'reset_at' => time() + $windowSeconds,
                'retry_after' => 0
            ];
        }
        
        $sessionKey = 'rate_limit_' . md5($key);
        $now = time();
        $windowStart = $now - $windowSeconds;
        
        // Get existing data from session
        $data = $_SESSION[$sessionKey] ?? [];
        
        // Filter out expired requests
        $data = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
        
        // Check if limit exceeded
        $requestCount = count($data);
        $allowed = $requestCount < $maxRequests;
        
        if ($allowed) {
            $data[] = $now;
            $_SESSION[$sessionKey] = $data;
        }
        
        $oldestRequest = !empty($data) ? min($data) : $now;
        $resetAt = $oldestRequest + $windowSeconds;
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxRequests - $requestCount - ($allowed ? 1 : 0)),
            'reset_at' => $resetAt,
            'retry_after' => $allowed ? 0 : ($resetAt - $now)
        ];
    }
    
    /**
     * Enforce rate limit - exits with 429 if exceeded
     * 
     * @param string $key Unique identifier
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     */
    public static function enforce($key, $maxRequests = null, $windowSeconds = null) {
        $result = self::check($key, $maxRequests, $windowSeconds);
        
        if (!$result['allowed']) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $result['retry_after']);
            header('X-RateLimit-Limit: ' . ($maxRequests ?? RATE_LIMIT_REQUESTS));
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $result['reset_at']);
            
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $result['retry_after']
            ]);
            exit;
        }
        
        // Add rate limit headers for allowed requests
        header('X-RateLimit-Limit: ' . ($maxRequests ?? RATE_LIMIT_REQUESTS));
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset_at']);
    }
    
    /**
     * Clean up old rate limit files
     */
    public static function cleanup() {
        self::init();
        
        $files = glob(self::$cacheDir . '/*.json');
        $expiry = time() - 3600; // Clean files older than 1 hour
        
        foreach ($files as $file) {
            if (filemtime($file) < $expiry) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Get client identifier for rate limiting
     * Uses IP address with optional user ID
     */
    public static function getClientKey($prefix = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = $_SESSION['doctor_id'] ?? 'guest';
        return $prefix . ':' . $ip . ':' . $userId;
    }
}

/**
 * Shortcut function for rate limit check
 */
function checkRateLimit($key = null, $maxRequests = null, $windowSeconds = null) {
    $key = $key ?? RateLimiter::getClientKey('api');
    return RateLimiter::check($key, $maxRequests, $windowSeconds);
}

/**
 * Shortcut function to enforce rate limit
 */
function enforceRateLimit($key = null, $maxRequests = null, $windowSeconds = null) {
    $key = $key ?? RateLimiter::getClientKey('api');
    RateLimiter::enforce($key, $maxRequests, $windowSeconds);
}
