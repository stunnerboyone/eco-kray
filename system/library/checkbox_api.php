<?php
/**
 * Checkbox ПРРО API Client
 *
 * Implements integration with Checkbox fiscal receipt service (https://checkbox.ua)
 * Checkbox is a licensed ПРРО (програмний реєстратор розрахункових операцій)
 * required by Ukrainian law for e-commerce.
 *
 * API Docs: https://wiki.checkbox.ua/uk/api
 */
class CheckboxApi {

    const API_URL = 'https://api.checkbox.ua/api/v1';

    /** @var string */
    private $login;

    /** @var string */
    private $password;

    /** @var string|null */
    private $cash_register_id;

    /** @var string|null JWT token after signin */
    private $token;

    /** @var Log */
    private $log;

    /**
     * @param string $login           Cashier email
     * @param string $password        Cashier password
     * @param string $cash_register_id Optional cash register UUID from Checkbox dashboard
     */
    public function __construct($login, $password, $cash_register_id = null) {
        $this->login            = $login;
        $this->password         = $password;
        $this->cash_register_id = $cash_register_id ?: null;
        $this->log              = new Log('checkbox.log');
    }

    // -------------------------------------------------------------------------
    // Auth
    // -------------------------------------------------------------------------

    /**
     * Sign in as cashier and store JWT token.
     *
     * POST /api/v1/cashier/signin
     *
     * @return string Access token
     * @throws Exception on failure
     */
    public function signIn() {
        $response = $this->request('POST', '/cashier/signin', [
            'login'    => $this->login,
            'password' => $this->password,
        ], false);

        if (!empty($response['access_token'])) {
            $this->token = $response['access_token'];
            return $this->token;
        }

        $error = isset($response['message']) ? $response['message'] : json_encode($response);
        throw new Exception('Checkbox signin failed: ' . $error);
    }

    /**
     * Restore previously saved token (e.g. from DB cache).
     *
     * @param string $token
     */
    public function setToken($token) {
        $this->token = $token;
    }

    // -------------------------------------------------------------------------
    // Shifts
    // -------------------------------------------------------------------------

    /**
     * Get current shift for the cashier.
     *
     * GET /api/v1/cashier/shift
     *
     * @return array|null Shift object or null if no open shift
     */
    public function getShift() {
        $response = $this->request('GET', '/cashier/shift');

        if (empty($response) || !empty($response['message'])) {
            return null;
        }

        return $response;
    }

    /**
     * Open a new shift.
     *
     * POST /api/v1/shifts
     *
     * @return array Shift object
     * @throws Exception on failure
     */
    public function openShift() {
        $response = $this->request('POST', '/shifts', []);

        if (empty($response['id'])) {
            $error = isset($response['message']) ? $response['message'] : json_encode($response);
            throw new Exception('Checkbox openShift failed: ' . $error);
        }

        return $response;
    }

    /**
     * Ensure a shift is open. Opens one if needed.
     *
     * @throws Exception
     */
    public function ensureShiftOpen() {
        $shift = $this->getShift();

        if (!$shift || !isset($shift['status']) || $shift['status'] !== 'OPENED') {
            $this->openShift();
        }
    }

    // -------------------------------------------------------------------------
    // Receipts
    // -------------------------------------------------------------------------

    /**
     * Create a sell receipt.
     *
     * POST /api/v1/receipts/sell
     *
     * @param array       $goods       List of goods (see buildGood())
     * @param array       $payments    List of payments (see buildPayment())
     * @param array|null  $discounts   Optional receipt-level discounts
     * @param string|null $fiscal_date ISO8601 date for offline receipts (> 5 min delay)
     *
     * @return array Receipt object from Checkbox
     * @throws Exception
     */
    public function createSellReceipt(array $goods, array $payments, array $discounts = [], $fiscal_date = null) {
        $payload = [
            'goods'    => $goods,
            'payments' => $payments,
        ];

        if (!empty($discounts)) {
            $payload['discounts'] = $discounts;
        }

        // Offline mode: pass fiscal_date if transaction is > 5 minutes old
        if ($fiscal_date) {
            $payload['fiscal_date'] = $fiscal_date;
        }

        $response = $this->request('POST', '/receipts/sell', $payload);

        if (empty($response['id'])) {
            $error = isset($response['message']) ? $response['message'] : json_encode($response);
            throw new Exception('Checkbox createSellReceipt failed: ' . $error);
        }

        return $response;
    }

