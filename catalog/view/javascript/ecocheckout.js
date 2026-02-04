/**
 * EcoCheckout - JavaScript Controller
 * Handles all client-side interactions for the checkout process
 */
(function($) {
    'use strict';

    var EcoCheckout = {
        urls: {},
        searchTimeout: null,
        currentCityRef: '',

        init: function() {
            var $form = $('#ecocheckout-form');
            if (!$form.length) return;

            // Parse URLs from form data attribute
            this.urls = $form.data('urls') || {};

            // Initialize event handlers
            this.initCitySearch();
            this.initShippingMethods();
            this.initQuantityControls();
            this.initRemoveProduct();
            this.initFormSubmit();

            // Trigger initial shipping method selection
            $('input[name="shipping_code"]:checked').trigger('change');
        },

        /**
         * City autocomplete search
         */
        initCitySearch: function() {
            var self = this;
            var $input = $('#input-city');
            var $suggestions = $('#city-suggestions');
            var $cityRef = $('#input-city-ref');

            // Input handler with debounce
            $input.on('input', function() {
                var query = $(this).val().trim();

                clearTimeout(self.searchTimeout);

                if (query.length < 2) {
                    $suggestions.removeClass('active').empty();
                    return;
                }

                self.searchTimeout = setTimeout(function() {
                    self.searchCities(query);
                }, 300);
            });

            // Focus handler
            $input.on('focus', function() {
                if ($suggestions.children().length > 0) {
                    $suggestions.addClass('active');
                }
            });

            // Click outside to close
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#city-wrapper').length) {
                    $suggestions.removeClass('active');
                }
            });

            // Select city
            $suggestions.on('click', '.city-suggestion', function() {
                var $this = $(this);
                var cityRef = $this.data('ref');
                var cityName = $this.data('city');

                $input.val(cityName);
                $cityRef.val(cityRef);
                $suggestions.removeClass('active');

                self.currentCityRef = cityRef;
                self.loadDepartments(cityRef);
                self.updateShipping();
            });
        },

        /**
         * Search cities via AJAX
         */
        searchCities: function(query) {
            var $suggestions = $('#city-suggestions');

            $suggestions.html('<div class="city-suggestion loading">Пошук...</div>').addClass('active');

            $.ajax({
                url: this.urls.searchCities,
                type: 'GET',
                data: { term: query },
                dataType: 'json',
                success: function(data) {
                    $suggestions.empty();

                    if (data && data.length > 0) {
                        $.each(data, function(i, city) {
                            $suggestions.append(
                                '<div class="city-suggestion" data-ref="' + city.value + '" data-city="' + city.city + '">' +
                                city.label +
                                '</div>'
                            );
                        });
                        $suggestions.addClass('active');
                    } else {
                        $suggestions.html('<div class="city-suggestion">Нічого не знайдено</div>');
                    }
                },
                error: function() {
                    $suggestions.html('<div class="city-suggestion">Помилка пошуку</div>');
                }
            });
        },

        /**
         * Load departments for selected city
         */
        loadDepartments: function(cityRef) {
            var self = this;
            var $select = $('#input-department');
            var $departmentName = $('#input-department-name');
            var shippingType = $('input[name="shipping_code"]:checked').data('type') || 'department';

            // Map type to Nova Poshta type
            var npType = shippingType === 'poshtomat' ? 'poshtomat' : 'department';

            $select.prop('disabled', true).html('<option value="">Завантаження...</option>');

            $.ajax({
                url: self.urls.getDepartments,
                type: 'GET',
                data: {
                    city_ref: cityRef,
                    type: npType
                },
                dataType: 'json',
                success: function(data) {
                    $select.empty();

                    if (data && data.length > 0) {
                        $select.append('<option value="">Оберіть відділення...</option>');
                        $.each(data, function(i, dept) {
                            $select.append(
                                '<option value="' + dept.value + '">' + dept.label + '</option>'
                            );
                        });
                        $select.prop('disabled', false);
                    } else {
                        $select.html('<option value="">Відділення не знайдено</option>');
                    }
                },
                error: function() {
                    $select.html('<option value="">Помилка завантаження</option>');
                }
            });

            // Update department name on change
            $select.off('change.eco').on('change.eco', function() {
                var selectedText = $(this).find('option:selected').text();
                if ($(this).val()) {
                    $departmentName.val(selectedText);
                    self.updateShipping();
                }
            });
        },

        /**
         * Shipping methods handling
         */
        initShippingMethods: function() {
            var self = this;

            $('input[name="shipping_code"]').on('change', function() {
                var type = $(this).data('type');

                // Show/hide address field for courier delivery
                if (type === 'courier') {
                    $('#department-wrapper').hide();
                    $('#address-wrapper').show();
                } else {
                    $('#department-wrapper').show();
                    $('#address-wrapper').hide();

                    // Reload departments if city is selected
                    if (self.currentCityRef) {
                        self.loadDepartments(self.currentCityRef);
                    }
                }

                self.updateShipping();
            });
        },

        /**
         * Update shipping cost
         */
        updateShipping: function() {
            var self = this;
            var shippingCode = $('input[name="shipping_code"]:checked').val();
            var cityRef = $('#input-city-ref').val();
            var city = $('#input-city').val();
            var departmentRef = $('#input-department').val();
            var department = $('#input-department-name').val();

            if (!shippingCode || !cityRef) return;

            $.ajax({
                url: self.urls.updateShipping,
                type: 'POST',
                data: {
                    shipping_code: shippingCode,
                    city_ref: cityRef,
                    city: city,
                    department_ref: departmentRef,
                    department: department
                },
                dataType: 'json',
                success: function(data) {
                    if (data.shipping_cost) {
                        $('[data-shipping="' + shippingCode + '"]').text(data.shipping_cost);
                    }

                    if (data.totals) {
                        self.updateTotals(data.totals);
                    }
                }
            });
        },

        /**
         * Quantity controls (+/-)
         */
        initQuantityControls: function() {
            var self = this;

            // Plus/Minus buttons
            $(document).on('click', '.btn-qty', function() {
                var $btn = $(this);
                var $input = $btn.closest('.input-group').find('.qty-input');
                var currentVal = parseInt($input.val()) || 1;
                var action = $btn.data('action');

                if (action === 'plus') {
                    $input.val(currentVal + 1);
                } else if (action === 'minus' && currentVal > 1) {
                    $input.val(currentVal - 1);
                }

                self.updateCartItem($input);
            });

            // Direct input change
            $(document).on('change', '.qty-input', function() {
                var $input = $(this);
                var val = parseInt($input.val()) || 1;
                if (val < 1) val = 1;
                $input.val(val);
                self.updateCartItem($input);
            });
        },

        /**
         * Update cart item quantity
         */
        updateCartItem: function($input) {
            var self = this;
            var $product = $input.closest('.ecocheckout-product');
            var cartId = $product.data('cart-id');
            var quantity = parseInt($input.val()) || 1;

            $.ajax({
                url: self.urls.updateCart,
                type: 'POST',
                data: {
                    cart_id: cartId,
                    quantity: quantity
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        self.updateProductsHtml(data.products);
                        self.updateTotals(data.totals);
                    }
                }
            });
        },

        /**
         * Remove product from cart
         */
        initRemoveProduct: function() {
            var self = this;

            $(document).on('click', '.btn-remove', function() {
                var $btn = $(this);
                var cartId = $btn.data('cart-id');

                if (!confirm('Видалити товар з кошика?')) return;

                $.ajax({
                    url: self.urls.removeProduct,
                    type: 'POST',
                    data: { cart_id: cartId },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            if (data.cart_empty) {
                                window.location.href = 'index.php?route=checkout/cart';
                                return;
                            }

                            self.updateProductsHtml(data.products);
                            self.updateTotals(data.totals);
                        }
                    }
                });
            });
        },

        /**
         * Update products HTML
         */
        updateProductsHtml: function(products) {
            var html = '';

            $.each(products, function(i, product) {
                var options = '';
                $.each(product.option || [], function(j, opt) {
                    options += '<small class="product-option">' + opt.name + ': ' + opt.value + '</small>';
                });

                html += '<div class="ecocheckout-product" data-cart-id="' + product.cart_id + '">' +
                    '<div class="product-image">' +
                        '<a href="' + product.href + '"><img src="' + product.image + '" alt="' + product.name + '" /></a>' +
                    '</div>' +
                    '<div class="product-info">' +
                        '<a href="' + product.href + '" class="product-name">' + product.name + '</a>' +
                        options +
                        '<div class="product-price">' + product.price + '</div>' +
                    '</div>' +
                    '<div class="product-quantity">' +
                        '<div class="input-group input-group-sm">' +
                            '<span class="input-group-btn">' +
                                '<button type="button" class="btn btn-default btn-qty" data-action="minus">-</button>' +
                            '</span>' +
                            '<input type="text" name="quantity[' + product.cart_id + ']" value="' + product.quantity + '" class="form-control text-center qty-input" />' +
                            '<span class="input-group-btn">' +
                                '<button type="button" class="btn btn-default btn-qty" data-action="plus">+</button>' +
                            '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="product-total">' + product.total + '</div>' +
                    '<button type="button" class="btn btn-danger btn-xs btn-remove" data-cart-id="' + product.cart_id + '" title="Видалити">&times;</button>' +
                '</div>';
            });

            $('#ecocheckout-products').html(html);
        },

        /**
         * Update totals HTML
         */
        updateTotals: function(totals) {
            var html = '<table class="table">';

            $.each(totals, function(i, total) {
                html += '<tr><td class="text-left">' + total.title + '</td><td class="text-right">' + total.text + '</td></tr>';
            });

            html += '</table>';
            $('#ecocheckout-totals').html(html);
        },

        /**
         * Form submission
         */
        initFormSubmit: function() {
            var self = this;

            $('#ecocheckout-form').on('submit', function(e) {
                e.preventDefault();

                // Clear previous errors
                self.clearErrors();

                var $btn = $('#btn-order');
                $btn.prop('disabled', true);
                $btn.find('.btn-text').hide();
                $btn.find('.btn-loading').show();

                $.ajax({
                    url: self.urls.createOrder,
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(data) {
                        if (data.success && data.redirect) {
                            window.location.href = data.redirect;
                        } else if (data.errors) {
                            self.showErrors(data.errors);
                            $btn.prop('disabled', false);
                            $btn.find('.btn-text').show();
                            $btn.find('.btn-loading').hide();

                            // Scroll to first error
                            var $firstError = $('.error-message.active:first');
                            if ($firstError.length) {
                                $('html, body').animate({
                                    scrollTop: $firstError.offset().top - 100
                                }, 500);
                            }
                        }
                    },
                    error: function() {
                        alert('Помилка при оформленні замовлення. Спробуйте ще раз.');
                        $btn.prop('disabled', false);
                        $btn.find('.btn-text').show();
                        $btn.find('.btn-loading').hide();
                    }
                });
            });
        },

        /**
         * Clear all error messages
         */
        clearErrors: function() {
            $('.error-message').removeClass('active').text('');
            $('.form-control').removeClass('error');
        },

        /**
         * Show error messages
         */
        showErrors: function(errors) {
            $.each(errors, function(field, message) {
                var $error = $('[data-error="' + field + '"]');
                $error.text(message).addClass('active');

                // Add error class to input
                var $input = $('[name="' + field + '"]');
                if ($input.length) {
                    $input.addClass('error');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EcoCheckout.init();
    });

})(jQuery);
