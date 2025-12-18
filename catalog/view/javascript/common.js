function getURLVar(key) {
  var value = [];

  var query = String(document.location).split("?");

  if (query[1]) {
    var part = query[1].split("&");

    for (i = 0; i < part.length; i++) {
      var data = part[i].split("=");

      if (data[0] && data[1]) {
        value[data[0]] = data[1];
      }
    }

    if (value[key]) {
      return value[key];
    } else {
      return "";
    }
  }
}

$(document).ready(function () {
  // Highlight any found errors
  $(".text-danger").each(function () {
    var element = $(this).parent().parent();

    if (element.hasClass("form-group")) {
      element.addClass("has-error");
    }
  });

  // Currency
  $("#form-currency .currency-select").on("click", function (e) {
    e.preventDefault();

    $("#form-currency input[name='code']").val($(this).attr("name"));

    $("#form-currency").submit();
  });

  // Language
  $("#form-language .language-select").on("click", function (e) {
    e.preventDefault();

    $("#form-language input[name='code']").val($(this).attr("name"));

    $("#form-language").submit();
  });

  /* Search */
  $("#searchbox input[name='search']")
    .parent()
    .find("button")
    .on("click", function () {
      var url = $("base").attr("href") + "index.php?route=product/search";

      var value = $("#searchbox input[name='search']").val();

      if (value) {
        url += "&search=" + encodeURIComponent(value);
      }

      var category_id = $("#searchbox select[name='category_id']").prop("value");

      if (category_id > 0) {
        url += "&category_id=" + encodeURIComponent(category_id);
      }

      location = url;
    });

  $("#searchbox input[name='search']").on("keydown", function (e) {
    if (e.keyCode == 13) {
      $("#searchbox input[name='search']").parent().find("button").trigger("click");
    }
  });

  // Product List
  $("#list-view").click(function () {
    $("#content .product-grid > .clearfix").remove();

    $("#content .product-grid").attr("class", "product-layout product-list col-xs-12");
    $("#grid-view").removeClass("active");
    $("#list-view").addClass("active");

    localStorage.setItem("display", "list");
  });

  // Product Grid
  $("#grid-view").click(function () {
    // What a shame bootstrap does not take into account dynamically loaded columns
    var cols = $("#column-right, #column-left").length;

    if (cols == 2) {
      $("#content .product-list").attr("class", "product-layout product-grid col-lg-6 col-md-6 col-sm-12 col-xs-12");
    } else if (cols == 1) {
      $("#content .product-list").attr("class", "product-layout product-grid col-lg-3 col-md-4 col-sm-4 col-xs-6");
    } else {
      $("#content .product-list").attr("class", "product-layout product-grid col-lg-3 col-md-3 col-sm-6 col-xs-12");
    }

    $("#list-view").removeClass("active");
    $("#grid-view").addClass("active");

    localStorage.setItem("display", "grid");
  });

  if (localStorage.getItem("display") == "list") {
    $("#list-view").trigger("click");
    $("#list-view").addClass("active");
  } else {
    $("#grid-view").trigger("click");
    $("#grid-view").addClass("active");
  }

  // Checkout
  $(document).on(
    "keydown",
    "#collapse-checkout-option input[name='email'], #collapse-checkout-option input[name='password']",
    function (e) {
      if (e.keyCode == 13) {
        $("#collapse-checkout-option #button-login").trigger("click");
      }
    },
  );

  // tooltips on hover
  $("[data-toggle='tooltip']").tooltip({ container: "body" });

  // Makes tooltips work on ajax generated content
  $(document).ajaxStop(function () {
    $("[data-toggle='tooltip']").tooltip({ container: "body" });
  });

  // Debug cart clicks
  $(document).on('click', '.cart-dropdown', function(e) {
    e.stopPropagation();
  });

  // Handle cart_button clicks (Add to Cart)
  $(document).on('click', '.cart_button', function(e) {
    e.preventDefault();
    e.stopPropagation();

    var $button = $(this);

    console.log('[DEBUG] Cart button clicked', {
      productId: $button.data('product-id'),
      disabled: $button.prop('disabled'),
      hasLoading: $button.hasClass('loading'),
      timestamp: new Date().getTime()
    });

    // Prevent double clicks
    if ($button.prop('disabled') || $button.hasClass('loading')) {
      console.log('[DEBUG] Button already disabled/loading, returning');
      return false;
    }

    var productId = $button.data('product-id');

    if (!productId) {
      console.error('No product ID found on cart button');
      return;
    }

    // Check if button is "out of stock"
    if ($button.hasClass('out_of_stock')) {
      return;
    }

    // Get quantity if exists (usually on product page)
    var quantity = parseInt($('input[name="quantity"]').val()) || 1;

    console.log('[DEBUG] Sending AJAX request', {
      productId: productId,
      quantity: quantity
    });

    // Get options if exists (for product page with options)
    var options = {};
    $('select[name^="option"], input[name^="option"]:checked, textarea[name^="option"]').each(function() {
      var $this = $(this);
      var name = $this.attr('name');
      var match = name.match(/option\[(\d+)\]/);
      if (match) {
        options[match[1]] = $this.val();
      }
    });

    // Disable button during request
    $button.prop('disabled', true).addClass('loading');

    $.ajax({
      url: 'index.php?route=checkout/cart/add',
      type: 'post',
      data: {
        product_id: productId,
        quantity: quantity,
        option: options
      },
      dataType: 'json',
      success: function(json) {
        console.log('[DEBUG] AJAX response received', json);
        $button.prop('disabled', false).removeClass('loading');

        if (json['error']) {
          if (json['error']['option']) {
            for (var optionId in json['error']['option']) {
              if (typeof showError === 'function') {
                showError(json['error']['option'][optionId], { duration: 5000 });
              }
            }
          }
          if (json['error']['recurring']) {
            if (typeof showError === 'function') {
              showError(json['error']['recurring'], { duration: 5000 });
            }
          }
        }

        if (json['success']) {
          // Try modern notification first, skip alert completely
          if (typeof showSuccess === 'function') {
            showSuccess(json['success'], { duration: 4000, showProgress: true });
          }

          // Update cart total
          if (json['total']) {
            $('#cart-total').html(json['total']);
          }

          // Refresh cart dropdown
          refreshCart();
        }
      },
      error: function(xhr, status, error) {
        $button.prop('disabled', false).removeClass('loading');
        if (typeof showError === 'function') {
          showError('Помилка при додаванні товару в кошик', { duration: 5000 });
        }
        console.error('Cart add error:', error);
      }
    });
  });
});

