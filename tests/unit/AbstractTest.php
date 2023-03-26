<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest;

use Adbar\Dot;
use DateTime;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use StoreKeeper\ApiWrapperDev\DumpFile;
use StoreKeeper\ApiWrapperDev\DumpFile\Reader;
use StoreKeeper\ApiWrapperDev\Wrapper\MockAdapter;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Debug\HookDumpFile;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\WebhookPostEndpoint;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Imports\CouponCodeImport;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\TestLib\DumpFileHelper;
use StoreKeeper\WooCommerce\B2C\TestLib\MediaHelper;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\ProductAttributes;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use WC_Coupon;
use WC_Email_Customer_Processing_Order;
use WC_Email_New_Order;
use WC_Emails;
use WC_Product;
use WP_REST_Request;
use WP_REST_Response;
use WP_UnitTestCase;

abstract class AbstractTest extends WP_UnitTestCase
{
    use ArraySubsetAsserts;

    const UPLOADS_DIRECTORY = '/app/src/wp-content/uploads/';

    // Markdown related constants
    const MARKDOWN_PREFIX = '[sk_markdown]';
    const MARKDOWN_SUFFIX = '[/sk_markdown]';

    // WC product related constants
    const WC_TYPE_SIMPLE = 'simple';
    const WC_TYPE_CONFIGURABLE = 'variable';
    const WC_TYPE_ASSIGNED = 'variation';
    const WC_STATUS_INSTOCK = 'instock';
    const WC_STATUS_OUTOFSTOCK = 'outofstock';
    const WC_BACKORDER_TRUE = 'yes';
    const WC_BACKORDER_NOTIFY = 'notify';
    const WC_BACKORDER_FALSE = 'no';
    const WC_MANAGE_STOCK_PARENT = 'parent';
    const WC_ATTR_OPTION_PREFIX = 'sk__';
    const WC_CONTEXT_EDIT = 'edit';

    // StoreKeeper product related constants
    const SK_TYPE_SIMPLE = 'simple';
    const SK_TYPE_CONFIGURABLE = 'configurable';
    const SK_TYPE_ASSIGNED = 'configurable_assign';

    /**
     * @var Reader
     */
    protected $reader;

    protected $api_url;
    protected $db;

    public function setUp()
    {
        parent::setUp();
        do_action('activate_woocommerce');
        StoreKeeperApi::$mockAdapter = new MockAdapter();
        $this->reader = DumpFileHelper::getReader();

        $this->db = new DatabaseConnection();

        $this->disableWooCommerceEmails();
    }

    public function tearDown()
    {
        parent::tearDown();

        StoreKeeperApi::$mockAdapter = null;
        $this->reader = null;
        $this->clearWPUploadsDirectory();
        $this->clearNonSystemTaxonomies();
        $this->clearAttributeTaxonomies();
    }

    protected function disableWooCommerceEmails()
    {
        // Remove broken emailing actions
        remove_action(
            'woocommerce_order_status_pending_to_processing_notification',
            [WC_Email_Customer_Processing_Order::class, 'trigger']
        );
        remove_action(
            'woocommerce_order_status_pending_to_processing_notification',
            [WC_Email_New_Order::class, 'trigger']
        );

        $actions = [
            'woocommerce_low_stock',
            'woocommerce_no_stock',
            'woocommerce_product_on_backorder',
            'woocommerce_order_status_pending_to_processing',
            'woocommerce_order_status_pending_to_completed',
            'woocommerce_order_status_processing_to_cancelled',
            'woocommerce_order_status_pending_to_failed',
            'woocommerce_order_status_pending_to_on-hold',
            'woocommerce_order_status_failed_to_processing',
            'woocommerce_order_status_failed_to_completed',
            'woocommerce_order_status_failed_to_on-hold',
            'woocommerce_order_status_cancelled_to_processing',
            'woocommerce_order_status_cancelled_to_completed',
            'woocommerce_order_status_cancelled_to_on-hold',
            'woocommerce_order_status_on-hold_to_processing',
            'woocommerce_order_status_on-hold_to_cancelled',
            'woocommerce_order_status_on-hold_to_failed',
            'woocommerce_order_status_completed',
            'woocommerce_order_fully_refunded',
            'woocommerce_order_partially_refunded',
            'woocommerce_new_customer_note',
            'woocommerce_created_customer',
        ];

        foreach ($actions as $action) {
            remove_action($action, [WC_Emails::class, 'queue_transactional_email']);
            remove_action($action, [WC_Emails::class, 'send_transactional_email']);
        }
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }

