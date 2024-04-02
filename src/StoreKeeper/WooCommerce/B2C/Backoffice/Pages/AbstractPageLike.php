<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Helpers\ServerStatusChecker;

abstract class AbstractPageLike
{
    public const REQUIRED_PHP_EXTENSION = ServerStatusChecker::REQUIRED_PHP_EXTENSION;
    public const OPTIONAL_PHP_EXTENSION = ServerStatusChecker::OPTIONAL_PHP_EXTENSION;

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
