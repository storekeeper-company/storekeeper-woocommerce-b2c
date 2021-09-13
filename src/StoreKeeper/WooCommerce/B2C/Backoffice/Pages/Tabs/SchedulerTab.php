<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Commands\ScheduledProcessor;
use StoreKeeper\WooCommerce\B2C\Commands\WpCliCommandRunner;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskRateCalculator;

class SchedulerTab extends AbstractTab
{
    use FormElementTrait;

    public const SAVE_ACTION = 'save-action';
    public const DOCS_WPCRON_TASK_SCHEDULER_LINK = 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler';
    public const DOCS_WPCLI_LINK = 'https://wp-cli.org/#installing';

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::SAVE_ACTION, [$this, 'saveAction']);
    }

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderCronConfiguration();
        $this->renderAdvancedConfiguration();
    }

    private function renderCronConfiguration(): void
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        echo $this->getFormstart('post', $url);

        echo $this->getFormHeader(__('Cron configuration', I18N::DOMAIN));

        echo $this->getFormGroup(
            __('Hook name', I18N::DOMAIN),
            CronRegistrar::HOOK_PROCESS_TASK,
        );

        $executionStatus = CronRegistrar::getStatusLabel(
            CronOptions::get(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED)
        );
        echo $this->getFormGroup(
            __('Last cron execution status', I18N::DOMAIN),
            esc_html($executionStatus)
        );

        $now = current_time('mysql', 1);
        $calculator = new TaskRateCalculator($now);
        $incomingRate = $calculator->countIncoming();
        $processedRate = $calculator->calculateProcessed();
        echo $this->getFormGroup(
            __('Tasks in queue', I18N::DOMAIN),
            TaskModel::count(['status = :status'], ['status' => TaskHandler::STATUS_NEW]).
            sprintf(
                __(
                    ' (new: %s p/h, processed: %s p/h)',
                    I18N::DOMAIN
                ),
                $incomingRate,
                $processedRate
            )
        );

        echo $this->getFormEnd();
    }

    private function renderAdvancedConfiguration(): void
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        echo $this->getFormstart('post', $url);

        echo '<br>'.$this->getFormHeader(__('Advanced configuration', I18N::DOMAIN));

        $this->renderRunner();
        $this->renderInstructions();
        $this->renderStatistics();

        echo $this->getFormEnd();
    }

    public function saveAction(): void
    {
        $cronRunner = CronOptions::getConstant(CronOptions::RUNNER);
        $cronLastStatus = CronOptions::getConstant(CronOptions::LAST_EXECUTION_STATUS);

        $data = [
            $cronRunner => sanitize_key($_POST[$cronRunner]),
            $cronLastStatus => CronRegistrar::STATUS_UNEXECUTED,
        ];

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        CronOptions::resetLastExecutionData();

        if (CronRegistrar::RUNNER_WPCRON !== $data[$cronRunner]) {
            wp_clear_scheduled_hook(CronRegistrar::HOOK_PROCESS_TASK);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderRunner(): void
    {
        $cronRunner = CronOptions::getConstant(CronOptions::RUNNER);

        $cronRunnerValue = CronOptions::get(CronOptions::RUNNER, CronRegistrar::RUNNER_WPCRON);
        echo $this->getFormGroup(
            __('Cron runner', I18N::DOMAIN),
            $this->getFormSelect(
                $cronRunner,
                CronRegistrar::getCronRunners(),
                $cronRunnerValue
            ).' '.$this->getFormButton(
                __('Apply', I18N::DOMAIN),
                'button-primary'
            ).'<br><small>'.__(
                'Select which cron runner will be used. Please use only if knowledgeable.',
                I18N::DOMAIN
            ).'</small>'
        );
    }

    private function renderInstructions(): void
    {
        $documentationText = __('See documentation', I18N::DOMAIN);
        $runner = CronOptions::get(CronOptions::RUNNER, CronRegistrar::RUNNER_WPCRON);
        if (CronRegistrar::RUNNER_CRONTAB_CLI === $runner) {
            $pluginPath = ABSPATH;
            $commandName = ScheduledProcessor::getCommandName();
            $commandPrefix = WpCliCommandRunner::command_prefix;
            $instructions = [
                __('Check if `wp-cli` is installed in the website\'s server.', I18N::DOMAIN).
                " <a target='_blank' href='".self::DOCS_WPCLI_LINK."'>{$documentationText}</a>",
                sprintf(__('Add %s to crontab.', I18N::DOMAIN), "<code>* * * * * wp {$commandPrefix} {$commandName} --path={$pluginPath}</code>"),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
            ];
        } elseif (CronRegistrar::RUNNER_CRONTAB_API === $runner) {
            $url = rest_url(EndpointLoader::getFullNamespace().'/'.TaskProcessorEndpoint::ROUTE);
            $instructions = [
                __('Check if `curl` and `cron` is installed in the website\'s server.', I18N::DOMAIN),
                sprintf(__('Add %s to crontab.', I18N::DOMAIN), "<code>* * * * * curl $url</code>"),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
            ];
        } else {
            $instructions = [
                sprintf(
                    __('You can improve Wordpress Cron performance by using System Task Scheduler. %s', I18N::DOMAIN),
                    "<a target='_blank' href='".self::DOCS_WPCRON_TASK_SCHEDULER_LINK."'>{$documentationText}</a>"
                ),
            ];
        }

        if (!empty($instructions)) {
            $instructionsHTML = '';
            foreach ($instructions as $key => $instruction) {
                $instructionsHTML .= '<p style="white-space: pre-line;">'.($key + 1).'. '.$instruction.'</p>';
            }

            echo $this->getFormGroup(
                __('Instructions', I18N::DOMAIN),
                $instructionsHTML
            );
        }
    }

    private function renderStatistics(): void
    {
        echo $this->getFormHeader(__('Statistics', I18N::DOMAIN));

        $data = $this->generateCommonStatistics();

        $table = new TableRenderer();

        $columns = [
            [
                'title' => __('Description', I18N::DOMAIN),
                'key' => 'description',
            ],
            [
                'title' => __('Value', I18N::DOMAIN),
                'key' => 'value',
            ],
            [
                'title' => __('Status', I18N::DOMAIN),
                'key' => 'status',
                'bodyFunction' => [$this, 'renderHtml'],
            ],
        ];

        foreach ($columns as $column) {
            $table->addColumn(
                $column['title'],
                $column['key'],
                $column['bodyFunction'] ?? null,
            );
        }

        $table->setData($data);
        $table->render();
    }

    public function renderHtml(?string $content): void
    {
        if (!is_null($content)) {
            echo <<<HTML
        $content
HTML;
        }
    }

    private function generateCommonStatistics(array $data = []): array
    {
        $preExecutionDateTime = CronOptions::get(CronOptions::LAST_PRE_EXECUTION_DATE);
        $hasProcessed = CronOptions::get(CronOptions::LAST_EXECUTION_HAS_PROCESSED, CronOptions::HAS_PROCESSED_WAITING);
        $postExecutionStatus = CronOptions::get(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED);
        $postExecutionError = CronOptions::get(CronOptions::LAST_POST_EXECUTION_ERROR);

        $preExecutionValue = '-';
        $isInactive = false;
        if (!is_null($preExecutionDateTime)) {
            $preExecutionValue = wp_date('Y-m-d H:i:s', strtotime($preExecutionDateTime));
            $inactiveTime = DateTimeHelper::dateDiff($preExecutionDateTime, 5);
            if ($inactiveTime) {
                $isInactive = true;
                $preExecutionValue .= sprintf(__(' (Last called %s ago)', I18N::DOMAIN), $inactiveTime);
            }
        }

        $data[] = [
            'description' => __('Pre-execution date and time', I18N::DOMAIN),
            'value' => $preExecutionValue,
            'status' => $this->generateStatusContent(!is_null($preExecutionDateTime) && !$isInactive),
        ];

        $data[] = [
            'description' => __('Tasks were processed', I18N::DOMAIN),
            'value' => CronOptions::getHasProcessedLabel($hasProcessed),
            'status' => $this->generateStatusContent(CronOptions::HAS_PROCESSED_YES === $hasProcessed),
        ];

        $lastSuccessSyncDate = WooCommerceOptions::get(WooCommerceOptions::SUCCESS_SYNC_RUN);
        if (CronOptions::HAS_PROCESSED_WAITING !== $hasProcessed) {
            $data[] = [
                'description' => __('Last processed task date and time', I18N::DOMAIN),
                'value' => wp_date('Y-m-d H:i:s', strtotime($lastSuccessSyncDate)),
                'status' => $this->generateStatusContent(true),
            ];
        }

        $data[] = [
            'description' => __('Post-execution status', I18N::DOMAIN),
            'value' => CronRegistrar::getStatusLabel($postExecutionStatus),
            'status' => $this->generateStatusContent(CronRegistrar::STATUS_SUCCESS === $postExecutionStatus),
        ];

        $data[] = [
            'description' => __('Post-execution error', I18N::DOMAIN),
            'value' => !is_null($postExecutionError) ? $postExecutionError : '-',
            'status' => $this->generateStatusContent(is_null($postExecutionError)),
        ];

        $invalidRunners = CronOptions::getInvalidRunners();
        $data[] = [
            'description' => __('Running invalid cron in last 5 minutes', I18N::DOMAIN),
            'value' => !empty($invalidRunners) ? implode(', ', $invalidRunners) : __('None'),
            'status' => $this->generateStatusContent(empty($invalidRunners)),
        ];

        return $data;
    }

    private function generateStatusContent(bool $isValid): string
    {
        return $isValid ? '<span class="dashicons dashicons-yes-alt text-success"></span>' : '<span class="dashicons dashicons-warning text-warning"></span>';
    }
}
