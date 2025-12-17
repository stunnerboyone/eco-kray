/**
 * EKO-KRAY Megamenu - Frontend JavaScript
 * Handles desktop hover, mobile drawer, AJAX product loading, and touch gestures
 */
(function($) {
    'use strict';

    var EkokrayMegamenu = function(element, options) {
        this.element = $(element);
        this.options = options;
        console.log('=== EKOKRAY MEGAMENU INIT ===');
        console.log('Element:', element);
        console.log('Options:', options);
        this.init();
    };

    EkokrayMegamenu.prototype = {
        init: function() {
            console.log('Initializing megamenu with options:', this.options);
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
                console.log('=== CATEGORY HOVER DEBUG ===');
                console.log('isMobile:', self.isMobile);
                console.log('Window width:', $(window).width());

                if (self.isMobile) {
                    console.log('Skipping - mobile mode');
                    return;
                }

                var $item = $(this);
                var $productsContainer = $item.find('.ekokray-category-products');
                var categoryId = $productsContainer.data('category-id');
                var loaded = $item.data('products-loaded');

                console.log('Category ID from data attr:', categoryId);
                console.log('Already loaded:', loaded);
                console.log('Container found:', $productsContainer.length);
                console.log('Container element:', $productsContainer);
                console.log('getProductsUrl:', self.options.getProductsUrl);

                if (!categoryId) {
                    console.error('>>> No category ID found!');
                    return;
                }

                if (!loaded && categoryId) {
                    console.log('>>> Loading products for category:', categoryId);
                    // Show loading state
                    $productsContainer.find('.ekokray-products-loading').show();

                    // Load products immediately (no debounce for debugging)
                    console.log('>>> Calling loadProducts NOW');
                    self.loadProducts(categoryId, 8, $productsContainer);
                    $item.data('products-loaded', true);
                } else {
                    console.log('Skipping load - already loaded:', loaded, 'or no categoryId:', categoryId);
                }
            });

            $(document).on('mouseleave', '.ekokray-category-item', function() {
                var $item = $(this);
                var hoverTimeout = $item.data('hover-timeout');
                if (hoverTimeout) {
                    console.log('Clearing hover timeout');
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
            console.log('=== LOAD PRODUCTS ===');
            console.log('Category ID:', categoryId);
            console.log('Limit:', limit);
            console.log('Container:', $container);

            var self = this;
            var $loading = $container.find('.ekokray-products-loading');
            var $grid = $container.find('.ekokray-products-grid');

            console.log('Loading element:', $loading.length);
            console.log('Grid element:', $grid.length);

            // Check if already loaded
            if ($grid.children().length > 0) {
                console.log('>>> Products already loaded, skipping');
                return;
            }

            // Show loading
            $loading.show();

            var ajaxUrl = this.options.getProductsUrl;
            console.log('>>> AJAX URL:', ajaxUrl);
            console.log('>>> Full request URL:', ajaxUrl + '&category_id=' + categoryId + '&limit=' + limit);

            // AJAX request
            $.ajax({
                url: ajaxUrl,
                type: 'GET',
                data: {
                    category_id: categoryId,
                    limit: limit
                },
                dataType: 'json',
                beforeSend: function() {
                    console.log('>>> AJAX request starting...');
                },
                success: function(response) {
                    console.log('>>> AJAX SUCCESS!');
                    console.log('Full Response:', response);
                    console.log('response.success:', response.success);
                    console.log('response.products:', response.products);
                    console.log('products length:', response.products ? response.products.length : 'undefined');

                    // Log debug info from server
                    if (response.debug) {
                        console.log('=== SERVER DEBUG INFO ===');
                        console.log('Debug data:', response.debug);
                        console.log('Category ID:', response.debug.category_id);
                        console.log('Category Name:', response.debug.category_name);
                        console.log('Store ID:', response.debug.store_id);
                        console.log('Language ID:', response.debug.language_id);
                        console.log('Products count:', response.debug.products_count);
                    }

                    if (response.success && response.products && response.products.length > 0) {
                        console.log('>>> Rendering', response.products.length, 'products');
                        self.renderProducts(response.products, $grid);
                    } else {
                        console.error('>>> No products found or empty array!');
                        console.error('Response data:', JSON.stringify(response));
                        $grid.html('<p class="text-muted text-center" style="padding: 20px;">Товари не знайдено в цій категорії</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('>>> AJAX ERROR!');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Status Code:', xhr.status);
                    console.error('Response Text:', xhr.responseText);
                    $grid.html('<p class="text-danger text-center">Помилка завантаження: ' + status + '</p>');
                },
                complete: function() {
                    console.log('>>> AJAX complete');
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
        console.log('=== AUTO-INIT MEGAMENU ===');
        console.log('ekokrayMegamenuData:', window.ekokrayMegamenuData);
        $('[class*="ekokray-megamenu"]').each(function() {
            console.log('Found megamenu element:', this);
            $(this).ekokrayMegamenu();
        });

        // Sync cart count with main cart total
        function syncCartCount() {
            var mainCartTotal = $('#cart-total').text().trim();
            console.log('Syncing cart count:', mainCartTotal);
            $('#ekokray-cart-count').text(mainCartTotal);
            $('#ekokray-cart-count-desktop').text(mainCartTotal);
        }

        // Initial sync
        syncCartCount();

        // Watch for cart updates
        var cartObserver = new MutationObserver(function(mutations) {
            console.log('Cart updated, syncing count');
            syncCartCount();
        });

        var cartElement = document.getElementById('cart-total');
        if (cartElement) {
            cartObserver.observe(cartElement, {
                childList: true,
                characterData: true,
                subtree: true
            });
        }

        // Also sync when cart is refreshed via AJAX
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url && settings.url.indexOf('common/cart') !== -1) {
                console.log('Cart AJAX complete, syncing count');
                setTimeout(syncCartCount, 100);
            }
        });
    });

})(jQuery);
