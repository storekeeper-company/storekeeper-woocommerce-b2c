<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;

class SegmentPriceProductView
{
    public function __construct()
    {
        add_action('woocommerce_single_product_summary', [$this, 'showSegmentPricesTableView'], 25);
    }

    public function getSegmentPricesForProductView($productId, $customerEmail): mixed
    {
        global $wpdb;
        $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();

        $sql = $wpdb->prepare(
            "
        SELECT sp.* 
        FROM {$segmentPricesTable} sp
        INNER JOIN {$customerSegmentsTable} cs ON sp.customer_segment_id = cs.id
        WHERE sp.product_id = %d AND cs.customer_email = %s
        ",
            $productId, $customerEmail
        );

        return $wpdb->get_results($sql, ARRAY_A);
    }

    public function showSegmentPricesTableView()
    {
        global $post;
        $productId = $post->ID;
        $user = get_current_user_id();
        $userData = get_userdata($user);
        $userEmail = $userData->user_email;
        $customerSegment = new CustomerSegmentModel();
        $customer = $customerSegment->findByEmail($userEmail);
        $product = wc_get_product($productId);

        if ($product->is_type('variable')) {
            echo '<h5>'.__('Customer Segment Prices', I18N::DOMAIN).'</h5>';
            echo '<table class="segment-prices-table" style="width: 50%; border: 1px solid; border-collapse: collapse;">';
            echo '<thead>';
            echo '<tr>';
            echo '<th style="background-color: var(--theme-form-selection-field-active-color); color: white">'.__('From Qty', I18N::DOMAIN).'</th>';
            echo '<th style="background-color: var(--theme-form-selection-field-active-color); color: white">'.__('Price', I18N::DOMAIN).'</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            echo '<tr><td colspan="2" align="center">'.__('Select a variation to view segment prices.', I18N::DOMAIN).'</td></tr>';
            echo '</tbody>';
            echo '</table>';

            $availableVariations = $product->get_available_variations();
            $segmentPricesData = [];

            foreach ($availableVariations as $variation) {
                $variationId = $variation['variation_id'];
                $segmentPrices = self::getSegmentPricesForProductView($variationId, $customer->customer_email);
                if ($segmentPrices) {
                    $segmentPricesData[$variationId] = $segmentPrices;
                }
            }

            $jsUrl = plugins_url('storekeeper-for-woocommerce/resources/js/frontend/price.js');

            wp_enqueue_script('segment-prices-script', $jsUrl, ['jquery'], null, true);
            $selectVariationMessage = __('Select a variation to view segment prices.', I18N::DOMAIN);
            $noSegmentPricesMessage = __('No segment prices available for this product.', I18N::DOMAIN);
            $noSegmentPricesVariationMessage = __('No segment prices available for this variation.', I18N::DOMAIN);

            wp_localize_script('segment-prices-script', 'segmentPricesData',
                [
                    'segmentPricesData' => $segmentPricesData,
                    'selectVariationMessage' => $selectVariationMessage,
                    'noSegmentPricesMessage' => $noSegmentPricesMessage,
                    'noSegmentPricesVariationMessage' => $noSegmentPricesVariationMessage,
                ]);
        } else {
            $segment_prices = self::getSegmentPricesForProductView($productId, $customer->customer_email);

            if ($segment_prices) {
                echo '<h5>'.__('Customer Segment Prices', I18N::DOMAIN).'</h5>';
                echo '<table class="segment-prices-table" style="width: 50%; border: 1px solid; border-collapse: collapse;">';
                echo '<thead>';
                echo '<tr>';
                echo '<th style="background-color: var(--theme-form-selection-field-active-color); color: white">'.__('From Qty', I18N::DOMAIN).'</th>';
                echo '<th style="background-color: var(--theme-form-selection-field-active-color); color:white">'.__('Price', I18N::DOMAIN).'</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($segment_prices as $price) {
                    echo '<tr>';
                    echo '<td align="center">'.esc_html($price['from_qty']).'</td>';
                    echo '<td align="center">'.$price['ppu_wt'].'</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<p>'.__('No segment prices available for this product.', I18N::DOMAIN).'</p>';
            }
        }
    }
}
