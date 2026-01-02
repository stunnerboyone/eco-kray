<?php
/**
 * 1C Sync Endpoint
 * URL: https://stunnerboyone.live/sync1c.php
 *
 * CommerceML 2.x compatible exchange endpoint
 */

// Security Headers
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header_remove('X-Powered-By');

// Increase limits for large imports
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

// Load configuration
require_once('overlord/config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Load settings
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
foreach ($query->rows as $result) {
    if (!$result['serialized']) {
        $config->set($result['key'], $result['value']);
    } else {
        $config->set($result['key'], json_decode($result['value'], true));
    }
}

// Log
$log = new Log('sync1c.log');
$registry->set('log', $log);

// Request
$request = new Request();
$registry->set('request', $request);

// Response
$response = new Response();
$response->addHeader('Content-Type: text/plain; charset=utf-8');
$registry->set('response', $response);

// Session - start it immediately
$config->set('session_engine', 'file');
$session = new Session('file', $registry);
$session->start();
$registry->set('session', $session);

// Cache
$registry->set('cache', new Cache('file'));

// Load sync library
require_once(DIR_SYSTEM . 'library/sync1c/sync1c.php');

$sync = new Sync1C($registry);

// Log request (Проблема #15: санітизація REQUEST_URI для захисту від log injection)
$safe_request_uri = isset($_SERVER['REQUEST_URI']) ? preg_replace('/[^\x20-\x7E]/', '', $_SERVER['REQUEST_URI']) : '';
$log->write('=== REQUEST: ' . $safe_request_uri . ' ===');

// Проблема #13 і #14: Видалено session_id з GET параметрів (Session Fixation вразливість)
// Session вже стартував на рядку 64, використовуємо cookie-based session
// 1C повинен використовувати HTTP Basic Auth або стандартні cookies

// Get request parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

// Route request
try {
    switch ($type) {
        case 'catalog':
            switch ($mode) {
                case 'checkauth':
                    echo $sync->checkAuth();
                    break;
                case 'init':
                    echo $sync->catalogInit();
                    break;
                case 'file':
                    echo $sync->catalogFile();
                    break;
                case 'import':
                    echo $sync->catalogImport();
                    break;
                default:
                    echo "success\n";
            }
            break;

        case 'sale':
            switch ($mode) {
                case 'checkauth':
                    echo $sync->checkAuth();
                    break;
                case 'init':
                    echo $sync->saleInit();
                    break;
                case 'query':
                    echo $sync->saleQuery();
                    break;
                case 'success':
                    echo $sync->saleSuccess();
                    break;
                default:
                    echo "success\n";
            }
            break;

        default:
            echo "Sync1C Endpoint Ready\n";
            echo "Use: ?type=catalog&mode=checkauth\n";
    }
} catch (Exception $e) {
    $log->write('ERROR: ' . $e->getMessage());
    echo "failure\n" . $e->getMessage();
}
