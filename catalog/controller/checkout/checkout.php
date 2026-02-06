<?php
class ControllerCheckoutCheckout extends Controller {
	public function index() {
		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$this->response->redirect($this->url->link('checkout/cart'));
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$this->response->redirect($this->url->link('checkout/cart'));
			}
		}

		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->document->addStyle('catalog/view/theme/default/stylesheet/checkout-unified.css');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['logged'] = $this->customer->isLogged();
		$data['shipping_required'] = $this->cart->hasShipping();

		// Customer info for logged users
		if ($this->customer->isLogged()) {
			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
			$data['customer_firstname'] = $customer_info['firstname'];
			$data['customer_lastname'] = $customer_info['lastname'];
			$data['customer_email'] = $customer_info['email'];
			$data['customer_telephone'] = $customer_info['telephone'];
		} else {
			$data['customer_firstname'] = '';
			$data['customer_lastname'] = '';
			$data['customer_email'] = '';
			$data['customer_telephone'] = '';
		}

		$data['forgotten'] = $this->url->link('account/forgotten', '', true);

		// Guest checkout allowed?
		$data['checkout_guest'] = ($this->config->get('config_checkout_guest') && !$this->config->get('config_customer_price') && !$this->cart->hasDownload());

		// Cart products
		$data['products'] = array();
		$this->load->model('tool/image');
		$this->load->model('tool/upload');

		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				if ($option['type'] != 'file') {
					$value = $option['value'];
				} else {
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
					$value = $upload_info ? $upload_info['name'] : '';
				}

				$option_data[] = array(
					'name'  => $option['name'],
					'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
				);
			}

			if ($product['image']) {
				$image = $this->model_tool_image->resize($product['image'], 80, 80);
			} else {
				$image = $this->model_tool_image->resize('no_image.png', 80, 80);
			}

			$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			$total = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']);

			$data['products'][] = array(
				'cart_id'    => $product['cart_id'],
				'product_id' => $product['product_id'],
				'thumb'      => $image,
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'quantity'   => $product['quantity'],
				'stock'      => $product['stock'],
				'price'      => $price,
				'total'      => $total,
				'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			);
		}

		// Vouchers
		$data['vouchers'] = array();
		if (!empty($this->session->data['vouchers'])) {
			foreach ($this->session->data['vouchers'] as $key => $voucher) {
				$data['vouchers'][] = array(
					'key'         => $key,
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency'])
				);
			}
		}

		// Totals
		$data['totals'] = $this->getOrderTotals();

		// Shipping methods
		$data['shipping_methods'] = array();

		if ($this->cart->hasShipping()) {
			// Set default shipping address for Ukraine if not set
			if (!isset($this->session->data['shipping_address'])) {
				$this->session->data['shipping_address'] = array(
					'address_id'     => 0,
					'firstname'      => '',
					'lastname'       => '',
					'company'        => '',
					'address_1'      => '',
					'address_2'      => '',
					'postcode'       => '',
					'city'           => '',
					'zone_id'        => $this->config->get('config_zone_id'),
					'zone'           => '',
					'zone_code'      => '',
					'country_id'     => $this->config->get('config_country_id'),
					'country'        => '',
					'iso_code_2'     => 'UA',
					'iso_code_3'     => 'UKR',
					'address_format' => ''
				);
			}

			// Set default payment address too
			if (!isset($this->session->data['payment_address'])) {
				$this->session->data['payment_address'] = $this->session->data['shipping_address'];
			}

			$this->load->model('setting/extension');
			$results = $this->model_setting_extension->getExtensions('shipping');

			foreach ($results as $result) {
				if ($this->config->get('shipping_' . $result['code'] . '_status')) {
					$this->load->model('extension/shipping/' . $result['code']);

					$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

					if ($quote) {
						$data['shipping_methods'][$result['code']] = array(
							'title'      => $quote['title'],
							'quote'      => $quote['quote'],
							'sort_order' => $quote['sort_order'],
							'error'      => $quote['error']
						);
					}
				}
			}

			$sort_order = array();
			foreach ($data['shipping_methods'] as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}
			array_multisort($sort_order, SORT_ASC, $data['shipping_methods']);

			$this->session->data['shipping_methods'] = $data['shipping_methods'];
		}

		// Selected shipping method
		if (isset($this->session->data['shipping_method']['code'])) {
			$data['shipping_code'] = $this->session->data['shipping_method']['code'];
		} else {
			$data['shipping_code'] = '';
		}

		// Payment methods
		$data['payment_methods'] = array();

		if (!isset($this->session->data['payment_address'])) {
			$this->session->data['payment_address'] = array(
				'address_id'     => 0,
				'firstname'      => '',
				'lastname'       => '',
				'company'        => '',
				'address_1'      => '',
				'address_2'      => '',
				'postcode'       => '',
				'city'           => '',
				'zone_id'        => $this->config->get('config_zone_id'),
				'zone'           => '',
				'zone_code'      => '',
				'country_id'     => $this->config->get('config_country_id'),
				'country'        => '',
				'iso_code_2'     => 'UA',
				'iso_code_3'     => 'UKR',
				'address_format' => ''
			);
		}

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;
		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$this->load->model('setting/extension');
		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
		}
		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$method_data = array();
		$results = $this->model_setting_extension->getExtensions('payment');
		$recurring = $this->cart->hasRecurringProducts();

		foreach ($results as $result) {
			if ($this->config->get('payment_' . $result['code'] . '_status')) {
				$this->load->model('extension/payment/' . $result['code']);

				$method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

				if ($method) {
					if ($recurring) {
						if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
							$method_data[$result['code']] = $method;
						}
					} else {
						$method_data[$result['code']] = $method;
					}
				}
			}
		}

		$sort_order = array();
		foreach ($method_data as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $method_data);

		$this->session->data['payment_methods'] = $method_data;
		$data['payment_methods'] = $method_data;

		// Selected payment method
		if (isset($this->session->data['payment_method']['code'])) {
			$data['payment_code'] = $this->session->data['payment_method']['code'];
		} else {
			$data['payment_code'] = '';
		}

		// Agreement text
		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');
			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info) {
				$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true), $information_info['title']);
			} else {
				$data['text_agree'] = '';
			}
		} else {
			$data['text_agree'] = '';
		}

		// Comment
		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}

		// Formatted error messages
		$data['error_no_shipping'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact'));
		$data['error_no_payment'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));

		// Countries for address form
		$this->load->model('localisation/country');
		$data['countries'] = $this->model_localisation_country->getCountries();
		$data['country_id'] = $this->config->get('config_country_id');
		$data['zone_id'] = $this->config->get('config_zone_id');

		// Config
		$data['config_stock_warning'] = $this->config->get('config_stock_warning');
		$data['config_stock_checkout'] = $this->config->get('config_stock_checkout');

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/checkout', $data));
	}

	private function getOrderTotals() {
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$this->load->model('setting/extension');

		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$sort_order = array();
		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $totals);

		$formatted_totals = array();
		foreach ($totals as $t) {
			$formatted_totals[] = array(
				'title' => $t['title'],
				'text'  => $this->currency->format($t['value'], $this->session->data['currency'])
			);
		}

		return $formatted_totals;
	}

	public function country() {
		$json = array();

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function customfield() {
		$json = array();

		$this->load->model('account/custom_field');

		if (isset($this->request->get['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->get['customer_group_id'], $this->config->get('config_customer_group_display'))) {
			$customer_group_id = $this->request->get['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$custom_fields = $this->model_account_custom_field->getCustomFields($customer_group_id);

		foreach ($custom_fields as $custom_field) {
			$json[] = array(
				'custom_field_id' => $custom_field['custom_field_id'],
				'required'        => $custom_field['required']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Update cart item quantity
	 */
	public function updateCart() {
		$json = array();

		if (isset($this->request->post['cart_id']) && isset($this->request->post['quantity'])) {
			$cart_id = (int)$this->request->post['cart_id'];
			$quantity = (int)$this->request->post['quantity'];

			if ($quantity > 0) {
				$this->cart->update($cart_id, $quantity);
			} else {
				$this->cart->remove($cart_id);
			}

			$json['success'] = true;
			$json['cart_data'] = $this->getCartData();
		} else {
			$json['error'] = 'Missing parameters';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Remove cart item
	 */
	public function removeCart() {
		$json = array();

		if (isset($this->request->post['cart_id'])) {
			$this->cart->remove((int)$this->request->post['cart_id']);

			$json['success'] = true;
			$json['cart_data'] = $this->getCartData();
		} else {
			$json['error'] = 'Missing cart_id';
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Refresh the entire checkout (cart + totals + shipping + payment)
	 */
	public function refresh() {
		$json = array();

		$json['cart_data'] = $this->getCartData();

		// Refresh shipping methods
		if ($this->cart->hasShipping() && isset($this->session->data['shipping_address'])) {
			$method_data = array();
			$this->load->model('setting/extension');
			$results = $this->model_setting_extension->getExtensions('shipping');

			foreach ($results as $result) {
				if ($this->config->get('shipping_' . $result['code'] . '_status')) {
					$this->load->model('extension/shipping/' . $result['code']);
					$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

					if ($quote) {
						$method_data[$result['code']] = array(
							'title'      => $quote['title'],
							'quote'      => $quote['quote'],
							'sort_order' => $quote['sort_order'],
							'error'      => $quote['error']
						);
					}
				}
			}

			$sort_order = array();
			foreach ($method_data as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}
			array_multisort($sort_order, SORT_ASC, $method_data);

			$this->session->data['shipping_methods'] = $method_data;
			$json['shipping_methods'] = $method_data;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Save shipping method selection
	 */
	public function saveShipping() {
		$this->load->language('checkout/checkout');

		$json = array();

		if (!isset($this->request->post['shipping_method'])) {
			$json['error'] = $this->language->get('error_shipping');
		} else {
			$shipping = explode('.', $this->request->post['shipping_method']);

			if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
				$json['error'] = $this->language->get('error_shipping');
			}
		}

		if (!isset($json['error'])) {
			$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
			$json['success'] = true;

			// Recalculate totals
			$json['totals'] = $this->getOrderTotals();

			// Refresh payment methods (they may depend on total)
			$json['payment_methods'] = $this->getPaymentMethods();
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Save payment method selection
	 */
	public function savePayment() {
		$this->load->language('checkout/checkout');

		$json = array();

		if (!isset($this->request->post['payment_method'])) {
			$json['error'] = $this->language->get('error_payment');
		} elseif (!isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
			$json['error'] = $this->language->get('error_payment');
		}

		if (!isset($json['error'])) {
			$this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];
			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Save guest details and proceed
	 */
	public function saveGuest() {
		$this->load->language('checkout/checkout');

		$json = array();

		if (!$json) {
			if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
				$json['error']['firstname'] = $this->language->get('error_firstname');
			}

			if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
				$json['error']['lastname'] = $this->language->get('error_lastname');
			}

			if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$json['error']['email'] = $this->language->get('error_email');
			}

			if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
				$json['error']['telephone'] = $this->language->get('error_telephone');
			}
		}

		if (!isset($json['error'])) {
			$this->session->data['guest'] = array(
				'customer_group_id' => $this->config->get('config_customer_group_id'),
				'firstname'         => $this->request->post['firstname'],
				'lastname'          => $this->request->post['lastname'],
				'email'             => $this->request->post['email'],
				'telephone'         => $this->request->post['telephone'],
				'custom_field'      => array()
			);

			$this->session->data['payment_address'] = array(
				'firstname'      => $this->request->post['firstname'],
				'lastname'       => $this->request->post['lastname'],
				'company'        => '',
				'address_1'      => isset($this->request->post['address_1']) ? $this->request->post['address_1'] : '',
				'address_2'      => '',
				'postcode'       => '',
				'city'           => isset($this->request->post['city']) ? $this->request->post['city'] : '',
				'zone_id'        => $this->config->get('config_zone_id'),
				'zone'           => '',
				'country_id'     => $this->config->get('config_country_id'),
				'country'        => '',
				'address_format' => '',
				'custom_field'   => array()
			);

			$this->session->data['shipping_address'] = $this->session->data['payment_address'];

			$this->session->data['account'] = 'guest';

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * AJAX: Confirm the order
	 */
	public function confirmOrder() {
		$this->load->language('checkout/checkout');

		$json = array();

		// Validate cart
		if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
			$json['error'] = 'Cart is empty';
		}

		// Validate customer
		if (!$this->customer->isLogged() && !isset($this->session->data['guest'])) {
			$json['error'] = $this->language->get('error_login');
		}

		// Validate shipping
		if ($this->cart->hasShipping() && !isset($this->session->data['shipping_method'])) {
			$json['error'] = $this->language->get('error_shipping');
		}

		// Validate payment
		if (!isset($this->session->data['payment_method'])) {
			$json['error'] = $this->language->get('error_payment');
		}

		// Agreement
		if ($this->config->get('config_checkout_id')) {
			$this->load->model('catalog/information');
			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));

			if ($information_info && !isset($this->request->post['agree'])) {
				$json['error'] = sprintf($this->language->get('error_agree'), $information_info['title']);
			}
		}

		if (!isset($json['error'])) {
			// Save comment
			$this->session->data['comment'] = isset($this->request->post['comment']) ? strip_tags($this->request->post['comment']) : '';

			// Create the order
			$order_data = $this->buildOrderData();

			$this->load->model('checkout/order');
			$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

			// Get payment form
			$payment_code = $this->session->data['payment_method']['code'];
			$json['payment_html'] = $this->load->controller('extension/payment/' . $payment_code);

			if (empty($json['payment_html'])) {
				// For COD or similar payment methods with no form, auto-confirm
				$json['redirect'] = $this->url->link('checkout/success');
			}

			$json['success'] = true;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function buildOrderData() {
		$order_data = array();

		// Totals
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$this->load->model('setting/extension');

		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$sort_order = array();
		foreach ($totals as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $totals);

		$order_data['totals'] = $totals;

		// Store info
		$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
		$order_data['store_id'] = $this->config->get('config_store_id');
		$order_data['store_name'] = $this->config->get('config_name');

		if ($order_data['store_id']) {
			$order_data['store_url'] = $this->config->get('config_url');
		} else {
			if ($this->request->server['HTTPS']) {
				$order_data['store_url'] = HTTPS_SERVER;
			} else {
				$order_data['store_url'] = HTTP_SERVER;
			}
		}

		// Customer info
		$this->load->model('account/customer');

		if ($this->customer->isLogged()) {
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

			$order_data['customer_id'] = $this->customer->getId();
			$order_data['customer_group_id'] = $customer_info['customer_group_id'];
			$order_data['firstname'] = $customer_info['firstname'];
			$order_data['lastname'] = $customer_info['lastname'];
			$order_data['email'] = $customer_info['email'];
			$order_data['telephone'] = $customer_info['telephone'];
			$order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
		} elseif (isset($this->session->data['guest'])) {
			$order_data['customer_id'] = 0;
			$order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
			$order_data['firstname'] = $this->session->data['guest']['firstname'];
			$order_data['lastname'] = $this->session->data['guest']['lastname'];
			$order_data['email'] = $this->session->data['guest']['email'];
			$order_data['telephone'] = $this->session->data['guest']['telephone'];
			$order_data['custom_field'] = isset($this->session->data['guest']['custom_field']) ? $this->session->data['guest']['custom_field'] : array();
		}

		// Payment address
		$order_data['payment_firstname'] = isset($this->session->data['payment_address']['firstname']) ? $this->session->data['payment_address']['firstname'] : '';
		$order_data['payment_lastname'] = isset($this->session->data['payment_address']['lastname']) ? $this->session->data['payment_address']['lastname'] : '';
		$order_data['payment_company'] = isset($this->session->data['payment_address']['company']) ? $this->session->data['payment_address']['company'] : '';
		$order_data['payment_address_1'] = isset($this->session->data['payment_address']['address_1']) ? $this->session->data['payment_address']['address_1'] : '';
		$order_data['payment_address_2'] = isset($this->session->data['payment_address']['address_2']) ? $this->session->data['payment_address']['address_2'] : '';
		$order_data['payment_city'] = isset($this->session->data['payment_address']['city']) ? $this->session->data['payment_address']['city'] : '';
		$order_data['payment_postcode'] = isset($this->session->data['payment_address']['postcode']) ? $this->session->data['payment_address']['postcode'] : '';
		$order_data['payment_zone'] = isset($this->session->data['payment_address']['zone']) ? $this->session->data['payment_address']['zone'] : '';
		$order_data['payment_zone_id'] = isset($this->session->data['payment_address']['zone_id']) ? $this->session->data['payment_address']['zone_id'] : '';
		$order_data['payment_country'] = isset($this->session->data['payment_address']['country']) ? $this->session->data['payment_address']['country'] : '';
		$order_data['payment_country_id'] = isset($this->session->data['payment_address']['country_id']) ? $this->session->data['payment_address']['country_id'] : '';
		$order_data['payment_address_format'] = isset($this->session->data['payment_address']['address_format']) ? $this->session->data['payment_address']['address_format'] : '';
		$order_data['payment_custom_field'] = isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array();

		// Payment method
		if (isset($this->session->data['payment_method']['title'])) {
			$order_data['payment_method'] = $this->session->data['payment_method']['title'];
		} else {
			$order_data['payment_method'] = '';
		}

		if (isset($this->session->data['payment_method']['code'])) {
			$order_data['payment_code'] = $this->session->data['payment_method']['code'];
		} else {
			$order_data['payment_code'] = '';
		}

		// Shipping
		if ($this->cart->hasShipping()) {
			$order_data['shipping_firstname'] = isset($this->session->data['shipping_address']['firstname']) ? $this->session->data['shipping_address']['firstname'] : '';
			$order_data['shipping_lastname'] = isset($this->session->data['shipping_address']['lastname']) ? $this->session->data['shipping_address']['lastname'] : '';
			$order_data['shipping_company'] = isset($this->session->data['shipping_address']['company']) ? $this->session->data['shipping_address']['company'] : '';
			$order_data['shipping_address_1'] = isset($this->session->data['shipping_address']['address_1']) ? $this->session->data['shipping_address']['address_1'] : '';
			$order_data['shipping_address_2'] = isset($this->session->data['shipping_address']['address_2']) ? $this->session->data['shipping_address']['address_2'] : '';
			$order_data['shipping_city'] = isset($this->session->data['shipping_address']['city']) ? $this->session->data['shipping_address']['city'] : '';
			$order_data['shipping_postcode'] = isset($this->session->data['shipping_address']['postcode']) ? $this->session->data['shipping_address']['postcode'] : '';
			$order_data['shipping_zone'] = isset($this->session->data['shipping_address']['zone']) ? $this->session->data['shipping_address']['zone'] : '';
			$order_data['shipping_zone_id'] = isset($this->session->data['shipping_address']['zone_id']) ? $this->session->data['shipping_address']['zone_id'] : '';
			$order_data['shipping_country'] = isset($this->session->data['shipping_address']['country']) ? $this->session->data['shipping_address']['country'] : '';
			$order_data['shipping_country_id'] = isset($this->session->data['shipping_address']['country_id']) ? $this->session->data['shipping_address']['country_id'] : '';
			$order_data['shipping_address_format'] = isset($this->session->data['shipping_address']['address_format']) ? $this->session->data['shipping_address']['address_format'] : '';
			$order_data['shipping_custom_field'] = isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array();

			if (isset($this->session->data['shipping_method']['title'])) {
				$order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
			} else {
				$order_data['shipping_method'] = '';
			}

			if (isset($this->session->data['shipping_method']['code'])) {
				$order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
			} else {
				$order_data['shipping_code'] = '';
			}
		} else {
			$order_data['shipping_firstname'] = '';
			$order_data['shipping_lastname'] = '';
			$order_data['shipping_company'] = '';
			$order_data['shipping_address_1'] = '';
			$order_data['shipping_address_2'] = '';
			$order_data['shipping_city'] = '';
			$order_data['shipping_postcode'] = '';
			$order_data['shipping_zone'] = '';
			$order_data['shipping_zone_id'] = '';
			$order_data['shipping_country'] = '';
			$order_data['shipping_country_id'] = '';
			$order_data['shipping_address_format'] = '';
			$order_data['shipping_custom_field'] = array();
			$order_data['shipping_method'] = '';
			$order_data['shipping_code'] = '';
		}

		// Products
		$order_data['products'] = array();

		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				$option_data[] = array(
					'product_option_id'       => $option['product_option_id'],
					'product_option_value_id' => $option['product_option_value_id'],
					'option_id'               => $option['option_id'],
					'option_value_id'         => $option['option_value_id'],
					'name'                    => $option['name'],
					'value'                   => $option['value'],
					'type'                    => $option['type']
				);
			}

			$order_data['products'][] = array(
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'download'   => $product['download'],
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $product['price'],
				'total'      => $product['total'],
				'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
				'reward'     => $product['reward']
			);
		}

		// Vouchers
		$order_data['vouchers'] = array();

		if (!empty($this->session->data['vouchers'])) {
			foreach ($this->session->data['vouchers'] as $voucher) {
				$order_data['vouchers'][] = array(
					'description'      => $voucher['description'],
					'code'             => token(10),
					'to_name'          => $voucher['to_name'],
					'to_email'         => $voucher['to_email'],
					'from_name'        => $voucher['from_name'],
					'from_email'       => $voucher['from_email'],
					'voucher_theme_id' => $voucher['voucher_theme_id'],
					'message'          => $voucher['message'],
					'amount'           => $voucher['amount']
				);
			}
		}

		$order_data['comment'] = isset($this->session->data['comment']) ? $this->session->data['comment'] : '';
		$order_data['total'] = $total;

		// Affiliate / Marketing
		if (isset($this->request->cookie['tracking'])) {
			$order_data['tracking'] = $this->request->cookie['tracking'];

			$subtotal = $this->cart->getSubTotal();

			$affiliate_info = $this->model_account_customer->getAffiliateByTracking($this->request->cookie['tracking']);

			if ($affiliate_info) {
				$order_data['affiliate_id'] = $affiliate_info['customer_id'];
				$order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
			} else {
				$order_data['affiliate_id'] = 0;
				$order_data['commission'] = 0;
			}

			$this->load->model('checkout/marketing');
			$marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

			if ($marketing_info) {
				$order_data['marketing_id'] = $marketing_info['marketing_id'];
			} else {
				$order_data['marketing_id'] = 0;
			}
		} else {
			$order_data['affiliate_id'] = 0;
			$order_data['commission'] = 0;
			$order_data['marketing_id'] = 0;
			$order_data['tracking'] = '';
		}

		$order_data['language_id'] = $this->config->get('config_language_id');
		$order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
		$order_data['currency_code'] = $this->session->data['currency'];
		$order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
		$order_data['ip'] = $this->request->server['REMOTE_ADDR'];

		if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
			$order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
		} elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
			$order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
		} else {
			$order_data['forwarded_ip'] = '';
		}

		if (isset($this->request->server['HTTP_USER_AGENT'])) {
			$order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
		} else {
			$order_data['user_agent'] = '';
		}

		if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
			$order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
		} else {
			$order_data['accept_language'] = '';
		}

		return $order_data;
	}

	private function getCartData() {
		$this->load->model('tool/image');
		$this->load->model('tool/upload');

		$products = array();

		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();

			foreach ($product['option'] as $option) {
				if ($option['type'] != 'file') {
					$value = $option['value'];
				} else {
					$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);
					$value = $upload_info ? $upload_info['name'] : '';
				}

				$option_data[] = array(
					'name'  => $option['name'],
					'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
				);
			}

			if ($product['image']) {
				$image = $this->model_tool_image->resize($product['image'], 80, 80);
			} else {
				$image = $this->model_tool_image->resize('no_image.png', 80, 80);
			}

			$price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
			$total = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']);

			$products[] = array(
				'cart_id'    => $product['cart_id'],
				'product_id' => $product['product_id'],
				'thumb'      => $image,
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'quantity'   => $product['quantity'],
				'stock'      => $product['stock'],
				'price'      => $price,
				'total'      => $total,
				'href'       => $this->url->link('product/product', 'product_id=' . $product['product_id'])
			);
		}

		return array(
			'products'    => $products,
			'totals'      => $this->getOrderTotals(),
			'has_products' => $this->cart->hasProducts(),
			'count'       => $this->cart->countProducts()
		);
	}

	private function getPaymentMethods() {
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;
		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

		$this->load->model('setting/extension');

		$sort_order = array();
		$results = $this->model_setting_extension->getExtensions('total');

		foreach ($results as $key => $value) {
			$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
		}

		array_multisort($sort_order, SORT_ASC, $results);

		foreach ($results as $result) {
			if ($this->config->get('total_' . $result['code'] . '_status')) {
				$this->load->model('extension/total/' . $result['code']);
				$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
			}
		}

		$method_data = array();
		$results = $this->model_setting_extension->getExtensions('payment');
		$recurring = $this->cart->hasRecurringProducts();

		foreach ($results as $result) {
			if ($this->config->get('payment_' . $result['code'] . '_status')) {
				$this->load->model('extension/payment/' . $result['code']);

				$method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

				if ($method) {
					if ($recurring) {
						if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
							$method_data[$result['code']] = $method;
						}
					} else {
						$method_data[$result['code']] = $method;
					}
				}
			}
		}

		$sort_order = array();
		foreach ($method_data as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $method_data);

		$this->session->data['payment_methods'] = $method_data;

		return $method_data;
	}
}
