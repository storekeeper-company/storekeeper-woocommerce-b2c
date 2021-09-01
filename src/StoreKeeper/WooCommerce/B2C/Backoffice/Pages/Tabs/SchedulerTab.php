<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class SchedulerTab extends AbstractTab
{
    use FormElementTrait;

    public const SAVE_ACTION = 'save-action';
    public const HELP_DISABLE_CRON_LINK = 'https://wordpress.org/support/article/editing-wp-config-php/#disable-cron-and-cron-timeout';
    public const HELP_ALTERNATE_CRON_LINK = 'https://wordpress.org/support/article/editing-wp-config-php/#alternative-cron';

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
        $this->renderRequirements();
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
        echo $this->getFormGroup(
            __('Cron recurrence', I18N::DOMAIN),
            __('Every minute', I18N::DOMAIN),
        );

        $executionStatus = CronRegistrar::getStatusLabel(
            StoreKeeperOptions::get(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS, CronRegistrar::STATUS_UNEXECUTED)
        );
        echo $this->getFormGroup(
            __('Last cron execution status', I18N::DOMAIN),
            $executionStatus
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

        echo $this->getFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        echo $this->getFormEnd();
    }

    public function saveAction(): void
    {
        $cronRunner = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_RUNNER);
        $cronLastStatus = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS);

        $data = [
            $cronRunner => $_POST[$cronRunner],
            $cronLastStatus => CronRegistrar::STATUS_UNEXECUTED,
        ];

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        if (!in_array($data[$cronRunner], CronRegistrar::WP_CRON_RUNNERS, true)) {
            wp_clear_scheduled_hook(CronRegistrar::HOOK_PROCESS_TASK);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderRunner(): void
    {
        $cronRunner = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_RUNNER);

        $cronRunnerValue = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER, CronRegistrar::RUNNER_WPCRON);
        echo $this->getFormGroup(
            __('Cron runner', I18N::DOMAIN),
            $this->getFormSelect(
                $cronRunner,
                CronRegistrar::getCronRunners(),
                $cronRunnerValue
            ).' <br><small>'.__(
                'Select which cron runner will be used. Please use only if knowledgeable.',
                I18N::DOMAIN
            ).'</small>'
        );
    }

    private function renderInstructions(): void
    {
        $runner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER, CronRegistrar::RUNNER_WPCRON);
        $instructions = [];
        if (CronRegistrar::RUNNER_WPCRON_CRONTAB === $runner) {
            $url = site_url('wp-cron.php');
            $instructions = [
                __('Check for admin notices related to cron.', I18N::DOMAIN),
                __('Check if `curl` and `cron` is installed in the website\'s server.', I18N::DOMAIN),
                sprintf(__('Add %s to crontab.', I18N::DOMAIN), "<code>* * * * * curl $url</code>"),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
            ];
        } elseif (CronRegistrar::RUNNER_CRONTAB_API === $runner) {
            $url = rest_url(EndpointLoader::getFullNamespace().'/'.TaskProcessorEndpoint::ROUTE);
            $instructions = [
                __('Check if `curl` and `cron` is installed in the website\'s server.', I18N::DOMAIN),
                sprintf(__('Add %s to crontab.', I18N::DOMAIN), "<code>* * * * * curl $url</code>"),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
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

    private function renderRequirements(): void
    {
        echo '<br>'.$this->getFormHeader(__('Requirements', I18N::DOMAIN));

        $runner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER, CronRegistrar::RUNNER_WPCRON);

        $disableWpCron = false;
        if (defined('DISABLE_WP_CRON')) {
            $disableWpCron = DISABLE_WP_CRON;
        }

        $alternateWpCron = false;
        if (defined('ALTERNATE_WP_CRON')) {
            $alternateWpCron = ALTERNATE_WP_CRON;
        }

        $helpText = __('See documentation', I18N::DOMAIN);
        if (CronRegistrar::RUNNER_WPCRON_CRONTAB === $runner) {
            $hasHelp = !$disableWpCron || $alternateWpCron;
            $data = [
                [
                    'key' => 'DISABLE_WP_CRON',
                    'value' => $disableWpCron ? 'true' : 'false',
                    'validity' => $this->generateValidityContent($disableWpCron),
                    'help' => $this->generateHelpContent($disableWpCron, self::HELP_DISABLE_CRON_LINK, $helpText),
                ],
                [
                    'key' => 'ALTERNATE_WP_CRON',
                    'value' => $alternateWpCron ? 'true' : 'false',
                    'validity' => $this->generateValidityContent(!$alternateWpCron),
                    'help' => $this->generateHelpContent(!$alternateWpCron, self::HELP_ALTERNATE_CRON_LINK, $helpText),
                ],
            ];
        } elseif (CronRegistrar::RUNNER_CRONTAB_API === $runner) {
            $hasHelp = !$disableWpCron;
            $data = [
                [
                    'key' => 'DISABLE_WP_CRON',
                    'value' => $disableWpCron ? 'true' : 'false',
                    'validity' => $this->generateValidityContent($disableWpCron),
                    'help' => $this->generateHelpContent($disableWpCron, self::HELP_DISABLE_CRON_LINK, $helpText),
                ],
            ];
        } else {
            $hasHelp = $disableWpCron || $alternateWpCron;
            $data = [
                [
                    'key' => 'DISABLE_WP_CRON',
                    'value' => $disableWpCron ? 'true' : 'false',
                    'validity' => $this->generateValidityContent(!$disableWpCron),
                    'help' => $this->generateHelpContent(!$disableWpCron, self::HELP_DISABLE_CRON_LINK, $helpText),
                ],
                [
                    'key' => 'ALTERNATE_WP_CRON',
                    'value' => $alternateWpCron ? 'true' : 'false',
                    'validity' => $this->generateValidityContent(!$alternateWpCron),
                    'help' => $this->generateHelpContent(!$alternateWpCron, self::HELP_ALTERNATE_CRON_LINK, $helpText),
                ],
            ];
        }

        $table = new TableRenderer();

        $columns = [
            [
                'title' => __('Key', I18N::DOMAIN),
                'key' => 'key',
            ],
            [
                'title' => __('Value', I18N::DOMAIN),
                'key' => 'value',
            ],
            [
                'title' => __('Validity', I18N::DOMAIN),
                'key' => 'validity',
                'bodyFunction' => [$this, 'renderHtml'],
            ],
        ];

        if ($hasHelp) {
            $columns[] = [
                'title' => __('Help', I18N::DOMAIN),
                'key' => 'help',
                'bodyFunction' => [$this, 'renderHtml'],
            ];
        }

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

    private function generateValidityContent(bool $isValid): string
    {
        return $isValid ? '<span class="dashicons dashicons-yes-alt text-success"></span>' : '<span class="dashicons dashicons-warning text-warning"></span>';
    }

    private function generateHelpContent(bool $isValid, string $link, string $text): ?string
    {
        return $isValid ? '<i>N/A</i>' : "<a href='{$link}'>{$text}</a>";
    }
}
