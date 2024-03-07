<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\OverlayRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Commands\AbstractSyncCommand;
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
use StoreKeeper\WooCommerce\B2C\Commands\WebCommandRunner;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class SynCheckTab extends AbstractTab
{
    use FormElementTrait;

    public const SYNC_ACTION = 'sync-action';

    public const FULL_TYPE = 'full';
    public const SHOP_INFO_TYPE = 'shop-info';
    public const COUPON_CODES_TYPE = 'coupon-codes';
    public const CATEGORIES_TYPE = 'categories';
    public const TAGS_TYPE = 'tags';
    public const ATTRIBUTES_TYPE = 'attributes';
    public const FEATURED_ATTRIBUTES_TYPE = 'featured-attributes';
    public const ATTRIBUTE_OPTIONS_TYPE = 'attribute-options';
    public const PRODUCTS_TYPE = 'products';
    public const UP_SELL_PRODUCTS_TYPE = 'up-sell-products';
    public const CROSS_SELL_PRODUCTS_TYPE = 'cross-sell-products';
    public const SHIPPING_METHODS_TYPE = 'shipping-methods';

    public const SYNC_TYPES = [
        self::FULL_TYPE => SyncWoocommerceFullSync::class,
        self::SHOP_INFO_TYPE => SyncWoocommerceShopInfo::class,
        self::COUPON_CODES_TYPE => SyncWoocommerceCouponCodes::class,
        self::CATEGORIES_TYPE => SyncWoocommerceCategories::class,
        self::TAGS_TYPE => SyncWoocommerceTags::class,
        self::ATTRIBUTES_TYPE => SyncWoocommerceAttributes::class,
        self::FEATURED_ATTRIBUTES_TYPE => SyncWoocommerceFeaturedAttributes::class,
        self::ATTRIBUTE_OPTIONS_TYPE => SyncWoocommerceAttributeOptions::class,
        self::PRODUCTS_TYPE => SyncWoocommerceProducts::class,
        self::UP_SELL_PRODUCTS_TYPE => SyncWoocommerceUpsellProducts::class,
        self::CROSS_SELL_PRODUCTS_TYPE => SyncWoocommerceCrossSellProducts::class,
        self::SHIPPING_METHODS_TYPE => SyncWoocommerceShippingMethods::class,
    ];

    public const OTHER_COMMANDS = [
        SyncWoocommerceAttributeOptionPage::class,
        SyncWoocommerceCrossSellProductPage::class,
        SyncWoocommerceUpsellProductPage::class,
        SyncWoocommerceProductPage::class,
    ];

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::SYNC_ACTION, [$this, 'handleSync']);
    }

    private function getTypeLabel(string $type)
    {
        switch ($type) {
            case self::FULL_TYPE:
                return __('Full sync', I18N::DOMAIN);
            case self::SHOP_INFO_TYPE:
                return __('Shop info sync', I18N::DOMAIN);
            case self::COUPON_CODES_TYPE:
                return __('Coupon code sync', I18N::DOMAIN);
            case self::CATEGORIES_TYPE:
                return __('Categories sync', I18N::DOMAIN);
            case self::TAGS_TYPE:
                return __('Tags/labels sync', I18N::DOMAIN);
            case self::ATTRIBUTES_TYPE:
                return __('Attributes sync', I18N::DOMAIN);
            case self::FEATURED_ATTRIBUTES_TYPE:
                return __('Featured attributes sync', I18N::DOMAIN);
            case self::ATTRIBUTE_OPTIONS_TYPE:
                return __('Attribute options sync', I18N::DOMAIN);
            case self::PRODUCTS_TYPE:
                return __('Products sync', I18N::DOMAIN);
            case self::UP_SELL_PRODUCTS_TYPE:
                return __('Upsell product sync', I18N::DOMAIN);
            case self::CROSS_SELL_PRODUCTS_TYPE:
                return __('Cross sell product sync', I18N::DOMAIN);
            case self::SHIPPING_METHODS_TYPE:
                return __('Shipping methods sync', I18N::DOMAIN);
            default:
                return $type;
        }
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderSuccess();

        $this->renderFormStart();

        $this->renderFormNote(
            __(
                'Some synchronizations can take up to 24 hours to complete, leave the page open until its done.',
                I18N::DOMAIN
            ),
            'text-information'
        );

        $this->renderFormHeader(__('Sync controls', I18N::DOMAIN));

        $startSync = __('Start sync', I18N::DOMAIN);
        foreach (self::SYNC_TYPES as $type => $class) {
            if (self::SHIPPING_METHODS_TYPE !== $type || BackofficeCore::isShippingMethodUsed()) {
                $url = add_query_arg(
                    'type',
                    $type,
                    $this->getActionUrl(self::SYNC_ACTION)
                );
                $this->renderFormGroup(
                    $this->getTypeLabel($type),
                    $this->getFormLink($url, $startSync, 'button')
                );
            }
        }

        $this->renderFormEnd();
    }

    private function renderSuccess()
    {
        if (array_key_exists('success-message', $_REQUEST)) {
            $message = sanitize_text_field($_REQUEST['success-message']);
            AdminNotices::showSuccess($message);
        }
    }

    public function handleSync()
    {
        $type = $this->getRequestType();
        if ($type && $class = self::SYNC_TYPES[$type]) {
            $label = $this->getTypeLabel($type);

            $overlay = new OverlayRenderer($class);
            $overlay->start(
                sprintf(__('Executing %s.', I18N::DOMAIN), strtolower($label)),
                __('This can take up to 24 hours depending of your data, please do not close the tab.', I18N::DOMAIN)
            );

            try {
                $this->runSync($class, $overlay);

                $overlay->renderMessage(
                    sprintf(
                        __('Done executing %s', I18N::DOMAIN),
                        strtolower($label)
                    )
                );

                $successMessage = sprintf(__('%s successful', I18N::DOMAIN), $label);

                $url = remove_query_arg(['action', 'type']);
                $url = add_query_arg('success-message', $successMessage, $url);
                $overlay->endWithRedirect($url);
            } catch (\Throwable $throwable) {
                $overlay->renderError(
                    $throwable->getMessage(),
                    $throwable->getTraceAsString()
                );
                $overlay->end();
            }
        } else {
            wp_redirect(remove_query_arg(['action', 'type']));
        }
    }

    private function runSync($className, OverlayRenderer $overlay)
    {
        IniHelper::setIni(
            'max_execution_time',
            60 * 60 * 24, // Time in hours
            [$overlay, 'renderMessage']
        );

        $runner = new WebCommandRunner();
        foreach (self::SYNC_TYPES as $type => $typeClassName) {
            $runner->addCommandClass($typeClassName);
        }
        foreach (self::OTHER_COMMANDS as $typeClassName) {
            $runner->addCommandClass($typeClassName);
        }

        /** @var AbstractSyncCommand $class */
        $class = new $className();
        $runner->execute($class::getCommandName());
    }

    private function getRequestType()
    {
        if (isset($_REQUEST['type'])) {
            return sanitize_key($_REQUEST['type']);
        }

        return null;
    }
}
