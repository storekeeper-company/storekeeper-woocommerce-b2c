<?php
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\WishlistHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

get_header();

/**
 * Retrieve user wishlists.
 *
 * @param int $userId
 *
 * @return array
 */
function getUserWishlists($userId)
{
    $args = [
        'post_type' => 'wishlist',
        'post_status' => 'publish',
        'author' => $userId,
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);

    return $query->posts;
}

$userId = get_current_user_id();
$wishlists = getUserWishlists($userId);
?>

<div class="wishlist-title" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
    <div>
        <h1><?php echo __('Orderlijsten', I18N::DOMAIN); ?></h1>
    </div>
    <div class="wishlist-count">
        <?php echo sprintf(
            __('%d lijsten', I18N::DOMAIN),
            count($wishlists)
        ); ?>
    </div>
</div>

<div class="wishlist-page container-wishlist">
    <hr>
    <div class="wishlist-summary">
        <?php if (!empty($wishlists)) { ?>
            <ul>
                <?php foreach ($wishlists as $wishlist) { ?>
                    <li class="wishlist-item">
                        <div class="wishlist-info">
                            <a href="<?php echo esc_url(get_permalink($wishlist->ID)); ?>">
                                <?php
                                echo esc_html($wishlist->post_title);
                    ?>
                            </a>
                            <span class="item-count">
                                 <?php echo '('.(new WishlistHandler())->getWishlistProductCount($wishlist->ID).')'; ?>
                            </span>
                            <span class="last-edit-time">
                                <?php
                    $last_modified = get_post_field('post_modified', $wishlist->ID);
                    echo sprintf(
                        __('Last edited: %s', I18N::DOMAIN),
                        date('F j, Y', strtotime($last_modified))
                    );

                    ?>
                            </span>
                        </div>
                        <button class="delete-button" data-wishlist-id="<?php echo $wishlist->ID; ?>">
                            <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/trash-xmark.png'); ?>"
                                 alt="Delete" class="delete-icon">
                        </button>
                    </li>
                <?php } ?>
            </ul>

            <div class="add-new-wishlist">
                <div class="wishlist-add">
                    <img src="<?php echo plugins_url('storekeeper-for-woocommerce/assets/images/plus.png'); ?>"
                         alt="Plus" class="plus-icon">
                    <span><?php echo __('Nieuwe lijst', I18N::DOMAIN); ?></span>
                </div>
            </div>

            <!-- Modal for creating a new wishlist -->
            <div id="new-wishlist-modal" class="wishlist-modal">
                <div class="wishlist-modal-content">
                    <span class="wishlist-close">&times;</span>
                    <h2><?php echo __('Create wishlist', I18N::DOMAIN); ?></h2>
                    <form id="new-wishlist-form" method="POST" action="<?php
                    echo esc_url(admin_url('admin-post.php?action=create_wishlist')); ?>">
                        <input type="text" name="wishlist_name" placeholder="<?php echo __('Wishlist Name', I18N::DOMAIN); ?>" required>
                        <button type="submit" class="submit-wishlist"><?php echo __('Create Wishlist', I18N::DOMAIN); ?></button>
                    </form>
                </div>
            </div>
        <?php } else { ?>
            <p><?php echo __('No wishlists found.', I18N::DOMAIN); ?></p>
        <?php } ?>
    </div>
</div>

<?php
get_footer();
?>