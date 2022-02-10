<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use Exception;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceSingleProductImage;
use StoreKeeper\WooCommerce\B2C\Commands\WebCommandRunner;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProductImageMetabox extends AbstractMetaBox
{
    const ACTION_NAME = 'sk_sync_product_image';
    const POST_TYPE = 'product';

    final public function register(): void
    {
        $action = get_current_screen()->action;

        if ('add' !== $action) {
            add_meta_box(
                'storekeeper-product-iamge-sync',
                __('Image synchronization'),
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

        $submitLabel = esc_html__('Force sync', I18N::DOMAIN);
        echo <<<HTML
                <a href="$syncUrl" class="button-primary product_sync_submission">$submitLabel</a>
            HTML;

        wp_enqueue_style('order-meta-box', plugin_dir_url(__FILE__).'../static/meta-boxes.css');
        $this->showPossibleError();
        $this->showPossibleSuccess();
    }

    /**
     * Function to sync product from storekeeper backoffice to wordpress.
     *
     * @throws Exception
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
                    $runner->addCommandClass(SyncWoocommerceSingleProductImage::class);
                    $runner->execute(SyncWoocommerceSingleProductImage::getCommandName(), [], [
                        'storekeeper_id' => $storekeeperId,
                    ]);
//                    foreach ($tasks as $task) {
//                        $task['status'] = TaskHandler::STATUS_SUCCESS;
//                        TaskModel::update($task['id'], $task);
//                    }
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
            return TaskHandler::getNewTasksByStorekeeperId($storekeeperId) ?? [];
        }

        return [];
    }
}
