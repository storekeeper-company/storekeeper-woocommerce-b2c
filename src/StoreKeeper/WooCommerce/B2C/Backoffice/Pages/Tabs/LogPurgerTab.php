<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\OverlayRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class LogPurgerTab extends AbstractTab
{
    use FormElementTrait;

    public const PURGE_TASKS_ACTION = 'purge-tasks';
    public const PURGE_WEBHOOKS_ACTION = 'purge-webhooks';

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
        $this->renderSuccess();
        $this->renderTasks();

        $this->renderWebhooks();
    }

    private function renderSuccess()
    {
        if (array_key_exists('success-message', $_REQUEST)) {
            $message = sanitize_text_field($_REQUEST['success-message']);
            AdminNotices::showSuccess($message);
        }
    }

    private function renderTasks()
    {
        $this->renderFormStart();

        $this->renderFormHeader(__('Tasks', I18N::DOMAIN));

        $this->renderRequestHiddenInputs();

        $this->renderFormHiddenInput('action', self::PURGE_TASKS_ACTION);

        $this->renderFormGroup(
            __('Total tasks', I18N::DOMAIN),
            esc_html(TaskModel::count())
        );

        $this->renderFormGroup(
            __('Successful tasks', I18N::DOMAIN),
            esc_html(TaskModel::countSuccessfulTasks())
        );

        $this->renderFormNote(
            sprintf(
                __(
                    '"%s" only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.',
                    I18N::DOMAIN
                ),
                __('Purge successful tasks', I18N::DOMAIN)
            ),
            'text-information'
        );

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Purge successful tasks', I18N::DOMAIN)
            )
        );

        $this->renderFormEnd();
    }

    private function renderWebhooks()
    {
        $this->renderFormStart();

        $this->renderFormHeader(__('Webhooks', I18N::DOMAIN));

        $this->renderRequestHiddenInputs();

        $this->renderFormHiddenInput('action', self::PURGE_WEBHOOKS_ACTION);

        $this->renderFormGroup(
            __('Total webhooks', I18N::DOMAIN),
            esc_html(WebhookLogModel::count())
        );

        $this->renderFormNote(
            sprintf(
                __(
                    '"%s" only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.',
                    I18N::DOMAIN
                ),
                __('Purge webhooks', I18N::DOMAIN)
            ),
            'text-information'
        );

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Purge webhooks', I18N::DOMAIN)
            )
        );

        $this->renderFormEnd();
    }

    public function purgeTasks(): void
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
        $url = add_query_arg([
            'success-message' => sprintf(
                __('%s tasks has been successfully purged', I18N::DOMAIN),
                $purged
            ),
        ], $url);
        $overlay->endWithRedirect($url);
    }

    public function purgeWebhooks(): void
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
        $url = add_query_arg([
            'success-message' => sprintf(
                __('%s webhooks has been successfully purged', I18N::DOMAIN),
                $purged
            ),
        ], $url);
        $overlay->endWithRedirect($url);
    }
}
