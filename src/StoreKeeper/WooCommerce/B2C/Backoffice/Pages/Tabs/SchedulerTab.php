<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

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

        $this->renderHookName();
        $this->renderCustomInterval();

        echo $this->getFormEnd();
    }

    private function renderAdvancedConfiguration(): void
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        echo $this->getFormstart('post', $url);

        echo $this->getFormHeader(__('Advanced configuration', I18N::DOMAIN));

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

        $data = [
            $cronRunner => $_POST[$cronRunner],
        ];

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        if (!in_array($data[$cronRunner], CronRegistrar::WP_CRON_RUNNERS, true)) {
            wp_clear_scheduled_hook(CronRegistrar::HOOK_PROCESS_TASK);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderCustomInterval(): void
    {
        echo $this->getFormGroup(
            __('Cron recurrence', I18N::DOMAIN),
            __('Every minute', I18N::DOMAIN),
        );
    }

    private function renderHookName(): void
    {
        echo $this->getFormGroup(
            __('Hook name', I18N::DOMAIN),
            CronRegistrar::HOOK_PROCESS_TASK,
        );
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
        $instructionsHTML = '';
        if (CronRegistrar::RUNNER_WPCRON_CRONTAB === $runner) {
            $url = site_url('wp-cron.php');
            $instructions = [
                __('Make sure `DISABLE_WP_CRON` AND `ALTERNATE_WP_CRON` is set to false.', I18N::DOMAIN),
                __('Check for admin notices related to cron.', I18N::DOMAIN),
                sprintf(__('Add `*/1 * * * * curl %s` to crontab', I18N::DOMAIN), $url),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
            ];
        } elseif (CronRegistrar::RUNNER_CRONTAB_API === $runner) {
            $url = rest_url(EndpointLoader::getFullNamespace().'/'.TaskProcessorEndpoint::ROUTE);
            $instructions = [
                __('Make sure `DISABLE_WP_CRON` is set to true.', I18N::DOMAIN),
                __('Check if `curl` and `cron` is installed in the website\'s server.', I18N::DOMAIN),
                sprintf(__('Add `*/1 * * * * curl %s` to crontab', I18N::DOMAIN), $url),
                __('Upon changing runner, please make sure to remove the cron above from crontab.', I18N::DOMAIN),
            ];
        } else {
            $instructions = [
                __('Make sure `DISABLE_WP_CRON` AND `ALTERNATE_WP_CRON` is set to false.', I18N::DOMAIN),
                __('Check for admin notices related to cron.', I18N::DOMAIN),
            ];
        }

        foreach ($instructions as $key => $instruction) {
            $instructionsHTML .= '<p style="white-space: pre-line;">'.($key + 1).'. '.$instruction.'</p>';
        }

        echo $this->getFormGroup(
            __('Instructions', I18N::DOMAIN),
            $instructionsHTML
        );
    }
}
