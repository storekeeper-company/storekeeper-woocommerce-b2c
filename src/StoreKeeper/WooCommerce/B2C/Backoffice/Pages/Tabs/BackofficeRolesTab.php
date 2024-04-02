<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Helpers\SsoHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class BackofficeRolesTab extends AbstractTab
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
        $url = $this->getActionUrl(self::SAVE_ACTION);
        $this->renderFormStart('post', $url);

        $this->renderRoleSetting(SsoHelper::FALLBACK_SSO_ROLE_NAME, SsoHelper::DISABLED_SSO_ROLE);

        $this->renderRoleSetting(SsoHelper::ADMIN_SSO_ROLE_NAME);

        $this->renderRoleSetting(SsoHelper::MANAGER_SSO_ROLE_NAME);

        $this->renderRoleSetting(SsoHelper::USER_SSO_ROLE_NAME);

        $this->renderFormGroup(
            __('Apply to all', I18N::DOMAIN),
            $this->getFormCheckbox('apply-to-existing')
        );

        $this->renderFormActionGroup(
            $this->getFormButton(
                __('Save settings', I18N::DOMAIN),
                'button-primary'
            )
        );

        $this->renderFormEnd();
    }

    public function saveAction()
    {
        $applyExisting = 'on' === sanitize_key($_POST['apply-to-existing']);
        $roles = [
            SsoHelper::FALLBACK_SSO_ROLE_NAME,
            SsoHelper::ADMIN_SSO_ROLE_NAME,
            SsoHelper::MANAGER_SSO_ROLE_NAME,
            SsoHelper::USER_SSO_ROLE_NAME,
        ];

        foreach ($roles as $role) {
            $name = SsoHelper::formatRoleOptionKey($role);
            if (!empty($_POST[$name])) {
                $value = sanitize_key($_POST[$name]);
            } else {
                $value = null;
            }
            update_option($name, $value);

            if ($applyExisting) {
                $this->updateExistingUser($role, $value);
            }
        }

        wp_redirect(remove_query_arg('action'));
    }

    private function updateExistingUser($storekeeper_role, $newRole)
    {
        $users = get_users(
            [
                'meta_query' => [
                    [
                        'key' => 'storekeeper_role',
                        'compare' => '=',
                        'value' => $storekeeper_role,
                    ],
                ],
            ]
        );

        foreach ($users as $user) {
            WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_user(
                    [
                        'ID' => $user->ID,
                        'role' => $newRole,
                    ]
                )
            );
        }
    }

    private function renderRoleSetting($backofficeRole, $defaultRole = SsoHelper::DEFAULT_SSO_FOR_KNOWN_ROLES): void
    {
        $options = $this->getRoles();
        $name = SsoHelper::formatRoleOptionKey($backofficeRole);
        $label = $this->getRoleLabel($backofficeRole);
        $value = get_option($name, $defaultRole);

        $this->renderFormGroup(
            $label,
            $this->getFormSelect(
                $name,
                $options,
                $value
            )
        );
    }

    private function getRoles()
    {
        global $wp_roles;

        $all_roles = $wp_roles->roles;
        $editable_roles = apply_filters('editable_roles', $all_roles);

        $roles = [];
        foreach ($editable_roles as $role => $data) {
            $roles[$role] = $data['name'];
        }
        $roles[SsoHelper::DISABLED_SSO_ROLE] = __('Disable SSO', I18N::DOMAIN);

        return $roles;
    }

    private function getRoleLabel(string $backofficeRole): string
    {
        if (SsoHelper::FALLBACK_SSO_ROLE_NAME === $backofficeRole) {
            return __('Other backoffice roles', I18N::DOMAIN);
        }

        return sprintf(
            __('Backoffice %s role', I18N::DOMAIN),
            $backofficeRole
        );
    }
}
