<?php
/**
 * Telegram Bot API Library
 *
 * @package    OpenCart
 * @author     EkoKray
 */
class Telegram {
    private $token;
    private $chat_ids = array();
    private $api_url = 'https://api.telegram.org/bot';
    private $log;

    /**
     * Constructor
     *
     * @param string $token Bot API token
     * @param mixed $chat_ids Chat ID or array of chat IDs
     * @param Log $log Optional log instance
     */
    public function __construct($token, $chat_ids = array(), $log = null) {
        $this->token = $token;
        $this->log = $log;

        if (!is_array($chat_ids)) {
            $chat_ids = array_filter(array_map('trim', explode(',', $chat_ids)));
        }

        $this->chat_ids = $chat_ids;
    }

    /**
     * Set chat IDs
     *
     * @param mixed $chat_ids Chat ID or array of chat IDs
     */
    public function setChatIds($chat_ids) {
        if (!is_array($chat_ids)) {
            $chat_ids = array_filter(array_map('trim', explode(',', $chat_ids)));
        }

        $this->chat_ids = $chat_ids;
    }

    /**
     * Send message to all configured chat IDs
     *
     * @param string $message Message text (supports HTML/Markdown)
     * @param string $parse_mode Parse mode: HTML or Markdown
     * @param bool $disable_web_preview Disable web page preview
     * @return array Results for each chat ID
     */
    public function sendMessage($message, $parse_mode = 'HTML', $disable_web_preview = false) {
        $results = array();

        foreach ($this->chat_ids as $chat_id) {
            $results[$chat_id] = $this->sendToChat($chat_id, $message, $parse_mode, $disable_web_preview);
        }

        return $results;
    }

    /**
     * Send message to specific chat
     *
     * @param string $chat_id Telegram chat ID
     * @param string $message Message text
     * @param string $parse_mode Parse mode: HTML or Markdown
     * @param bool $disable_web_preview Disable web page preview
     * @return array API response
     */
    public function sendToChat($chat_id, $message, $parse_mode = 'HTML', $disable_web_preview = false) {
        $params = array(
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => $disable_web_preview
        );

        return $this->request('sendMessage', $params);
    }

    /**
     * Get bot info
     *
     * @return array Bot information
     */
    public function getMe() {
        return $this->request('getMe');
    }

    /**
     * Test bot connection
     *
     * @return bool True if connection successful
     */
    public function testConnection() {
        $result = $this->getMe();
        return isset($result['ok']) && $result['ok'] === true;
    }

    /**
     * Send API request
     *
     * @param string $method API method
     * @param array $params Request parameters
     * @return array API response
     */
    private function request($method, $params = array()) {
        $url = $this->api_url . $this->token . '/' . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            $this->log('Telegram API cURL error: ' . $error);
            return array(
                'ok' => false,
                'error_code' => 0,
                'description' => 'cURL error: ' . $error
            );
        }

        $result = json_decode($response, true);

        if (!$result) {
            $this->log('Telegram API invalid response: ' . $response);
            return array(
                'ok' => false,
                'error_code' => $http_code,
                'description' => 'Invalid API response'
            );
        }

        if (!$result['ok']) {
            $this->log('Telegram API error: ' . $result['description']);
        }

        return $result;
    }

    /**
     * Log message
     *
     * @param string $message Log message
     */
    private function log($message) {
        if ($this->log) {
            $this->log->write($message);
        }
    }

    /**
     * Format order notification message
     *
     * @param array $order_info Order information
     * @param array $products Order products
     * @param array $totals Order totals
     * @param string $store_url Store URL
     * @return string Formatted message
     */
    public function formatOrderMessage($order_info, $products, $totals, $store_url = '') {
        $message = "<b>🛒 Нове замовлення #" . $order_info['order_id'] . "</b>\n\n";

        // Customer info
        $message .= "<b>👤 Клієнт:</b>\n";
        $customer_name = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
        if ($customer_name) {
            $message .= "Ім'я: " . $this->escapeHtml($customer_name) . "\n";
        }
        if (!empty($order_info['email'])) {
            $message .= "Email: " . $this->escapeHtml($order_info['email']) . "\n";
        }
        if (!empty($order_info['telephone'])) {
            $message .= "Тел: " . $this->escapeHtml($order_info['telephone']) . "\n";
        }

        // Shipping address
        if (!empty($order_info['shipping_address_1']) || !empty($order_info['shipping_city'])) {
            $message .= "\n<b>📍 Адреса доставки:</b>\n";
            $address_parts = array_filter(array(
                $order_info['shipping_city'],
                $order_info['shipping_address_1'],
                $order_info['shipping_address_2']
            ));
            $message .= $this->escapeHtml(implode(', ', $address_parts)) . "\n";
        }

        // Shipping & Payment methods
        if (!empty($order_info['shipping_method'])) {
            $message .= "\n<b>🚚 Доставка:</b> " . $this->escapeHtml($order_info['shipping_method']) . "\n";
        }
        if (!empty($order_info['payment_method'])) {
            $message .= "<b>💳 Оплата:</b> " . $this->escapeHtml($order_info['payment_method']) . "\n";
        }

        // Products
        $message .= "\n<b>📦 Товари:</b>\n";
        foreach ($products as $product) {
            $message .= "• " . $this->escapeHtml($product['name']);
            if (!empty($product['model'])) {
                $message .= " (" . $this->escapeHtml($product['model']) . ")";
            }
            $message .= " × " . $product['quantity'];
            if (!empty($product['total'])) {
                $message .= " — " . $product['total'];
            }
            $message .= "\n";

            // Product options
            if (!empty($product['option'])) {
                foreach ($product['option'] as $option) {
                    $message .= "   ↳ " . $this->escapeHtml($option['name']) . ": " . $this->escapeHtml($option['value']) . "\n";
                }
            }
        }

        // Totals
        $message .= "\n<b>💰 Підсумок:</b>\n";
        foreach ($totals as $total) {
            $message .= $this->escapeHtml($total['title']) . ": " . $total['text'] . "\n";
        }

        // Comment
        if (!empty($order_info['comment'])) {
            $message .= "\n<b>💬 Коментар:</b>\n" . $this->escapeHtml($order_info['comment']) . "\n";
        }

        // Date
        $message .= "\n<b>📅 Дата:</b> " . date('d.m.Y H:i', strtotime($order_info['date_added']));

        // Admin link
        if ($store_url) {
            $admin_url = rtrim($store_url, '/') . '/overlord/index.php?route=sale/order/info&order_id=' . $order_info['order_id'];
            $message .= "\n\n<a href=\"" . $admin_url . "\">Переглянути в адмінці →</a>";
        }

        return $message;
    }

    /**
     * Escape HTML special characters for Telegram
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeHtml($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
