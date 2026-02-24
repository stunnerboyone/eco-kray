<?php
/**
 * Checkbox ПРРО — Catalog Model
 *
 * Handles all database operations for fiscal receipts storage.
 */
class ModelExtensionModuleCheckbox extends Model {

    /**
     * Get the latest receipt for an order.
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

    /**
     * Get a receipt by its Checkbox UUID.
     *
     * @param string $receipt_id UUID returned by Checkbox
     * @return array|false
     */
    public function getReceiptById($receipt_id) {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "checkbox_receipts`
             WHERE `receipt_id` = '" . $this->db->escape($receipt_id) . "'
             LIMIT 1"
        );

        return $query->num_rows ? $query->row : false;
    }

    /**
     * Insert a new receipt record.
     *
     * @param int    $order_id
     * @param string $receipt_id  UUID from Checkbox
     * @param string $status      CREATED | DONE | ERROR
     * @param float  $total       Grand total in UAH
     * @param string $fiscal_code Fiscal number from ДПС (may be empty until DONE)
     * @param string $fiscal_date ISO8601 fiscal date
     * @param string $tax_url     URL to verify on ДПС cabinet
     *
     * @return int Inserted row ID
     */
    public function addReceipt($order_id, $receipt_id, $status, $total, $fiscal_code = '', $fiscal_date = '', $tax_url = '') {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "checkbox_receipts` SET
             `order_id`    = '" . (int)$order_id . "',
             `receipt_id`  = '" . $this->db->escape($receipt_id) . "',
             `status`      = '" . $this->db->escape($status) . "',
             `total`       = '" . (float)$total . "',
             `fiscal_code` = '" . $this->db->escape($fiscal_code) . "',
             `fiscal_date` = '" . $this->db->escape($fiscal_date) . "',
             `tax_url`     = '" . $this->db->escape($tax_url) . "',
             `created_at`  = NOW()"
        );

        return $this->db->getLastId();
    }

    /**
     * Update receipt status and fiscal data after Checkbox processes it.
     *
     * @param string $receipt_id
     * @param string $status
     * @param string $fiscal_code
     * @param string $fiscal_date
     * @param string $tax_url
     */
    public function updateReceipt($receipt_id, $status, $fiscal_code = '', $fiscal_date = '', $tax_url = '') {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "checkbox_receipts` SET
             `status`      = '" . $this->db->escape($status) . "',
             `fiscal_code` = '" . $this->db->escape($fiscal_code) . "',
             `fiscal_date` = '" . $this->db->escape($fiscal_date) . "',
             `tax_url`     = '" . $this->db->escape($tax_url) . "'
             WHERE `receipt_id` = '" . $this->db->escape($receipt_id) . "'"
        );
    }
}
