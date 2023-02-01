<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class StorekeeperSeo
{
    public const STOREKEEPER_SEO_OPTION_KEY = 'wpseo_taxonomy_meta';


    public function th_show_all_hooks($tag)
    {
        if (is_admin()) { // Display Hooks in front end pages only
            $debug_tags = array();
            global $debug_tags;
            if ( in_array( $tag, $debug_tags ) ) {
                return;
            }
            echo "<pre>" . $tag . "</pre>";
            $debug_tags[] = $tag;
        }
    }

    public function extraCategoryFields( $tag ) {    //check for existing featured ID
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
            <th scope="row" valign="top"><label for="cat_seo_title"><?php _e('SEO Title'); ?></label></th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_title]"
                        id="Cat_meta[seo_title]" size="3" style="width:60%;"
                        value="<?php echo $category['wpseo_title'] ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="cat_seo_description"><?php _e('SEO Description'); ?></label></th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_description]"
                        id="Cat_meta[seo_description]" size="3" style="width:60%;"
                        value="<?php echo $category['wpseo_desc'] ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="cat_seo_keywords"><?php _e('SEO Keywords'); ?></label></th>
            <td>
                <input
                        type="text" name="Cat_meta[seo_keywords]"
                        id="Cat_meta[seo_keywords]" size="3" style="width:60%;"
                        value="<?php echo $category['wpseo_focuskw'] ?? ''; ?>"
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
        <tr class="form-field">
            <th scope="row" valign="top"><label for="post_seo_title"><?php _e('SEO Title'); ?></label></th>
            <td>
                <input
                        type="text" name="Post_meta[seo_title]"
                        id="Post_meta[seo_title]" size="3" style="width:60%;"
                        value="<?php echo $seoTitle ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="post_seo_description"><?php _e('SEO Description'); ?></label></th>
            <td>
                <input
                        type="text" name="Post_meta[seo_description]"
                        id="Post_meta[seo_description]" size="3" style="width:60%;"
                        value="<?php echo $seoDescription ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" valign="top"><label for="post_seo_keywords"><?php _e('SEO Keywords'); ?></label></th>
            <td>
                <input
                        type="text" name="Post_meta[seo_keywords]"
                        id="Post_meta[seo_keywords]" size="3" style="width:60%;"
                        value="<?php echo $seoKeywords ?? ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <?php
    }

    public function addCategorySeoFields($tag)
    {
        $tId = $tag->term_id;
        $catMeta = get_option( "category_$tId");
        ?>
        <tr class="form-field">
            <th scope="row" vertical-align="top"><label for="seo_title"><?php _e('SEO Title'); ?></label></th>
            <td>
                <input
                        type="text" name="seo_title" id="seo_title"
                        size="25" style="width:60%;"
                        value="<?php echo $catMeta['extra1'] ? $catMeta['extra1'] : ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" vertical-align="top"><label for="seo_description"><?php _e('SEO Description'); ?></label></th>
            <td>
                <input
                        type="text" name="seo_description"
                        id="seo_description" size="25" style="width:60%;"
                        value="<?php echo $catMeta['extra2'] ? $catMeta['extra2'] : ''; ?>"
                        readonly
                ><br />
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row" vertical-align="top"><label for="seo_keywords"><?php _e('SEO Keywords'); ?></label></th>
            <td>
                <textarea
                        name="seo_keywords" id="seo_keywords"
                        style="width:60%;" readonly
                >
                    <?php echo $catMeta['extra3'] ? $catMeta['extra3'] : ''; ?>
                </textarea><br />
            </td>
        </tr>
        <?php
    }

    public static function shouldAddSeo(?string $title, ?string $description, ?string $keyword): bool
    {
        return (!is_null($title) || !is_null($description) || !is_null($keyword)) && self::isSelectedHandler();
    }

    public static function isSelectedHandler(): bool
    {
        return Seo::STOREKEEPER_HANDLER === StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER);
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
            $title = get_term_meta($termId, 'wpseo_title', true);
            $storekeeperSeoTitle = $title ?? '';
        }

        return $storekeeperSeoTitle;
    }

    public static function getCategoryKeywords($termId): string
    {
        $storeSeoFocusKeywords = '';

        if (self::isSelectedHandler()) {
            $focusKeywords = get_term_meta($termId, 'wpseo_keywords', true);
            $storeSeoFocusKeywords = $focusKeywords ?? '';
        }

        return $storeSeoFocusKeywords;
    }

    public static function getCategoryDescription($termId): string
    {
        $storekeeperSeoDescription = '';

        if (self::isSelectedHandler()) {
            $description = get_term_meta($termId, 'wpseo_desc', true);
            $storekeeperSeoDescription = $description ?? '';
        }

        return $storekeeperSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostTitle(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $storekeeperSeoTitle = '';

        if (self::isSelectedHandler() && !is_null($post)) {
            $storekeeperSeoTitle = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, 'wpseo_title', true)
            );
        }

        return $storekeeperSeoTitle;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostDescription(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $storekeeperSeoDescription = '';

        if (self::isSelectedHandler() && !is_null($post)) {
            $storekeeperSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, 'wpseo_desc', true)
            );
        }

        return $storekeeperSeoDescription;
    }

    /**
     * @throws WordpressException
     */
    public static function getPostKeywords(int $postId): string
    {
        $post = WordpressExceptionThrower::throwExceptionOnWpError(
            get_post($postId)
        );
        $storekeeperSeoDescription = '';

        if (self::isSelectedHandler() && !is_null($post)) {
            $storekeeperSeoDescription = WordpressExceptionThrower::throwExceptionOnWpError(
                get_post_meta($postId, 'wpseo_keywords', true)
            );
        }

        return $storekeeperSeoDescription;
    }
}
