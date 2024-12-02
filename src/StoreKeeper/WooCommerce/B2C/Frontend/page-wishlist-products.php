<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\WishlistHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

get_header();

global $wpdb;

$currentUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$pathSegments = explode('/wishlists/', parse_url($currentUrl, PHP_URL_PATH));
$segmentAfterWishlists = trim($pathSegments[1] ?? '', '/');

$wishlist = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}posts WHERE post_name = %s",
    $segmentAfterWishlists
));

if (!$wishlist) {
    echo '<p>'.__('Wishlist not found.', I18N::DOMAIN).'</p>';
    get_footer();
    exit;
}

/**
 * Fetch wishlist products and their quantities.
 */
function getWishlistProducts($wishlistId)
{
    $serializedData = get_post_meta($wishlistId, '_wishlist_products', true);
    $data = is_string($serializedData) && is_serialized($serializedData) ? unserialize($serializedData) : $serializedData;

    if (!is_array($data)) {
        return [];
    }

    $validProducts = array_map(function ($item) {
        if (isset($item['product_id'], $item['qty'])) {
            $product = wc_get_product($item['product_id']);

            if ($product) {
                return [
                    'product' => $product,
                    'qty' => $item['qty'],
                    'variation_id' => isset($item['variation_id']) ? $item['variation_id'] : 0,
                ];
            }
        }

        return null;
    }, $data);

    $validProducts = array_filter($validProducts);

    return $validProducts;
}

/**
 * Group products by category.
 */
function groupProductsByCategory($productsQty)
{
    $grouped = [];

    foreach ($productsQty as $productData) {
        $product = $productData['product'];
        $categories = get_the_terms($product->get_id(), 'product_cat');

        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                $grouped[$category->slug][] = $productData;
            }
        }
    }

    return $grouped;
}

/**
 * Render product list HTML.
 */
