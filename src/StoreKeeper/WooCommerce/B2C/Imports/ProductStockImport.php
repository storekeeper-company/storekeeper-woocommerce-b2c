<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProductStockImport extends AbstractProductImport
{
    protected function doProcessProductItem($dotObject, array $options = [])
    {
        $log_data = $this->setupLogData($dotObject);

        $sku = $dotObject->get('flat_product.product.sku');
        $post = self::getItemBySku($sku);

        if ($post) {
            $product = wc_get_product($post);
            $this->setWoocommerceProductId($product->get_id());
            // If the product is dirty, it means it still needs processing to we plan another import
            if ($dotObject->get('flat_product.dirty')) {
                $this->debug('Product dirty, skipped', $log_data);
                TaskHandler::scheduleTask(
                    TaskHandler::PRODUCT_STOCK_UPDATE,
                    $dotObject->get('id'),
                    ['storekeeper_id' => (int) $dotObject->get('id')],
                    true
                );
            } else {
                // Else we just process the task.
                $log_data = $this->setProductStock($product, $dotObject, $log_data);

                $post_id = $product->save();
                $log_data['post_id'] = $post_id;

                $this->debug('Product stock updated', $log_data);

                return $product->get_id();
            }
        }

        return false;
    }

    protected function getImportEntityName(): string
    {
        return __('products stock', I18N::DOMAIN);
    }
}
