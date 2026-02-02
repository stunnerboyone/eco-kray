<?php
/**
 * Product Filter Manager for 1C Sync
 * Automatically extracts and assigns product filters from product names
 */

class Sync1CProductFilterManager {
    private $db;
    private $log;

    // Pattern to match volume/weight in product name
    private $patterns = [
        // Volume patterns: 1л, 1,5л, 0.5л, 300мл
        '/(\d+[\.,]?\d*)\s*(л|мл)/ui' => 'volume',
        // Weight patterns: 350г, 100 г, 1кг, 250гр
        '/(\d+[\.,]?\d*)\s*(г|гр|кг)/ui' => 'weight',
    ];

    private $filterGroupNames = [
        'volume' => "Об'єм",
        'weight' => 'Вага'
    ];

    public function __construct($db, $log) {
        $this->db = $db;
        $this->log = $log;
    }

    /**
     * Extract and assign filters from product name
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     * @return bool True if filters were assigned
     */
    public function assignFiltersFromName($product_id, $product_name) {
        $this->log->write("FILTER: Processing for product #$product_id: $product_name");

        // Remove existing filters for this product
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

        $filters_assigned = false;

        foreach ($this->patterns as $pattern => $filter_type) {
            if (preg_match($pattern, $product_name, $matches)) {
                $value = $matches[1] . $matches[2]; // e.g., "350г", "1л"
                $value = str_replace(',', '.', $value); // Normalize decimal separator

                $this->log->write("FILTER: Found $filter_type: $value");

                // Assign this filter to product
                if ($this->assignFilter($product_id, $filter_type, $value)) {
                    $filters_assigned = true;
                }
            }
        }

        if (!$filters_assigned) {
            $this->log->write("FILTER: No volume/weight found in product name");
        } else {
            // Link filter groups to product categories (so filters show in catalog)
            $this->linkFiltersToProductCategories($product_id);
        }

        return $filters_assigned;
    }

    /**
     * Assign specific filter to product
     *
     * @param int $product_id Product ID
     * @param string $filter_type Filter type (volume, weight)
     * @param string $value Filter value (e.g., "350г")
     * @return bool Success
     */
    private function assignFilter($product_id, $filter_type, $value) {
        // Get or create filter group
        $filter_group_id = $this->getOrCreateFilterGroup($filter_type);
        if (!$filter_group_id) {
            $this->log->write("FILTER: Failed to get/create filter group for $filter_type");
            return false;
        }

        // Get or create filter value
        $filter_id = $this->getOrCreateFilter($filter_group_id, $value);
        if (!$filter_id) {
            $this->log->write("FILTER: Failed to get/create filter value: $value");
            return false;
        }

        // Add product filter link
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET
            product_id = '" . (int)$product_id . "',
            filter_id = '" . (int)$filter_id . "'");

        $this->log->write("FILTER: Assigned {$this->filterGroupNames[$filter_type]}: $value to product #$product_id");

        return true;
    }

    /**
     * Get or create filter group by type
     *
     * @param string $filter_type Filter type (volume, weight)
     * @return int|null Filter group ID
     */
    private function getOrCreateFilterGroup($filter_type) {
        $group_name = $this->filterGroupNames[$filter_type];

        // Try to find existing filter group
        $query = $this->db->query("
            SELECT fg.filter_group_id
            FROM " . DB_PREFIX . "filter_group fg
            LEFT JOIN " . DB_PREFIX . "filter_group_description fgd ON fg.filter_group_id = fgd.filter_group_id
            WHERE fgd.name = '" . $this->db->escape($group_name) . "'
            AND fgd.language_id = 4
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['filter_group_id'];
        }

        // Create new filter group
        $this->db->query("INSERT INTO " . DB_PREFIX . "filter_group SET
            sort_order = '0'");

        $filter_group_id = $this->db->getLastId();

        // Add filter group description
        $this->db->query("INSERT INTO " . DB_PREFIX . "filter_group_description SET
            filter_group_id = '" . (int)$filter_group_id . "',
            language_id = '4',
            name = '" . $this->db->escape($group_name) . "'");

        $this->log->write("FILTER: Created new filter group: $group_name (ID: $filter_group_id)");

        return $filter_group_id;
    }

    /**
     * Get or create filter value
     *
     * @param int $filter_group_id Filter group ID
     * @param string $value Value (e.g., "350г")
     * @return int|null Filter ID
     */
    private function getOrCreateFilter($filter_group_id, $value) {
        // Try to find existing filter
        $query = $this->db->query("
            SELECT f.filter_id
            FROM " . DB_PREFIX . "filter f
            LEFT JOIN " . DB_PREFIX . "filter_description fd ON f.filter_id = fd.filter_id
            WHERE f.filter_group_id = '" . (int)$filter_group_id . "'
            AND fd.name = '" . $this->db->escape($value) . "'
            AND fd.language_id = 4
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['filter_id'];
        }

        // Create new filter
        $this->db->query("INSERT INTO " . DB_PREFIX . "filter SET
            filter_group_id = '" . (int)$filter_group_id . "',
            sort_order = '0'");

        $filter_id = $this->db->getLastId();

        // Add filter description
        $this->db->query("INSERT INTO " . DB_PREFIX . "filter_description SET
            filter_id = '" . (int)$filter_id . "',
            language_id = '4',
            filter_group_id = '" . (int)$filter_group_id . "',
            name = '" . $this->db->escape($value) . "'");

        $this->log->write("FILTER: Created new filter value: $value (ID: $filter_id)");

        return $filter_id;
    }

    /**
     * Link filter groups to product categories
     * This makes filters visible in catalog for these categories
     *
     * @param int $product_id Product ID
     */
    private function linkFiltersToProductCategories($product_id) {
        // Get all categories this product belongs to
        $query = $this->db->query("
            SELECT category_id
            FROM " . DB_PREFIX . "product_to_category
            WHERE product_id = '" . (int)$product_id . "'
        ");

        if (!$query->num_rows) {
            $this->log->write("FILTER: Product #$product_id has no categories");
            return;
        }

        // Get all filter groups (Об'єм, Вага)
        foreach ($this->filterGroupNames as $filter_type => $group_name) {
            $filter_group_id = $this->getOrCreateFilterGroup($filter_type);
            if (!$filter_group_id) {
                continue;
            }

            // Link this filter group to each product category
            foreach ($query->rows as $row) {
                $category_id = $row['category_id'];

                // Check if already linked
                $check = $this->db->query("
                    SELECT category_id
                    FROM " . DB_PREFIX . "category_filter
                    WHERE category_id = '" . (int)$category_id . "'
                    AND filter_group_id = '" . (int)$filter_group_id . "'
                ");

                if (!$check->num_rows) {
                    // Link filter group to category
                    $this->db->query("INSERT INTO " . DB_PREFIX . "category_filter SET
                        category_id = '" . (int)$category_id . "',
                        filter_group_id = '" . (int)$filter_group_id . "'");

                    $this->log->write("FILTER: Linked filter group '$group_name' to category #$category_id");
                }
            }
        }
    }

    /**
     * Remove all filters from product
     *
     * @param int $product_id Product ID
     */
    public function removeFilters($product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");
    }
}
