<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressRestRequestWrapper;

class InfoHandler
{
    const EXTRA_BLOG_INFO_FIELDS = [
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

    const EXTRA_ACTIVE_THEME_FIELD = [
        'Name',
        'Description',
        'Author',
        'Version',
        'ThemeURI',
        'AuthorURI',
        'Status',
        'Tags',
    ];

    const VENDOR = 'StoreKeeper';
    const PLATFORM_NAME = 'Wordpress';
    const SOFTWARE_NAME = 'storekeeper-woocommerce-b2c';

    /**
     * @var WordpressRestRequestWrapper
     */
    private $wrappedRequest;

    /**
     * InitHandler constructor.
     */
    public function __construct(WordpressRestRequestWrapper $request)
    {
        $this->wrappedRequest = $request;
    }

    public function run(): array
    {
        return self::gatherInformation();
    }

    public static function gatherInformation()
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

    public static function getExtras()
    {
        $extras = [
            'plugins' => self::getPlugins(),
            'active_theme' => self::getActiveTheme(),
            'sync_mode' => StoreKeeperOptions::getSyncMode(),
            'last_sync_run_date' => self::getLastSyncRunDate(),
            'last_hook_date' => self::getLastHookDate(),
            'last_hook_id' => self::getLastWebhookLogId(),
            'last_task_id' => self::getLastTaskId(),
            'task_quantity' => TaskModel::countTasks(),
            'task_failed_quantity' => TaskModel::countFailedTasks(),
            'task_successful_quantity' => TaskModel::countSuccessfulTasks(),
            'hook_quantity' => WebhookLogModel::count(),
            'active_capability' => self::getActiveCapabilities(),
            'image_variants' => self::getImageVariants(),
        ];

        foreach (self::EXTRA_BLOG_INFO_FIELDS as $blogInfoField) {
            get_bloginfo($blogInfoField);
            // Getting the blog information twice seems to ensure `atom_url` returns correctly.
            // No idea why this is the case. But already spend an hour on trying to find out why.
            $extras[$blogInfoField] = get_bloginfo($blogInfoField);
        }

        return $extras;
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
                    [ $xCropPosition, $yCropPosition ] = $sizeMetadata['crop'];
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
            StoreKeeperOptions::isPaymentSyncEnabled() &&
            'yes' === StoreKeeperOptions::get(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes')
        ) {
            $activeCapabilities[] = 'b2s_payment_method';
        }

        $activeCapabilities[] = 's2b_image_variants';

        return $activeCapabilities;
    }

    public static function getLastHookDate(): ?string
    {
        global $wpdb;

        $select = WebhookLogModel::getSelectHelper()
            ->cols(['date_updated'])
            ->orderBy(['date_updated DESC'])
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

    public static function getLastSyncRunDate(): ?string
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
            return WooCommerceOptions::get(WooCommerceOptions::LAST_SYNC_RUN);
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
