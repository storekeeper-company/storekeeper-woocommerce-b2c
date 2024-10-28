<?php

namespace StoreKeeper\WooCommerce\B2C;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Commands\CleanWoocommerceEnvironment;
use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Commands\ConnectBackend;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportAll;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportAttribute;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportAttributeOption;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportCategory;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportCustomer;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportProduct;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportProductBlueprint;
use StoreKeeper\WooCommerce\B2C\Commands\FileExports\FileExportTag;
use StoreKeeper\WooCommerce\B2C\Commands\MarkTasksAsRetry;
use StoreKeeper\WooCommerce\B2C\Commands\MarkTasksAsSuccess;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task\TaskDelete;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task\TaskGet;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task\TaskList;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task\TaskPurge;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task\TaskPurgeOld;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog\WebhookLogDelete;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog\WebhookLogGet;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog\WebhookLogList;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog\WebhookLogPurge;
use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog\WebhookLogPurgeOld;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessSingleTask;
use StoreKeeper\WooCommerce\B2C\Commands\ScheduledProcessor;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributeOptionPage;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributes;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCategories;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCouponCodes;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCrossSellProductPage;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCrossSellProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFeaturedAttributes;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFullSync;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProductPage;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShippingMethods;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceTags;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceUpsellProductPage;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceUpsellProducts;
use StoreKeeper\WooCommerce\B2C\Commands\WpCliCommandRunner;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Cron\ProcessTaskCron;
use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Exceptions\BootError;
use StoreKeeper\WooCommerce\B2C\Frontend\Filters\OrderTrackingMessage;
use StoreKeeper\WooCommerce\B2C\Frontend\Filters\PrepareProductCategorySummaryFilter;
use StoreKeeper\WooCommerce\B2C\Frontend\FrontendCore;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\AddressFormattingHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\CustomerLoginRegisterHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes\MarkdownCode;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;

class Core
{
    public const HIGH_PRIORITY = 9001;

    public const COMMANDS = [
        ScheduledProcessor::class,
        SyncWoocommerceShopInfo::class,
        SyncWoocommerceFullSync::class,
        ConnectBackend::class,
        SyncWoocommerceUpsellProducts::class,
        SyncWoocommerceUpsellProductPage::class,
        SyncWoocommerceCrossSellProducts::class,
        SyncWoocommerceCrossSellProductPage::class,
        SyncWoocommerceCategories::class,
        SyncWoocommerceTags::class,
        SyncWoocommerceCouponCodes::class,
        SyncWoocommerceAttributes::class,
        SyncWoocommerceFeaturedAttributes::class,
        SyncWoocommerceCouponCodes::class,
        SyncWoocommerceAttributeOptions::class,
        SyncWoocommerceAttributeOptionPage::class,
        SyncWoocommerceProducts::class,
        SyncWoocommerceProductPage::class,
        SyncWoocommerceShippingMethods::class,
        ProcessAllTasks::class,
        ProcessSingleTask::class,
        CleanWoocommerceEnvironment::class,

        MarkTasksAsRetry::class,
        MarkTasksAsSuccess::class,

        FileExportCategory::class,
        FileExportTag::class,
        FileExportAttribute::class,
        FileExportAttributeOption::class,
        FileExportProduct::class,
        FileExportProductBlueprint::class,
        FileExportCustomer::class,
        FileExportAll::class,

        WebhookLogDelete::class,
        WebhookLogGet::class,
        WebhookLogList::class,
        WebhookLogPurge::class,
        WebhookLogPurgeOld::class,

        TaskDelete::class,
        TaskGet::class,
        TaskList::class,
        TaskPurge::class,
        TaskPurgeOld::class,
    ];

    public const HOOKS = [
        PrepareProductCategorySummaryFilter::class,
        OrderTrackingMessage::class,
    ];
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.0.1
     *
     * @var ActionFilterLoader maintains and registers all hooks for the plugin
     */
    protected $loader;

    public static function plugin_url()
    {
        return untrailingslashit(plugins_url('/', STOREKEEPER_WOOCOMMERCE_FILE));
    }