    /**
     * Get receipt by ID.
     *
     * GET /api/v1/receipts/{receipt_id}
     *
     * @param string $receipt_id UUID
     * @return array
     */
    public function getReceipt($receipt_id) {
        return $this->request('GET', '/receipts/' . rawurlencode($receipt_id));
    }

    /**
     * Get receipt HTML for display.
     *
     * GET /api/v1/receipts/{receipt_id}/html
     *
     * @param string $receipt_id
     * @return string Raw HTML
     */
    public function getReceiptHtml($receipt_id) {
        return $this->requestRaw('GET', '/receipts/' . rawurlencode($receipt_id) . '/html');
    }

    // -------------------------------------------------------------------------
    // Helpers to build payload items
    // -------------------------------------------------------------------------

    /**
     * Build a "good" item for the goods array.
     *
     * @param string $code       Product code / ID
     * @param string $name       Product name
     * @param int    $price      Price per unit in kopiykas (UAH × 100)
     * @param int    $quantity   Quantity × 1000 (1 item = 1000)
     * @param bool   $is_return  true for return receipt
     *
     * @return array
     */
    public static function buildGood($code, $name, $price, $quantity, $is_return = false) {
        return [
            'good' => [
                'code'  => (string)$code,
                'name'  => (string)$name,
                'price' => (int)$price,
            ],
            'quantity'  => (int)$quantity,
            'is_return' => (bool)$is_return,
        ];
    }

    /**
     * Build a payment item.
     *
     * @param int    $value_kopiykas Total amount in kopiykas
     * @param string $type           CASHLESS | CASH | CARD
     *
     * @return array
     */
    public static function buildPayment($value_kopiykas, $type = 'CASHLESS') {
        return [
            'type'  => $type,
            'value' => (int)$value_kopiykas,
        ];
    }

    /**
     * Build a receipt-level discount.
     *
     * @param int    $value_kopiykas Discount amount in kopiykas
     * @param string $type           DISCOUNT | EXTRA_CHARGE
     * @param string $mode           VALUE | PERCENT
     *
     * @return array
     */
    public static function buildDiscount($value_kopiykas, $type = 'DISCOUNT', $mode = 'VALUE') {
        return [
            'type'  => $type,
            'mode'  => $mode,
            'value' => (int)$value_kopiykas,
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    /**
     * Send authenticated JSON request.
     *
     * @param string     $method      GET | POST | PUT
     * @param string     $endpoint    e.g. '/receipts/sell'
     * @param array|null $data        Request body (will be JSON-encoded)
     * @param bool       $auth        Whether to include the auth token header
     *
     * @return array Decoded JSON response
     */
    private function request($method, $endpoint, $data = null, $auth = true) {
        $url = self::API_URL . $endpoint;

        $headers = ['Content-Type: application/json'];

        if ($auth && $this->token) {
            $headers[] = 'X-Access-Token: Bearer ' . $this->token;
        }

        if ($this->cash_register_id) {
            $headers[] = 'X-Cash-Register-Id-Local: ' . $this->cash_register_id;
        }

        $curl = curl_init();

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = ($data !== null) ? json_encode($data) : '{}';
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS]    = ($data !== null) ? json_encode($data) : '{}';
        }

        curl_setopt_array($curl, $options);

        $response  = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $this->log->write('Checkbox cURL error on ' . $method . ' ' . $endpoint . ': ' . $curl_error);
            throw new Exception('Checkbox cURL error: ' . $curl_error);
        }

        $this->log->write('Checkbox API ' . $method . ' ' . $endpoint . ' [HTTP ' . $http_code . ']: ' . $response);

        $decoded = json_decode($response, true);

        if ($decoded === null && $response !== '') {
            // Non-JSON response (shouldn't happen on normal endpoints)
            return ['_raw' => $response, '_http_code' => $http_code];
        }

        return $decoded ?: [];
    }

    /**
     * Send raw request (returns response body as string, e.g. for HTML receipts).
     *
     * @param string $method
     * @param string $endpoint
     *
     * @return string
     */
    private function requestRaw($method, $endpoint) {
        $url = self::API_URL . $endpoint;

        $headers = [];

        if ($this->token) {
            $headers[] = 'X-Access-Token: Bearer ' . $this->token;
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return (string)$response;
    }
}
