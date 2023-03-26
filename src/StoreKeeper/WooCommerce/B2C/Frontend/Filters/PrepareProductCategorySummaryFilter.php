<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Filters;

use StoreKeeper\WooCommerce\B2C\Hooks\AbstractWpFilter;
use StoreKeeper\WooCommerce\B2C\I18N;

class PrepareProductCategorySummaryFilter extends AbstractWpFilter
{
    public static function getTag(): string
    {
        return self::FILTER_PREFIX.'prepare_product_category_summary';
    }

    public static function getDescription(): string
    {
        return __('Allows to change the product category summary, which is shown below the products in the category archive page.', I18N::DOMAIN);
    }
}
