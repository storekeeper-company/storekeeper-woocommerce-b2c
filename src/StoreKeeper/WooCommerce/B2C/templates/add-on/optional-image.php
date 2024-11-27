<?php
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

$image_min_h = $addon['image_min_h'];
$image_min_w = $addon['image_min_w'];
$image_max_h = $addon['image_max_h'];
$image_max_w = $addon['image_max_w'];

echo '<div class="sk-addon-select sk-addon-'.$addon['type'].'" '.ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID.'="'.$addon['product_addon_group_id'].'">';
echo '<label for="agree">
        <input type="checkbox" name="agree" id="agree"> <strong>' . __(
        'Would you like an image on the product?', I18N::DOMAIN
    ) . '</strong>
      </label>';
echo '<input type="hidden" id="product-id" value="' . get_the_ID() . '">';
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li id="option-' . esc_attr($option['id']) . '">';
    echo esc_html($option['title']) .
        '<span style="font-size: 0.8em;">' .
        ($option['ppu_wt'] > 0
            ? '+' . get_woocommerce_currency_symbol(get_woocommerce_currency()) . ' ' . esc_html($option['ppu_wt'])
            : ' (' . __('free', I18N::DOMAIN) . ')') .
        '</span>';
    echo '<br><button type="button" class="upload-image-btn" data-option-id="' . esc_attr($option['id']) . '">'
        . __('Upload Image', I18N::DOMAIN) . '</button>';
    echo '<input type="hidden" id="uploaded_image_url_' . esc_attr($option['id']) . '" 
        name="addon_image[' . esc_attr($addon['product_addon_group_id']) . '][' . esc_attr($option['id']) . '][' . esc_attr(ProductAddOnHandler::ADDON_TYPE_IMAGE) . ']" 
        value="">';
    echo '<div id="validation-message-' . esc_attr($option['id']) . '" class="validation-message"></div>';
    echo '<div id="success-message" style="display: none; color: green; font-weight: bold; text-align: center;"></div>';
    echo '</li>';
}
echo '</ul>';
echo '</div>';
?>

<div id="image-upload-popup" class="image-upload-popup" style="display:none;">
    <div class="popup-content">
        <span class="popup-close">&times;</span>
        <h2><?php echo __(
                'Select an image', I18N::DOMAIN
            ) ?>
        </h2>
        <input type="file" id="image-upload-input" accept="image/*">
        <button type="button" id="upload-image-btn-popup">
            <?php echo __(
            'Upload', I18N::DOMAIN
            ) ?>
        </button>
        <div style="display: none;">
            <input type="hidden" id="image_min_h" value="<?php echo (int) $image_min_h; ?>">
            <input type="hidden" id="image_min_w" value="<?php echo (int) $image_min_w; ?>">
            <input type="hidden" id="image_max_h" value="<?php echo (int) $image_max_h; ?>">
            <input type="hidden" id="image_max_w" value="<?php echo (int) $image_max_w; ?>">
        </div>
    </div>
</div>

<style>
    .upload-image-btn {
        padding:5px;
        margin-left: 10px;
        border-radius: 10px;
    }

    #upload-image-btn-popup {
        padding:3px;
        margin-left: 10px;
        border-radius: 10px;
    }

    .image-upload-popup {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .popup-content {
        background: white;
        padding: 20px;
        border-radius: 8px;
        width: 400px;
        max-width: 90%;
        text-align: center;
        position: relative;
    }

    .popup-close {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 24px;
        cursor: pointer;
    }

    li {
        list-style: none;
    }

    input[type="checkbox"] {
        width: 1.5em;
        height: 1.5rem;
        vertical-align: middle;
    }
</style>