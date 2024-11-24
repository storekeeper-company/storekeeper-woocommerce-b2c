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

    $validProducts = array_map(function ($productId, $qty) {
        $product = wc_get_product($productId);

        return $product ? ['product' => $product, 'qty' => $qty] : null;
    }, array_keys($data), $data);

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
function renderProductList($products)
{
    foreach ($products as $productData) {
        $product = $productData['product'];
        $qty = $productData['qty'];
        if ($product->is_type('variable')) {
            ?>
            <li class="wishlist-item-orderlist wishlist-parent-item">
                <div class="wishlist-info">
                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                        <?php
                        $productImage = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
            $productImageUrl = $productImage ? $productImage[0] : '';
            if ($productImageUrl) { ?>
                            <img src="<?php echo esc_url($productImageUrl); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" class="wishlist-product-image"/>
                        <?php } ?>
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </div>
            </li>
            <?php

            $variations = $product->get_children(); // Get variation IDs
            foreach ($variations as $variationId) {
                $variation = wc_get_product($variationId); // Get the variation object
                $variationImage = wp_get_attachment_image_src($variation->get_image_id(), 'thumbnail');
                $variationImageUrl = $variationImage ? $variationImage[0] : $productImageUrl;
                $variationPrice = $variation->get_price_html();
                $variationAttributes = wc_get_formatted_variation($variation, true);
                ?>
                <li class="wishlist-item-orderlist wishlist-child-item">
                    <div class="wishlist-info">
                        <a href="<?php echo esc_url(get_permalink($variation->get_id())); ?>">
                            <?php if ($variationImageUrl) { ?>
                                <img src="<?php echo esc_url($variationImageUrl); ?>"
                                     alt="<?php echo esc_attr($variation->get_name()); ?>" class="wishlist-product-image"/>
                            <?php } ?>
                            <?php echo esc_html($product->get_name().' - '.$variationAttributes); ?>
                        </a>
                        <?php if ($variationPrice) { ?>
                            <span class="wishlist-product-price last-edit-time"><?php echo $variationPrice; ?></span>
                        <?php } ?>
                        <div class="product-qty">
                            <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/minus.png'); ?>"
                                 alt="Minus" class="minus-icon">
                            <?php echo esc_html($qty); ?>
                            <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/plus-black.png'); ?>"
                                 alt="Plus" class="plus-icon">
                        </div>
                    </div>
                </li>
            <?php } ?>
        <?php } else { ?>
            <li class="wishlist-item-orderlist wishlist-single-item">
                <div class="wishlist-info">
                    <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>">
                        <?php
                        $productImage = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
            $productImageUrl = $productImage ? $productImage[0] : '';
            if ($productImageUrl) { ?>
                            <img src="<?php echo esc_url($productImageUrl); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" class="wishlist-product-image"/>
                        <?php } ?>
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                    <?php if ($product->get_price_html()) { ?>
                        <span class="wishlist-product-price last-edit-time"><?php echo $product->get_price_html(); ?></span>
                    <?php } ?>
                    <div class="product-qty">
                        <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/minus.png'); ?>"
                             alt="Minus" class="minus-icon">
                        <?php echo esc_html($qty); ?>
                        <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/plus-black.png'); ?>"
                             alt="Plus" class="plus-icon">
                    </div>
                </div>
            </li>
        <?php } ?>
        <?php
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
            <input type="text" name="search" placeholder="<?php __('Zoek naar producten op basis van de titel, SKU...', I18N::DOMAIN); ?>" style="margin-right: 10px;">
            <div class="custom-dropdown" style="display: flex; align-items: center;">
                <button class="dropdown-button"><?php echo __('Orders importeren', I18N::DOMAIN); ?></button>
                <div class="dropdown-menu">
                    <input type="text" placeholder="<?php __('Zoek naar ID, Datum', I18N::DOMAIN); ?>" class="dropdown-search">
                    <ul class="dropdown-list">
                        <?php foreach ($orders as $order) { ?>
                            <li data-order-id="<?php echo $order->get_id(); ?>" class="order-item">
                                #<?php echo $order->get_id(); ?> - <?php echo $order->get_date_created()->date('d F Y'); ?>
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
                <button class="item-action cart-act"><?php echo __('Alles toevoegen aan winkelmand', I18N::DOMAIN).'('.(new WishlistHandler())->getWishlistProductCount($wishlist->ID); ?>)</button>
            </div>
        </div>
        <input type="hidden" id="wishlist_id" value="<?php echo $wishlist->ID; ?>">
    </div>
    <?php
}

// Fetch wishlist products
$productsWithqty = getWishlistProducts($wishlist->ID);
$groupedProducts = groupProductsByCategory($productsWithqty);

// Fetch user orders
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
                <?php renderProductList($categoryProducts); ?>
            </ul>
        </li>
    <?php } ?>
</ul>
<div class="wishlist-title">
    <div class="products-actions">
        <span><?php echo (new WishlistHandler())->getWishlistProductCount($wishlist->ID).' '.__('Products', I18N::DOMAIN); ?></span>
        <div>
            <button class="item-action store-act"><?php echo __('Lijst opslaan', I18N::DOMAIN); ?></button>
            <button class="item-action cart-act"><?php echo __('Alles toevoegen aan winkelmand', I18N::DOMAIN).'('.(new WishlistHandler())->getWishlistProductCount($wishlist->ID); ?>)</button>
        </div>
    </div>
</div>

<?php get_footer(); ?>

