<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\PluginConflictChecker;
use StoreKeeper\WooCommerce\B2C\Helpers\ServerStatusChecker;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskRateCalculator;

class InfoHandler
{
    public const EXTRA_BLOG_INFO_FIELDS = [
        'name',
        'description',
        'url',
        'language',
        'pingback_url',
        'atom_url',
        'rdf_url',
        'rss_url',
        'rss2_url',
        'comments_atom_url',
        'comments_rss2_url',
    ];

    public const EXTRA_ACTIVE_THEME_FIELD = [
        'Name',
        'Description',
        'Author',
        'Version',
        'ThemeURI',
        'AuthorURI',
        'Status',
        'Tags',
    ];

    public const VENDOR = StoreKeeperOptions::VENDOR;
    public const PLATFORM_NAME = StoreKeeperOptions::PLATFORM_NAME;
    public const SOFTWARE_NAME = 'storekeeper-woocommerce-b2c';

    public const IMAGE_CDN_PLUGIN_OPTION = StoreKeeperOptions::IMAGE_CDN;
    public const SHIPPING_METHOD_SYNC_OPTION = 'shipping-method-sync';
    public const SK_PAYMENTS_OPTION = 'sk-payments';

    protected static function getUnsynchronizedOrders()
    {
        $orderQuery = new \WC_Order_Query([
            'meta_key' => OrderHandler::TO_BE_SYNCHRONIZED_META_KEY,
            'meta_value' => 'yes',
            'meta_compare' => '=',
            'orderby' => 'date_created',
            'order' => 'ASC',
        ]);
        $unsynchronizedOrders = $orderQuery->get_orders();
        // Order query by multiple status don't work so we have to filter manually
        $unsynchronizedOrders = array_filter(
            $unsynchronizedOrders,
            static function (\WC_Order $order) {
                $status = $order->get_status();

                return OrderExport::STATUS_CANCELLED !== $status
                    && OrderExport::STATUS_REFUNDED !== $status;
            }
        );

        return $unsynchronizedOrders;
    }

    public function run(): array
    {
        $data = self::gatherInformation();

        array_walk_recursive($data, static function (&$v) {
            if ($v instanceof \DateTimeInterface) {
                $v = $v->format(DATE_ATOM);
            }
        });

        return $data;
    }

    public static function gatherInformation(): array
    {
        return [
            'vendor' => self::VENDOR,
            'platform_name' => self::PLATFORM_NAME,
            'platform_version' => get_bloginfo('version'),
            'software_name' => self::SOFTWARE_NAME,
            'software_version' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
            'extra' => self::getExtras(),
        ];
    }

    public static function getExtras(): array
    {
        $extras = [
            'plugins' => self::getPlugins(),
            'active_theme' => self::getActiveTheme(),
            'sync_mode' => StoreKeeperOptions::getSyncMode(),
            'active_capability' => self::getActiveCapabilities(),
            'image_variants' => self::getImageVariants(),
            'plugin_settings_url' => admin_url('/admin.php?page=storekeeper-settings'),
            'now_date' => DateTimeHelper::currentDateTime(),
            'plugin_options' => self::getPluginOptions(),
            'system_status' => self::getSystemStatus(),
        ];

        foreach (self::EXTRA_BLOG_INFO_FIELDS as $blogInfoField) {
            get_bloginfo($blogInfoField);
            // Getting the blog information twice seems to ensure `atom_url` returns correctly.
            // No idea why this is the case. But already spend an hour on trying to find out why.
            $extras[$blogInfoField] = get_bloginfo($blogInfoField);
        }

        return $extras;
    }

    public static function getSystemStatus(): array
    {
        $systemStatus = [];
        $systemStatus['order'] = self::getOrderSystemStatus();
        $systemStatus['task_processor'] = self::getTaskProcessorStatus();
        $systemStatus['failed_compatibility_checks'] = self::getFailedCompatibilityChecks();

        return $systemStatus;
    }

    public static function getFailedCompatibilityChecks(): array
    {
        return array_merge(
            PluginConflictChecker::getPluginsWithConflict(),
            ServerStatusChecker::getServerIssues(),
        );
    }

