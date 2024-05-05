<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

abstract class AbstractPostSyncMetaBox
{
    public const ACTION_QUERY_ARGUMENT = 'action';

    abstract public function register(): void;

    abstract public function renderSyncBox(\WP_Post $post): void;

    abstract public function doSync(int $postId): void;

    protected function showPossibleError(): void
    {
        if (array_key_exists('sk_sync_error', $_REQUEST)) {
            $message = sanitize_text_field($_REQUEST['sk_sync_error']);
            echo <<<HTML
                    <div class="notice notice-error">
                        <h4>$message</h4>
                    </div>
            HTML;
        }
    }

    protected function showPossibleSuccess(): void
    {
        if (array_key_exists('sk_sync_success', $_REQUEST)) {
            $message = sanitize_text_field($_REQUEST['sk_sync_success']);
            echo <<<HTML
                    <div class="notice notice-success">
                        <h4>$message</h4>
                    </div>
            HTML;
        }
    }

    protected function getNonceSyncActionLink(\WP_Post $post): string
    {
        $post_type_object = get_post_type_object($post->post_type);
        $syncLink = add_query_arg(
            self::ACTION_QUERY_ARGUMENT,
            static::ACTION_NAME,
            admin_url(sprintf($post_type_object->_edit_link, $post->ID))
        );

        return wp_nonce_url($syncLink, static::ACTION_NAME.'_post_'.$post->ID);
    }

    protected function isNonceValid(int $postId): bool
    {
        $nonce = $_REQUEST['_wpnonce'] ?? null; // no need to escape, wp_verify_nonce compares hash

        return 1 === wp_verify_nonce($nonce, static::ACTION_NAME.'_post_'.$postId);
    }

    protected function getPostMeta($postId, string $metaKey, $fallback)
    {
        if (metadata_exists('post', $postId, $metaKey)) {
            return get_post_meta($postId, $metaKey, true);
        }

        return $fallback;
    }
}
