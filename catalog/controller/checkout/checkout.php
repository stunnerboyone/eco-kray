<?php
/**
 * EcoCheckout - Simple One-Page Checkout for EkoKray
 * Replaces standard OpenCart multi-step checkout
 *
 * Features:
 * - Guest checkout with required fields (name, phone)
 * - Nova Poshta integration (department, poshtomat, courier)
 * - LiqPay payment (API v3)
 * - Cart editing on checkout page
 * - Save customer data for logged-in users
 */
class ControllerCheckoutCheckout extends Controller {

    public function index() {
        // Redirect if cart is empty
        if (!$this->cart->hasProducts()) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        $this->load->language('checkout/checkout');
        $this->load->model('tool/image');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('catalog/view/javascript/ecocheckout.js');

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('checkout/checkout')
        ];

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_contact_info'] = $this->language->get('text_contact_info');
        $data['text_delivery'] = $this->language->get('text_delivery');
        $data['text_payment'] = $this->language->get('text_payment');
        $data['text_cart'] = $this->language->get('text_cart');
        $data['text_comment'] = $this->language->get('text_comment');
        $data['text_total'] = $this->language->get('text_total');
        $data['text_agree'] = $this->language->get('text_agree');
        $data['text_privacy_policy'] = $this->language->get('text_privacy_policy');

        $data['entry_firstname'] = $this->language->get('entry_firstname');
        $data['entry_lastname'] = $this->language->get('entry_lastname');
        $data['entry_telephone'] = $this->language->get('entry_telephone');
        $data['entry_email'] = $this->language->get('entry_email');
        $data['entry_city'] = $this->language->get('entry_city');
        $data['entry_department'] = $this->language->get('entry_department');
        $data['entry_address'] = $this->language->get('entry_address');
        $data['entry_comment'] = $this->language->get('entry_comment');

        $data['text_np_department'] = $this->language->get('text_np_department');
        $data['text_np_poshtomat'] = $this->language->get('text_np_poshtomat');
        $data['text_np_courier'] = $this->language->get('text_np_courier');
        $data['text_liqpay'] = $this->language->get('text_liqpay');
        $data['text_cod'] = $this->language->get('text_cod');

        $data['button_order'] = $this->language->get('button_order');
        $data['button_update'] = $this->language->get('button_update');
        $data['button_remove'] = $this->language->get('button_remove');

        $data['error_firstname'] = $this->language->get('error_firstname');
        $data['error_telephone'] = $this->language->get('error_telephone');
        $data['error_city'] = $this->language->get('error_city');
        $data['error_department'] = $this->language->get('error_department');
        $data['error_address'] = $this->language->get('error_address');
        $data['error_agree'] = $this->language->get('error_agree');

        // URLs for AJAX
        $data['url_search_cities'] = $this->url->link('checkout/checkout/searchCities', '', true);
        $data['url_get_departments'] = $this->url->link('checkout/checkout/getDepartments', '', true);
        $data['url_update_shipping'] = $this->url->link('checkout/checkout/updateShipping', '', true);
        $data['url_update_cart'] = $this->url->link('checkout/checkout/updateCart', '', true);
        $data['url_remove_product'] = $this->url->link('checkout/checkout/removeProduct', '', true);
        $data['url_create_order'] = $this->url->link('checkout/checkout/createOrder', '', true);

        // Customer data (for logged-in users or from session)
        $data['customer'] = $this->getCustomerData();

        // Cart products
        $data['products'] = $this->getCartProducts();

        // Totals
        $data['totals'] = $this->getTotals();

        // Shipping methods
        $data['shipping_methods'] = $this->getShippingMethods();

        // Payment methods
        $data['payment_methods'] = $this->getPaymentMethods();

        // Is customer logged in
        $data['logged'] = $this->customer->isLogged();

