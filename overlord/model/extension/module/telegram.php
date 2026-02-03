<?php
/**
 * Telegram Notifications Module - Admin Model
 *
 * @package    OpenCart
 * @author     EkoKray
 */
class ModelExtensionModuleTelegram extends Model {
    /**
     * Get notification statistics
     *
     * @return array Statistics
     */
    public function getStats() {
        $stats = array();

        // Count total notifications sent (from log)
        $log_file = DIR_STORAGE . 'logs/telegram.log';

        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $stats['total_sent'] = substr_count($log_content, 'notification sent');
            $stats['total_failed'] = substr_count($log_content, 'Failed to send');
        } else {
            $stats['total_sent'] = 0;
            $stats['total_failed'] = 0;
        }

        return $stats;
    }
}