function renderProductList($products, $wishlist)
{
    $displayedParent = false;
    $displayedVariations = [];

    foreach ($products as $productData) {
        $product = $productData['product'];
        $qty = $productData['qty'];

        $productImage = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
        $productImageUrl = $productImage ? $productImage[0] : '';

        if ($product->is_type('variable')) {
            $wishlistVariations = get_post_meta($wishlist->ID, '_wishlist_products', true);
            $wishlistVariations = maybe_unserialize($wishlistVariations);

            if (!$displayedParent) {
                ?>
                <li class="wishlist-item-orderlist wishlist-parent-item">
                    <div class="wishlist-info main-product">
                        <div class="wishlist-product-image-container">
                            <img src="<?php echo esc_url($productImageUrl); ?>"
                                 alt="<?php echo esc_attr($product->get_name()); ?>"
                                 class="wishlist-product-image"/>
                        </div>
                        <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>"
                           class="wishlist-item-link">
                            <div class="wishlist-product-name"><?php echo esc_html($product->get_name()); ?></div>
                        </a>
                    </div>
                </li>
                <?php
                $displayedParent = true;
            }

            $variations = $product->get_children();

            if (!empty($variations)) {
                foreach ($variations as $variationId) {
                    foreach ($wishlistVariations as $wishlistItem) {
                        if (is_array($wishlistItem) && $wishlistItem['variation_id'] == $variationId) {
                            if (in_array($variationId, $displayedVariations)) {
                                continue;
                            }

                            $displayedVariations[] = $variationId;
                            $variationProduct = wc_get_product($variationId);
                            $variationImage = wp_get_attachment_image_src($variationProduct->get_image_id(), 'thumbnail');
                            $variationImageUrl = $variationImage ? $variationImage[0] : $productImageUrl;
                            $variationPrice = $variationProduct->get_price_html();
                            ?>
                            <!-- Variation Item -->
                            <li class="wishlist-item-orderlist wishlist-child-item"
                                data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                                data-variation-id="<?php echo esc_attr($variationId); ?>">
                                <div class="wishlist-info">
                                    <input type="hidden" name="variation_id" class="variation_id"
                                           value="<?php echo esc_attr($variationId); ?>">
                                    <div class="wishlist-product-image-container">
                                        <img src="<?php echo esc_url($variationImageUrl); ?>"
                                             alt="<?php echo esc_attr($variationProduct->get_name()); ?>"
                                             class="wishlist-product-image"/>
                                        <div class="wishlist-item-delete-product"
                                             style="background-image: url('<?php echo plugins_url('storekeeper-for-woocommerce/assets/trash-xmark.png'); ?>');">
                                            <button class="delete-button-product"
                                                    data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                                                    data-variation-id="<?php echo esc_attr($variationId); ?>"></button>
                                            <div class="tooltip-container-product">
                                                <p class="tooltip-product-message"><?php echo __('Wilt u het verwijderen?', I18N::DOMAIN); ?><br>
                                                    <span class="tooltip-option-product yes"><?php echo __('Ja', I18N::DOMAIN); ?></span> / <span
                                                        class="tooltip-option-product no"><?php echo __('Nee', I18N::DOMAIN); ?></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url(get_permalink($variationProduct->get_id())); ?>"
                                       class="wishlist-item-link">
                                        <div
                                            class="wishlist-product-name"><?php echo esc_html($variationProduct->get_name()); ?></div>
                                    </a>
                                    <?php if ($variationPrice) { ?>
                                        <span class="wishlist-product-price"><?php echo $variationPrice; ?></span>
                                    <?php } ?>
                                    <div class="product-qty">
                                        <img
                                            src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/minus.png'); ?>"
                                            alt="Minus" class="minus-icon"
                                            data-product-id="<?php echo esc_attr($variationProduct->get_id()); ?>"
                                            data-variation-id="<?php echo esc_attr($variationId); ?>"
                                            data-action="decrease">
                                        <span class="qty-value"><?php echo esc_html($qty); ?></span>
                                        <img
                                            src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/plus-black.png'); ?>"
                                            alt="Plus" class="plus-icon"
                                            data-product-id="<?php echo esc_attr($variationProduct->get_id()); ?>"
                                            data-variation-id="<?php echo esc_attr($variationId); ?>"
                                            data-action="increase">
                                    </div>
                                </div>
                            </li>
                            <?php
                        }
                    }
                }
            }
        } else { ?>
            <li class="wishlist-item-orderlist wishlist-single-item"
                data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <div class="wishlist-info">
                    <div class="wishlist-product-image-container">
                        <img src="<?php echo esc_url($productImageUrl); ?>"
                             alt="<?php echo esc_attr($product->get_name()); ?>"
                             class="wishlist-product-image"/>
                        <div class="wishlist-item-delete-product"
                             style="background-image: url('<?php echo plugins_url('storekeeper-for-woocommerce/assets/trash-xmark.png'); ?>');">
                            <button class="delete-button-product"
                                    data-product-id="<?php echo esc_attr($product->get_id()); ?>"></button>
                            <div class="tooltip-container-product">
                                <p class="tooltip-product-message"><?php echo __('Wilt u het verwijderen?', I18N::DOMAIN); ?><br>
                                    <span class="tooltip-option-product yes"><?php echo __('Ja', I18N::DOMAIN); ?></span> / <span
                                        class="tooltip-option-product no"><?php echo __('Nee', I18N::DOMAIN); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" class="wishlist-item-link">
                        <div class="wishlist-product-name"><?php echo esc_html($product->get_name()); ?></div>
                    </a>
                    <?php if ($product->get_price_html()) { ?>
                        <span class="wishlist-product-price"><?php echo $product->get_price_html(); ?></span>
                    <?php } ?>
                    <div class="product-qty">
                        <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/minus.png'); ?>"
                             alt="Minus" class="minus-icon"
                             data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                             data-action="decrease">
                        <span class="qty-value"><?php echo esc_html($qty); ?></span>
                        <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/plus-black.png'); ?>"
                             alt="Plus" class="plus-icon"
                             data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                             data-action="increase">
                    </div>
                </div>
            </li>
        <?php }
        }
}

