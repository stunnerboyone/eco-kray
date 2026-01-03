<?php
/**
 * SEO URL Generator for 1C Sync
 * Handles SEO-friendly URL generation with transliteration
 */

class Sync1CSeoUrlGenerator {
    private $db;
    private $log;

    public function __construct($db, $log) {
        $this->db = $db;
        $this->log = $log;
    }

    /**
     * Generate SEO URL for product or category
     *
     * @param string $type Type: 'product' or 'category'
     * @param int $id Product or category ID
     * @param string $name Name to generate URL from
     * @return string Generated keyword
     */
    public function generate($type, $id, $name) {
        $this->log->write("--- SEO URL Generation Started ---");
        $this->log->write("Type: $type, ID: $id, Name: $name");

        // Transliterate and clean the name
        $keyword = $this->transliterate($name);
        $this->log->write("After transliteration: $keyword");

        $keyword = strtolower($keyword);
        $this->log->write("After lowercase: $keyword");

        $keyword = preg_replace('/[^a-z0-9\-]/', '-', $keyword);
        $this->log->write("After special chars removal: $keyword");

        $keyword = preg_replace('/-+/', '-', $keyword);
        $keyword = trim($keyword, '-');
        $this->log->write("After cleanup: $keyword");

        // Ensure uniqueness
        $original_keyword = $keyword;
        $keyword = $this->ensureUniqueKeyword($keyword, $type, $id);
        if ($keyword !== $original_keyword) {
            $this->log->write("Keyword modified for uniqueness: $original_keyword -> $keyword");
        }

        // Delete existing SEO URL for this item
        $delete_query = "DELETE FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($type . '_id=' . $id) . "'";
        $this->log->write("Deleting existing SEO URL: $delete_query");
        $this->db->query($delete_query);

        // Insert new SEO URL
        $insert_query = "INSERT INTO " . DB_PREFIX . "seo_url SET
            store_id = '0',
            language_id = '1',
            query = '" . $this->db->escape($type . '_id=' . $id) . "',
            keyword = '" . $this->db->escape($keyword) . "'";

        $this->log->write("Inserting SEO URL: $insert_query");

        try {
            $this->db->query($insert_query);
            $seo_url_id = $this->db->getLastId();
            $this->log->write("SUCCESS: SEO URL created with ID: $seo_url_id");
            $this->log->write("SEO URL: $keyword for $type #$id");
        } catch (Exception $e) {
            $this->log->write("ERROR: Failed to insert SEO URL - " . $e->getMessage());
        }

        $this->log->write("--- SEO URL Generation Completed ---");

        return $keyword;
    }

    /**
     * Transliterate Cyrillic to Latin
     *
     * @param string $text Text to transliterate
     * @return string Transliterated text
     */
    private function transliterate($text) {
        $cyrillic = [
            // Russian
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo', 'ж'=>'zh',
            'з'=>'z', 'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m', 'н'=>'n', 'о'=>'o',
            'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u', 'ф'=>'f', 'х'=>'h', 'ц'=>'ts',
            'ч'=>'ch', 'ш'=>'sh', 'щ'=>'sch', 'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
            'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'Yo', 'Ж'=>'Zh',
            'З'=>'Z', 'И'=>'I', 'Й'=>'Y', 'К'=>'K', 'Л'=>'L', 'М'=>'M', 'Н'=>'N', 'О'=>'O',
            'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T', 'У'=>'U', 'Ф'=>'F', 'Х'=>'H', 'Ц'=>'Ts',
            'Ч'=>'Ch', 'Ш'=>'Sh', 'Щ'=>'Sch', 'Ъ'=>'', 'Ы'=>'Y', 'Ь'=>'', 'Э'=>'E', 'Ю'=>'Yu', 'Я'=>'Ya',
            // Ukrainian
            'і'=>'i', 'ї'=>'yi', 'є'=>'ye', 'ґ'=>'g',
            'І'=>'I', 'Ї'=>'Yi', 'Є'=>'Ye', 'Ґ'=>'G'
        ];

        return strtr($text, $cyrillic);
    }

    /**
     * Ensure keyword is unique by appending number if needed
     *
     * @param string $keyword Base keyword
     * @param string $type Type: 'product' or 'category'
     * @param int $id Product or category ID
     * @return string Unique keyword
     */
    private function ensureUniqueKeyword($keyword, $type, $id) {
        $original_keyword = $keyword;
        $i = 1;

        $this->log->write("Checking uniqueness for keyword: $keyword");

        while (true) {
            // Check if keyword exists for different item
            $check_query = "SELECT * FROM " . DB_PREFIX . "seo_url
                WHERE keyword = '" . $this->db->escape($keyword) . "'
                AND query != '" . $this->db->escape($type . '_id=' . $id) . "'";

            $query = $this->db->query($check_query);

            if (!$query->num_rows) {
                $this->log->write("Keyword is unique: $keyword");
                break; // Keyword is unique
            }

            $this->log->write("Keyword '$keyword' already exists, trying with number suffix");

            // Append number and try again
            $keyword = $original_keyword . '-' . $i;
            $i++;

            // Safety limit to prevent infinite loop
            if ($i > 100) {
                $this->log->write("ERROR: Too many attempts to find unique keyword, using: $keyword");
                break;
            }
        }

        return $keyword;
    }
}
