<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class StorekeeperSeo
{
    public const STOREKEEPER_SEO_OPTION_KEY = 'skseo_taxonomy_meta';
    public const STOREKEEPER_HANDLER = 'storekeeper';

    public function extraCategoryFields( $tag ) {
        $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
            get_option(self::STOREKEEPER_SEO_OPTION_KEY)
        );

        $categories = &$termMeta['product_cat'];

        if (isset($categories[$tag->term_id])) {
            $category = $categories[$tag->term_id];
        } else {
            $category = [];
        }

        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="cat_seo_title"><?php _e('SEO Title'); ?></label>
            </th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_title]"
                        id="Cat_meta[seo_title]" size="3" style="width:60%;"
                        value="<?php echo esc_html($category['skseo_title']) ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="cat_seo_description"><?php _e('SEO Description'); ?></label>
            </th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_description]"
                        id="Cat_meta[seo_description]" size="3" style="width:60%;"
                        value="<?php echo esc_html($category['skseo_desc']) ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="cat_seo_keywords"><?php _e('SEO Keywords'); ?></label>
            </th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_keywords]"
                        id="Cat_meta[seo_keywords]" size="3" style="width:60%;"
                        value="<?php echo esc_html($category['skseo_focuskw']) ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <?php
    }

    public function extraProductFields($post) {
        $seoTitle = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post_meta($post->ID, 'wpseo_title', true)
        );
        $seoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post_meta($post->ID, 'wpseo_desc', true)
        );
        $seoKeywords = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post_meta($post->ID, 'wpseo_focuskw', true)
        );

        ?>
        <table id="alg-product-input-fields-table" class="alg-product-input-fields-table">
            <tr>
                <th scope="row" vertical-align="top">
                    <label for="post_seo_title"><?php _e('SEO Title'); ?></label>
                </th>
                <td>
                    <input
                            type="text" name="Post_meta[seo_title]"
                            id="Post_meta[seo_title]" size="3" style="width:800%;"
                            value="<?php echo esc_html($seoTitle) ?? ''; ?>"
                            readonly
                    ><br />
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" vertical-align="top">
                    <label for="post_seo_description"><?php _e('SEO Description'); ?></label>
                </th>
                <td>
                    <input
                            type="text" name="Post_meta[seo_description]"
                            id="Post_meta[seo_description]" size="3" style="width:800%;"
                            value="<?php echo esc_html($seoDescription) ?? ''; ?>"
                            readonly
                    ><br />
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" vertical-align="top">
                    <label for="post_seo_keywords"><?php _e('SEO Keywords'); ?></label>
                </th>
                <td>
                    <input
                            type="text" name="Post_meta[seo_keywords]"
                            id="Post_meta[seo_keywords]" size="3" style="width:800%;"
                            value="<?php echo esc_html($seoKeywords) ?? ''; ?>"
                            readonly
                    ><br />
                </td>
            </tr>
        </table>
        <?php
    }

    public static function shouldAddSeo(?string $title, ?string $description, ?string $keyword): bool
    {
        return (!is_null($title) || !is_null($description) || !is_null($keyword)) && self::isSelectedHandler();
    }

    public static function isSelectedHandler(): bool
    {
        return Seo::STOREKEEPER_HANDLER === StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER, self::STOREKEEPER_HANDLER);
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToCategory(
        int $termId,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null,
        int $categoryId = null
    ): void {
        if (self::isSelectedHandler()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::STOREKEEPER_SEO_OPTION_KEY)
            );

            $categories = &$termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
            } else {
                $category = [];
            }

            if (!is_null($title)) {
                $category['skseo_title'] = $title;
            }

            if (!is_null($description)) {
                $category['skseo_desc'] = $description;
            }

            if (!is_null($keywords)) {
                $category['skseo_focuskw'] = $keywords;
            }

            if (!is_null($categoryId)) {
                $category['category_id'] = $categoryId;
            }

            $categories[$termId] = $category;

            WordpressExceptionThrower::throwExceptionOnWpError(
                update_option(self::STOREKEEPER_SEO_OPTION_KEY, $termMeta)
            );
        }
    }

    /**
     * @throws WordpressException
     */
    public static function addSeoToWoocommerceProduct(
        Object $product,
        ?string $title = null,
        ?string $description = null,
        ?string $keywords = null
    ): void {
        if (self::isSelectedHandler()) {
            if (!is_null($title)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('wpseo_title', $title)
                );
            }

            if (!is_null($description)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('wpseo_desc', $description)
                );
            }

            if (!is_null($keywords)) {
                WordpressExceptionThrower::throwExceptionOnWpError(
                    $product->update_meta_data('wpseo_focuskw', $keywords)
                );
            }
        }
    }

    public static function getCategoryTitle(int $termId): string
    {
        $storekeeperSeoTitle = '';

        if (self::isSelectedHandler()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::STOREKEEPER_SEO_OPTION_KEY)
            );

            $categories = &$termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $storekeeperSeoTitle = $category['wpseo_title'] ?? '';
            }
        }

        return $storekeeperSeoTitle;
    }

    public static function getCategoryKeywords($termId): string
    {
        $storeSeoFocusKeywords = '';

        if (self::isSelectedHandler()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::STOREKEEPER_SEO_OPTION_KEY)
            );

            $categories = &$termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $storeSeoFocusKeywords = $category['wpseo_focuskw'] ?? '';
            }
        }

        return $storeSeoFocusKeywords;
    }

    public static function getCategoryDescription($termId): string
    {
        $storekeeperSeoDescription = '';

        if (self::isSelectedHandler()) {
            $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
                get_option(self::STOREKEEPER_SEO_OPTION_KEY)
            );

            $categories = &$termMeta['product_cat'];

            if (isset($categories[$termId])) {
                $category = $categories[$termId];
                $storekeeperSeoDescription = $category['wpseo_desc']  ?? '';
            }
        }

        return $storekeeperSeoDescription;
    }
}
