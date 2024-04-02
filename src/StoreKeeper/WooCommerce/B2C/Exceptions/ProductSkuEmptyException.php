<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class ProductSkuEmptyException extends BaseException
{
    /**
     * @var \WC_Product
     */
    protected $product;

    public function __construct(\WC_Product $product, ?\Throwable $previous = null)
    {
        $message = sprintf(
            'Product %s [id=%s]: "%s" has empty sku',
            $product->get_type(),
            $product->get_id(),
            $product->get_title()
        );
        $this->product = $product;
        parent::__construct($message, 0, $previous);
    }

    public function getProduct(): \WC_Product
    {
        return $this->product;
    }
}
