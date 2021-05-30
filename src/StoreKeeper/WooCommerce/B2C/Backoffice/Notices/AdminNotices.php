<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Notices;

use Adbar\Dot;
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
        $G = new Dot($_GET);

        if ('post-new.php' === $pagenow && 'product' === $G->get('post_type')) {
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
        $G = new Dot($_GET);

        if ($G->has('post') && 'edit' === $G->get('action') && 'product' === $post_type) {
            $goid = get_post_meta($G->get('post'), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('product', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductCategoryCheck()
    {
        $G = new Dot($_GET);

        if ('product_cat' === $G->get('taxonomy') && $G->has('tag_ID')) {
            $goid = get_term_meta($G->get('tag_ID'), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('category', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductTagCheck()
    {
        $G = new Dot($_GET);

        if ('product_tag' === $G->get('taxonomy') && $G->has('tag_ID')) {
            $goid = get_term_meta($G->get('tag_ID'), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('tag', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductAttributeCheck()
    {
        $G = new Dot($_GET);

        if ('product' === $G->get('post_type') && 'product_attributes' === $G->get('page') && $G->has('edit')) {
            $goid = WooCommerceAttributeMetadata::getMetadata($G->get('edit'), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('attribute', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperProductAttributeTermCheck()
    {
        $G = new Dot($_GET);

        if (StringFunctions::startsWith($G->get('taxonomy'), 'pa_') && $G->has('tag_ID')) {
            $goid = get_term_meta($G->get('tag_ID'), 'storekeeper_id', true);
            if ($goid) {
                $this->isSyncedFromBackend(__('attribute term', I18N::DOMAIN));
            }
        }
    }

    private function StoreKeeperOrderCheck()
    {
        global $post_type;
        $G = new Dot($_GET);

        if ($G->has('post') && 'edit' === $G->get('action') && 'shop_order' === $post_type) {
            $id = $G->get('post');
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
        $G = new Dot($_GET);

        if ('product_cat' === $G->get('taxonomy') && $G->has('tag_ID')) {
            if ($fistLine = $this->taskHasError('category', $G->get('tag_ID'))) {
                $this->hasTaskError(__('category', I18N::DOMAIN), $fistLine);
            }
        }

        if ('product_tag' === $G->get('taxonomy') && $G->has('tag_ID')) {
            if ($fistLine = $this->taskHasError('tag', $G->get('tag_ID'))) {
                $this->hasTaskError(__('tag', I18N::DOMAIN), $fistLine);
            }
        }

        if ($G->has('post') && 'edit' === $G->get('action') && 'product' === $post_type) {
            if ($fistLine = $this->taskHasError('product', $G->get('post'))) {
                $this->hasTaskError(__('product', I18N::DOMAIN), $fistLine);
            }
        }

        if ($G->has('post') && 'edit' === $G->get('action') && 'shop_order' === $post_type) {
            if ($fistLine = $this->taskHasError('order', $G->get('post'))) {
                $this->hasTaskError(__('order', I18N::DOMAIN), $fistLine);
            }
        }

        if ('admin.php' === $pagenow && 'wc-settings' === $G->get('page') && 'storekeeper-woocommerce-b2c' === $G->get(
                'tab'
            )) {
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
}
