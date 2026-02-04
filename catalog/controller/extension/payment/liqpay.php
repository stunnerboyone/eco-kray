<?php
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

		// LiqPay API v3 checkout URL
		$data['action'] = 'https://www.liqpay.ua/api/3/checkout';

		// Prepare payment data for API v3
		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

		$description = $this->config->get('config_name') . ' - Замовлення #' . $this->session->data['order_id'];

		$liqpay_data = array(
			'version'     => 3,
			'public_key'  => $this->config->get('payment_liqpay_merchant'),
			'action'      => 'pay',
			'amount'      => $amount,
			'currency'    => $order_info['currency_code'],
			'description' => $description,
			'order_id'    => (string)$this->session->data['order_id'],
			'result_url'  => $this->url->link('checkout/success', '', true),
			'server_url'  => $this->url->link('extension/payment/liqpay/callback', '', true)
		);

		// Add payment type if specified
		$pay_type = $this->config->get('payment_liqpay_type');
		if ($pay_type && $pay_type != 'liqpay') {
			$liqpay_data['paytypes'] = $pay_type;
		}

		// Encode data and create signature
		$data['data'] = base64_encode(json_encode($liqpay_data));
		$private_key = $this->config->get('payment_liqpay_signature');
		$data['signature'] = base64_encode(sha1($private_key . $data['data'] . $private_key, true));

		return $this->load->view('extension/payment/liqpay', $data);
	}

	public function callback() {
		// Verify required parameters exist
		if (!isset($this->request->post['data']) || !isset($this->request->post['signature'])) {
			return;
		}

		$data = $this->request->post['data'];
		$received_signature = $this->request->post['signature'];

		// Verify signature
		$private_key = $this->config->get('payment_liqpay_signature');
		$expected_signature = base64_encode(sha1($private_key . $data . $private_key, true));

		if ($received_signature !== $expected_signature) {
			return;
		}

		// Decode and parse response
		$response = json_decode(base64_decode($data), true);

		if (!$response || !isset($response['order_id']) || !isset($response['status'])) {
			return;
		}

		$order_id = (int)$response['order_id'];
		$status = $response['status'];

		// Verify order exists
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);

		if (!$order_info) {
			return;
		}

		// Process based on payment status
		// success - successful payment
		// sandbox - successful payment in test mode
		if ($status === 'success' || $status === 'sandbox') {
			$order_status_id = $this->config->get('payment_liqpay_order_status_id');

			if (!$order_status_id) {
				$order_status_id = $this->config->get('config_order_status_id');
			}

			$comment = 'LiqPay: Платіж успішний. ';

			if (isset($response['payment_id'])) {
				$comment .= 'ID транзакції: ' . $response['payment_id'] . '. ';
			}

			if (isset($response['amount']) && isset($response['currency'])) {
				$comment .= 'Сума: ' . $response['amount'] . ' ' . $response['currency'];
			}

			$this->model_checkout_order->addOrderHistory($order_id, $order_status_id, $comment, true);
		}
	}
}
