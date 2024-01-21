<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Backoffice\MenuStructure;
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
use StoreKeeper\WooCommerce\B2C\Commands\ImportStartingSiteCommand;
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
use StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck;
use StoreKeeper\WooCommerce\B2C\Commands\SyncIssueFixer;
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
use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;

class Core
{
    const HIGH_PRIORITY = 9001;

    const COMMANDS = [
        ImportStartingSiteCommand::class,

        ScheduledProcessor::class,
        SyncWoocommerceShopInfo::class,
        SyncWoocommerceFullSync::class,
        ConnectBackend::class,
        SyncIssueCheck::class,
        SyncIssueFixer::class,
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

    const HOOKS = [
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

    /**
     * Core constructor.
     */
    public function __construct()
    {
        $this->loader = new ActionFilterLoader();

        $this->setUpdateCheck();
        $this->setLocale();
        if (StoreKeeperOptions::isOrderSyncEnabled()) {
            $this->setOrderHooks();
        }
        if (StoreKeeperOptions::isCustomerSyncEnabled()) {
            $this->setCustomerHooks();
        }
        $this->setCouponHooks();
        $this->prepareCron();
        self::registerCommands();
        $this->loadAdditionalCore();
        $this->loadEndpoints();
        if (StoreKeeperOptions::isPaymentSyncEnabled()) {
            $this->registerPaymentGateway();
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

//        add_action('init', function () {
//            if( RoleHelper::isContentManager() ){
//                $current_user = wp_get_current_user();
//                $current_user->remove_cap('manage_options');
//            }
//        });
    }

    private function prepareCron()
    {
        $registrar = new CronRegistrar();
        $this->loader->add_filter('cron_schedules', $registrar, 'addCustomCronInterval');
        $this->loader->add_action('admin_init', $registrar, 'register');

        $processTask = new ProcessTaskCron();
        $this->loader->add_action(CronRegistrar::HOOK_PROCESS_TASK, $processTask, 'execute');
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
        return (defined('WP_DEBUG') && WP_DEBUG) ||
            (defined('STOREKEEPER_WOOCOMMERCE_B2C_DEBUG') && STOREKEEPER_WOOCOMMERCE_B2C_DEBUG) ||
            !empty($_ENV['STOREKEEPER_WOOCOMMERCE_B2C_DEBUG'])
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

    private function registerPaymentGateway(): void
    {
        if ($this->isPaymentGatewayActivated()) {
            //activated the payment gateway and backend is connected, which is required for this feature.
            $PaymentGateway = new PaymentGateway();
            $this->loader->add_action('woocommerce_thankyou', $PaymentGateway, 'checkPayment');
            $this->loader->add_filter('woocommerce_payment_gateways', $PaymentGateway, 'addGatewayClasses');
            // check the order status
            $this->loader->add_filter('woocommerce_api_backoffice_pay_gateway_return', $PaymentGateway, 'onReturn');

            $this->loader->add_filter('init', $PaymentGateway, 'registerCheckoutFlash');
        }

        // Add hooks when testing
        if (self::isTest() || $this->isPaymentGatewayActivated()) {
            // Handle payment refunds
            $PaymentGateway = new PaymentGateway();

            $this->loader->add_action('woocommerce_create_refund', $PaymentGateway, 'createWooCommerceRefund', 10, 2);
            $this->loader->add_action('woocommerce_order_refunded', $PaymentGateway, 'createStoreKeeperRefundPayment', 10, 2);
            $this->loader->add_action('woocommerce_order_partially_refunded', $PaymentGateway, 'createStoreKeeperRefundPayment', 10, 2);
        }
    }

    private function isPaymentGatewayActivated(): bool
    {
        return 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes') &&
            StoreKeeperOptions::isConnected();
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

        // Check for updates upon loading WordPress pages
        $this->loader->add_action('load-plugins.php', $updator, 'updateAction');
        $this->loader->add_action('load-plugin-install.php', $updator, 'updateAction');
        $this->loader->add_action('upgrader_process_complete', $updator, 'onProcessComplete', 0, 2);

        // Check for updates upon loading StoreKeeper pages
        list($pages) = MenuStructure::getPages();
        foreach ($pages as $page) {
            $this->loader->add_action($page->getActionName(), $updator, 'updateAction');
        }
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

    private function setCustomerHooks()
    {
        $customerHook = new CustomerLoginRegisterHandler();
        $this->loader->add_action('wp_login', $customerHook, 'loginBackendSync', null, 2);
        $this->loader->add_action('user_register', $customerHook, 'registerBackendSync', null, 2);
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
        if (function_exists('posix_getpwuid') &&
            function_exists('posix_geteuid')
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
}
