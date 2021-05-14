<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use Exception;
use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class PluginConflictCheckerTab extends AbstractTab
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
    const CONFLICTS = [
        'woocommerce/woocommerce.php' => '<4.1.0',
    ];

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $table = new TableRenderer();
        $table->setData($this->getConflictData());
        $table->addColumn(
            __('Name', I18N::DOMAIN),
            'name'
        );
        $table->addColumn(
            __('Version', I18N::DOMAIN),
            'version'
        );
        $table->addColumn(
            __('Conflicts', I18N::DOMAIN),
            'conflicts',
            [$this, 'renderBool']
        );
        $table->render();
    }

    public function renderBool($conflicts, $item)
    {
        $html = '<span class="dashicons dashicons-no text-success"></span>';
        if ($conflicts) {
            $description = esc_html($item['conflictedVersion']) ?? '';
            $html = <<<HTML
<span class="text-danger">
    <span class="dashicons dashicons-warning"></span>
    $description
</span>
HTML;
        }

        echo $html;
    }

    private function getConflictData()
    {
        $data = [];

        foreach (get_plugins() as $path => $plugin) {
            $conflicts = false;

            if ('storekeeper-woocommerce-b2c/storekeeper-woocommerce-b2c.php' === $path) {
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
                        throw new Exception("Incorrect conflict version found: $conflictVersion");
                    }

                    list($full, $operator, $version) = $matches;
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
}
