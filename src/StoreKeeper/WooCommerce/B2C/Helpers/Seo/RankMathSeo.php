<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Product;

class RankMathSeo
{
    public const RANK_MATH_SEO_OPTION_KEY = 'wpseo_taxonomy_meta';

    /**
     * @throws WordpressException
     */
    public static function getPostTitle(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $rankMathSeoTitle = '';
        if (PluginStatus::isRankMathSeoEnabled() && !is_null($post)) {
            $rankMathSeoTitle = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_title', true)
            );

            if (empty($rankMathSeoTitle)) {
                $rankMathSeoTitles = get_option('wpseo_titles', []);
                $postTitleKey = 'title-'.$post->post_type;
                $rankMathSeoTitle = $rankMathSeoTitles[$postTitleKey] ?? get_the_title($post);
            }

            return wpseo_replace_vars($rankMathSeoTitle, $post);
        }

        return $rankMathSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostDescription(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $rankMathSeoDescription = '';
        if (PluginStatus::isRankMathSeoEnabled() && !is_null($post)) {
            $rankMathSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_metadesc', true)
            );

            if (empty($rankMathSeoDescription)) {
                $rankMathSeoTitles = get_option('wpseo_titles', []);
                $postDescriptionKey = 'metadesc-'.$post->post_type;
                $rankMathSeoDescription = $rankMathSeoTitles[$postDescriptionKey] ?? get_the_title($post);
            }

            return wpseo_replace_vars($rankMathSeoDescription, $post);
        }

        return $rankMathSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostKeywords(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $rankMathSeoDescription = '';
        if (PluginStatus::isRankMathSeoEnabled() && !is_null($post)) {
            $rankMathSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, '_yoast_wpseo_metakeywords', true)
            );
        }

        return $rankMathSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getCategoryTitle(int $termId): string
    {
        $rankMathSeoTitle = '';
        if (PluginStatus::isRankMathSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::RANK_MATH_SEO_OPTION_KEY)
            );

            $categories = $termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $rankMathSeoTitle = $category['wpseo_title'] ?? '';
            }
        }

        return $rankMathSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function getCategoryDescription(int $termId): string
    {
        $rankMathSeoTitle = '';
        if (PluginStatus::isRankMathSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::RANK_MATH_SEO_OPTION_KEY)
            );

            $categories = $termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $rankMathSeoTitle = $category['wpseo_desc'] ?? '';
            }
        }

        return $rankMathSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToWoocommerceProduct(WC_Product $product, ?string $title = null, ?string $description = null, ?string $keywords = null): void
    {
        if (PluginStatus::isRankMathSeoEnabled()) {
            if (!is_null($title)) {
                // TODO: update references to _yoast_wpseo_title when you figure out the correct value
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
        if (PluginStatus::isRankMathSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::RANK_MATH_SEO_OPTION_KEY)
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
                update_option(self::RANK_MATH_SEO_OPTION_KEY, $termMeta)
            );
        }
    }

    public static function shouldAddSeo(?string $title, ?string $description, ?string $keyword): bool
    {
        return (!is_null($title) || !is_null($description) || !is_null($keyword)) && self::isSelectedHandler();
    }

    public static function isSelectedHandler(): bool
    {
        return PluginStatus::isRankMathSeoEnabled() && Seo::RANK_MATH_HANDLER === StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER);
    }
}
