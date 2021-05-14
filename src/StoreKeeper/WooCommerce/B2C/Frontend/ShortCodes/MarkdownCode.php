<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes;

use StoreKeeper\WooCommerce\B2C\Tools\ParseDown;

/**
 * This class registers the form shortcode, this short code is used for a subscription form.
 *
 * Example usage:
 * [sk_markdown]
 ** This will be bold text **
 * [/sk_markdown]
 *
 * Class FormShortCode
 */
class MarkdownCode extends AbstractShortCode
{
    /**
     * @return string
     */
    protected function getShortCode()
    {
        return 'sk_markdown';
    }

    public function render($attributes = null, $content = null)
    {
        if (empty($content)) {
            return '';
        }

        $parsedown = new ParseDown();

        return $parsedown->text($content);
    }
}
