<?php

class ModelExtensionPaymentMono extends Model
{
    const CURRENCY_CODE = ['UAH','EUR','USD'];

    public function getMethod($address, $total)
    {
        if(VERSION < '3.0.0.0') {
            $prefix = '';
        } else {
            $prefix = 'payment_';
        }

        $this->load->language('extension/payment/mono');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get($prefix . 'mono_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if ($this->session->data['currency'] != self::CURRENCY_CODE[0] && $this->session->data['currency'] != self::CURRENCY_CODE[1] && $this->session->data['currency'] != self::CURRENCY_CODE[2])  {
            $status = false;
        } elseif (!$this->config->get('mono_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code' => 'mono',
                'terms' => '',
                'title' => $this->language->get('text_title').'<img src="/catalog/view/theme/default/image/footer_monopay_light_bg.svg"  style="width: 75px;margin-left: 25px;display: inline-block;vertical-align: bottom;"/>',
                'sort_order' => $this->config->get($prefix . 'mono_sort_order')
            ];
        }
        return $method_data;
    }
    public function addOrder($args)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE OrderId = '" . (int)$args['order_id'] . "'");

        if($query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "mono_orders` SET SecretKey = '" . $this->db->escape($args['randKey']) . "', InvoiceId = '" . $this->db->escape($args['InvoiceId']) . "' WHERE OrderId = '" . (int)$args['order_id'] . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "mono_orders` (InvoiceId, OrderId, SecretKey) VALUES('".$args['InvoiceId']."',".$args['order_id'].",'".$args['randKey']."')");
        }
    }

    public function getInvoiceId($OrderId)
    {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE OrderId = '". (int)$OrderId . "'");

        return $q->num_rows ? $q->row : false;
    }
    public function getOrderInfo($InvoiceId){
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "mono_orders` WHERE InvoiceId = '". $this->db->escape($InvoiceId) . "'");

        return $q->num_rows ? $q->row : false;
    }
    public function getCheckoutUrl($requestData)
    {
        $request = $this->sendToAPI($requestData);
        return $request['pageUrl'];
    }

    public function getImageUrl($product_id){
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product` WHERE product_id = ". (int)$product_id . "");

        return $q->num_rows ? $q->row : false;
    }
    public function getEncodedProducts($order_id){
        $this->load->model('checkout/order');

        if(VERSION < '3.0.0.0') {
            $orderProducts = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'")->rows;
        } else {
            $orderProducts = $this->model_checkout_order->getOrderProducts($order_id);
        }

        $orderInfo = $this->model_checkout_order->getOrder($order_id);

        foreach ($orderProducts as $orderProduct){
            $products[] = [
                'name' => $orderProduct['name'],
                'sum' => $this->currency->format($orderProduct['price'], $orderInfo['currency_code'], $orderInfo['currency_value'], false) * 100,
                'qty' => (int)$orderProduct['quantity'],
                'icon' => HTTPS_SERVER . "/image/" . $this->getImageUrl($orderProduct['product_id'])['image']
            ];
        }
        return $products;
    }
    public function sendToAPI($requestData)
    {
        if(VERSION < '3.0.0.0') {
            $prefix = '';
        } else {
            $prefix = 'payment_';
        }

        $redirect_url = $this->config->get($prefix . 'mono_redirect_url');
        $destination = $this->config->get($prefix . 'mono_destination');
        

        $basketOrder =$this->getEncodedProducts($requestData['order_id']);

        $currencyCode = $this->session->data['currency'];

        $currencyDecode = 980;

        if($currencyCode == 'UAH'){
            $currencyDecode = 980;
        }

        if($currencyCode == 'USD'){
            $currencyDecode = 840;
        }

        if($currencyCode == 'EUR'){
            $currencyDecode = 978;
        }

        $holdStatus = $this->config->get($prefix . 'mono_hold_mode');
        if($holdStatus == 1){
            $data = [
                'amount' => $requestData['amount'],
                'ccy' => $currencyDecode,
                'merchantPaymInfo' => [
                    'reference' => (string)$requestData['order_id'],
                    'destination' => $destination,
                    'basketOrder' => $basketOrder,
                ],
                'redirectUrl' => HTTPS_SERVER . $redirect_url,
                'webHookUrl' => str_replace('&amp;', '&', $requestData['server_callback_url']),
                'paymentType' => 'hold',
            ];
        }
        else{
            $data = [
                'amount' => $requestData['amount'],
                'ccy' => $currencyDecode,
                'merchantPaymInfo' => [
                    'reference' => (string)$requestData['order_id'],
                    'destination' => $destination,
                    'basketOrder' => $basketOrder,
                ],
                'redirectUrl' => HTTPS_SERVER . $redirect_url,
                'webHookUrl' => str_replace('&amp;', '&', $requestData['server_callback_url']),
            ];
        }
       

        // var_dump($data);

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.monobank.ua/api/merchant/invoice/create',
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
            'X-Token: '.$requestData['merchant_id'].''
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        if(!$response) {
            throw new \Exception('No response');
        }

        $response = json_decode($response, true);

        if(empty($response['invoiceId'])) {
            throw new \Exception('Invalid response');
        }

        $requestData['InvoiceId'] = $response['invoiceId'];

        $this->addOrder($requestData);
        return $response;
    }
}

?>