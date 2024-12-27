<?php
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

$image_min_h = $addon['image_min_h'];
$image_min_w = $addon['image_min_w'];
$image_max_h = $addon['image_max_h'];
$image_max_w = $addon['image_max_w'];

$is_required = (ProductAddOnHandler::ADDON_TYPE_REQUIRED_IMAGE === $addon['type']);

echo '<div class="sk-addon-select sk-addon-'.$addon['type'].'" '.ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID.'="'.$addon['product_addon_group_id'].' '.(!$is_required ? 'addon-image-optional' : '').'">';
echo '<label for="agree-images">
        <input type="checkbox" name="agree" id="agree" '.($is_required ? 'checked disabled' : '').'> 
        <strong>'.esc_html(
    $is_required
        ? __('Required Image', I18N::DOMAIN)
        : __('Would you like an image on the product?', I18N::DOMAIN)
).'</strong>
      </label>';
echo '<input type="hidden" id="product-id" value="'.get_the_ID().'">';
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li id="option-'.esc_attr($option['id']).'">';
    echo esc_html($option['title']).
        '<span style="font-size: 0.8em;">'.
        ($option['ppu_wt'] > 0
            ? '+'.esc_html(strip_tags(wc_price($option['ppu_wt'])))
            : ' ('.esc_html__('free', I18N::DOMAIN).')').
        '</span>';

    echo '<br><button type="button" class="upload-image-btn" data-option-id="'.esc_attr($option['id']).'">'.esc_html__('Upload Image', I18N::DOMAIN).'</button>';
    echo '<div class="image-preview-container" id="image-preview-container-'.esc_attr($option['id']).'" style="margin-top: 10px;">';
    echo '<a href="" class="uploaded-image" id="image-preview-'.esc_attr($option['id']).'"></a>';
    echo '</div>';
    echo '<input type="hidden" id="uploaded_image_url_'.esc_attr($option['id']).'" 
        name="addon_image['.esc_attr($addon['product_addon_group_id']).']['.esc_attr($option['id']).']['.esc_attr(ProductAddOnHandler::ADDON_TYPE_IMAGE).']" 
        value="">';
    echo '<div id="validation-message-'.esc_attr($option['id']).'" class="validation-message"></div>';
    echo '<div id="success-message" style="display: none; color: green; font-weight: bold; text-align: center;"></div>';
    echo '</li>';
}
echo '</ul>';
echo '</div>';
?>

<div id="image-upload-popup" class="image-upload-popup" style="display:none;">
    <div class="popup-content">
        <span class="popup-close">&times;</span>
        <h2><?php echo esc_html__(
            'Select an image', I18N::DOMAIN
        ); ?>
        </h2>
        <input type="file" id="image-upload-input" accept="image/*">
        <button type="button" id="upload-image-btn-popup">
            <?php echo esc_html__(
                'Upload', I18N::DOMAIN
            ); ?>
        </button>
        <div id="image-preview" style="margin-top: 20px; display: none;">
            <img id="preview-img" src="" alt="Image preview" style="max-width: 100%; max-height: 300px;">
        </div>
        <div style="display: none;">
            <input type="hidden" id="image_min_h" value="<?php echo (int) $image_min_h; ?>">
            <input type="hidden" id="image_min_w" value="<?php echo (int) $image_min_w; ?>">
            <input type="hidden" id="image_max_h" value="<?php echo (int) $image_max_h; ?>">
            <input type="hidden" id="image_max_w" value="<?php echo (int) $image_max_w; ?>">
        </div>
    </div>
</div>
