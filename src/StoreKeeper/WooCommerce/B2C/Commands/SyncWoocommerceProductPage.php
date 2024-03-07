<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Cache\ShopProductCache;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class SyncWoocommerceProductPage extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync products with limit and offset.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync products from Storekeeper Backoffice with limit and offset.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'start',
                'description' => __('Skip other products and synchronize from specified starting point.', I18N::DOMAIN),
                'optional' => false,
            ],
            [
                'type' => 'assoc',
                'name' => 'limit',
                'description' => __('Determines how many products will be synchronized from the starting point.', I18N::DOMAIN),
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
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (key_exists('limit', $assoc_arguments) && key_exists('start', $assoc_arguments)) {
                $this->runWithPagination($assoc_arguments);
            } else {
                throw new BaseException('Limit and start attribute need to be set');
            }
        }
    }

    public function runWithPagination($assoc_arguments)
    {
        $import = new ProductImport($assoc_arguments);
        $import->setLogger($this->logger);
        $import->setTaskHandler(new TaskHandler());
        $import->setSkipBroken(true);
        $import->run(
            [
                'skip_cross_sell' => true,
                'skip_upsell' => true,
            ]
        );
    }

    protected function prepareExecute(): bool
    {
        add_filter('woocommerce_product_type_query', [$this, 'recheckType'], 1, 2);

        return parent::prepareExecute();
    }

    /**
     * re check product type bacause woocommerce doesnt return it correctly after saving.
     */
    public function recheckType($some_false_variable, $product_id)
    {
        $id = get_post_meta($product_id, 'storekeeper_id', true);

        if ($id) {
            $data = ShopProductCache::get($id);

            if ($data) {
                return $data;
            }

            $storekeeper_api = StoreKeeperApi::getApiByAuthName();
            $ShopModule = $storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->naturalSearchShopFlatProductForHooks(
                0,
                0,
                0,
                1,
                null,
                [
                    [
                        'name' => 'id__=',
                        'val' => $id,
                    ],
                ]
            );
            if ($response['count'] > 0) {
                $shopProduct = new Dot($response['data'][0]);
                if ($shopProduct->has('flat_product.product.type')) {
                    $type = $shopProduct->get('flat_product.product.type');
                    $wp_type = WooCommerceOptions::getWooCommerceTypeFromProductType($type);
                    ShopProductCache::set($id, $wp_type);
                    unset($storekeeper_api);
                    unset($ShopModule);
                    unset($shopProduct);

                    return WooCommerceOptions::getWooCommerceTypeFromProductType($type);
                }
            }
        }
    }
}
