<?php

class ControllerExtensionPaymentMono extends Controller
{
    private $error = [];
    private $prefix = '';

    const MONOBANK_PAYMENT_VERSION = '1.0.8';

    public function __construct($registry)
    {
        parent::__construct($registry);

        if(VERSION >= '3.0.0.0') {
            $this->prefix = 'payment_';
        }
    }

    public function install() {
        $this->load->model('extension/payment/mono');
        $this->model_extension_payment_mono->install();
    }

    public function uninstall() {
        $this->load->model('extension/payment/mono');
        $this->model_extension_payment_mono->uninstall();
    }

    public function index()
    {   
        $data = $this->load->language('extension/payment/mono');
        $this->document->setTitle($this->language->get('heading_title'));

        $data['version'] = self::MONOBANK_PAYMENT_VERSION;

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting($this->prefix . 'mono', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');

            if(VERSION < '3.0.0.0') {
                $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true));
            } else {
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
            }
        }

        $errorMessageValues = ["warning", "merchant"];
        foreach ($errorMessageValues as $errorMessageValue)
            $data['error_' . $errorMessageValue] = (isset($this->error[$errorMessageValue])) ? $this->error[$errorMessageValue] : "";

        if(VERSION < '3.0.0.0') {
            $data['action'] = $this->url->link('extension/payment/mono', 'token=' . $this->session->data['token'], true);
            $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);
        } else {
            $data['action'] = $this->url->link('extension/payment/mono', 'user_token=' . $this->session->data['user_token'], true);
            $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        }

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $formInputs = [
            $this->prefix . "mono_status",
            $this->prefix . "mono_merchant",
            $this->prefix . 'mono_geo_zone_id',
            $this->prefix . "mono_sort_order",
            $this->prefix . "mono_order_success_status_id",
            $this->prefix . "mono_order_cancelled_status_id",
            $this->prefix . "mono_order_process_status_id",
            $this->prefix . "mono_destination",
            $this->prefix . "mono_redirect_url",
            $this->prefix . "mono_hold_mode",
        ];

        foreach ($formInputs as $formInput) {
            $data[$formInput] = (isset($this->request->post[$formInput])) ? $this->request->post[$formInput] : $this->config->get($formInput);
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/mono', $data));

        
        
    }
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/mono')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post[$this->prefix . 'mono_merchant']) {
            $this->error['merchant'] = $this->language->get('error_merchant');
        }

        return !$this->error;
    }

    public function order_info(&$route, &$data, &$output) {
        if(VERSION < '3.0.0.0') {
            $prefix = '';
        } else {
            $prefix = 'payment_';
        }
         /* The below block to add hitpay refund tab to the order page */
        $order_id = $this->request->get['order_id'];
        $this->load->model('extension/payment/mono');
        $order = $this->model_extension_payment_mono->getOrder($order_id);

        if ($order) {
            $metaData = $order['InvoiceId'];
            if (!empty($metaData)) {

                $this->load->model('sale/order');
                    $order_info = $this->model_sale_order->getOrder($order_id);
                    $params = $order;
                    
                    /* The below block to add hitpay refund tab to the order page */
                    $tab['title'] = 'Mono Refund';
                    $tab['code'] = 'mono_refund';

                    if(isset($order['is_refunded']) && $order['is_refunded'] == 1){
                        $params['amount_refunded'] = $this->currency->format($order['amount_refunded'], $order_info['currency_code'], $order_info['currency_value']);
                        $params['total_amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
                    }
                    else{
                        $params['is_refunded'] = 0;
                        $params['amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
                    }
                    $params['user_token'] = $this->session->data['user_token'];
                    $params['order_id'] = $order_id;

                    $params['payment_id'] = $order['InvoiceId'];


                    $content = $this->load->view('extension/payment/mono_refund', $params);

                    $tab['content'] = $content;
                    $data['tabs'][] = $tab;
        }
    }
     /* The below block to add hitpay refund tab to the order page end */

     $holdStatus = 0;
     if($holdStatus == 1){
        $params['user_token'] = $this->session->data['user_token'];
        $params['order_id'] = $order_id;
        $params['amount'] = round($order_info['total']*100);

        $params['payment_id'] = $order['InvoiceId'];
        if($order['is_hold'] == 0){
        $tab['title'] = 'Mono Hold';
        $tab['code'] = 'mono_hold';

        $content = $this->load->view('extension/payment/mono_hold', $params);

        $tab['content'] = $content;
        $data['tabs'][] = $tab;
        }
     }
    }

    public function refund()
    {
        if(VERSION < '3.0.0.0') {
            $prefix = '';
        } else {
            $prefix = 'payment_';
        }

        $data = [
            'invoiceId' => $this->request->post['payment_id'],
            'extRef' => (string)$this->request->post['order_id'],
            'amount' => round((int)$this->request->post['mono_amount']*100),
        ];

        
        
        $token = $this->config->get($this->prefix . 'mono_merchant');
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/cancel',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Token: '.$token.''
            ),
        ));

       
        $response = curl_exec($curl);
        curl_close($curl);

        if(!$response) {
            throw new \Exception('No response');
        }

       // $requestData['InvoiceId'] = $response['invoiceId'];
 
        $datainv = $this->request->post['payment_id'];
        $amount_refunded =$this->request->post['mono_amount'];

        $orderId = $this->request->post['order_id'];


        $this->load->model('extension/payment/mono');
        $this->model_extension_payment_mono->addRefund($datainv, $amount_refunded, $response);

       

        $this->model_extension_payment_mono->addRefundHistory($orderId);

        return $response;

    }

    public function hold()
    {
        if(VERSION < '3.0.0.0') {
            $prefix = '';
        } else {
            $prefix = 'payment_';
        }
        $data = [
            'invoiceId' => $this->request->post['payment_id'],
            'amount' => (int)$this->request->post['mono_amount'],
        ];

        $token = $this->config->get($this->prefix . 'mono_merchant');
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/finalize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Token: '.$token.''
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if(!$response) {
            throw new \Exception('No response');
        }
 
        $orderId = $this->request->post['order_id'];


        $this->load->model('extension/payment/mono');

        $this->model_extension_payment_mono->addHoldHistory($orderId);

        return $response;

    }


}