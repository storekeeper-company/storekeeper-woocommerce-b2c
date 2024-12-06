<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;

class WishlistHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('woocommerce_before_single_product', [$this, 'createNewWishlistForm']);
        add_action('init', [$this, 'createWishlistPostType']);
        add_action('woocommerce_single_product_summary', [$this, 'addWishlistButtonToProductPage']);
        add_action('admin_post_create_wishlist', [$this, 'handleCreateWishlist']);
        add_action('admin_post_nopriv_create_wishlist', [$this, 'handleCreateWishlist']);
        add_action('wp_ajax_delete_wishlist_item', [$this, 'deleteWishlistItem']);
        add_action('wp_ajax_nopriv_delete_wishlist_item', [$this, 'deleteWishlistItem']);
        add_action('wp_ajax_search_products_by_sku_name', [$this, 'handleProductSearchAjax']);
        add_action('wp_ajax_nopriv_search_products_by_sku_name', [$this, 'handleProductSearchAjax']);
        add_action('wp_ajax_add_product_to_wishlist', [$this,'addProductToWishlist']);
        add_action('wp_ajax_update_wishlist_quantity', [$this, 'updateWishlistQuantity']);
        add_action('wp_ajax_nopriv_update_wishlist_quantity', [$this, 'updateWishlistQuantity']);
        add_action('wp_ajax_store_wishlist', [$this, 'storeWishlistCallback']);
        add_action('wp_ajax_nopriv_store_wishlist', [$this, 'storeWishlistCallback']);
        add_action('wp_ajax_add_products_to_cart', [$this, 'addProductsToCart']);
        add_action('wp_ajax_nopriv_add_products_to_cart', [$this, 'addProductsToCart']);
        add_action('wp_ajax_delete_product_from_wishlist', [$this, 'deleteProductFromWishlist']);
        add_action('wp_ajax_nopriv_delete_product_from_wishlist', [$this, 'deleteProductFromWishlist']);
    }

    public function createWishlistPostType(): void
    {
        $args = [
            'labels' => [
                'name' => 'Wishlists',
                'singular_name' => 'Wishlist',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Wishlist',
                'edit_item' => 'Edit Wishlist',
                'new_item' => 'New Wishlist',
                'view_item' => 'View Wishlist',
                'search_items' => 'Search Wishlists',
                'not_found' => 'No wishlists found',
                'not_found_in_trash' => 'No wishlists found in Trash',
                'all_items' => 'All Wishlists',
                'menu_name' => 'Wishlists',
                'name_admin_bar' => 'Wishlist',
            ],
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'author', 'custom-fields'),
            'rewrite' => array('slug' => 'wishlists'),
            'capability_type' => 'post',
        ];

        register_post_type('wishlist', $args);
    }

    public function addWishlistButtonToProductPage(): void
    {
        if (is_user_logged_in()) {
            echo '<p><a href="' . get_permalink() . '?create_wishlist=true">' . esc_html__('Add to wishlist', I18N::DOMAIN) . '</a></p>';
        } else {
            echo '<a href="' . wp_login_url(get_permalink()) . '">' . esc_html__('Login to Add to Wishlist', I18N::DOMAIN) . '</a>';
        }
    }

    public function createNewWishlistForm(): void
    {
        if (isset($_GET['create_wishlist']) && $_GET['create_wishlist'] == 'true') {
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $args = array(
                    'post_type' => 'wishlist',
                    'post_status' => 'publish',
                    'author' => $user_id,
                    'posts_per_page' => -1,
                );

                $wishlists = new \WP_Query($args);

                echo '<div id="wishlist-modal" class="wishlist-modal">';
                echo '    <div class="wishlist-modal-content">';
                echo '        <span class="wishlist-close-btn">&times;</span>';

                if ($wishlists->have_posts()) {
                    $this->displayWishlistSelectionform($wishlists);
                }

                wp_reset_postdata();

                $this->displayCreateWishlistForm();
                echo '    </div>';
                echo '</div>';
                $this->handleWishlistFormSubmissions();
            } else {
                echo '<p>' . esc_html__('Login to Add to Wishlist', I18N::DOMAIN) . '</p>';
            }
        }
    }

    private function displayCreateWishlistForm(): void
    {
        global $post;
        echo '<form action="" method="POST">';
        echo '    <input type="hidden" name="product_id" value="' . $post->ID . '">';
        echo '    <label for="wishlist_name"><strong>' . esc_html__('Or Create a new Wishlist', I18N::DOMAIN) . '</strong></label>';
        echo '    <input type="text" name="wishlist_name" id="wishlist_name" required class="wishlist-name"><br>';
        echo '<input type="submit" name="create_wishlist" value="' . esc_html__('Create Wishlist', I18N::DOMAIN) . '">';
        echo '</form>';
    }

    private function displayWishlistSelectionform($wishlists): void
    {
        global $post;
        echo '<h4>' . __('Add product to your wishlists', I18N::DOMAIN) . ':</h4>';
        echo '<form action="" method="POST">';
        echo '    <input type="hidden" name="product_id" value="'.  $post->ID . '">';
        echo '    <label for="wishlist_id"><strong>' . esc_html__('Select from your wishlist', I18N::DOMAIN) . '</strong></label>';
        echo '    <select name="wishlist_id" id="wishlist-select" class="wishlist-select">';
        echo '        <option>' . esc_html__('Select the option', I18N::DOMAIN) . '</option>';
        while ($wishlists->have_posts()) {
            $wishlists->the_post();
            echo '        <option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
        }
        echo '    </select><br>';
        echo '    <input type="submit" name="select_wishlist" value="' . esc_html__('Select the option', I18N::DOMAIN) . '">';
        echo '</form>';
    }


    private function handleWishlistFormSubmissions(): void
    {
        if (isset($_POST['create_wishlist']) && isset($_POST['wishlist_name']) && isset($_POST['product_id'])) {
            $this->createWishlist($_POST['wishlist_name'], intval($_POST['product_id']));
        }

        if (isset($_POST['select_wishlist']) && isset($_POST['wishlist_id']) && isset($_POST['product_id'])) {
            $this->addProductsToExistingWishlistWithQty(intval($_POST['wishlist_id']), intval($_POST['product_id']), 1);
        }
    }

    public function createWishlist($wishlistName, $productId): void
    {
        if (is_user_logged_in()) {
            $userId = get_current_user_id();
            $wishlistName = sanitize_text_field($wishlistName);
            $newWishlist = array(
                'post_title' => $wishlistName,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => $userId,
                'post_type' => 'wishlist',
            );
            $wishlistId = wp_insert_post($newWishlist);
            self::addProductsToExistingWishlistWithQty($wishlistId, $productId, 1);

            if ($wishlistId) {
                echo '<p style="text-align: center;color: green;">' . sprintf(
                        esc_html__('New wishlist %s created successfully!', I18N::DOMAIN),
                        esc_html($wishlistName)
                    ) . '</p>';
            } else {
                echo '<p style="text-align: center;color: red;">' . esc_html__('There was an error creating the wishlist.', I18N::DOMAIN) . '</p>';
            }
        } else {
            echo '<p>' . __('Login to Add to Wishlist', I18N::DOMAIN) . '</p>';
        }
    }

    public function addProductsToExistingWishlistWithQty($wishlistId, $productIds, $quantities = [])
    {
        if (is_user_logged_in()) {
            $wishlistId = intval($wishlistId);
            $productIds = array_map('intval', (array) $productIds);
            $quantities = array_map('intval', (array) $quantities);
            $existingProducts = get_post_meta($wishlistId, '_wishlist_products', true);
            $existingProducts = $existingProducts ? (array) $existingProducts : [];

            foreach ($productIds as $index => $productId) {
                $quantity = isset($quantities[$index]) ? $quantities[$index] : 1;

                if (isset($existingProducts[$productId])) {
                    $existingProducts[$productId] += $quantity;
                } else {
                    $existingProducts[$productId] = $quantity;
                }
            }

            update_post_meta($wishlistId, '_wishlist_products', $existingProducts);
        }
    }

    public function handleCreateWishlist(): void
    {
        if (isset($_POST['wishlist_name'])) {
            $wishlistName = sanitize_text_field($_POST['wishlist_name']);
            $wishlistPost = array(
                'post_title'   => $wishlistName,
                'post_type'    => 'wishlist',
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            );

            $postId = wp_insert_post($wishlistPost);

            if ($postId) {
                wp_redirect(get_permalink($postId));
                exit;
            } else {
                wp_die(__('There was an error creating the wishlist.', I18N::DOMAIN));
            }
        }
    }

    public function getWishlistProductCount($wishlistId): int
    {
        $serializedData = get_post_meta($wishlistId, '_wishlist_products', true);
        $data = is_string($serializedData) && is_serialized($serializedData) ? unserialize($serializedData) : $serializedData;

        if (!is_array($data)) return 0;

        $productIds = [];

        foreach ($data as $wishlistItem) {
            if (isset($wishlistItem['product_id'])) {
                $productIds[] = $wishlistItem['product_id'];

                if (isset($wishlistItem['variation_id']) && $wishlistItem['variation_id'] != 0) {
                    $productIds[] = $wishlistItem['variation_id'];
                }
            }
        }

        $unique_product_ids = array_unique($productIds);

        return count($unique_product_ids);
    }

    public function deleteWishlistItem(): void
    {
        if (isset($_POST['wishlist_id'])) {
            $wishlistId = intval($_POST['wishlist_id']);

            $deleted = wp_delete_post($wishlistId);

            if ($deleted) {
                wp_send_json_success();
            }
        }
    }

    public function handleProductSearchAjax()
    {
        if (isset($_POST['search_term']) && !empty($_POST['search_term'])) {
            global $wpdb;

            $searchTerm = sanitize_text_field($_POST['search_term']);

            $args = [
                'limit' => -1,
                's' => $searchTerm,
                'meta_query' => [
                    [
                        'key' => '_sku',
                        'value' => $searchTerm,
                        'compare' => 'LIKE'
                    ]
                ]
            ];

            $products = wc_get_products($args);

            foreach ($products as $product) {
                $productImageUrl = wp_get_attachment_url($product->get_image_id());
                $results[] = [
                    'id' => $product->get_id(),
                    'sku' => $product->get_sku(),
                    'name' => $product->get_name(),
                    'price' => wc_price($product->get_price()),
                    'image' => $productImageUrl,
                    'permalink' => get_permalink($product->get_id()),
                ];
            }

            wp_send_json_success(['products' => $results]);
        }
    }

    public function updateWishlistQuantity()
    {
        $productId = absint($_POST['product_id']);
        $newQty = absint($_POST['qty']);

        if ($newQty < 1) {
            return;
        }

        $product = wc_get_product($productId);

        if (!$product) {
            wp_send_json_error(['message' => 'Product not found']);
            return;
        }
        $wishlist = get_user_meta(get_current_user_id(), '_wishlist', true);

        if (!is_array($wishlist)) {
            $wishlist = [];
        }


        foreach ($wishlist as &$item) {
            if ($item['product_id'] == $productId) {
                $item['qty'] = $newQty;
                break;
            }
        }

        update_user_meta(get_current_user_id(), '_wishlist', $wishlist);
    }

    public function storeWishlistCallback() {
        $wishlistId = intval($_POST['wishlist_id']);
        $products = $_POST['products'];
        $existingProducts = get_post_meta($wishlistId, '_wishlist_products', true);

        if (is_string($existingProducts)) {
            $existingProducts = @unserialize($existingProducts);
        }

        if (!is_array($existingProducts)) {
            $existingProducts = [];
        }

        foreach ($products as $productData) {
            $productId = intval($productData['product_id']);
            $qty = intval($productData['qty']);

            if ($productId <= 0 || $qty <= 0) continue;

            $product = wc_get_product($productId);

            if (!$product) continue;

            if ($product->is_type('variable')) {
                $variations = $product->get_available_variations();

                foreach ($variations as $variation) {
                    $variationId = $variation['variation_id'];
                    $productExists = false;

                    foreach ($existingProducts as &$existingProduct) {
                        if ($existingProduct['product_id'] == $productId && $existingProduct['variation_id'] == $variationId) {
                            $existingProduct['qty'] += $qty;
                            $productExists = true;
                            break;
                        }
                    }

                    if (!$productExists) {
                        $existingProducts[] = [
                            'product_id'   => $productId,
                            'variation_id' => $variationId,
                            'qty'          => $qty
                        ];
                    }
                }
            } else {
                $productExists = false;

                foreach ($existingProducts as &$existingProduct) {
                    if ($existingProduct['product_id'] == $productId && $existingProduct['variation_id'] == 0) {
                        $existingProduct['qty'] += $qty;
                        $productExists = true;
                        break;
                    }
                }

                if (!$productExists) {
                    $existingProducts[] = [
                        'product_id'   => $productId,
                        'variation_id' => 0,
                        'qty'          => $qty
                    ];
                }
            }
        }

        update_post_meta($wishlistId, '_wishlist_products', serialize($existingProducts));
    }

    public function addProductsToCart()
    {
        if (isset($_POST['wishlist_id']) && isset($_POST['products'])) {
            $products = $_POST['products'];

            foreach ($products as $productData) {
                $productId = intval($productData['product_id']);
                $variationId = intval($productData['variation_id']);
                $quantity = intval($productData['qty']);

                if ($productId <= 0 || $quantity <= 0) continue;

                if ($variationId > 0) {
                    WC()->cart->add_to_cart($productId, $quantity, $variationId);
                } else {
                    WC()->cart->add_to_cart($productId, $quantity);
                }
            }

            wp_send_json_success(['cart_url' => wc_get_cart_url()]);
        }
    }

    public function addProductToWishlist() {
        $wishlistId = intval($_POST['wishlist_id']);
        $productId = intval($_POST['product_id']);
        $product = wc_get_product($productId);

        if (!$product) {
            wp_send_json_error(['message' => 'Invalid product']);
            return;
        }

        $existingProducts = get_post_meta($wishlistId, '_wishlist_products', true);

        if ($existingProducts) {
            $existingProducts = maybe_unserialize($existingProducts);
        } else {
            $existingProducts = [];
        }

        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {
                $variationId = $variation['variation_id'];
                $productExists = false;

                foreach ($existingProducts as &$existingProduct) {
                    if (isset($existingProduct['product_id'], $existingProduct['variation_id'], $existingProduct['qty']) &&
                        $existingProduct['product_id'] == $productId &&
                        $existingProduct['variation_id'] == $variationId) {
                        $existingProduct['qty'] += 1;
                        $productExists = true;
                        break;
                    }
                }

                if (!$productExists) {
                    $newProduct = [
                        'product_id'   => $productId,
                        'variation_id' => $variationId,
                        'qty'          => 1
                    ];
                    $existingProducts[] = $newProduct;
                }
            }
        } else {
            $productExists = false;
            foreach ($existingProducts as &$existingProduct) {
                if (isset($existingProduct['product_id'], $existingProduct['variation_id'], $existingProduct['qty']) &&
                    $existingProduct['product_id'] == $productId && $existingProduct['variation_id'] == 0) {
                    $existingProduct['qty'] += 1;
                    $productExists = true;
                    break;
                }
            }

            if (!$productExists) {
                $newProduct = [
                    'product_id'   => $productId,
                    'variation_id' => 0,
                    'qty'          => 1
                ];
                $existingProducts[] = $newProduct;
            }
        }

        update_post_meta($wishlistId, '_wishlist_products', maybe_serialize($existingProducts));
    }

    public function deleteProductFromWishlist()
    {
        $wishlistId = intval($_POST['wishlist_id']);
        $productId = intval($_POST['product_id']);
        $variationId = intval($_POST['variation_id']);
        $serializedProducts = get_post_meta($wishlistId, '_wishlist_products', true);
        $existingProducts = maybe_unserialize($serializedProducts);

        if (!is_array($existingProducts)) {
            wp_send_json_error(array('message' => esc_html__('Invalid wishlist data.', I18N::DOMAIN)));
        }

        $found = false;

        foreach ($existingProducts as $key => $productData) {
            if (is_array($productData) &&
                $productData['product_id'] == $productId &&
                $productData['variation_id'] == $variationId
            ) {
                unset($existingProducts[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            wp_send_json_error(array('message' => esc_html__('Product not found in wishlist.', I18N::DOMAIN)));
        }

        update_post_meta($wishlistId, '_wishlist_products', $existingProducts);
    }
}
