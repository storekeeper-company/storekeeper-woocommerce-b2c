<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Blocksy\Plugin;
use StoreKeeper\WooCommerce\B2C\Commands\ImportStartingSite\BlocksyDemoInstall;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class ImportStartingSiteCommand extends AbstractCommand
{

    public static function getCommandName(): string
    {
        return 'import-starting-site';
    }

    public static function getShortDescription(): string
    {
        return __('Import initial storekeeper site.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        $examples = static::generateExamples([
            'wp sk import-starting-site ./storekeeper-fashion.json',
        ]);

        return $examples;
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'positional',
                'name' => 'export-file',
                'description' => __('Site export from blocksy.', I18N::DOMAIN),
                'optional' => false,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        if( ! PluginStatus::isBlocksyEnabled() ){
            throw new \Exception("Blocksy companion is not enabled");
        }

        $blocksy = Plugin::instance();
        if( ! $blocksy->check_if_blocksy_is_activated() ){
            throw new \Exception("Blocksy theme is not activated");
        }

        $demo = new BlocksyDemoInstall();
        var_dump($demo); // todo
    }
}
