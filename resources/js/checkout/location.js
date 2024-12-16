/*! StoreKeeper | StoreKeeper for WooCommerce | https://www.storekeeper.com/ */
jQuery(function($) {
    $('form.checkout').on(
        'change',
        '.storekeeper-locations input[name^="storekeeper\[location\]\[shipping_method\]"]',
        function () {
            $(document.body).trigger('update_checkout');
        }
    );
});
