<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Helpers\PluginConflictChecker;
use StoreKeeper\WooCommerce\B2C\I18N;

class PluginConflictCheckerTab extends AbstractTab
{
    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $table = new TableRenderer();
        $table->setData(PluginConflictChecker::getConflictData());
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
}