    public static function getTaskProcessorStatus(): array
    {
        $taskProcessorStatus = [];

        $now = DateTimeHelper::currentDateTime();
        $calculator = new TaskRateCalculator($now);
        $processedRate = $calculator->calculateProcessed();
        $cronRunner = CronOptions::get(CronOptions::RUNNER, CronRegistrar::RUNNER_WPCRON);
        $postExecutionStatus = CronOptions::get(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED);
        $postExecutionError = CronOptions::get(CronOptions::LAST_POST_EXECUTION_ERROR);
        $preExecutionDateTime = CronOptions::get(CronOptions::LAST_PRE_EXECUTION_DATE);

        $invalidRunners = CronOptions::getInvalidRunners();

        $postExecutionSuccessful = CronRegistrar::STATUS_SUCCESS === $postExecutionStatus;

        $taskProcessorStatus['in_queue_quantity'] = TaskModel::count(
            ['status = :status'],
            ['status' => TaskHandler::STATUS_NEW]
        );
        $taskProcessorStatus['processing_p_h'] = $processedRate;
        $taskProcessorStatus['runner'] = $cronRunner;
        $taskProcessorStatus['last_execute_date'] = DatabaseConnection::formatFromDatabaseDateIfNotEmpty($preExecutionDateTime);
        $taskProcessorStatus['last_success_date'] = self::getLastSuccessSyncRunDate();
        $taskProcessorStatus['last_end_date'] = self::getLastSyncRunDate();
        $taskProcessorStatus['last_task_date'] = TaskModel::getLastProcessTaskDate();
        $taskProcessorStatus['last_is_success'] = $postExecutionSuccessful;
        $taskProcessorStatus['last_error_message'] = $postExecutionError;
        $taskProcessorStatus['other_processor_type_is_running'] = !empty($invalidRunners);
        $taskProcessorStatus['failed_task_quantity'] = TaskModel::count(
            ['status = :status AND type_group != :type_group'],
            [
                'status' => TaskHandler::STATUS_FAILED,
                'type_group' => TaskHandler::REPORT_ERROR_TYPE_GROUP,
            ]
        );

        return $taskProcessorStatus;
    }

    public static function getOrderSystemStatus(): array
    {
        $orderSystemStatus = [];

        $lastDate = null;
        $orderIds = wc_get_orders([
            'limit' => 1,
            'return' => 'ids',
            'orderby' => 'date_created',
            'order' => 'DESC',
        ]);

        if (!empty($orderIds)) {
            $lastOrder = wc_get_order(reset($orderIds));
            $lastDate = $lastOrder->get_date_created() ?: null;
        }

        $orderSystemStatus['last_date'] = $lastDate;

        $orderSystemStatus['last_synchronized_date'] = TaskModel::getLatestSuccessfulSynchronizedDateForType(TaskHandler::ORDERS_EXPORT);

        $failedOrderIds = TaskModel::getFailedOrderIds();
        $failedOrderIdsWithoutCancelled = [];
        if (!empty($failedOrderIds)) {
            $failedOrdersQuery = new \WC_Order_Query([
                'post__in' => $failedOrderIds,
                'orderby' => 'ID',
                'order' => 'ASC',
            ]);

            $failedOrders = $failedOrdersQuery->get_orders();

            // Order query by multiple status don't wmakork so we have to filter manually
            $failedOrdersWithoutCancelled = array_filter($failedOrders, static function (\WC_Order $order) {
                return OrderExport::STATUS_CANCELLED !== $order->get_status();
            });
            $failedOrderIdsWithoutCancelled = array_map(static function (\WC_Order $order) {
                return $order->get_id();
            }, $failedOrdersWithoutCancelled);
        }

        $orderSystemStatus['ids_with_failed_tasks'] = $failedOrderIdsWithoutCancelled;

        $orderQuery = new \WC_Order_Query([
            'meta_key' => OrderHandler::TO_BE_SYNCHRONIZED_META_KEY,
            'meta_value' => 'yes',
            'meta_compare' => '=',
            'orderby' => 'date_created',
            'order' => 'ASC',
        ]);
        $unsynchronizedOrders = $orderQuery->get_orders();
        $unsynchronizedOrderIds = [];
        // Order query by multiple status don't work so we have to filter manually
        $unsynchronizedOrders = array_filter(
            $unsynchronizedOrders,
            static function (\WC_Order $order) {
                $status = $order->get_status();

                return OrderExport::STATUS_CANCELLED !== $status
                    && OrderExport::STATUS_REFUNDED !== $status;
            }
        );

        $oldestUnsynchronizedOrderDateTime = null;
        if (!empty($unsynchronizedOrders)) {
            $unsynchronizedOrderIds = array_map(
                static function (\WC_Order $order) {
                    return $order->get_id();
                },
                $unsynchronizedOrders
            );

            $oldestUnsynchronizedOrder = reset($unsynchronizedOrders);
            $oldestUnsynchronizedOrderDateTime = $oldestUnsynchronizedOrder->get_date_created();
        }

        $orderSystemStatus['ids_not_synchronized'] = $unsynchronizedOrderIds;
        $orderSystemStatus['oldest_date_not_synchronized'] = $oldestUnsynchronizedOrderDateTime;

        return $orderSystemStatus;
    }

