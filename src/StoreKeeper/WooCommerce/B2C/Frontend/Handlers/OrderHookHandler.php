<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;

class OrderHookHandler
{
    public const STOREKEEPER_ORDER_TRACK_HOOK = 'storekeeper_order_tracking_message';

    public function addOrderStatusLink(\WC_Order $order): void
    {
        $storekeeperId = $order->get_meta('storekeeper_id', true);

        $orderPageStatusUrl = null;

        if (!empty($storekeeperId)) {
            try {
                $orderPageStatusUrl = OrderImport::ensureOrderStatusUrl($order, $storekeeperId);
            } catch (\Throwable $throwable) {
                LoggerFactory::create('order')->error($throwable->getMessage(), ['trace' => $throwable->getTrace()]);
            }
        }

        if (!empty($orderPageStatusUrl)) {
            $trackMessage = esc_html__('Your order is ready for track and trace.', I18N::DOMAIN);

            $orderTrackingHtml = apply_filters(self::STOREKEEPER_ORDER_TRACK_HOOK, $trackMessage, $orderPageStatusUrl);
            echo <<<HTML
        $orderTrackingHtml
HTML;
        }
    }

    /**
     * Creates a default order tracking message and to be overriden by storekeeper_order_tracking_message hook.
     */
    public function createOrderTrackingMessage(string $message, ?string $url = null): string
    {
        if (!is_null($url)) {
            $link = wp_kses(
                '<a href="'.$url.'" target="_blank">'.
                esc_html__('View here').
                '</a>',
                HtmlEscape::ALLOWED_ANCHOR
            );

            return <<<HTML
        $message $link
HTML;
        }

        return <<<HTML
        $message
HTML;
    }
}
