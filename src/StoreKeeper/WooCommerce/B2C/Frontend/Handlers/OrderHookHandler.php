<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Frontend\Filters\OrderTrackingMessage;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;

class OrderHookHandler
{
    public function addOrderStatusLink(\WC_Order $order): void
    {
        $storekeeperId = $order->get_meta('storekeeper_id', true);

        $orderPageStatusUrl = null;

        if (!empty($storekeeperId)) {
            try {
                $orderPageStatusUrl = OrderImport::ensureOrderStatusUrl($order, $storekeeperId);
            } catch (\Throwable $throwable) {
                LoggerFactory::create('order')->error($throwable->getMessage(), ['trace' => $throwable->getTraceAsString()]);
            }
        }

        if (!empty($orderPageStatusUrl)) {
            $message = wp_kses(
                sprintf(__('To check your parcel status, go to <a href="%s">Track & Trace page</a>.', I18N::DOMAIN), $orderPageStatusUrl),
                HtmlEscape::ALLOWED_ANCHOR
            );
            $orderTrackingHtml = apply_filters(OrderTrackingMessage::getTag(), $message, $orderPageStatusUrl);
            echo $orderTrackingHtml;
        }
    }

    /**
     * Creates a default order tracking message and to be overriden by storekeeper_order_tracking_message hook.
     */
    public function createOrderTrackingMessage(string $message): string
    {
        return <<<HTML
    <div class='storekeeeper-track-trace-box'>
        $message
    </div>
HTML;
    }

    public function addEmballageTaxRateId(\WC_Order_Item_Fee $orderItemFee, $feeKey, object $fee, \WC_Order $order): void
    {
        $feeData = (array) $fee;
        if (isset($feeData[OrderExport::IS_EMBALLAGE_FEE_KEY])) {
            $emballageTaxRateId = $feeData[OrderExport::TAX_RATE_ID_FEE_KEY] ?? null;
            if ($emballageTaxRateId) {
                $orderItemFee->add_meta_data(OrderExport::EMBALLAGE_TAX_RATE_ID_META_KEY, $emballageTaxRateId);
            }
        }
    }
}
