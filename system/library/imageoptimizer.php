<?php
/**
 * Image Optimizer Library
 * Handles WebP conversion and image optimization
 */

class ImageOptimizer {
    private $image_directory;
    private $webp_enabled;

    public function __construct($image_directory) {
        $this->image_directory = rtrim($image_directory, '/') . '/';
        $this->webp_enabled = function_exists('imagewebp');
    }

    /**
     * Get WebP version of image path if it exists
     * @param string $image_path Original image path
     * @return string WebP path or original if WebP doesn't exist
     */
    public function getWebPPath($image_path) {
        if (!$this->webp_enabled) {
            return $image_path;
        }

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $image_path);
        $full_webp_path = $this->image_directory . $webp_path;

        if (file_exists($full_webp_path)) {
            return $webp_path;
        }

        return $image_path;
    }

    /**
     * Convert image to WebP format
     * @param string $source_path Path to source image (relative to image directory)
     * @param int $quality Quality 0-100 (default 85)
     * @return bool|string WebP path on success, false on failure
     */
    public function convertToWebP($source_path, $quality = 85) {
        if (!$this->webp_enabled) {
            return false;
        }

        $source_full = $this->image_directory . $source_path;

        if (!file_exists($source_full)) {
            return false;
        }

        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $source_path);
        $webp_full = $this->image_directory . $webp_path;

        // Skip if WebP already exists and is newer
        if (file_exists($webp_full) && filemtime($webp_full) >= filemtime($source_full)) {
            return $webp_path;
        }

        $image = null;
        $extension = strtolower(pathinfo($source_full, PATHINFO_EXTENSION));

        // Load image based on type
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($source_full);
                break;
            case 'png':
                $image = @imagecreatefrompng($source_full);
                // Preserve transparency
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'gif':
                $image = @imagecreatefromgif($source_full);
                break;
        }

        if (!$image) {
            return false;
        }

        // Create directory if it doesn't exist
        $webp_dir = dirname($webp_full);
        if (!is_dir($webp_dir)) {
            mkdir($webp_dir, 0755, true);
        }

        // Convert to WebP
        $success = imagewebp($image, $webp_full, $quality);
        imagedestroy($image);

        return $success ? $webp_path : false;
    }

    /**
     * Batch convert images in a directory
     * @param string $directory Directory path relative to image directory
     * @param int $quality Quality 0-100
     * @return array Array of converted files
     */
    public function batchConvert($directory = '', $quality = 85) {
        $converted = array();
        $dir_full = $this->image_directory . $directory;

        if (!is_dir($dir_full)) {
            return $converted;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_full, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(jpg|jpeg|png)$/i', $file->getFilename())) {
                $relative_path = str_replace($this->image_directory, '', $file->getPathname());
                $result = $this->convertToWebP($relative_path, $quality);
                if ($result) {
                    $converted[] = $result;
                }
            }
        }

        return $converted;
    }

    /**
     * Check if WebP is supported
     * @return bool
     */
    public function isWebPSupported() {
        return $this->webp_enabled;
    }
}
