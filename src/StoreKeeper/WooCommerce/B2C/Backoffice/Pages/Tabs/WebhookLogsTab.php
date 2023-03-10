<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogsTab extends AbstractLogsTab
{
    public function render(): void
    {
        $this->items = $this->fetchData(WebhookLogModel::class);
        $this->count = WebhookLogModel::count();

        $this->renderPagination();

        $this->renderTable(
            [
                [
                    'title' => __('Request route', I18N::DOMAIN),
                    'key' => 'route',
                ],
                [
                    'title' => __('Request body', I18N::DOMAIN),
                    'key' => 'body',
                ],
                [
                    'title' => __('Request method', I18N::DOMAIN),
                    'key' => 'method',
                ],
                [
                    'title' => __('Request action', I18N::DOMAIN),
                    'key' => 'action',
                ],
                [
                    'title' => __('Response code', I18N::DOMAIN),
                    'key' => 'response_code',
                ],
                [
                    'title' => __('Date', I18N::DOMAIN),
                    'key' => WebhookLogModel::FIELD_DATE_CREATED,
                    'headerFunction' => [$this, 'renderDateCreated'],
                    'bodyFunction' => [$this, 'renderDate'],
                ],
            ]
        );

        $this->renderPagination();
    }
}
