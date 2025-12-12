<?php
/**
 * WebP Conversion - Run from browser
 * URL: https://stunnerboyone.live/run-convert.php?key=ekokrai2024
 *
 * DELETE THIS FILE AFTER USE!
 */

// Security check
if (!isset($_GET['key']) || $_GET['key'] !== 'ekokrai2024') {
    die('Access denied');
}

set_time_limit(600);
ini_set('memory_limit', '512M');

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family: monospace; padding: 20px;">';
echo "WebP Conversion Started...\n\n";
flush();

require_once('config.php');
require_once(DIR_SYSTEM . 'library/imageoptimizer.php');

if (!function_exists('imagewebp')) {
    die("ERROR: WebP not supported by your PHP.\nContact hosting support.\n");
}

$dir = isset($_GET['dir']) ? $_GET['dir'] : '';
$quality = isset($_GET['q']) ? (int)$_GET['q'] : 80;

$optimizer = new ImageOptimizer(DIR_IMAGE);
$converted = $optimizer->batchConvert($dir, $quality);

echo "=================================\n";
echo "DONE!\n";
echo "=================================\n";
echo "Converted: " . count($converted) . " files\n\n";

foreach ($converted as $f) {
    echo "OK: $f\n";
}

echo "\n\n*** DELETE run-convert.php NOW! ***\n";
echo '</pre>';
