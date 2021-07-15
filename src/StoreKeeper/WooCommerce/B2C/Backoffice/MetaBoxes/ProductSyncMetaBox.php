<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessSingleTask;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProductSyncMetaBox extends AbstractMetaBox
{
    const ACTION_NAME = 'sk_sync_product';
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
        $this->showPossibleSuccess();
    }

    /**
     * Function to sync product from storekeeper backoffice to wordpress.
     *
     * @throws \Exception
     */
    final public function doSync(int $postId): void
    {
        $nonce = array_key_exists('_wpnonce', $_REQUEST) ? $_REQUEST['_wpnonce'] : null; // no need to escape, wp_verify_nonce compares hash
        if (1 === wp_verify_nonce($nonce, self::ACTION_NAME.'_post_'.$postId)) {
            if (wc_get_product($postId)) {
                $product = wc_get_product($postId);
                $storekeeperId = $this->getPostMeta($product->get_id(), 'storekeeper_id', false);
                $tasks = $this->getTasks($storekeeperId);

                if (empty($tasks)) {
                    $noUpdateMessage = __('Product is in sync and no updates were found.', I18N::DOMAIN);
                    wp_redirect(
                        get_edit_post_link($postId, 'url').'&sk_sync_success='.urlencode($noUpdateMessage)
                    );
                    exit();
                }

                $processor = new ProcessSingleTask();
                try {
                    foreach ($tasks as $task) {
                        $processor->execute([
                            $task['id'],
                        ], []);

                        $task['status'] = TaskHandler::STATUS_SUCCESS;

                        TaskModel::update($task['id'], $task);
                    }
                } catch (\Throwable $throwable) {
                    $errorMessage = $throwable->getMessage();
                    $message = __('Something went wrong while syncing product', I18N::DOMAIN).': '.$errorMessage;
                    wp_redirect(
                        get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($message)
                    );
                    exit();
                }

                $successMessage = __('Product was synced successfully.', I18N::DOMAIN);
                wp_redirect(
                    get_edit_post_link($postId, 'url').'&sk_sync_success='.urlencode($successMessage)
                );
                exit();
            }
        }

        // Nonce expired, user can just try again.
        $message = __('Failed to sync product', I18N::DOMAIN).': '.__('Please try again', I18N::DOMAIN);
        wp_redirect(
            get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($message)
        );
        exit();
    }

    private function getTasks(int $storekeeperId): array
    {
        if (!is_null($storekeeperId)) {
            try {
                return TaskHandler::getTasksByStorekeeperId($storekeeperId);
            } catch (\Throwable $throwable) {
            }
        }

        return [];
    }
}
