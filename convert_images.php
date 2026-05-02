<?php
/**
 * Image Optimization Script
 * Converts PNG/JPEG images to WebP format and creates responsive sizes
 * 
 * Run this script once to generate optimized images:
 * php convert_images.php
 * 
 * Requirements: PHP GD extension with WebP support
 * Enable in php.ini: extension=gd
 */

// Check if GD is available
if (!extension_loaded('gd')) {
    echo "ERROR: GD extension is not loaded.\n";
    echo "Enable it in php.ini by uncommenting: extension=gd\n";
    echo "Then restart Apache.\n";
    exit(1);
}

if (!function_exists('imagewebp')) {
    echo "ERROR: WebP support is not available in GD.\n";
    echo "You need PHP 5.5+ with GD compiled with WebP support.\n";
    exit(1);
}

$imageDir = __DIR__ . '/assets/image/';

// Images to convert with their target sizes
$images = [
    'logo.png' => [
        'sizes' => [
            '' => ['width' => 1038, 'quality' => 85],      // Original size
            '-800' => ['width' => 800, 'quality' => 85],   // Large screens
            '-400' => ['width' => 400, 'quality' => 85],   // Medium/mobile
            '-200' => ['width' => 200, 'quality' => 85],   // Small mobile
        ]
    ],
    'xrunbg.png' => [
        'sizes' => [
            '' => ['width' => null, 'quality' => 80],      // Original size, lower quality for bg
            '-mobile' => ['width' => 500, 'quality' => 75], // Mobile version
        ]
    ],
];

function convertToWebP($sourcePath, $destPath, $quality = 85, $targetWidth = null) {
    $info = getimagesize($sourcePath);
    if (!$info) {
        echo "  ERROR: Cannot read image: $sourcePath\n";
        return false;
    }
    
    $mime = $info['mime'];
    $origWidth = $info[0];
    $origHeight = $info[1];
    
    // Load source image
    switch ($mime) {
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        default:
            echo "  ERROR: Unsupported format: $mime\n";
            return false;
    }
    
    if (!$source) {
        echo "  ERROR: Failed to load image\n";
        return false;
    }
    
    // Resize if needed
    if ($targetWidth && $targetWidth < $origWidth) {
        $ratio = $targetWidth / $origWidth;
        $newHeight = (int)($origHeight * $ratio);
        
        $resized = imagecreatetruecolor($targetWidth, $newHeight);
        
        // Preserve transparency for PNG
        if ($mime === 'image/png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($source);
        $source = $resized;
        
        echo "  Resized to {$targetWidth}x{$newHeight}\n";
    }
    
    // Convert to WebP
    $result = imagewebp($source, $destPath, $quality);
    imagedestroy($source);
    
    if ($result) {
        $origSize = filesize($sourcePath);
        $newSize = filesize($destPath);
        $savings = round((1 - $newSize / $origSize) * 100, 1);
        echo "  Created: $destPath\n";
        echo "  Size: " . round($newSize / 1024, 1) . " KiB (saved {$savings}%)\n";
        return true;
    }
    
    return false;
}

echo "=== Image Optimization Script ===\n\n";

foreach ($images as $filename => $config) {
    $sourcePath = $imageDir . $filename;
    
    if (!file_exists($sourcePath)) {
        echo "SKIP: $filename not found\n";
        continue;
    }
    
    echo "Processing: $filename\n";
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    
    foreach ($config['sizes'] as $suffix => $settings) {
        $destPath = $imageDir . $baseName . $suffix . '.webp';
        echo "  Creating: " . basename($destPath) . "\n";
        convertToWebP($sourcePath, $destPath, $settings['quality'], $settings['width']);
    }
    
    echo "\n";
}

echo "=== Done! ===\n";
echo "\nNext steps:\n";
echo "1. The WebP images have been created in assets/image/\n";
echo "2. Update your HTML to use <picture> elements (already done in index.php)\n";
echo "3. Test in browser - it will use WebP if supported, PNG as fallback\n";
