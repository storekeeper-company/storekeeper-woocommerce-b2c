<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Notices;

use Automattic\WooCommerce\Utilities\OrderUtil;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use Throwable;

class AdminNotices
{
    public function emitNotices()
    {
        try {
            $this->connectionCheck();
            $this->lastCronCheck();
            $this->checkCronRunner();

            $this->StoreKeeperNewProduct();
            $this->StoreKeeperProductCheck();
            $this->StoreKeeperProductCategoryCheck();
            $this->StoreKeeperProductTagCheck();
            $this->StoreKeeperProductAttributeCheck();
            $this->StoreKeeperProductAttributeTermCheck();
            $this->StoreKeeperOrderCheck();
        } catch (Throwable $e) {
            AdminNotices::showError(
                sprintf(__('Failed to build notices: %s.', I18N::DOMAIN), $e->getMessage()),
                $e->getTraceAsString()
            );
        }
    }

    private function StoreKeeperNewProduct()
    {
        global $pagenow;
        $postType = $this->getRequestPostType();
        if ('post-new.php' === $pagenow && 'product' === $postType) {
            AdminNotices::showWarning(__('New products are not synced back to StoreKeeper.', I18N::DOMAIN));
        }
    }

    private function checkCronRunner(): void
    {
        try {
            CronRegistrar::checkRunnerStatus();
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            self::showError($message);
        }
    }

    private function connectionCheck()
    {
        if (!StoreKeeperOptions::isConnected()) {
            $message = __(
                'The StoreKeeper synchronization plugin extension is not connected, thus nothing is synchronized. please click configure to do so.',
                I18N::DOMAIN
            );
            $configure = __('Configure StoreKeeper synchronization plugin', I18N::DOMAIN);
            $url = admin_url('/admin.php?page=storekeeper-settings'); ?>
            <div class="notice notice-error">
                <p style="display:flex;justify-content:space-between;align-items:center;">
                    <?php
                    echo esc_html($message); ?>
                </p>
                <p>
                    <a href="<?php echo $url; ?>"
                       class="button-primary woocommerce-save-button"><?php
                        echo esc_html($configure); ?></a>
                </p>
            </div>
            <?php
        }
    }

