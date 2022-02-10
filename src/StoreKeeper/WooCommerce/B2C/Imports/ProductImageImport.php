<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use WC_Product_Simple;
use WC_Product_Variable;

class ProductImageImport extends ProductImport
{
    public function __construct(array $settings = [])
    {
        if (array_key_exists('hide-progress-bar', $settings)) {
            $this->setIsProgressBarShown(false);
        }
        parent::__construct($settings);
    }

    /**
     * @param Dot $dotObject
     *
     * @return bool|int
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function processItem($dotObject, array $options = [])
    {
        $log_data = $this->setupLogData($dotObject);
        $importProductType = $dotObject->get('flat_product.product.type');

        $productCheck = self::gettingSimpleOrConfigurableProduct($dotObject);

        $product_id = 0;
        if (false !== $productCheck && null !== $productCheck) {
            $product_id = $productCheck->ID;
            ++$this->updatedItemsCount;
        } else {
            ++$this->newItemsCount;
        }

        if ('simple' === $importProductType) {
            $newProduct = new WC_Product_Simple($product_id);
        } else {
            if ('configurable' === $importProductType) {
                $newProduct = new WC_Product_Variable($product_id);
            }
        }

        $main_image_id = $this->setImage($newProduct, $dotObject);

        $post_id = $newProduct->save();

        return false;
    }

    /**
     * @param $newProduct
     * @param $product
     *
     * @return mixed
     */
    private function setImage(&$newProduct, $product)
    {
        if ($product->has('flat_product.main_image')) {
            $attachment_id = Media::createAttachmentCDN($product->get('flat_product.main_image.big_url'), $product->get('flat_product.main_image.url'));
            $this->debug(
                'Found main product image',
                [
                    'product_images' => $product->get('flat_product.main_image'),
                    'attachment_id' => $attachment_id,
                ]
            );
            $newProduct->set_image_id($attachment_id);

            return $product->get('flat_product.main_image.id');
        }
    }
}
