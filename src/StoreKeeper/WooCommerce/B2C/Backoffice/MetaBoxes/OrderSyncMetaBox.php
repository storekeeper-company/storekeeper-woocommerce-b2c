<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use Exception;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class OrderSyncMetaBox extends AbstractMetaBox
{
    const ACTION_NAME = 'sk_sync_order';

    final public function register(): void
    {
        if ('add' !== get_current_screen()->action) {
            foreach (wc_get_order_types('order-meta-boxes') as $type) {
                $orderTypeObject = get_post_type_object($type);
                add_meta_box(
                    'storekeeper-order-sync',
                    sprintf(__('%s sync', I18N::DOMAIN), $orderTypeObject->labels->singular_name),
                    [$this, 'renderSyncBox'],
                    $type,
                    'side',
                    'high'
                );
            }
        }
    }

    final public function renderSyncBox($post): void
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }

        $syncUrl = esc_url($this->getNonceSyncActionLink($post));

        $storekeeperId = (int) $this->getPostMeta($theorder->get_id(), 'storekeeper_id', 0);
        $idLabel = esc_html__('Backoffice ID', I18N::DOMAIN);
        $idValue = esc_html($storekeeperId ?: '-');

        $dateLabel = esc_html__('Last sync', I18N::DOMAIN);
        $dateValue = esc_html($this->getPostMeta($theorder->get_id(), 'storekeeper_sync_date', '-'));

        $backoffice = '';
        if (0 !== $storekeeperId && StoreKeeperOptions::isConnected()) {
            $backofficeLabel = esc_html__('Open in backoffice', I18N::DOMAIN);
            $backofficeLink = esc_attr(StoreKeeperOptions::getBackofficeUrl()."#order/details/$storekeeperId");
            $backoffice = <<<HTML
                        <a href="$backofficeLink" class="order_backoffice_submission" target="_blank">$backofficeLabel</a>
                        HTML;
        }

        $submitLabel = esc_html__('Force sync', I18N::DOMAIN);
        echo <<<HTML
            <ul class="order_actions submitbox">
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
                    <a href="$syncUrl" class="button-primary order_sync_submission">$submitLabel</a>
                    $backoffice
                </li>
            </ul>
            HTML;

        wp_enqueue_style('product-meta-box', plugin_dir_url(__FILE__).'../static/meta-boxes.css');
        $this->showPossibleError();
    }

    /**
     * Function to sync order from wordpress to storekeeper backoffice.
     *
     * @throws Exception
     */
    final public function doSync(int $postId): void
    {
        if (!$this->isNonceValid($postId)) {
            // Nonce expired, user can just try again.
            $message = __('Failed to sync order', I18N::DOMAIN).': '.__('Please try again', I18N::DOMAIN);
            wp_redirect(
                get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($message)
            );
            exit();
        }

        if (wc_get_order($postId)) {
            $export = new OrderExport(
                [
                    'id' => $postId,
                ]
            );
            $exception = current($export->run());

            if ($exception) {
                $message = $exception->getMessage();
                if ($exception instanceof GeneralException) {
                    $message = "[{$exception->getClass()}] {$exception->getMessage()}";
                }
                $error = __('Failed to sync order', I18N::DOMAIN).': '.$message;
                wp_redirect(
                    get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($error)
                );
                exit();
            }
        }

        wp_redirect(get_edit_post_link($postId, 'url'));
        exit();
    }
}
