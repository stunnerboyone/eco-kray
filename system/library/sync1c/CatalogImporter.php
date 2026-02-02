<?php
/**
 * Catalog Importer for 1C Sync
 * Handles import of categories and products from 1C
 */

class Sync1CCatalogImporter {
    private $db;
    private $log;
    private $seoUrlGenerator;
    private $imageLinker;

    // Category mapping configuration
    private $categoryKeywords = [
        'Натуральні соки' => ['сік', 'сок', 'березовий'],
        'Пастила' => ['пастила'],
        'Сухофрукти' => ['сухофрукт', 'узвар'],
        'Джеми/Соуси/Конфітюр' => ['джем', 'соус', 'конфітюр', 'конфитюр'],
        'Набори' => ['набір', 'набор']
    ];

    public function __construct($db, $log, $seoUrlGenerator, $imageLinker = null) {
        $this->db = $db;
        $this->log = $log;
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->imageLinker = $imageLinker;
    }

    /**
     * Import catalog from XML
     *
     * @param SimpleXMLElement $xml XML data
     * @return string Result message
     */
    public function import($xml) {
        $stats = ['categories' => 0, 'products' => 0, 'updated' => 0];

        $this->log->write("=== CATALOG IMPORT STARTED ===");

        // Clean orphaned 1C links (products deleted but links remain)
        $this->cleanOrphanedLinks();

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
     *
     * @param SimpleXMLElement $groups Category groups
     * @param int $parent_id Parent category ID
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
                $this->db->query("INSERT INTO " . DB_PREFIX . "category_description SET category_id = '" . (int)$category_id . "', language_id = '4', name = '" . $this->db->escape($name) . "'");

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
     *
     * @param SimpleXMLElement $product Product XML
     * @return string 'created' or 'updated'
     */
    private function importProduct($product) {
        $guid = (string)$product->Ид;
        $sku = (string)$product->Артикул;
        $name = (string)$product->Наименование;
        $description = isset($product->Описание) ? (string)$product->Описание : '';

        // Check if exists (verify product actually exists, not just the link)
        $query = $this->db->query("
            SELECT p1c.product_id
            FROM " . DB_PREFIX . "product_to_1c p1c
            INNER JOIN " . DB_PREFIX . "product p ON p1c.product_id = p.product_id
            WHERE p1c.guid = '" . $this->db->escape($guid) . "'
        ");

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
            $seo_keyword = $this->seoUrlGenerator->generate('product', $product_id, $name);

            // Link image using SEO URL pattern
            if ($this->imageLinker) {
                $this->imageLinker->linkImageBySeoUrl($product_id, $seo_keyword);
            }
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
                language_id = '4',
                name = '" . $this->db->escape($name) . "',
                description = '" . $this->db->escape($description) . "'");

            // Add to store
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '0'");

            // Link to 1C
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_1c SET product_id = '" . (int)$product_id . "', guid = '" . $this->db->escape($guid) . "'");

            $result = 'created';
            $this->log->write("CREATED: Product #$product_id - $name (SKU: $sku)");

            // Generate SEO URL
            $seo_keyword = $this->seoUrlGenerator->generate('product', $product_id, $name);

            // Link image using SEO URL pattern
            if ($this->imageLinker) {
                $this->imageLinker->linkImageBySeoUrl($product_id, $seo_keyword);
            }
        }

        // Auto-categorize based on product name
        $this->autoCategorizeProduct($product_id, $name);

        return $result;
    }

    /**
     * Automatically categorize product based on name keywords
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     */
    public function autoCategorizeProduct($product_id, $product_name) {
        $this->log->write("Auto-categorizing: $product_name");

        // Clear existing categories first
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        // Extract first word from product name
        $name_lower = mb_strtolower($product_name, 'UTF-8');
        $words = preg_split('/[\s\-]+/', $name_lower, 2); // Split by space or hyphen, limit to 2 parts
        $first_word = $words[0];

        $this->log->write("  → First word: '$first_word'");

        $assigned_categories = [];

        // Find matching categories based on FIRST WORD only
        foreach ($this->categoryKeywords as $category_name => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($first_word, $keyword, 0, 'UTF-8') !== false) {
                    // Find category by name
                    $this->log->write("  → Searching for category: '$category_name' (keyword: $keyword matched first word)");

                    $cat_query = $this->db->query("
                        SELECT c.category_id, cd.name
                        FROM " . DB_PREFIX . "category c
                        LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
                        WHERE cd.name = '" . $this->db->escape($category_name) . "'
                        AND cd.language_id = 4
                        LIMIT 1
                    ");

                    $this->log->write("  → Query returned: " . $cat_query->num_rows . " rows");

                    if ($cat_query->num_rows) {
                        $category_id = $cat_query->row['category_id'];
                        $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category
                            SET product_id = '" . (int)$product_id . "',
                            category_id = '" . (int)$category_id . "'");
                        $assigned_categories[] = $category_name;
                        $this->log->write("  ✓ Added to category: $category_name (ID: $category_id)");
                    } else {
                        // Debug: Try to find ANY category
                        $debug_query = $this->db->query("SELECT cd.name FROM " . DB_PREFIX . "category_description cd WHERE cd.language_id = 4 LIMIT 5");
                        $found_cats = [];
                        if ($debug_query->num_rows) {
                            foreach ($debug_query->rows as $row) {
                                $found_cats[] = $row['name'];
                            }
                        }
                        $this->log->write("  ! Category '$category_name' not found. Sample categories in DB: " . implode(', ', $found_cats));
                    }
                    break; // Found keyword, move to next category
                }
            }
        }

        // Always add to "Наша продукція" category
        $main_cat_query = $this->db->query("
            SELECT c.category_id
            FROM " . DB_PREFIX . "category c
            LEFT JOIN " . DB_PREFIX . "category_description cd ON c.category_id = cd.category_id
            WHERE cd.name IN ('Наша продукція', 'Наша продукция')
            AND cd.language_id = 4
            LIMIT 1
        ");

        if ($main_cat_query->num_rows) {
            $main_cat_id = $main_cat_query->row['category_id'];
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category
                SET product_id = '" . (int)$product_id . "',
                category_id = '" . (int)$main_cat_id . "'");
            $assigned_categories[] = 'Наша продукція';
            $this->log->write("  → Added to category: Наша продукція");
        }

        if (empty($assigned_categories)) {
            $this->log->write("  ! No categories matched");
        } else {
            $this->log->write("  ✓ Assigned to: " . implode(', ', $assigned_categories));
        }
    }

    /**
     * Count categories recursively
     *
     * @param SimpleXMLElement $groups Category groups
     * @return int Count
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
     * Clean orphaned 1C links (when products deleted but links remain)
     */
    private function cleanOrphanedLinks() {
        // Delete product links where product doesn't exist
        $result = $this->db->query("
            DELETE p1c FROM " . DB_PREFIX . "product_to_1c p1c
            LEFT JOIN " . DB_PREFIX . "product p ON p1c.product_id = p.product_id
            WHERE p.product_id IS NULL
        ");

        $affected = $this->db->countAffected();
        if ($affected > 0) {
            $this->log->write("Cleaned $affected orphaned product links");
        }

        // Delete category links where category doesn't exist
        $result = $this->db->query("
            DELETE c1c FROM " . DB_PREFIX . "category_to_1c c1c
            LEFT JOIN " . DB_PREFIX . "category c ON c1c.category_id = c.category_id
            WHERE c.category_id IS NULL
        ");

        $affected = $this->db->countAffected();
        if ($affected > 0) {
            $this->log->write("Cleaned $affected orphaned category links");
        }
    }

    /**
     * Set category mapping configuration
     * Allows external configuration of category keywords
     *
     * @param array $keywords Category name => keywords array
     */
    public function setCategoryKeywords($keywords) {
        $this->categoryKeywords = $keywords;
    }

    /**
     * Get current category mapping configuration
     *
     * @return array Category keywords mapping
     */
    public function getCategoryKeywords() {
        return $this->categoryKeywords;
    }

    /**
     * Set image linker for automatic image linking
     *
     * @param Sync1CImageLinker $imageLinker
     */
    public function setImageLinker($imageLinker) {
        $this->imageLinker = $imageLinker;
    }
}
