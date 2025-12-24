<?php
/**
 * Sync1C Library
 * Handles CommerceML exchange with 1C
 */

class Sync1C {
    private $registry;
    private $db;
    private $config;
    private $log;
    private $session;
    private $request;

    private $upload_dir;
    private $session_id;

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
    }

    /**
     * Authentication check
     * Returns session info on success
     */
    public function checkAuth() {
        $this->log->write('=== AUTH CHECK STARTED ===');
        $this->log->write('Client IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log->write('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

        // Log all relevant headers for debugging
        $this->log->write('--- Request Headers ---');
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->log->write('HTTP_AUTHORIZATION: present (hidden for security)');
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $this->log->write('REDIRECT_HTTP_AUTHORIZATION: present');
        }
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $this->log->write('PHP_AUTH_USER: present');
        }

        // Get credentials
        $username = '';
        $password = '';
        $auth_method = 'none';

        // Try different auth methods (various hosting configurations)
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
            $auth_method = 'PHP_AUTH_USER';
        }

        // HTTP_AUTHORIZATION (mod_rewrite)
        if (empty($username) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if (strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
                    $auth_method = 'HTTP_AUTHORIZATION';
                }
            }
        }

        // REDIRECT_HTTP_AUTHORIZATION (CGI mode)
        if (empty($username) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if (strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
                    $auth_method = 'REDIRECT_HTTP_AUTHORIZATION';
                }
            }
        }

        // Try getallheaders() as fallback
        if (empty($username) && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    if (strpos($value, 'Basic ') === 0) {
                        $decoded = base64_decode(substr($value, 6));
                        if (strpos($decoded, ':') !== false) {
                            list($username, $password) = explode(':', $decoded, 2);
                            $auth_method = 'getallheaders()';
                        }
                    }
                    break;
                }
            }
        }

        // Try apache_request_headers() as another fallback
        if (empty($username) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    if (strpos($value, 'Basic ') === 0) {
                        $decoded = base64_decode(substr($value, 6));
                        if (strpos($decoded, ':') !== false) {
                            list($username, $password) = explode(':', $decoded, 2);
                            $auth_method = 'apache_request_headers()';
                        }
                    }
                    break;
                }
            }
        }

        // URL-based auth fallback (for hosts that strip Authorization header)
        if (empty($username) && isset($_GET['user'])) {
            $username = $_GET['user'];
            $password = isset($_GET['pass']) ? $_GET['pass'] : '';
            $auth_method = 'URL parameters';
        }

        // Log received credentials (username only, never log passwords!)
        $this->log->write("Auth method used: $auth_method");
        $this->log->write("Received username: " . ($username ?: '(empty)'));
        $this->log->write("Received password: " . (empty($password) ? '(empty)' : '(present, length: ' . strlen($password) . ')'));

        // Get configured credentials
        $config_user = $this->config->get('sync1c_username') ?: 'admin';
        $config_pass = $this->config->get('sync1c_password') ?: '';

        $this->log->write("Expected username: " . ($config_user ?: '(empty)'));
        $this->log->write("Expected password: " . (empty($config_pass) ? '(empty)' : '(present, length: ' . strlen($config_pass) . ')'));

        // Validate
        if ($username !== $config_user || $password !== $config_pass) {
            $this->log->write("ERROR: Auth FAILED - Credentials do not match!");
            if ($username !== $config_user) {
                $this->log->write("  - Username mismatch: '$username' != '$config_user'");
            }
            if ($password !== $config_pass) {
                $this->log->write("  - Password mismatch (lengths: " . strlen($password) . " != " . strlen($config_pass) . ")");
            }
            $this->log->write('=== AUTH CHECK FAILED ===');
            return "failure\nInvalid username or password";
        }

        // Session should already be started in sync1c.php
        $session_id = session_id();
        if (empty($session_id)) {
            session_start();
            $session_id = session_id();
        }

        $this->session->data['sync1c_auth'] = true;
        $this->session->data['sync1c_time'] = time();

        $this->log->write("SUCCESS: Authentication successful!");
        $this->log->write("Session ID: $session_id");
        $this->log->write("Cookie name: " . session_name());
        $this->log->write('=== AUTH CHECK COMPLETED ===');

        $cookie_name = session_name();
        return "success\n$cookie_name\n$session_id";
    }

    /**
     * Check if authenticated
     */
    private function isAuthenticated() {
        // Try HTTP Authorization header first (for session-less 1C clients)
        $username = '';
        $password = '';

        // Try different auth methods
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if (strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
                }
            }
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if (strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
                }
            }
        }

        // Validate credentials if provided
        if (!empty($username)) {
            $config_user = $this->config->get('sync1c_username') ?: 'admin';
            $config_pass = $this->config->get('sync1c_password') ?: '';

            if ($username === $config_user && $password === $config_pass) {
                $this->log->write('Auth OK: HTTP Authorization header');
                return true;
            }
        }

        // Check session
        if (isset($this->session->data['sync1c_auth']) && $this->session->data['sync1c_auth']) {
            // Check timeout (1 hour)
            if (time() - $this->session->data['sync1c_time'] < 3600) {
                $this->log->write('Auth OK: session valid');
                return true;
            }
        }

        // Fallback: check URL params (for hosts with session issues)
        if (isset($_GET['user']) && isset($_GET['pass'])) {
            $config_user = $this->config->get('sync1c_username') ?: 'admin';
            $config_pass = $this->config->get('sync1c_password') ?: '';

            if ($_GET['user'] === $config_user && $_GET['pass'] === $config_pass) {
                $this->session->data['sync1c_auth'] = true;
                $this->session->data['sync1c_time'] = time();
                $this->log->write('Auth OK: URL params');
                return true;
            }
        }

        // Authentication failed
        $this->log->write('Auth FAILED: No valid session or credentials');
        return false;
    }

    /**
     * Catalog init - prepare for import
     */
    public function catalogInit() {
        $this->log->write('=== CATALOG INIT STARTED ===');
        $this->log->write('Client IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        if (!$this->isAuthenticated()) {
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
     */
    public function catalogFile() {
        $this->log->write('=== FILE UPLOAD STARTED ===');

        if (!$this->isAuthenticated()) {
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
     */
    public function catalogImport() {
        $this->log->write('=== CATALOG IMPORT REQUEST ===');

        if (!$this->isAuthenticated()) {
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

            // Determine file type and process
            if (strpos($filename, 'import') !== false) {
                $result = $this->importCatalog($xml);
            } elseif (strpos($filename, 'offers') !== false) {
                $result = $this->importOffers($xml);
            } else {
                // Try to detect by content
                if (isset($xml->Каталог->Товары)) {
                    $result = $this->importCatalog($xml);
                } elseif (isset($xml->ПакетПредложений)) {
                    $result = $this->importOffers($xml);
                } else {
                    $result = "success\nUnknown file type, skipped";
                }
            }

            return $result;

        } catch (Exception $e) {
            $this->log->write('Import error: ' . $e->getMessage());
            return "failure\n" . $e->getMessage();
        }
    }

    /**
     * Import catalog (products, categories)
     */
    private function importCatalog($xml) {
        $stats = ['categories' => 0, 'products' => 0, 'updated' => 0];

        $this->log->write("=== CATALOG IMPORT STARTED ===");

        // Import categories
        if (isset($xml->Классификатор->Группы)) {
            $this->log->write("Importing categories...");
            $this->importCategories($xml->Классификатор->Группы->Группа, 0);
            $stats['categories'] = $this->countCategories($xml->Классификатор->Группы->Группа);
            $this->log->write("Categories imported: {$stats['categories']}");
        }

        // Import products
        if (isset($xml->Каталог->Товары->Товар)) {
            $this->log->write("Importing products...");
            foreach ($xml->Каталог->Товары->Товар as $product) {
                $result = $this->importProduct($product);
                if ($result === 'created') {
                    $stats['products']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                }
            }
        }

        $this->log->write("=== CATALOG IMPORT COMPLETED ===");
        $msg = "Categories: {$stats['categories']}, Products added: {$stats['products']}, Updated: {$stats['updated']}";
        $this->log->write("Summary: $msg");

        return "success\n$msg";
    }

    /**
     * Import categories recursively
     */
    private function importCategories($groups, $parent_id) {
        foreach ($groups as $group) {
            $guid = (string)$group->Ид;
            $name = (string)$group->Наименование;

            // Check if exists
            $query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category_to_1c WHERE guid = '" . $this->db->escape($guid) . "'");

            if ($query->num_rows) {
                $category_id = $query->row['category_id'];
                // Update name
                $this->db->query("UPDATE " . DB_PREFIX . "category_description SET name = '" . $this->db->escape($name) . "' WHERE category_id = '" . (int)$category_id . "'");

                // Update SEO URL
                $this->generateSeoUrl('category', $category_id, $name);
            } else {
                // Create category
                $this->db->query("INSERT INTO " . DB_PREFIX . "category SET parent_id = '" . (int)$parent_id . "', top = '0', status = '1', date_added = NOW(), date_modified = NOW()");
                $category_id = $this->db->getLastId();

                // Add description
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$category_id . "', language_id = '1', name = '" . $this->db->escape($name) . "'");

                // Add to store
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_store SET category_id = '" . (int)$category_id . "', store_id = '0'");

                // Link to 1C
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_to_1c SET category_id = '" . (int)$category_id . "', guid = '" . $this->db->escape($guid) . "'");

                // Generate SEO URL
                $this->generateSeoUrl('category', $category_id, $name);
            }

            // Process child categories
            if (isset($group->Группы->Группа)) {
                $this->importCategories($group->Группы->Группа, $category_id);
            }
        }
    }

    /**
     * Import single product
     */
    private function importProduct($product) {
        $guid = (string)$product->Ид;
        $sku = (string)$product->Артикул;
        $name = (string)$product->Наименование;
        $description = isset($product->Описание) ? (string)$product->Описание : '';

        // Check if exists
        $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_to_1c WHERE guid = '" . $this->db->escape($guid) . "'");

        if ($query->num_rows) {
            $product_id = $query->row['product_id'];

            // Update product
            $this->db->query("UPDATE " . DB_PREFIX . "product SET
                sku = '" . $this->db->escape($sku) . "',
                date_modified = NOW()
                WHERE product_id = '" . (int)$product_id . "'");

            // Update description
            $this->db->query("UPDATE " . DB_PREFIX . "product_description SET
                name = '" . $this->db->escape($name) . "',
                description = '" . $this->db->escape($description) . "'
                WHERE product_id = '" . (int)$product_id . "'");

            $result = 'updated';
            $this->log->write("UPDATED: Product #$product_id - $name (SKU: $sku)");

            // Update SEO URL
            $this->generateSeoUrl('product', $product_id, $name);
        } else {
            // Create product
            $this->db->query("INSERT INTO " . DB_PREFIX . "product SET
                model = '" . $this->db->escape($sku) . "',
                sku = '" . $this->db->escape($sku) . "',
                quantity = 0,
                price = 0,
                status = 1,
                date_added = NOW(),
                date_modified = NOW()");

            $product_id = $this->db->getLastId();

            // Add description
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET
                product_id = '" . (int)$product_id . "',
                language_id = '1',
                name = '" . $this->db->escape($name) . "',
                description = '" . $this->db->escape($description) . "'");

            // Add to store
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '0'");

            // Link to 1C
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_1c SET product_id = '" . (int)$product_id . "', guid = '" . $this->db->escape($guid) . "'");

            $result = 'created';
            $this->log->write("CREATED: Product #$product_id - $name (SKU: $sku)");

            // Generate SEO URL
            $this->generateSeoUrl('product', $product_id, $name);
        }

        // Link to categories
        if (isset($product->Группы->Ид)) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

            foreach ($product->Группы->Ид as $cat_guid) {
                $cat_query = $this->db->query("SELECT category_id FROM " . DB_PREFIX . "category_to_1c WHERE guid = '" . $this->db->escape((string)$cat_guid) . "'");
                if ($cat_query->num_rows) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$cat_query->row['category_id'] . "'");
                }
            }
        }

        return $result;
    }

    /**
     * Import offers (prices and stock)
     */
    private function importOffers($xml) {
        $stats = ['updated' => 0, 'not_found' => 0];
        $exported_products = [];

        if (!isset($xml->ПакетПредложений->Предложения->Предложение)) {
            return "success\nNo offers to import";
        }

        $this->log->write("=== PRODUCT EXPORT STARTED ===");

        foreach ($xml->ПакетПредложений->Предложения->Предложение as $offer) {
            $guid = (string)$offer->Ид;

            // Handle composite GUID (product#variant)
            if (strpos($guid, '#') !== false) {
                $guid = explode('#', $guid)[0];
            }

            // Find product
            $query = $this->db->query("SELECT product_id FROM " . DB_PREFIX . "product_to_1c WHERE guid = '" . $this->db->escape($guid) . "'");

            if (!$query->num_rows) {
                $stats['not_found']++;
                $this->log->write("ERROR: Product not found - GUID: $guid");
                continue;
            }

            $product_id = $query->row['product_id'];

            // Get price
            $price = 0;
            if (isset($offer->Цены->Цена->ЦенаЗаЕдиницу)) {
                $price = (float)$offer->Цены->Цена->ЦенаЗаЕдиницу;
            }

            // Get quantity
            $quantity = 0;
            if (isset($offer->Количество)) {
                $quantity = (int)$offer->Количество;
            } elseif (isset($offer->Склад)) {
                foreach ($offer->Склад as $warehouse) {
                    $quantity += (int)$warehouse['КоличествоНаСкладе'];
                }
            }

            // Get product name for logging
            $name_query = $this->db->query("SELECT name FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "' LIMIT 1");
            $product_name = $name_query->num_rows ? $name_query->row['name'] : 'Unknown Product';

            // Get current values
            $old_query = $this->db->query("SELECT price, quantity, status FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");
            $old_price = $old_query->num_rows ? $old_query->row['price'] : 0;
            $old_qty = $old_query->num_rows ? $old_query->row['quantity'] : 0;
            $status = $old_query->num_rows ? $old_query->row['status'] : 1;

            // Update product
            $this->db->query("UPDATE " . DB_PREFIX . "product SET
                price = '" . (float)$price . "',
                quantity = '" . (int)$quantity . "',
                date_modified = NOW()
                WHERE product_id = '" . (int)$product_id . "'");

            // Detailed logging in English with format: Product Name Price UAH Quantity units
            $this->log->write("EXPORTED: $product_name {$price} UAH {$quantity} units");

            // Track changes for summary
            if ($old_price != $price || $old_qty != $quantity) {
                $changes = [];
                if ($old_price != $price) {
                    $changes[] = "price: {$old_price} → {$price} UAH";
                }
                if ($old_qty != $quantity) {
                    $changes[] = "quantity: {$old_qty} → {$quantity} units";
                }
                $this->log->write("  └─ Changes: " . implode(", ", $changes));
            }

            $exported_products[] = $product_name;
            $stats['updated']++;
        }

        $this->log->write("=== PRODUCT EXPORT COMPLETED ===");
        $this->log->write("Total exported: {$stats['updated']} products");

        if ($stats['not_found'] > 0) {
            $this->log->write("ERROR: {$stats['not_found']} products not found in database");
        }

        if (count($exported_products) > 0) {
            $this->log->write("--- Exported Products List ---");
            foreach ($exported_products as $idx => $product) {
                $this->log->write(($idx + 1) . ". $product");
            }
        }

        $msg = "Updated: {$stats['updated']}, Not found: {$stats['not_found']}";
        $this->log->write("Summary: $msg");

        return "success\n$msg";
    }

    /**
     * Sale init
     */
    public function saleInit() {
        if (!$this->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $max_size = 50 * 1024 * 1024;
        return "zip=no\nfile_limit=$max_size";
    }

    /**
     * Export orders to 1C (DISABLED - orders export not required)
     */
    public function saleQuery() {
        if (!$this->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $this->log->write('saleQuery: Order export is disabled (not required)');

        // Return empty XML response - no orders will be exported
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<КоммерческаяИнформация ВерсияСхемы=\"2.08\" ДатаФормирования=\"" . date('Y-m-d') . "\"></КоммерческаяИнформация>";
    }

    /**
     * Build order XML
     */
    private function buildOrderXml($order) {
        $order_id = $order['order_id'];

        $xml = "<Документ>\n";
        $xml .= "  <Ид>order-{$order_id}</Ид>\n";
        $xml .= "  <Номер>{$order_id}</Номер>\n";
        $xml .= "  <Дата>" . date('Y-m-d', strtotime($order['date_added'])) . "</Дата>\n";
        $xml .= "  <Время>" . date('H:i:s', strtotime($order['date_added'])) . "</Время>\n";
        $xml .= "  <Валюта>{$order['currency_code']}</Валюта>\n";
        $xml .= "  <Курс>1</Курс>\n";
        $xml .= "  <Сумма>{$order['total']}</Сумма>\n";

        // Customer
        $xml .= "  <Контрагенты>\n";
        $xml .= "    <Контрагент>\n";
        $xml .= "      <Наименование>" . htmlspecialchars($order['firstname'] . ' ' . $order['lastname']) . "</Наименование>\n";
        $xml .= "      <Телефон>" . htmlspecialchars($order['telephone']) . "</Телефон>\n";
        $xml .= "      <Email>" . htmlspecialchars($order['email']) . "</Email>\n";
        $xml .= "    </Контрагент>\n";
        $xml .= "  </Контрагенты>\n";

        // Products
        $products = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

        $xml .= "  <Товары>\n";
        foreach ($products->rows as $product) {
            // Get 1C guid
            $guid_query = $this->db->query("SELECT guid FROM " . DB_PREFIX . "product_to_1c WHERE product_id = '" . (int)$product['product_id'] . "'");
            $guid = $guid_query->num_rows ? $guid_query->row['guid'] : 'unknown-' . $product['product_id'];

            $xml .= "    <Товар>\n";
            $xml .= "      <Ид>{$guid}</Ид>\n";
            $xml .= "      <Наименование>" . htmlspecialchars($product['name']) . "</Наименование>\n";
            $xml .= "      <ЦенаЗаЕдиницу>{$product['price']}</ЦенаЗаЕдиницу>\n";
            $xml .= "      <Количество>{$product['quantity']}</Количество>\n";
            $xml .= "      <Сумма>{$product['total']}</Сумма>\n";
            $xml .= "    </Товар>\n";
        }
        $xml .= "  </Товары>\n";

        $xml .= "</Документ>\n";

        return $xml;
    }

    /**
     * Confirm orders received by 1C
     */
    public function saleSuccess() {
        if (!$this->isAuthenticated()) {
            return "failure\nNot authorized";
        }

        $this->log->write('saleSuccess called');
        return "success";
    }

    /**
     * Extract zip archive
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
     * Count categories recursively
     */
    private function countCategories($groups) {
        $count = 0;
        foreach ($groups as $group) {
            $count++;
            if (isset($group->Группы->Группа)) {
                $count += $this->countCategories($group->Группы->Группа);
            }
        }
        return $count;
    }

    /**
     * Generate SEO URL for product/category
     */
    private function generateSeoUrl($type, $id, $name) {
        $this->log->write("--- SEO URL Generation Started ---");
        $this->log->write("Type: $type, ID: $id, Name: $name");

        // Transliterate and clean the name
        $keyword = $this->transliterate($name);
        $this->log->write("After transliteration: $keyword");

        $keyword = strtolower($keyword);
        $this->log->write("After lowercase: $keyword");

        $keyword = preg_replace('/[^a-z0-9\-]/', '-', $keyword);
        $this->log->write("After special chars removal: $keyword");

        $keyword = preg_replace('/-+/', '-', $keyword);
        $keyword = trim($keyword, '-');
        $this->log->write("After cleanup: $keyword");

        // Ensure uniqueness
        $original_keyword = $keyword;
        $keyword = $this->ensureUniqueKeyword($keyword, $type, $id);
        if ($keyword !== $original_keyword) {
            $this->log->write("Keyword modified for uniqueness: $original_keyword -> $keyword");
        }

        // Delete existing SEO URL for this item
        $delete_query = "DELETE FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($type . '_id=' . $id) . "'";
        $this->log->write("Deleting existing SEO URL: $delete_query");
        $this->db->query($delete_query);

        // Insert new SEO URL
        $insert_query = "INSERT INTO " . DB_PREFIX . "seo_url SET
            store_id = '0',
            language_id = '1',
            query = '" . $this->db->escape($type . '_id=' . $id) . "',
            keyword = '" . $this->db->escape($keyword) . "'";

        $this->log->write("Inserting SEO URL: $insert_query");

        try {
            $this->db->query($insert_query);
            $seo_url_id = $this->db->getLastId();
            $this->log->write("SUCCESS: SEO URL created with ID: $seo_url_id");
            $this->log->write("SEO URL: $keyword for $type #$id");
        } catch (Exception $e) {
            $this->log->write("ERROR: Failed to insert SEO URL - " . $e->getMessage());
        }

        $this->log->write("--- SEO URL Generation Completed ---");

        return $keyword;
    }

    /**
     * Transliterate Cyrillic to Latin
     */
    private function transliterate($text) {
        $cyrillic = [
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo', 'ж'=>'zh',
            'з'=>'z', 'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o',
            'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h', 'ц'=>'ts',
            'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch', 'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
            'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'Yo', 'Ж'=>'Zh',
            'З'=>'Z', 'И'=>'I', 'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O',
            'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Х'=>'H', 'Ц'=>'Ts',
            'Ч'=>'Ch', 'Ш'=>'Sh', 'Щ'=>'Sch', 'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'', 'Э'=>'E', 'Ю'=>'Yu', 'Я'=>'Ya',
            // Ukrainian
            'і'=>'i', 'ї'=>'yi', 'є'=>'ye', 'ґ'=>'g',
            'І'=>'I', 'Ї'=>'Yi', 'Є'=>'Ye', 'Ґ'=>'G'
        ];

        return strtr($text, $cyrillic);
    }

    /**
     * Ensure keyword is unique by appending number if needed
     */
    private function ensureUniqueKeyword($keyword, $type, $id) {
        $original_keyword = $keyword;
        $i = 1;

        $this->log->write("Checking uniqueness for keyword: $keyword");

        while (true) {
            // Check if keyword exists for different item
            $check_query = "SELECT * FROM " . DB_PREFIX . "seo_url
                WHERE keyword = '" . $this->db->escape($keyword) . "'
                AND query != '" . $this->db->escape($type . '_id=' . $id) . "'";

            $query = $this->db->query($check_query);

            if (!$query->num_rows) {
                $this->log->write("Keyword is unique: $keyword");
                break; // Keyword is unique
            }

            $this->log->write("Keyword '$keyword' already exists, trying with number suffix");

            // Append number and try again
            $keyword = $original_keyword . '-' . $i;
            $i++;

            // Safety limit to prevent infinite loop
            if ($i > 100) {
                $this->log->write("ERROR: Too many attempts to find unique keyword, using: $keyword");
                break;
            }
        }

        return $keyword;
    }
}
