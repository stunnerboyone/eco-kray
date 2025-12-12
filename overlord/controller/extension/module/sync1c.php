<?php
class ControllerExtensionModuleSync1c extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/sync1c');
        $this->document->setTitle('Sync 1C');

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('sync1c', $this->request->post);
            $this->session->data['success'] = 'Налаштування збережено!';
            $this->response->redirect($this->url->link('extension/module/sync1c', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Головна',
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Sync 1C',
            'href' => $this->url->link('extension/module/sync1c', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Errors
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Form action
        $data['action'] = $this->url->link('extension/module/sync1c', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Settings
        $data['sync1c_status'] = $this->config->get('sync1c_status') ? $this->config->get('sync1c_status') : 0;
        $data['sync1c_username'] = $this->config->get('sync1c_username') ? $this->config->get('sync1c_username') : 'admin';
        $data['sync1c_password'] = $this->config->get('sync1c_password') ? $this->config->get('sync1c_password') : '';

        // Endpoint URL
        $data['sync_url'] = HTTPS_CATALOG . 'sync1c.php';

        // Stats
        $this->load->model('extension/module/sync1c');
        $data['stats'] = $this->model_extension_module_sync1c->getStats();

        // Log
        $log_file = DIR_STORAGE . 'logs/sync1c.log';
        if (file_exists($log_file)) {
            $data['log'] = file_get_contents($log_file);
            $data['log'] = substr($data['log'], -10000); // Last 10KB
        } else {
            $data['log'] = 'Лог порожній';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/sync1c', $data));
    }

    public function clearLog() {
        $log_file = DIR_STORAGE . 'logs/sync1c.log';
        if (file_exists($log_file)) {
            unlink($log_file);
        }
        $this->response->redirect($this->url->link('extension/module/sync1c', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/sync1c')) {
            $this->error['warning'] = 'У вас немає прав для зміни налаштувань!';
        }
        return !$this->error;
    }

    public function install() {
        $this->load->model('extension/module/sync1c');
        $this->model_extension_module_sync1c->install();
    }

    public function uninstall() {
        // Keep tables for data preservation
    }
}
