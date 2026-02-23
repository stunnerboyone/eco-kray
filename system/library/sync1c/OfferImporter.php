<?php
/**
 * Offer Importer for 1C Sync
 * Handles import of prices and stock quantities from 1C
 */

class Sync1COfferImporter {
    private $db;
    private $log;
    private $cache;
    private $catalogImporter;
    private $imageLinker;
    private $seoUrlGenerator;
    private $filterManager;

    public function __construct($db, $log, $catalogImporter = null, $imageLinker = null, $seoUrlGenerator = null, $filterManager = null, $cache = null) {
        $this->db = $db;
        $this->log = $log;
        $this->cache = $cache;
        $this->catalogImporter = $catalogImporter;
        $this->imageLinker = $imageLinker;
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->filterManager = $filterManager;
    }

    /**
     * Import offers from XML
     *
     * @param SimpleXMLElement $xml XML data
     * @return string Result message
     */
    public function import($xml) {
        $stats = ['updated' => 0, 'not_found' => 0];
        $exported_products = [];

        if (!isset($xml->ПакетПредложений->Предложения->Предложение)) {
            return "success\nNo offers to import";
        }

        $this->log->write("=== PRODUCT EXPORT STARTED ===");

        foreach ($xml->ПакетПредложений->Предложения->Предложение as $offer) {
            $result = $this->importOffer($offer);

            if ($result['status'] === 'updated') {
                $stats['updated']++;
                $exported_products[] = $result['product_name'];

                // Log export details
                $this->log->write("EXPORTED: {$result['product_name']} {$result['price']} UAH {$result['quantity']} units");

                // Log changes if any
                if (!empty($result['changes'])) {
                    $this->log->write("  └─ Changes: " . implode(", ", $result['changes']));
                }
            } elseif ($result['status'] === 'not_found') {
                $stats['not_found']++;
                $this->log->write("ERROR: Product not found - GUID: {$result['guid']}");
            }
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
     * Import single offer
     *
     * @param SimpleXMLElement $offer Offer XML
     * @return array Result info
     */
    private function importOffer($offer) {
        $guid = (string)$offer->Ид;

        // Handle composite GUID (product#variant)
        if (strpos($guid, '#') !== false) {
            $guid = explode('#', $guid)[0];
        }

        // Find product (verify product actually exists, not just the link)
        $query = $this->db->query("
            SELECT p1c.product_id
            FROM " . DB_PREFIX . "product_to_1c p1c
            INNER JOIN " . DB_PREFIX . "product p ON p1c.product_id = p.product_id
            WHERE p1c.guid = '" . $this->db->escape($guid) . "'
        ");

        // If product not found - create it from offer data
        if (!$query->num_rows) {
            $product_name = isset($offer->Наименование) ? (string)$offer->Наименование : "Unknown Product (GUID: $guid)";

            $this->log->write("Creating new product from offer: $product_name (GUID: $guid)");

            // Create product
            $this->db->query("INSERT INTO " . DB_PREFIX . "product SET
                model = '" . $this->db->escape($guid) . "',
                sku = '',
                quantity = 0,
                price = 0,
                status = 1,
                date_added = NOW(),
                date_modified = NOW()");

            $product_id = $this->db->getLastId();

            // Add description
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_description SET
                product_id = '" . (int)$product_id . "',
                language_id = '4',
                name = '" . $this->db->escape($product_name) . "',
                description = ''");

            // Add to store
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '0'");

            // Link to 1C
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_1c SET product_id = '" . (int)$product_id . "', guid = '" . $this->db->escape($guid) . "'");

            $this->log->write("CREATED: Product #$product_id from offer");

            // Generate SEO URL and link image
            if ($this->seoUrlGenerator) {
                $seo_keyword = $this->seoUrlGenerator->generate('product', $product_id, $product_name);

                // Link image using SEO URL pattern
                if ($this->imageLinker) {
                    $this->imageLinker->linkImageBySeoUrl($product_id, $seo_keyword);
                }
            }

            // Assign filters from product name (volume/weight)
            if ($this->filterManager) {
                $this->filterManager->assignFiltersFromName($product_id, $product_name);
            }
        } else {
            $product_id = $query->row['product_id'];
        }

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

        // Update product
        $this->db->query("UPDATE " . DB_PREFIX . "product SET
            price = '" . (float)$price . "',
            quantity = '" . (int)$quantity . "',
            date_modified = NOW()
            WHERE product_id = '" . (int)$product_id . "'");

        // Remove any active special prices — 1C is the source of truth for pricing.
        // Stale product_special records would otherwise override the new price on the storefront.
        if ($old_price != $price) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");
        }

        // Clear product cache so updated prices appear immediately on the storefront
        if ($this->cache) {
            $this->cache->delete('product');
        }

        // Auto-categorize product based on name (update categories if needed)
        if ($this->catalogImporter) {
            $this->catalogImporter->autoCategorizeProduct($product_id, $product_name);
        }

        // Link image based on SEO URL (if not already linked)
        if ($this->seoUrlGenerator && $this->imageLinker) {
            $seo_keyword = $this->seoUrlGenerator->generate('product', $product_id, $product_name);
            if ($seo_keyword) {
                $this->imageLinker->linkImageBySeoUrl($product_id, $seo_keyword);
            }
        }

        // Assign filters from product name (volume/weight)
        if ($this->filterManager) {
            $this->filterManager->assignFiltersFromName($product_id, $product_name);
        }

        // Track changes for summary
        $changes = [];
        if ($old_price != $price) {
            $changes[] = "price: {$old_price} → {$price} UAH";
        }
        if ($old_qty != $quantity) {
            $changes[] = "quantity: {$old_qty} → {$quantity} units";
        }

        return [
            'status' => 'updated',
            'product_id' => $product_id,
            'product_name' => $product_name,
            'price' => $price,
            'quantity' => $quantity,
            'changes' => $changes
        ];
    }

    /**
     * Set catalog importer for auto-categorization
     *
     * @param Sync1CCatalogImporter $catalogImporter
     */
    public function setCatalogImporter($catalogImporter) {
        $this->catalogImporter = $catalogImporter;
    }

    /**
     * Set image linker for automatic image linking
     *
     * @param Sync1CImageLinker $imageLinker
     */
    public function setImageLinker($imageLinker) {
        $this->imageLinker = $imageLinker;
    }

    /**
     * Set SEO URL generator for automatic SEO URL generation
     *
     * @param Sync1CSeoUrlGenerator $seoUrlGenerator
     */
    public function setSeoUrlGenerator($seoUrlGenerator) {
        $this->seoUrlGenerator = $seoUrlGenerator;
    }
}
