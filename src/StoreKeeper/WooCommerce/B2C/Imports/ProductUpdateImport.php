<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;

class ProductUpdateImport extends ProductImport
{
    public const PRODUCT_DEFAULT_PRICE_SCOPE = 'product_default_price_id';
    public const PRODUCT_DISCOUNT_PRICE_SCOPE = 'product_discount_price_id';
    public const PRODUCT_PRICE_SCOPE = 'product_price_id';
    public const PRODUCT_STOCK_ORDERABLE_SCOPE = 'orderable_stock_value';
    public const PRODUCT_STOCK_SCOPE = 'product.product_stock';
    public const PRODUCT_CROSS_SELL_SCOPE = 'product.cross_sell_products';
    public const PRODUCT_UP_SELL_SCOPE = 'product.upsell_products';

    public const ALLOWED_UPDATE_SCOPES = [
        self::PRODUCT_DEFAULT_PRICE_SCOPE,
        self::PRODUCT_DISCOUNT_PRICE_SCOPE,
        self::PRODUCT_PRICE_SCOPE,
        self::PRODUCT_STOCK_ORDERABLE_SCOPE,
        self::PRODUCT_STOCK_SCOPE,
        self::PRODUCT_CROSS_SELL_SCOPE,
        self::PRODUCT_UP_SELL_SCOPE,
    ];

    protected $scope = [];

    public function __construct(array $settings = [])
    {
        if (array_key_exists('scope', $settings)) {
            $this->scope = explode(',', $settings['scope']);
        }
        unset($settings['scope']);
        parent::__construct($settings);
    }

    /**
     * @throws \WC_Data_Exception
     * @throws WordpressException
     * @throws \Exception
     */
    protected function processSimpleAndConfigurableProduct(
        Dot $dotObject,
        array $log_data,
        array $options,
        string $importProductType
    ): int {
        $scope = array_intersect($this->scope, self::ALLOWED_UPDATE_SCOPES);
        if (
            0 === count($scope)
            || false === self::getProductByProductData($dotObject)
        ) {
            $this->logger->debug(
                'Update product scope is empty or no product is found, runnign full import',
                ['scope' => $scope]
            );

            return parent::processSimpleAndConfigurableProduct($dotObject, $log_data, $options, $importProductType);
        }

        $this->logger->debug(
            'Update product from scope',
            ['scope' => $scope]
        );

        // Get the product entity
        $newProduct = $this->ensureWooCommerceProduct($dotObject, $importProductType);

        if (
            in_array(self::PRODUCT_DEFAULT_PRICE_SCOPE, $scope, true)
            || in_array(self::PRODUCT_DISCOUNT_PRICE_SCOPE, $scope, true)
            || in_array(self::PRODUCT_PRICE_SCOPE, $scope, true)
        ) {
            // Product prices
            $log_data = $this->setProductPrice($newProduct, $dotObject, $log_data);
        } elseif (
            in_array(self::PRODUCT_STOCK_SCOPE, $scope, true)
            || in_array(self::PRODUCT_STOCK_ORDERABLE_SCOPE, $scope, true)
        ) {
            // Product stock
            $log_data = $this->setProductStock($newProduct, $dotObject, $log_data);
        } elseif (in_array(self::PRODUCT_CROSS_SELL_SCOPE, $scope, true)) {
            // Cross-sell products
            $log_data = $this->handleCrossSellProducts($newProduct, $dotObject, $options, $log_data);
        } elseif (in_array(self::PRODUCT_UP_SELL_SCOPE, $scope, true)) {
            // Upsell products
            $log_data = $this->handleUpsellProducts($newProduct, $dotObject, $options, $log_data);
        }

        // Save the product changes
        $log_data = $this->saveProduct($newProduct, $dotObject, $log_data);
        // Update product object's metadata
        $this->updateProductMeta($newProduct, $dotObject, $log_data);

        return $newProduct->get_id();
    }
}
