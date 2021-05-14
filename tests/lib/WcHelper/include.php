<?php

if (!class_exists('WC_Helper_Coupon')) {
    // classes from
    // git clone --branch 3.9.0 --single-branch  https://github.com/woocommerce/woocommerce ~/storekeeper/woocommerce
    // https://github.com/woocommerce/woocommerce/tree/master/tests/framework/helpers
    require __DIR__.'/class-wc-helper-coupon.php';
    require __DIR__.'/class-wc-helper-customer.php';
    require __DIR__.'/class-wc-helper-fee.php';
    require __DIR__.'/class-wc-helper-order.php';
    require __DIR__.'/class-wc-helper-payment-token.php';
    require __DIR__.'/class-wc-helper-product.php';
    require __DIR__.'/class-wc-helper-settings.php';
    require __DIR__.'/class-wc-helper-shipping-zones.php';
    require __DIR__.'/class-wc-helper-shipping.php';
}
