<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\AddressFormHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\OrderHookHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\SubscribeHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes\FormShortCode;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class FrontendCore
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.0.1
     *
     * @var ActionFilterLoader maintains and registers all hooks for the plugin
     */
    protected $loader;

    /**
     * Core constructor.
     */
    public function __construct()
    {
        $this->loader = new ActionFilterLoader();

        $seo = new Seo();
        $this->loader->add_filter('woocommerce_structured_data_product', $seo, 'prepareSeo', 10, 2);

        $orderHookHandler = new OrderHookHandler();
        $this->loader->add_action('woocommerce_order_details_after_order_table', $orderHookHandler, 'addOrderStatusLink');
        $this->loader->add_filter(OrderHookHandler::STOREKEEPER_ORDER_TRACK_HOOK, $orderHookHandler, 'createOrderTrackingMessage', 10, 2);

        $this->registerShortCodes();
        $this->registerHandlers();
        $this->loadWooCommerceTemplate();
        $this->registerStyle();
        $this->registerRedirects();
        if ('yes' === StoreKeeperOptions::get(StoreKeeperOptions::VALIDATE_CUSTOMER_ADDRESS, 'yes')) {
            $this->registerAddressFormHandler();
        }
//        $this->loader->add_filter('woocommerce_gallery_image_html_attachment_image_params', $this, 'testImageZoom', 11, 4);
//        $this->loader->add_filter('woocommerce_single_product_image_thumbnail_html', $this, 'testThumbnail', 11, 2);
        if (StoreKeeperOptions::isConnected()) {
            $this->loader->add_filter('wp_get_attachment_url', $this, 'testAttachmentUrl', 999, 2);
            $this->loader->add_filter('woocommerce_gallery_image_html_attachment_image_params', $this, 'testImageSrcSet', 999, 4);
            $this->loader->add_filter('wp_get_attachment_image_src', $this, 'testImageSrc', 999, 2);
            $this->loader->add_filter('wp_generate_attachment_metadata', $this, 'attachmentMetadata', 999, 2);
            $this->loader->add_filter('image_get_intermediate_size', $this, 'overrideSizeAppend', 999, 3);
            $this->loader->add_filter('wp_calculate_image_srcset', $this, 'testImageSrcSet', 999, 5);
        }
    }

    public function attachmentMetadata($data, $postId)
    {
        $s = $data;

        return $data;
    }

    public function overrideSizeAppend($d, $pid, $size)
    {
        $p = get_post($pid);
        $file = $d['file'];
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);

        if (!strpos($file, $api)) {
            $d['file'] = basename($file);
        }

        return $d;
    }

    public function testImageSrc($a, $s)
    {
        $imgUrl = $a[0];
        $pattern = get_site_url();
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);

        preg_match('/\-(?P<width>\d+)x(?P<height>\d+)\.(?P<filetype>[jpeg|png|jpg]+)$/i', $imgUrl, $matches);
        if (isset($matches['width'], $matches['height']) && !empty($width = $matches['width']) && !empty($height = $matches['height'])) {
            $filetype = $matches['filetype'];
            $imgUrl = preg_replace('/\-(?P<width>\d+)x(?P<height>\d+)\.[jpeg|png|jpg]+$/i', '', $imgUrl);
            $imgUrl .= '.'.$filetype.'?img_w='.$width.'&img_h='.$height;
        }

        $a[0] = $imgUrl;

        return $a;
    }

    public function testImageSrcSet($args, $a)
    {
        foreach ($args as &$data) {
            if (is_array($data) && false === strpos($data['url'], 'woocommerce-placeholder')) {
                $uploads_dir = wp_get_upload_dir()['basedir'];
                $partial_uploads_dir = str_replace(ABSPATH, '', $uploads_dir);
                $pattern = get_site_url().'/'.$partial_uploads_dir;
                $data['url'] = preg_replace("#$pattern\/#", '', $data['url']);
            }
        }

        return $args;
    }

    public function testAttachmentUrl($url, $postId)
    {
        $p = get_post($postId);
        $meta = wp_get_attachment_metadata($postId);
        $origUrl = get_post_meta($postId, 'original_url', true);
        if (empty($origUrl)) {
            return $url;
        }

//        preg_match('/\-(?P<width>\d+)x(?P<height>\d+)\.(?P<filetype>[jpeg|png|jpg])+$/i', $origUrl, $matches);
//        if (isset($matches['width'], $matches['height']) && !empty($width = $matches['width']) && !empty($height = $matches['height'])) {
//            $filetype = $matches['filetype'];
//            $origUrl = preg_replace('/\-(?P<width>\d+)x(?P<height>\d+)\.[jpeg|png|jpg]+$/i', '',$origUrl);
//            $origUrl .= $origUrl.$filetype.'?img_w='.$width.'&img_h='.$height;
//        }

        if (!empty($origUrl) && '/' === $origUrl[0]) {
            $origUrl = ltrim($origUrl, '/');
        }
        // Instead of keeping full path we actually need just 'wp-content/uploads'.
        // And we do this the right way, dynamically, calling functions and constants.
        $uploads_dir = wp_get_upload_dir()['basedir'];
        $partial_uploads_dir = str_replace(ABSPATH, '', $uploads_dir);

        // Check if attachment file is in WordPress uploads directory.
        if (false === strpos($url, $partial_uploads_dir)) {
            return $url;
        }

        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        if (false !== strpos($origUrl, $api)) {
            return $origUrl;
        }
//
//        $pattern      = get_site_url();
//        $url          = preg_replace( "#$pattern\/#", $api, $url );

        // Again, just for reference, now the $url looks like:
        // http://cdn-domain.com/wp-content/uploads/2019/03/image.jpg

        return $api.$origUrl;
    }

