<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes;

abstract class AbstractShortCode
{
    /**
     * @return string
     */
    abstract protected function getShortCode();

    abstract public function render($attributes = null, $content = null);

    public function load()
    {
        add_shortcode($this->getShortCode(), [$this, 'render']);
    }
}
