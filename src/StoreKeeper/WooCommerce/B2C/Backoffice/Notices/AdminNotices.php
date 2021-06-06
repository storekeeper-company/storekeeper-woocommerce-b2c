<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Notices;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WooCommerceAttributeMetadata;

class AdminNotices
{
    public function emitNotices()
    {
        $this->connectionCheck();
        $this->StoreKeeperSyncCheck();
        $this->lastCronCheck();

        $this->StoreKeeperNewProduct();
        $this->StoreKeeperProductCheck();
        $this->StoreKeeperProductCategoryCheck();
        $this->StoreKeeperProductTagCheck();
        $this->StoreKeeperProductAttributeCheck();
        $this->StoreKeeperProductAttributeTermCheck();
        $this->StoreKeeperOrderCheck();
    }

    private function StoreKeeperNewProduct()
    {
        global $pagenow;
        $postType = $this->getRequestPostType();
        if ('post-new.php' === $pagenow && 'product' === $postType) {
            AdminNotices::showWarning(__('New products are not synced back to StoreKeeper.', I18N::DOMAIN));
        }
    }

    private function connectionCheck()
    {
        if (!StoreKeeperOptions::isConnected()) {
            $message = __(
                'The StoreKeeper synchronization plugin extension is not connected, thus nothing is synchronized. please click configure to do so.',
                I18N::DOMAIN
            );
            $configure = __('Configure StoreKeeper synchronization plugin', I18N::DOMAIN); ?>
            <div class="notice notice-error">
                <p style="display:flex;justify-content:space-between;align-items:center;">
                    <?php
                    echo $message; ?>
                </p>
                <p>
                    <a href="/wp-admin/admin.php?page=storekeeper-settings"
                       class="button-primary woocommerce-save-button"><?php
                        echo $configure; ?></a>
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

    private function StoreKeeperProductAttributeCheck()
    {
        if ('product' === $this->getRequestPostType()
            && 'product_attributes' === $_GET['page'] ?? ''
            && isset($_GET['edit'])
        ) {
            $goid = WooCommerceAttributeMetadata::getMetadata((int) $_GET['edit'], 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('attribute', I18N::DOMAIN));
            }
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

    private function StoreKeeperOrderCheck()
    {
        global $post_type;

        if ($this->isPostPage() && 'edit' === $this->getRequestAction() && 'shop_order' === $post_type) {
            $id = $this->getRequestPostId();
            $goid = get_post_meta($id, 'storekeeper_id', true);
            $order = new \WC_Order($id);
            if ($goid && $order->has_status('completed')) {
                $message = __(
                    'This order is marked as completed, any changes might not be synced to the backoffice',
                    I18N::DOMAIN
                ); ?>
                <div class="notice notice-warning">
                    <h4>
                        <?php
                        echo $message; ?>
                    </h4>
                </div>
                <?php
            }
        }
    }

    private function StoreKeeperSyncCheck()
    {
        global $post_type, $pagenow;

        if ($this->isProductCategoryPage()) {
            if ($fistLine = $this->taskHasError('category', $this->getRequestTagId())) {
                $this->hasTaskError(__('category', I18N::DOMAIN), $fistLine);
            }
        }

        if ($this->isProductTagPage()) {
            if ($fistLine = $this->taskHasError('tag', $this->getRequestTagId())) {
                $this->hasTaskError(__('tag', I18N::DOMAIN), $fistLine);
            }
        }

        if ($this->isPostPage() && 'edit' === $this->getRequestAction() && 'product' === $post_type) {
            if ($fistLine = $this->taskHasError('product', $this->getRequestPostId())) {
                $this->hasTaskError(__('product', I18N::DOMAIN), $fistLine);
            }
        }

        if ($this->isPostPage() && 'edit' === $this->getRequestAction() && 'shop_order' === $post_type) {
            if ($fistLine = $this->taskHasError('order', $this->getRequestPostId())) {
                $this->hasTaskError(__('order', I18N::DOMAIN), $fistLine);
            }
        }

        if ('admin.php' === $pagenow
            && 'wc-settings' === $_GET['page'] ?? ''
            && 'storekeeper-woocommerce-b2c' === $_GET['tab'] ?? ''
        ) {
            if ($fistLine = $this->taskHasError('full')) {
                $this->hasTaskError(__('full', I18N::DOMAIN), $fistLine);
            }
        }
    }

    /**
     * @param $type
     * @param $id
     *
     * @return bool
     *
     * @throws WordpressException
     */
    private function taskHasError($type, $id = 0)
    {
        switch ($type) {
            case 'category':
                $delTask = TaskHandler::getScheduledTask(TaskHandler::CATEGORY_DELETE, $id);
                $impTask = TaskHandler::getScheduledTask(TaskHandler::CATEGORY_IMPORT, $id);
                $content = $this->getTaskBody($delTask).$this->getTaskBody($impTask);
                if (!empty($content)) {
                    return esc_html(strtok($content, "\n"));
                }

                return false;
                break;
            case 'tag':
                $delTask = TaskHandler::getScheduledTask(TaskHandler::TAG_DELETE, $id);
                $impTask = TaskHandler::getScheduledTask(TaskHandler::TAG_IMPORT, $id);
                $content = $this->getTaskBody($delTask).$this->getTaskBody($impTask);
                if (!empty($content)) {
                    return esc_html(strtok($content, "\n"));
                }

                return false;
                break;
            case 'product':
                $delTask = TaskHandler::getScheduledTask(TaskHandler::PRODUCT_DELETE, $id);
                $impTask = TaskHandler::getScheduledTask(TaskHandler::PRODUCT_IMPORT, $id);
                $content = $this->getTaskBody($delTask).$this->getTaskBody($impTask);
                if (!empty($content)) {
                    return esc_html(strtok($content, "\n"));
                }

                return false;
                break;
            case 'order':
                $delTask = TaskHandler::getScheduledTask(TaskHandler::ORDERS_DELETE, $id);
                $impTask = TaskHandler::getScheduledTask(TaskHandler::ORDERS_IMPORT, $id);
                $expTask = TaskHandler::getScheduledTask(TaskHandler::ORDERS_EXPORT, $id);
                $content = $this->getTaskBody($delTask).$this->getTaskBody($impTask).$this->getTaskBody($expTask);
                if (!empty($content)) {
                    return esc_html(strtok($content, "\n"));
                }
                break;
            case 'full':
                $impTask = TaskHandler::getScheduledTask(TaskHandler::FULL_IMPORT);
                $content = $this->getTaskBody($impTask);
                if (!empty($content)) {
                    return esc_html(strtok($content, "\n"));
                }
                break;
            default:
                return false;
        }
    }

    private function getTaskBody($task)
    {
        if (empty($task['error_output'])) {
            return '';
        }

        return $task['error_output'];
    }

    private function hasTaskError($type, $fistLine = false)
    {
        $message = __(
            'This %s has a failing task, check the logs of the tasks or ask your system administrator to check out the problem.',
            I18N::DOMAIN
        );
        $message = sprintf($message, $type);
        if ($fistLine) {
            $message = $message.' '.__('With as first line', I18N::DOMAIN);
            $message = "<b>$message:</b><br>$fistLine";
        }
        AdminNotices::showError($message);
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
                <?php
                echo $message; ?>
            </h4>
        </div>
        <?php
    }

    public static function showError($message, $description = '')
    {
        ?>
        <div class="notice notice-error">
            <h4>
                <?php
                echo $message; ?>
            </h4>
            <?php
            echo trim($description); ?>
        </div>
        <?php
    }

    public static function showInfo($message)
    {
        ?>
        <div class="notice notice-info">
            <h4>
                <?php
                echo $message; ?>
            </h4>
        </div>
        <?php
    }

    public static function showSuccess($message)
    {
        ?>
        <div class="notice notice-success">
            <h4>
                <?php
                echo $message; ?>
            </h4>
        </div>
        <?php
    }

    private function lastCronCheck()
    {
        if (WooCommerceOptions::exists(WooCommerceOptions::LAST_SYNC_RUN)) {
            $cronTime = WooCommerceOptions::get(WooCommerceOptions::LAST_SYNC_RUN);
            $timeAgo = $this->dateDiff($cronTime);
            if ($timeAgo) {
                $initialMessage = __(
                    'It seems that its been %s ago since the process tasks cron has been running, Contact your system administrator to solve this problem.',
                    I18N::DOMAIN
                );
                $message = sprintf($initialMessage, $timeAgo);
                AdminNotices::showError($message);
            }
        } else {
            $message = __(
                'Please configure as your cron tab:',
                I18N::DOMAIN
            );

            $path = esc_html(ABSPATH);
            $plugin_dir = esc_html(plugin_dir_path().'/storekeeper-woocommerce-b2c/');
            $description = <<<HTML
<p style="white-space: pre-line;">* * * * * php {$plugin_dir}/scripts/process-tasks.php
* * * * * php {$plugin_dir}/scripts/maybe-wp-cron.php >/dev/null 2>&1
0 3 * * * /usr/local/bin/wp sk sync-issue-check --path={$path} --skip-plugins=wp-optimize
</p>
HTML;

            AdminNotices::showError($message, $description);
        }
    }

    public function dateDiff($date)
    {
        $mydate = date(DATE_RFC2822);

        $datetime1 = date_create($date);
        $datetime2 = date_create($mydate);
        $interval = date_diff($datetime1, $datetime2);

        $min = $interval->format('%i');
        $minInt = (int) $min;
        $hour = $interval->format('%h');
        $mon = $interval->format('%m');
        $day = $interval->format('%d');
        $year = $interval->format('%y');

        if ('00000' == $interval->format('%i%h%d%m%y')) {
            return false;
        } else {
            if ('0000' == $interval->format('%h%d%m%y') && $minInt < 15) {
                return false;
            } else {
                if ('0000' == $interval->format('%h%d%m%y')) {
                    return $min.' '.__('minutes', I18N::DOMAIN);
                } else {
                    if ('000' == $interval->format('%d%m%y')) {
                        return $hour.' '.__('hours', I18N::DOMAIN);
                    } else {
                        if ('00' == $interval->format('%m%y')) {
                            return $day.' '.__('days', I18N::DOMAIN);
                        } else {
                            if ('0' == $interval->format('%y')) {
                                return $mon.' '.__('months', I18N::DOMAIN);
                            } else {
                                return $year.' '.__('years', I18N::DOMAIN);
                            }
                        }
                    }
                }
            }
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
        return isset($_GET['taxonomy']) &&
            'product_cat' === $_GET['taxonomy']
            && isset($_GET['tag_ID']);
    }

    private function isProductTagPage(): bool
    {
        return isset($_GET['taxonomy']) &&
            'product_tag' === $_GET['taxonomy']
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
        return isset($_GET['taxonomy']) &&
            StringFunctions::startsWith($_GET['taxonomy'], 'pa_')
            && isset($_GET['tag_ID']);
    }

    private function getRequestPostId(): int
    {
        $id = (int) $_GET['post'];

        return $id;
    }
}