        // Error from session (e.g., payment failed)
        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];
            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        // Agreement info
        $this->load->model('catalog/information');
        $information_info = $this->model_catalog_information->getInformation($this->config->get('config_checkout_id'));
        if ($information_info) {
            $data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true), $information_info['title']);
        } else {
            $data['text_agree'] = '';
        }

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('checkout/checkout', $data));
    }

    /**
     * Get customer data from session or logged-in customer
     */
    private function getCustomerData() {
        $customer = [
            'firstname' => '',
            'lastname' => '',
            'telephone' => '',
            'email' => '',
            'city' => '',
            'city_ref' => '',
            'department' => '',
            'department_ref' => '',
            'address' => ''
        ];

        if ($this->customer->isLogged()) {
            $customer['firstname'] = $this->customer->getFirstName();
            $customer['lastname'] = $this->customer->getLastName();
            $customer['telephone'] = $this->customer->getTelephone();
            $customer['email'] = $this->customer->getEmail();

            // Try to get saved Nova Poshta data
            $this->load->model('account/address');
            $address = $this->model_account_address->getAddress($this->customer->getAddressId());
            if ($address) {
                $customer['city'] = $address['city'];
                $customer['address'] = $address['address_1'];
            }
        }

        // Override with session data if exists
        if (isset($this->session->data['eco_checkout'])) {
            $customer = array_merge($customer, $this->session->data['eco_checkout']);
        }

        return $customer;
    }

    /**
     * Get cart products with images
     */
    private function getCartProducts() {
        $this->load->model('tool/image');

        $products = [];

        foreach ($this->cart->getProducts() as $product) {
            if ($product['image']) {
                $image = $this->model_tool_image->resize($product['image'], 80, 80);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 80, 80);
            }

            $products[] = [
                'cart_id' => $product['cart_id'],
                'product_id' => $product['product_id'],
                'name' => $product['name'],
                'model' => $product['model'],
                'image' => $image,
                'option' => $product['option'],
                'quantity' => $product['quantity'],
                'price' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                'total' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                'href' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            ];
        }

        return $products;
    }

    /**
     * Get order totals
     */
    private function getTotals() {
        $this->load->model('setting/extension');

        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $total_data = [
            'totals' => &$totals,
            'taxes'  => &$taxes,
            'total'  => &$total
        ];

        $results = $this->model_setting_extension->getExtensions('total');

        $sort_order = [];
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

        $sort_order = [];
        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $totals);

        $formatted_totals = [];
        foreach ($totals as $total_row) {
            $formatted_totals[] = [
                'title' => $total_row['title'],
                'text' => $this->currency->format($total_row['value'], $this->session->data['currency'])
            ];
        }

        return $formatted_totals;
    }

    /**
     * Get available shipping methods
     */
    private function getShippingMethods() {
        // Check if Nova Poshta module is enabled
        $shipping_novaposhta = $this->config->get('shipping_novaposhta_status');

        $methods = [];

        if ($shipping_novaposhta) {
            $settings = $this->config->get('shipping_novaposhta');

            if (!empty($settings['shipping_methods']['department']['status'])) {
                $methods[] = [
                    'code' => 'novaposhta.department',
                    'title' => $this->language->get('text_np_department'),
                    'type' => 'department'
                ];
            }

            if (!empty($settings['shipping_methods']['poshtomat']['status'])) {
                $methods[] = [
                    'code' => 'novaposhta.poshtomat',
                    'title' => $this->language->get('text_np_poshtomat'),
                    'type' => 'poshtomat'
                ];
            }

            if (!empty($settings['shipping_methods']['doors']['status'])) {
                $methods[] = [
                    'code' => 'novaposhta.doors',
                    'title' => $this->language->get('text_np_courier'),
                    'type' => 'courier'
                ];
            }
        }

        return $methods;
    }

    /**
     * Get available payment methods
     */
    private function getPaymentMethods() {
        $methods = [];

        // LiqPay
        if ($this->config->get('payment_liqpay_status')) {
            $methods[] = [
                'code' => 'liqpay',
                'title' => $this->language->get('text_liqpay')
            ];
        }

        // Cash on Delivery (for future)
        if ($this->config->get('payment_cod_status')) {
            $methods[] = [
                'code' => 'cod',
                'title' => $this->language->get('text_cod')
            ];
        }

        return $methods;
    }

    /**
     * AJAX: Search Nova Poshta cities
     */
    public function searchCities() {
        $json = [];

        if (isset($this->request->get['term']) && utf8_strlen($this->request->get['term']) >= 2) {
            $term = $this->request->get['term'];

            require_once(DIR_SYSTEM . 'helper/novaposhta.php');
            $this->registry->set('novaposhta', new NovaPoshta($this->registry));

            // Search in local database first
            $query = $this->db->query("SELECT `Ref`, `Description`, `DescriptionRu`, `AreaDescription`
                FROM `" . DB_PREFIX . "novaposhta_cities`
                WHERE `Description` LIKE '%" . $this->db->escape($term) . "%'
                   OR `DescriptionRu` LIKE '%" . $this->db->escape($term) . "%'
                ORDER BY `Description`
                LIMIT 20");

            foreach ($query->rows as $city) {
                $label = $city['Description'];
                if ($city['AreaDescription']) {
                    $label .= ' (' . $city['AreaDescription'] . ' обл.)';
                }

                $json[] = [
                    'value' => $city['Ref'],
                    'label' => $label,
                    'city' => $city['Description']
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Get Nova Poshta departments by city
     */
    public function getDepartments() {
        $json = [];

        if (isset($this->request->get['city_ref'])) {
            $city_ref = $this->request->get['city_ref'];
            $type = isset($this->request->get['type']) ? $this->request->get['type'] : 'department';

            require_once(DIR_SYSTEM . 'helper/novaposhta.php');
            $this->registry->set('novaposhta', new NovaPoshta($this->registry));

            $departments = $this->novaposhta->getDepartments($city_ref, $type);

            foreach ($departments as $dept) {
                $json[] = [
                    'value' => $dept['Ref'],
                    'label' => $dept['description'],
                    'number' => $dept['Number']
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Update shipping cost
     */
    public function updateShipping() {
        $json = ['success' => false];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $shipping_code = isset($this->request->post['shipping_code']) ? $this->request->post['shipping_code'] : '';
            $city_ref = isset($this->request->post['city_ref']) ? $this->request->post['city_ref'] : '';
            $department_ref = isset($this->request->post['department_ref']) ? $this->request->post['department_ref'] : '';

            // Save to session
            if (!isset($this->session->data['eco_checkout'])) {
                $this->session->data['eco_checkout'] = [];
            }
            $this->session->data['eco_checkout']['city_ref'] = $city_ref;
            $this->session->data['eco_checkout']['department_ref'] = $department_ref;
            $this->session->data['eco_checkout']['shipping_code'] = $shipping_code;

            // Get shipping cost
            if ($shipping_code && $city_ref) {
                $this->load->model('extension/shipping/novaposhta');

                $address = [
                    'country_id' => $this->config->get('config_country_id'),
                    'zone_id' => 0,
                    'city' => $this->request->post['city'] ?? '',
                    'address_1' => $this->request->post['department'] ?? ''
                ];

                $quote = $this->model_extension_shipping_novaposhta->getQuote($address);

                if ($quote && isset($quote['quote'])) {
                    $method_code = str_replace('novaposhta.', '', $shipping_code);
                    if (isset($quote['quote'][$method_code])) {
                        $this->session->data['shipping_method'] = [
                            'code' => $quote['quote'][$method_code]['code'],
                            'title' => $quote['quote'][$method_code]['title'],
                            'cost' => $quote['quote'][$method_code]['cost'],
                            'tax_class_id' => $quote['quote'][$method_code]['tax_class_id'],
                            'text' => $quote['quote'][$method_code]['text']
                        ];

                        $json['success'] = true;
                        $json['shipping_cost'] = $quote['quote'][$method_code]['text'];
                    }
                }
            }

            // Return updated totals
            $json['totals'] = $this->getTotals();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Update cart quantity
     */
    public function updateCart() {
        $json = ['success' => false];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $cart_id = isset($this->request->post['cart_id']) ? $this->request->post['cart_id'] : 0;
            $quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;

            if ($cart_id && $quantity > 0) {
                $this->cart->update($cart_id, $quantity);

                $json['success'] = true;
                $json['products'] = $this->getCartProducts();
                $json['totals'] = $this->getTotals();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Remove product from cart
     */
    public function removeProduct() {
        $json = ['success' => false];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $cart_id = isset($this->request->post['cart_id']) ? $this->request->post['cart_id'] : 0;

            if ($cart_id) {
                $this->cart->remove($cart_id);

                $json['success'] = true;
                $json['products'] = $this->getCartProducts();
                $json['totals'] = $this->getTotals();
                $json['cart_empty'] = !$this->cart->hasProducts();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX: Create order
     */
    public function createOrder() {
        $json = ['success' => false, 'errors' => []];

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->load->language('checkout/checkout');

            // Validate required fields
            $firstname = isset($this->request->post['firstname']) ? trim($this->request->post['firstname']) : '';
            $lastname = isset($this->request->post['lastname']) ? trim($this->request->post['lastname']) : '';
            $telephone = isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '';
            $email = isset($this->request->post['email']) ? trim($this->request->post['email']) : '';
            $shipping_code = isset($this->request->post['shipping_code']) ? $this->request->post['shipping_code'] : '';
            $payment_code = isset($this->request->post['payment_code']) ? $this->request->post['payment_code'] : '';
            $city = isset($this->request->post['city']) ? trim($this->request->post['city']) : '';
            $city_ref = isset($this->request->post['city_ref']) ? $this->request->post['city_ref'] : '';
            $department = isset($this->request->post['department']) ? trim($this->request->post['department']) : '';
            $department_ref = isset($this->request->post['department_ref']) ? $this->request->post['department_ref'] : '';
            $address = isset($this->request->post['address']) ? trim($this->request->post['address']) : '';
            $comment = isset($this->request->post['comment']) ? trim($this->request->post['comment']) : '';
            $agree = isset($this->request->post['agree']) ? $this->request->post['agree'] : 0;

            // Validation
            if (utf8_strlen($firstname) < 2 || utf8_strlen($firstname) > 32) {
                $json['errors']['firstname'] = $this->language->get('error_firstname');
            }

            if (utf8_strlen($telephone) < 10 || utf8_strlen($telephone) > 15) {
                $json['errors']['telephone'] = $this->language->get('error_telephone');
            }

            if (!$city || !$city_ref) {
                $json['errors']['city'] = $this->language->get('error_city');
            }

            // For courier delivery - address is required
            if (strpos($shipping_code, 'doors') !== false) {
                if (utf8_strlen($address) < 3) {
                    $json['errors']['address'] = $this->language->get('error_address');
                }
            } else {
                // For department/poshtomat - department is required
                if (!$department_ref) {
                    $json['errors']['department'] = $this->language->get('error_department');
                }
            }

            if (!$shipping_code) {
                $json['errors']['shipping'] = $this->language->get('error_shipping');
            }

            if (!$payment_code) {
                $json['errors']['payment'] = $this->language->get('error_payment');
            }

            if ($this->config->get('config_checkout_id') && !$agree) {
                $json['errors']['agree'] = $this->language->get('error_agree');
            }

            if (!$this->cart->hasProducts()) {
                $json['errors']['cart'] = $this->language->get('error_cart_empty');
            }

            // If no errors, create order
            if (empty($json['errors'])) {
                $this->load->model('checkout/order');

                // Prepare shipping address
                $shipping_address = $city;
                if (strpos($shipping_code, 'doors') !== false) {
                    $shipping_address .= ', ' . $address;
                } else {
                    $shipping_address .= ', ' . $department;
                }

                // Save customer data to session
                $this->session->data['eco_checkout'] = [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'telephone' => $telephone,
                    'email' => $email,
                    'city' => $city,
                    'city_ref' => $city_ref,
                    'department' => $department,
                    'department_ref' => $department_ref,
                    'address' => $address
                ];

                // Prepare order data
                $order_data = [];

                // Customer info
                if ($this->customer->isLogged()) {
                    $order_data['customer_id'] = $this->customer->getId();
                    $order_data['customer_group_id'] = $this->customer->getGroupId();
                } else {
                    $order_data['customer_id'] = 0;
                    $order_data['customer_group_id'] = $this->config->get('config_customer_group_id');
                }

                $order_data['firstname'] = $firstname;
                $order_data['lastname'] = $lastname;
                $order_data['email'] = $email ?: 'guest@' . $this->request->server['HTTP_HOST'];
                $order_data['telephone'] = $telephone;
                $order_data['custom_field'] = [];

                // Payment address
                $order_data['payment_firstname'] = $firstname;
                $order_data['payment_lastname'] = $lastname;
                $order_data['payment_company'] = '';
                $order_data['payment_address_1'] = $shipping_address;
                $order_data['payment_address_2'] = '';
                $order_data['payment_city'] = $city;
                $order_data['payment_postcode'] = '';
                $order_data['payment_zone'] = '';
                $order_data['payment_zone_id'] = 0;
                $order_data['payment_country'] = 'Україна';
                $order_data['payment_country_id'] = $this->config->get('config_country_id');
                $order_data['payment_address_format'] = '';
                $order_data['payment_custom_field'] = [];

                // Shipping address
                $order_data['shipping_firstname'] = $firstname;
                $order_data['shipping_lastname'] = $lastname;
                $order_data['shipping_company'] = '';
                $order_data['shipping_address_1'] = $shipping_address;
                $order_data['shipping_address_2'] = '';
                $order_data['shipping_city'] = $city;
                $order_data['shipping_postcode'] = '';
                $order_data['shipping_zone'] = '';
                $order_data['shipping_zone_id'] = 0;
                $order_data['shipping_country'] = 'Україна';
                $order_data['shipping_country_id'] = $this->config->get('config_country_id');
                $order_data['shipping_address_format'] = '';
                $order_data['shipping_custom_field'] = [];

                // Shipping method
                if (isset($this->session->data['shipping_method'])) {
                    $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                    $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                } else {
                    $order_data['shipping_method'] = '';
                    $order_data['shipping_code'] = $shipping_code;
                }

                // Payment method
                $order_data['payment_method'] = $payment_code == 'liqpay' ? 'LiqPay' : 'Накладений платіж';
                $order_data['payment_code'] = $payment_code;

                // Products
                $order_data['products'] = [];
                foreach ($this->cart->getProducts() as $product) {
                    $order_data['products'][] = [
                        'product_id' => $product['product_id'],
                        'name' => $product['name'],
                        'model' => $product['model'],
                        'option' => $product['option'],
                        'download' => $product['download'],
                        'quantity' => $product['quantity'],
                        'subtract' => $product['subtract'],
                        'price' => $product['price'],
                        'total' => $product['total'],
                        'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
                        'reward' => $product['reward']
                    ];
                }

                // Vouchers (if any)
                $order_data['vouchers'] = [];

                // Totals
                $this->load->model('setting/extension');

                $totals = [];
                $taxes = $this->cart->getTaxes();
                $total = 0;

                $total_data = [
                    'totals' => &$totals,
                    'taxes'  => &$taxes,
                    'total'  => &$total
                ];

                $results = $this->model_setting_extension->getExtensions('total');

                $sort_order = [];
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

                $order_data['totals'] = $totals;
                $order_data['total'] = $total;

                // Comment
                $order_data['comment'] = $comment;

                // Other data
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
                $order_data['marketing_id'] = 0;
                $order_data['tracking'] = '';
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

                // Create order
                $order_id = $this->model_checkout_order->addOrder($order_data);

                $this->session->data['order_id'] = $order_id;

                // Add order history
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'));

                $json['success'] = true;
                $json['order_id'] = $order_id;

                // Redirect based on payment method
                if ($payment_code == 'liqpay') {
                    $json['redirect'] = $this->url->link('checkout/checkout/liqpay', '', true);
                } else {
                    // Clear cart and redirect to success
                    $this->cart->clear();
                    unset($this->session->data['shipping_method']);
                    unset($this->session->data['payment_method']);
                    unset($this->session->data['eco_checkout']);

                    $json['redirect'] = $this->url->link('checkout/success', '', true);
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * LiqPay payment page (API v3)
     */
    public function liqpay() {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if (!$order_info) {
            $this->response->redirect($this->url->link('checkout/cart'));
            return;
        }

        // LiqPay API v3 parameters
        $public_key = $this->config->get('payment_liqpay_merchant');
        $private_key = $this->config->get('payment_liqpay_signature');

        $params = [
            'version' => 3,
            'public_key' => $public_key,
            'action' => 'pay',
            'amount' => round($this->currency->format($order_info['total'], 'UAH', '', false), 2),
            'currency' => 'UAH',
            'description' => 'Замовлення #' . $order_info['order_id'] . ' - ' . $this->config->get('config_name'),
            'order_id' => $order_info['order_id'] . '_' . time(),
            'result_url' => $this->url->link('checkout/checkout/liqpayReturn', '', true),
            'server_url' => $this->url->link('checkout/checkout/liqpayCallback', '', true),
            'language' => 'uk'
        ];

        $data_base64 = base64_encode(json_encode($params));
        $signature = base64_encode(sha1($private_key . $data_base64 . $private_key, true));

        $data['action'] = 'https://www.liqpay.ua/api/3/checkout';
        $data['data'] = $data_base64;
        $data['signature'] = $signature;

        $data['order_id'] = $order_info['order_id'];
        $data['amount'] = $this->currency->format($order_info['total'], 'UAH', '', true);

        $this->load->language('checkout/checkout');
        $data['heading_title'] = $this->language->get('text_liqpay_title');
        $data['text_redirect'] = $this->language->get('text_liqpay_redirect');
        $data['button_pay'] = $this->language->get('button_pay');

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('checkout/checkout_liqpay', $data));
    }

    /**
     * LiqPay callback (server-to-server)
     */
    public function liqpayCallback() {
        if (isset($this->request->post['data']) && isset($this->request->post['signature'])) {
            $data = $this->request->post['data'];
            $signature = $this->request->post['signature'];

            $private_key = $this->config->get('payment_liqpay_signature');
            $expected_signature = base64_encode(sha1($private_key . $data . $private_key, true));

            if ($signature === $expected_signature) {
                $response = json_decode(base64_decode($data), true);

                if ($response && isset($response['order_id']) && isset($response['status'])) {
                    // Extract original order_id (before underscore)
                    $order_id_parts = explode('_', $response['order_id']);
                    $order_id = (int)$order_id_parts[0];

                    $this->load->model('checkout/order');

                    // Check payment status
                    if (in_array($response['status'], ['success', 'sandbox'])) {
                        // Payment successful
                        $this->model_checkout_order->addOrderHistory(
                            $order_id,
                            $this->config->get('payment_liqpay_order_status_id') ?: 2,
                            'LiqPay: ' . $response['status'] . ' (Transaction: ' . ($response['transaction_id'] ?? 'N/A') . ')',
                            true
                        );
                    } elseif (in_array($response['status'], ['failure', 'error'])) {
                        // Payment failed
                        $this->model_checkout_order->addOrderHistory(
                            $order_id,
                            10, // Failed status
                            'LiqPay: Payment failed - ' . ($response['err_description'] ?? $response['status']),
                            false
                        );
                    }
                }
            }
        }

        echo 'OK';
    }

    /**
     * LiqPay return URL (user redirect after payment)
     */
    public function liqpayReturn() {
        if (isset($this->request->post['data'])) {
            $data = $this->request->post['data'];
            $response = json_decode(base64_decode($data), true);

            if ($response && isset($response['status']) && in_array($response['status'], ['success', 'sandbox'])) {
                // Clear cart
                $this->cart->clear();
                unset($this->session->data['shipping_method']);
                unset($this->session->data['payment_method']);
                unset($this->session->data['eco_checkout']);

                $this->response->redirect($this->url->link('checkout/success', '', true));
                return;
            }
        }

        // Payment failed or cancelled
        $this->session->data['error'] = 'Оплата не була завершена. Спробуйте ще раз.';
        $this->response->redirect($this->url->link('checkout/checkout', '', true));
    }

    /**
     * Keep country method for compatibility
     */
    public function country() {
        $json = [];

        $this->load->model('localisation/country');

        $country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

        if ($country_info) {
            $this->load->model('localisation/zone');

            $json = [
                'country_id'        => $country_info['country_id'],
                'name'              => $country_info['name'],
                'iso_code_2'        => $country_info['iso_code_2'],
                'iso_code_3'        => $country_info['iso_code_3'],
                'address_format'    => $country_info['address_format'],
                'postcode_required' => $country_info['postcode_required'],
                'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
                'status'            => $country_info['status']
            ];
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
