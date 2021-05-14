<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

abstract class AbstractPageLike
{
    public function register(): void
    {
        $this->doRegisterStyles();
    }

    /** @return string[] Returns style paths to import */
    abstract protected function getStylePaths(): array;

    private function doRegisterStyles()
    {
        foreach ($this->getStylePaths() as $index => $stylePath) {
            wp_enqueue_style(
                "storekeeper-style-$index-$stylePath",
                $stylePath
            );
        }
    }

    abstract public function render(): void;
}
