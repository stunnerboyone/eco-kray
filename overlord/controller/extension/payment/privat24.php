<?php 
class ControllerExtensionPaymentPrivat24 extends Controller {
	private $error = array(); 

	public function index() {
		$this->load->language('extension/payment/privat24');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {			
			$this->model_setting_setting->editSetting('payment_privat24', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

 		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['merchant'])) { 
			$data['error_merchant'] = $this->error['merchant'];
		} else {
			$data['error_merchant'] = '';
		}
		
		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}
		
		if (isset($this->error['currency'])) {
			$data['error_currency'] = $this->error['currency'];
		} else {
			$data['error_currency'] = '';
		}
				
		if (isset($this->error['type'])) { 
			$data['error_type'] = $this->error['type'];
		} else {
			$data['error_type'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/privat24', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/privat24', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
     
		if (isset($this->request->post['payment_privat24_merchant'])) {
			$data['payment_privat24_merchant'] = $this->request->post['payment_privat24_merchant'];
		} else {
			$data['payment_privat24_merchant'] = $this->config->get('payment_privat24_merchant');
		}

		if (isset($this->request->post['payment_privat24_password'])) {
			$data['payment_privat24_password'] = $this->request->post['payment_privat24_password'];
		} else {
			$data['payment_privat24_password'] = $this->config->get('payment_privat24_password');
		}

		if (isset($this->request->post['payment_privat24_type'])) {
			$data['payment_privat24_type'] = $this->request->post['payment_privat24_type'];
		} else {
			$data['payment_privat24_type'] = $this->config->get('payment_privat24_type');
		}

		if (isset($this->request->post['payment_privat24_currency'])) {
			$data['payment_privat24_currency'] = $this->request->post['payment_privat24_currency'];
		} else {
			$data['payment_privat24_currency'] = $this->config->get('payment_privat24_currency');
		}

		if (isset($this->request->post['payment_privat24_total'])) {
			$data['payment_privat24_total'] = $this->request->post['payment_privat24_total'];
		} else {
			$data['payment_privat24_total'] = $this->config->get('payment_privat24_total');
		}
				
		if (isset($this->request->post['payment_privat24_order_status_id'])) {
			$data['payment_privat24_order_status_id'] = $this->request->post['payment_privat24_order_status_id'];
		} else {
			$data['payment_privat24_order_status_id'] = $this->config->get('payment_privat24_order_status_id');
		} 

		$this->load->model('localisation/order_status');
		
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		if (isset($this->request->post['payment_privat24_geo_zone_id'])) {
			$data['payment_privat24_geo_zone_id'] = $this->request->post['payment_privat24_geo_zone_id'];
		} else {
			$data['payment_privat24_geo_zone_id'] = $this->config->get('payment_privat24_geo_zone_id');
		} 		
		
		$this->load->model('localisation/geo_zone');
										
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_privat24_status'])) {
			$data['payment_privat24_status'] = $this->request->post['payment_privat24_status'];
		} else {
			$data['payment_privat24_status'] = $this->config->get('payment_privat24_status');
		}
		
		if (isset($this->request->post['payment_privat24_sort_order'])) {
			$data['payment_privat24_sort_order'] = $this->request->post['payment_privat24_sort_order'];
		} else {
			$data['payment_privat24_sort_order'] = $this->config->get('payment_privat24_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/privat24', $data));
	}
        
	private function validate() {
             $this->load->model('localisation/currency'); 
             
		if (!$this->user->hasPermission('modify', 'extension/payment/privat24')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		if (!$this->request->post['payment_privat24_merchant']) {
			$this->error['merchant'] = $this->language->get('error_merchant');
		}

		if (!$this->request->post['payment_privat24_password']) {
			$this->error['password'] = $this->language->get('error_password');
		}

        $currencies = array();
		
		$results = $this->model_localisation_currency->getCurrencies();	
                  	
		foreach ($results as $result) {
			if ($result['status']) {
   				$currencies[] = $result['code'];
			}
		}
                        
		if (!in_array((string)$this->request->post['payment_privat24_currency'], $currencies)) { 
			$this->error['currency'] = $this->language->get('error_currency_not');
		}
                
		if ($this->request->post['payment_privat24_currency']=='0') {
			$this->error['currency'] = $this->language->get('error_currency');
		}

		return !$this->error;
	}
}
?>