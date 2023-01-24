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
                get_post_meta($postId, 'rank_math_title', true)
            );
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
                get_post_meta($postId, 'rank_math_description', true)
            );
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
                get_post_meta($postId, 'rank_math_focus_keyword', true)
            );
        }

        return $rankMathSeoDescription;
    }

    public static function getCategoryTitle(int $termId): string
    {
        $rankMathSeoTitle = '';

        if (PluginStatus::isRankMathSeoEnabled()) {
            $title = get_term_meta($termId, 'rank_math_title', true);
            $rankMathSeoTitle = $title ?? '';
        }

        return $rankMathSeoTitle;
    }

    public static function getCategoryKeywords($termId): string
    {
        $rankMathSeoFocusKeywords = '';

        if (PluginStatus::isRankMathSeoEnabled()) {
            $focusKeywords = get_term_meta($termId, 'rank_math_focus_keyword', true);
            $rankMathSeoFocusKeywords = $focusKeywords ?? '';
        }

        return $rankMathSeoFocusKeywords;
    }

    public static function getCategoryDescription($termId): string
    {
        $rankMathSeoDescription = '';

        if (PluginStatus::isRankMathSeoEnabled()) {
            $description = get_term_meta($termId, 'rank_math_description', true);
            $rankMathSeoDescription = $description ?? '';
        }

        return $rankMathSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToWoocommerceProduct(
        WC_Product $product,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null
    ): void {
        if (PluginStatus::isRankMathSeoEnabled()) {
            if (!is_null($title)) {
                // TODO: update references to _yoast_wpseo_title when you figure out the correct value
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('rank_math_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('rank_math_description', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('rank_math_focus_keyword', $keywords)
                );
            }
        }
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToCategory(
        int $termId,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null
    ): void {
        if (PluginStatus::isRankMathSeoEnabled()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::RANK_MATH_SEO_OPTION_KEY)
            );

            $categories = &$termMeta['product_cat'];

            $category = $categories[$termId] ?? [];

            if (!is_null($title)) {
                $category['wpseo_title'] = $title;
            }

            if (!is_null($description)) {
                $category['wpseo_desc'] = $description;
            }

            if (!is_null($keywords)) {
                $category['wpseo_focuskw'] = $keywords;
            }

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
