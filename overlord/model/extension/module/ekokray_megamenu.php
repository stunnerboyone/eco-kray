<?php
/**
 * EKO-KRAY Custom Megamenu Module - Backend Model
 *
 * @author  EKO-KRAY Development Team
 * @version 1.0.0
 * @license MIT
 */
class ModelExtensionModuleEkokrayMegamenu extends Model {

    /**
     * Install module - Create database tables
     */
    public function install() {
        // Create main menu table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ekokray_menu` (
                `menu_id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(64) NOT NULL,
                `status` tinyint(1) NOT NULL DEFAULT '1',
                `mobile_breakpoint` int(11) DEFAULT '992',
                `cache_enabled` tinyint(1) DEFAULT '1',
                `cache_duration` int(11) DEFAULT '3600',
                `date_added` datetime NOT NULL,
                `date_modified` datetime NOT NULL,
                PRIMARY KEY (`menu_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create menu items table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ekokray_menu_item` (
                `item_id` int(11) NOT NULL AUTO_INCREMENT,
                `menu_id` int(11) NOT NULL,
                `parent_id` int(11) DEFAULT '0',
                `item_type` enum('category','custom_link','category_tree') NOT NULL DEFAULT 'category',
                `category_id` int(11) DEFAULT NULL,
                `link` varchar(255) DEFAULT NULL,
                `target` varchar(20) DEFAULT '_self',
                `icon` varchar(255) DEFAULT NULL,
                `show_products` tinyint(1) DEFAULT '0',
                `product_limit` int(11) DEFAULT '8',
                `sort_order` int(11) NOT NULL DEFAULT '0',
                `status` tinyint(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`item_id`),
                KEY `menu_id` (`menu_id`),
                KEY `parent_id` (`parent_id`),
                KEY `category_id` (`category_id`),
                KEY `sort_order` (`sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Create menu item descriptions table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ekokray_menu_item_description` (
                `item_id` int(11) NOT NULL,
                `language_id` int(11) NOT NULL,
                `title` varchar(255) NOT NULL,
                `description` text,
                PRIMARY KEY (`item_id`, `language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Uninstall module - Drop database tables
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ekokray_menu_item_description`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ekokray_menu_item`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "ekokray_menu`");
    }

    /**
     * Add a new menu
     *
     * @param array $data Menu data
     * @return int Menu ID
     */
    public function addMenu($data) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "ekokray_menu`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `status` = '" . (int)$data['status'] . "',
                `mobile_breakpoint` = '" . (isset($data['mobile_breakpoint']) ? (int)$data['mobile_breakpoint'] : 992) . "',
                `cache_enabled` = '" . (isset($data['cache_enabled']) ? (int)$data['cache_enabled'] : 1) . "',
                `cache_duration` = '" . (isset($data['cache_duration']) ? (int)$data['cache_duration'] : 3600) . "',
                `date_added` = NOW(),
                `date_modified` = NOW()
        ");

        return $this->db->getLastId();
    }

    /**
     * Edit an existing menu
     *
     * @param int $menu_id Menu ID
     * @param array $data Menu data
     */
    public function editMenu($menu_id, $data) {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "ekokray_menu`
            SET
                `name` = '" . $this->db->escape($data['name']) . "',
                `status` = '" . (int)$data['status'] . "',
                `mobile_breakpoint` = '" . (isset($data['mobile_breakpoint']) ? (int)$data['mobile_breakpoint'] : 992) . "',
                `cache_enabled` = '" . (isset($data['cache_enabled']) ? (int)$data['cache_enabled'] : 1) . "',
                `cache_duration` = '" . (isset($data['cache_duration']) ? (int)$data['cache_duration'] : 3600) . "',
                `date_modified` = NOW()
            WHERE `menu_id` = '" . (int)$menu_id . "'
        ");

        // Clear cache for this menu
        $this->clearMenuCache($menu_id);
    }

    /**
     * Delete a menu and all its items
     *
     * @param int $menu_id Menu ID
     */
    public function deleteMenu($menu_id) {
        // Delete menu item descriptions
        $this->db->query("
            DELETE mid FROM `" . DB_PREFIX . "ekokray_menu_item_description` mid
            INNER JOIN `" . DB_PREFIX . "ekokray_menu_item` mi ON mid.item_id = mi.item_id
            WHERE mi.menu_id = '" . (int)$menu_id . "'
        ");

        // Delete menu items
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ekokray_menu_item` WHERE `menu_id` = '" . (int)$menu_id . "'");

        // Delete menu
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ekokray_menu` WHERE `menu_id` = '" . (int)$menu_id . "'");

        // Clear cache
        $this->clearMenuCache($menu_id);
    }

    /**
     * Get menu by ID
     *
     * @param int $menu_id Menu ID
     * @return array Menu data
     */
    public function getMenu($menu_id) {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "ekokray_menu`
            WHERE `menu_id` = '" . (int)$menu_id . "'
        ");

        return $query->row;
    }

    /**
     * Get all menus
     *
     * @param array $data Filter data
     * @return array List of menus
     */
    public function getMenus($data = array()) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "ekokray_menu` ORDER BY `name` ASC";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }

            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * Get total number of menus
     *
     * @return int Total menus
     */
    public function getTotalMenus() {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ekokray_menu`");

        return $query->row['total'];
    }

    /**
     * Add a menu item
     *
     * @param array $data Item data
     * @return int Item ID
     */
    public function addMenuItem($data) {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "ekokray_menu_item`
            SET
                `menu_id` = '" . (int)$data['menu_id'] . "',
                `parent_id` = '" . (int)$data['parent_id'] . "',
                `item_type` = '" . $this->db->escape($data['item_type']) . "',
                `category_id` = '" . (isset($data['category_id']) ? (int)$data['category_id'] : 0) . "',
                `link` = '" . $this->db->escape($data['link']) . "',
                `target` = '" . $this->db->escape($data['target']) . "',
                `icon` = '" . $this->db->escape($data['icon']) . "',
                `show_products` = '" . (int)$data['show_products'] . "',
                `product_limit` = '" . (int)$data['product_limit'] . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `status` = '" . (int)$data['status'] . "'
        ");

        $item_id = $this->db->getLastId();

        // Add descriptions for each language
        if (isset($data['menu_item_description'])) {
            foreach ($data['menu_item_description'] as $language_id => $description) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "ekokray_menu_item_description`
                    SET
                        `item_id` = '" . (int)$item_id . "',
                        `language_id` = '" . (int)$language_id . "',
                        `title` = '" . $this->db->escape($description['title']) . "',
                        `description` = '" . $this->db->escape($description['description']) . "'
                ");
            }
        }

        // Clear menu cache
        $this->clearMenuCache($data['menu_id']);

        return $item_id;
    }

    /**
     * Edit a menu item
     *
     * @param int $item_id Item ID
     * @param array $data Item data
     */
    public function editMenuItem($item_id, $data) {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "ekokray_menu_item`
            SET
                `menu_id` = '" . (int)$data['menu_id'] . "',
                `parent_id` = '" . (int)$data['parent_id'] . "',
                `item_type` = '" . $this->db->escape($data['item_type']) . "',
                `category_id` = '" . (isset($data['category_id']) ? (int)$data['category_id'] : 0) . "',
                `link` = '" . $this->db->escape($data['link']) . "',
                `target` = '" . $this->db->escape($data['target']) . "',
                `icon` = '" . $this->db->escape($data['icon']) . "',
                `show_products` = '" . (int)$data['show_products'] . "',
                `product_limit` = '" . (int)$data['product_limit'] . "',
                `sort_order` = '" . (int)$data['sort_order'] . "',
                `status` = '" . (int)$data['status'] . "'
            WHERE `item_id` = '" . (int)$item_id . "'
        ");

        // Delete existing descriptions
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ekokray_menu_item_description` WHERE `item_id` = '" . (int)$item_id . "'");

        // Add new descriptions
        if (isset($data['menu_item_description'])) {
            foreach ($data['menu_item_description'] as $language_id => $description) {
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "ekokray_menu_item_description`
                    SET
                        `item_id` = '" . (int)$item_id . "',
                        `language_id` = '" . (int)$language_id . "',
                        `title` = '" . $this->db->escape($description['title']) . "',
                        `description` = '" . $this->db->escape($description['description']) . "'
                ");
            }
        }

        // Clear menu cache
        $this->clearMenuCache($data['menu_id']);
    }

    /**
     * Delete a menu item
     *
     * @param int $item_id Item ID
     */
    public function deleteMenuItem($item_id) {
        // Get menu_id before deletion for cache clearing
        $item = $this->getMenuItem($item_id);

        // Delete descriptions
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ekokray_menu_item_description` WHERE `item_id` = '" . (int)$item_id . "'");

        // Delete item
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ekokray_menu_item` WHERE `item_id` = '" . (int)$item_id . "'");

        // Clear cache
        if ($item) {
            $this->clearMenuCache($item['menu_id']);
        }
    }

    /**
     * Get menu item by ID
     *
     * @param int $item_id Item ID
     * @return array Item data
     */
    public function getMenuItem($item_id) {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "ekokray_menu_item`
            WHERE `item_id` = '" . (int)$item_id . "'
        ");

        $item = $query->row;

        if ($item) {
            // Add descriptions for all languages
            $item['descriptions'] = $this->getMenuItemDescriptions($item_id);
        }

        return $item;
    }

    /**
     * Get menu item descriptions
     *
     * @param int $item_id Item ID
     * @return array Item descriptions indexed by language_id
     */
    public function getMenuItemDescriptions($item_id) {
        $menu_item_description_data = array();

        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "ekokray_menu_item_description`
            WHERE `item_id` = '" . (int)$item_id . "'
        ");

        foreach ($query->rows as $result) {
            $menu_item_description_data[$result['language_id']] = array(
                'title'       => $result['title'],
                'description' => $result['description']
            );
        }

        return $menu_item_description_data;
    }

    /**
     * Get all menu items for a menu
     *
     * @param int $menu_id Menu ID
     * @param int $parent_id Parent item ID (0 for top level)
     * @return array List of menu items
     */
    public function getMenuItems($menu_id, $parent_id = 0) {
        $query = $this->db->query("
            SELECT mi.*, mid.title, mid.description
            FROM `" . DB_PREFIX . "ekokray_menu_item` mi
            LEFT JOIN `" . DB_PREFIX . "ekokray_menu_item_description` mid ON (mi.item_id = mid.item_id)
            WHERE mi.menu_id = '" . (int)$menu_id . "'
            AND mi.parent_id = '" . (int)$parent_id . "'
            AND mid.language_id = '" . (int)$this->config->get('config_language_id') . "'
            ORDER BY mi.sort_order ASC, mid.title ASC
        ");

        return $query->rows;
    }

    /**
     * Update sort order for menu items
     *
     * @param array $items Array of item_id => sort_order
     */
    public function updateSortOrder($items) {
        foreach ($items as $item_id => $sort_order) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "ekokray_menu_item`
                SET `sort_order` = '" . (int)$sort_order . "'
                WHERE `item_id` = '" . (int)$item_id . "'
            ");
        }
    }

    /**
     * Clear menu cache
     *
     * @param int $menu_id Menu ID
     */
    public function clearMenuCache($menu_id) {
        $this->cache->delete('ekokray.menu.' . $menu_id);

        // Clear for all languages
        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $this->cache->delete('ekokray.menu.' . $menu_id . '.' . $language['language_id']);
        }
    }
}
