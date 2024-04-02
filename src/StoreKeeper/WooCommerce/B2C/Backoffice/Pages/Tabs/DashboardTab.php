<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class DashboardTab extends AbstractTab
{
    protected function getStylePaths(): array
    {
        return [
            plugin_dir_url(__FILE__).'/../../../static/dashboard.tab.css',
        ];
    }

    public function render(): void
    {
        $this->renderCheck(
            __('Products & categories synced', I18N::DOMAIN),
            $this->checkProductsAndCategories()
        );

        $this->renderCheck(
            __('Orders & customers synced', I18N::DOMAIN),
            $this->checkOrdersAndCustomers()
        );

        $this->renderCheck(
            __('Promotions & coupons synced', I18N::DOMAIN),
            $this->checkPromotionsAndCoupons()
        );

        $this->renderCheck(
            __('Payments active', I18N::DOMAIN),
            $this->checkPaymentsActive()
        );
    }

    private function renderCheck(string $message, bool $success)
    {
        $message = esc_html($message);
        $level = $success
            ? '<span class="dashicons dashicons-saved dashboard-status circle-success"></span>'
            : '<span class="dashicons dashicons-no-alt dashboard-status circle-danger"></span>';

        echo <<<HTML
<h2 class="dashboard-text">
    $level $message
</h2>
HTML;
    }

    private function checkProductsAndCategories(): bool
    {
        $failedProducts = TaskModel::count(
            [
                'type like "product-%"',
                'status = :status',
            ],
            [
                'status' => TaskHandler::STATUS_FAILED,
            ]
        );
        $failedCategories = TaskModel::count(
            [
                'type like "category-%"',
                'status = :status',
            ],
            [
                'status' => TaskHandler::STATUS_PROCESSING,
            ]
        );

        return 0 === $failedProducts + $failedCategories;
    }

    private function checkOrdersAndCustomers(): bool
    {
        return 0 === TaskModel::count(
            [
                'type like "orders-%"',
                'status = :status',
            ],
            [
                'status' => TaskHandler::STATUS_FAILED,
            ]
        );
    }

    private function checkPromotionsAndCoupons(): bool
    {
        return 0 === TaskModel::count(
            [
                'type like "coupon-code-%"',
                'status = :status',
            ],
            [
                'status' => TaskHandler::STATUS_FAILED,
            ]
        );
    }

    private function checkPaymentsActive(): bool
    {
        return 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes');
    }
}