//    public function testImageZoom($args, $attachmentId, $imageSize, $mainImage)
//    {
//        $s = $args;
//        $args['data-src'] = 'https://storekeeper.nl/wp-content/uploads/2020/12/home-1.png';
//        $args['data-large_image'] = 'https://storekeeper.nl/wp-content/uploads/2020/12/home-1.png';
//
//        return $args;
//    }
//
//    public function testThumbnail($html, $thumbnailId)
//    {
//        $s = $html;
//        $args['data-src'] = 'https://storekeeper.nl/wp-content/uploads/2020/12/home-1.png';
//        $args['data-large_image'] = 'https://storekeeper.nl/wp-content/uploads/2020/12/home-1.png';
//        $html = preg_replace('/data-thumb=".*?"/', 'data-thumb="https://storekeeper.nl/wp-content/uploads/2020/12/home-1.png"', $html);
//
//        return $html;
//    }

    public function run()
    {
        $this->loader->run();
    }

    private function registerAddressFormHandler(): void
    {
        $addressFormHandler = new AddressFormHandler();

        // Form altering and validation
        $this->loader->add_filter('woocommerce_default_address_fields', $addressFormHandler, 'alterAddressForm', 11);
        $this->loader->add_filter('woocommerce_get_country_locale', $addressFormHandler, 'customLocale', 11);
        $this->loader->add_filter('woocommerce_country_locale_field_selectors', $addressFormHandler, 'customSelectors', 11);
        $this->loader->add_action('woocommerce_account_edit-address_endpoint', $addressFormHandler, 'enqueueScriptsAndStyles');
        $this->loader->add_action('woocommerce_after_save_address_validation', $addressFormHandler, 'validateCustomFields', 11, 2);
        $this->loader->add_action('woocommerce_checkout_process', $addressFormHandler, 'validateCustomFieldsForCheckout', 11, 2);
        $this->loader->add_action('woocommerce_checkout_create_order', $addressFormHandler, 'saveCustomFields');
        $this->loader->add_action('woocommerce_before_checkout_form', $addressFormHandler, 'addCheckoutScripts', 11);
    }

    private function registerRedirects()
    {
        $redirectHandler = new RedirectHandler();

        $this->loader->add_action('init', $redirectHandler, 'redirect');
    }

    private function registerShortCodes()
    {
        $this->loader->add_filter('init', new FormShortCode(), 'load');
    }

    private function registerHandlers()
    {
        $subscribeHandler = new SubscribeHandler();
        $this->loader->add_filter('init', $subscribeHandler, 'register');
    }

    private function loadWooCommerceTemplate()
    {
        $this->loader->add_action('after_setup_theme', $this, 'includeTemplateFunctions', 10);
        // Register actions that use global functions.
        add_action('woocommerce_after_shop_loop', 'woocommerce_taxonomy_archive_summary', 100);
        add_action('woocommerce_no_products_found', 'woocommerce_taxonomy_archive_summary', 100);

        // Add the markdown parsers
        add_filter('the_content', 'woocommerce_markdown_description');
        add_filter('woocommerce_short_description', 'woocommerce_markdown_short_description');
    }

    private function registerStyle()
    {
        add_action(
            'wp_enqueue_style',
            function () {
                $style = 'assets/backoffice-sync.css';
                \wp_enqueue_style(
                    STOREKEEPER_WOOCOMMERCE_B2C_NAME.basename($style),
                    plugins_url($style, __FILE__),
                    [],
                    STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                    'all'
                );
            }
        );
    }

    public static function includeTemplateFunctions()
    {
        include_once __DIR__.'/Templates/wc-template-functions.php';
    }
}
