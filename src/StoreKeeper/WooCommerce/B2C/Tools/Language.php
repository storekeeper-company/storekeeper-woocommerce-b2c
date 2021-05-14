<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class Language
{
    public static function getSiteLanguageIso2()
    {
        return substr(get_bloginfo('language'), 0, 2);
    }
}