function refreshCart() {
  $.ajax({
    url: "index.php?route=common/cart/info",
    dataType: "html",
    success: function (html) {
      const $html = $(html);

      const $newTotal = $html.find("#cart-total");
      const $newCart = $html.find("#cart");

      if ($newTotal.length) {
        $("#cart-total").html($newTotal.html());

        // Trigger cart update event for other components to sync
        $(document).trigger('cartUpdated');
      }

      if ($newCart.length) {
        $("#cart").html($newCart.html());
      }
    },
    error: function (xhr) {
      console.error("[refreshCart] AJAX error:", xhr.responseText);
    },
  });
}

var voucher = {
  add: function () {},
  remove: function (key) {
    $.ajax({
      url: "index.php?route=checkout/cart/remove",
      type: "post",
      data: "key=" + key,
      dataType: "json",
      beforeSend: function () {
        $("#cart > button").button("loading");
      },
      complete: function () {
        $("#cart > button").button("reset");
      },
      success: function (json) {
        $("#cart-total").html(json["total"]);

        if (getURLVar("route") == "checkout/cart" || getURLVar("route") == "checkout/checkout") {
          location = "index.php?route=checkout/cart";
        } else {
          $("#cart > ul").load("index.php?route=common/cart/info ul li");
          $("#cart > button").html(
            '<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json["total"] + "</span>",
          );
        }
      },
    });
  },
};

var cart = {
  add: function (product_id, quantity) {
    console.log('[DEBUG] cart.add() called', {
      product_id: product_id,
      quantity: quantity,
      stack: new Error().stack
    });
    $.ajax({
      url: "index.php?route=checkout/cart/add",
      type: "post",
      data: "product_id=" + product_id + "&quantity=" + (typeof quantity != "undefined" ? quantity : 1),
      dataType: "json",
      beforeSend: function () {
        $("#cart > button").button("loading");
      },
      complete: function () {
        $("#cart > button").button("reset");
      },
      success: function (json) {
        $(".alert-dismissible").remove();

        if (json["redirect"]) {
          location = json["redirect"];
        }

        if (json["success"]) {
          // Show beautiful notification
          showSuccess(json["success"], { duration: 4000, showProgress: true });

          // Update cart total
          $("#cart-total").html(json["total"]);

          // Load entire mini-cart HTML into #cart
          refreshCart();

        }
      },
    });
  },

  remove: function (key) {
    $.ajax({
      url: "index.php?route=checkout/cart/remove",
      type: "post",
      data: "key=" + key,
      dataType: "json",
      success: function (json) {
        $("#cart-total").html(json["total"]);

        // Reload mini-cart content
        refreshCart();

      },
    });
  },

  update: function (key, quantity) {
    if (quantity <= 0) {
      cart.remove(key);
      return;
    }
    
    $.ajax({
      url: "index.php?route=common/cart/edit",
      type: "post",
      data: "key=" + key + "&quantity=" + quantity,
      dataType: "json",
      success: function (json) {
        if (json["total"]) {
          $("#cart-total").html(json["total"]);
        }

        // Reload mini-cart content
        refreshCart();
      },
      error: function() {
        console.error("Cart update failed");
      }
    });
  },
};

