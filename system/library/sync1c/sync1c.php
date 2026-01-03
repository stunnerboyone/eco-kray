<?php
/**
 * Sync1C Library - Refactored Version
 * Handles CommerceML exchange with 1C using service classes
 */

// Load service classes
require_once(__DIR__ . '/AuthService.php');
require_once(__DIR__ . '/SeoUrlGenerator.php');
require_once(__DIR__ . '/CatalogImporter.php');
require_once(__DIR__ . '/OfferImporter.php');
require_once(__DIR__ . '/XmlValidator.php');
require_once(__DIR__ . '/RateLimiter.php');

class Sync1C {
    private $registry;
    private $db;
    private $config;
    private $log;
    private $session;
    private $request;

    private $upload_dir;

    // Service instances
    private $authService;
    private $seoUrlGenerator;
    private $catalogImporter;
    private $offerImporter;
    private $xmlValidator;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->session = $registry->get('session');
        $this->request = $registry->get('request');

        $this->upload_dir = DIR_STORAGE . 'sync1c/';

        // Create upload directory if not exists
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        // Initialize services
        $this->initializeServices();
    }

    /**
     * Initialize all service instances
     */
    private function initializeServices() {
        // Rate Limiter (5 attempts per 5 minutes, block for 15 minutes)
        $rateLimiter = new Sync1CRateLimiter($this->log, DIR_STORAGE . 'sync1c/', 5, 300, 900);

        // Auth service (with rate limiter)
        $this->authService = new Sync1CAuthService($this->registry, $rateLimiter);

        // XML Validator
        $this->xmlValidator = new Sync1CXmlValidator($this->log);

        // SEO URL Generator
        $this->seoUrlGenerator = new Sync1CSeoUrlGenerator($this->db, $this->log);

        // Catalog Importer
        $this->catalogImporter = new Sync1CCatalogImporter($this->db, $this->log, $this->seoUrlGenerator);

        // Offer Importer (with catalog importer for auto-categorization)
        $this->offerImporter = new Sync1COfferImporter($this->db, $this->log, $this->catalogImporter);
    }

    /**
     * Authentication check
     * Delegates to AuthService
     *
     * @return string Response for 1C
     */
    public function checkAuth() {
        return $this->authService->checkAuth();
    }

    /**
     * Catalog init - prepare for import
     *
     * @return string Response for 1C
     */
    public function catalogInit() {
        $this->log->write('=== CATALOG INIT STARTED ===');
        $this->log->write('Client IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        if (!$this->authService->isAuthenticated()) {
            $this->log->write('ERROR: Not authenticated');
            $this->log->write('=== CATALOG INIT FAILED ===');
            return "failure\nNot authorized";
        }

        // Clean old files
        $this->cleanUploadDir();

        // Get limits
        $zip_support = function_exists('zip_open') ? 'yes' : 'no';
        $max_size = min(
            $this->parseSize(ini_get('upload_max_filesize')),
            $this->parseSize(ini_get('post_max_size')),
            50 * 1024 * 1024 // 50MB max
        );

        $this->log->write("Zip support: $zip_support");
        $this->log->write("Max file size: " . ($max_size / 1024 / 1024) . " MB");
        $this->log->write('=== CATALOG INIT COMPLETED ===');

        return "zip=$zip_support\nfile_limit=$max_size";
    }

    /**
     * Handle file upload
     *
     * @return string Response for 1C
     */
    public function catalogFile() {
        $this->log->write('=== FILE UPLOAD STARTED ===');

        if (!$this->authService->isAuthenticated()) {
            $this->log->write('ERROR: Not authenticated');
            $this->log->write('=== FILE UPLOAD FAILED ===');
            return "failure\nNot authorized";
        }

        $filename = isset($_GET['filename']) ? basename($_GET['filename']) : '';

        if (empty($filename)) {
            $this->log->write('ERROR: Filename not specified');
            $this->log->write('=== FILE UPLOAD FAILED ===');
            return "failure\nFilename not specified";
        }

        $this->log->write("Receiving file: $filename");

        // Read raw input
        $data = file_get_contents('php://input');

        if (empty($data)) {
            $this->log->write('ERROR: Empty file received');
            $this->log->write('=== FILE UPLOAD FAILED ===');
            return "failure\nEmpty file";
        }

        $filepath = $this->upload_dir . $filename;
        $is_append = file_exists($filepath);

        // Handle chunked upload
        if ($is_append) {
            file_put_contents($filepath, $data, FILE_APPEND);
            $this->log->write("File chunk appended: $filename (" . strlen($data) . " bytes, total: " . filesize($filepath) . " bytes)");
        } else {
            file_put_contents($filepath, $data);
            $this->log->write("File created: $filename (" . strlen($data) . " bytes)");
        }

        // Extract if zip
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'zip') {
            $this->log->write("Extracting ZIP archive...");
            $this->extractZip($filepath);
        }

        $this->log->write('=== FILE UPLOAD COMPLETED ===');
        return "success";
    }

    /**
     * Process catalog import
     * Delegates to CatalogImporter
     *
     * @return string Response for 1C
     */
    public function catalogImport() {
        $this->log->write('=== CATALOG IMPORT REQUEST ===');

        if (!$this->authService->isAuthenticated()) {
            $this->log->write('ERROR: Not authenticated');
            return "failure\nNot authorized";
        }

        $filename = isset($_GET['filename']) ? basename($_GET['filename']) : '';
        $filepath = $this->upload_dir . $filename;

        if (!file_exists($filepath)) {
            $this->log->write("ERROR: File not found: $filename");
            return "failure\nFile not found: $filename";
        }

        $filesize = filesize($filepath);
        $this->log->write("Processing file: $filename (" . ($filesize / 1024) . " KB)");

        try {
            // Load XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($filepath);

            if (!$xml) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return "failure\nXML error: " . $errors[0]->message;
            }

            // Determine file type, validate, and process
            if (strpos($filename, 'import') !== false) {
                // Validate catalog XML
                if (!$this->xmlValidator->validateCatalog($xml)) {
                    return "failure\nXML validation failed:\n" . $this->xmlValidator->getErrorsAsString();
                }
                return $this->catalogImporter->import($xml);
            } elseif (strpos($filename, 'offers') !== false) {
                // Validate offers XML
                if (!$this->xmlValidator->validateOffers($xml)) {
                    return "failure\nXML validation failed:\n" . $this->xmlValidator->getErrorsAsString();
                }
                return $this->offerImporter->import($xml);
            } else {
                // Try to detect by content
                if (isset($xml->Каталог->Товары)) {
                    // Validate catalog XML
                    if (!$this->xmlValidator->validateCatalog($xml)) {
                        return "failure\nXML validation failed:\n" . $this->xmlValidator->getErrorsAsString();
                    }
                    return $this->catalogImporter->import($xml);
                } elseif (isset($xml->ПакетПредложений)) {
                    // Validate offers XML
                    if (!$this->xmlValidator->validateOffers($xml)) {
                        return "failure\nXML validation failed:\n" . $this->xmlValidator->getErrorsAsString();
                    }
                    return $this->offerImporter->import($xml);
                } else {
                    return "success\nUnknown file type, skipped";
                }
            }

        } catch (Exception $e) {
            $this->log->write('Import error: ' . $e->getMessage());
            return "failure\n" . $e->getMessage();
        }
    }

    /**
     * Sale init
     *
     * @return string Response for 1C
     */
    public function saleInit() {
        if (!$this->authService->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $max_size = 50 * 1024 * 1024;
        return "zip=no\nfile_limit=$max_size";
    }

    /**
     * Export orders to 1C (DISABLED - orders export not required)
     *
     * @return string Response for 1C
     */
    public function saleQuery() {
        if (!$this->authService->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $this->log->write('saleQuery: Order export is disabled (not required)');

        // Return empty XML response - no orders will be exported
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<КоммерческаяИнформация ВерсияСхемы=\"2.08\" ДатаФормирования=\"" . date('Y-m-d') . "\"></КоммерческаяИнформация>";
    }

    /**
     * Confirm orders received by 1C
     *
     * @return string Response for 1C
     */
    public function saleSuccess() {
        if (!$this->authService->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $this->log->write('saleSuccess called');
        return "success";
    }

    /**
     * Extract zip archive
     *
     * @param string $filepath Path to zip file
     */
    private function extractZip($filepath) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            $num_files = $zip->numFiles;
            $this->log->write("ZIP archive opened: $num_files files inside");

            $zip->extractTo($this->upload_dir);
            $zip->close();
            unlink($filepath);

            $this->log->write("SUCCESS: Extracted $num_files files from ZIP archive");
            $this->log->write("ZIP file deleted: " . basename($filepath));
        } else {
            $this->log->write("ERROR: Failed to open ZIP archive: $filepath");
        }
    }

    /**
     * Clean upload directory
     */
    private function cleanUploadDir() {
        $files = glob($this->upload_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400) {
                unlink($file);
            }
        }
    }

    /**
     * Parse size string to bytes
     *
     * @param string $size Size string (e.g. "2M")
     * @return int Size in bytes
     */
    private function parseSize($size) {
        $unit = strtoupper(substr($size, -1));
        $value = (int)$size;

        switch ($unit) {
            case 'G': $value *= 1024;
            case 'M': $value *= 1024;
            case 'K': $value *= 1024;
        }

        return $value;
    }

    /**
     * Get service instance for external configuration
     *
     * @param string $service Service name
     * @return object|null Service instance
     */
    public function getService($service) {
        switch ($service) {
            case 'auth':
                return $this->authService;
            case 'seo':
                return $this->seoUrlGenerator;
            case 'catalog':
                return $this->catalogImporter;
            case 'offer':
                return $this->offerImporter;
            default:
                return null;
        }
    }
}