    private function StoreKeeperProductCheck()
    {
        global $post_type;

        if ($this->isPostPage() && 'edit' === $this->getRequestAction() && 'product' === $post_type) {
            $goid = get_post_meta((int) $_GET['post'], 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('product', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductCategoryCheck()
    {
        if ($this->isProductCategoryPage()) {
            $goid = get_term_meta($this->getRequestTagId(), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('category', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductTagCheck()
    {
        if ($this->isProductTagPage()) {
            $goid = get_term_meta($this->getRequestTagId(), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('tag', I18N::DOMAIN));
            }
        }
    }

    /**
     * Show notification for Product->Attribute->edit if the attribute was synchronized from backend.
     */
    private function StoreKeeperProductAttributeCheck()
    {
        if ('product' === $this->getRequestPostType()
            && 'product_attributes' === ($_GET['page'] ?? '')
            && intval($_GET['edit'])
            && Attributes::isAttributeLinkedToBackend($_GET['edit'])
        ) {
            $this->isSyncedFromBackend(__('attribute', I18N::DOMAIN));
        }
    }

    private function StoreKeeperProductAttributeTermCheck()
    {
        if ($this->isStorekeeperProductAttributePage()) {
            $goid = get_term_meta($this->getRequestTagId(), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('attribute term', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperOrderCheck(): void
    {
        if (
            $this->isPostPage()
            && $this->isPostTypeOrder()
            && 'edit' === $this->getRequestAction()
        ) {
            $id = $this->getRequestPostId();
            $order = new \WC_Order($id);
            $storeKeeperId = $order->get_meta('storekeeper_id');

            if ($storeKeeperId && $order->has_status('completed')) {
                $message = __(
                    'This order is marked as completed, any changes might not be synced to the backoffice',
                    I18N::DOMAIN
                ); ?>
                <div class="notice notice-warning">
                    <h4>
                        <?php
                        echo esc_html($message); ?>
                    </h4>
                </div>
                <?php
            }
        }
    }

    private function isPostTypeOrder(): bool
    {
        global $post, $post_type;

        return (class_exists(OrderUtil::class) && OrderUtil::is_order($post->ID)) || in_array($post_type, wc_get_order_types(), true);
    }

    private function isSyncedFromBackend($type)
    {
        $message = __(
            "This %s is synced from the backoffice, thus any changes won't be synced back and can be overwritten when made in WooCommerce!",
            I18N::DOMAIN
        );
        $message = sprintf($message, $type);
        AdminNotices::showWarning($message);
    }

    public static function showWarning($message)
    {
        ?>
        <div class="notice notice-warning">
            <h4>
                StoreKeeper for Woocommerce:
                <?php
                echo nl2br(esc_html($message)); ?>
            </h4>
        </div>
        <?php
    }

    public static function showError($message, $description = '')
    {
        ?>
        <div class="notice notice-error">
            <h4>
                StoreKeeper for Woocommerce:
                <?php echo esc_html($message); ?>
            </h4>
            <?php
            if (!empty($description)) {
                echo '<p>';
                echo nl2br(wp_kses($description, HtmlEscape::ALLOWED_ALL_SAFE));
                echo '</p>';
            } ?>
        </div>
        <?php
    }

    public static function showException(Throwable $e, string $message)
    {
        self::showError($message, (string) $e);
    }

    public static function showInfo($message)
    {
        ?>
        <div class="notice notice-info">
            <h4>
                StoreKeeper for Woocommerce:
                <?php
                echo esc_html($message); ?>
            </h4>
        </div>
        <?php
    }

    public static function showSuccess($message)
    {
        ?>
        <div class="notice notice-success">
            <h4>
                StoreKeeper for Woocommerce:
                <?php
                echo esc_html($message); ?>
            </h4>
        </div>
        <?php
    }

    private function lastCronCheck()
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
            $cronTime = DatabaseConnection::formatFromDatabaseDate(
                WooCommerceOptions::get(WooCommerceOptions::LAST_SYNC_RUN)
            );
            $timeAgo = DateTimeHelper::dateDiff($cronTime);
            if ($timeAgo) {
                $initialMessage = __(
                    'It seems that its been %s ago since the process tasks cron has been running, Contact your system administrator to solve this problem.',
                    I18N::DOMAIN
                );
                $message = sprintf($initialMessage, $timeAgo);
                AdminNotices::showError($message);
            }
        } else {
            [
                $message,
                $description
            ] = CronRegistrar::buildMessage();

            AdminNotices::showError($message, $description);
        }
    }

    private function getRequestPostType(): ?string
    {
        if (array_key_exists('post_type', $_GET)) {
            return sanitize_key($_GET['post_type']);
        }

        return null;
    }

    private function getRequestAction(): ?string
    {
        if (array_key_exists('action', $_GET)) {
            return sanitize_key($_GET['action']);
        }

        return null;
    }

    private function isProductCategoryPage(): bool
    {
        return isset($_GET['taxonomy'])
            && 'product_cat' === $_GET['taxonomy']
            && isset($_GET['tag_ID']);
    }

    private function isProductTagPage(): bool
    {
        return isset($_GET['taxonomy'])
            && 'product_tag' === $_GET['taxonomy']
            && isset($_GET['tag_ID']);
    }

    private function getRequestTagId(): int
    {
        return (int) $_GET['tag_ID'];
    }

    private function isPostPage(): bool
    {
        return isset($_GET['post']);
    }

    private function isStorekeeperProductAttributePage(): bool
    {
        return isset($_GET['taxonomy'])
            && StringFunctions::startsWith($_GET['taxonomy'], 'pa_')
            && isset($_GET['tag_ID']);
    }

    private function getRequestPostId(): int
    {
        $id = (int) $_GET['post'];

        return $id;
    }
}
