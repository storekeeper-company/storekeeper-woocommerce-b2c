<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\I18N;

class PluginConflictChecker
{
    /**
     * Add the conflicts plugins here:
     * Key:             The plugin path, this one is behind the plugin name in the table.
     * Value:           Prefix the version with a `version_compare` operator to declare the version that is conflicting.
     *                  Use "*" to all versions as conflicting
     * Value example:   <5.0.0 will mark version prior to 5.0.0 as conflicting
     * Example:         We want to mark all WooCommerce version before 5 as conflicting, We add the following line.
     *                  'woocommerce/woocommerce.php' => '<5.0.0'.
     */
    public const CONFLICTS = [
        'woocommerce/woocommerce.php' => '<4.1.0',
        'woocommerce-product-search/woocommerce-product-search.php' => '*',
    ];

    public static function getConflictData(): array
    {
        $data = [];

        foreach (get_plugins() as $path => $plugin) {
            $conflicts = false;

            if ('storekeeper-for-woocommerce/storekeeper-woocommerce-b2c.php' === $path) {
                continue;
            }

            $conflictVersion = null;
            if (isset(self::CONFLICTS[$path]) && $conflictVersion = self::CONFLICTS[$path]) {
                if ('*' === $conflictVersion) {
                    $conflicts = true;
                } else {
                    $exec = preg_match(
                        '/^(<|lt|<=|le|>|gt|>=|ge|==|=|eq|!=|<>)([\d\.]+)/',
                        $conflictVersion,
                        $matches
                    );
                    if (0 === $exec || false === $exec) {
                        throw new \RuntimeException("Incorrect conflict version found: $conflictVersion");
                    }

                    [, $operator, $version] = $matches;
                    if (!$operator) {
                        $operator = '=';
                    }

                    $conflicts = version_compare($plugin['Version'], $version, $operator);
                }
            }

            $data[] = [
                'name' => $plugin['Title'].' ('.$path.')',
                'version' => $plugin['Version'],
                'conflictedVersion' => '*' === $conflictVersion ? __('Not supported', I18N::DOMAIN) : $conflictVersion,
                'conflicts' => $conflicts,
            ];
        }

        return $data;
    }

    public static function getPluginsWithConflict(): array
    {
        $pluginsWithConflict = [];

        foreach (get_plugins() as $path => $plugin) {
            $conflicts = false;

            if ('storekeeper-for-woocommerce/storekeeper-woocommerce-b2c.php' === $path) {
                continue;
            }

            $conflictVersion = null;
            if (isset(self::CONFLICTS[$path]) && $conflictVersion = self::CONFLICTS[$path]) {
                if ('*' === $conflictVersion) {
                    $conflicts = true;
                } else {
                    $exec = preg_match(
                        '/^(<|lt|<=|le|>|gt|>=|ge|==|=|eq|!=|<>)(\d\.\d\.\d)/',
                        $conflictVersion,
                        $matches
                    );
                    if (0 === $exec || false === $exec) {
                        throw new \RuntimeException("Incorrect conflict version found: $conflictVersion");
                    }

                    [, $operator, $version] = $matches;
                    if (!$operator) {
                        $operator = '=';
                    }

                    $conflicts = version_compare($plugin['Version'], $version, $operator);
                }
            }

            if ($conflicts) {
                $pluginsWithConflict[] = [
                    'name' => $plugin['Title'].' ('.$path.')',
                    'level' => '*' === $conflictVersion ? 'error' : 'warning',
                    'error' => '*' === $conflictVersion ? __('Not supported', I18N::DOMAIN) : sprintf(
                        __('Plugin version %s is not compatible with the plugin', I18N::DOMAIN),
                        $conflictVersion
                    ),
                ];
            }
        }

        return $pluginsWithConflict;
    }
}
