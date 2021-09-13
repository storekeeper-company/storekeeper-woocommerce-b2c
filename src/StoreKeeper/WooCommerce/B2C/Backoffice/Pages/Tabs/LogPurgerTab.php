<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\OverlayRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class LogPurgerTab extends AbstractTab
{
    use FormElementTrait;

    const PURGE_TASKS_ACTION = 'purge-tasks';
    const PURGE_WEBHOOKS_ACTION = 'purge-webhooks';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::PURGE_TASKS_ACTION, [$this, 'purgeTasks']);
        $this->addAction(self::PURGE_WEBHOOKS_ACTION, [$this, 'purgeWebhooks']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderTasks();

        $this->renderWebhooks();
    }

    private function renderTasks()
    {
        echo $this->getFormStart();

        echo $this->getFormHeader(__('Tasks', I18N::DOMAIN));

        echo $this->getRequestHiddenInputs();

        echo $this->getFormHiddenInput('action', self::PURGE_TASKS_ACTION);

        echo $this->getFormGroup(
            __('Total tasks', I18N::DOMAIN),
            esc_html(TaskModel::count())
        );

        echo $this->getFormGroup(
            __('Successful tasks', I18N::DOMAIN),
            esc_html(TaskModel::countSuccessfulTasks())
        );

        echo $this->getFormNote(
            sprintf(
                __(
                    '"%s" only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.',
                    I18N::DOMAIN
                ),
                __('Purge successful tasks', I18N::DOMAIN)
            ),
            'text-information'
        );

        echo $this->getFormActionGroup(
            $this->getFormButton(
                __('Purge successful tasks', I18N::DOMAIN)
            )
        );

        echo $this->getFormEnd();
    }

    private function renderWebhooks()
    {
        echo $this->getFormStart();

        echo $this->getFormHeader(__('Webhooks', I18N::DOMAIN));

        echo $this->getRequestHiddenInputs();

        echo $this->getFormHiddenInput('action', self::PURGE_WEBHOOKS_ACTION);

        echo $this->getFormGroup(
            __('Total webhooks', I18N::DOMAIN),
            esc_html(WebhookLogModel::count())
        );

        echo $this->getFormNote(
            sprintf(
                __(
                    '"%s" only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.',
                    I18N::DOMAIN
                ),
                __('Purge webhooks', I18N::DOMAIN)
            ),
            'text-information'
        );

        echo $this->getFormActionGroup(
            $this->getFormButton(
                __('Purge webhooks', I18N::DOMAIN)
            )
        );

        echo $this->getFormEnd();
    }

    public function purgeTasks()
    {
        $overlay = new OverlayRenderer();
        $overlay->start(
            __('Started purging tasks, please wait.', I18N::DOMAIN)
        );

        IniHelper::setIni(
            'max_execution_time',
            60 * 30, // Time in minutes
            [$overlay, 'renderMessage']
        );

        $purged = TaskModel::purge();

        $overlay->renderMessage(
            sprintf(
                __('Purged %s items', I18N::DOMAIN),
                $purged
            )
        );

        $url = remove_query_arg('action');
        $overlay->endWithRedirect($url);
    }

    public function purgeWebhooks()
    {
        $overlay = new OverlayRenderer();
        $overlay->start(
            __('Started purging webhooks, please wait.', I18N::DOMAIN)
        );

        IniHelper::setIni(
            'max_execution_time',
            60 * 30, // Time in minutes
            [$overlay, 'renderMessage']
        );

        $purged = WebhookLogModel::purge();

        $overlay->renderMessage(
            sprintf(
                __('Purged %s items', I18N::DOMAIN),
                $purged
            )
        );

        $url = remove_query_arg('action');
        $overlay->endWithRedirect($url);
    }
}
