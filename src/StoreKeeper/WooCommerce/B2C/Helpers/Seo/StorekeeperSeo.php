<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

class StorekeeperSeo
{
    public function testWoocommerceSeo($categoryId)
    {
        $txt = "SOME OBVIOUS HTML TEXT";
        echo <<<HTML
            <div class="notice notice-error">
            <p style="color: red; text-decoration: blink;">$txt</p>
            </div>
        HTML;
    }
}