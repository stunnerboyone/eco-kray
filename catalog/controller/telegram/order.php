<?php
/**
 * Telegram Order Notifications Controller
 *
 * Handles sending Telegram notifications when orders are created or updated
 *
 * @package    OpenCart
 * @author     EkoKray
 */
class ControllerTelegramOrder extends Controller {
    /**
     * Handle order history add event
     * Triggered by: catalog/model/checkout/order/addOrderHistory/after
     *
     * @param string $route Event route
     * @param array $args Event arguments [order_id, order_status_id, comment, notify]
     */
    public function index(&$route, &$args) {
        // Check if module is enabled
        if (!$this->config->get('module_telegram_status')) {
            return;
        }

        $order_id = isset($args[0]) ? (int)$args[0] : 0;
        $order_status_id = isset($args[1]) ? (int)$args[1] : 0;

        if (!$order_id || !$order_status_id) {
            return;
        }

        // Get order info
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            return;
        }

        // Only send notification for new orders (status changed from 0 to something)
        if ($order_info['order_status_id'] != 0) {
            return;
        }

        $this->sendNewOrderNotification($order_info);
    }

    /**
     * Send new order notification to Telegram
     *
     * @param array $order_info Order information
     */
    private function sendNewOrderNotification($order_info) {
        $bot_token = $this->config->get('module_telegram_bot_token');
        $chat_ids = $this->config->get('module_telegram_chat_id');

        if (!$bot_token || !$chat_ids) {
            return;
        }

        // Initialize Telegram library
        $log = new Log('telegram.log');
        $telegram = new Telegram($bot_token, $chat_ids, $log);

        // Get order products
        $products = array();
        $order_products = $this->model_checkout_order->getOrderProducts($order_info['order_id']);

        $this->load->model('tool/upload');

        foreach ($order_products as $order_product) {
            $option_data = array();
            $order_options = $this->model_checkout_order->getOrderOptions($order_info['order_id'], $order_product['order_product_id']);

            foreach ($order_options as $order_option) {
                if ($order_option['type'] != 'file') {
                    $value = $order_option['value'];
                } else {
                    $upload_info = $this->model_tool_upload->getUploadByCode($order_option['value']);
                    $value = $upload_info ? $upload_info['name'] : '';
                }

                $option_data[] = array(
                    'name'  => $order_option['name'],
                    'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
                );
            }

            $products[] = array(
                'name'     => $order_product['name'],
                'model'    => $order_product['model'],
                'quantity' => $order_product['quantity'],
                'option'   => $option_data,
                'total'    => $this->currency->format(
                    $order_product['total'] + ($this->config->get('config_tax') ? ($order_product['tax'] * $order_product['quantity']) : 0),
                    $order_info['currency_code'],
                    $order_info['currency_value']
                )
            );
        }

        // Get order totals
        $totals = array();
        $order_totals = $this->model_checkout_order->getOrderTotals($order_info['order_id']);

        foreach ($order_totals as $order_total) {
            $totals[] = array(
                'title' => $order_total['title'],
                'text'  => $this->currency->format($order_total['value'], $order_info['currency_code'], $order_info['currency_value'])
            );
        }

        // Format and send message
        $message = $telegram->formatOrderMessage($order_info, $products, $totals, $order_info['store_url']);
        $result = $telegram->sendMessage($message);

        // Log results
        foreach ($result as $chat_id => $response) {
            if (isset($response['ok']) && $response['ok']) {
                $log->write('Telegram notification sent to ' . $chat_id . ' for order #' . $order_info['order_id']);
            } else {
                $error = isset($response['description']) ? $response['description'] : 'Unknown error';
                $log->write('Failed to send Telegram notification to ' . $chat_id . ' for order #' . $order_info['order_id'] . ': ' . $error);
            }
        }
    }
}
