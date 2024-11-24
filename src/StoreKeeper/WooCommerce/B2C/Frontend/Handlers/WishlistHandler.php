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
    }

    public function createWishlistPostType(): void
    {
        $args = array(
            'labels' => array(
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
            ),
            'public' => true,
            'has_archive' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'author', 'custom-fields'),
            'rewrite' => array('slug' => 'wishlists'),
            'capability_type' => 'post',
        );

        register_post_type('wishlist', $args);
    }

    public function addWishlistButtonToProductPage(): void
    {
        if (is_user_logged_in()) {
            echo '<p><a href="' . get_permalink() . '?create_wishlist=true">' . __('Add to wishlist', I18N::DOMAIN) . '</a></p>';
        } else {
            echo '<a href="' . wp_login_url(get_permalink()) . '">' . __('Login to Add to Wishlist', I18N::DOMAIN) . '</a>';
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
                echo '<p>' . __('Login to Add to Wishlist', I18N::DOMAIN) . '</p>';
            }
        }
    }

    private function displayCreateWishlistForm(): void
    {
        global $post;
        echo '<form action="" method="POST">';
        echo '    <input type="hidden" name="product_id" value="' . $post->ID . '">';
        echo '    <label for="wishlist_name"><strong>' . __('Or Create a new Wishlist', I18N::DOMAIN) . '</strong></label>';
        echo '    <input type="text" name="wishlist_name" id="wishlist_name" required class="wishlist-name"><br>';
        echo '<input type="submit" name="create_wishlist" value="' . __('Create Wishlist', I18N::DOMAIN) . '">';
        echo '</form>';
    }

    private function displayWishlistSelectionform($wishlists): void
    {
        global $post;
        echo '<h4>' . __('Add product to your wishlists', I18N::DOMAIN) . ':</h4>';
        echo '<form action="" method="POST">';
        echo '    <input type="hidden" name="product_id" value="'.  $post->ID . '">';
        echo '    <label for="wishlist_id"><strong>' . __('Select from your wishlist', I18N::DOMAIN) . '</strong></label>';
        echo '    <select name="wishlist_id" id="wishlist-select" class="wishlist-select">';
        echo '        <option>' . __('Select the option', I18N::DOMAIN) . '</option>';
        while ($wishlists->have_posts()) {
            $wishlists->the_post();
            echo '        <option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
        }
        echo '    </select><br>';
        echo '    <input type="submit" name="select_wishlist" value="' . __('Select the option', I18N::DOMAIN) . '">';
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

    public function createWishlist($wishlist_name, $product_id): void
    {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $wishlist_name = sanitize_text_field($wishlist_name);
            $new_wishlist = array(
                'post_title' => $wishlist_name,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => $user_id,
                'post_type' => 'wishlist',
            );
            $wishlist_id = wp_insert_post($new_wishlist);
            self::addProductsToExistingWishlistWithQty($wishlist_id, $product_id, 1);

            if ($wishlist_id) {
                echo '<p style="text-align: center;color: green;">' . sprintf(
                        __('New wishlist %s created successfully!', I18N::DOMAIN),
                        esc_html($wishlist_name)
                    ) . '</p>';
            } else {
                echo '<p style="text-align: center;color: red;">' . __('There was an error creating the wishlist.', I18N::DOMAIN) . '</p>';
            }
        } else {
            echo '<p>' . __('Login to Add to Wishlist', I18N::DOMAIN) . '</p>';
        }
    }

    public function addProductsToExistingWishlistWithQty($wishlist_id, $product_ids, $quantities = [])
    {
        if (is_user_logged_in()) {
            $wishlist_id = intval($wishlist_id);
            $product_ids = array_map('intval', (array) $product_ids);
            $quantities = array_map('intval', (array) $quantities);
            $user_id = get_current_user_id();
            $wishlist_post = get_post($wishlist_id);

            if (!$wishlist_post || $wishlist_post->post_author != $user_id) {
                return ['status' => 'error', 'message' => __('You do not have permission to add products to this wishlist.', 'text-domain')];
            }

            $existing_products = get_post_meta($wishlist_id, '_wishlist_products', true);
            $existing_products = $existing_products ? (array) $existing_products : [];

            foreach ($product_ids as $index => $product_id) {
                $quantity = isset($quantities[$index]) ? $quantities[$index] : 1;

                if (isset($existing_products[$product_id])) {
                    $existing_products[$product_id] += $quantity;
                } else {
                    $existing_products[$product_id] = $quantity;
                }
            }

            update_post_meta($wishlist_id, '_wishlist_products', $existing_products);
        }
    }

    public function handleCreateWishlist(): void
    {
        if (isset($_POST['wishlist_name'])) {
            $wishlist_name = sanitize_text_field($_POST['wishlist_name']);
            $wishlist_post = array(
                'post_title'   => $wishlist_name,
                'post_type'    => 'wishlist',
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            );

            $post_id = wp_insert_post($wishlist_post);

            if ($post_id) {
                wp_redirect(get_permalink($post_id));
                exit;
            } else {
                wp_die('There was an error creating the wishlist.');
            }
        }
    }

   public function getWishlistProductCount($wishlist_id): int
   {
        $serialized_data = get_post_meta($wishlist_id, '_wishlist_products', true);
        $data = is_string($serialized_data) && is_serialized($serialized_data) ? unserialize($serialized_data) : $serialized_data;

        if (!is_array($data)) return 0;

        $valid_products = array_filter(array_map(function($product_id, $qty) {
            $product = wc_get_product($product_id);
            return $product ? ['product' => $product, 'qty' => $qty] : null;
        }, array_keys($data), $data));

        return count($valid_products);
    }
}