    public static function getPluginOptions(): array
    {
        $pluginOptions = [];
        $pluginOptions[self::IMAGE_CDN_PLUGIN_OPTION] = StoreKeeperOptions::isImageCdnEnabled();
        $pluginOptions[self::SHIPPING_METHOD_SYNC_OPTION] = StoreKeeperOptions::isShippingMethodSyncEnabled();
        $pluginOptions[self::SK_PAYMENTS_OPTION] = StoreKeeperOptions::isPaymentSyncEnabled();

        return $pluginOptions;
    }

    /**
     * Mutate the registered image sub-sizes in WordPress to a readable
     * format by the StoreKeeper BackOffice.
     */
    public static function getImageVariants(): array
    {
        $registeredSubSizes = wp_get_registered_image_subsizes();
        $imageVariants = [];
        foreach ($registeredSubSizes as $sizeName => $sizeMetadata) {
            $imageVariants[$sizeName] = [];
            $imageVariants[$sizeName]['width'] = $sizeMetadata['width'] > 0 ? $sizeMetadata['width'] : null;
            $imageVariants[$sizeName]['height'] = $sizeMetadata['height'] > 0 ? $sizeMetadata['height'] : null;

            $variantFit = 'pad';
            if ($sizeMetadata['crop']) {
                $variantFit = 'cover';
            }
            $imageVariants[$sizeName]['fit'] = $variantFit;

            $variantGravity = 'center';
            if (is_array($sizeMetadata['crop'])) {
                if (1 === count($sizeMetadata['crop'])) {
                    $xCropPosition = $sizeMetadata['crop'][0];
                    $variantGravity = $xCropPosition;
                } elseif (2 === count($sizeMetadata['crop'])) {
                    [$xCropPosition, $yCropPosition] = $sizeMetadata['crop'];
                    $variantGravity = "{$yCropPosition}-$xCropPosition";
                }
            }
            $imageVariants[$sizeName]['gravity'] = $variantGravity;
        }

        $imageVariants[Media::FULL_VARIANT_KEY] = [
            'fit' => 'scale-down',
            'width' => '10000',
            'height' => '10000',
            'gravity' => 'center',
        ];

        return $imageVariants;
    }

    public static function getActiveCapabilities(): array
    {
        $activeCapabilities = [];
        if (
            StoreKeeperOptions::isPaymentSyncEnabled()
            && 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes')
        ) {
            $activeCapabilities[] = 'b2s_payment_method';
        }

        if (
            BackofficeCore::isShippingMethodUsed()
        ) {
            $activeCapabilities[] = 'b2s_shipping_method';
        }

        $activeCapabilities[] = 's2b_image_variants';
        $activeCapabilities[] = 's2b_report_product_state';
        $activeCapabilities[] = 's2b_report_system_status';

        if (
            StoreKeeperOptions::isImageCdnEnabled()
        ) {
            $activeCapabilities[] = 's2b_image_cdn';
        }

        return $activeCapabilities;
    }

    public static function getLastHookDate(): ?string
    {
        global $wpdb;

        $select = WebhookLogModel::getSelectHelper()
            ->cols([WebhookLogModel::FIELD_DATE_UPDATED])
            ->orderBy([WebhookLogModel::FIELD_DATE_UPDATED.' DESC'])
            ->limit(1);

        return $wpdb->get_var(WebhookLogModel::prepareQuery($select));
    }

    public static function getLastWebhookLogId(): ?int
    {
        global $wpdb;

        $select = WebhookLogModel::getSelectHelper()
            ->cols(['id'])
            ->orderBy(['date_updated DESC'])
            ->limit(1);

        return $wpdb->get_var(WebhookLogModel::prepareQuery($select));
    }

    public static function getLastTaskId(): ?int
    {
        global $wpdb;

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->orderBy(['date_updated DESC'])
            ->limit(1);

        return $wpdb->get_var(TaskModel::prepareQuery($select));
    }

    public static function getLastSyncRunDate(): ?\DateTime
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
            return DatabaseConnection::formatFromDatabaseDate(
                WooCommerceOptions::get(WooCommerceOptions::LAST_SYNC_RUN)
            );
        }

        return null;
    }

    public static function getLastSuccessSyncRunDate(): ?\DateTime
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::SUCCESS_SYNC_RUN)) {
            return DatabaseConnection::formatFromDatabaseDate(
                WooCommerceOptions::get(WooCommerceOptions::SUCCESS_SYNC_RUN)
            );
        }

        return null;
    }

    public static function getActiveTheme(): array
    {
        $activeTheme = [];

        $theme = wp_get_theme();
        foreach (self::EXTRA_ACTIVE_THEME_FIELD as $activeThemeField) {
            $activeTheme[$activeThemeField] = $theme->get($activeThemeField);
        }

        return $activeTheme;
    }

    public static function getPlugins(): array
    {
        include_once ABSPATH.'/wp-admin/includes/plugin.php';

        return array_values(get_plugins());
    }
}
