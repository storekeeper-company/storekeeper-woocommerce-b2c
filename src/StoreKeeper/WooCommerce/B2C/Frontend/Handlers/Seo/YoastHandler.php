<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class YoastHandler implements SeoHandlerInterface
{
    const YOAST_SEO_OPTION_KEY = 'wpseo_taxonomy_meta';

    public function handle($markdown, $product)
    {
        if (!self::isSelectedHandler()) {
            // revert handler to storekeeper
            StoreKeeperOptions::set(StoreKeeperOptions::SEO_HANDLER, Seo::STOREKEEPER_HANDLER);
        }
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToPost(int $postId, ?string $title = null, ?string $description = null, ?string $keywords = null): void
    {
        if (Seo::isYoastActive()) {
            if (!is_null($title)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($postId, '_yoast_wpseo_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($postId, '_yoast_wpseo_metadesc', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($postId, '_yoast_wpseo_metakeywords', $keywords)
                );
            }
        }
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToCategory(int $termId, ?string $title = null, ?string $description = null, ?string $keywords = null): void
    {
        if (Seo::isYoastActive()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::YOAST_SEO_OPTION_KEY)
            );
            $categories = &$termMeta['product_cat'];
            if (isset($categories[$termId])) {
                $category = $categories[$termId];
            } else {
                $category = [];
            }

            if (!is_null($title)) {
                $category['wpseo_title'] = $title;
            }

            if (!is_null($description)) {
                $category['wpseo_desc'] = $description;
            }

            // Not sure how keywords is being stored, as it's a premium inclusion for Yoast

            $categories[$termId] = $category;

            WordpressExceptionThrower::throwExceptionOnWpError(
                update_option(self::YOAST_SEO_OPTION_KEY, $termMeta)
            );
        }
    }

    public static function shouldAddSeo(?string $title, ?string $description, ?string $keyword): bool
    {
        return (!is_null($title) || !is_null($description) || !is_null($keyword)) && self::isSelectedHandler();
    }

    public static function isSelectedHandler(): bool
    {
        return Seo::isYoastActive() && Seo::YOAST_HANDLER === StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER);
    }
}
