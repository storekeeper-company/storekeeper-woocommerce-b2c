<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Product;

abstract class AbstractProductImport extends AbstractImport
{
    const STOCK_STATUS_IN_STOCK = 'instock';
    const STOCK_STATUS_OUT_OF_STOCK = 'outofstock';

    protected function getModule()
    {
        return 'ShopModule';
    }

    protected function getFunction()
    {
        return 'naturalSearchShopFlatProductForHooks';
    }

    protected function getQuery()
    {
        return 0;
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'flat_product/product/type__in_list',
                'multi_val' => [
                    'simple',
                    'configurable',
                    'configurable_assign',
                ],
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        if ($this->product_id > 0) {
            $f[] = [
                'name' => 'product_id__=',
                'val' => $this->product_id,
            ];
        }

        return $f;
    }

    protected $storekeeper_id = 0;
    protected $product_id = 0;

    /**
     * ProductImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->product_id = key_exists('product_id', $settings) ? (int) $settings['product_id'] : 0;
        unset($settings['storekeeper_id'], $settings['product_id']);
        parent::__construct($settings);
    }

    protected function setupLogData(Dot $dot): array
    {
        $log_data = [
            'id' => $dot->get('id'),
            'dirty' => $dot->get('flat_product.dirty'),
        ];
        if ($dot->has('flat_product.product.sku')) {
            $log_data['sku'] = $dot->get('flat_product.product.sku');
        }

        return $log_data;
    }

    /**
     * @param $sku
     *
     * @return WC_Product|bool
     *
     * @throws WordpressException
     */
    public static function getItemBySku($sku)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product',
                    'number' => 1,
                    'meta_key' => '_sku',
                    'meta_value' => $sku,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    protected function setProductStock(WC_Product $product, Dot $dot, array $log_data): array
    {
        // Check if the product is in stock
        $log_data['in_stock'] = $dot->get('flat_product.product.product_stock.in_stock');

        if ($dot->get('flat_product.product.product_stock.in_stock')) {
            //in stock
            $manage_stock = !$dot->get('flat_product.product.product_stock.unlimited');
            $stock_quantity = $manage_stock ? $dot->get('flat_product.product.product_stock.value') : 1;

            $product->set_manage_stock($manage_stock);
            $product->set_stock_quantity($stock_quantity);
            $product->set_stock_status(self::STOCK_STATUS_IN_STOCK);
        } else {
            //out of stock
            $product->set_manage_stock(true);
            $product->set_stock_quantity(0);
            $product->set_stock_status(self::STOCK_STATUS_OUT_OF_STOCK);
        }

        $log_data['manage_stock'] = $product->get_manage_stock();
        $log_data['stock_quantity'] = $product->get_stock_quantity();
        $log_data['stock_status'] = $product->get_stock_status();
        $this->debug('Set stock on product', $log_data);

        return $log_data;
    }

    protected function setProductBackorder(WC_Product $product, Dot $dot): void
    {
        $trueValue = $this->getBackorderTrueValue();
        $backorder_string = $dot->get('backorder_enabled', false) ? $trueValue : 'no';
        $product->set_backorders($backorder_string);
    }

    /**
     * @return string
     */
    protected function getBackorderTrueValue()
    {
        return 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::NOTIFY_ON_BACKORDER, 'no') ? 'notify' : 'yes';
    }
}
