<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;

class StoreKeeperSeoEditPages
{

    function registerHooks(){

        if( StoreKeeperSeo::isSelectedHandler()){
            add_filter('woocommerce_product_data_tabs', [$this, 'addSeoProductTab']);
            add_action('woocommerce_product_data_panels', [$this, 'renderProductTab']);
            add_action('woocommerce_admin_process_product_object', [$this, 'saveProductSeo']);
            add_action('admin_enqueue_scripts', function (){
                wp_enqueue_style('storekeeper-seo-admin', plugin_dir_url(__FILE__).'../static/seo.css');
            });
        }
    }
    public function addSeoProductTab($tabs): array
    {
        $tabs['storekeeper_seo'] = [
            'label' => __('StoreKeeper Seo', I18N::DOMAIN),
            'target' => 'storekeeper_seo_options',
        ];

        return $tabs;
    }
    public function renderProductTab(): void
    {
        global $post;
        if( $post instanceof \WC_Product ){
            $product = $post;
        } else {
            $product = wc_get_product($post->ID);
        }
        $storekeeper_id = $product->get_meta('storekeeper_id', true, 'edit');

        $seo = \StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo::getProductSeo($product);
        $attributes = [];
        $description = '';
        if( !empty($storekeeper_id)){
            $attributes += ['disabled' => 'disabled'] ;

        }

        ?>
        <div id='storekeeper_seo_options' class='panel woocommerce_options_panel'>
            <?php
            if( !empty($storekeeper_id)){
                echo "<p>";
                echo __('This is managed product, you can only edit this seo information in StoreKeeper Backoffice.', I18N::DOMAIN);
                echo "</p>";
            }
            woocommerce_wp_text_input(
                [
                    'id' => StoreKeeperSeo::SEO_TITLE,
                    'label' => __('Title',I18N::DOMAIN),
                    'value' => $seo[StoreKeeperSeo::SEO_TITLE],
                    'description' => $description,
                    'desc_tip' => true,
                    'custom_attributes' => $attributes,
                ]
            );
            woocommerce_wp_text_input(
                [
                    'id' => StoreKeeperSeo::SEO_KEYWORDS,
                    'label' => __('Keywords',I18N::DOMAIN),
                    'value' => $seo[StoreKeeperSeo::SEO_KEYWORDS],
                    'description' => $description,
                    'desc_tip' => true,
                    'custom_attributes' => $attributes,
                ]
            );
            woocommerce_wp_textarea_input(
                [
                    'id' => StoreKeeperSeo::SEO_DESCRIPTION,
                    'label' => __('Description',I18N::DOMAIN),
                    'value' => $seo[StoreKeeperSeo::SEO_DESCRIPTION],
                    'description' => $description,
                    'desc_tip' => true,
                    'custom_attributes' => $attributes,
                ]
            );
            ?>
        </div>
        <?php
    }

    public function saveProductSeo(\WC_Product $product): void
    {
        $storekeeper_id = $product->get_meta('storekeeper_id', true, 'edit');
        if( empty($storekeeper_id)){
            $title = wc_clean( $_POST[StoreKeeperSeo::SEO_TITLE] );
            $keywords = wc_clean( $_POST[StoreKeeperSeo::SEO_KEYWORDS] );
            $description = wc_clean( $_POST[StoreKeeperSeo::SEO_DESCRIPTION] );

            StoreKeeperSeo::setProductSeo($product,$title,$keywords,$description);
        }
    }
}
