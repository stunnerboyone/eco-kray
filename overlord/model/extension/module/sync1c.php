<?php
class ModelExtensionModuleSync1c extends Model {

    public function install() {
        // Create product_to_1c table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_to_1c` (
                `product_id` int(11) NOT NULL,
                `guid` varchar(36) NOT NULL,
                `date_synced` datetime DEFAULT NULL,
                PRIMARY KEY (`product_id`),
                UNIQUE KEY `guid` (`guid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create category_to_1c table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "category_to_1c` (
                `category_id` int(11) NOT NULL,
                `guid` varchar(36) NOT NULL,
                `date_synced` datetime DEFAULT NULL,
                PRIMARY KEY (`category_id`),
                UNIQUE KEY `guid` (`guid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create order_to_1c table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "order_to_1c` (
                `order_id` int(11) NOT NULL,
                `exported` tinyint(1) NOT NULL DEFAULT 0,
                `date_exported` datetime DEFAULT NULL,
                `guid_1c` varchar(36) DEFAULT NULL,
                PRIMARY KEY (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function getStats() {
        $stats = array();

        // Products synced
        $query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "product_to_1c");
        $stats['products_synced'] = $query->row['total'];

        // Categories synced
        $query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "category_to_1c");
        $stats['categories_synced'] = $query->row['total'];

        // Orders exported
        $query = $this->db->query("SELECT COUNT(*) as total FROM " . DB_PREFIX . "order_to_1c WHERE exported = 1");
        $stats['orders_exported'] = $query->row['total'];

        // Last sync
        $query = $this->db->query("SELECT MAX(date_synced) as last_sync FROM " . DB_PREFIX . "product_to_1c");
        $stats['last_sync'] = $query->row['last_sync'] ?: 'Ніколи';

        return $stats;
    }
}
