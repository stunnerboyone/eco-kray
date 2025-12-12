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
        $this->log->write('checkAuth called');

        // Get credentials
        $username = '';
        $password = '';

        // Try different auth methods (various hosting configurations)
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
        }

        // HTTP_AUTHORIZATION (mod_rewrite)
        if (empty($username) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (strpos($auth, 'Basic ') === 0) {
                $decoded = base64_decode(substr($auth, 6));
                if (strpos($decoded, ':') !== false) {
                    list($username, $password) = explode(':', $decoded, 2);
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
        }

        $this->log->write("Auth attempt: user=$username");

        // Get configured credentials
        $config_user = $this->config->get('sync1c_username') ?: 'admin';
        $config_pass = $this->config->get('sync1c_password') ?: '';

        // Validate
        if ($username !== $config_user || $password !== $config_pass) {
            $this->log->write("Auth FAILED: expected '$config_user', got '$username'");
            return "failure\nНеверное имя пользователя или пароль";
        }

        // Session should already be started in sync1c.php
        $session_id = session_id();
        if (empty($session_id)) {
            session_start();
            $session_id = session_id();
        }

        $this->session->data['sync1c_auth'] = true;
        $this->session->data['sync1c_time'] = time();

        $this->log->write("Auth SUCCESS, session: $session_id");

        $cookie_name = session_name();
        return "success\n$cookie_name\n$session_id";
    }

    /**
     * Check if authenticated
     */
    private function isAuthenticated() {
        // Check session
        if (isset($this->session->data['sync1c_auth']) && $this->session->data['sync1c_auth']) {
            // Check timeout (1 hour)
            if (time() - $this->session->data['sync1c_time'] < 3600) {
                return true;
            }
        }
        return false;
    }

    /**
     * Catalog init - prepare for import
     */
    public function catalogInit() {
        $this->log->write('catalogInit called');

        if (!$this->isAuthenticated()) {
            return "failure\nНе авторизован";
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

        $this->log->write("catalogInit: zip=$zip_support, max_size=$max_size");

        return "zip=$zip_support\nfile_limit=$max_size";
    }

    /**
     * Handle file upload
     */
    public function catalogFile() {
        $this->log->write('catalogFile called');

        if (!$this->isAuthenticated()) {
            return "failure\nНе авторизован";
        }

        $filename = isset($_GET['filename']) ? basename($_GET['filename']) : '';

        if (empty($filename)) {
            return "failure\nНе указано имя файла";
        }

        // Read raw input
        $data = file_get_contents('php://input');

        if (empty($data)) {
            return "failure\nПустой файл";
        }

        $filepath = $this->upload_dir . $filename;

        // Handle chunked upload
        if (file_exists($filepath)) {
            file_put_contents($filepath, $data, FILE_APPEND);
        } else {
            file_put_contents($filepath, $data);
        }

        $this->log->write("File saved: $filename (" . strlen($data) . " bytes)");

        // Extract if zip
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'zip') {
            $this->extractZip($filepath);
        }

        return "success";
    }

    /**
     * Process catalog import
     */
    public function catalogImport() {
        $this->log->write('catalogImport called');

        if (!$this->isAuthenticated()) {
            return "failure\nНе авторизован";
        }

        $filename = isset($_GET['filename']) ? basename($_GET['filename']) : '';
        $filepath = $this->upload_dir . $filename;

        if (!file_exists($filepath)) {
            return "failure\nФайл не найден: $filename";
        }

        $this->log->write("Importing: $filename");

        try {
            // Load XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($filepath);

            if (!$xml) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return "failure\nОшибка XML: " . $errors[0]->message;
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
                    $result = "success\nНеизвестный тип файла, пропущен";
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

        // Import categories
        if (isset($xml->Классификатор->Группы)) {
            $this->importCategories($xml->Классификатор->Группы->Группа, 0);
            $stats['categories'] = $this->countCategories($xml->Классификатор->Группы->Группа);
        }

        // Import products
        if (isset($xml->Каталог->Товары->Товар)) {
            foreach ($xml->Каталог->Товары->Товар as $product) {
                $result = $this->importProduct($product);
                if ($result === 'created') {
                    $stats['products']++;
                } elseif ($result === 'updated') {
                    $stats['updated']++;
                }
            }
        }

        $msg = "Категорий: {$stats['categories']}, Товаров добавлено: {$stats['products']}, Обновлено: {$stats['updated']}";
        $this->log->write("Import complete: $msg");

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
            $this->log->write("[PRODUCT UPDATE] ID:$product_id | SKU:$sku | $name");
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
            $this->log->write("[PRODUCT NEW] ID:$product_id | SKU:$sku | $name");
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

        if (!isset($xml->ПакетПредложений->Предложения->Предложение)) {
            return "success\nНет предложений для импорта";
        }

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
            $product_name = $name_query->num_rows ? $name_query->row['name'] : 'Unknown';

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

            // Log with details
            $price_change = ($old_price != $price) ? " price:$old_price->$price" : "";
            $qty_change = ($old_qty != $quantity) ? " qty:$old_qty->$quantity" : "";
            $this->log->write("[OFFER] ID:$product_id | $product_name |$price_change$qty_change | status:$status");

            $stats['updated']++;
        }

        $msg = "Обновлено: {$stats['updated']}, Не найдено: {$stats['not_found']}";
        $this->log->write("Offers import complete: $msg");

        return "success\n$msg";
    }

    /**
     * Sale init
     */
    public function saleInit() {
        if (!$this->isAuthenticated()) {
            return "failure\nНе авторизован";
        }

        $max_size = 50 * 1024 * 1024;
        return "zip=no\nfile_limit=$max_size";
    }

    /**
     * Export orders to 1C
     */
    public function saleQuery() {
        if (!$this->isAuthenticated()) {
            return "failure\nНе авторизован";
        }

        $this->log->write('saleQuery: exporting orders');

        // Get orders not yet exported
        $query = $this->db->query("
            SELECT o.*, os.name as status_name
            FROM " . DB_PREFIX . "order o
            LEFT JOIN " . DB_PREFIX . "order_status os ON o.order_status_id = os.order_status_id AND os.language_id = 1
            WHERE o.order_status_id > 0
            AND o.order_id NOT IN (SELECT order_id FROM " . DB_PREFIX . "order_to_1c WHERE exported = 1)
            ORDER BY o.date_added DESC
            LIMIT 100
        ");

        if (!$query->num_rows) {
            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<КоммерческаяИнформация ВерсияСхемы=\"2.08\" ДатаФормирования=\"" . date('Y-m-d') . "\"></КоммерческаяИнформация>";
        }

        // Build XML
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<КоммерческаяИнформация ВерсияСхемы=\"2.08\" ДатаФормирования=\"" . date('Y-m-d') . "\">\n";

        foreach ($query->rows as $order) {
            $xml .= $this->buildOrderXml($order);

            // Mark as exported
            $this->db->query("INSERT INTO " . DB_PREFIX . "order_to_1c SET order_id = '" . (int)$order['order_id'] . "', exported = 1, date_exported = NOW()
                ON DUPLICATE KEY UPDATE exported = 1, date_exported = NOW()");
        }

        $xml .= "</КоммерческаяИнформация>";

        $this->log->write('saleQuery: exported ' . $query->num_rows . ' orders');

        return $xml;
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
            return "failure\nНе авторизован";
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
            $zip->extractTo($this->upload_dir);
            $zip->close();
            unlink($filepath);
            $this->log->write("Extracted: $filepath");
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
}
