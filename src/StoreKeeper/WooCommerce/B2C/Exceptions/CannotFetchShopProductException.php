<?php


namespace StoreKeeper\WooCommerce\B2C\Exceptions;


class CannotFetchShopProductException extends BaseException
{
    protected $shop_product_id;

    /**
     * ShopProductNotFound constructor.
     * @param $shop_product_id
     */
    public function __construct($shop_product_id)
    {
        $this->shop_product_id = $shop_product_id;
        parent::__construct('Could not fetch parent product with shop_product_id='.$shop_product_id);
    }

    /**
     * @return mixed
     */
    public function getShopProductId()
    {
        return $this->shop_product_id;
    }

}
