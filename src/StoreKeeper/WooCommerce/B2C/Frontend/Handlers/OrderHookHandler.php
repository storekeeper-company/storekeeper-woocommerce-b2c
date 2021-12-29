<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class OrderHookHandler
{
    public function addOrderStatusLink(int $orderId)
    {
        $order = wc_get_order($orderId);
        $orderPageStatusUrl = $order->get_meta(OrderImport::ORDER_PAGE_META_KEY, true);
        $storekeeperId = $order->get_meta('storekeeper_id', true);
        if (!empty($storekeeperId) && empty($orderPageStatusUrl)) {
            try {
                $apiWrapper = StoreKeeperApi::getApiByAuthName();
                $shopModule = $apiWrapper->getModule('ShopModule');
                $storekeeperOrder = $shopModule->getOrder($storekeeperId, null);
                if (isset($storekeeperOrder['shipped_item_no'])) {
                    $shippedItem = (int) $storekeeperOrder['shipped_item_no'];
                    if ($shippedItem > 0) {
                        OrderImport::fetchOrderStatusUrl($order, $storekeeperId);
                    }
                }

                $orderPageStatusUrl = $order->get_meta(OrderImport::ORDER_PAGE_META_KEY, true);
            } catch (GeneralException $generalException) {
                LoggerFactory::create('order')->error($generalException->getMessage(), $generalException->getTrace());
            }
        }

        if (!empty($orderPageStatusUrl)) {
            $trackMessage = esc_html__('Your order is ready for track and trace.', I18N::DOMAIN);
            $trackLink = wp_kses(
                '<a href="'.$orderPageStatusUrl.'">'.
                esc_html__('View here').
                '</a>',
                HtmlEscape::ALLOWED_ANCHOR
            );
            echo <<<HTML
        $trackMessage $trackLink
HTML;
        }
    }
}
