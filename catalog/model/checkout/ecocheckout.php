<?php
/**
 * EcoCheckout Model
 * Handles order-related database operations for the EcoCheckout module
 */
class ModelCheckoutEcocheckout extends Model {

    /**
     * Save customer Nova Poshta data for future orders
     */
    public function saveCustomerNovaPoshtaData($customer_id, $data) {
        if (!$customer_id) {
            return false;
        }

        // Check if record exists
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_novaposhta`
            WHERE customer_id = '" . (int)$customer_id . "'");

        if ($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "customer_novaposhta` SET
                city = '" . $this->db->escape($data['city']) . "',
                city_ref = '" . $this->db->escape($data['city_ref']) . "',
                department = '" . $this->db->escape($data['department']) . "',
                department_ref = '" . $this->db->escape($data['department_ref']) . "',
                address = '" . $this->db->escape($data['address']) . "',
                date_modified = NOW()
                WHERE customer_id = '" . (int)$customer_id . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "customer_novaposhta` SET
                customer_id = '" . (int)$customer_id . "',
                city = '" . $this->db->escape($data['city']) . "',
                city_ref = '" . $this->db->escape($data['city_ref']) . "',
                department = '" . $this->db->escape($data['department']) . "',
                department_ref = '" . $this->db->escape($data['department_ref']) . "',
                address = '" . $this->db->escape($data['address']) . "',
                date_added = NOW(),
                date_modified = NOW()");
        }

        return true;
    }

    /**
     * Get customer Nova Poshta data
     */
    public function getCustomerNovaPoshtaData($customer_id) {
        if (!$customer_id) {
            return [];
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_novaposhta`
            WHERE customer_id = '" . (int)$customer_id . "'");

        if ($query->num_rows) {
            return $query->row;
        }

        return [];
    }

    /**
     * Check if table exists (for installation)
     */
    public function checkTable() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "customer_novaposhta'");
        return $query->num_rows > 0;
    }

    /**
     * Create Nova Poshta customer data table
     */
    public function createTable() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "customer_novaposhta` (
            `customer_novaposhta_id` INT(11) NOT NULL AUTO_INCREMENT,
            `customer_id` INT(11) NOT NULL,
            `city` VARCHAR(255) NOT NULL DEFAULT '',
            `city_ref` VARCHAR(36) NOT NULL DEFAULT '',
            `department` VARCHAR(255) NOT NULL DEFAULT '',
            `department_ref` VARCHAR(36) NOT NULL DEFAULT '',
            `address` VARCHAR(255) NOT NULL DEFAULT '',
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`customer_novaposhta_id`),
            KEY `customer_id` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /**
     * Get order shipping data by order ID
     */
    public function getOrderShippingData($order_id) {
        $query = $this->db->query("SELECT
                o.shipping_firstname,
                o.shipping_lastname,
                o.shipping_city,
                o.shipping_address_1,
                o.shipping_method,
                o.shipping_code
            FROM `" . DB_PREFIX . "order` o
            WHERE o.order_id = '" . (int)$order_id . "'");

        if ($query->num_rows) {
            return $query->row;
        }

        return [];
    }
}
