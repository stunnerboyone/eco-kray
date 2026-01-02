<?php
/**
 * EKO-KRAY Custom Megamenu Module - Frontend Model
 *
 * @author  EKO-KRAY Development Team
 * @version 1.0.0
 * @license MIT
 */
class ModelExtensionModuleEkokrayMegamenu extends Model {

    /**
     * Get current language ID from config_language code
     *
     * @return int Language ID
     */
    protected function getLanguageId() {
        $language_code = $this->config->get('config_language');
        $language_query = $this->db->query("
            SELECT language_id FROM `" . DB_PREFIX . "language`
            WHERE code = '" . $this->db->escape($language_code) . "'
            AND status = '1'
        ");

        if ($language_query->num_rows) {
            return (int)$language_query->row['language_id'];
        }

        // Fallback to first active language
        return 1;
    }

    /**
     * Get menu structure with all items (hierarchical)
     *
     * @param int $menu_id Menu ID
     * @param int $language_id Language ID
     * @return array Menu structure
     */
    public function getMenuStructure($menu_id, $language_id = null) {
        if ($language_id === null) {
            $language_id = $this->getLanguageId();
        }

        // Try to get from cache
        $cache_key = 'ekokray.menu.' . $menu_id . '.' . $language_id;
        $menu_data = $this->cache->get($cache_key);

        if ($menu_data) {
            return $menu_data;
        }

        // Get menu settings
        $menu_query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "ekokray_menu`
            WHERE `menu_id` = '" . (int)$menu_id . "'
            AND `status` = '1'
        ");

        if (!$menu_query->num_rows) {
            return array();
        }

        $menu = $menu_query->row;

        // Get menu items (only top level items)
        $items = $this->getMenuItems($menu_id, 0, $language_id);

        // Build hierarchical structure
        foreach ($items as &$item) {
            $item['children'] = $this->getMenuItems($menu_id, $item['item_id'], $language_id);

            // Get category info if item is category type
            if ($item['item_type'] == 'category' && $item['category_id']) {
                $item['category'] = $this->getCategoryInfo($item['category_id'], $language_id);
            }

            // If item has category_tree type, get all subcategories
            if ($item['item_type'] == 'category_tree' && $item['category_id']) {
                error_log("MEGAMENU DEBUG - Loading category_tree for item_id={$item['item_id']}, category_id={$item['category_id']}, title={$item['title']}");
                $item['subcategories'] = $this->getCategoryTree($item['category_id'], $language_id);
                error_log("MEGAMENU DEBUG - Loaded " . count($item['subcategories']) . " subcategories for item '{$item['title']}'");
            }
        }

        $menu_data = array(
            'menu_id'           => $menu['menu_id'],
            'name'              => $menu['name'],
            'mobile_breakpoint' => $menu['mobile_breakpoint'],
            'items'             => $items
        );

        // Cache if enabled
        if ($menu['cache_enabled']) {
            $this->cache->set($cache_key, $menu_data, $menu['cache_duration']);
        }

        return $menu_data;
    }

    /**
     * Get menu items for a specific parent
     *
     * @param int $menu_id Menu ID
     * @param int $parent_id Parent item ID
     * @param int $language_id Language ID
     * @return array Menu items
     */
    protected function getMenuItems($menu_id, $parent_id, $language_id) {
        $query = $this->db->query("
            SELECT
                mi.*,
                mid.title,
                mid.description
            FROM `" . DB_PREFIX . "ekokray_menu_item` mi
            LEFT JOIN `" . DB_PREFIX . "ekokray_menu_item_description` mid
                ON (mi.item_id = mid.item_id AND mid.language_id = '" . (int)$language_id . "')
            WHERE mi.menu_id = '" . (int)$menu_id . "'
            AND mi.parent_id = '" . (int)$parent_id . "'
            AND mi.status = '1'
            ORDER BY mi.sort_order ASC, mid.title ASC
        ");

        return $query->rows;
    }

    /**
     * Get category information
     *
     * @param int $category_id Category ID
     * @param int $language_id Language ID
     * @return array Category data
     */
    protected function getCategoryInfo($category_id, $language_id) {
        $query = $this->db->query("
            SELECT
                c.category_id,
                cd.name,
                c.image,
                c.parent_id
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd
                ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
            WHERE c.category_id = '" . (int)$category_id . "'
            AND c.status = '1'
        ");

        if ($query->num_rows) {
            return $query->row;
        }

        return array();
    }

    /**
     * Get category tree (all subcategories)
     *
     * @param int $parent_id Parent category ID
     * @param int $language_id Language ID
     * @return array Category tree
     */
    protected function getCategoryTree($parent_id, $language_id) {
        $this->load->model('tool/image');

        $sql = "
            SELECT DISTINCT
                c.category_id,
                cd.name,
                c.image,
                c.parent_id
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd
                ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
            LEFT JOIN `" . DB_PREFIX . "category_to_store` c2s
                ON (c.category_id = c2s.category_id)
            WHERE c.parent_id = '" . (int)$parent_id . "'
            AND c.status = '1'
            AND (c2s.store_id IS NULL OR c2s.store_id = '0' OR c2s.store_id = '" . (int)$this->config->get('config_store_id') . "')
            ORDER BY c.sort_order ASC, cd.name ASC
        ";

        error_log("MEGAMENU DEBUG - getCategoryTree SQL for parent_id={$parent_id}, lang={$language_id}: " . $sql);

        $query = $this->db->query($sql);

        error_log("MEGAMENU DEBUG - getCategoryTree found " . count($query->rows) . " categories for parent_id={$parent_id}");

        $categories = array();

        foreach ($query->rows as $category) {
            // Resize category image properly
            if ($category['image']) {
                $image = $this->model_tool_image->resize($category['image'], 250, 250);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 250, 250);
            }

            $categories[] = array(
                'category_id' => $category['category_id'],
                'name'        => $category['name'],
                'image'       => $image,
                'href'        => $this->url->link('product/category', 'path=' . $category['category_id'])
            );
        }

        return $categories;
    }

    /**
     * Get products from a category (for AJAX loading)
     *
     * @param int $category_id Category ID
     * @param int $limit Product limit
     * @param int $language_id Language ID
     * @return array Products
     */
    public function getCategoryProducts($category_id, $limit = 8, $language_id = null) {
        if ($language_id === null) {
            $language_id = $this->getLanguageId();
        }

        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $sql = "
            SELECT p.product_id, p.status, p.date_available
            FROM `" . DB_PREFIX . "product` p
            INNER JOIN `" . DB_PREFIX . "product_to_category` p2c
                ON (p.product_id = p2c.product_id)
            WHERE p2c.category_id = '" . (int)$category_id . "'
            AND p.status = '1'
            LIMIT " . (int)$limit;

        $query = $this->db->query($sql);

        $products = array();

        foreach ($query->rows as $result) {
            $product_info = $this->model_catalog_product->getProduct($result['product_id']);

            if ($product_info) {
                if ($product_info['image']) {
                    $image = $this->model_tool_image->resize($product_info['image'], 200, 200);
                } else {
                    $image = $this->model_tool_image->resize('placeholder.png', 200, 200);
                }

                if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                    $price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $price = false;
                }

                if ((float)$product_info['special']) {
                    $special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
                } else {
                    $special = false;
                }

                $products[] = array(
                    'product_id'  => $product_info['product_id'],
                    'name'        => $product_info['name'],
                    'description' => utf8_substr(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8')), 0, 100) . '...',
                    'image'       => $image,
                    'price'       => $price,
                    'special'     => $special,
                    'href'        => $this->url->link('product/product', 'product_id=' . $product_info['product_id'])
                );
            }
        }

        return $products;
    }

    /**
     * Get all categories with subcategories (for complete category tree)
     *
     * @param int $parent_id Parent category ID
     * @return array Category tree
     */
    public function getAllCategories($parent_id = 0) {
        $language_id = $this->getLanguageId();

        $query = $this->db->query("
            SELECT DISTINCT
                c.category_id,
                cd.name,
                c.image,
                c.parent_id,
                c.sort_order
            FROM `" . DB_PREFIX . "category` c
            LEFT JOIN `" . DB_PREFIX . "category_description` cd
                ON (c.category_id = cd.category_id AND cd.language_id = '" . (int)$language_id . "')
            LEFT JOIN `" . DB_PREFIX . "category_to_store` c2s
                ON (c.category_id = c2s.category_id)
            WHERE c.parent_id = '" . (int)$parent_id . "'
            AND c.status = '1'
            AND (c2s.store_id IS NULL OR c2s.store_id = '0' OR c2s.store_id = '" . (int)$this->config->get('config_store_id') . "')
            ORDER BY c.sort_order ASC, cd.name ASC
        ");

        $categories = array();

        foreach ($query->rows as $category) {
            $categories[] = array(
                'category_id' => $category['category_id'],
                'name'        => $category['name'],
                'image'       => $category['image'],
                'href'        => $this->url->link('product/category', 'path=' . $category['category_id']),
                'children'    => $this->getAllCategories($category['category_id'])
            );
        }

        return $categories;
    }
}
