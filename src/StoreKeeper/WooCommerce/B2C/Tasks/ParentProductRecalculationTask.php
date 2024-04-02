<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\ProductAttributes;

/**
 * Recalculated the parents attribute options. to make sure its still correct after an assigned product has updated.
 * Class ParentProductRecalculationTask.
 */
class ParentProductRecalculationTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        $debug = array_key_exists('debug', $task_options) ? $task_options['debug'] : false;
        $this->setDebug($debug);

        $parent_shop_product_id = 0;
        $parent_post_id = 0;

        $logger = LoggerFactory::create('parentProductRecalculationTask');

        // Get parent_shop_product_id from task meta
        if ($this->taskMetaExists('parent_shop_product_id')) {
            $parent_shop_product_id = $this->getTaskMeta('parent_shop_product_id');
        } else {
            if ($this->taskMetaExists('parent_post_id')) {
                // Get parent_shop_product_id from product/post meta
                $parent_post_id = $this->getTaskMeta('parent_post_id');
                $parent_shop_product_id = get_post_meta($parent_post_id, 'storekeeper_id', true);

                if (!$parent_shop_product_id) {
                    $parent_post = get_post($parent_post_id);

                    if (!$parent_post) {
                        // If the parent post and the meta both do not exist it is possible the task was removed, so no exception needed
                        $logger->info(
                            'Product does not have a shop_product_id',
                            [
                                'parent_post_id' => $parent_post_id,
                            ]
                        );

                        return;
                    } else {
                        // If the meta does not exist there must be something wrong.
                        throw new \Exception("Product with post id (post_id=$parent_post_id) does not have a shop_product_id");
                    }
                }
            }
        }

        $parent_product_object = $this->getParentProductObject($parent_shop_product_id);

        if (!$parent_product_object) {
            $logger->warning(
                'Parent not found',
                [
                    'parent_shop_product_id' => $parent_shop_product_id,
                    'parent_post_id' => $parent_post_id,
                ]
            );
        } else {
            $parent_post = ProductImport::gettingSimpleOrConfigurableProduct($parent_product_object);
            if (!$parent_post || 'publish' !== $parent_post->post_status) {
                throw new \Exception('Could not find parent post');
            }

            $options_config = $this->getOptionsConfig($parent_shop_product_id);

            $parent_product = new \WC_Product_Variable($parent_post);

            $productAttributes = new ProductAttributes($this->logger);
            $productAttributes->setConfigurableAttributes(
                $parent_product, $parent_product_object, $options_config
            );

            $parent_product->save();
        }
    }

    /**
     * @return Dot
     */
    protected function getOptionsConfig($parent_shop_product_id)
    {
        $configResponse = $this->storekeeper_api->getModule('ShopModule')->getConfigurableShopProductOptions(
            $parent_shop_product_id
        );

        return new Dot($configResponse);
    }

    /**
     * @return Dot|void
     */
    protected function getParentProductObject($parent_shop_product_id)
    {
        $parentResponse = $this->storekeeper_api->getModule('ShopModule')->naturalSearchShopFlatProductForHooks(
            0,
            ProductImport::getMainLanguage(),
            0,
            1,
            null,
            [
                [
                    'name' => 'id__=',
                    'val' => $parent_shop_product_id,
                ],
            ]
        );

        $parentData = $parentResponse['data'];
        if (count($parentResponse['data']) <= 0) {
            return; // no parent found.
        }

        return new Dot($parentData[0]);
    }
}
