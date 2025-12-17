<?php
/**
 * EKO-KRAY Custom Megamenu Module - Backend Controller
 *
 * @author  EKO-KRAY Development Team
 * @version 1.0.0
 * @license MIT
 */
class ControllerExtensionModuleEkokrayMegamenu extends Controller {
    private $error = array();

    /**
     * Main module settings page
     */
    public function index() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('extension/module/ekokray_megamenu');

        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if (!isset($this->request->get['module_id'])) {
                $this->model_setting_module->addModule('ekokray_megamenu', $this->request->post);
            } else {
                $this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        // Set up data for the view
        $data = $this->loadLanguageData();
        $data['breadcrumbs'] = $this->buildBreadcrumbs();

        // Get all menus for dropdown
        $data['menus'] = $this->model_extension_module_ekokray_megamenu->getMenus();

        // Module settings
        if (isset($this->request->get['module_id'])) {
            $module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
        }

        $data['menu_id'] = isset($this->request->post['menu_id']) ? $this->request->post['menu_id'] : (isset($module_info['menu_id']) ? $module_info['menu_id'] : '');
        $data['status'] = isset($this->request->post['status']) ? $this->request->post['status'] : (isset($module_info['status']) ? $module_info['status'] : '');
        $data['name'] = isset($this->request->post['name']) ? $this->request->post['name'] : (isset($module_info['name']) ? $module_info['name'] : '');

        // URLs
        $url = isset($this->request->get['module_id']) ? '&module_id=' . $this->request->get['module_id'] : '';
        $data['action'] = $this->url->link('extension/module/ekokray_megamenu', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['menu_list_url'] = $this->url->link('extension/module/ekokray_megamenu/menuList', 'user_token=' . $this->session->data['user_token'], true);

        // Load view
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/ekokray_megamenu', $data));
    }

    /**
     * Install module
     */
    public function install() {
        $this->load->model('extension/module/ekokray_megamenu');
        $this->model_extension_module_ekokray_megamenu->install();

        // Set permissions
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/ekokray_megamenu');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/ekokray_megamenu');
    }

    /**
     * Uninstall module
     */
    public function uninstall() {
        $this->load->model('extension/module/ekokray_megamenu');
        $this->model_extension_module_ekokray_megamenu->uninstall();
    }

    /**
     * Menu list page
     */
    public function menuList() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/ekokray_megamenu');

        $data = $this->loadLanguageData();
        $data['breadcrumbs'] = $this->buildBreadcrumbs(true);

        // Get menus
        $menus = $this->model_extension_module_ekokray_megamenu->getMenus();
        $data['menus'] = array();

        foreach ($menus as $menu) {
            $data['menus'][] = array(
                'menu_id'       => $menu['menu_id'],
                'name'          => $menu['name'],
                'status'        => $menu['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'date_modified' => date($this->language->get('date_format_short'), strtotime($menu['date_modified'])),
                'edit'          => $this->url->link('extension/module/ekokray_megamenu/editMenu', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $menu['menu_id'], true),
                'delete'        => $this->url->link('extension/module/ekokray_megamenu/deleteMenu', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $menu['menu_id'], true)
            );
        }

        // URLs
        $data['add_menu'] = $this->url->link('extension/module/ekokray_megamenu/addMenu', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Load view
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/ekokray_megamenu_list', $data));
    }

    /**
     * Add new menu
     */
    public function addMenu() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/ekokray_megamenu');

        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $menu_id = $this->model_extension_module_ekokray_megamenu->addMenu($this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/ekokray_megamenu/editMenu', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $menu_id, true));
        }

        $this->getMenuForm();
    }

    /**
     * Edit menu
     */
    public function editMenu() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('extension/module/ekokray_megamenu');

        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_module_ekokray_megamenu->editMenu($this->request->get['menu_id'], $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/ekokray_megamenu/editMenu', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true));
        }

