<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use Automattic\WooCommerce\Admin\Overrides\Order;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class OrderSyncMetaBox extends AbstractPostSyncMetaBox implements WithHooksInterface
{
    public const ACTION_NAME = 'sk_sync_order';

    final public function register(): void
    {
        if ('add' !== get_current_screen()->action) {
            if (BackofficeCore::isHighPerformanceOrderStorageReady()) {
                $screen = wc_get_page_screen_id('shop-order');
                add_meta_box(
                    'storekeeper-order-sync',
                    __('Order sync', I18N::DOMAIN),
                    [$this, 'renderSyncBox'],
                    $screen,
                    'side',
                    'high'
                );
            } else {
                $this->registerLegacyOrderScreen();
            }
        }
    }

    /**
     * @deprecated
     * Support for old WooCommerce which does not support High Performance Order Storage
     * To be removed in future
     */
    private function registerLegacyOrderScreen(): void
    {
        // Support for old WooCommerce which does not support High Performance Order Storage
        foreach (wc_get_order_types('order-meta-boxes') as $type) {
            $orderTypeObject = get_post_type_object($type);
            if (!is_null($orderTypeObject)) {
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

        $storekeeperId = (int) $theorder->meta_exists('storekeeper_id') ? $theorder->get_meta('storekeeper_id') : 0;

        $idLabel = esc_html__('Backoffice ID', I18N::DOMAIN);
        $idValue = esc_html($storekeeperId ?: '-');

        $dateLabel = esc_html__('Last sync', I18N::DOMAIN);
        $dateValue = DatabaseConnection::formatFromDatabaseDateIfNotEmpty(
            esc_html($theorder->meta_exists('storekeeper_sync_date') ? $theorder->get_meta('storekeeper_sync_date') : null)
        );
        $syncDateForDisplay = DateTimeHelper::formatForDisplay($dateValue);

        $backoffice = '';
        if (0 !== $storekeeperId && StoreKeeperOptions::isConnected()) {
            $backofficeLabel = esc_html__('Open in backoffice', I18N::DOMAIN);
            $backofficeLink = esc_attr(StoreKeeperOptions::getBackofficeUrl()."#order/details/$storekeeperId");
            $backoffice = <<<HTML
                        <a href="$backofficeLink" class="order_backoffice_submission" target="_blank">$backofficeLabel</a>
                        HTML;
        }

        $submitLabel = esc_html__('Force sync', I18N::DOMAIN);

        $manualSyncWarning = '';
        if (!StoreKeeperOptions::isOrderSyncEnabled()) {
            $manualSyncWarningText = esc_html__("All orders won't be synced automatically with your currently selected Synchronization mode, but you can do it manually using the Force sync button", I18N::DOMAIN);
            $manualSyncWarning = <<<HTML
                <li class="wide">
                    <small class="text-danger">$manualSyncWarningText</small>
                </li>
            HTML;
        }

        echo <<<HTML
            <ul class="order_actions submitbox">
                <li class="wide">
                    <div>   
                        <strong>$idLabel:</strong>
                        <div>$idValue</div>
                    </div>
                    <div>
                        <strong>$dateLabel:</strong>
                        <div>$syncDateForDisplay</div>
                    </div>
                </li>
                <li class="wide">        
                    <a href="$syncUrl" class="button-primary order_sync_submission">$submitLabel</a>
                    $backoffice
                </li>
                $manualSyncWarning
            </ul>
            HTML;

        wp_enqueue_style('product-meta-box', plugin_dir_url(__FILE__).'../static/meta-boxes.css');
        $this->showPossibleError();
        $this->showPossibleSuccess();
    }

    /**
     * Function to sync order from wordpress to storekeeper backoffice.
     *
     * @throws \Exception
     */
    final public function doSync(int $postId): void
    {
        $editUrl = get_edit_post_link($postId, 'url');
        $wooCommerceOrder = wc_get_order($postId);
        if (BackofficeCore::isHighPerformanceOrderStorageReady()) {
            $editUrl = $wooCommerceOrder->get_edit_order_url();
        }

        if (!$this->isNonceValid($postId)) {
            // Nonce expired, user can just try again.
            $message = __('Failed to sync order', I18N::DOMAIN).': '.__('Please try again', I18N::DOMAIN);
            wp_redirect(
                add_query_arg(
                    'sk_sync_error',
                    urlencode($message),
                    $editUrl
                )
            );
            exit;
        }

        if ($wooCommerceOrder) {
            $export = new OrderExport(
                [
                    'id' => $postId,
                ]
            );

            try {
                $export->run();
            } catch (\Throwable $throwable) {
                $message = $throwable->getMessage();
                if ($throwable instanceof GeneralException) {
                    $message = "[{$throwable->getClass()}] {$throwable->getMessage()}";
                }
                $error = __('Failed to sync order', I18N::DOMAIN).': '.$message;
                wp_redirect(
                    add_query_arg(
                        'sk_sync_error',
                        urlencode($error),
                        $editUrl
                    )
                );
                exit;
            }
        }

        $successMessage = __('Order was synced successfully.', I18N::DOMAIN);
        wp_redirect(
            add_query_arg(
                'sk_sync_success',
                urlencode($successMessage),
                $editUrl
            ),
        );
        exit;
    }

    private function handleSave(): void
    {
        if (isset($_GET['action'], $_GET['id']) && self::ACTION_NAME === $_GET['action']) {
            $wooCommerceOrderId = sanitize_text_field($_GET['id']);
            $this->doSync($wooCommerceOrderId);
        }
    }

    public function registerHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('woocommerce_after_register_post_type', [$this, 'setup']);
    }

    public function setup(): void
    {
        if (BackofficeCore::isHighPerformanceOrderStorageReady()) {
            $this->handleSave();
        } else {
            // This means it still uses the /wp-admin/post.php
            add_action('post_action_'.self::ACTION_NAME, [$this, 'doSync']);
        }
    }

    protected function getNonceSyncActionLink($post): string
    {
        if ($post instanceof \WP_Post) {
            return parent::getNonceSyncActionLink($post);
        }

        /** @var Order $wooCommerceOrder */
        $wooCommerceOrder = $post;

        $syncLink = add_query_arg(
            self::ACTION_QUERY_ARGUMENT,
            static::ACTION_NAME,
            $wooCommerceOrder->get_edit_order_url()
        );

        return wp_nonce_url($syncLink, static::ACTION_NAME.'_post_'.$wooCommerceOrder->get_id());
    }
}