var wishlist = {
  add: function (product_id) {
    $.ajax({
      url: "index.php?route=account/wishlist/add",
      type: "post",
      data: "product_id=" + product_id,
      dataType: "json",
      success: function (json) {
        if (json["success"]) {
          if (typeof showSuccess === 'function') {
            showSuccess(json["success"], { duration: 4000, showProgress: true });
          }
        }

        if (json["info"]) {
          if (typeof showInfo === 'function') {
            showInfo(json["info"], { duration: 4000, showProgress: true });
          }
        }

        $("#wishlist-total").html(json["total"]);
      },
    });
  },
  remove: function () {},
};

var compare = {
  add: function (product_id) {
    $.ajax({
      url: "index.php?route=product/compare/add",
      type: "post",
      data: "product_id=" + product_id,
      dataType: "json",
      success: function (json) {
        if (json["success"]) {
          if (typeof showSuccess === 'function') {
            showSuccess(json["success"], { duration: 4000, showProgress: true });
          }
          $("#compare-total").html(json["total"]);
        }
      },
    });
  },
  remove: function () {},
};

/* Agree to Terms */
$(document).delegate(".agree", "click", function (e) {
  e.preventDefault();

  $("#modal-agree").remove();

  var element = this;

  $.ajax({
    url: $(element).attr("href"),
    type: "get",
    dataType: "html",
    success: function (data) {
      html = '<div id="modal-agree" class="modal">';
      html += '  <div class="modal-dialog">';
      html += '    <div class="modal-content">';
      html += '      <div class="modal-header">';
      html += '        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>';
      html += '        <h4 class="modal-title">' + $(element).text() + "</h4>";
      html += "      </div>";
      html += '      <div class="modal-body">' + data + "</div>";
      html += "    </div>";
      html += "  </div>";
      html += "</div>";

      $("body").append(html);

      $("#modal-agree").modal("show");
    },
  });
});

// Autocomplete */
(function ($) {
  $.fn.autocomplete = function (option) {
    return this.each(function () {
      this.timer = null;
      this.items = new Array();

      $.extend(this, option);

      $(this).attr("autocomplete", "off");

      // Focus
      $(this).on("focus", function () {
        this.request();
      });

      // Blur
      $(this).on("blur", function () {
        setTimeout(
          function (object) {
            object.hide();
          },
          200,
          this,
        );
      });

      // Keydown
      $(this).on("keydown", function (event) {
        switch (event.keyCode) {
          case 27: // escape
            this.hide();
            break;
          default:
            this.request();
            break;
        }
      });

      // Click
      this.click = function (event) {
        event.preventDefault();

        value = $(event.target).parent().attr("data-value");

        if (value && this.items[value]) {
          this.select(this.items[value]);
        }
      };

      // Show
      this.show = function () {
        var pos = $(this).position();

        $(this)
          .siblings("ul.dropdown-menu")
          .css({
            top: pos.top + $(this).outerHeight(),
            left: pos.left,
          });

        $(this).siblings("ul.dropdown-menu").show();
      };

      // Hide
      this.hide = function () {
        $(this).siblings("ul.dropdown-menu").hide();
      };

      // Request
      this.request = function () {
        clearTimeout(this.timer);

        this.timer = setTimeout(
          function (object) {
            object.source($(object).val(), $.proxy(object.response, object));
          },
          200,
          this,
        );
      };

      // Response
      this.response = function (json) {
        html = "";

        if (json.length) {
          for (i = 0; i < json.length; i++) {
            this.items[json[i]["value"]] = json[i];
          }

          for (i = 0; i < json.length; i++) {
            if (!json[i]["category"]) {
              html += '<li data-value="' + json[i]["value"] + '"><a href="#">' + json[i]["label"] + "</a></li>";
            }
          }

          // Get all the ones with a categories
          var category = new Array();

          for (i = 0; i < json.length; i++) {
            if (json[i]["category"]) {
              if (!category[json[i]["category"]]) {
                category[json[i]["category"]] = new Array();
                category[json[i]["category"]]["name"] = json[i]["category"];
                category[json[i]["category"]]["item"] = new Array();
              }

              category[json[i]["category"]]["item"].push(json[i]);
            }
          }

          for (i in category) {
            html += '<li class="dropdown-header">' + category[i]["name"] + "</li>";

            for (j = 0; j < category[i]["item"].length; j++) {
              html +=
                '<li data-value="' +
                category[i]["item"][j]["value"] +
                '"><a href="#">&nbsp;&nbsp;&nbsp;' +
                category[i]["item"][j]["label"] +
                "</a></li>";
            }
          }
        }

        if (html) {
          this.show();
        } else {
          this.hide();
        }

        $(this).siblings("ul.dropdown-menu").html(html);
      };

      $(this).after('<ul class="dropdown-menu"></ul>');
      $(this).siblings("ul.dropdown-menu").delegate("a", "click", $.proxy(this.click, this));
    });
  };
})(window.jQuery);
