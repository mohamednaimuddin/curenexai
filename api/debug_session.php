<?php
/**
 * Debug Session Info
 * DISABLED FOR SECURITY - Only enable in development with authorization
 */

header('Content-Type: application/json');
http_response_code(403);

echo json_encode([
    'error' => 'Access denied. Debug endpoint is disabled in production.',
    'status' => 403
]);
