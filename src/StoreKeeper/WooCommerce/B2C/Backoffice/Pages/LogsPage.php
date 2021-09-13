<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\TaskLogsTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\WebhookLogsTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class LogsPage extends AbstractPage
{
    final public function render(): void
    {
        parent::render();
        $this->renderModal();
        $this->registerVendors();
    }

    private function renderModal(): void
    {
        echo '<div id="dialog-error-message" title="'.esc_html__('Task error details', I18N::DOMAIN).'"></div>';
    }

    public function registerVendors(): void
    {
        // Add jquery ui scripts and styles
        wp_enqueue_style('wp-jquery-ui-dialog');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_script('logsScript', plugin_dir_url(__FILE__).'../static/backoffice.pages.logs.js');
    }

    protected function getTabs(): array
    {
        return [
            new TaskLogsTab(
                __('Tasks', I18N::DOMAIN)
            ),
            new WebhookLogsTab(
                __('Webhooks', I18N::DOMAIN),
                'webhook'
            ),
        ];
    }
}
