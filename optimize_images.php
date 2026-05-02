<?php
/**
 * Image Optimization Script
 * Creates optimized WebP versions of images
 * Access via: https://curenexai.com/optimize_images.php
 */

// Check if GD is available
if (!extension_loaded('gd')) {
    die("ERROR: GD extension is not loaded. Enable it in php.ini");
}

header('Content-Type: text/plain');

$imageDir = __DIR__ . '/assets/image/';

// Images to optimize with target dimensions
$images = [
    [
        'source' => 'CURENEXAI PNG.png',
        'targets' => [
            ['name' => 'logo-500.webp', 'width' => 500, 'height' => 107, 'quality' => 90],
            ['name' => 'logo-250.webp', 'width' => 250, 'height' => 53, 'quality' => 85],
            ['name' => 'logo-500.png', 'width' => 500, 'height' => 107, 'quality' => 9], // PNG compression 0-9
        ]
    ],
    [
        'source' => 'xrunbg.png',
        'targets' => [
            ['name' => 'xrunbg-800.webp', 'width' => 800, 'height' => 800, 'quality' => 80],
            ['name' => 'xrunbg-400.webp', 'width' => 400, 'height' => 400, 'quality' => 75],
        ]
    ]
];

echo "=== CurenexAI Image Optimization ===\n\n";

foreach ($images as $imageConfig) {
    $sourcePath = $imageDir . $imageConfig['source'];
    
    if (!file_exists($sourcePath)) {
        echo "ERROR: Source not found: {$imageConfig['source']}\n";
        continue;
    }
    
    echo "Processing: {$imageConfig['source']}\n";
    
    // Get source image info
    $info = getimagesize($sourcePath);
    $mime = $info['mime'];
    
    // Load source image
    switch ($mime) {
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            // Handle transparency
            imagealphablending($source, true);
            imagesavealpha($source, true);
            break;
        case 'image/jpeg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        default:
            echo "  Unsupported format: $mime\n";
            continue 2;
    }
    
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    echo "  Original size: {$sourceWidth}x{$sourceHeight}\n";
    
    foreach ($imageConfig['targets'] as $target) {
        $targetPath = $imageDir . $target['name'];
        $ext = pathinfo($target['name'], PATHINFO_EXTENSION);
        
        // Create resized image
        $resized = imagecreatetruecolor($target['width'], $target['height']);
        
        // Handle transparency for PNG and WebP
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }
        
        // Resize with high quality resampling
        imagecopyresampled(
            $resized, $source,
            0, 0, 0, 0,
            $target['width'], $target['height'],
            $sourceWidth, $sourceHeight
        );
        
        // Save in target format
        switch ($ext) {
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($resized, $targetPath, $target['quality']);
                    echo "  Created: {$target['name']} ({$target['width']}x{$target['height']}) - WebP quality {$target['quality']}\n";
                } else {
                    echo "  ERROR: WebP not supported by this PHP installation\n";
                }
                break;
            case 'png':
                imagepng($resized, $targetPath, $target['quality']);
                echo "  Created: {$target['name']} ({$target['width']}x{$target['height']}) - PNG compression {$target['quality']}\n";
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($resized, $targetPath, $target['quality']);
                echo "  Created: {$target['name']} ({$target['width']}x{$target['height']}) - JPEG quality {$target['quality']}\n";
                break;
        }
        
        // Show file size
        if (file_exists($targetPath)) {
            $size = filesize($targetPath);
            echo "    File size: " . round($size / 1024, 1) . " KB\n";
        }
        
        imagedestroy($resized);
    }
    
    imagedestroy($source);
    echo "\n";
}

echo "=== Optimization Complete ===\n";
echo "\nNext steps:\n";
echo "1. Update index.php to use the optimized images\n";
echo "2. Use <picture> element with WebP fallback to PNG\n";
