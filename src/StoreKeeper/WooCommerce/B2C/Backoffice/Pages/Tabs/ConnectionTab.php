<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ConnectionTab extends AbstractTab
{
    use FormElementTrait;

    const DISCONNECT_ACTION = 'disconnect-action';
    const SAVE_ACTION = 'save-action';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::DISCONNECT_ACTION, [$this, 'disconnectAction']);
        $this->addAction(self::SAVE_ACTION, [$this, 'saveAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        if (StoreKeeperOptions::isConnected()) {
            $this->renderConnected();
        } else {
            $this->renderDisconnected();
        }

        $this->renderStatistics();

        $this->renderSettings();
    }

    private function renderConnected()
    {
        echo $this->getFormStart();

        $url = $this->getActionUrl(self::DISCONNECT_ACTION);
        $api = StoreKeeperOptions::get(StoreKeeperOptions::API_URL);
        echo $this->getFormGroup(
            __('Currently connected to', I18N::DOMAIN),
            $api.' '.$this->getFormLink(
                $url,
                __('Disconnect', I18N::DOMAIN),
                'button-primary'
            )
        );

        echo $this->getFormEnd();
    }

    private function renderDisconnected()
    {
        echo $this->getFormStart();

        $key = WooCommerceOptions::getApiKey();
        echo $this->getFormGroup(
            __('Backoffice API key', I18N::DOMAIN),
            "<input type='text' readonly onfocus='this.select()' onclick='this.select()' value='$key' />"
        );

        $title = __('Steps', I18N::DOMAIN);
        echo $this->getFormGroup('', "<b>$title</b>");

        $steps = [
            '1. '.__('Copy the "Backoffice API Key" from the text area.', I18n::DOMAIN),
            '2. '.__('Log into your admin environment', I18n::DOMAIN),
            '3. '.__('Navigate to settings > technical settings and click on the "webhook" tab.', I18n::DOMAIN),
            '4. '.__('Paste your api key into the field that says "Api key" and click connect.', I18n::DOMAIN),
            '5. '.__('Once done, you should reload this page and you will be fully connected.', I18n::DOMAIN),
        ];
        echo $this->getFormGroup('', join('<br/>', $steps));

        echo $this->getFormEnd();
    }

    private function renderStatistics()
    {
        echo $this->getFormStart();

        echo $this->getFormHeader(__('Sync statistics', I18N::DOMAIN));

        echo $this->getFormGroup(
            __('Tasks in queue', I18N::DOMAIN),
            TaskModel::count([
                'status != :success',
                'status != :failed',
            ], [
                'success' => TaskHandler::STATUS_SUCCESS,
                'failed' => TaskHandler::STATUS_FAILED,
            ])
        );

        echo $this->getFormGroup(
            __('Webhooks log count', I18N::DOMAIN),
            WebhookLogModel::count()
        );

        echo $this->getFormGroup(
            __('Last task processed', I18N::DOMAIN),
            $this->getLastTaskProcessed()
        );

        echo $this->getFormGroup(
            __('Last webhook received', I18N::DOMAIN),
            $this->getLastWebhookReceived()
        );

        echo $this->getFormEnd();
    }

    private function renderSettings()
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        echo $this->getFormstart('post', $url);

        echo $this->getFormHeader(__('Synchronization settings', I18N::DOMAIN));

        $this->renderSyncModeSetting();

        $this->renderPaymentSetting();

        $this->renderBackorderSetting();

        echo $this->getFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        echo $this->getFormEnd();
    }

    private function getLastTaskProcessed(): string
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::SUCCESS_SYNC_RUN)) {
            $date = WooCommerceOptions::get(WooCommerceOptions::SUCCESS_SYNC_RUN);

            return date_create($date)->format('Y-m-d H:i:s');
        } else {
            if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
                return __('No tasks processed (successfully) yet, but the cron is running');
            }
        }

        return __('Never, please check the "cron tab" notice.', I18N::DOMAIN);
    }

    private function getLastWebhookReceived(): string
    {
        global $wpdb;

        $hookDescription = __('Never', I18N::DOMAIN);

        $select = WebhookLogModel::getSelectHelper()
            ->cols(['date_updated'])
            ->orderBy(['date_updated DESC'])
            ->limit(1);

        $lastHook = $wpdb->get_row(WebhookLogModel::prepareQuery($select));
        if ($lastHook) {
            $date = $lastHook->date_updated;
            $hookDescription = date_create($date)->format('Y-m-d H:i:s');
        }

        return $hookDescription;
    }

    public function disconnectAction()
    {
        StoreKeeperOptions::disconnect();
        wp_redirect(remove_query_arg('action'));
    }

    public function saveAction()
    {
        $payment = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        $backorder = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        $mode = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);

        $data = [
            $payment => 'on' === sanitize_key($_POST[$payment]) ? 'yes' : 'no',
            $backorder => 'on' === sanitize_key($_POST[$backorder]) ? 'yes' : 'no',
        ];
        if (!empty($_POST[$mode])) {
            $data[$mode] = sanitize_key($_POST[$mode]);
        } else {
            $data[$mode] = StoreKeeperOptions::SYNC_MODE_FULL_SYNC;
        }

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderSyncModeSetting(): void
    {
        $full = __('Full sync', I18N::DOMAIN);
        $order = __('Order only', I18N::DOMAIN);

        $fullDescription = esc_html__(
            'Products, categories, labels/tags, attributes with options, coupons, orders and customers are being synced',
            I18N::DOMAIN
        );
        $orderDescription = esc_html__(
            'Orders and customers are being exported, order status and product stock with matching skus are being imports',
            I18N::DOMAIN
        );

        $description = <<<HTML
<b>$full:</b> $fullDescription</br>
<b>$order:</b> $orderDescription
HTML;

        $options = [];
        $options[StoreKeeperOptions::SYNC_MODE_FULL_SYNC] = $full;
        $options[StoreKeeperOptions::SYNC_MODE_ORDER_ONLY] = $order;

        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::SYNC_MODE);
        echo $this->getFormGroup(
            __('Synchronization mode', I18N::DOMAIN),
            $this->getFormSelect(
                $name,
                $options,
                StoreKeeperOptions::getSyncMode()
            )
        );

        echo $this->getFormGroup('', $description);
    }

    private function renderPaymentSetting(): void
    {
        $paymentName = StoreKeeperOptions::getConstant(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED);
        echo $this->getFormGroup(
            __('Activate StoreKeeper payments', I18N::DOMAIN),
            $this->getFormCheckbox(
                $paymentName,
                'yes' === StoreKeeperOptions::get($paymentName)
            ).' '.__(
                'When checked, active webshop payment methods from your StoreKeeper backoffice are added to your webshop\'s checkout',
                I18N::DOMAIN
            )
        );
    }

    private function renderBackorderSetting(): void
    {
        $backorderName = StoreKeeperOptions::getConstant(StoreKeeperOptions::NOTIFY_ON_BACKORDER);
        echo $this->getFormGroup(
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