    /**
     * @param string $dir directory within ./tests/data/
     *
     * @throws \Exception
     */
    public function mockApiCallsFromDirectory(string $dir, bool $matchParams = true)
    {
        $dir = $this->getDataDir().$dir.'/';

        $files = scandir($dir);
        // filter ending with .json
        $files = array_filter(
            $files,
            function ($v) {
                return strpos($v, '.json') === strlen($v) - 5;
            }
        );
        if (empty($files)) {
            $this->markTestSkipped("No dump files in $dir");
        }
        StoreKeeperApi::$mockAdapter->registerDumpFiles($files, $dir, $matchParams, $this->reader);
    }

    public function mockMediaFromDirectory($dir)
    {
        $dir = $this->getDataDir().$dir.'/';

        if (empty(scandir($dir))) {
            $this->markTestSkipped("No media files in $dir");
        }

        MediaHelper::$mockDirectory = $dir;
    }

    public function getDataDir(): string
    {
        return __DIR__.'/../data/';
    }

    /**
     * @param $filename
     */
    public function getHookDataDump($filename): HookDumpFile
    {
        $dir = $this->getDataDir();
        $reader = DumpFileHelper::getReader();

        return $reader->read($dir.$filename);
    }

    protected function handleRequest(WP_REST_Request $rest): WP_REST_Response
    {
        $endpoint = new WebhookPostEndpoint();
        $result = $endpoint->handleRequest($rest);
        $status = $result->get_status();
        if (200 !== $status) {
            throw $endpoint->getLastError();
        }

        return $result;
    }

