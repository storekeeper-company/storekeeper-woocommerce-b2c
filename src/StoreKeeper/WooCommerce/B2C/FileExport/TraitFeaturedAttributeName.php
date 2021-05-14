<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;

trait TraitFeaturedAttributeName
{
    private $mappedFeaturedExportAttributes;

    protected function ensureAttributeName($name): string
    {
        if (empty($this->mappedFeaturedExportAttributes)) {
            $this->mappedFeaturedExportAttributes = FeaturedAttributeOptions::getMappedFeaturedExportAttributes();
        }

        if (key_exists($name, $this->mappedFeaturedExportAttributes)) {
            return $this->mappedFeaturedExportAttributes[$name];
        }

        return $name;
    }
}
