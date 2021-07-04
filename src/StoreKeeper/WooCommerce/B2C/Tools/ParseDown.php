<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;

class ParseDown extends \Parsedown
{
    public static function wrapContentInShortCode($content)
    {
        return !empty($content) ? "[sk_markdown]{$content}[/sk_markdown]" : '';
    }

    /**
     * Default set safeMode to true.
     * This escapes HTML when set to true.
     *
     * @var bool
     */
    protected $safeMode = true;

    protected function inlineLink($Excerpt)
    {
        $link = parent::inlineLink($Excerpt);

        // Safety check, something the Parsedown also does.
        if (empty($link)) {
            return;
        }

        // Get the href with a reference to the original value
        // This is dont to make updating of the href easier
        $href = $link['element']['attributes']['href'];

        // Check if the link starts with backref://
        if (StringFunctions::startsWith($href, 'backref://')) {
            // We extract the mainType and the options parsed
            list(
                $mainType,
                $options) = StoreKeeperApi::extractMainTypeAndOptions($href);

            // Switch for current and future mainType's
            switch ($mainType) {
                case 'backref://ShopModule::ShopProduct':
                    $href = $this->parseShopProduct($href, $options);
            }

            // The href is still broken, we set it to an empty string
            // This causes it to link to the current page.
            if (StringFunctions::startsWith($href, 'backref://')) {
                $href = '';
            }

            // Update the href
            $link['element']['attributes']['href'] = $href;
        }

        return $link;
    }

    private function parseShopProduct($href, $options)
    {
        if (!empty($options['id'])) {
            // Get the WooCommerce product
            $product = ProductImport::getItem($options['id']);
            if ($product) {
                // Get & set the link to the product
                $href = $product->guid;
            }
        }

        return $href;
    }
}
