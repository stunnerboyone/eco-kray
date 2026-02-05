<?php
/**
 * LiqPay Payment Module - API v3
 * Updated to use modern JSON-based API
 */
class ControllerExtensionPaymentLiqPay extends Controller {

	public function index() {
		$data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		if (!isset($this->session->data['order_id'])) {
			return false;
		}

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		if (!$order_info) {
			return false;
		}

		// LiqPay API v3
		$public_key = $this->config->get('payment_liqpay_merchant');
		$private_key = $this->config->get('payment_liqpay_signature');

		$params = [
			'version'     => 3,
			'public_key'  => $public_key,
			'action'      => 'pay',
			'amount'      => round($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), 2),
			'currency'    => $order_info['currency_code'],
			'description' => 'Замовлення #' . $order_info['order_id'] . ' - ' . $this->config->get('config_name'),
			'order_id'    => $order_info['order_id'] . '_' . time(),
			'result_url'  => $this->url->link('extension/payment/liqpay/return', '', true),
			'server_url'  => $this->url->link('extension/payment/liqpay/callback', '', true),
			'language'    => $this->getLanguageCode()
		];

		$data['action'] = 'https://www.liqpay.ua/api/3/checkout';
		$data['data'] = base64_encode(json_encode($params));
		$data['signature'] = base64_encode(sha1($private_key . $data['data'] . $private_key, true));

		return $this->load->view('extension/payment/liqpay', $data);
	}

	/**
	 * Server-to-server callback from LiqPay
	 */
	public function callback() {
		if (!isset($this->request->post['data']) || !isset($this->request->post['signature'])) {
			return;
		}

		$data = $this->request->post['data'];
		$signature = $this->request->post['signature'];
		$private_key = $this->config->get('payment_liqpay_signature');

		// Verify signature
		$expected_signature = base64_encode(sha1($private_key . $data . $private_key, true));

		if ($signature !== $expected_signature) {
			return;
		}

		$response = json_decode(base64_decode($data), true);

		if (!$response || !isset($response['order_id']) || !isset($response['status'])) {
			return;
		}

		// Extract original order_id (before underscore timestamp)
		$order_id_parts = explode('_', $response['order_id']);
		$order_id = (int)$order_id_parts[0];

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);
		if (!$order_info) {
			return;
		}

		// Process based on status
		if (in_array($response['status'], ['success', 'sandbox'])) {
			// Payment successful
			$status_id = $this->config->get('payment_liqpay_order_status_id') ?: $this->config->get('config_order_status_id');
			$comment = 'LiqPay: Оплата успішна. Transaction ID: ' . ($response['transaction_id'] ?? 'N/A');

			$this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true);

		} elseif (in_array($response['status'], ['failure', 'error', 'reversed'])) {
			// Payment failed
			$comment = 'LiqPay: Помилка оплати - ' . ($response['err_description'] ?? $response['status']);

			$this->model_checkout_order->addOrderHistory($order_id, 10, $comment, false);

		} elseif ($response['status'] === 'wait_accept') {
			// Waiting for confirmation
			$comment = 'LiqPay: Очікує підтвердження';

			$this->model_checkout_order->addOrderHistory($order_id, 1, $comment, false);
		}

		echo 'OK';
	}

	/**
	 * Return URL - user redirect after payment
	 */
	public function return() {
		if (isset($this->request->post['data'])) {
			$data = $this->request->post['data'];
			$response = json_decode(base64_decode($data), true);

			if ($response && isset($response['status']) && in_array($response['status'], ['success', 'sandbox'])) {
				// Payment successful - redirect to success page
				$this->response->redirect($this->url->link('checkout/success', '', true));
				return;
			}
		}

		// Payment failed or cancelled - redirect back to checkout
		$this->session->data['error'] = 'Оплата не була завершена. Спробуйте ще раз.';
		$this->response->redirect($this->url->link('checkout/checkout', '', true));
	}

	/**
	 * Get language code for LiqPay
	 */
	private function getLanguageCode() {
		$lang = $this->config->get('config_language');

		if (strpos($lang, 'uk') !== false) {
			return 'uk';
		} elseif (strpos($lang, 'ru') !== false) {
			return 'ru';
		}

		return 'en';
	}
}