<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;

class StoreKeeperSeoHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        if (StoreKeeperSeo::isSelectedHandler()) {
            add_filter('woocommerce_structured_data_product', [$this, 'setProductStructuredData'], 10, 2);
            add_action('wp_head', [$this, 'addMetaTags'], 20);
            add_action('document_title_parts', [$this, 'setTitle'], 10);
        }
    }

    protected function getCurrentProduct(): ?\WC_Product
    {
        if (is_product()) {
            $product = wc_get_product();
            if ($product instanceof \WC_Product) {
                return $product;
            }
        }

        return null;
    }

    public function setTitle($title)
    {
        $product = $this->getCurrentProduct();
        if (!is_null($product)) {
            $seo = StoreKeeperSeo::getProductSeo($product);
            if (!empty($seo[StoreKeeperSeo::SEO_TITLE])) {
                $title['title'] = $seo[StoreKeeperSeo::SEO_TITLE];
            }
        } elseif (is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $seo = StoreKeeperSeo::getCategorySeo($term);
                if (!empty($seo[StoreKeeperSeo::SEO_TITLE])) {
                    $title['title'] = $seo[StoreKeeperSeo::SEO_TITLE];
                }
            }
        }

        return $title;
    }

    public function addMetaTags()
    {
        $product = $this->getCurrentProduct();
        if (!is_null($product)) {
            $seo = StoreKeeperSeo::getProductSeo($product);
            $this->renderHeadMetaSeo($seo);
            $this->renderProductSocialMeta($seo, $product);
        } elseif (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $this->renderPostSocialMeta($post);
                $this->renderPostStructuredData($post);
            } else {
                $this->renderSocialMetaFallback();
            }
            $this->renderMetaDescription();
        } elseif (is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $seo = StoreKeeperSeo::getCategorySeo($term);
                $this->renderHeadMetaSeo($seo);
                $this->renderTermSocialMeta($term);
            } else {
                $this->renderSocialMetaFallback();
                $this->renderMetaDescription();
            }
        } else {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $this->renderTermSocialMeta($term);
                $this->renderMetaDescription($term->description);
            } else {
                $this->renderSocialMetaFallback();
                $this->renderMetaDescription();
            }
        }
    }

    public function setProductStructuredData($markdown, $product)
    {
        /* @var $product \WC_Product */
        $this->applyBrand($markdown, $product);
        $this->applyBarcode($markdown, $product);

        foreach ($markdown['offers'] as &$offer) {
            if (isset($offer['priceSpecification']['valueAddedTaxIncluded'])) {
                $offer['priceSpecification']['valueAddedTaxIncluded'] = true; // we always set with tax included
            }
            // seems google does not handle https:// and woommerce does not want to change
            // see https://support.google.com/webmasters/thread/3210194?hl=en
            if (!empty($offer['availability'])) {
                $offer['availability'] = str_replace(
                    'https://schema.org/',
                    'http://schema.org/',
                    $offer['availability']
                );
            }

            // get minimum price instead of first variation
            if ($product instanceof \WC_Product_Variable) {
                $offer['price'] = $product->get_variation_price('min', false);
            }
        }

        return $markdown;
    }

    public function renderPostStructuredData(\WP_Post $post)
    {
        $data = [];
        $data[] = [
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url('?s={search_term_string}&post_type='.$post->post_type),
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $isPost = 'post' === $post->post_type;
        if ($isPost) {
            $article = [
                '@type' => 'Article',
                '@context' => 'https://schema.org',
                'headline' => get_the_title($post),
                'datePublished' => get_the_date('c'),
                'dateModified' => get_the_modified_date('c'),
            ];
            $imageSrc = $this->getPostIdFirstImage($post->ID);
            if (!empty($imageSrc)) {
                $article['image'][] = $imageSrc;
            }

            list($authorName, $authorUrl) = $this->getPostAuthor($post);
            $article['author'][] = [
                '@type' => 'Person',
                'name' => $authorName,
                'url' => $authorUrl,
            ];

            $data[] = $article;
        }

        echo '<script type="application/ld+json">'.wc_esc_json(wp_json_encode($data), true).'</script>';
    }

    protected function applyBarcode(&$markdown, $product)
    {
        $barcode_name = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BARCODE);
        if (!empty($barcode_name)) {
            $barcode = $product->get_attribute($barcode_name);
            if (!empty($barcode)) {
                $field = self::getBarcodeType($barcode);
                $markdown[$field] = $barcode;
            }
        }
    }

    public static function getBarcodeType(string $barcode): string
    {
        if (preg_match('/^[\d\-\s]{8,}$/', $barcode)) {
            // only numbers (space and minus)
            $barcode = preg_replace('/[-\s]/', '', $barcode);
            if (8 === strlen($barcode)) {
                return 'gtin8';
            }
            if (12 === strlen($barcode)) {
                return 'gtin12';
            }
            if (14 === strlen($barcode)) {
                return 'gtin14';
            }
            if (13 === strlen($barcode)) {
                // check prefixes for ISBN
                // see: https://en.wikipedia.org/wiki/International_Standard_Book_Number#Overview
                $first3 = substr($barcode, 0, 3);
                if ('978' === $first3 || '979' === $first3) {
                    return 'isbn';
                } else {
                    return 'gtin13';
                }
            }
        }

        return 'mpn';
    }

    protected function applyBrand(&$markdown, $product)
    {
        $brand_name = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BRAND);
        if (!empty($brand_name)) {
            $brand = $product->get_attribute($brand_name);
            if (!empty($brand)) {
                $markdown['brand'] = $brand;
            }
        }
    }

    protected function renderHeadMetaSeo(array $seo): void
    {
        $kw = esc_attr($seo[StoreKeeperSeo::SEO_KEYWORDS]);
        if (!empty($kw)) {
            echo <<<HTML
  <meta name="keywords" content="$kw">
HTML;
        }
        $description = esc_attr($seo[StoreKeeperSeo::SEO_DESCRIPTION]);
        $this->renderMetaDescription($description);
    }

    protected function renderProductSocialMeta(array $seo, \WC_Product $product): void
    {
        $title = $seo[StoreKeeperSeo::SEO_TITLE];
        if (empty($title)) {
            $title = $product->get_title();
        }
        $title = esc_attr($title);

        $description = $seo[StoreKeeperSeo::SEO_DESCRIPTION];
        if (empty($description)) {
            $description = $product->get_short_description();
        }
        if (empty($description)) {
            $description = $product->get_description();
        }
        if (empty($description)) {
            $description = '';
        }
        $description = esc_attr(strip_tags(do_shortcode($description)));
        $post_id = $product->get_id();
        $image_src = $this->getPostIdFirstImage($post_id);
        $image_src = esc_attr($image_src);
        $url = esc_attr(wp_get_canonical_url());
        $price = $product->get_sale_price('edit');
        if (empty($price)) {
            $price = $product->get_regular_price('edit');
        }
        if ($product instanceof \WC_Product_Variable) {
            $price = $product->get_variation_price('min', false);
        }
        $currency = get_woocommerce_currency();
        $this->renderSocialMeta('product', $title, $url, $description, $image_src);
        echo <<<HTML
  <meta property="product:price.amount" content="$price">
  <meta property="product:price.currency" content="$currency">
HTML;
    }

    protected function renderPostSocialMeta(\WP_Post $post): void
    {
        $title = get_the_title($post);
        $title = esc_attr($title);

        $description = $post->post_excerpt;
        $description = esc_attr(strip_tags(do_shortcode($description)));

        $image_src = $this->getPostIdFirstImage($post->ID);
        $image_src = esc_attr($image_src);
        $url = esc_attr(wp_get_canonical_url());

        $isPost = 'post' === $post->post_type;
        $type = $isPost ? 'article' : 'website';

        $this->renderSocialMeta($type, $title, $url, $description, $image_src);

        if ($isPost) {
            $mod = esc_attr(get_the_modified_date('c'));
            $pub = esc_attr(get_the_date('c'));
            $categories = get_the_category();
            if (is_array($categories)) {
                foreach ($categories as $category) {
                    /* @var $category \WP_Term */
                    $term_name = esc_attr($category->name);
                    echo <<<HTML
  <meta property="article:section" content="$term_name">
HTML;
                    break; // we show only first
                }
            }

            echo <<<HTML
  <meta property="article:published_time" content="$pub">
  <meta property="article:modified_time" content="$mod">
HTML;
            $tags = get_the_tags();
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    /* @var $tag \WP_Term */
                    $term_name = esc_attr($tag->name);
                    echo <<<HTML
  <meta property="article:tag" content="$term_name">
HTML;
                }
            }
        }
    }

    protected function renderTermSocialMeta(\WP_Term $term): void
    {
        $title = esc_attr(wp_get_document_title());

        $description = $term->description;
        $description = esc_attr(strip_tags(do_shortcode($description)));

        $this->renderSocialMeta('website', $title, null, $description);
    }

    protected function renderSocialMetaFallback(): void
    {
        $title = esc_attr(wp_get_document_title());

        $this->renderSocialMeta('website', $title);
    }

    protected function renderSocialMeta(string $type, string $title, ?string $url = null, ?string $description = null, ?string $image_src = null): void
    {
        echo <<<HTML
  <meta property="og:type" content="$type">
  <meta property="og:title" content="$title">
  <meta name="twitter:title" content="$title">
HTML;
        if (!empty($url)) {
            echo <<<HTML
  <meta property="og:url" content="$url">
  <meta property="twitter:url" content="$url">
HTML;
        }

        if (empty($description)) {
            $description = get_bloginfo('description');
        }
        if (!empty($description)) {
            echo <<<HTML
  <meta property="og:description" content="$description">
  <meta name="twitter:description" content="$description">
HTML;
        }
        if (!empty($image_src)) {
            echo <<<HTML
  <meta name="twitter:card" content="summary_large_image">
  <meta property="og:image" content="$image_src">
  <meta name="twitter:image" content="$image_src">
HTML;
        }
    }

    protected function renderMetaDescription(?string $description = null): void
    {
        if (empty($description)) {
            $description = get_bloginfo('description');
        }
        echo <<<HTML
<meta name="description" content="$description">
HTML;
    }

    protected function getPostIdFirstImage(int $post_id): ?string
    {
        $image_src = wp_get_attachment_image_src(
            get_post_thumbnail_id($post_id), 'single-post-thumbnail'
        );
        if (!empty($image_src)) {
            return $image_src[0];
        }

        return null;
    }

    protected function getPostAuthor(\WP_Post $post): array
    {
        $authorName = get_the_author_meta('display_name', $post->post_author);
        $authorUrl = get_the_author_meta('user_url', $post->post_author);
        if (empty($authorUrl)) {
            $authorUrl = get_author_posts_url($post->post_author);
        }

        return [$authorName, $authorUrl];
    }
}
