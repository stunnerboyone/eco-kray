<?php
/**
 * Checkbox ПРРО — Admin Model
 *
 * Handles DB table creation/removal and event registration.
 */
class ModelExtensionModuleCheckbox extends Model {

    public function install() {
        // Create receipts table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "checkbox_receipts` (
                `id`          INT          NOT NULL AUTO_INCREMENT,
                `order_id`    INT          NOT NULL,
                `receipt_id`  VARCHAR(36)  DEFAULT NULL COMMENT 'UUID from Checkbox',
                `fiscal_code` VARCHAR(64)  DEFAULT NULL COMMENT 'Fiscal number from ДПС',
                `fiscal_date` VARCHAR(32)  DEFAULT NULL,
                `status`      VARCHAR(16)  DEFAULT NULL COMMENT 'CREATED | DONE | ERROR',
                `total`       DECIMAL(15,4) DEFAULT 0.0000,
                `tax_url`     VARCHAR(512) DEFAULT NULL COMMENT 'ДПС verification URL',
                `created_at`  DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                KEY `receipt_id` (`receipt_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        // Register catalog event: fiscalize on order status change
        $this->load->model('setting/event');

        if (!$this->model_setting_event->getEventByCode('module_checkbox_fiscalize')) {
            $this->model_setting_event->addEvent(
                'module_checkbox_fiscalize',
                'catalog/model/checkout/order/addOrderHistory/after',
                'extension/module/checkbox/index'
            );
        }

        // Register admin event: show receipt tab on order page
        if (!$this->model_setting_event->getEventByCode('module_checkbox_order_info')) {
            $this->model_setting_event->addEvent(
                'module_checkbox_order_info',
                'admin/view/sale/order_info/before',
                'extension/module/checkbox/orderInfo'
            );
        }
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "checkbox_receipts`;");

        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('module_checkbox_fiscalize');
        $this->model_setting_event->deleteEventByCode('module_checkbox_order_info');
    }

    /**
     * Get receipt by order ID.
     *
     * @param int $order_id
     * @return array|false
     */
    public function getReceiptByOrderId($order_id) {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "checkbox_receipts`
             WHERE `order_id` = '" . (int)$order_id . "'
             ORDER BY `created_at` DESC
             LIMIT 1"
        );

        return $query->num_rows ? $query->row : false;
    }
}
