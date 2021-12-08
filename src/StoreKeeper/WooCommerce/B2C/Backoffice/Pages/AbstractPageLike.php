<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

abstract class AbstractPageLike
{
    const REQUIRED_PHP_EXTENSION = [
        'bcmath',
        'json',
        'mbstring',
        'mysqli',
        'openssl',
        'zip',
    ];

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
