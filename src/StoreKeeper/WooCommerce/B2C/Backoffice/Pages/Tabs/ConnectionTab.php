<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\InfoEndpoint;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskRateCalculator;

class ConnectionTab extends AbstractTab
{
    use FormElementTrait;

    public const DISCONNECT_ACTION = 'disconnect-action';
    public const CONNECT_ACTION = 'connect-action';
    public const SAVE_ACTION = 'save-action';

    public const PLUGIN_INITIALIZE_URL = STOREKEEPER_WOOCOMMERCE_INTEGRATIONS.'/sk_connect/sales_channel/init_plugin_connect';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::DISCONNECT_ACTION, [$this, 'disconnectAction']);
        $this->addAction(self::CONNECT_ACTION, [$this, 'connectAction']);
        $this->addAction(self::SAVE_ACTION, [$this, 'saveAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->showPossibleWarnings();

        if (StoreKeeperOptions::isConnected()) {
            $this->renderConnected();
        } else {
            $this->renderDisconnected();
        }

        $this->renderStatistics();

        $this->renderSettings();
    }

    private function showPossibleWarnings(): void
    {
        if (array_key_exists('shipping-method-unsupported', $_REQUEST)) {
            AdminNotices::showWarning(__('Shipping method synchronization is not supported, please upgrade to a higher package.', I18N::DOMAIN));
        }
    }

    private function renderConnected()
    {
        $this->renderFormStart();

        $url = $this->getActionUrl(self::DISCONNECT_ACTION);
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        $this->renderFormGroup(
            __('Currently connected to', I18N::DOMAIN),
            esc_html($api).' '.$this->getFormLink(
                esc_url($url),
                __('Disconnect', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function renderDisconnected()
    {
        $this->renderFormStart();

        if (STOREKEEPER_WOOCOMMERCE_INTEGRATIONS_USE_FLAG) {
            $url = $this->getActionUrl(self::CONNECT_ACTION);
            $this->renderFormGroup(
                __('Backoffice connection', I18N::DOMAIN),
                $this->getFormLink(
                    esc_url($url),
                    __('Connect to StoreKeeper', I18N::DOMAIN),
                    'button-primary'
                )
            );
        } else {
            $this->renderFormStart();

            $key = esc_attr(WooCommerceOptions::getApiKey());
            $this->renderFormGroup(
                __('Backoffice API key', I18N::DOMAIN),
                "<input type='text' readonly onfocus='this.select()' onclick='this.select()' value='$key' />"
            );

            $title = __('Steps', I18N::DOMAIN);
            $this->renderFormGroup('', "<b>$title</b>");

            $steps = [
                '1. '.__('Copy the "Backoffice API Key" from the text area.', I18N::DOMAIN),
                '2. '.__('Log into your admin environment', I18N::DOMAIN),
                '3. '.__('Navigate to settings > technical settings and click on the "webhook" tab.', I18N::DOMAIN),
                '4. '.__('Paste your api key into the field that says "Api key" and click connect.', I18N::DOMAIN),
                '5. '.__('Once done, you should reload this page and you will be fully connected.', I18N::DOMAIN),
            ];
            $this->renderFormGroup('', implode('<br/>', $steps));

            $this->renderFormEnd();
        }

        $this->renderFormEnd();
    }

    private function renderStatistics()
    {
        $now = DateTimeHelper::currentDateTime();
        $calculator = new TaskRateCalculator($now);
        $incomingRate = $calculator->countIncoming();
        $processedRate = $calculator->calculateProcessed();
        $this->renderFormStart();

        $this->renderFormHeader(__('Sync statistics', I18N::DOMAIN));

        $this->renderFormGroup(
            __('Tasks in queue', I18N::DOMAIN),
            TaskModel::count(['status = :status'], ['status' => TaskHandler::STATUS_NEW]).
            sprintf(
                __(
                    ' (new: %s p/h, processing rate: %s p/h)',
                    I18N::DOMAIN
                ),
                $incomingRate,
                $processedRate
            )
        );

        $this->renderFormGroup(
            __('Webhooks log count', I18N::DOMAIN),
            esc_html(WebhookLogModel::count())
        );

        $this->renderFormGroup(
            __('Last task processed', I18N::DOMAIN),
            esc_html($this->getLastTaskProcessed())
        );

        $this->renderFormGroup(
            __('Last webhook received', I18N::DOMAIN),
            esc_html($this->getLastWebhookReceived())
        );

        $this->renderFormEnd();
    }

    private function renderSettings()
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        $this->renderFormStart('post', $url);

        $this->renderFormHeader(__('Synchronization settings', I18N::DOMAIN));

        $this->renderSyncModeSetting();
        $this->renderOrderSyncFromDate();
        $this->renderSeoSetting();
        $this->renderPaymentSetting();
        $this->renderShippingMethodSetting();
        $this->renderBackorderSetting();
        $this->renderBarcodeModeSetting();
        $this->renderCategoryOptions();

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    private function getLastTaskProcessed(): string
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::SUCCESS_SYNC_RUN)) {
            $date = WooCommerceOptions::get(WooCommerceOptions::SUCCESS_SYNC_RUN);
            $datetime = DatabaseConnection::formatFromDatabaseDate($date);

            return DateTimeHelper::formatForDisplay($datetime);
        }

        if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
            return __('No tasks processed (successfully) yet, but the cron is running');
        }

        return __('Never, please check the "cron tab" notice.', I18N::DOMAIN);
    }

    private function getLastWebhookReceived(): string
    {
        global $wpdb;

        $hookDescription = __('Never', I18N::DOMAIN);

        $select = WebhookLogModel::getSelectHelper()
            ->cols([WebhookLogModel::FIELD_DATE_UPDATED])
            ->orderBy([WebhookLogModel::FIELD_DATE_UPDATED.' DESC'])
            ->limit(1);

        $lastHook = $wpdb->get_row(WebhookLogModel::prepareQuery($select));

        if ($lastHook) {
            $datetime = DatabaseConnection::formatFromDatabaseDate($lastHook->date_updated);
            $hookDescription = DateTimeHelper::formatForDisplay($datetime);
        }

        return $hookDescription;
    }

    public function disconnectAction()
    {
        StoreKeeperOptions::disconnect();
        wp_redirect(remove_query_arg('action'));
    }

    public function connectAction(): void
    {
        $woocommerceToken = WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_INFO_TOKEN);
        if (is_null($woocommerceToken)) {
            $woocommerceToken = WooCommerceOptions::createToken();
            WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_INFO_TOKEN, $woocommerceToken);
        }

        $pluginInitUrl = add_query_arg([
            'rest_info_url' => urlencode(add_query_arg([
                    'api-token' => $woocommerceToken,
                ],
                rest_url(EndpointLoader::getFullNamespace().'/'.InfoEndpoint::ROUTE)
            )),
            '_locale' => Language::getSiteLanguageIso2(),
        ], self::PLUGIN_INITIALIZE_URL);

        wp_redirect($pluginInitUrl);
    }

    public function saveAction()
    {
        $shouldAddShippingMethodWarning = false;

        $payment = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        $shippingMethod = StoreKeeperOptions::getConstant(StoreKeeperOptions::SHIPPING_METHOD_ACTIVATED);
        $backorder = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        $seoHandler = StoreKeeperOptions::getConstant(StoreKeeperOptions::SEO_HANDLER);
        $mode = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);
        $orderSyncFromDate = StoreKeeperOptions::getConstant(StoreKeeperOptions::ORDER_SYNC_FROM_DATE);
        $barcode = StoreKeeperOptions::getConstant(StoreKeeperOptions::BARCODE_MODE);
        $categoryHtml = StoreKeeperOptions::getConstant(StoreKeeperOptions::CATEGORY_DESCRIPTION_HTML);

        $data = [
            $backorder => 'on' === sanitize_key($_POST[$backorder]) ? 'yes' : 'no',
            $categoryHtml => 'on' === sanitize_key($_POST[$categoryHtml]) ? 'yes' : 'no',
        ];

        if (in_array($_POST[$mode], StoreKeeperOptions::MODES_WITH_PAYMENTS, true)) {
            if (!StoreKeeperOptions::isPaymentSyncEnabled()) {
                // Retain the old value
                $data[$payment] = StoreKeeperOptions::get($payment);
            } else {
                $data[$payment] = 'on' === sanitize_key($_POST[$payment]) ? 'yes' : 'no';
            }
        }

        if (in_array($_POST[$mode], StoreKeeperOptions::MODES_WITH_SHIPPING_METHODS, true)) {
            $shippingMethodOldValue = StoreKeeperOptions::get($shippingMethod, 'no');
            if (!StoreKeeperOptions::isShippingMethodAllowedForCurrentSyncMode()) {
                // Retain the old value
                $data[$shippingMethod] = $shippingMethodOldValue;
            } else {
                $shippingMethodNewValue = 'on' === sanitize_key($_POST[$shippingMethod]) ? 'yes' : 'no';
                $data[$shippingMethod] = $shippingMethodNewValue;
                if ('yes' === $shippingMethodNewValue && $shippingMethodNewValue !== $shippingMethodOldValue) {
                    $isShippingMethodSyncSupported = true;
                    try {
                        $api = StoreKeeperApi::getApiByAuthName();
                        $shopModule = $api->getModule('ShopModule');
                        $shopModule->listShippingMethodsForHooks();
                    } catch (GeneralException $exception) {
                        $isShippingMethodSyncSupported = false;
                    }

                    if (!$isShippingMethodSyncSupported) {
                        $shouldAddShippingMethodWarning = true;
                        $data[$shippingMethod] = 'no';
                    }
                }

                if ('yes' === $data[$shippingMethod]) {
                    TaskHandler::scheduleTask(
                        TaskHandler::SHIPPING_METHOD_IMPORT,
                        0,
                        [],
                        true
                    );
                } else {
                    $woocommerceZoneIds = ShippingZoneModel::getWoocommerceZoneIds();
                    foreach ($woocommerceZoneIds as $woocommerceZoneId) {
                        $woocommerceZone = new \WC_Shipping_Zone($woocommerceZoneId);
                        $woocommerceZone->delete(true);
                    }

                    ShippingZoneModel::purge();
                    ShippingMethodModel::purge();
                }
            }
        }

        if (!empty($_POST[$mode])) {
            $data[$mode] = sanitize_key($_POST[$mode]);
        } else {
            $data[$mode] = StoreKeeperOptions::SYNC_MODE_FULL_SYNC;
        }

        if (!empty($_POST[$seoHandler])) {
            $data[$seoHandler] = sanitize_key($_POST[$seoHandler]);
        } else {
            $data[$seoHandler] = Seo::STOREKEEPER_HANDLER;
        }

        if (!empty($_POST[$orderSyncFromDate])) {
            $data[$orderSyncFromDate] = sanitize_key($_POST[$orderSyncFromDate]);
        }

        if (!empty($_POST[$barcode])) {
            $data[$barcode] = sanitize_key($_POST[$barcode]);
        } else {
            $data[$barcode] = StoreKeeperOptions::BARCODE_META_FALLBACK;
        }

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        $url = remove_query_arg('action');
        if ($shouldAddShippingMethodWarning) {
            $url = add_query_arg([
                'shipping-method-unsupported' => 1,
            ], $url);
        } else {
            $url = remove_query_arg('shipping-method-unsupported', $url);
        }
        wp_redirect($url);
    }

    private function renderSyncModeSetting(): void
    {
        $full = __('Full sync', I18N::DOMAIN);
        $order = __('Order only', I18N::DOMAIN);
        $product = __('Products only', I18N::DOMAIN);
        $none = __('No sync', I18N::DOMAIN);

        $fullDescription = esc_html__(
            'Products, categories, labels/tags, attributes with options, coupons, orders and customers are being synced',
            I18N::DOMAIN
        );
        $orderDescription = esc_html__(
            'Orders and customers are being exported, order status and product stock with matching skus are being imports',
            I18N::DOMAIN
        );
        $productDescription = esc_html__(
            'Products, categories, labels/tags, attributes with options, and coupons are being synced',
            I18N::DOMAIN
        );
        $noneDescription = esc_html__(
            'Nothing will be synced',
            I18N::DOMAIN
        );

        $description = <<<HTML
<strong>$full:</strong> $fullDescription</br>
<strong>$order:</strong> $orderDescription</br>
<strong>$product:</strong> $productDescription</br>
<strong>$none:</strong> $noneDescription
HTML;

        $options = [];
        $options[StoreKeeperOptions::SYNC_MODE_FULL_SYNC] = $full;
        $options[StoreKeeperOptions::SYNC_MODE_ORDER_ONLY] = $order;
        $options[StoreKeeperOptions::SYNC_MODE_PRODUCT_ONLY] = $product;
        $options[StoreKeeperOptions::SYNC_MODE_NONE] = $none;

        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);
        $this->renderFormGroup(
            __('Synchronization mode', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getSyncMode()
            )
        );

        $this->renderFormGroup('', $description);
    }

    private function renderSeoSetting(): void
    {
        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::SEO_HANDLER);

        $options = [
            Seo::STOREKEEPER_HANDLER => esc_html__('Storekeeper SEO handler', I18N::DOMAIN),
            Seo::NO_HANDLER => esc_html__('Don\'t handle SEO', I18N::DOMAIN),
        ];

        if (PluginStatus::isYoastSeoEnabled()) {
            $options = [Seo::YOAST_HANDLER => esc_html__('Yoast SEO handler', I18N::DOMAIN)] + $options;
        }

        if (PluginStatus::isRankMathSeoEnabled()) {
            $options = [Seo::RANK_MATH_HANDLER => esc_html__('Rank Math SEO handler', I18N::DOMAIN)] + $options;
        }

        $this->renderFormGroup(
            __('SEO handler', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getSeoHandler()
            )
        );
    }

    private function renderBarcodeModeSetting(): void
    {
        $options = StoreKeeperOptions::getBarcodeOptions();
        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::BARCODE_MODE);
        $this->renderFormGroup(
            __('Barcode meta key', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getBarcodeMode()
            )
        );

        $description = esc_html__(
            'Changing this settings allows to use various EAN, barcode plugins. After changing this setting all products need to be synchronized again.',
            I18N::DOMAIN
        );
        $this->renderFormGroup('', $description);
    }

    private function renderOrderSyncFromDate(): void
    {
        $orderSyncFromDateName = StoreKeeperOptions::getConstant(StoreKeeperOptions::ORDER_SYNC_FROM_DATE);

        $this->renderFormGroup(
            __('Order sync from date', I18N::DOMAIN),
            $this->getFormInput($orderSyncFromDateName, '', StoreKeeperOptions::get($orderSyncFromDateName, ''), '', 'date')
        );

        $description = esc_html__(
            'Order created before the date set will not be synchronized to backoffice.',
            I18N::DOMAIN
        );
        $this->renderFormGroup('', $description);
    }

    private function renderPaymentSetting(): void
    {
        $paymentName = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        $extraInfo = '';
        if (!StoreKeeperOptions::isPaymentSyncEnabled()) {
            $extraInfo = '<br><small>'.__('Payments are disabled in currently selected Synchronization mode').'</small>';
        }
        $this->renderFormGroup(
            __('Activate StoreKeeper payments', I18N::DOMAIN),
            $this->getFormCheckbox(
                $paymentName,
                'yes' === StoreKeeperOptions::get($paymentName) && StoreKeeperOptions::isPaymentSyncEnabled(),
                StoreKeeperOptions::isPaymentSyncEnabled() ? '' : 'disabled',
            ).' '.__(
                'When checked, active webshop payment methods from your StoreKeeper backoffice are added to your webshop\'s checkout',
                I18N::DOMAIN
            ).$extraInfo
        );
    }

    private function renderShippingMethodSetting(): void
    {
        $shippingMethodName = StoreKeeperOptions::getConstant(StoreKeeperOptions::SHIPPING_METHOD_ACTIVATED);
        $extraInfo = '';
        if (!StoreKeeperOptions::isShippingMethodAllowedForCurrentSyncMode()) {
            $extraInfo = '<br><small>'.__('Shipping methods are disabled in currently selected Synchronization mode').'</small>';
        }
        $this->renderFormGroup(
            __('Use StoreKeeper shipping methods', I18N::DOMAIN),
            $this->getFormCheckbox(
                $shippingMethodName,
                'yes' === StoreKeeperOptions::get($shippingMethodName, 'no') && StoreKeeperOptions::isShippingMethodAllowedForCurrentSyncMode(),
                StoreKeeperOptions::isShippingMethodAllowedForCurrentSyncMode() ? '' : 'disabled',
            ).' '.__(
                'When checked, shipping countries and methods from your StoreKeeper backoffice are added to your WooCommerce settings and webshop\'s checkout',
                I18N::DOMAIN
            ).$extraInfo
        );
    }

    private function renderCategoryOptions(): void
    {
        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::CATEGORY_DESCRIPTION_HTML);
        $this->renderFormGroup(
            __('Import category description as HTML', I18N::DOMAIN),
            $this->getFormCheckbox(
                $name,
                StoreKeeperOptions::getBoolOption($name, false)
            ).' '.__(
                'It will import the category descriptions as html, otherwise plain text. It requires a theme support for rendering it correctly.',
                I18N::DOMAIN
            )
        );
    }

    private function renderBackorderSetting(): void
    {
        $backorderName = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        $this->renderFormGroup(
            __('Notify when backorderable', I18N::DOMAIN),
            $this->getFormCheckbox(
                $backorderName,
                'yes' === StoreKeeperOptions::get($backorderName)
            ).' '.__(
                "When checked, imported or updated products will have the backorder status set to 'Allow, but notify customer', else I will be set to 'Allow'",
                I18N::DOMAIN
            )
        );
    }
}
