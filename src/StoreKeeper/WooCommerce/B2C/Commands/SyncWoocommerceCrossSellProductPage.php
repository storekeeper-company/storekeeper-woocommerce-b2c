<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;
use WP_CLI;

class SyncWoocommerceCrossSellProductPage extends AbstractSyncCommand implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    /**
     * Execute this command to sync the cross sell products.
     *
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (key_exists('limit', $assoc_arguments) && key_exists('page', $assoc_arguments)) {
                $this->runWithPagination($assoc_arguments);
            } else {
                throw new BaseException('Limit and start attribute need to be set');
            }
        }
    }

    public function runWithPagination($assoc_arguments)
    {
        $arguments = [
            'limit' => $assoc_arguments['limit'],
            'page' => $assoc_arguments['page'],
            'orderby' => 'ID',
            'order' => 'ASC',
        ];

        $products = wc_get_products($arguments);
        $this->syncCrossSellForProducts($products);
    }

    /**
     * Syncs the cross sell for the given products.
     *
     * @param $products
     *
     * @throws WordpressException
     */
    private function syncCrossSellForProducts($products)
    {
        $this->createProgressBar(count($products), WP_CLI::colorize(
            '%G'.
                sprintf(
                    __('Syncing %s from Storekeeper backoffice', I18N::DOMAIN),
                    __('cross-sell products', I18N::DOMAIN)
                )
                .'%n'
            )
        );
        foreach ($products as $index => $product) {
            $this->logger->debug(
                'Processing product',
                [
                    'post_id' => $product->get_id(),
                ]
            );
            $this->setProductCrossSell($product);
            $this->logger->notice(
                'Added cross sell products to product',
                [
                    'post_id' => $product->get_id(),
                ]
            );

            $this->tickProgressBar();
        }

        $this->endProgressBar();
    }

    /**
     * Sets the cross sell for the given product.
     *
     * @param $product
     *
     * @throws WordpressException
     */
    private function setProductCrossSell(&$product)
    {
        $shop_product_id = get_post_meta($product->get_id(), 'storekeeper_id', true);

        if (!$shop_product_id) {
            $this->logger->warning(
                'No shop_product_id set',
                [
                    'name' => $product->get_name(),
                    'post_id' => $product->get_id(),
                ]
            );

            return;
        }

        $cross_sell_ids = [];
        $not_found_cross_sell_ids = [];

        $ShopModule = $this->api->getModule('ShopModule');

        $cross_sell_shop_product_ids = $ShopModule->getCrossSellShopProductIds((int) $shop_product_id);

        foreach ($cross_sell_shop_product_ids as $cross_sell_shop_product_id) {
            // Checking is we can find the product by sku
            $product_tmp = ProductImport::findBackendShopProductId($cross_sell_shop_product_id);
            if (false !== $product_tmp) {
                $this->logger->debug('[cross sell] Found product with id '.$product_tmp->ID, [$product_tmp]);
                $cross_sell_ids[] = $product_tmp->ID;
            } else {
                $not_found_cross_sell_ids[] = $cross_sell_shop_product_id;
            }
        }

        $product->set_cross_sell_ids($cross_sell_ids);
        $product->save();

        if (count($not_found_cross_sell_ids) > 0) {
            $this->logger->warning(
                'Could not find products',
                [
                    'from' => $shop_product_id,
                    'shop_product_ids' => $not_found_cross_sell_ids,
                ]
            );
        }
    }
}
