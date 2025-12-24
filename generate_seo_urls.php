<?php
/**
 * Generate SEO URLs for all products and categories
 * Run once: php generate_seo_urls.php
 */

// Load OpenCart
require_once('overlord/config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Load Sync1C library
require_once(DIR_SYSTEM . 'library/sync1c/sync1c.php');

// Registry
$registry = new Registry();

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Config
$config = new Config();
$registry->set('config', $config);

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
$log = new Log('seo_generation.log');
$registry->set('log', $log);

// Session (dummy)
$registry->set('session', new stdClass());

// Request (dummy)
$registry->set('request', new stdClass());

// Create Sync1C instance
$sync = new Sync1C($registry);

// Use reflection to access private method
$reflection = new ReflectionClass($sync);
$generateSeoUrl = $reflection->getMethod('generateSeoUrl');
$generateSeoUrl->setAccessible(true);

echo "=== SEO URL Generation Started ===\n\n";

// Generate for categories
echo "Generating SEO URLs for categories...\n";
$categories = $db->query("
    SELECT c.category_id, cd.name
    FROM " . DB_PREFIX . "category c
    LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
    WHERE cd.language_id = 1
    ORDER BY c.category_id
");

$cat_count = 0;
foreach ($categories->rows as $category) {
    try {
        $keyword = $generateSeoUrl->invoke($sync, 'category', $category['category_id'], $category['name']);
        echo "✓ Category #{$category['category_id']}: {$category['name']} → $keyword\n";
        $cat_count++;
    } catch (Exception $e) {
        echo "✗ Error for category #{$category['category_id']}: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Generate for products
echo "Generating SEO URLs for products...\n";
$products = $db->query("
    SELECT p.product_id, pd.name
    FROM " . DB_PREFIX . "product p
    LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id
    WHERE pd.language_id = 1
    ORDER BY p.product_id
");

$prod_count = 0;
foreach ($products->rows as $product) {
    try {
        $keyword = $generateSeoUrl->invoke($sync, 'product', $product['product_id'], $product['name']);
        echo "✓ Product #{$product['product_id']}: {$product['name']} → $keyword\n";
        $prod_count++;
    } catch (Exception $e) {
        echo "✗ Error for product #{$product['product_id']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== SEO URL Generation Completed ===\n";
echo "Categories: $cat_count\n";
echo "Products: $prod_count\n";
echo "Total: " . ($cat_count + $prod_count) . "\n";
echo "\nCheck detailed logs in: storage/logs/seo_generation.log\n";
