<?php
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