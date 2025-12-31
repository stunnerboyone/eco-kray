<?php
/**
 * EKO-KRAY Custom Megamenu Module - Frontend Controller
 *
 * @author  EKO-KRAY Development Team
 * @version 1.0.0
 * @license MIT
 */
class ControllerExtensionModuleEkokrayMegamenu extends Controller {

    /**
     * Main index method - renders the megamenu
     *
     * @param array $setting Module settings
     * @return string Rendered HTML
     */
    public function index($setting) {
        $this->load->model('extension/module/ekokray_megamenu');
        $this->load->language('extension/module/ekokray_megamenu');

        // Get menu ID from settings
        if (!isset($setting['menu_id']) || empty($setting['menu_id'])) {
            return '';
        }

        $menu_id = $setting['menu_id'];
        $language_id = (int)$this->config->get('config_language_id');

        // Get menu structure
        $menu_data = $this->model_extension_module_ekokray_megamenu->getMenuStructure($menu_id, $language_id);

        if (empty($menu_data)) {
            return '';
        }

        // Prepare data for template
        $data['menu_id'] = $menu_data['menu_id'];
        $data['menu_name'] = $menu_data['name'];
        $data['mobile_breakpoint'] = $menu_data['mobile_breakpoint'];
        $data['items'] = $this->buildMenuItems($menu_data['items']);

        // Add search, account, and cart data for mobile menu
        $data['search_url'] = $this->url->link('product/search');
        $data['account_url'] = $this->url->link('account/account');
        $data['login_url'] = $this->url->link('account/login');
        $data['register_url'] = $this->url->link('account/register');
        $data['logout_url'] = $this->url->link('account/logout');
        $data['cart_url'] = $this->url->link('checkout/cart');
        $data['checkout_url'] = $this->url->link('checkout/checkout');

        // Check if customer is logged in
        $data['logged'] = $this->customer->isLogged();
        $data['customer_name'] = $this->customer->isLogged() ? $this->customer->getFirstName() : '';

        // Get cart info
        $this->load->model('tool/image');
        $data['cart_total'] = $this->cart->countProducts();

        // Language strings
        $data['text_search'] = $this->language->get('text_search');
        $data['text_account'] = $this->language->get('text_account');
        $data['text_login'] = $this->language->get('text_login');
        $data['text_register'] = $this->language->get('text_register');
        $data['text_logout'] = $this->language->get('text_logout');
        $data['text_cart'] = $this->language->get('text_cart');
        $data['text_checkout'] = $this->language->get('text_checkout');

        // Add styles and scripts with cache busting
        $this->document->addStyle('catalog/view/theme/EkoKray/stylesheet/ekokray/megamenu.css?v=' . time());
        $this->document->addScript('catalog/view/javascript/ekokray/megamenu.js?v=' . time());

        return $this->load->view('extension/module/ekokray_megamenu', $data);
    }

    /**
     * Build menu items with URLs
     *
     * @param array $items Menu items
     * @return array Processed items
     */
    protected function buildMenuItems($items) {
        $result = array();

        foreach ($items as $item) {
            $item_data = array(
                'item_id'       => $item['item_id'],
                'title'         => $item['title'],
                'item_type'     => $item['item_type'],
                'show_products' => $item['show_products'],
                'product_limit' => $item['product_limit'],
                'children'      => array(),
                'href'          => '#'
            );

            // Set link based on item type
            if ($item['item_type'] == 'category' && isset($item['category_id'])) {
                $item_data['href'] = $this->url->link('product/category', 'path=' . $item['category_id']);
                $item_data['category_id'] = $item['category_id'];

                // Add category info if available
                if (isset($item['category'])) {
                    $item_data['category_name'] = $item['category']['name'];
                }
            } elseif ($item['item_type'] == 'custom_link' && !empty($item['link'])) {
                $item_data['href'] = $item['link'];
            } elseif ($item['item_type'] == 'category_tree' && isset($item['category_id'])) {
                $item_data['href'] = $this->url->link('product/category', 'path=' . $item['category_id']);
                $item_data['category_id'] = $item['category_id'];

                // Add subcategories if available
                if (isset($item['subcategories'])) {
                    $item_data['subcategories'] = $item['subcategories'];
                }
            }

            // Set target
            $item_data['target'] = isset($item['target']) ? $item['target'] : '_self';

            // Process children recursively
            if (!empty($item['children'])) {
                $item_data['children'] = $this->buildMenuItems($item['children']);
            }

            $result[] = $item_data;
        }

        return $result;
    }

    /**
     * AJAX endpoint to get category products
     */
    public function getProducts() {
        // Start output buffering to catch any PHP notices/warnings
        ob_start();

        $this->load->model('extension/module/ekokray_megamenu');

        $json = array();
        $json['debug'] = array();

        if (isset($this->request->get['category_id'])) {
            $category_id = (int)$this->request->get['category_id'];
            $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 8;

            $json['debug']['category_id'] = $category_id;
            $json['debug']['limit'] = $limit;
            $json['debug']['store_id'] = (int)$this->config->get('config_store_id');
            $json['debug']['language_id'] = (int)$this->config->get('config_language_id');

            // Check category exists
            $category_query = $this->db->query("SELECT name FROM " . DB_PREFIX . "category_description WHERE category_id = '" . (int)$category_id . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "'");
            $json['debug']['category_name'] = $category_query->num_rows ? $category_query->row['name'] : 'Category not found';

            $products = $this->model_extension_module_ekokray_megamenu->getCategoryProducts($category_id, $limit);

            $json['success'] = true;
            $json['products'] = $products;
            $json['debug']['products_count'] = count($products);
        } else {
            $json['success'] = false;
            $json['error'] = 'Missing category ID';
        }

        // Clean output buffer before sending JSON
        ob_end_clean();

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
