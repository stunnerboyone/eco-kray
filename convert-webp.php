<?php
/**
 * WebP Batch Conversion Script
 * Usage: php convert-webp.php [directory] [quality]
 *
 * Examples:
 *   php convert-webp.php                    // Convert all images in image/
 *   php convert-webp.php catalog/           // Convert images in image/catalog/
 *   php convert-webp.php catalog/ 90        // Convert with 90% quality
 */

// Load configuration
if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found. Run this script from the OpenCart root directory.\n");
}

// Load ImageOptimizer
require_once(DIR_SYSTEM . 'library/imageoptimizer.php');

// Parse arguments
$directory = isset($argv[1]) ? trim($argv[1], '/') . '/' : '';
$quality = isset($argv[2]) ? (int)$argv[2] : 85;

// Validate quality
if ($quality < 1 || $quality > 100) {
    die("Error: Quality must be between 1 and 100\n");
}

echo "=================================\n";
echo "WebP Conversion Script\n";
echo "=================================\n";
echo "Directory: " . ($directory ?: 'image/') . "\n";
echo "Quality: {$quality}%\n";
echo "=================================\n\n";

// Check WebP support
if (!function_exists('imagewebp')) {
    die("Error: WebP support is not available in your PHP installation.\n" .
        "Please install/enable GD library with WebP support.\n");
}

// Initialize optimizer
$optimizer = new ImageOptimizer(DIR_IMAGE);

// Start conversion
echo "Starting conversion...\n\n";
$start_time = microtime(true);

$converted = $optimizer->batchConvert($directory, $quality);

$end_time = microtime(true);
$duration = round($end_time - $start_time, 2);

// Display results
echo "\n=================================\n";
echo "Conversion Complete!\n";
echo "=================================\n";
echo "Files converted: " . count($converted) . "\n";
echo "Time taken: {$duration} seconds\n";

if (count($converted) > 0) {
    echo "\nConverted files:\n";
    foreach ($converted as $file) {
        echo "  ✓ {$file}\n";
    }
}

echo "\n";
