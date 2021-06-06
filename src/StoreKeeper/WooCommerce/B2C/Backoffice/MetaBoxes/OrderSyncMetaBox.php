<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use WP_Post;

class OrderSyncMetaBox
{
    const ACTION_NAME = 'sk_sync';

    public function register()
    {
        if ('add' !== get_current_screen()->action) {
            foreach (wc_get_order_types('order-meta-boxes') as $type) {
                $orderTypeObject = get_post_type_object($type);
                add_meta_box(
                    'storekeeper-order-sync',
                    sprintf(__('%s sync', I18N::DOMAIN), $orderTypeObject->labels->singular_name),
                    '\StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes\OrderSyncMetaBox::output',
                    $type,
                    'side',
                    'high'
                );
            }
        }
    }

    public function action($post_id)
    {
        $nonce = array_key_exists('_wpnonce', $_REQUEST) ? $_REQUEST['_wpnonce'] : null; // no need to escape, wp_verify_nonce compares hash
        if (1 === wp_verify_nonce($nonce, self::ACTION_NAME.'_post_'.$post_id)) {
            if (wc_get_order($post_id)) {
                $export = new OrderExport(
                    [
                        'id' => $post_id,
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
                        get_edit_post_link($post_id, 'url').'&sk_sync_error='.urlencode($error)
                    );
                    exit();
                }
            }

            wp_redirect(get_edit_post_link($post_id, 'url'));
            exit();
        }

        // Nonce expired, user can just try again.
        $message = __('Failed to sync order', I18N::DOMAIN).': '.__('Please try again', I18N::DOMAIN);
        wp_redirect(
            get_edit_post_link($post_id, 'url').'&sk_sync_error='.urlencode($message)
        );
        exit();
    }

    private static function showPossibleError()
    {
        if (array_key_exists('sk_sync_error', $_REQUEST)) {
            $message = esc_html($_REQUEST['sk_sync_error']);
            echo <<<HTML
        <div class="notice notice-error">
            <h4>$message</h4>
        </div>
HTML;
        }
    }

    /**
     * @param WP_Post $post
     */
    public static function output($post)
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }

        $syncUrl = esc_attr(self::getNonceSyncActionLink($post));

        $skId = self::getPostMeta($theorder->get_id(), 'storekeeper_id', false);
        $idLabel = esc_html__('Backoffice ID', I18N::DOMAIN);
        $idValue = esc_html($skId ? $skId : '-');

        $dateLabel = esc_html__('Last sync', I18N::DOMAIN);
        $dateValue = esc_html(self::getPostMeta($theorder->get_id(), 'storekeeper_sync_date', '-'));

        $backoffice = '';
        if ($skId && StoreKeeperOptions::isConnected()) {
            $backofficeLabel = esc_html__('Open in backoffice', I18N::DOMAIN);
            $backofficeLink = esc_attr(StoreKeeperOptions::getBackofficeUrl()."#order/details/$skId");
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
<style>
    #poststuff #storekeeper-order-sync .inside {
        margin: 0;
        padding: 0;
    } 
    #poststuff #storekeeper-order-sync .inside ul.order_actions li {
        padding: 6px 10px;
        box-sizing: border-box;
    } 
    #poststuff #storekeeper-order-sync .inside .order_sync_submission {
        float: right;
    } 
</style>
HTML;

        self::showPossibleError();
    }

    private static function getNonceSyncActionLink(WP_Post $post): string
    {
        $post_type_object = get_post_type_object($post->post_type);
        $syncLink = add_query_arg(
            'action',
            self::ACTION_NAME,
            admin_url(sprintf($post_type_object->_edit_link, $post->ID))
        );

        return wp_nonce_url($syncLink, self::ACTION_NAME.'_post_'.$post->ID);
    }

    private static function getPostMeta($postId, string $metaKey, $fallback)
    {
        if (metadata_exists('post', $postId, $metaKey)) {
            return get_post_meta($postId, $metaKey, true);
        }

        return $fallback;
    }
}
