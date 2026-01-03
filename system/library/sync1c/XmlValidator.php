<?php
/**
 * XML Validator for 1C Sync
 * Validates CommerceML XML structure and required fields
 */

class Sync1CXmlValidator {
    private $log;
    private $errors = [];

    public function __construct($log) {
        $this->log = $log;
    }

    /**
     * Validate catalog XML structure
     *
     * @param SimpleXMLElement $xml XML to validate
     * @return bool True if valid
     */
    public function validateCatalog($xml) {
        $this->errors = [];
        $this->log->write('=== XML CATALOG VALIDATION STARTED ===');

        // Check root element
        if (!isset($xml->Классификатор) && !isset($xml->Каталог)) {
            $this->addError('Invalid catalog XML: missing Классификатор or Каталог root element');
            return false;
        }

        // Validate categories if present
        if (isset($xml->Классификатор->Группы->Группа)) {
            $this->validateCategories($xml->Классификатор->Группы->Группа);
        }

        // Validate products if present
        if (isset($xml->Каталог->Товары->Товар)) {
            $this->validateProducts($xml->Каталог->Товары->Товар);
        }

        $isValid = empty($this->errors);

        if ($isValid) {
            $this->log->write('✓ XML catalog validation PASSED');
        } else {
            $this->log->write('✗ XML catalog validation FAILED with ' . count($this->errors) . ' errors');
            foreach ($this->errors as $error) {
                $this->log->write('  - ' . $error);
            }
        }

        $this->log->write('=== XML CATALOG VALIDATION COMPLETED ===');
        return $isValid;
    }

    /**
     * Validate offers XML structure
     *
     * @param SimpleXMLElement $xml XML to validate
     * @return bool True if valid
     */
    public function validateOffers($xml) {
        $this->errors = [];
        $this->log->write('=== XML OFFERS VALIDATION STARTED ===');

        // Check root element
        if (!isset($xml->ПакетПредложений)) {
            $this->addError('Invalid offers XML: missing ПакетПредложений root element');
            return false;
        }

        // Check if offers exist
        if (!isset($xml->ПакетПредложений->Предложения->Предложение)) {
            $this->addError('No offers found in XML');
            return false;
        }

        // Validate each offer
        $offerCount = 0;
        foreach ($xml->ПакетПредложений->Предложения->Предложение as $offer) {
            $offerCount++;
            $this->validateOffer($offer, $offerCount);
        }

        $isValid = empty($this->errors);

        if ($isValid) {
            $this->log->write('✓ XML offers validation PASSED (' . $offerCount . ' offers)');
        } else {
            $this->log->write('✗ XML offers validation FAILED with ' . count($this->errors) . ' errors');
            foreach ($this->errors as $error) {
                $this->log->write('  - ' . $error);
            }
        }

        $this->log->write('=== XML OFFERS VALIDATION COMPLETED ===');
        return $isValid;
    }

    /**
     * Validate category structure
     *
     * @param SimpleXMLElement $categories Categories to validate
     */
    private function validateCategories($categories) {
        foreach ($categories as $index => $category) {
            $position = "Category #" . ($index + 1);

            // Check required fields
            if (!isset($category->Ид) || empty((string)$category->Ид)) {
                $this->addError("$position: missing or empty Ід (GUID)");
            }

            if (!isset($category->Наименование) || empty((string)$category->Наименование)) {
                $this->addError("$position: missing or empty Наименование (name)");
            }

            // Validate GUID format
            $guid = (string)$category->Ид;
            if (!empty($guid) && !$this->isValidGuid($guid)) {
                $this->addError("$position: invalid GUID format: $guid");
            }

            // Recursively validate child categories
            if (isset($category->Группы->Группа)) {
                $this->validateCategories($category->Группы->Группа);
            }
        }
    }

    /**
     * Validate product structure
     *
     * @param SimpleXMLElement $products Products to validate
     */
    private function validateProducts($products) {
        foreach ($products as $index => $product) {
            $position = "Product #" . ($index + 1);

            // Check required fields
            if (!isset($product->Ід) || empty((string)$product->Ід)) {
                $this->addError("$position: missing or empty Ід (GUID)");
            }

            if (!isset($product->Наименование) || empty((string)$product->Наименование)) {
                $this->addError("$position: missing or empty Наименование (name)");
            }

            if (!isset($product->Артикул)) {
                $this->addError("$position: missing Артикул (SKU) - will be empty");
            }

            // Validate GUID format
            $guid = (string)$product->Ід;
            if (!empty($guid) && !$this->isValidGuid($guid)) {
                $this->addError("$position: invalid GUID format: $guid");
            }

            // Validate name length
            $name = (string)$product->Наименование;
            if (mb_strlen($name, 'UTF-8') > 255) {
                $this->addError("$position: name too long (" . mb_strlen($name, 'UTF-8') . " chars, max 255)");
            }
        }
    }

    /**
     * Validate offer structure
     *
     * @param SimpleXMLElement $offer Offer to validate
     * @param int $index Offer index
     */
    private function validateOffer($offer, $index) {
        $position = "Offer #$index";

        // Check required fields
        if (!isset($offer->Ід) || empty((string)$offer->Ід)) {
            $this->addError("$position: missing or empty Ід (GUID)");
            return;
        }

        $guid = (string)$offer->Ід;

        // Validate GUID format (can be composite: product#variant)
        $baseGuid = $guid;
        if (strpos($guid, '#') !== false) {
            $baseGuid = explode('#', $guid)[0];
        }

        if (!$this->isValidGuid($baseGuid)) {
            $this->addError("$position (GUID: $guid): invalid GUID format");
        }

        // Validate price if present
        if (isset($offer->Цены->Цена->ЦенаЗаЕдиницу)) {
            $price = (string)$offer->Цены->Цена->ЦенаЗаЕдиницу;
            if (!is_numeric($price)) {
                $this->addError("$position (GUID: $guid): invalid price format: $price");
            } elseif ((float)$price < 0) {
                $this->addError("$position (GUID: $guid): negative price: $price");
            }
        }

        // Validate quantity if present
        if (isset($offer->Количество)) {
            $quantity = (string)$offer->Количество;
            if (!is_numeric($quantity)) {
                $this->addError("$position (GUID: $guid): invalid quantity format: $quantity");
            } elseif ((int)$quantity < 0) {
                $this->addError("$position (GUID: $guid): negative quantity: $quantity");
            }
        }
    }

    /**
     * Validate GUID format
     * Accepts standard GUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     *
     * @param string $guid GUID to validate
     * @return bool True if valid
     */
    private function isValidGuid($guid) {
        // Accept standard GUID format with hyphens
        $pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

        if (preg_match($pattern, $guid)) {
            return true;
        }

        // Also accept GUID without hyphens (32 hex chars)
        $pattern = '/^[a-f0-9]{32}$/i';
        if (preg_match($pattern, $guid)) {
            return true;
        }

        // Accept 1C-style GUIDs (may have different format)
        // Allow any alphanumeric string with hyphens of reasonable length
        if (strlen($guid) >= 32 && strlen($guid) <= 40) {
            return true;
        }

        return false;
    }

    /**
     * Add validation error
     *
     * @param string $error Error message
     */
    private function addError($error) {
        $this->errors[] = $error;
    }

    /**
     * Get all validation errors
     *
     * @return array Array of error messages
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get errors as formatted string
     *
     * @return string Formatted errors
     */
    public function getErrorsAsString() {
        if (empty($this->errors)) {
            return '';
        }

        return "Validation errors:\n" . implode("\n", array_map(function($error) {
            return "  - $error";
        }, $this->errors));
    }
}
