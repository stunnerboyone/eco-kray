<?php
/**
 * Rate Limiter for 1C Sync
 * Protects authentication endpoints from brute-force attacks
 */

class Sync1CRateLimiter {
    private $log;
    private $storage_dir;
    private $max_attempts;
    private $time_window;
    private $block_duration;

    /**
     * Constructor
     *
     * @param object $log Logger instance
     * @param string $storage_dir Directory for storing rate limit data
     * @param int $max_attempts Maximum attempts allowed in time window
     * @param int $time_window Time window in seconds (default: 300 = 5 minutes)
     * @param int $block_duration How long to block after exceeding limit (default: 900 = 15 minutes)
     */
    public function __construct($log, $storage_dir, $max_attempts = 5, $time_window = 300, $block_duration = 900) {
        $this->log = $log;
        $this->storage_dir = rtrim($storage_dir, '/') . '/';
        $this->max_attempts = $max_attempts;
        $this->time_window = $time_window;
        $this->block_duration = $block_duration;

        // Create storage directory if not exists
        if (!is_dir($this->storage_dir)) {
            mkdir($this->storage_dir, 0755, true);
        }
    }

    /**
     * Check if IP is allowed to attempt authentication
     *
     * @param string $ip IP address to check
     * @return bool True if allowed, false if rate limited
     */
    public function isAllowed($ip) {
        $data = $this->loadData($ip);

        // Check if currently blocked
        if (isset($data['blocked_until']) && $data['blocked_until'] > time()) {
            $remaining = $data['blocked_until'] - time();
            $this->log->write("RATE LIMIT: IP $ip is blocked for $remaining more seconds");
            return false;
        }

        // Clean up old attempts outside time window
        $data = $this->cleanupOldAttempts($data);

        // Check if within rate limit
        $attempts_count = count($data['attempts']);
        if ($attempts_count >= $this->max_attempts) {
            // Block the IP
            $data['blocked_until'] = time() + $this->block_duration;
            $this->saveData($ip, $data);

            $this->log->write("!!! RATE LIMIT EXCEEDED !!!");
            $this->log->write("IP $ip blocked for {$this->block_duration} seconds");
            $this->log->write("Reason: $attempts_count attempts in {$this->time_window} seconds (max: {$this->max_attempts})");

            return false;
        }

        return true;
    }

    /**
     * Record authentication attempt
     *
     * @param string $ip IP address
     * @param bool $success Whether attempt was successful
     */
    public function recordAttempt($ip, $success = false) {
        $data = $this->loadData($ip);

        // If successful auth, clear all data
        if ($success) {
            $this->log->write("Rate limit: Successful auth from $ip, clearing history");
            $this->clearData($ip);
            return;
        }

        // Clean up old attempts
        $data = $this->cleanupOldAttempts($data);

        // Add new attempt
        $data['attempts'][] = time();

        $attempts_count = count($data['attempts']);
        $remaining = $this->max_attempts - $attempts_count;

        $this->log->write("Rate limit: Failed attempt from $ip (total: $attempts_count, remaining: $remaining)");

        $this->saveData($ip, $data);
    }

    /**
     * Get remaining attempts for IP
     *
     * @param string $ip IP address
     * @return int Remaining attempts
     */
    public function getRemainingAttempts($ip) {
        $data = $this->loadData($ip);
        $data = $this->cleanupOldAttempts($data);
        $attempts_count = count($data['attempts']);
        return max(0, $this->max_attempts - $attempts_count);
    }

    /**
     * Get time until unblock for IP
     *
     * @param string $ip IP address
     * @return int Seconds until unblock (0 if not blocked)
     */
    public function getBlockedTime($ip) {
        $data = $this->loadData($ip);

        if (isset($data['blocked_until']) && $data['blocked_until'] > time()) {
            return $data['blocked_until'] - time();
        }

        return 0;
    }

    /**
     * Manually clear rate limit data for IP
     *
     * @param string $ip IP address
     */
    public function clearData($ip) {
        $file = $this->getFilePath($ip);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Load rate limit data for IP
     *
     * @param string $ip IP address
     * @return array Data structure
     */
    private function loadData($ip) {
        $file = $this->getFilePath($ip);

        if (!file_exists($file)) {
            return ['attempts' => [], 'blocked_until' => 0];
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data || !is_array($data)) {
            return ['attempts' => [], 'blocked_until' => 0];
        }

        return $data;
    }

    /**
     * Save rate limit data for IP
     *
     * @param string $ip IP address
     * @param array $data Data to save
     */
    private function saveData($ip, $data) {
        $file = $this->getFilePath($ip);
        file_put_contents($file, json_encode($data));
    }

    /**
     * Get file path for IP data
     *
     * @param string $ip IP address
     * @return string File path
     */
    private function getFilePath($ip) {
        // Use hash to avoid issues with IPv6 colons in filenames
        $hash = md5($ip);
        return $this->storage_dir . 'ratelimit_' . $hash . '.json';
    }

    /**
     * Remove attempts outside the time window
     *
     * @param array $data Current data
     * @return array Cleaned data
     */
    private function cleanupOldAttempts($data) {
        $cutoff = time() - $this->time_window;

        if (!isset($data['attempts']) || !is_array($data['attempts'])) {
            $data['attempts'] = [];
            return $data;
        }

        $data['attempts'] = array_filter($data['attempts'], function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });

        // Reset array keys
        $data['attempts'] = array_values($data['attempts']);

        return $data;
    }

    /**
     * Clean up old rate limit files
     * Should be called periodically
     */
    public function cleanup() {
        $files = glob($this->storage_dir . 'ratelimit_*.json');
        $cleaned = 0;

        foreach ($files as $file) {
            // Delete files older than block duration + time window
            $max_age = $this->block_duration + $this->time_window;
            if (filemtime($file) < time() - $max_age) {
                unlink($file);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $this->log->write("Rate limiter: Cleaned up $cleaned old files");
        }
    }
}
