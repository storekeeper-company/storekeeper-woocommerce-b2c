<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

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
        \WC_Product $product,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null
    ): void {
        if (PluginStatus::isRankMathSeoEnabled()) {
            $id = $product->get_id();

            if (!is_null($title)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($id, 'rank_math_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($id, 'rank_math_description', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_post_meta($id, 'rank_math_focus_keyword', $keywords)
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
            if (!is_null($title)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_term_meta($termId, 'rank_math_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_term_meta($termId, 'rank_math_description', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    update_term_meta($termId, 'rank_math_focus_keyword', $keywords)
                );
            }
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
