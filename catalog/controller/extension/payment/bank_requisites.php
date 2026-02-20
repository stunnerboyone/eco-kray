<?php
class ControllerExtensionPaymentBankRequisites extends Controller {
	public function index() {
		return $this->load->view('extension/payment/bank_requisites');
	}

	public function confirm() {
		$json = array();

		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'bank_requisites') {
			$this->load->model('checkout/order');

			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_bank_requisites_order_status_id'));

			$json['redirect'] = $this->url->link('checkout/success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
