<?php
/**
 * Telegram Notifications Module - Admin Controller
 *
 * @package    OpenCart
 * @author     EkoKray
 */
class ControllerExtensionModuleTelegram extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/telegram');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_telegram', $this->request->post);

            // Ensure event is registered
            $this->ensureEventRegistered();

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true));
        }

        // Errors
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['bot_token'])) {
            $data['error_bot_token'] = $this->error['bot_token'];
        } else {
            $data['error_bot_token'] = '';
        }

        if (isset($this->error['chat_id'])) {
            $data['error_chat_id'] = $this->error['chat_id'];
        } else {
            $data['error_chat_id'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Test result
        if (isset($this->session->data['test_result'])) {
            $data['test_result'] = $this->session->data['test_result'];
            unset($this->session->data['test_result']);
        } else {
            $data['test_result'] = '';
        }

        // Form action URLs
        $data['action'] = $this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['test_url'] = $this->url->link('extension/module/telegram/test', 'user_token=' . $this->session->data['user_token'], true);

        // Settings
        if (isset($this->request->post['module_telegram_status'])) {
            $data['module_telegram_status'] = $this->request->post['module_telegram_status'];
        } else {
            $data['module_telegram_status'] = $this->config->get('module_telegram_status');
        }

        if (isset($this->request->post['module_telegram_bot_token'])) {
            $data['module_telegram_bot_token'] = $this->request->post['module_telegram_bot_token'];
        } else {
            $data['module_telegram_bot_token'] = $this->config->get('module_telegram_bot_token');
        }

        if (isset($this->request->post['module_telegram_chat_id'])) {
            $data['module_telegram_chat_id'] = $this->request->post['module_telegram_chat_id'];
        } else {
            $data['module_telegram_chat_id'] = $this->config->get('module_telegram_chat_id');
        }

        // Log
        $log_file = DIR_STORAGE . 'logs/telegram.log';
        if (file_exists($log_file)) {
            $data['log'] = file_get_contents($log_file);
            $data['log'] = substr($data['log'], -10000); // Last 10KB
        } else {
            $data['log'] = $this->language->get('text_log_empty');
        }

        $data['clear_log_url'] = $this->url->link('extension/module/telegram/clearLog', 'user_token=' . $this->session->data['user_token'], true);

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        $data['text_log_empty'] = $this->language->get('text_log_empty');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_bot_token'] = $this->language->get('entry_bot_token');
        $data['entry_chat_id'] = $this->language->get('entry_chat_id');
        $data['entry_log'] = $this->language->get('entry_log');

        $data['help_bot_token'] = $this->language->get('help_bot_token');
        $data['help_chat_id'] = $this->language->get('help_chat_id');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_test'] = $this->language->get('button_test');
        $data['button_clear_log'] = $this->language->get('button_clear_log');

        $data['tab_general'] = $this->language->get('tab_general');
        $data['tab_log'] = $this->language->get('tab_log');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/telegram', $data));
    }

    /**
     * Test Telegram connection
     */
    public function test() {
        $this->load->language('extension/module/telegram');

        $bot_token = $this->config->get('module_telegram_bot_token');
        $chat_ids = $this->config->get('module_telegram_chat_id');

        if (!$bot_token || !$chat_ids) {
            $this->session->data['test_result'] = $this->language->get('error_test_config');
            $this->response->redirect($this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $telegram = new Telegram($bot_token, $chat_ids);

        // Test connection
        if (!$telegram->testConnection()) {
            $this->session->data['test_result'] = $this->language->get('error_test_connection');
            $this->response->redirect($this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        // Send test message
        $message = "<b>🔔 Тестове повідомлення</b>\n\n";
        $message .= "Підключення до Telegram налаштовано успішно!\n";
        $message .= "Магазин: " . htmlspecialchars($this->config->get('config_name'), ENT_QUOTES, 'UTF-8') . "\n";
        $message .= "Час: " . date('d.m.Y H:i:s');

        $results = $telegram->sendMessage($message);

        $success = 0;
        $failed = 0;

        foreach ($results as $chat_id => $result) {
            if (isset($result['ok']) && $result['ok']) {
                $success++;
            } else {
                $failed++;
            }
        }

        if ($success > 0 && $failed == 0) {
            $this->session->data['test_result'] = sprintf($this->language->get('text_test_success'), $success);
        } elseif ($success > 0 && $failed > 0) {
            $this->session->data['test_result'] = sprintf($this->language->get('text_test_partial'), $success, $failed);
        } else {
            $this->session->data['test_result'] = $this->language->get('error_test_send');
        }

        $this->response->redirect($this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Clear log file
     */
    public function clearLog() {
        $log_file = DIR_STORAGE . 'logs/telegram.log';

        if (file_exists($log_file)) {
            unlink($log_file);
        }

        $this->session->data['success'] = $this->language->get('text_log_cleared');
        $this->response->redirect($this->url->link('extension/module/telegram', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Ensure event is registered in database
     */
    private function ensureEventRegistered() {
        $this->load->model('setting/event');

        $event = $this->model_setting_event->getEventByCode('telegram_order_notification');

        if (!$event) {
            $this->model_setting_event->addEvent(
                'telegram_order_notification',
                'catalog/model/checkout/order/addOrderHistory/after',
                'telegram/order'
            );
        }
    }

    /**
     * Module install
     */
    public function install() {
        $this->ensureEventRegistered();
    }

    /**
     * Module uninstall
     */
    public function uninstall() {
        $this->load->model('setting/event');

        // Remove event
        $this->model_setting_event->deleteEventByCode('telegram_order_notification');
    }

    /**
     * Validate form data
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/telegram')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (isset($this->request->post['module_telegram_status']) && $this->request->post['module_telegram_status']) {
            if (empty($this->request->post['module_telegram_bot_token'])) {
                $this->error['bot_token'] = $this->language->get('error_bot_token');
            }

            if (empty($this->request->post['module_telegram_chat_id'])) {
                $this->error['chat_id'] = $this->language->get('error_chat_id');
            }
        }

        return !$this->error;
    }
}
