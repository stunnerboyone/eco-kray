<?php
/**
 * Sitemap Generator Script
 * Generates sitemap.xml file for search engines
 *
 * Usage: php generate-sitemap.php
 *
 * This script can be run manually or via cron job
 * Recommended: Run daily or after significant catalog changes
 */

// Load OpenCart configuration
if (is_file('config.php')) {
    require_once('config.php');
} else {
    die("Error: config.php not found\n");
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

// Create Registry
$registry = new Registry();

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// Config
$config = new Config();
$registry->set('config', $config);

// Load
$loader = new Loader($registry);
$registry->set('load', $loader);

// URL
$url = new Url(HTTP_SERVER, HTTPS_SERVER);
$registry->set('url', $url);

// Request
$request = new Request();
$registry->set('request', $request);

// Response
$response = new Response();
$registry->set('response', $response);

// Session (needed for some models)
$session = new Session();
$registry->set('session', $session);

// Load models
$loader->model('catalog/product');
$loader->model('catalog/category');
$loader->model('catalog/manufacturer');
$loader->model('catalog/information');
$loader->model('tool/image');
$loader->model('setting/setting');

// Get store settings
$settings = $loader->model('setting/setting')->getSetting('config');

echo "=================================\n";
echo "Sitemap Generator\n";
echo "=================================\n";
echo "Store: " . (isset($settings['config_name']) ? $settings['config_name'] : 'OpenCart') . "\n";
echo "URL: " . HTTPS_SERVER . "\n";
echo "=================================\n\n";

// Start XML
$output  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

// Add homepage
$output .= "  <url>\n";
$output .= "    <loc>" . HTTPS_SERVER . "</loc>\n";
$output .= "    <changefreq>daily</changefreq>\n";
$output .= "    <priority>1.0</priority>\n";
$output .= "    <lastmod>" . date('Y-m-d\TH:i:sP') . "</lastmod>\n";
$output .= "  </url>\n";

echo "Adding homepage...\n";

// Add products
echo "Adding products...\n";
$products = $loader->model('catalog/product')->getProducts();
$product_count = 0;

foreach ($products as $product) {
    if ($product['status']) {
        $output .= "  <url>\n";
        $output .= "    <loc>" . str_replace('&', '&amp;', $url->link('product/product', 'product_id=' . $product['product_id'])) . "</loc>\n";
        $output .= "    <changefreq>weekly</changefreq>\n";
        $output .= "    <lastmod>" . date('Y-m-d\TH:i:sP', strtotime($product['date_modified'])) . "</lastmod>\n";
        $output .= "    <priority>0.9</priority>\n";

        if (!empty($product['image'])) {
            $image_url = HTTPS_SERVER . 'image/' . $product['image'];
            $output .= "    <image:image>\n";
            $output .= "      <image:loc>" . htmlspecialchars($image_url) . "</image:loc>\n";
            $output .= "      <image:caption>" . htmlspecialchars($product['name']) . "</image:caption>\n";
            $output .= "      <image:title>" . htmlspecialchars($product['name']) . "</image:title>\n";
            $output .= "    </image:image>\n";
        }

        $output .= "  </url>\n";
        $product_count++;
    }
}

echo "  ✓ Added {$product_count} products\n";

// Add categories
echo "Adding categories...\n";
$category_count = 0;

function getCategories($loader, $url, $parent_id = 0, &$category_count) {
    $output = '';
    $categories = $loader->model('catalog/category')->getCategories($parent_id);

    foreach ($categories as $category) {
        if ($category['status']) {
            $output .= "  <url>\n";
            $output .= "    <loc>" . str_replace('&', '&amp;', $url->link('product/category', 'path=' . $category['category_id'])) . "</loc>\n";
            $output .= "    <changefreq>weekly</changefreq>\n";
            $output .= "    <priority>0.8</priority>\n";
            $output .= "  </url>\n";
            $category_count++;

            $output .= getCategories($loader, $url, $category['category_id'], $category_count);
        }
    }

    return $output;
}

$output .= getCategories($loader, $url, 0, $category_count);
echo "  ✓ Added {$category_count} categories\n";

// Add manufacturers
echo "Adding manufacturers...\n";
$manufacturers = $loader->model('catalog/manufacturer')->getManufacturers();
$manufacturer_count = 0;

foreach ($manufacturers as $manufacturer) {
    $output .= "  <url>\n";
    $output .= "    <loc>" . str_replace('&', '&amp;', $url->link('product/manufacturer/info', 'manufacturer_id=' . $manufacturer['manufacturer_id'])) . "</loc>\n";
    $output .= "    <changefreq>monthly</changefreq>\n";
    $output .= "    <priority>0.6</priority>\n";
    $output .= "  </url>\n";
    $manufacturer_count++;
}

echo "  ✓ Added {$manufacturer_count} manufacturers\n";

// Add information pages
echo "Adding information pages...\n";
$informations = $loader->model('catalog/information')->getInformations();
$information_count = 0;

foreach ($informations as $information) {
    if ($information['status']) {
        $output .= "  <url>\n";
        $output .= "    <loc>" . str_replace('&', '&amp;', $url->link('information/information', 'information_id=' . $information['information_id'])) . "</loc>\n";
        $output .= "    <changefreq>monthly</changefreq>\n";
        $output .= "    <priority>0.5</priority>\n";
        $output .= "  </url>\n";
        $information_count++;
    }
}

echo "  ✓ Added {$information_count} information pages\n";

// Close XML
$output .= "</urlset>\n";

// Save to file
$sitemap_file = __DIR__ . '/sitemap.xml';
$result = file_put_contents($sitemap_file, $output);

echo "\n=================================\n";

if ($result !== false) {
    $file_size = round(filesize($sitemap_file) / 1024, 2);
    echo "✓ Sitemap generated successfully!\n";
    echo "=================================\n";
    echo "File: sitemap.xml\n";
    echo "Size: {$file_size} KB\n";
    echo "Total URLs: " . ($product_count + $category_count + $manufacturer_count + $information_count + 1) . "\n";
    echo "\nNext steps:\n";
    echo "1. Visit " . HTTPS_SERVER . "sitemap.xml to verify\n";
    echo "2. Submit to Google Search Console\n";
    echo "3. Submit to Bing Webmaster Tools\n";
    echo "4. Add to robots.txt: Sitemap: " . HTTPS_SERVER . "sitemap.xml\n";
} else {
    echo "✗ Error: Failed to write sitemap.xml\n";
    echo "Check file permissions on the web root directory.\n";
}

echo "\n";