        $this->getMenuForm();
    }

    /**
     * Delete menu
     */
    public function deleteMenu() {
        $this->load->language('extension/module/ekokray_megamenu');

        $this->load->model('extension/module/ekokray_megamenu');

        if (isset($this->request->get['menu_id']) && $this->validateDelete()) {
            $this->model_extension_module_ekokray_megamenu->deleteMenu($this->request->get['menu_id']);

            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link('extension/module/ekokray_megamenu/menuList', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Get menu form (add/edit)
     */
    protected function getMenuForm() {
        $this->load->model('localisation/language');

        // Add scripts and styles BEFORE header is loaded
        $this->document->addScript('view/javascript/jquery/jquery-ui/jquery-ui.js');
        $this->document->addScript('view/javascript/ekokray/megamenu-admin.js?v=' . time());
        $this->document->addStyle('view/javascript/jquery/jquery-ui/jquery-ui.css');
        $this->document->addStyle('view/stylesheet/ekokray/megamenu-admin.css');

        $data = $this->loadLanguageData();
        $data['breadcrumbs'] = $this->buildBreadcrumbs(true);

        // Get menu data if editing
        $menu_info = array();
        if (isset($this->request->get['menu_id'])) {
            $menu_info = $this->model_extension_module_ekokray_megamenu->getMenu($this->request->get['menu_id']);
        }

        // Form fields
        $data['name'] = isset($this->request->post['name']) ? $this->request->post['name'] : (isset($menu_info['name']) ? $menu_info['name'] : '');
        $data['status'] = isset($this->request->post['status']) ? $this->request->post['status'] : (isset($menu_info['status']) ? $menu_info['status'] : 1);
        $data['mobile_breakpoint'] = isset($this->request->post['mobile_breakpoint']) ? $this->request->post['mobile_breakpoint'] : (isset($menu_info['mobile_breakpoint']) ? $menu_info['mobile_breakpoint'] : 992);
        $data['cache_enabled'] = isset($this->request->post['cache_enabled']) ? $this->request->post['cache_enabled'] : (isset($menu_info['cache_enabled']) ? $menu_info['cache_enabled'] : 1);
        $data['cache_duration'] = isset($this->request->post['cache_duration']) ? $this->request->post['cache_duration'] : (isset($menu_info['cache_duration']) ? $menu_info['cache_duration'] : 3600);

        // Get menu items if editing
        $data['menu_items'] = array();
        if (isset($this->request->get['menu_id'])) {
            $data['menu_items'] = $this->getMenuItemsTree($this->request->get['menu_id']);
            $data['menu_id'] = $this->request->get['menu_id'];
        }

        // Languages
        $data['languages'] = $this->model_localisation_language->getLanguages();

        // URLs
        if (!isset($this->request->get['menu_id'])) {
            $data['action'] = $this->url->link('extension/module/ekokray_megamenu/addMenu', 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['action'] = $this->url->link('extension/module/ekokray_megamenu/editMenu', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true);
            $data['add_item_url'] = $this->url->link('extension/module/ekokray_megamenu/addItem', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true);
            $data['edit_item_url'] = $this->url->link('extension/module/ekokray_megamenu/editItem', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true);
            $data['delete_item_url'] = $this->url->link('extension/module/ekokray_megamenu/deleteItem', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true);
            $data['update_order_url'] = $this->url->link('extension/module/ekokray_megamenu/updateOrder', 'user_token=' . $this->session->data['user_token'] . '&menu_id=' . $this->request->get['menu_id'], true);
        }

        $data['cancel'] = $this->url->link('extension/module/ekokray_megamenu/menuList', 'user_token=' . $this->session->data['user_token'], true);
        $data['user_token'] = $this->session->data['user_token'];

        // Load view
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/ekokray_megamenu_form', $data));
    }

    /**
     * Get menu items tree
     */
    protected function getMenuItemsTree($menu_id, $parent_id = 0) {
        $items = $this->model_extension_module_ekokray_megamenu->getMenuItems($menu_id, $parent_id);

        $tree = array();
        foreach ($items as $item) {
            $item['children'] = $this->getMenuItemsTree($menu_id, $item['item_id']);
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * Add menu item (AJAX)
     */
    public function addItem() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->load->model('extension/module/ekokray_megamenu');

        $json = array();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateItemForm()) {
            $item_id = $this->model_extension_module_ekokray_megamenu->addMenuItem($this->request->post);

            $json['success'] = true;
            $json['item_id'] = $item_id;
            $json['message'] = $this->language->get('text_success');
        } else {
            $json['success'] = false;
            $json['errors'] = $this->error;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Edit menu item (AJAX) - handles both GET (load data) and POST (save data)
     */
    public function editItem() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->load->model('extension/module/ekokray_megamenu');

        $json = array();

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // POST request - save item
            if ($this->validateItemForm()) {
                $this->model_extension_module_ekokray_megamenu->editMenuItem($this->request->post['item_id'], $this->request->post);

                $json['success'] = true;
                $json['message'] = $this->language->get('text_success');
            } else {
                $json['success'] = false;
                $json['errors'] = $this->error;
            }
        } else {
            // GET request - load item data for editing
            if (isset($this->request->get['item_id'])) {
                $item_id = $this->request->get['item_id'];
                $item = $this->model_extension_module_ekokray_megamenu->getMenuItem($item_id);

                if ($item) {
                    $json['success'] = true;
                    $json['item'] = $item;
                } else {
                    $json['success'] = false;
                    $json['error'] = 'Item not found';
                }
            } else {
                $json['success'] = false;
                $json['error'] = 'Missing item_id parameter';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete menu item (AJAX)
     */
    public function deleteItem() {
        $this->load->language('extension/module/ekokray_megamenu');
        $this->load->model('extension/module/ekokray_megamenu');

        $json = array();

        if (isset($this->request->post['item_id']) && $this->validateDelete()) {
            $this->model_extension_module_ekokray_megamenu->deleteMenuItem($this->request->post['item_id']);

            $json['success'] = true;
            $json['message'] = $this->language->get('text_success');
        } else {
            $json['success'] = false;
            $json['error'] = $this->language->get('error_permission');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Update sort order (AJAX)
     */
    public function updateOrder() {
        $this->load->model('extension/module/ekokray_megamenu');

        $json = array();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['items'])) {
            $this->model_extension_module_ekokray_megamenu->updateSortOrder($this->request->post['items']);

            $json['success'] = true;
        } else {
            $json['success'] = false;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Category autocomplete (AJAX)
     */
    public function autocompleteCategory() {
        $json = array();

        if (isset($this->request->get['filter_name'])) {
            $this->load->model('catalog/category');

            $filter_data = array(
                'filter_name' => $this->request->get['filter_name'],
                'sort'        => 'name',
                'order'       => 'ASC',
                'start'       => 0,
                'limit'       => 5
            );

            $results = $this->model_catalog_category->getCategories($filter_data);

            foreach ($results as $result) {
                $json[] = array(
                    'category_id' => $result['category_id'],
                    'name'        => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8'))
                );
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Load language data
     */
    protected function loadLanguageData() {
        $data = array();

        $keys = array(
            'heading_title', 'text_edit', 'text_enabled', 'text_disabled',
            'text_success', 'entry_name', 'entry_status', 'entry_menu',
            'entry_mobile_breakpoint', 'entry_cache_enabled', 'entry_cache_duration',
            'button_save', 'button_cancel', 'button_add_item', 'button_add_menu',
            'text_menu_editor', 'text_no_items', 'text_add', 'date_format_short'
        );

        foreach ($keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        return $data;
    }

    /**
     * Build breadcrumbs
     */
    protected function buildBreadcrumbs($include_menu_list = false) {
        $breadcrumbs = array();

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        if ($include_menu_list) {
            $breadcrumbs[] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/ekokray_megamenu/menuList', 'user_token=' . $this->session->data['user_token'], true)
            );
        } else {
            $breadcrumbs[] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/ekokray_megamenu', 'user_token=' . $this->session->data['user_token'], true)
            );
        }

        return $breadcrumbs;
    }

    /**
     * Validate module settings
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/ekokray_megamenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Validate menu form
     */
    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/ekokray_megamenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    /**
     * Validate menu item form
     */
    protected function validateItemForm() {
        if (!$this->user->hasPermission('modify', 'extension/module/ekokray_megamenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Validate delete
     */
    protected function validateDelete() {
        if (!$this->user->hasPermission('modify', 'extension/module/ekokray_megamenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}
