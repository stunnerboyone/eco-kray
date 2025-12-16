/**
 * EKO-KRAY Megamenu - Admin JavaScript
 */
(function($) {
    'use strict';

    var EkokrayMegamenu = {
        autocompleteInitialized: false,

        init: function() {
            this.initSortable();
            this.initEventHandlers();
            this.initItemTypeToggle();
            // Note: initCategoryAutocomplete is called when modal opens
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

            // Add item button
            $('#btn-add-item').on('click', function() {
                self.showItemModal();
            });

            // Edit item buttons
            $(document).on('click', '.btn-edit-item', function() {
                var itemId = $(this).data('item-id');
                self.editItem(itemId);
            });

            // Delete item buttons
            $(document).on('click', '.btn-delete-item', function() {
                if (confirm('Are you sure you want to delete this item?')) {
                    var itemId = $(this).data('item-id');
                    self.deleteItem(itemId);
                }
            });

            // Save item button
            $('#btn-save-item').on('click', function() {
                self.saveItem();
            });

            // Show products toggle
            $('#item-show-products').on('change', function() {
                if ($(this).val() == '1') {
                    $('#item-product-limit-group').show();
                } else {
                    $('#item-product-limit-group').hide();
                }
            });
        },

        initItemTypeToggle: function() {
            $('#item-type').on('change', function() {
                var type = $(this).val();

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
            // Only initialize once
            if (this.autocompleteInitialized) {
                return;
            }

            var userToken = $('#user-token').val();

            $('#item-category-autocomplete').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: 'index.php?route=extension/module/ekokray_megamenu/autocompleteCategory&user_token=' + userToken + '&filter_name=' + encodeURIComponent(request.term),
                        dataType: 'json',
                        success: function(json) {
                            response($.map(json, function(item) {
                                return {
                                    label: item.name,
                                    value: item.category_id
                                };
                            }));
                        }
                    });
                },
                select: function(event, ui) {
                    $('#item-category-autocomplete').val(ui.item.label);
                    $('#item-category-id').val(ui.item.value);
                    return false;
                }
            });

            this.autocompleteInitialized = true;
        },

        showItemModal: function(itemData) {
            var self = this;

            // Reset form
            $('#form-item')[0].reset();
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

            // Show modal and initialize autocomplete after it's fully shown
            $('#item-modal').modal('show').one('shown.bs.modal', function() {
                self.initCategoryAutocomplete();
            });
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

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#item-modal').modal('hide');
                        location.reload();
                    } else {
                        if (response.errors) {
                            var errorMsg = '';
                            $.each(response.errors, function(key, value) {
                                errorMsg += value + '\n';
                            });
                            alert(errorMsg);
                        }
                    }
                },
                error: function() {
                    alert('Error saving item');
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
        EkokrayMegamenu.init();
    });

})(jQuery);
