<?php
/**
 * Image proxy with caching headers
 * Ensures browsers cache images properly
 * 
 * Usage: /img.php?f=logo.png
 */

$allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'];
$baseDir = __DIR__ . '/assets/image/';

// Get requested file
$file = isset($_GET['f']) ? basename($_GET['f']) : '';

if (empty($file)) {
    http_response_code(400);
    die('Missing file parameter');
}

$filePath = $baseDir . $file;

// Security: Only allow certain extensions
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    http_response_code(403);
    die('File type not allowed');
}

// Check file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Get MIME type
$mimeTypes = [
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
$fileSize = filesize($filePath);
$lastModified = filemtime($filePath);
$etag = md5_file($filePath);

// Check if client has cached version
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) 
    ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) 
    : 0;
$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) 
    ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') 
    : '';

if ($ifNoneMatch === $etag || $ifModifiedSince >= $lastModified) {
    http_response_code(304);
    exit;
}

// Set caching headers (1 year)
$maxAge = 31536000;
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: "' . $etag . '"');
header('Vary: Accept-Encoding');

// Output file
readfile($filePath);