    public static function plugin_abspath()
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * Core constructor.
     */
    public function __construct()
    {
        // Declare HPOS compabitility
        add_action('before_woocommerce_init', static function () {
            if (class_exists(FeaturesUtil::class)) {
                FeaturesUtil::declare_compatibility('custom_order_tables', STOREKEEPER_FOR_WOOCOMMERCE_NAME.'/'.STOREKEEPER_WOOCOMMERCE_B2C_NAME.'.php');
            }
        });

        $this->loader = new ActionFilterLoader();

        $this->setUpdateCheck();
        $this->setLocale();
        if (StoreKeeperOptions::isOrderSyncEnabled()) {
            $this->setOrderHooks();
        }
        if (StoreKeeperOptions::isCustomerSyncEnabled()) {
            (new CustomerLoginRegisterHandler())->registerHooks();
        }
        $this->setCouponHooks();
        $this->prepareCron();
        self::registerCommands();
        $this->loadAdditionalCore();
        $this->loadEndpoints();

        if (StoreKeeperOptions::isPaymentSyncEnabled()) {
            $PaymentGateway = new PaymentGateway();
            $PaymentGateway->registerHooks();
        }

        $this->versionChecks();
        $this->registerMarkDown();
        $this->registerAddressFormatting();
        // Register hooks for unit testing as well
        if (StoreKeeperOptions::isConnected() || self::isTest()) {
            $media = new Media();
            $this->loader->add_filter('wp_get_attachment_url', $media, 'getAttachmentUrl', 999, 2);
            $this->loader->add_filter('wp_get_attachment_image_src', $media, 'getAttachmentImageSource', 999, 4);
            $this->loader->add_filter('wp_calculate_image_srcset', $media, 'calculateImageSrcSet', 999, 5);
        }

        add_filter('woocommerce_shipping_settings', 'displayMinAmountField');
        add_action('woocommerce_shipping_init', [$this, 'applyMinAmountToAllShippingMethods']);
        add_action('woocommerce_review_order_after_shipping', [$this, 'displayShippingMinAmountContent']);
        add_filter('woocommerce_package_rates', [$this, 'modifyShippingRates'], 10, 2);
    }

    private function prepareCron()
    {
        $registrar = new CronRegistrar();
        $this->loader->add_filter('cron_schedules', $registrar, 'addCustomCronInterval');
        $this->loader->add_action('admin_init', $registrar, 'register');

        $processTask = new ProcessTaskCron();
        $this->loader->add_action(CronRegistrar::HOOK_PROCESS_TASK, $processTask, 'execute');

        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_day'] = [
                'interval' => 86400,
                'display' => __('Every Day'),
            ];

