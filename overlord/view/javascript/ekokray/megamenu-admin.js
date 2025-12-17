/**
 * EKO-KRAY Megamenu - Admin JavaScript
 * Compatible with OpenCart 3.0.3.8 (jQuery 2.1.1, Bootstrap 3)
 */

// Check if jQuery is loaded
if (typeof jQuery === 'undefined') {
    console.error('EkokrayMegamenu: jQuery is not loaded!');
    alert('ERROR: jQuery is not loaded. Cannot initialize megamenu admin.');
} else {
    console.log('EkokrayMegamenu: jQuery loaded, version:', jQuery.fn.jquery);
}

(function($) {
    'use strict';

    var EkokrayMegamenu = {
        autocompleteInitialized: false,

        init: function() {
            console.log('EkokrayMegamenu: Initializing...');
            console.log('jQuery version:', $.fn.jquery);
            console.log('jQuery UI available:', typeof $.fn.autocomplete !== 'undefined');
            console.log('Bootstrap version:', typeof $.fn.modal !== 'undefined' ? 'available' : 'not available');

            this.initSortable();
            this.initEventHandlers();
            this.initItemTypeToggle();
        },

        initSortable: function() {
            if ($('#menu-items-container').length) {
                $('#menu-items-container').sortable({
                    handle: '.menu-item-handle',
                    placeholder: 'menu-item-placeholder',
                    tolerance: 'pointer',
                    update: function(event, ui) {
                        EkokrayMegamenu.updateSortOrder();
                    }
                });
            }
        },

        initEventHandlers: function() {
            var self = this;
            console.log('EkokrayMegamenu: Initializing event handlers...');

            // Add item button
            var $btnAddItem = $('#btn-add-item');
            console.log('Add item button found:', $btnAddItem.length);

            $btnAddItem.on('click', function(e) {
                console.log('Add item button clicked!');
                e.preventDefault();
                self.showItemModal();
                return false;
            });

            // Edit item buttons
            $(document).on('click', '.btn-edit-item', function(e) {
                e.preventDefault();
                var itemId = $(this).data('item-id');
                console.log('Edit item clicked:', itemId);
                self.editItem(itemId);
            });

            // Delete item buttons
            $(document).on('click', '.btn-delete-item', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this item?')) {
                    var itemId = $(this).data('item-id');
                    console.log('Delete item:', itemId);
                    self.deleteItem(itemId);
                }
            });

            // Save item button - use document delegation since modal might not exist yet
            $(document).on('click', '#btn-save-item', function(e) {
                e.preventDefault();
                console.log('Save item clicked');
                self.saveItem();
            });

            // Show products toggle - use document delegation
            $(document).on('change', '#item-show-products', function() {
                console.log('Show products changed:', $(this).val());
                if ($(this).val() == '1') {
                    $('#item-product-limit-group').show();
                } else {
                    $('#item-product-limit-group').hide();
                }
            });

            console.log('EkokrayMegamenu: Event handlers initialized');
        },

        initItemTypeToggle: function() {
            // Use document delegation since the element is in a modal
            $(document).on('change', '#item-type', function() {
                var type = $(this).val();
                console.log('Item type changed to:', type);

                if (type === 'custom_link') {
                    $('#item-category-group').hide();
                    $('#item-link-group').show();
                } else {
                    $('#item-category-group').show();
                    $('#item-link-group').hide();
                }
            });
        },

        initCategoryAutocomplete: function() {
            console.log('EkokrayMegamenu: initCategoryAutocomplete called');

            // Check if jQuery UI autocomplete is available
            if (typeof $.fn.autocomplete === 'undefined') {
                console.error('EkokrayMegamenu: jQuery UI autocomplete is not available!');
                alert('Error: jQuery UI is not loaded. Please contact administrator.');
                return;
            }

            var $input = $('#item-category-autocomplete');
            if ($input.length === 0) {
                console.error('EkokrayMegamenu: Category autocomplete input not found!');
                return;
            }

            // Destroy existing autocomplete if any
            if ($input.hasClass('ui-autocomplete-input')) {
                console.log('EkokrayMegamenu: Destroying existing autocomplete');
                $input.autocomplete('destroy');
            }

            console.log('EkokrayMegamenu: Initializing autocomplete on input');

            var userToken = $('#user-token').val();

            $input.autocomplete({
                minLength: 0,  // Show results immediately
                source: function(request, response) {
                    console.log('Autocomplete: Searching for:', request.term);
                    $.ajax({
                        url: 'index.php?route=extension/module/ekokray_megamenu/autocompleteCategory&user_token=' + userToken + '&filter_name=' + encodeURIComponent(request.term),
                        dataType: 'json',
                        success: function(json) {
                            console.log('Autocomplete: Results received:', json);
                            response($.map(json, function(item) {
                                return {
                                    label: item.name,
                                    value: item.category_id
                                };
                            }));
                        },
                        error: function(xhr, status, error) {
                            console.error('Autocomplete: Error:', error);
                            response([]);
                        }
                    });
                },
                select: function(event, ui) {
                    console.log('Autocomplete: Selected:', ui.item);
                    $('#item-category-autocomplete').val(ui.item.label);
                    $('#item-category-id').val(ui.item.value);
                    return false;
                }
            });

            // Focus on input to trigger autocomplete
            $input.focus(function() {
                if ($(this).val().length === 0) {
                    $(this).autocomplete('search', '');
                }
            });

            this.autocompleteInitialized = true;
            console.log('EkokrayMegamenu: Autocomplete initialized successfully');
        },

        showItemModal: function(itemData) {
            var self = this;
            console.log('EkokrayMegamenu: showItemModal called', itemData ? 'editing' : 'adding');

            var $modal = $('#item-modal');
            if ($modal.length === 0) {
                console.error('EkokrayMegamenu: Modal not found!');
                alert('Error: Modal not found in the page.');
                return;
            }

            // Reset form
            var $form = $('#form-item');
            if ($form.length && $form[0].reset) {
                $form[0].reset();
            }

            $('#item-id').val('');
            $('#item-parent-id').val('0');
            $('#item-category-autocomplete').val('');
            $('#item-category-id').val('');

            // Set default values
            $('#item-type').val('category').trigger('change');
            $('#item-show-products').val('0').trigger('change');
            $('#item-status').val('1');

            // If editing, populate form
            if (itemData) {
                console.log('EkokrayMegamenu: Populating form with data');
                $('#item-id').val(itemData.item_id);
                $('#item-parent-id').val(itemData.parent_id);
                $('#item-type').val(itemData.item_type).trigger('change');
                $('#item-category-id').val(itemData.category_id);
                $('#item-link').val(itemData.link);
                $('#item-target').val(itemData.target);
                $('#item-show-products').val(itemData.show_products).trigger('change');
                $('#item-product-limit').val(itemData.product_limit);
                $('#item-sort-order').val(itemData.sort_order);
                $('#item-status').val(itemData.status);

                // Populate titles for each language
                if (itemData.descriptions) {
                    $.each(itemData.descriptions, function(langId, desc) {
                        $('#item-title-' + langId).val(desc.title);
                    });
                }
            }

            console.log('EkokrayMegamenu: Showing modal...');

            // Show modal and initialize autocomplete after it's fully shown
            $modal.modal('show');

            // Use both event and timeout to ensure initialization
            $modal.one('shown.bs.modal', function() {
                console.log('EkokrayMegamenu: Modal shown event fired');
                // Use small timeout to ensure DOM is fully ready
                setTimeout(function() {
                    self.initCategoryAutocomplete();
                }, 100);
            });

            // Fallback timeout in case event doesn't fire
            setTimeout(function() {
                if (!self.autocompleteInitialized) {
                    console.log('EkokrayMegamenu: Fallback autocomplete initialization');
                    self.initCategoryAutocomplete();
                }
            }, 500);
        },

        editItem: function(itemId) {
            var self = this;
            var userToken = $('#user-token').val();
            var editUrl = $('#edit-item-url').val();

            $.ajax({
                url: editUrl + '&item_id=' + itemId,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.showItemModal(response.item);
                    } else {
                        alert('Error loading item');
                    }
                },
                error: function() {
                    alert('Error loading item');
                }
            });
        },

        saveItem: function() {
            var formData = $('#form-item').serialize();
            var itemId = $('#item-id').val();
            var userToken = $('#user-token').val();
            var url = itemId ? $('#edit-item-url').val() : $('#add-item-url').val();

            console.log('Saving item...', itemId ? 'Editing ID: ' + itemId : 'Adding new');
            console.log('Form data:', formData);
            console.log('URL:', url);

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Save response:', response);
                    if (response.success) {
                        $('#item-modal').modal('hide');
                        location.reload();
                    } else {
                        var errorMsg = 'Error saving item';
                        if (response.errors) {
                            errorMsg = '';
                            $.each(response.errors, function(key, value) {
                                errorMsg += value + '\n';
                            });
                        } else if (response.error) {
                            errorMsg = response.error;
                        }
                        console.error('Save errors:', errorMsg);
                        alert(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.error('Response:', xhr.responseText);
                    alert('Error saving item: ' + error);
                }
            });
        },

        deleteItem: function(itemId) {
            var userToken = $('#user-token').val();
            var deleteUrl = $('#delete-item-url').val();

            $.ajax({
                url: deleteUrl,
                type: 'POST',
                data: { item_id: itemId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.error || 'Error deleting item');
                    }
                },
                error: function() {
                    alert('Error deleting item');
                }
            });
        },

        updateSortOrder: function() {
            var items = {};
            var order = 0;

            $('#menu-items-container .menu-item').each(function() {
                var itemId = $(this).data('item-id');
                items[itemId] = order++;
            });

            var userToken = $('#user-token').val();
            var updateUrl = $('#update-order-url').val();

            $.ajax({
                url: updateUrl,
                type: 'POST',
                data: { items: items },
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        alert('Error updating sort order');
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('Document ready fired!');
        EkokrayMegamenu.init();
    });

    // Fallback initialization if document.ready doesn't fire
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        console.log('Document already ready, initializing immediately');
        setTimeout(function() {
            EkokrayMegamenu.init();
        }, 1);
    }

})(jQuery);
