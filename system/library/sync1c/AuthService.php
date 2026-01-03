<?php
/**
 * Authentication Service for 1C Sync
 * Handles all authentication logic including HTTP Basic Auth and session management
 */

class Sync1CAuthService {
    private $registry;
    private $db;
    private $config;
    private $log;
    private $session;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
        $this->log = $registry->get('log');
        $this->session = $registry->get('session');
    }

    /**
     * Perform full authentication check and return session info
     *
     * @return string Response for 1C (success or failure)
     */
    public function checkAuth() {
        $this->log->write('=== AUTH CHECK STARTED ===');
        $this->log->write('Client IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $this->log->write('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

        // Log relevant headers for debugging
        $this->logAuthHeaders();

        // Extract credentials
        $credentials = $this->extractCredentials();

        if (!$credentials) {
            $this->log->write('ERROR: No credentials provided');
            $this->log->write('=== AUTH CHECK FAILED ===');
            return "failure\nNo credentials provided";
        }

        // Log received credentials (username only, never full passwords!)
        $this->log->write("Auth method used: {$credentials['method']}");
        $this->log->write("Received username: " . ($credentials['username'] ?: '(empty)'));
        $this->log->write("Received password: " . (empty($credentials['password']) ? '(empty)' : '(present, length: ' . strlen($credentials['password']) . ')'));

        // Validate credentials
        if (!$this->validateCredentials($credentials['username'], $credentials['password'])) {
            $this->log->write('=== AUTH CHECK FAILED ===');
            return "failure\nInvalid username or password";
        }

        // Create/update session
        $session_id = $this->createAuthSession();

        $this->log->write("SUCCESS: Authentication successful!");
        $this->log->write("Session ID: $session_id");
        $this->log->write("Cookie name: " . session_name());
        $this->log->write('=== AUTH CHECK COMPLETED ===');

        $cookie_name = session_name();
        return "success\n$cookie_name\n$session_id";
    }

    /**
     * Check if current request is authenticated
     *
     * @return bool
     */
    public function isAuthenticated() {
        // Try HTTP Authorization header first (for session-less 1C clients)
        $credentials = $this->extractCredentials();

        if ($credentials && $this->validateCredentials($credentials['username'], $credentials['password'])) {
            $this->log->write('Auth OK: HTTP Authorization header');
            return true;
        }

        // Check session
        if (isset($this->session->data['sync1c_auth']) && $this->session->data['sync1c_auth']) {
            // Check timeout (1 hour)
            if (time() - $this->session->data['sync1c_time'] < 3600) {
                $this->log->write('Auth OK: session valid');
                return true;
            }
        }

        // Authentication failed
        $this->log->write('Auth FAILED: No valid session or credentials');
        return false;
    }

    /**
     * Extract credentials from various sources
     * Returns array with username, password, and method used
     *
     * @return array|null ['username' => string, 'password' => string, 'method' => string]
     */
    private function extractCredentials() {
        $username = '';
        $password = '';
        $method = 'none';

        // Method 1: PHP_AUTH_USER (most common)
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
            $method = 'PHP_AUTH_USER';
        }

        // Method 2: HTTP_AUTHORIZATION (mod_rewrite)
        if (empty($username) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $decoded = $this->decodeBasicAuth($_SERVER['HTTP_AUTHORIZATION']);
            if ($decoded) {
                $username = $decoded['username'];
                $password = $decoded['password'];
                $method = 'HTTP_AUTHORIZATION';
            }
        }

        // Method 3: REDIRECT_HTTP_AUTHORIZATION (CGI mode)
        if (empty($username) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $decoded = $this->decodeBasicAuth($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
            if ($decoded) {
                $username = $decoded['username'];
                $password = $decoded['password'];
                $method = 'REDIRECT_HTTP_AUTHORIZATION';
            }
        }

        // Method 4: getallheaders() fallback
        if (empty($username) && function_exists('getallheaders')) {
            $decoded = $this->extractFromHeaders(getallheaders());
            if ($decoded) {
                $username = $decoded['username'];
                $password = $decoded['password'];
                $method = 'getallheaders()';
            }
        }

        // Method 5: apache_request_headers() fallback
        if (empty($username) && function_exists('apache_request_headers')) {
            $decoded = $this->extractFromHeaders(apache_request_headers());
            if ($decoded) {
                $username = $decoded['username'];
                $password = $decoded['password'];
                $method = 'apache_request_headers()';
            }
        }

        // Method 6: URL-based auth fallback (SECURITY WARNING!)
        // This is insecure and should only be used as last resort
        if (empty($username) && isset($_GET['user'])) {
            $username = $_GET['user'];
            $password = isset($_GET['pass']) ? $_GET['pass'] : '';
            $method = 'URL parameters';

            // Log security warning
            $this->log->write('WARNING: Using URL-based authentication - credentials exposed in logs!');
            $this->log->write('RECOMMENDATION: Configure web server to pass Authorization headers properly');
        }

        if (empty($username)) {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
            'method' => $method
        ];
    }

    /**
     * Decode Basic Auth header
     *
     * @param string $auth Authorization header value
     * @return array|null ['username' => string, 'password' => string]
     */
    private function decodeBasicAuth($auth) {
        if (strpos($auth, 'Basic ') !== 0) {
            return null;
        }

        $decoded = base64_decode(substr($auth, 6));
        if (strpos($decoded, ':') === false) {
            return null;
        }

        list($username, $password) = explode(':', $decoded, 2);
        return ['username' => $username, 'password' => $password];
    }

    /**
     * Extract credentials from headers array
     *
     * @param array $headers Headers array
     * @return array|null ['username' => string, 'password' => string]
     */
    private function extractFromHeaders($headers) {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                return $this->decodeBasicAuth($value);
            }
        }
        return null;
    }

    /**
     * Validate credentials against config
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    private function validateCredentials($username, $password) {
        $config_user = $this->config->get('sync1c_username') ?: 'admin';
        $config_pass = $this->config->get('sync1c_password') ?: '';

        $this->log->write("Expected username: " . ($config_user ?: '(empty)'));
        $this->log->write("Expected password: " . (empty($config_pass) ? '(empty)' : '(present, length: ' . strlen($config_pass) . ')'));

        if ($username !== $config_user || $password !== $config_pass) {
            $this->log->write("ERROR: Auth FAILED - Credentials do not match!");
            if ($username !== $config_user) {
                $this->log->write("  - Username mismatch: '$username' != '$config_user'");
            }
            if ($password !== $config_pass) {
                $this->log->write("  - Password mismatch (lengths: " . strlen($password) . " != " . strlen($config_pass) . ")");
            }
            return false;
        }

        return true;
    }

    /**
     * Create or update authentication session
     *
     * @return string Session ID
     */
    private function createAuthSession() {
        // Session should already be started in sync1c.php
        $session_id = session_id();
        if (empty($session_id)) {
            session_start();
            $session_id = session_id();
        }

        $this->session->data['sync1c_auth'] = true;
        $this->session->data['sync1c_time'] = time();

        return $session_id;
    }

    /**
     * Log authentication-related headers for debugging
     */
    private function logAuthHeaders() {
        $this->log->write('--- Request Headers ---');
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->log->write('HTTP_AUTHORIZATION: present (hidden for security)');
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $this->log->write('REDIRECT_HTTP_AUTHORIZATION: present');
        }
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $this->log->write('PHP_AUTH_USER: present');
        }
    }
}
