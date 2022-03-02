<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use DateTime;
use Exception;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use WC_Data_Exception;

class ProductUpdateImport extends ProductImport
{
    /**
     * @throws WC_Data_Exception
     * @throws WordpressException
     * @throws Exception
     */
    protected function processSimpleAndConfigurableProduct(
        Dot $dotObject,
        array $log_data,
        array $options,
        string $importProductType
    ): int {
        // Get the product entity
        $newProduct = $this->getNewProduct($dotObject, $importProductType);
        $lastSyncDate = get_post_meta($newProduct->get_id(), 'storekeeper_sync_date', true);
        $mysqlFormat = 'Y-m-d H:i:s';
        // Handle seo
        $this->processSeo($newProduct, $dotObject);
        $timezone = wp_timezone();

        // Product variables/details
        $log_data = $this->setProductDetails($newProduct, $dotObject, $importProductType, $log_data);

        $productPriceUpdatedDateTime = new DateTime($dotObject->get('product_price.date_updated'));
        $productPriceUpdatedDateTime->setTimezone($timezone);
        $productPriceUpdatedDateTimeString = $productPriceUpdatedDateTime->format($mysqlFormat);
        $productDefaultPriceUpdatedDateTime = new DateTime($dotObject->get('product_default_price.date_updated'), $timezone);
        $productDefaultPriceUpdatedDateTime->setTimezone($timezone);
        $productDefaultPriceUpdatedDateString = $productDefaultPriceUpdatedDateTime->format($mysqlFormat);

        if ($productPriceUpdatedDateTimeString > $lastSyncDate || $productDefaultPriceUpdatedDateString > $lastSyncDate) {
            // Product prices
            $log_data = $this->setProductPrice($newProduct, $dotObject, $log_data);
        }

        [ , , , $productStockDate] = $this->getStockProperties($dotObject);
        $productStockUpdatedDateTime = new DateTime($productStockDate);
        $productStockUpdatedDateTime->setTimezone($timezone);
        $productStockUpdatedDateTimeString = $productStockUpdatedDateTime->format($mysqlFormat);

        if ($productStockUpdatedDateTimeString > $lastSyncDate) {
            // Product stock
            $log_data = $this->setProductStock($newProduct, $dotObject, $log_data);
        }

        // Upsell products
        $log_data = $this->handleUpsellProducts($newProduct, $dotObject, $options, $log_data);
        // Cross-sell products
        $log_data = $this->handleCrossSellProducts($newProduct, $dotObject, $options, $log_data);
        // Save the product changes
        $log_data = $this->saveProduct($newProduct, $dotObject, $log_data);
        // Update product object's metadata
        $this->updateProductMeta($newProduct, $dotObject, $log_data);

        return $newProduct->get_id();
    }

    protected function getImportEntityName(): string
    {
        return __('products', I18N::DOMAIN);
    }
}
