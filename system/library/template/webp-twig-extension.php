<?php
/**
 * Twig Extension for WebP and Lazy Loading Support
 */

class WebPTwigExtension extends \Twig\Extension\AbstractExtension {
    private $image_optimizer;
    private $lazy_loading_enabled;

    public function __construct($image_directory, $lazy_loading = true) {
        require_once(DIR_SYSTEM . 'library/imageoptimizer.php');
        $this->image_optimizer = new ImageOptimizer($image_directory);
        $this->lazy_loading_enabled = $lazy_loading;
    }

    public function getFunctions() {
        return [
            new \Twig\TwigFunction('webp_image', [$this, 'webpImage'], ['is_safe' => ['html']]),
            new \Twig\TwigFunction('lazy_image', [$this, 'lazyImage'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters() {
        return [
            new \Twig\TwigFilter('webp', [$this, 'getWebPPath']),
        ];
    }

    /**
     * Get WebP path if available, otherwise original
     */
    public function getWebPPath($image_path) {
        return $this->image_optimizer->getWebPPath($image_path);
    }

    /**
     * Generate picture element with WebP fallback
     * Usage in Twig: {{ webp_image(image, alt, class) }}
     */
    public function webpImage($image_url, $alt = '', $class = '', $width = '', $height = '') {
        $webp_url = $this->image_optimizer->getWebPPath($image_url);

        $attrs = '';
        if ($class) $attrs .= ' class="' . htmlspecialchars($class) . '"';
        if ($alt) $attrs .= ' alt="' . htmlspecialchars($alt) . '"';
        if ($width) $attrs .= ' width="' . htmlspecialchars($width) . '"';
        if ($height) $attrs .= ' height="' . htmlspecialchars($height) . '"';

        if ($this->lazy_loading_enabled) {
            $attrs .= ' loading="lazy"';
        }

        // If WebP is different from original, use picture element
        if ($webp_url !== $image_url) {
            $html = '<picture>';
            $html .= '<source srcset="' . htmlspecialchars($webp_url) . '" type="image/webp">';
            $html .= '<img src="' . htmlspecialchars($image_url) . '"' . $attrs . '>';
            $html .= '</picture>';
            return $html;
        }

        // Otherwise just return img tag
        return '<img src="' . htmlspecialchars($image_url) . '"' . $attrs . '>';
    }

    /**
     * Generate lazy-loaded image
     * Usage in Twig: {{ lazy_image(image, alt, class) }}
     */
    public function lazyImage($image_url, $alt = '', $class = '', $width = '', $height = '') {
        $attrs = '';
        if ($class) $attrs .= ' class="' . htmlspecialchars($class) . '"';
        if ($alt) $attrs .= ' alt="' . htmlspecialchars($alt) . '"';
        if ($width) $attrs .= ' width="' . htmlspecialchars($width) . '"';
        if ($height) $attrs .= ' height="' . htmlspecialchars($height) . '"';

        $attrs .= ' loading="lazy"';

        return '<img src="' . htmlspecialchars($image_url) . '"' . $attrs . '>';
    }

    public function getName() {
        return 'webp_extension';
    }
}
