<?php
/**
 * Image Linker for 1C Sync
 * Automatically links product images from FTP based on product names
 */

class Sync1CImageLinker {
    private $db;
    private $log;
    private $imageDir;
    private $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct($db, $log, $imageDir = 'catalog/products/') {
        $this->db = $db;
        $this->log = $log;
        $this->imageDir = rtrim($imageDir, '/') . '/';
    }

    /**
     * Link image to product based on product name
     *
     * @param int $product_id Product ID
     * @param string $product_name Product name
     * @return bool True if image found and linked
     */
    public function linkImage($product_id, $product_name) {
        $this->log->write("--- Image Linking Started ---");
        $this->log->write("Product ID: $product_id, Name: $product_name");

        // Generate base filename from product name
        $base_filename = $this->generateFilename($product_name);
        $this->log->write("Generated base filename: $base_filename");

        // Find matching image on FTP
        $image_path = $this->findImageOnFtp($base_filename);

        if (!$image_path) {
            $this->log->write("No image found for: $product_name");
            $this->log->write("Expected filename pattern: {$this->imageDir}{$base_filename}.{jpg|jpeg|png|webp}");
            return false;
        }

        // Link image to product
        $this->db->query("UPDATE " . DB_PREFIX . "product SET
            image = '" . $this->db->escape($image_path) . "'
            WHERE product_id = '" . (int)$product_id . "'");

        $this->log->write("SUCCESS: Image linked - $image_path");
        $this->log->write("--- Image Linking Completed ---");

        return true;
    }

    /**
     * Generate filename from product name using transliteration
     *
     * @param string $name Product name
     * @return string Filename (without extension)
     */
    private function generateFilename($name) {
        // Transliterate Cyrillic to Latin
        $filename = $this->transliterate($name);

        // Convert to lowercase
        $filename = mb_strtolower($filename, 'UTF-8');

        // Replace spaces and special chars with hyphens
        $filename = preg_replace('/[^a-z0-9]+/', '-', $filename);

        // Remove multiple hyphens
        $filename = preg_replace('/-+/', '-', $filename);

        // Trim hyphens from start/end
        $filename = trim($filename, '-');

        return $filename;
    }

    /**
     * Find image file on FTP
     * Tries various extensions and pattern matching
     *
     * @param string $base_filename Base filename without extension
     * @return string|null Image path relative to image directory, or null if not found
     */
    private function findImageOnFtp($base_filename) {
        $image_root = DIR_IMAGE;
        $full_path = $image_root . $this->imageDir;

        $this->log->write("Searching in: $full_path");

        // Check if directory exists
        if (!is_dir($full_path)) {
            $this->log->write("ERROR: Image directory does not exist: $full_path");
            $this->log->write("Please create directory: " . $this->imageDir);
            return null;
        }

        // Try exact match with different extensions
        foreach ($this->imageExtensions as $ext) {
            $filename = $base_filename . '.' . $ext;
            $file_path = $full_path . $filename;

            if (file_exists($file_path)) {
                $this->log->write("Found exact match: $filename");
                return $this->imageDir . $filename;
            }
        }

        // Try pattern matching (filename might have suffix like -1, -2, etc.)
        $this->log->write("Exact match not found, trying pattern matching...");

        $files = scandir($full_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Check if file starts with our base filename
            $file_lower = strtolower($file);
            if (strpos($file_lower, $base_filename) === 0) {
                // Verify it has a valid image extension
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), $this->imageExtensions)) {
                    $this->log->write("Found pattern match: $file");
                    return $this->imageDir . $file;
                }
            }
        }

        $this->log->write("No matching image found");
        return null;
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
     * Set custom image directory
     *
     * @param string $dir Directory path relative to DIR_IMAGE
     */
    public function setImageDir($dir) {
        $this->imageDir = rtrim($dir, '/') . '/';
    }

    /**
     * Get current image directory
     *
     * @return string
     */
    public function getImageDir() {
        return $this->imageDir;
    }

    /**
     * Set allowed image extensions
     *
     * @param array $extensions Array of extensions without dot (e.g., ['jpg', 'png'])
     */
    public function setImageExtensions($extensions) {
        $this->imageExtensions = $extensions;
    }

    /**
     * Bulk link images for multiple products
     * Useful for initial setup or re-linking all products
     *
     * @return array Statistics ['linked' => int, 'not_found' => int, 'total' => int]
     */
    public function linkAllProducts() {
        $this->log->write("=== BULK IMAGE LINKING STARTED ===");

        $stats = ['linked' => 0, 'not_found' => 0, 'total' => 0];

        // Get all products with names
        $query = $this->db->query("
            SELECT p.product_id, pd.name
            FROM " . DB_PREFIX . "product p
            LEFT JOIN " . DB_PREFIX . "product_description pd ON p.product_id = pd.product_id
            WHERE pd.language_id = 4
        ");

        $stats['total'] = $query->num_rows;
        $this->log->write("Found {$stats['total']} products to process");

        foreach ($query->rows as $row) {
            if ($this->linkImage($row['product_id'], $row['name'])) {
                $stats['linked']++;
            } else {
                $stats['not_found']++;
            }
        }

        $this->log->write("=== BULK IMAGE LINKING COMPLETED ===");
        $this->log->write("Summary: {$stats['linked']} linked, {$stats['not_found']} not found, {$stats['total']} total");

        return $stats;
    }
}
