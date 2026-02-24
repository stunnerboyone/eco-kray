<?php
/**
 * Checkbox ПРРО — Admin Controller
 *
 * Provides the settings page in the admin panel and hooks into
 * the order detail page to display fiscal receipt information.
 */
class ControllerExtensionModuleCheckbox extends Controller {

    private $error = [];

    // -------------------------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------------------------

    public function install() {
        $this->load->model('extension/module/checkbox');
        $this->model_extension_module_checkbox->install();
    }

    public function uninstall() {
        $this->load->model('extension/module/checkbox');
        $this->model_extension_module_checkbox->uninstall();
    }

    // -------------------------------------------------------------------------
    // Settings page
    // -------------------------------------------------------------------------

    public function index() {
        $this->load->language('extension/module/checkbox');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('module_checkbox', $this->request->post);

            // Re-register events every save to ensure they are present
            $this->ensureEventsRegistered();

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'extension/module/checkbox',
                'user_token=' . $this->session->data['user_token'],
                true
            ));
        }

        // Error messages
        foreach (['warning', 'login', 'password'] as $field) {
            $data['error_' . $field] = isset($this->error[$field]) ? $this->error[$field] : '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/checkbox', 'user_token=' . $this->session->data['user_token'], true),
            ],
        ];

        $data['action']    = $this->url->link('extension/module/checkbox', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel']    = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['test_url']  = $this->url->link('extension/module/checkbox/test', 'user_token=' . $this->session->data['user_token'], true);

        // Flash messages
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        if (isset($this->session->data['test_result'])) {
            $data['test_result'] = $this->session->data['test_result'];
            unset($this->session->data['test_result']);
        } else {
            $data['test_result'] = '';
        }

        // Settings — prefer POST values (re-display on error), fall back to saved config
        $setting_keys = [
            'module_checkbox_status',
            'module_checkbox_login',
            'module_checkbox_password',
            'module_checkbox_cash_register_id',
            'module_checkbox_payment_type',
            'module_checkbox_trigger_statuses',
        ];

        foreach ($setting_keys as $key) {
            $data[$key] = isset($this->request->post[$key])
                ? $this->request->post[$key]
                : $this->config->get($key);
        }

        // Ensure trigger_statuses is always an array for the view
        if (!is_array($data['module_checkbox_trigger_statuses'])) {
            $data['module_checkbox_trigger_statuses'] = $data['module_checkbox_trigger_statuses']
                ? array_map('intval', explode(',', $data['module_checkbox_trigger_statuses']))
                : [];
        }

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Log file content
        $log_file = DIR_STORAGE . 'logs/checkbox.log';
        $data['log'] = file_exists($log_file)
            ? substr(file_get_contents($log_file), -15000)
            : $this->language->get('text_log_empty');

        $data['clear_log_url'] = $this->url->link(
            'extension/module/checkbox/clearLog',
            'user_token=' . $this->session->data['user_token'],
            true
        );

        // Language strings for the view
        $lang_keys = [
            'heading_title', 'text_edit', 'text_enabled', 'text_disabled',
            'text_all_statuses', 'text_log_empty', 'text_cashless', 'text_cash',
            'entry_status', 'entry_login', 'entry_password', 'entry_cash_register_id',
            'entry_payment_type', 'entry_trigger_statuses',
            'help_login', 'help_password', 'help_cash_register_id',
            'help_trigger_statuses', 'help_payment_type',
            'button_save', 'button_cancel', 'button_test', 'button_clear_log',
            'tab_general', 'tab_log',
        ];

        foreach ($lang_keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/checkbox', $data));
    }

    // -------------------------------------------------------------------------
    // Test connection action
    // -------------------------------------------------------------------------

    public function test() {
        $this->load->language('extension/module/checkbox');

        $login    = $this->config->get('module_checkbox_login');
        $password = $this->config->get('module_checkbox_password');

        if (!$login || !$password) {
            $this->session->data['test_result'] = $this->language->get('error_credentials');
            $this->response->redirect($this->url->link(
                'extension/module/checkbox', 'user_token=' . $this->session->data['user_token'], true
            ));
            return;
        }

        try {
            require_once(DIR_SYSTEM . 'library/checkbox_api.php');

            $api = new CheckboxApi(
                $login,
                $password,
                $this->config->get('module_checkbox_cash_register_id') ?: null
            );

            $api->signIn();

            $this->session->data['test_result'] = $this->language->get('text_test_success');

        } catch (Exception $e) {
            $this->session->data['test_result'] =
                $this->language->get('error_test_connection') . ': ' . $e->getMessage();
        }

        $this->response->redirect($this->url->link(
            'extension/module/checkbox', 'user_token=' . $this->session->data['user_token'], true
        ));
    }

    // -------------------------------------------------------------------------
    // Clear log action
    // -------------------------------------------------------------------------

    public function clearLog() {
        $log_file = DIR_STORAGE . 'logs/checkbox.log';

        if (file_exists($log_file)) {
            unlink($log_file);
        }

        $this->load->language('extension/module/checkbox');
        $this->session->data['success'] = $this->language->get('text_log_cleared');

        $this->response->redirect($this->url->link(
            'extension/module/checkbox', 'user_token=' . $this->session->data['user_token'], true
        ));
    }

    // -------------------------------------------------------------------------
    // Order info tab event (admin/view/sale/order_info/before)
    // -------------------------------------------------------------------------

    /**
     * Injects a "Checkbox" tab into the admin order detail page.
     *
     * @param string $route
     * @param array  $data   The order info view data (passed by reference)
     * @param string $output
     */
    public function orderInfo(&$route, &$data, &$output) {
        if (!isset($this->request->get['order_id'])) {
            return;
        }

        $order_id = (int)$this->request->get['order_id'];

        $this->load->model('extension/module/checkbox');
        $receipt = $this->model_extension_module_checkbox->getReceiptByOrderId($order_id);

        if (!$receipt) {
            return;
        }

        $this->load->language('extension/module/checkbox');

        $params = array_merge($receipt, [
            'user_token'        => $this->session->data['user_token'],
            'order_id'          => $order_id,
            'label_receipt_id'  => $this->language->get('label_receipt_id'),
            'label_fiscal_code' => $this->language->get('label_fiscal_code'),
            'label_status'      => $this->language->get('label_status'),
            'label_total'       => $this->language->get('label_total'),
            'label_date'        => $this->language->get('label_date'),
            'label_tax_url'     => $this->language->get('label_tax_url'),
            'text_verify'       => $this->language->get('text_verify'),
            'text_no_receipt'   => $this->language->get('text_no_receipt'),
        ]);

        $data['tabs'][] = [
            'title'   => 'Checkbox',
            'code'    => 'checkbox_receipt',
            'content' => $this->load->view('extension/module/checkbox_receipt', $params),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Register event hooks if not already in the DB.
     */
    private function ensureEventsRegistered() {
        $this->load->model('setting/event');

        if (!$this->model_setting_event->getEventByCode('module_checkbox_fiscalize')) {
            $this->model_setting_event->addEvent(
                'module_checkbox_fiscalize',
                'catalog/model/checkout/order/addOrderHistory/after',
                'extension/module/checkbox/index'
            );
        }

        if (!$this->model_setting_event->getEventByCode('module_checkbox_order_info')) {
            $this->model_setting_event->addEvent(
                'module_checkbox_order_info',
                'admin/view/sale/order_info/before',
                'extension/module/checkbox/orderInfo'
            );
        }
    }

    /**
     * Validate the settings form.
     *
     * @return bool
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/checkbox')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!empty($this->request->post['module_checkbox_status'])) {
            if (empty($this->request->post['module_checkbox_login'])) {
                $this->error['login'] = $this->language->get('error_login');
            }

            if (empty($this->request->post['module_checkbox_password'])) {
                $this->error['password'] = $this->language->get('error_password');
            }
        }

        return empty($this->error);
    }
}
