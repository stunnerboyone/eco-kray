/**
 * EKO-KRAY Megamenu - Frontend JavaScript
 * Handles desktop hover, mobile drawer, AJAX product loading, and touch gestures
 */
(function($) {
    'use strict';

    var EkokrayMegamenu = function(element, options) {
        this.element = $(element);
        this.options = options;
        this.init();
    };

    EkokrayMegamenu.prototype = {
        init: function() {
            this.setupElements();
            this.setupEvents();
            this.setupResizeHandler();
            this.checkViewport();
        },

        setupElements: function() {
            this.$toggle = $('#ekokray-mobile-toggle');
            this.$overlay = $('#ekokray-mobile-overlay');
            this.$container = this.element.find('.ekokray-menu-container');
            this.$close = $('#ekokray-mobile-close');
            this.$menuItems = this.element.find('.ekokray-menu-item');
            this.isMobile = false;
        },

        setupEvents: function() {
            var self = this;

            // Mobile toggle
            this.$toggle.on('click', function(e) {
                e.preventDefault();
                self.toggleMobileMenu();
            });

            // Mobile close button
            this.$close.on('click', function(e) {
                e.preventDefault();
                self.closeMobileMenu();
            });

            // Overlay click
            this.$overlay.on('click', function() {
                self.closeMobileMenu();
            });

            // Menu item clicks (mobile accordion)
            this.$menuItems.each(function() {
                var $item = $(this);
                var $link = $item.find('> .ekokray-menu-link');
                var $dropdown = $item.find('> .ekokray-dropdown');

                if ($dropdown.length) {
                    $link.on('click', function(e) {
                        if (self.isMobile) {
                            e.preventDefault();
                            self.toggleAccordion($item);
                        }
                    });
                }
            });

            // Desktop hover for product loading on category items
            $(document).on('mouseenter', '.ekokray-category-item', function() {
                if (self.isMobile) return;

                var $item = $(this);
                var $productsContainer = $item.find('.ekokray-category-products');
                var categoryId = $productsContainer.data('category-id');
                var loaded = $item.data('products-loaded');

                if (!loaded && categoryId) {
                    // Show loading state
                    $productsContainer.find('.ekokray-products-loading').show();

                    // Load products with debounce
                    var hoverTimeout = setTimeout(function() {
                        self.loadProducts(categoryId, 8, $productsContainer);
                        $item.data('products-loaded', true);
                    }, 200);

                    $item.data('hover-timeout', hoverTimeout);
                }
            });

            $(document).on('mouseleave', '.ekokray-category-item', function() {
                var $item = $(this);
                var hoverTimeout = $item.data('hover-timeout');
                if (hoverTimeout) {
                    clearTimeout(hoverTimeout);
                }
            });

            // Touch gestures for mobile menu
            this.setupSwipeGestures();

            // Escape key to close mobile menu
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$container.hasClass('active')) {
                    self.closeMobileMenu();
                }
            });
        },

        setupResizeHandler: function() {
            var self = this;
            var resizeTimeout;

            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    self.checkViewport();
                }, 250);
            });
        },

        checkViewport: function() {
            var breakpoint = this.options.mobileBreakpoint || 992;
            var wasMobile = this.isMobile;
            this.isMobile = window.innerWidth < breakpoint;

            // If switched from mobile to desktop, close mobile menu
            if (wasMobile && !this.isMobile) {
                this.closeMobileMenu();
                this.$menuItems.removeClass('active');
            }
        },

        toggleMobileMenu: function() {
            if (this.$container.hasClass('active')) {
                this.closeMobileMenu();
            } else {
                this.openMobileMenu();
            }
        },

        openMobileMenu: function() {
            this.$toggle.addClass('active');
            this.$container.addClass('active');
            this.$overlay.addClass('active');
            $('body').css('overflow', 'hidden'); // Prevent background scrolling
        },

        closeMobileMenu: function() {
            this.$toggle.removeClass('active');
            this.$container.removeClass('active');
            this.$overlay.removeClass('active');
            $('body').css('overflow', '');
        },

        toggleAccordion: function($item) {
            var isActive = $item.hasClass('active');

            // Close all other items at the same level
            $item.siblings('.ekokray-menu-item').removeClass('active');

            // Toggle current item
            if (isActive) {
                $item.removeClass('active');
            } else {
                $item.addClass('active');
            }
        },

        loadProducts: function(categoryId, limit, $container) {
            var self = this;
            var $loading = $container.find('.ekokray-products-loading');
            var $grid = $container.find('.ekokray-products-grid');

            // Check if already loaded
            if ($grid.children().length > 0) {
                return;
            }

            // Show loading
            $loading.show();

            // AJAX request
            $.ajax({
                url: this.options.getProductsUrl,
                type: 'GET',
                data: {
                    category_id: categoryId,
                    limit: limit
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.products) {
                        self.renderProducts(response.products, $grid);
                    } else {
                        $grid.html('<p class="text-muted text-center">Товари не знайдено</p>');
                    }
                },
                error: function() {
                    $grid.html('<p class="text-danger text-center">Помилка завантаження товарів</p>');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        },

        renderProducts: function(products, $grid) {
            var html = '';

            products.forEach(function(product) {
                var priceHtml = '';

                if (product.special) {
                    priceHtml = '<span class="ekokray-product-price-old">' + product.price + '</span>';
                    priceHtml += '<span class="ekokray-product-price">' + product.special + '</span>';
                } else if (product.price) {
                    priceHtml = '<span class="ekokray-product-price">' + product.price + '</span>';
                }

                html += '<div class="ekokray-product-item">';
                html += '  <div class="ekokray-product-image">';
                html += '    <a href="' + product.href + '">';
                html += '      <img src="' + product.image + '" alt="' + product.name + '" loading="lazy">';
                html += '    </a>';
                html += '  </div>';
                html += '  <div class="ekokray-product-name">';
                html += '    <a href="' + product.href + '">' + product.name + '</a>';
                html += '  </div>';
                if (priceHtml) {
                    html += '  <div class="ekokray-product-price-wrapper">' + priceHtml + '</div>';
                }
                html += '</div>';
            });

            $grid.html(html);
        },

        setupSwipeGestures: function() {
            var self = this;
            var startX = 0;
            var currentX = 0;
            var isDragging = false;

            this.$container.on('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            });

            this.$container.on('touchmove', function(e) {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
                var diff = currentX - startX;

                // If swiping left (closing gesture)
                if (diff < -50 && self.$container.hasClass('active')) {
                    self.closeMobileMenu();
                    isDragging = false;
                }
            });

            this.$container.on('touchend', function() {
                isDragging = false;
            });

            // Swipe right on overlay or page to open menu
            this.$overlay.on('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            });

            this.$overlay.on('touchmove', function(e) {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
                var diff = currentX - startX;

                // If swiping right from left edge (opening gesture)
                if (diff > 50 && startX < 50 && !self.$container.hasClass('active')) {
                    self.openMobileMenu();
                    isDragging = false;
                }
            });

            this.$overlay.on('touchend', function() {
                isDragging = false;
            });
        }
    };

    // jQuery plugin
    $.fn.ekokrayMegamenu = function(options) {
        return this.each(function() {
            var $this = $(this);
            var data = $this.data('ekokray.megamenu');

            if (!data) {
                var menuId = $this.attr('id').replace('ekokray-megamenu-', '');
                var config = window.ekokrayMegamenuData && window.ekokrayMegamenuData['menu_' + menuId]
                    ? window.ekokrayMegamenuData['menu_' + menuId]
                    : {};

                var finalOptions = $.extend({}, config, options);

                $this.data('ekokray.megamenu', new EkokrayMegamenu(this, finalOptions));
            }
        });
    };

    // Auto-init
    $(document).ready(function() {
        $('[class*="ekokray-megamenu"]').each(function() {
            $(this).ekokrayMegamenu();
        });
    });

})(jQuery);
