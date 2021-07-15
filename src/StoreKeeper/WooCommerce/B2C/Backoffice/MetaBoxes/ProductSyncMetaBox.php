<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class ProductSyncMetaBox extends AbstractMetaBox
{
    const ACTION_NAME = 'sk_sync';
    const POST_TYPE = 'product';

    final public function register(): void
    {
        $action = get_current_screen()->action;

        if ('add' !== $action) {
            add_meta_box(
                'storekeeper-product-sync',
                __('Product synchronization'),
                [$this, 'renderSyncBox'],
                self::POST_TYPE,
                'side',
                'high'
            );
        }
    }

    final public function renderSyncBox($post): void
    {
        $product = wc_get_product($post->ID);
        $syncUrl = esc_attr($this->getNonceSyncActionLink($post));
        $storekeeperId = $this->getPostMeta($product->get_id(), 'storekeeper_id', false);

        $idLabel = esc_html__('Backoffice ID', I18N::DOMAIN);
        $idValue = esc_html($storekeeperId ?: '-');

        $dateLabel = esc_html__('Last sync', I18N::DOMAIN);
        $dateValue = esc_html($this->getPostMeta($product->get_id(), 'storekeeper_sync_date', '-'));

        $backoffice = '';
        if ($storekeeperId && StoreKeeperOptions::isConnected()) {
            $backofficeLabel = esc_html__('Open in backoffice', I18N::DOMAIN);
            $backofficeLink = esc_attr(StoreKeeperOptions::getBackofficeUrl()."#products/details/$storekeeperId");
            $backoffice = <<<HTML
                        <a href="$backofficeLink" class="product_backoffice_link" target="_blank">$backofficeLabel</a>
                        HTML;
        }

        $submitLabel = esc_html__('Force sync', I18N::DOMAIN);
        echo <<<HTML
            <ul class="product_actions submitbox">
                <li class="wide">
                    <div>   
                        <strong>$idLabel:</strong>
                        <div>$idValue</div>
                    </div>
                    <div>
                        <strong>$dateLabel:</strong>
                        <div>$dateValue</div>
                    </div>
                </li>
                <li class="wide">        
                    <a href="$syncUrl" class="button-primary product_sync_submission">$submitLabel</a>
                    $backoffice
                </li>
            </ul>
            <style>
                #poststuff #storekeeper-product-sync .inside {
                    margin: 0;
                    padding: 0;
                } 
                #poststuff #storekeeper-product-sync .inside ul.product_actions li {
                    padding: 6px 10px;
                    box-sizing: border-box;
                } 
                #poststuff #storekeeper-product-sync .inside .product_sync_submission {
                    float: right;
                } 
            </style>
            HTML;

        $this->showPossibleError();
    }
}
