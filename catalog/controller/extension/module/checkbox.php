<?php
/**
 * Checkbox ПРРО — Catalog Event Controller
 *
 * Listens to: catalog/model/checkout/order/addOrderHistory/after
 * Automatically creates fiscal receipts when an order reaches a configured status.
 */
class ControllerExtensionModuleCheckbox extends Controller {

    /**
     * Event handler — called after addOrderHistory().
     *
     * @param string $route Event route (not used)
     * @param array  $args  [0 => order_id, 1 => order_status_id, 2 => comment, 3 => notify]
     */
    public function index(&$route, &$args) {
        if (!$this->config->get('module_checkbox_status')) {
            return;
        }

        $order_id        = isset($args[0]) ? (int)$args[0] : 0;
        $order_status_id = isset($args[1]) ? (int)$args[1] : 0;

        if (!$order_id || !$order_status_id) {
            return;
        }

        // Determine which statuses trigger fiscalization
        $trigger_statuses = $this->config->get('module_checkbox_trigger_statuses');

        if (empty($trigger_statuses)) {
            return;
        }

        if (!is_array($trigger_statuses)) {
            $trigger_statuses = array_filter(array_map('intval', explode(',', $trigger_statuses)));
        } else {
            $trigger_statuses = array_map('intval', $trigger_statuses);
        }

        if (!in_array($order_status_id, $trigger_statuses)) {
            return;
        }

        // Avoid duplicates: skip if already fiscalized for this order
        $this->load->model('extension/module/checkbox');
        $existing = $this->model_extension_module_checkbox->getReceiptByOrderId($order_id);

        if ($existing && $existing['status'] === 'DONE') {
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            return;
        }

        $this->fiscalize($order_id, $order_info);
    }

    // -------------------------------------------------------------------------
    // Core fiscalization logic
    // -------------------------------------------------------------------------

    /**
     * Create a fiscal receipt on Checkbox for the given order.
     *
     * @param int   $order_id
     * @param array $order_info
     */
    private function fiscalize($order_id, array $order_info) {
        $log = new Log('checkbox.log');
        $log->write('Checkbox: starting fiscalization for order #' . $order_id);

        try {
            require_once(DIR_SYSTEM . 'library/checkbox_api.php');

            $login            = $this->config->get('module_checkbox_login');
            $password         = $this->config->get('module_checkbox_password');
            $cash_register_id = $this->config->get('module_checkbox_cash_register_id');

            if (!$login || !$password) {
                $log->write('Checkbox: credentials not configured, skipping order #' . $order_id);
                return;
            }

            $api = new CheckboxApi($login, $password, $cash_register_id ?: null);

            // 1. Authenticate
            $api->signIn();

            // 2. Ensure shift is open
            $api->ensureShiftOpen();

            // 3. Build goods list
            $goods         = $this->buildGoods($order_id, $order_info);
            $goods_total   = $this->sumGoods($goods);

            // 4. Build receipt-level discounts if grand total < goods sum
            $payment_value = (int)round($order_info['total'] * 100);
            $discounts     = [];
            $diff          = $goods_total - $payment_value;

            if ($diff > 0) {
                $discounts[] = CheckboxApi::buildDiscount($diff);
                $log->write('Checkbox: applying receipt discount of ' . $diff . ' kopiykas for order #' . $order_id);
            }

            // 5. Payment type: CASHLESS for online payments
            $payment_type = $this->config->get('module_checkbox_payment_type') ?: 'CASHLESS';
            $payments     = [CheckboxApi::buildPayment($payment_value, $payment_type)];

            // 6. Create receipt
            $receipt = $api->createSellReceipt($goods, $payments, $discounts);

            $receipt_id  = $receipt['id'];
            $status      = isset($receipt['status'])      ? $receipt['status']      : 'CREATED';
            $fiscal_code = isset($receipt['fiscal_code']) ? $receipt['fiscal_code'] : '';
            $fiscal_date = isset($receipt['fiscal_date']) ? $receipt['fiscal_date'] : '';
            $tax_url     = isset($receipt['tax_url'])     ? $receipt['tax_url']     : '';

            $this->model_extension_module_checkbox->addReceipt(
                $order_id, $receipt_id, $status, $order_info['total'],
                $fiscal_code, $fiscal_date, $tax_url
            );

            $log->write(
                'Checkbox: receipt created for order #' . $order_id .
                ' | receipt_id: ' . $receipt_id .
                ' | status: ' . $status .
                ' | fiscal_code: ' . $fiscal_code
            );

            // Add note to order history
            $this->load->model('checkout/order');
            $comment = 'Checkbox ПРРО: чек зареєстровано. Фіскальний номер: ' . ($fiscal_code ?: 'очікується');
            $this->db->query(
                "INSERT INTO " . DB_PREFIX . "order_history SET
                 order_id        = '" . (int)$order_id . "',
                 order_status_id = '" . (int)$order_info['order_status_id'] . "',
                 notify          = '0',
                 comment         = '" . $this->db->escape($comment) . "',
                 date_added      = NOW()"
            );

        } catch (Exception $e) {
            $log->write('Checkbox ERROR for order #' . $order_id . ': ' . $e->getMessage());

            // Record the failed attempt
            $this->model_extension_module_checkbox->addReceipt(
                $order_id, '', 'ERROR', isset($order_info['total']) ? $order_info['total'] : 0
            );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the goods array for the Checkbox receipt.
     * Includes products + shipping line if applicable.
     *
     * @param int   $order_id
     * @param array $order_info
     * @return array
     */
    private function buildGoods($order_id, array $order_info) {
        $this->load->model('checkout/order');

        $order_products = $this->model_checkout_order->getOrderProducts($order_id);
        $order_totals   = $this->model_checkout_order->getOrderTotals($order_id);

        $goods = [];

        // Products
        foreach ($order_products as $product) {
            $price_kopiykas = (int)round($product['price'] * 100);
            $quantity       = (int)$product['quantity'] * 1000;

            if ($price_kopiykas <= 0) {
                continue;
            }

            $goods[] = CheckboxApi::buildGood(
                $product['product_id'],
                $product['name'],
                $price_kopiykas,
                $quantity
            );
        }

        // Shipping as a separate line item
        foreach ($order_totals as $total) {
            if ($total['code'] === 'shipping' && (float)$total['value'] > 0) {
                $shipping_kopiykas = (int)round((float)$total['value'] * 100);
                $goods[] = CheckboxApi::buildGood(
                    'shipping',
                    $total['title'] ?: 'Доставка',
                    $shipping_kopiykas,
                    1000
                );
                break;
            }
        }

        return $goods;
    }

    /**
     * Sum all goods amounts (price × quantity/1000) in kopiykas.
     *
     * @param array $goods
     * @return int
     */
    private function sumGoods(array $goods) {
        $total = 0;

        foreach ($goods as $item) {
            $total += (int)$item['good']['price'] * ((int)$item['quantity'] / 1000);
        }

        return (int)round($total);
    }
}