    /**
     * @throws \Throwable
     */
    protected function initApiConnection(string $syncMode = StoreKeeperOptions::SYNC_MODE_FULL_SYNC): void
    {
        $file = $this->getHookDataDump('hook.init.json');
        $rest = $this->getRestWithToken($file);
        $this->assertEquals('init', $file->getHookAction());
        $this->handleRequest($rest);

        $rest_data = new Dot(json_decode($rest->get_body(), true));
        $this->api_url = $rest_data->get('payload.api_url');

        // set to fullsync, because it's order only by default
        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, $syncMode);
    }

    protected function getRestWithToken(HookDumpFile $file): WP_REST_Request
    {
        $shopToken = WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_TOKEN);
        if (empty($shopToken)) {
            WooCommerceOptions::resetToken();
            $shopToken = WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_TOKEN);
        }

        return $file->getRestRequest(
            function ($k, $v) use ($shopToken) {
                if ('headers' === $k) {
                    $v['upxhooktoken'] = $shopToken;
                }

                return $v;
            }
        );
    }

    /**
     * @param $filename
     */
    public function getDataDump($filename): DumpFile
    {
        $dir = $this->getDataDir();
        $reader = DumpFileHelper::getReader();

        return $reader->read($dir.$filename);
    }

    /**
     * @throws \Exception
     */
    protected function clearWPUploadsDirectory()
    {
        // https://stackoverflow.com/a/3349792 : Copied option two and added a is_dir check.
        $dir = __DIR__.'/../../../../uploads/'.(new DateTime())->format('Y');
        if (is_dir($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator(
                $it,
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Clear all the non system taxonomies after running the tests.
     */
    protected function clearNonSystemTaxonomies()
    {
        $wp_taxonomies = get_taxonomies();
        foreach ($wp_taxonomies as $wp_taxonomy) {
            $featuredAttributes = FeaturedAttributes::ALL_FEATURED_ALIASES;
            /* @since 9.0.6 */
            // needs_description_on_kassa length is > 25 so it's
            // imported as needs_description_on_kass
            $featuredAttributes[] = 'needs_description_on_kass';

            if (in_array($wp_taxonomy, $featuredAttributes, true) || 'pa_' === substr($wp_taxonomy, 0, strlen('pa_'))) {
                unregister_taxonomy($wp_taxonomy);
            }
        }
    }

    protected function clearAttributeTaxonomies()
    {
        $attributeTaxonomies = wc_get_attribute_taxonomies();
        foreach ($attributeTaxonomies as $attributeTaxonomy) {
            unregister_taxonomy($attributeTaxonomy->attribute_name);
            wc_delete_attribute($attributeTaxonomy->attribute_id);
        }
    }

    public function assertFileUrls($expected_file_url, $current_file_url, $isCdn = false)
    {
        // Check file name
        $expected_file_name = $this->getUrlBasename($expected_file_url);
        $current_file_name = $this->getUrlBasename($current_file_url);
        $this->assertEquals(
            $expected_file_name,
            $current_file_name,
            'Image file name does not matches'
        );

        if (!$isCdn) {
            // Compare MD5 Hash
            $expected_file_md5 = $this->getUrlMd5($expected_file_url);
            $current_file_md5 = $this->getUrlMd5($current_file_url);
            $this->assertEquals(
                $expected_file_md5,
                $current_file_md5,
                'Image file MD5 does not matches'
            );
        }
    }

    /**
     * @param $file_url
     */
    private function getUrlBasename($file_url): string
    {
        return basename(parse_url($file_url)['path']);
    }

    /**
     * @param $file_url
     *
     * @return false|string
     */
    private function getUrlMd5($file_url)
    {
        $file = Media::downloadFile($file_url);
        if (false === $file) {
            throw new RuntimeException("Unable to get from from\n-Url:$file_url");
        }

        return md5($file['body']);
    }

    /**
     * @param $original_product_data
     * @param $product_type
     *
     * @return array
     */
    protected function getProductsByTypeFromDataDump($original_product_data, $product_type)
    {
        return array_filter(
            $original_product_data,
            function ($k) use ($product_type) {
                return $k['flat_product']['product']['type'] === $product_type;
            }
        );
    }

    protected function assertCouponCode(Dot $expected, WC_Coupon $actual)
    {
        $this->assertEquals(
            $expected->get('code'),
            $actual->get_code(),
            'Coupon code does not match'
        );

        $code = $actual->get_code();

        $this->assertEquals(
            $expected->get('title'),
            $actual->get_description(),
            "[$code] Coupon description does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getCouponType($expected),
            $actual->get_discount_type(),
            "[$code] Coupon type does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getCouponAmount($expected),
            $actual->get_amount(),
            "[$code] Coupon amount does not matches"
        );

        $this->assertEquals(
            $expected->get('free_shipping', false),
            $actual->get_free_shipping(),
            "[$code] Coupon free shipping does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getCouponExpireDate($expected),
            CouponCodeImport::getCouponCodeDateFormatted($actual->get_date_expires()),
            "[$code] Coupon date expires does not matches"
        );

        $this->assertEquals(
            $expected->get('min_order_value_wt'),
            $actual->get_minimum_amount(),
            "[$code] Coupon minimum amount does not matches"
        );

        $this->assertEquals(
            $expected->get('max_order_value_wt'),
            $actual->get_maximum_amount(),
            "[$code] Coupon maximum amount does not matches"
        );

        $this->assertEquals(
            !$expected->get('allow_with_other_codes', true),
            $actual->get_individual_use(),
            "[$code] Coupon individual use does not matches"
        );

        $this->assertEquals(
            !$expected->get('allow_on_discounted_products', false),
            $actual->get_exclude_sale_items(),
            "[$code] Coupon exclude sale items does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getCouponIncludedCategoryIds($expected),
            $actual->get_product_categories(),
            "[$code] Coupon product categories does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getCouponExcludedCategoryIds($expected),
            $actual->get_excluded_product_categories(),
            "[$code] Coupon excluded product categories does not matches"
        );

        $this->assertEquals(
            CouponCodeImport::getSanitizedFilteredEmails($expected->get('allowed_emails', [])),
            $actual->get_email_restrictions(),
            "[$code] Coupon email restrictions does not matches"
        );

        $this->assertEquals(
            $expected->get('times_it_can_be_used_per_shop'),
            $actual->get_usage_limit(),
            "[$code] Coupon usage limit does not matches"
        );

        $this->assertEquals(
            $expected->get('times_per_customer_per_shop'),
            $actual->get_usage_limit_per_user(),
            "[$code] Coupon usage limit per user does not matches"
        );
    }

    public function assertProduct(Dot $original_product, WC_Product $wc_product)
    {
        $sku = $wc_product->get_sku();

        // SKU
        $expected_sku = $original_product->get('flat_product.product.sku');
        $this->assertEquals(
            $expected_sku,
            $sku,
            "[sku=$sku] WooCommerce product sku doesn't match the expected product sku"
        );

        // Title and slug are not set for assigned products, it's set for both simple and configurable products
        if (self::WC_TYPE_ASSIGNED !== $wc_product->get_type()) {
            // Title
            $expected_title = $original_product->get('flat_product.title');
            $this->assertEquals(
                $expected_title,
                $wc_product->get_title(),
                "[sku=$sku] WooCommerce product title doens\'t match the expected product title"
            );

            // Slug
            $expected_slug = $original_product->get('flat_product.slug');
            $this->assertEquals(
                $expected_slug,
                $wc_product->get_slug(),
                "[sku=$sku] WooCommerce product slug doesn't match the expected product slug"
            );
        }

        $got_seo = StoreKeeperSeo::getProductSeo($wc_product);
        $expected_seo = [
            StoreKeeperSeo::SEO_TITLE => $original_product->get('flat_product.seo_title') ?? '',
            StoreKeeperSeo::SEO_DESCRIPTION => $original_product->get('flat_product.seo_description') ?? '',
            StoreKeeperSeo::SEO_KEYWORDS => $original_product->get('flat_product.seo_keywords') ?? '',
        ];
        $this->assertArraySubset($expected_seo, $got_seo, 'Seo sku='.$sku);

        // Product description
        $expected_description = $original_product->get('flat_product.body');
        if (empty($expected_description)) {
            $expected_description = '';
        } else {
            $expected_description = self::MARKDOWN_PREFIX.$expected_description.self::MARKDOWN_SUFFIX;
        }
        $this->assertEquals(
            $expected_description,
            $wc_product->get_description(),
            "[sku=$sku] WooCommerce product description doesn't match the expected product description"
        );

        // Product summary is not set for assigned products. it's set for both simple and configurable products
        if (self::WC_TYPE_ASSIGNED !== $wc_product->get_type()) {
            // Product summary
            $expected_summary = $original_product->get('flat_product.summary');
            if (empty($expected_summary)) {
                $expected_summary = '';
            } else {
                $expected_summary = self::MARKDOWN_PREFIX.$expected_summary.self::MARKDOWN_SUFFIX;
            }
            $this->assertEquals(
                $expected_summary,
                $wc_product->get_short_description(),
                "[sku=$sku] WooCommerce product summary doesn't match the expected product summary"
            );
        }

        $this->assertProductStock($original_product, $wc_product, $sku);
        $this->assertProductPrices($original_product, $wc_product, $sku);

        // Backorder
        $expected_backorder = $original_product->get('backorder_enabled') ?
            self::WC_BACKORDER_TRUE : self::WC_BACKORDER_FALSE;
        // When backorder is enabled and the notify on backorder is set, the status should be 'notify'
        if (StoreKeeperOptions::get(StoreKeeperOptions::NOTIFY_ON_BACKORDER, false) &&
            $original_product->get('backorder_enabled')) {
            $expected_backorder = self::WC_BACKORDER_NOTIFY;
        }
        $this->assertEquals(
            $expected_backorder,
            $wc_product->get_backorders(),
            "[sku=$sku] WooCommerce backorder status doesn't match the expected backorder status"
        );

        // Images : Assigned products take the images of their parents
        if (self::WC_TYPE_ASSIGNED !== $wc_product->get_type()) {
            // Main image
            if ($original_product->has('flat_product.main_image.cdn_url') && StoreKeeperOptions::isImageCdnEnabled()) {
                $cdnUrl = $original_product->get('flat_product.main_image.cdn_url');
                $currentUrl = get_post_meta($wc_product->get_image_id(), 'original_url', true);
                $currentCdnUrl = get_post_meta($wc_product->get_image_id(), 'cdn_url', true);
                $this->assertEquals(urlencode($cdnUrl), $currentCdnUrl, 'CDN url does not match');
                $this->assertFileUrls(Media::fixUrl($cdnUrl), Media::fixUrl($currentUrl), true);
            } elseif ($original_product->has('flat_product.product.content.big_image_url')) {
                $original_url = $original_product->get('flat_product.product.content.big_image_url');
                $current_url = get_post_meta($wc_product->get_image_id(), 'original_url', true);
                $this->assertFileUrls(Media::fixUrl($original_url), Media::fixUrl($current_url));
            }

            // Additional images
            if ($original_product->has('flat_product.product_images')) {
                $originalAdditionalImages = $original_product->get('flat_product.product_images');
                foreach ($originalAdditionalImages as $originalAdditionalImage) {
                    if (isset($originalAdditionalImage['cdn_url']) && StoreKeeperOptions::isImageCdnEnabled()) {
                        $cdnUrl = $originalAdditionalImage['cdn_url'];
                        $encodedCdnUrl = urlencode($cdnUrl);
                        $current = Media::getAttachmentByCdnUrl($encodedCdnUrl);
                        $currentUrl = get_post_meta($current->ID, 'original_url', true);
                        $currentCdnUrl = get_post_meta($current->ID, 'cdn_url', true);
                        $this->assertEquals($encodedCdnUrl, $currentCdnUrl, 'CDN url does not match');
                        $this->assertFileUrls(Media::fixUrl($cdnUrl), Media::fixUrl($currentUrl), true);
                    } else {
                        $bigImageUrl = $originalAdditionalImage['big_url'];
                        $originalUrl = Media::fixUrl($bigImageUrl);
                        $current = Media::getAttachment($bigImageUrl);
                        $currentUrl = get_post_meta($current->ID, 'original_url', true);
                        $currentUrl = Media::fixUrl($currentUrl);
                        $this->assertFileUrls($originalUrl, $currentUrl);
                    }
                }
            }
        }

        // Attributes
        $isAssigned = self::WC_TYPE_ASSIGNED === $wc_product->get_type();
        $barcode = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BARCODE);

        if ($barcode) {
            foreach ($original_product->get('flat_product.content_vars') as $content_var_data) {
                $content_var = new Dot($content_var_data);
                if ($content_var->get('name') === $barcode) {
                    $expected_attribute_value = $content_var->get('value');
                    $current_attribute_value = ProductAttributes::getBarcodeMeta($wc_product);
                    $this->assertEquals(
                        $expected_attribute_value,
                        $current_attribute_value,
                        "[sku=$sku] WooCommerce barcode meta value doesn't match the expected value"
                    );
                }
            }
        }
        if ($isAssigned) {
            foreach ($original_product->get('flat_product.content_vars') as $content_var_data) {
                $content_var = new Dot($content_var_data);
                if ($content_var->has('attribute_option_id')) {
                    $expected_attribute_name = $content_var->get('name');
                    $this->assertNotEmpty(
                        $wc_product->get_attribute($expected_attribute_name),
                        "Attribute $expected_attribute_name does not exist"
                    );
                    $this->assertEquals(
                        $content_var->get('value_label'),
                        $wc_product->get_attribute($expected_attribute_name),
                        "[sku=$sku] WooCommerce attribute option value doesn't match the expected value"
                    );
                }
            }
        } else {
            foreach ($original_product->get('flat_product.content_vars') as $content_var_data) {
                $content_var = new Dot($content_var_data);

                $expected_attribute_name = $content_var->get('name');
                $expected_attribute_value = $content_var->get('value');
                if ($content_var->has('attribute_option_id')) {
                    $expected_attribute_value = $content_var->get('value_label');
                } else {
                    $expected_attribute_name = $content_var->get('label');
                }

                $this->assertNotEmpty(
                    $wc_product->get_attribute($expected_attribute_name),
                    "Attribute $expected_attribute_name does not exist"
                );

                $current_attribute_value = $wc_product->get_attribute($expected_attribute_name);
                $this->assertEquals(
                    $expected_attribute_value,
                    $current_attribute_value,
                    "[sku=$sku] WooCommerce attribute option value doesn't match the expected value"
                );
            }
        }

        $galleryImages = $wc_product->get_gallery_image_ids();
        $productImages = $original_product->get('flat_product.product_images');
        if (!empty($productImages)) {
            foreach ($productImages as $index => $images) {
                if ($images['id'] === $original_product->get('flat_product.main_image.id')) {
                    unset($productImages[$index]);
                }
            }
            $this->assertSameSize($galleryImages, $productImages, 'Product images should updated');
        }
    }

    /**
     * @param $expected
     * @param $actual
     * @param string $message This value will be appended with the path taken to a certain value. So you can easily see what value does not equals.
     */
    public function assertDeepArray($expected, $actual, string $message = '')
    {
        foreach (array_keys($expected) as $array_key) {
            $errorMessage = $message."$array_key.";
            $expectedValue = $expected[$array_key] ?? null;
            $actualValue = $actual[$array_key] ?? null;

            if (is_array($expectedValue)) {
                $this->assertDeepArray($expectedValue, $actualValue, $errorMessage);
            } else {
                $this->assertEquals($expectedValue, $actualValue, $errorMessage);
            }
        }
    }

    protected function syncShopInformation()
    {
        $this->mockApiCallsFromDirectory('commands/shop-info', false);
        $this->runner->execute(SyncWoocommerceShopInfo::getCommandName());
    }

    protected function assertTaskCount(int $expected, string $message)
    {
        global $wpdb;
        $this->assertEquals(
            $expected,
            TaskModel::count(),
            $message
        );
    }

    protected function assertTaskNotCount(int $expected, string $message)
    {
        $this->assertNotEquals(
            $expected,
            TaskModel::count(),
            $message
        );
    }

    protected function assertProductPrices(Dot $original_product, WC_Product $wc_product, string $sku): void
    {
        // Regular price (not applicable for configurable products)
        if (self::WC_TYPE_CONFIGURABLE !== $wc_product->get_type()) {
            $expected_regular_price = $original_product->get('product_default_price.ppu_wt');
            $this->assertEquals(
                $expected_regular_price,
                $wc_product->get_regular_price(),
                "[sku=$sku] WooCommerce regular price doesn't match the expected regular price"
            );

            // Discounted price (not applicable for configurable products). Only applicable when not equal to regular price
            if ($original_product->get('product_price.ppu_wt') !== $expected_regular_price) {
                $expected_discounted_price = $original_product->get('product_price.ppu_wt');
                $this->assertEquals(
                    $expected_discounted_price,
                    $wc_product->get_sale_price(),
                    "[sku=$sku] WooCommerce discount price doesn't match the expected discount price"
                );
            }
        }
    }

    protected function assertProductStock(Dot $original_product, WC_Product $wc_product, string $sku): void
    {
        // Stock
        $expected_in_stock = $original_product->get('flat_product.product.product_stock.in_stock');
        if ($expected_in_stock) {
            // Manage stock is based on stock unlimited. unlimited equals no stock management
            $expected_manage_stock = !$original_product->get('flat_product.product.product_stock.unlimited');
            $this->assertEquals(
                $expected_manage_stock,
                $wc_product->get_manage_stock(self::WC_CONTEXT_EDIT),
                "[sku=$sku] WooCommerce manage stock doesn't match the expected manage stock"
            );

            // Stock quantity is set to one when stock isn't managed
            $expected_stock_quantity = $expected_manage_stock ?
                $original_product->get('flat_product.product.product_stock.value') : 1;

            // Added orderable stock on test
            if ($original_product->has('orderable_stock_value')) {
                // set stock based on orderable stock instead
                $stock_quantity = $original_product->get('orderable_stock_value');
                $expected_stock_quantity = $stock_quantity > 0;
            }

            if (!$expected_manage_stock) {
                // When we don't manage stock, the quantity will be null
                $expected_stock_quantity = null;
            }

            $this->assertEquals(
                $expected_stock_quantity,
                $wc_product->get_stock_quantity(),
                "[sku=$sku] WooCommerce stock quantity doesn't match the expected stock quantity"
            );

            // Stock status
            $expected_stock_status = self::WC_STATUS_INSTOCK;
            if (self::WC_TYPE_CONFIGURABLE === $wc_product->get_type()) {
                $expected_stock_status = $expected_manage_stock ? self::WC_STATUS_INSTOCK : self::WC_STATUS_OUTOFSTOCK;
            }

            $this->assertEquals(
                $expected_stock_status,
                $wc_product->get_stock_status(),
                "[sku=$sku] WooCommerce stock status doesn't match the expected stock status"
            );
        } else {
            // Manage stock is always set to true when product is out of stock
            $expected_manage_stock = true;
            $this->assertEquals(
                $expected_manage_stock,
                $wc_product->get_manage_stock(self::WC_CONTEXT_EDIT),
                "[sku=$sku] WooCommerce manage stock doesn't match the expected manage stock"
            );

            // Stock quantity is always set to 0 when product is out of stock
            $expected_stock_quantity = 0;
            $this->assertEquals(
                $expected_stock_quantity,
                $wc_product->get_stock_quantity(),
                "[sku=$sku] WooCommerce stock quantity doesn't match the expected stock quantity"
            );

            // Stock status
            $expected_stock_status = self::WC_STATUS_OUTOFSTOCK;
            $this->assertEquals(
                $expected_stock_status,
                $wc_product->get_stock_status(),
                "[sku=$sku] WooCommerce stock status doesn't match the expected stock status"
            );
        }
    }

    protected function assertProductCrossSell(WC_Product $wc_product, string $sku): void
    {
        $crossSellIds = $wc_product->get_cross_sell_ids();
        $this->assertNotEmpty(
            $crossSellIds,
            "[sku=$sku] WooCommerce cross-sell IDs should not be empty"
        );
    }

    protected function assertProductUpSell(WC_Product $wc_product, string $sku): void
    {
        $upSellIds = $wc_product->get_upsell_ids();
        $this->assertNotEmpty(
            $upSellIds,
            "[sku=$sku] WooCommerce up-sell IDs should not be empty"
        );
    }
}
