<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceSingleProduct;
use StoreKeeper\WooCommerce\B2C\Commands\WebCommandRunner;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProductSyncMetaBox extends AbstractPostSyncMetaBox
{
    public const ACTION_NAME = 'sk_sync_product';
    public const POST_TYPE = 'product';

    final public function register(): void
    {
        $action = get_current_screen()->action;

        if ('add' !== $action) {
            add_meta_box(
                'storekeeper-product-sync',
                __('Product synchronization', I18N::DOMAIN),
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
        $syncUrl = esc_url($this->getNonceSyncActionLink($post));
        $storekeeperId = (int) $this->getPostMeta($product->get_id(), 'storekeeper_id', 0);

        $idLabel = esc_html__('Backoffice ID', I18N::DOMAIN);
        $idValue = esc_html($storekeeperId ?: '-');

        $dateLabel = esc_html__('Last sync', I18N::DOMAIN);
        $dateValue = DatabaseConnection::formatFromDatabaseDateIfNotEmpty(
            esc_html($this->getPostMeta($product->get_id(), 'storekeeper_sync_date', null))
        );
        $syncDateForDisplay = DateTimeHelper::formatForDisplay($dateValue);

        $backoffice = '';
        if (0 !== $storekeeperId && StoreKeeperOptions::isConnected()) {
            $backofficeLabel = esc_html__('Open in backoffice', I18N::DOMAIN);
            $backofficeLink = esc_attr(StoreKeeperOptions::getBackofficeUrl()."#shop-products/details/$storekeeperId");
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
                        <div>$syncDateForDisplay</div>
                    </div>
                </li>
                <li class="wide">        
                    <a href="$syncUrl" class="button-primary product_sync_submission">$submitLabel</a>
                    $backoffice
                </li>
            </ul>
            HTML;

        wp_enqueue_style('order-meta-box', plugin_dir_url(__FILE__).'../static/meta-boxes.css');
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
        if ($this->isNonceValid($postId)) {
            if ($product = wc_get_product($postId)) {
                $storekeeperId = $this->getPostMeta($product->get_id(), 'storekeeper_id', false);
                $tasks = $this->getTasks($storekeeperId);

                if ($product->is_type('variable')) {
                    $variationPostIds = $product->get_children();
                    // Get all tasks related to variations and add to tasks
                    foreach ($variationPostIds as $variationPostId) {
                        $variationStorekeeperId = $this->getPostMeta($variationPostId, 'storekeeper_id', false);

                        foreach ($this->getTasks($variationStorekeeperId) as $task) {
                            $tasks[] = $task;
                        }
                    }
                }

                try {
                    $runner = new WebCommandRunner();
                    $runner->addCommandClass(SyncWoocommerceSingleProduct::class);
                    $runner->execute(SyncWoocommerceSingleProduct::getCommandName(), [], [
                        'storekeeper_id' => $storekeeperId,
                    ]);
                    foreach ($tasks as $task) {
                        $task['status'] = TaskHandler::STATUS_SUCCESS;
                        TaskModel::update($task['id'], $task);
                    }
                } catch (\Throwable $throwable) {
                    $errorMessage = $throwable->getMessage();
                    $message = __('Something went wrong while syncing product', I18N::DOMAIN).': '.$errorMessage;
                    wp_redirect(
                        get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($message)
                    );
                    exit;
                }

                $successMessage = __('Product was synced successfully.', I18N::DOMAIN);
                wp_redirect(
                    get_edit_post_link($postId, 'url').'&sk_sync_success='.urlencode($successMessage)
                );
                exit;
            }
        }

        // Nonce expired, user can just try again.
        $message = __('Failed to sync product', I18N::DOMAIN).': '.__('Please try again', I18N::DOMAIN);
        wp_redirect(
            get_edit_post_link($postId, 'url').'&sk_sync_error='.urlencode($message)
        );
        exit;
    }

    private function getTasks(int $storekeeperId): array
    {
        if (!is_null($storekeeperId)) {
            return TaskHandler::getNewTasksByStorekeeperId($storekeeperId) ?? [];
        }

        return [];
    }
}