/**
 * Render the wishlist header and search form.
 */
function renderWishlistHeader($wishlist, $orders)
{
    ?>
    <div class="wishlist-title">
        <div style="display: flex; align-items: center;">
            <h4 class="order-list"><?php echo __('Orderlijsten', I18N::DOMAIN); ?>:</h4>
            <h4 class="product-title"><?php echo $wishlist->post_title; ?></h4>
        </div>

        <div style="display: flex; align-items: center;">
            <div style="position: relative; flex: 1; margin-right:10px;">
                <input
                    type="text"
                    class="product-search-input"
                    name="search"
                    placeholder="<?php echo esc_attr(__('Zoek naar producten op basis van de titel, SKU...', I18N::DOMAIN)); ?>"
                    style="margin-right: 10px;"
                    autocomplete="off"
                >
                <ul class="dropdown-list-search">
                    <!-- Products will be populated here via AJAX -->
                </ul>
            </div>

            <div class="custom-dropdown" style="display: flex; align-items: center;">
                <button class="dropdown-button"><?php echo __('Orders importeren', I18N::DOMAIN); ?></button>
                <div class="dropdown-menu">
                    <input type="text" placeholder="<?php __('Zoek naar ID, Datum', I18N::DOMAIN); ?>"
                           class="dropdown-search">
                    <ul class="dropdown-list">
                        <?php foreach ($orders as $order) { ?>
                            <li data-order-id="<?php echo $order->get_id(); ?>" class="order-item">
                                #<?php echo $order->get_id(); ?>
                                - <?php echo $order->get_date_created()->date('d F Y'); ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>
        <hr>
        <div class="products-actions">
            <span><?php echo (new WishlistHandler())->getWishlistProductCount($wishlist->ID).' '.__('Products', I18N::DOMAIN); ?></span>
            <div>
                <button class="item-action store-act"><?php echo __('Lijst opslaan', I18N::DOMAIN); ?></button>
                <button class="item-action cart-act">
                    <?php echo __('Alles toevoegen aan winkelmand', I18N::DOMAIN).'('.(new WishlistHandler())->getWishlistProductCount($wishlist->ID); ?>
                    )
                </button>
            </div>

        </div>
        <div class="success-message" style="color: green;">
            <p></p>
        </div>
        <input type="hidden" id="wishlist_id" value="<?php echo $wishlist->ID; ?>">
    </div>

    <?php
}

$productsWithqty = getWishlistProducts($wishlist->ID);
$groupedProducts = groupProductsByCategory($productsWithqty);

$userId = get_current_user_id();
$ordersQuery = new WC_Order_Query([
    'customer_id' => $userId,
    'status' => 'processing',
    'limit' => -1,
]);
$orders = $ordersQuery->get_orders();

renderWishlistHeader($wishlist, $orders);
?>
    <ul class="wishlist-children">
        <?php foreach ($groupedProducts as $categorySlug => $categoryProducts) { ?>
            <li class="wishlist-category">
                <div class="wishlist-category-name">
                    <h4 class="category-title"><?php echo esc_html(get_term_by('slug', $categorySlug, 'product_cat')->name); ?></h4>
                </div>
                <ul class="wishlist-category-products">
                    <?php renderProductList($categoryProducts, $wishlist); ?>
                </ul>
            </li>
        <?php } ?>
    </ul>
    <div class="wishlist-title">
        <div class="products-actions">
            <span><?php echo (new WishlistHandler())->getWishlistProductCount($wishlist->ID).' '.__('Products', I18N::DOMAIN); ?></span>
            <div>
                <button class="item-action store-act"><?php echo __('Lijst opslaan', I18N::DOMAIN); ?></button>
                <button
                    class="item-action cart-act"><?php echo __('Alles toevoegen aan winkelmand', I18N::DOMAIN).'('.(new WishlistHandler())->getWishlistProductCount($wishlist->ID); ?>
                    )
                </button>
            </div>
        </div>
    </div>

<?php get_footer(); ?>