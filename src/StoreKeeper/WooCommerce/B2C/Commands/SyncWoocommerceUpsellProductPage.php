<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Helpers\WpCliHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;

class SyncWoocommerceUpsellProductPage extends AbstractSyncCommand
{
    protected $isProgressBarShown = true;

    public function setIsProgressBarShown(bool $isProgressBarShown): void
    {
        $this->isProgressBarShown = $isProgressBarShown;
    }

    public static function getShortDescription(): string
    {
        return __('Sync cross-sell products with limit and offset.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync upsell products from Storekeeper Backoffice with limit and offset.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'page',
                'description' => __('Skip other upsell products and synchronize from specified starting point.', I18N::DOMAIN),
                'optional' => false,
            ],
            [
                'type' => 'assoc',
                'name' => 'limit',
                'description' => __('Determines how many upsell products will be synchronized from the starting point.', I18N::DOMAIN),
                'optional' => false,
            ],
            [
                'type' => 'flag',
                'name' => 'hide-progress-bar',
                'description' => __('Hide displaying of progress bar while executing command.', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    /**
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (array_key_exists('hide-progress-bar', $assoc_arguments)) {
                $this->setIsProgressBarShown(false);
            }
            if (array_key_exists('limit', $assoc_arguments) && array_key_exists('page', $assoc_arguments)) {
                $this->runWithPagination($assoc_arguments);
            } else {
                throw new BaseException('Limit and page attribute need to be set');
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
        $this->syncUpsellForProducts($products);
    }

    /**
     * Syncs the upsell for the given products.
     *
     * @throws WordpressException
     */
    private function syncUpsellForProducts($products)
    {
        if ($this->isProgressBarShown) {
            $this->createProgressBar(count($products), WpCliHelper::setGreenOutputColor(sprintf(
                __('Syncing %s from Storekeeper backoffice', I18N::DOMAIN),
                __('upsell products', I18N::DOMAIN)
            )));
        }
        foreach ($products as $index => $product) {
            $this->logger->debug(
                'Processing product',
                [
                    'post_id' => $product->get_id(),
                ]
            );

            $this->setProductUpsell($product);

            $this->logger->notice(
                'Added upsell products to product',
                [
                    'post_id' => $product->get_id(),
                ]
            );
            if ($this->isProgressBarShown) {
                $this->tickProgressBar();
            }
        }

        if ($this->isProgressBarShown) {
            $this->endProgressBar();
        }
    }

    /**
     * Sets the upsell for the given product.
     *
     * @throws WordpressException
     */
    private function setProductUpsell(&$product)
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

        $upsell_ids = [];
        $not_found_upsell_ids = [];

        $ShopModule = $this->api->getModule('ShopModule');

        $upsell_shop_product_ids = $ShopModule->getUpsellShopProductIds($shop_product_id);

        foreach ($upsell_shop_product_ids as $upsell_shop_product_id) {
            // Checking is we can find the product by sku
            $product_tmp = ProductImport::findBackendShopProductId($upsell_shop_product_id);
            if (false !== $product_tmp) {
                $this->logger->debug('[upsell] Found product with id '.$product_tmp->ID, [$product_tmp]);
                $upsell_ids[] = $product_tmp->ID;
            } else {
                $not_found_upsell_ids[] = $upsell_shop_product_id;
            }
        }

        $product->set_upsell_ids($upsell_ids);
        $product->save();

        if (count($not_found_upsell_ids) > 0) {
            $this->logger->warning(
                'Could not find products',
                [
                    'from' => $shop_product_id,
                    'shop_product_ids' => $not_found_upsell_ids,
                ]
            );
        }
    }
}
