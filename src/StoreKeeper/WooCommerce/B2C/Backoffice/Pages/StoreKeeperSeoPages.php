<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;

class StoreKeeperSeoPages implements WithHooksInterface
{
    public function registerHooks(): void
    {
        if (StoreKeeperSeo::isSelectedHandler()) {
            add_filter('woocommerce_product_data_tabs', [$this, 'addSeoProductTab']);
            add_action('woocommerce_product_data_panels', [$this, 'renderProductTab']);
            add_action('woocommerce_admin_process_product_object', [$this, 'saveProductSeo']);

            add_action('product_cat_add_form_fields', [$this, 'renderCategoryCreateFields'], 10, 2);
            add_action('product_cat_edit_form_fields', [$this, 'renderCategoryEditFields'], 10, 1);
            add_action('edited_product_cat', [$this, 'saveCategorySeo'], 10, 1);
            add_action('create_product_cat', [$this, 'saveCategorySeo'], 10, 1);

            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_style('storekeeper-seo-admin', plugin_dir_url(__FILE__).'../static/seo.css');
            });
        }
    }

    public function addSeoProductTab($tabs): array
    {
        $tabs['storekeeper_seo'] = [
            'label' => __('StoreKeeper Seo', I18N::DOMAIN),
            'target' => 'storekeeper_seo_options',
        ];

        return $tabs;
    }

    public function renderCategoryCreateFields(): void
    {
        $this->renderCategoryAddTextField(StoreKeeperSeo::SEO_TITLE, __('Seo title', I18N::DOMAIN));
        $this->renderCategoryAddTextField(StoreKeeperSeo::SEO_KEYWORDS, __('Seo keywords', I18N::DOMAIN));
        $this->renderCategoryAddTextArea(StoreKeeperSeo::SEO_DESCRIPTION, __('Seo description', I18N::DOMAIN));
    }

    public function renderCategoryEditFields($term): void
    {
        $attributes = [];
        $storekeeper_id = get_term_meta($term->term_id, 'storekeeper_id', true);
        $seo = StoreKeeperSeo::getCategorySeo($term);
        if (!empty($storekeeper_id)) {
            $attributes += ['disabled' => 'disabled'];
        }
        $this->renderCategoryEditTextField(
            StoreKeeperSeo::SEO_TITLE,
            __('Seo title', I18N::DOMAIN),
            $seo[StoreKeeperSeo::SEO_TITLE],
            $attributes
        );
        $this->renderCategoryEditTextField(
            StoreKeeperSeo::SEO_KEYWORDS,
            __('Seo keywords', I18N::DOMAIN),
            $seo[StoreKeeperSeo::SEO_KEYWORDS],
            $attributes
        );

        $this->renderCategoryEditTextArea(
            StoreKeeperSeo::SEO_DESCRIPTION,
            __('Seo description', I18N::DOMAIN),
            $seo[StoreKeeperSeo::SEO_DESCRIPTION],
            $attributes
        );
    }

    public function saveCategorySeo($term_id)
    {
        $term = get_term($term_id);
        $storekeeper_id = get_term_meta($term->term_id, 'storekeeper_id', true);
        if (empty($storekeeper_id)) {
            //  if storekeeper_id is set than it should be managed in storekeeper, not in WooCommerce
            $title = wc_clean($_POST[StoreKeeperSeo::SEO_TITLE]);
            $keywords = wc_clean($_POST[StoreKeeperSeo::SEO_KEYWORDS]);
            $description = wc_clean($_POST[StoreKeeperSeo::SEO_DESCRIPTION]);

            StoreKeeperSeo::setCategorySeo($term, $title, $keywords, $description);
        }
    }

    public function renderProductTab(): void
    {
        global $post;
        if ($post instanceof \WC_Product) {
            $product = $post;
        } else {
            $product = wc_get_product($post->ID);
        }
        $storekeeper_id = $product->get_meta('storekeeper_id', true, 'edit');

        $seo = StoreKeeperSeo::getProductSeo($product);
        $attributes = [];
        $description = '';
        if (!empty($storekeeper_id)) {
            $attributes += ['disabled' => 'disabled'];
        } ?>
        <div id='storekeeper_seo_options' class='panel woocommerce_options_panel'>
            <?php
            if (!empty($storekeeper_id)) {
                echo '<p>';
                echo __('This is managed product, you can only edit this seo information in StoreKeeper Backoffice.', I18N::DOMAIN);
                echo '</p>';
            }
        woocommerce_wp_text_input(
            [
                'id' => StoreKeeperSeo::SEO_TITLE,
                'label' => __('Title', I18N::DOMAIN),
                'value' => $seo[StoreKeeperSeo::SEO_TITLE],
                'description' => $description,
                'desc_tip' => true,
                'custom_attributes' => $attributes,
            ]
        );
        woocommerce_wp_text_input(
            [
                'id' => StoreKeeperSeo::SEO_KEYWORDS,
                'label' => __('Keywords', I18N::DOMAIN),
                'value' => $seo[StoreKeeperSeo::SEO_KEYWORDS],
                'description' => $description,
                'desc_tip' => true,
                'custom_attributes' => $attributes,
            ]
        );
        woocommerce_wp_textarea_input(
            [
                'id' => StoreKeeperSeo::SEO_DESCRIPTION,
                'label' => __('Description', I18N::DOMAIN),
                'value' => $seo[StoreKeeperSeo::SEO_DESCRIPTION],
                'description' => $description,
                'desc_tip' => true,
                'custom_attributes' => $attributes,
            ]
        ); ?>
        </div>
        <?php
    }

    public function saveProductSeo(\WC_Product $product): void
    {
        $storekeeper_id = $product->get_meta('storekeeper_id', true, 'edit');
        if (empty($storekeeper_id)) {
            $title = wc_clean($_POST[StoreKeeperSeo::SEO_TITLE]);
            $keywords = wc_clean($_POST[StoreKeeperSeo::SEO_KEYWORDS]);
            $description = wc_clean($_POST[StoreKeeperSeo::SEO_DESCRIPTION]);

            StoreKeeperSeo::setProductSeo($product, $title, $keywords, $description);
        }
    }

    protected function renderCategoryAddTextField(string $field, string $label): void
    {
        $label = esc_html($label);
        echo <<<HTML
        <div class="form-field">
            <label for="$field">$label</label>
            <input type="text" name="$field" id="$field" value="">
        </div>
        HTML;
    }

    protected function renderCategoryAddTextArea(string $field, string $label): void
    {
        $label = esc_html($label);
        echo <<<HTML
        <div class="form-field">
            <label for="$field">$label</label>
            <textarea rows="3" name="$field" id="$field"></textarea>
        </div>
        HTML;
    }

    protected function renderCategoryEditTextField(string $field, string $label, string $value, array $attributes = []): void
    {
        $custom_attributes = $this->formatCustomAttributes($attributes);
        $label = esc_html($label);
        $value = esc_html($value);
        echo <<<HTML
        <tr class="form-field">
            <th scope="row" valign="top"><label for="$field">$label</label></th>
            <td>
            <input type="text" name="$field" id="$field" value="$value" $custom_attributes>
            </td>
        </tr>
        HTML;
    }

    protected function renderCategoryEditTextArea(string $field, string $label, string $value, array $attributes = []): void
    {
        $custom_attributes = $this->formatCustomAttributes($attributes);
        $label = esc_html($label);
        $value = esc_html($value);
        echo <<<HTML
        <tr class="form-field">
            <th scope="row" valign="top"><label for="$field">$label</label></th>
            <td>
            <textarea rows="3" name="$field" id="$field" $custom_attributes>$value</textarea>
            </td>
        </tr>
        HTML;
    }

    protected function formatCustomAttributes(array $attributes): string
    {
        $custom_attributes = '';
        foreach ($attributes as $attribute => $attrValue) {
            $custom_attributes .= ' '.esc_attr($attribute).'="'.esc_attr($attrValue).'"';
        }

        return $custom_attributes;
    }
}
