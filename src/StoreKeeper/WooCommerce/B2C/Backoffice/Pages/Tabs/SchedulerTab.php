<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
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
    }

    private function renderCronConfiguration(): void
    {
        $url = $this->getActionUrl(self::SAVE_ACTION);
        echo $this->getFormstart('post', $url);

        echo $this->getFormHeader(__('Cron configuration', I18N::DOMAIN));

        $this->renderEnabler();

        $this->renderCustomInterval();

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
        $isEnabled = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_ENABLED);
        $customInterval = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_CUSTOM_INTERVAL);

        $data = [
            $isEnabled => 'on' === sanitize_key($_POST[$isEnabled]) ? 'yes' : 'no',
            $customInterval => (int) $_POST[$customInterval],
        ];

        foreach ($data as $key => $value) {
            update_option($key, $value);
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function renderEnabler(): void
    {
        $isEnabled = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_ENABLED);
        echo $this->getFormGroup(
            __('Enable task processing cron', I18N::DOMAIN),
            $this->getFormCheckbox(
                $isEnabled,
                'yes' === StoreKeeperOptions::get($isEnabled)
            ).' '.__(
                'When checked, cron for processing of tasks will be enabled.',
                I18N::DOMAIN
            )
        );
    }

    private function renderCustomInterval(): void
    {
        $customInterval = StoreKeeperOptions::getConstant(StoreKeeperOptions::CRON_CUSTOM_INTERVAL);
        echo $this->getFormGroup(
            __('Cron recurrence', I18N::DOMAIN),
            $this->getFormInput(
                $customInterval,
                '',
                StorekeeperOptions::get($customInterval, 600),
                '',
                'number'
            ).'<br><small>'.__(
                'Set desired interval of cron in seconds. e.g 600 seconds = 10 minutes.',
                I18N::DOMAIN
            ).'</small>'
        );
    }
}
