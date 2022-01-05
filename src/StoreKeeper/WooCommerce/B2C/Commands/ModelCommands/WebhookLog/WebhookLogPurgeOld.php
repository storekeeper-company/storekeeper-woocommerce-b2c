<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\AbstractCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Singletons\QueryFactorySingleton;

class WebhookLogPurgeOld extends AbstractCommand
{
    public static function getShortDescription(): string
    {
        return __('Purge old webhook log types.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Purge all old webhook log types', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    public static function getCommandName(): string
    {
        return 'webhook-log purge-old';
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        $select = QueryFactorySingleton::getInstance()
            ->newSelect()
            ->from($wpdb->prefix.'posts')
            ->cols(['ID'])
            ->where('post_type = :type')
            ->bindValue('type', 'GO-WC-webhook-logs');

        $query = QueryFactorySingleton::getInstance()
            ->prepare($select);

        $results = $wpdb->get_results($query, ARRAY_N);
        $ids = array_map('current', $results);

        foreach ($ids as $id) {
            wp_delete_post($id, true);
        }
    }
}