            return $schedules;
        });

        if (!wp_next_scheduled('sk_sync_paid_orders')) {
            wp_schedule_event(time(), 'every_day', 'sk_sync_paid_orders');
        }

        $this->loader->add_action('sk_sync_paid_orders', $this, 'syncAllPaidAndProcessingOrders');
    }

    /**
     * @throws Exceptions\BaseException
     */
    public static function getWpCommandRunner(): WpCliCommandRunner
    {
        $runner = new WpCliCommandRunner();
        foreach (self::COMMANDS as $class) {
            $runner->addCommandClass($class);
        }

        return $runner;
    }

    /**
     * @throws Exceptions\BaseException
     */
    public static function getCommandRunner(): CommandRunner
    {
        $runner = new CommandRunner();
        foreach (self::COMMANDS as $class) {
            $runner->addCommandClass($class);
        }

        return $runner;
    }

    /**
     * @throws BootError
     */
    public static function checkRequirements()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
        if (!in_array('woocommerce/woocommerce.php', $active_plugins)) {
            $txt = sprintf(
                __(
                    '%s: You need to have WooCommerce installed for this add-on to work',
                    I18N::DOMAIN
                ),
                STOREKEEPER_FOR_WOOCOMMERCE_NAME
            );

            throw new BootError($txt);
        }
    }

    public static function isDebug(): bool
    {
        return (defined('WP_DEBUG') && WP_DEBUG)
            || (defined('STOREKEEPER_WOOCOMMERCE_B2C_DEBUG') && STOREKEEPER_WOOCOMMERCE_B2C_DEBUG)
            || !empty($_ENV['STOREKEEPER_WOOCOMMERCE_B2C_DEBUG'])
        ;
    }

    public static function isDataDump(): bool
    {
        return STOREKEEPER_WOOCOMMERCE_API_DUMP && !self::isTest();
    }

    public static function isTest(): bool
    {
        return defined('WP_TESTS') && WP_TESTS;
    }

    public static function isWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    public static function getDumpDir(): string
    {
        $tmp = Core::getTmpBaseDir();
        if (is_null($tmp)) {
            throw new \RuntimeException('Cannot find writable directory, for dumping api calls. Set define(\'STOREKEEPER_WOOCOMMERCE_API_DUMP\', false); to in your wp-config.php prevent dumping api calls.');
        }

        return $tmp.'/dumps/';
    }

    private function registerAddressFormatting(): void
    {
        $addressFormatting = new AddressFormattingHandler();

        // Address display and formatting
        $this->loader->add_filter('woocommerce_localisation_address_formats', $addressFormatting, 'customAddressFormats', 11);
        $this->loader->add_filter('woocommerce_formatted_address_replacements', $addressFormatting, 'customAddressReplacements', 11, 2);
        $this->loader->add_filter('woocommerce_my_account_my_address_formatted_address', $addressFormatting, 'addCustomAddressArguments', 11, 3);
        $this->loader->add_filter('woocommerce_get_order_address', $addressFormatting, 'addCustomAddressArgumentsForOrder', 11, 3);
    }

    private function versionChecks()
    {
        $this->loader->add_action('admin_notices', $this, 'wooCommerceVersionCheck');
    }

    public function wooCommerceVersionCheck()
    {
        $current = WC()->version;
        $required = '3.9.0';
        if (version_compare($current, $required, '<')) {
            $txt = sprintf(
                __(
                    'Your WooCommerce version (%s) is lower then the minimum required %s which could cause unexpected behaviour',
                    I18N::DOMAIN
                ),
                $current,
                $required
            );
            $txt = esc_html($txt);
            echo <<<HTML
<div class="notice notice-error">
<p style="color: red;">$txt</p>
</div>
HTML;

            return;
        }
    }

    private function setUpdateCheck()
    {
        $updator = new Updator();
        $updator->registerHooks();
    }

    private function setLocale()
    {
        $I18N = new I18N();
        $this->loader->add_action('plugin_loaded', $I18N, 'load_plugin_textdomain');
    }

    private function setOrderHooks()
    {
        $orderHandler = new OrderHandler();
        // Creation
        $this->loader->add_action('woocommerce_checkout_order_processed', $orderHandler, 'create', self::HIGH_PRIORITY);
        $this->loader->add_action('woocommerce_new_order', $orderHandler, 'addToBeSynchronizedMetadata', self::HIGH_PRIORITY, 2);
        // Update events
        $this->loader->add_action(
            'woocommerce_payment_complete',
            $orderHandler,
            'updateWithIgnore',
            self::HIGH_PRIORITY
        );
        $this->loader->add_action('woocommerce_update_order', $orderHandler, 'updateWithIgnore', self::HIGH_PRIORITY);
        // Status change events
        $this->loader->add_action('woocommerce_order_status_pending', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_failed', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_on-hold', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_processing', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_completed', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_refunded', $orderHandler, 'updateWithIgnore');
        $this->loader->add_action('woocommerce_order_status_cancelled', $orderHandler, 'updateWithIgnore');

        // Adding custom data to the order items
        $this->loader->add_action(
            'woocommerce_checkout_create_order',
            $orderHandler,
            'addShopProductIdsToMetaData',
            self::HIGH_PRIORITY,
            4
        );
    }

    private function setCouponHooks()
    {
        // Overwrite WooCommerce lower casing of coupon codes
        add_filter('woocommerce_coupon_code', 'wc_strtoupper', self::HIGH_PRIORITY);
    }

    public static function registerCommands()
    {
        if (self::isWpCli()) {
            $runner = self::getWpCommandRunner();
            $runner->load();
        }
    }

    private function loadAdditionalCore()
    {
        if (is_admin()) {
            $core = new BackofficeCore();
        } else {
            $core = new FrontendCore();
        }
        $core->run();
    }

    private function loadEndpoints()
    {
        $endpointLoader = new EndpointLoader();
        $this->loader->add_action('rest_api_init', $endpointLoader, 'load');
    }

    private function registerMarkDown()
    {
        $this->loader->add_filter('init', new MarkdownCode(), 'load');

        // for ajax responses, when markdown present in short description
        add_filter('woocommerce_short_description', function ($description) {
            return do_shortcode($description);
        });
    }

    public function run()
    {
        try {
            self::checkRequirements();
            $this->loader->run();
        } catch (BootError $e) {
            $this->renderBootError($e);
        }
    }

    public static function getTmpBaseDir(): ?string
    {
        $dirs = self::getPossibleTmpDirs();

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (false === @mkdir($dir, '0777', true) && !is_dir($dir)) {
                    continue; // failed to create
                }
            }
            if (is_writable($dir)) {
                return $dir;
            }
        }

        return null;
    }

    public static function getPossibleTmpDirs(): array
    {
        $dirs = [];
        if (function_exists('posix_getpwuid')
            && function_exists('posix_geteuid')
        ) {
            $processUser = posix_getpwuid(posix_geteuid());
            $user = $processUser['name'];
            $dirs[] = "/home/$user/tmp";
        }
        if (!empty($_SERVER['HOME'])) {
            $dirs[] = $_SERVER['HOME'].'/tmp';
        }

        $dirs[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.STOREKEEPER_FOR_WOOCOMMERCE_NAME;
        $dirs[] = sys_get_temp_dir().DIRECTORY_SEPARATOR.STOREKEEPER_WOOCOMMERCE_B2C_NAME;
        $dirs[] = STOREKEEPER_WOOCOMMERCE_B2C_ABSPATH.DIRECTORY_SEPARATOR.'tmp';

        return $dirs;
    }

    public function renderBootError(BootError $e)
    {
        if (!self::isTest()) {
            $txt = esc_html($e->getMessage());
            if (self::isWpCli()) {
                echo "$txt\n";
            } else {
                echo <<<HTML
<div class="notice notice-error">
<p style="color: red; text-decoration: blink;">$txt</p>
</div>
HTML;
            }
        }
    }

    public function addExtraFieldsInShippingMethods($settings)
    {
        // Add a new setting for Minimum Amount
        $new_settings = $settings;
        $currency_code = get_woocommerce_currency_symbol();

        $new_settings['min_amount'] = [
            'title' => sprintf(__('Minimum Cost (%s)', I18N::DOMAIN), $currency_code),
            'type' => 'text',
            'placeholder' => '100.00', // Adjusted to reflect decimal format
            'default' => '100.00', // Default value as a decimal
            'custom_attributes' => [
                'min' => 0,
                'step' => '0.01', // Allow decimal steps in the input
            ],
        ];

        return $new_settings;
    }

    public function displayShippingMinAmountContent(): void
    {
        // Retrieve the shipping method selected by the user
        $chosen_shipping_method = WC()->session->get('chosen_shipping_methods')[0];

        // Get the shipping settings for the chosen method
        $shipping_method_settings = get_option("woocommerce_{$chosen_shipping_method}_settings");

        // Ensure the settings are an array and contain 'min_amount'
        if (is_array($shipping_method_settings) && isset($shipping_method_settings['min_amount'])) {
            $min_amount = floatval($shipping_method_settings['min_amount']);
        } else {
            $min_amount = '';
        }
    }

    public function applyMinAmountToAllShippingMethods(): void
    {
        // Manually add the filter for known methods, including Local Pickup
        $shipping_methods = ['flat_rate', 'free_shipping', 'local_pickup'];

        foreach ($shipping_methods as $method_id) {
            add_filter("woocommerce_shipping_instance_form_fields_{$method_id}", [$this, 'addExtraFieldsInShippingMethods'], 10, 1);
        }
    }

    public function modifyShippingRates($rates, $package)
    {
        // Get the formatted cart total using WooCommerce's functions and settings
        $total = WC()->cart->get_cart_contents_total();

        // Get the available shipping methods
        $shipping_methods = WC()->shipping->get_shipping_methods();

        // Prepare the methods data, ensuring 'min_amount' is properly retrieved and formatted
        $methods_data = array_map(function ($method) {
            $min_amount = isset($method->instance_settings['min_amount']) ? floatval($method->instance_settings['min_amount']) : 0;

            return [
                'name' => $method->title,
                'min_amount' => $min_amount,
            ];
        }, $shipping_methods);

        foreach ($rates as $rate_key => $rate) {
            if (isset($rate->method_id, $rate->instance_id)) {
                foreach ($methods_data as $method_data) {
                    if ($method_data['name'] === $rate->label && $method_data['min_amount'] > 0 && $total >= $method_data['min_amount']) {
                        $rates[$rate_key]->cost = 0;
                        $rates[$rate_key]->label .= ': '.__('Free Shipping', I18N::DOMAIN);
                        break;
                    }
                }
            }
        }

        return $rates;
    }

    public function syncAllPaidAndProcessingOrders()
    {
        $orderSyncMetaBox = new Cron();
        $orderSyncMetaBox->syncAllPaidOrders();
    }
}
