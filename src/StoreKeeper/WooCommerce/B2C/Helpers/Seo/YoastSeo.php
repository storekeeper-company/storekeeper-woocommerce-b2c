<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

/**
 * Tested up to Yoast SEO version 17.8.
 */
class YoastSeo
{
    public const YOAST_SEO_OPTION_KEY = 'wpseo_taxonomy_meta';

    /**
     * @throws WordpressException
     */
    public static function getPostTitle(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $yoastSeoTitle = '';
        if (PluginStatus::isYoastSeoEnabled() && !is_null($post)) {
            $yoastSeoTitle = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_title', true)
            );

            if (empty($yoastSeoTitle)) {
                $yoastSeoTitles = get_option('wpseo_titles', []);
                $postTitleKey = 'title-'.$post->post_type;
                $yoastSeoTitle = $yoastSeoTitles[$postTitleKey] ?? get_the_title($post);
            }

            return wpseo_replace_vars($yoastSeoTitle, $post);
        }

        return $yoastSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostDescription(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $yoastSeoDescription = '';
        if (PluginStatus::isYoastSeoEnabled() && !is_null($post)) {
            $yoastSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_metadesc', true)
            );

            if (empty($yoastSeoDescription)) {
                $yoastSeoTitles = get_option('wpseo_titles', []);
                $postDescriptionKey = 'metadesc-'.$post->post_type;
                $yoastSeoDescription = $yoastSeoTitles[$postDescriptionKey] ?? get_the_title($post);
            }

            return wpseo_replace_vars($yoastSeoDescription, $post);
        }

        return $yoastSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostKeywords(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $yoastSeoDescription = '';
        if (PluginStatus::isYoastSeoEnabled() && !is_null($post)) {
            $yoastSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_metakeywords', true)
            );
        }

        return $yoastSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getCategoryTitle(int $termId): string
    {
        $yoastSeoTitle = '';
        if (PluginStatus::isYoastSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::YOAST_SEO_OPTION_KEY)
            );

            $categories = $termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $yoastSeoTitle = $category['wpseo_title'] ?? '';
            }
        }

        return $yoastSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function getCategoryDescription(int $termId): string
    {
        $yoastSeoTitle = '';
        if (PluginStatus::isYoastSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::YOAST_SEO_OPTION_KEY)
            );

            $categories = $termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $yoastSeoTitle = $category['wpseo_desc'] ?? '';
            }
        }

        return $yoastSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToWoocommerceProduct(\WC_Product $product, ?string $title = null, ?string $description = null, ?string $keywords = null): void
    {
        if (PluginStatus::isYoastSeoEnabled()) {
            if (!is_null($title)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('_yoast_wpseo_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('_yoast_wpseo_metadesc', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('_yoast_wpseo_metakeywords', $keywords)
                );
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('_yoast_wpseo_focuskw', $keywords)
                );
            }
        }
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToCategory(int $termId, ?string $title = null, ?string $description = null, ?string $keywords = null): void
    {
        if (PluginStatus::isYoastSeoEnabled()) {
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
        return PluginStatus::isYoastSeoEnabled() && Seo::YOAST_HANDLER === StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER);
    }
}
