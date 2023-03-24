<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class StoreKeeperSeo
{
    public const META_PREFIX = 'skseo_';
    const META_TITLE = self::META_PREFIX.'title';
    const META_DESCRIPTION = self::META_PREFIX.'desc';
    const META_KEYWORDS = self::META_PREFIX.'focuskw';

    const ALL_META_KEYS = [
        self::META_TITLE,
        self::META_DESCRIPTION,
        self::META_KEYWORDS,
    ];

    public static function isSelectedHandler(): bool
    {
        return Seo::STOREKEEPER_HANDLER === StoreKeeperOptions::getSeoHandler();
    }

    public static function getProductSeo(\WC_Product $product, string $context = 'view'): array
    {
        $values = [];
        foreach (self::ALL_META_KEYS as $key) {
            $values[$key] = $product->get_meta($key, true, $context);
            if (empty($values[$key])) {
                $values[$key] = '';
            }
        }

        return $values;
    }

    public static function setProductSeo(
        \WC_Product $product,
        ?string $title,
        ?string $description,
        ?string $keywords
    ): bool {
        $values = [
            self::META_TITLE => $title ?? '',
            self::META_DESCRIPTION => $description ?? '',
            self::META_KEYWORDS => $keywords ?? '',
        ];

        $changed = false;
        foreach ($values as $key => $value) {
            $old = $product->get_meta($key, true, 'edit');
            if ($old !== $value) {
                $product->add_meta_data($key, $value, true);
                $changed = true;
            }
        }

        if ($changed) {
            $product->save_meta_data();
        }

        return $changed;
    }

    public static function getCategorySeo(\WP_Term $term): array
    {
        $values = [];
        foreach (self::ALL_META_KEYS as $key) {
            $values[$key] = get_term_meta($term->term_id, $key, true);
            if (empty($values[$key])) {
                $values[$key] = '';
            }
        }

        return $values;
    }

    public static function setCategorySeo(
        \WP_Term $term,
        ?string $title,
        ?string $description,
        ?string $keywords
    ): bool {
        $values = [
            self::META_TITLE => $title ?? '',
            self::META_DESCRIPTION => $description ?? '',
            self::META_KEYWORDS => $keywords ?? '',
        ];

        $changed = false;
        foreach ($values as $key => $value) {
            $old = get_term_meta($term->term_id, $key, true);
            if ($old !== $value) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_term_meta($term->term_id, $key, $value)
                );
                $changed = true;
            }
        }

        return $changed;
    }
}
