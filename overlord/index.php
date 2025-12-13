<?php
// Enable Gzip Compression (reduces page size by ~70%)
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

// Security Headers (for nginx compatibility)
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
header('Strict-Transport-Security: max-age=300'); // 5 minutes for testing - increase after verification
header_remove('X-Powered-By');

// Version
define('VERSION', '3.0.3.8');

// Configuration
if (is_file('config.php')) {
	require_once('config.php');
}

// Error Reporting - Only enable in development
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
	error_reporting(0);
}

// Install
if (!defined('DIR_APPLICATION')) {
	header('Location: ../install/index.php');
	exit;
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

start('admin');