<?php

require_once __DIR__.'/ProcessHelpers.php';

class WordPressHelpers extends ProcessHelpers
{
    public static function setupAndRequireWordpress()
    {
        // We found out that sometimes the error is not being thrown on the console. Thus we did it this way.
        register_shutdown_function(
            function () {
                $last_error = error_get_last();
                $type = &$last_error['type'];
                if (in_array($type, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    echo "----------------------------------------------------------------\n";
                    print_r($last_error);
                    echo "----------------------------------------------------------------\n";
                }
            }
        );

        // To make sure even big products sync
        ini_set('memory_limit', '512M');

        // Tricking WordPress into thinking its being runned from wp-admin Else it will decline some functions from working
        $_SERVER['PHP_SELF'] = '/wp-admin/index.php';
        // Some plugins use this one without checking if the index exists, Thus I set it to localhost
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($GLOBALS['current_screen']);
        define('WP_ADMIN', true);

        // Load Wordpress
        require_once __DIR__.'/../../../../wp-load.php';

        // To see the logs, instead of waiting untill the end
        for ($i = ob_get_level(); $i > 0; --$i) {
            ob_end_clean();
        }
    }

    public static function isStoreKeeperConnected()
    {
        return StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions::isConnected();
    }

    public static function setTaskPostStatusToNew($task_post_id)
    {
        if (null !== get_post($task_post_id)) {
            update_post_meta($task_post_id, 'status', StoreKeeper\WooCommerce\B2C\Tools\TaskHandler::STATUS_NEW);

            return true;
        }

        return false;
    }

    public static function setTaskPostStatusToProcessing($task_post_id)
    {
        if (null !== get_post($task_post_id)) {
            update_post_meta(
                $task_post_id,
                'status',
                StoreKeeper\WooCommerce\B2C\Tools\TaskHandler::STATUS_PROCESSING
            );

            return true;
        }

        return false;
    }

    public static function setTaskPostStatusToFailed($task_post_id)
    {
        if (null !== get_post($task_post_id)) {
            update_post_meta($task_post_id, 'status', StoreKeeper\WooCommerce\B2C\Tools\TaskHandler::STATUS_FAILED);

            return true;
        }

        return false;
    }
}
