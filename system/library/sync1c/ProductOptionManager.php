<?php
/**
 * Product Option Manager for 1C Sync
 * Automatically extracts and assigns product options from product names
 */

class Sync1CProductOptionManager {
    private $db;
    private $log;

    // Pattern to match volume/weight in product name
    private $patterns = [
        // Volume patterns: 1л, 1,5л, 0.5л, 300мл
        '/(\d+[\.,]?\d*)\s*(л|мл)/ui' => 'volume',
        // Weight patterns: 350г, 100 г, 1кг, 250гр
        '/(\d+[\.,]?\d*)\s*(г|гр|кг)/ui' => 'weight',
    ];

    private $optionNames = [
        'volume' => "Об'єм",
        'weight' => 'Вага'
    ];

    public function __construct($db, $log) {
        $this->db = $db;
        $this->log = $log;
    }

    /**
     * Extract and assign options from product name
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     * @return bool True if options were assigned
     */
    public function assignOptionsFromName($product_id, $product_name) {
        $this->log->write("OPTION: Processing for product #$product_id: $product_name");

        $options_assigned = false;

        foreach ($this->patterns as $pattern => $option_type) {
            if (preg_match($pattern, $product_name, $matches)) {
                $value = $matches[1] . $matches[2]; // e.g., "350г", "1л"
                $value = str_replace(',', '.', $value); // Normalize decimal separator

                $this->log->write("OPTION: Found $option_type: $value");

                // Assign this option to product
                if ($this->assignOption($product_id, $option_type, $value)) {
                    $options_assigned = true;
                }
            }
        }

        if (!$options_assigned) {
            $this->log->write("OPTION: No volume/weight found in product name");
        }

        return $options_assigned;
    }

    /**
     * Assign specific option to product
     *
     * @param int $product_id Product ID
     * @param string $option_type Option type (volume, weight)
     * @param string $value Option value (e.g., "350г")
     * @return bool Success
     */
    private function assignOption($product_id, $option_type, $value) {
        // Get or create option
        $option_id = $this->getOrCreateOption($option_type);
        if (!$option_id) {
            $this->log->write("OPTION: Failed to get/create option for $option_type");
            return false;
        }

        // Get or create option value
        $option_value_id = $this->getOrCreateOptionValue($option_id, $value);
        if (!$option_value_id) {
            $this->log->write("OPTION: Failed to get/create option value: $value");
            return false;
        }

        // Remove existing options for this product (clean slate)
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

        // Add product option
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_option SET
            product_id = '" . (int)$product_id . "',
            option_id = '" . (int)$option_id . "',
            value = '',
            required = '0'");

        $product_option_id = $this->db->getLastId();

        // Add product option value
        $this->db->query("INSERT INTO " . DB_PREFIX . "product_option_value SET
            product_option_id = '" . (int)$product_option_id . "',
            product_id = '" . (int)$product_id . "',
            option_id = '" . (int)$option_id . "',
            option_value_id = '" . (int)$option_value_id . "',
            quantity = '1',
            subtract = '0',
            price = '0.0000',
            price_prefix = '+',
            points = '0',
            points_prefix = '+',
            weight = '0.00000000',
            weight_prefix = '+'");

        $this->log->write("OPTION: Assigned {$this->optionNames[$option_type]}: $value to product #$product_id");

        return true;
    }

    /**
     * Get or create option by type
     *
     * @param string $option_type Option type (volume, weight)
     * @return int|null Option ID
     */
    private function getOrCreateOption($option_type) {
        $option_name = $this->optionNames[$option_type];

        // Try to find existing option
        $query = $this->db->query("
            SELECT o.option_id
            FROM " . DB_PREFIX . "option o
            LEFT JOIN " . DB_PREFIX . "option_description od ON o.option_id = od.option_id
            WHERE od.name = '" . $this->db->escape($option_name) . "'
            AND od.language_id = 4
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['option_id'];
        }

        // Create new option
        $this->db->query("INSERT INTO " . DB_PREFIX . "option SET
            type = 'select',
            sort_order = '0'");

        $option_id = $this->db->getLastId();

        // Add option description
        $this->db->query("INSERT INTO " . DB_PREFIX . "option_description SET
            option_id = '" . (int)$option_id . "',
            language_id = '4',
            name = '" . $this->db->escape($option_name) . "'");

        $this->log->write("OPTION: Created new option: $option_name (ID: $option_id)");

        return $option_id;
    }

    /**
     * Get or create option value
     *
     * @param int $option_id Option ID
     * @param string $value Value (e.g., "350г")
     * @return int|null Option value ID
     */
    private function getOrCreateOptionValue($option_id, $value) {
        // Try to find existing option value
        $query = $this->db->query("
            SELECT ov.option_value_id
            FROM " . DB_PREFIX . "option_value ov
            LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON ov.option_value_id = ovd.option_value_id
            WHERE ov.option_id = '" . (int)$option_id . "'
            AND ovd.name = '" . $this->db->escape($value) . "'
            AND ovd.language_id = 4
            LIMIT 1
        ");

        if ($query->num_rows) {
            return $query->row['option_value_id'];
        }

        // Create new option value
        $this->db->query("INSERT INTO " . DB_PREFIX . "option_value SET
            option_id = '" . (int)$option_id . "',
            image = '',
            sort_order = '0'");

        $option_value_id = $this->db->getLastId();

        // Add option value description
        $this->db->query("INSERT INTO " . DB_PREFIX . "option_value_description SET
            option_value_id = '" . (int)$option_value_id . "',
            language_id = '4',
            option_id = '" . (int)$option_id . "',
            name = '" . $this->db->escape($value) . "'");

        $this->log->write("OPTION: Created new option value: $value (ID: $option_value_id)");

        return $option_value_id;
    }

    /**
     * Remove all options from product
     *
     * @param int $product_id Product ID
     */
    public function removeOptions($product_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");
    }
}
